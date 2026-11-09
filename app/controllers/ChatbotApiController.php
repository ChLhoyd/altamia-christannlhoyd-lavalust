<?php

/**
 * School chatbot for students, teachers and admins.
 *
 *   POST /api/chatbot/ask   { message: "send me a summary of Jamie's absences" }
 *
 * The NLP step (ChatbotNlp) works out what was asked; this controller
 * decides what the signed-in role is ALLOWED to see:
 *   student : only their own attendance and schedule
 *   teacher : only students enrolled in their own classes
 *   admin   : everything
 */
class ChatbotApiController extends Controller
{
    private const MAX_ROWS = 20;

    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->library('api');
        $this->call->model('UserModel');
        $this->call->model('AttendanceModel');
        $this->call->model('ChatbotNlp');
        $this->call->model('ChatbotModel');
    }

    public function ask()
    {
        $this->api->require_method('POST');

        $user = $this->authenticatedUser();

        if (!$user) {
            return;
        }

        $this->api->rate_limit('chatbot_' . (int) $user['user_id'], 60, 60);

        $message = trim((string) ($this->api->body()['message'] ?? ''));

        if ($message === '' || (function_exists('mb_strlen') ? mb_strlen($message) : strlen($message)) > 300) {
            $this->api->respond_error('Type a question (up to 300 characters).', 422);
            return;
        }

        $this->api->respond([
            'success' => true,
            'reply' => $this->answer($user, $message),
        ]);
    }

    /** Public so it can be unit tested. */
    public function answer(array $user, string $message): array
    {
        $role = strtolower((string) ($user['role'] ?? ''));
        $today = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));

        $f = $this->ChatbotNlp->parse($message, $today);

        // What this role may see.
        $classes = $this->classesFor($user, $role);
        $classIds = array_map('intval', array_column($classes, 'class_id'));
        $people = $this->peopleFor($user, $role, $classIds);

        $matches = $this->ChatbotNlp->matchPeople($f['text'], $people);
        $classMatches = $this->ChatbotNlp->matchClasses($f['text'], $classes);

        // A named student the role is NOT allowed to see.
        if (!$matches && $role !== 'admin') {
            $hidden = $this->ChatbotNlp->matchPeople($f['text'], $this->ChatbotModel->all_students());

            if ($hidden) {
                return $this->reply(
                    $role === 'student'
                        ? 'You can only view your own attendance. Try "my attendance summary".'
                        : 'That student is not in your classes, so I cannot show their records.',
                    null,
                    $this->suggestions($role, $people)
                );
            }
        }

        $intent = $this->ChatbotNlp->intent($f, (bool) $matches, (bool) $classMatches, $role);

        switch ($intent) {
            case 'greeting':
                return $this->reply('Hello ' . trim((string) ($user['first_name'] ?? '')) . '! I can answer questions about attendance and schedules. What would you like to know?', null, $this->suggestions($role, $people));

            case 'thanks':
                return $this->reply('You\'re welcome! Ask me anything else about attendance or schedules.', null, $this->suggestions($role, $people));

            case 'help':
                return $this->helpReply($role, $people);

            case 'student_report':
                return $this->studentReport($role, $user, $matches, $classes, $classMatches, $f);

            case 'who_status':
                return $this->whoStatus($role, $classIds, $people, $classes, $f);

            case 'top_absent':
                return $this->topAbsent($role, $classIds, $people, $f);

            case 'class_report':
                return $this->classReport($role, $classMatches, $people, $f);

            case 'schedule':
                return $this->scheduleReply($role, $classes, $f);

            case 'next_class':
                return $this->nextClassReply($classes, $today);

            case 'counts':
                return $this->countsReply($role, $classes, $people);

            case 'gate':
                return $this->gateReply($role, $today);
        }

        return $this->reply(
            'Sorry, I did not understand that. Here are some things you can ask:',
            null,
            $this->suggestions($role, $people)
        );
    }

    /* =========================================================
       HANDLERS
       ========================================================= */

    private function studentReport(string $role, array $user, array $matches, array $classes, array $classMatches, array $f): array
    {
        if (count($matches) > 1) {
            $names = array_map(function ($m) {
                return $this->fullName($m['person']);
            }, $matches);

            return $this->reply(
                'I found more than one student: ' . implode(', ', $names) . '. Which one do you mean?',
                null,
                array_map(static function ($n) {
                    return 'Summary of ' . $n . '\'s absences';
                }, array_slice($names, 0, 4))
            );
        }

        $closest = '';

        if ($matches) {
            $person = $matches[0]['person'];

            // Only a spelling match: say who we picked so nobody is misled.
            if (empty($matches[0]['exact'])) {
                $closest = 'Closest match for your spelling: ' . $this->fullName($person) . ".\n";
            }
        } elseif ($role === 'student') {
            $person = [
                'user_id' => (int) $user['user_id'],
                'first_name' => (string) ($user['first_name'] ?? ''),
                'last_name' => (string) ($user['last_name'] ?? ''),
            ];
        } else {
            return $this->reply('Tell me which student, for example: "Summary of Jamie Student\'s absences".', null, []);
        }

        $isSelf = (int) $person['user_id'] === (int) $user['user_id'];
        $name = $isSelf ? 'you' : $this->fullName($person);
        $range = $f['range'];
        $rangeText = $range ? $range['label'] : 'all time';

        $scopeClasses = $classes;

        if (count($classMatches) === 1) {
            $scopeClasses = [$classMatches[0]];
        }

        $subjects = [];

        foreach ($scopeClasses as $class) {
            $subjects[(int) $class['class_id']] = (string) $class['subject_name'];
        }

        $rows = $this->ChatbotModel->attendance_rows(
            array_keys($subjects),
            (int) $person['user_id'],
            $range['from'] ?? null,
            $range['to'] ?? null
        );

        $title = $isSelf ? 'Your' : $this->fullName($person) . '\'s';
        $where = count($scopeClasses) === 1 ? ' in ' . $subjects[array_key_first($subjects)] : '';

        if (!$rows) {
            return $this->reply($closest . 'No attendance records found for ' . $name . $where . ' (' . $rangeText . ').', null, $this->followUps($isSelf));
        }

        $count = ['Present' => 0, 'Late' => 0, 'Absent' => 0];
        $byClass = [];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $cid = (int) $row['class_id'];

            if (isset($count[$status])) {
                $count[$status]++;
                $byClass[$cid][$status] = ($byClass[$cid][$status] ?? 0) + 1;
            }
        }

        $total = array_sum($count);
        $rate = $total ? round((($count['Present'] + $count['Late']) / $total) * 100) : 0;

        // Focus on one status.
        if ($f['status'] === 'Absent' || $f['status'] === 'Late') {
            $status = $f['status'];
            $list = array_values(array_filter($rows, static function ($r) use ($status) {
                return ($r['status'] ?? '') === $status;
            }));

            $word = $status === 'Absent' ? 'absent' : 'late';

            if (!$list) {
                return $this->reply(
                    $closest . ($isSelf ? 'You have' : $this->fullName($person) . ' has') . ' no ' . $word . ' records' . $where . ' (' . $rangeText . ').',
                    null,
                    $this->followUps($isSelf)
                );
            }

            $text = $closest . ($isSelf ? 'You were' : $this->fullName($person) . ' was') . ' ' . $word . ' ' . count($list)
                . ' time' . (count($list) === 1 ? '' : 's') . $where . ' (' . $rangeText . ').';

            if (count($list) > self::MAX_ROWS) {
                $text .= ' Showing the latest ' . self::MAX_ROWS . '.';
            }

            return $this->reply($text, [
                'columns' => ['Date', 'Subject'],
                'rows' => array_map(function ($r) use ($subjects) {
                    return [$this->prettyDate((string) $r['date']), $subjects[(int) $r['class_id']] ?? 'Class'];
                }, array_slice($list, 0, self::MAX_ROWS)),
            ], $this->followUps($isSelf));
        }

        // Summary
        $text = $closest . $title . ' attendance summary' . $where . ' (' . $rangeText . '):' . "\n"
            . '• Present: ' . $count['Present'] . "\n"
            . '• Late: ' . $count['Late'] . "\n"
            . '• Absent: ' . $count['Absent'] . "\n"
            . 'Attendance rate: ' . $rate . '% (Present + Late out of ' . $total . ' record' . ($total === 1 ? '' : 's') . ').';

        $recent = array_values(array_filter($rows, static function ($r) {
            return ($r['status'] ?? '') === 'Absent';
        }));

        if ($recent) {
            $text .= "\n\nRecent absences:";

            foreach (array_slice($recent, 0, 5) as $r) {
                $text .= "\n• " . $this->prettyDate((string) $r['date']) . ' - ' . ($subjects[(int) $r['class_id']] ?? 'Class');
            }
        }

        $table = null;

        if (count($subjects) > 1) {
            $tableRows = [];

            foreach ($byClass as $cid => $c) {
                $tableRows[] = [$subjects[$cid] ?? 'Class', $c['Present'] ?? 0, $c['Late'] ?? 0, $c['Absent'] ?? 0];
            }

            $table = ['columns' => ['Subject', 'Present', 'Late', 'Absent'], 'rows' => $tableRows];
        }

        return $this->reply($text, $table, $this->followUps($isSelf));
    }

    private function whoStatus(string $role, array $classIds, array $people, array $classes, array $f): array
    {
        if ($role === 'student') {
            return $this->reply('Only teachers and administrators can list other students. You can ask about your own attendance.', null, $this->suggestions($role, $people));
        }

        $status = $f['status'] ?? 'Absent';
        $range = $f['range'] ?? $this->todayRange();
        $names = [];

        foreach ($people as $p) {
            $names[(int) $p['user_id']] = $this->fullName($p);
        }

        $subjects = [];

        foreach ($classes as $class) {
            $subjects[(int) $class['class_id']] = (string) $class['subject_name'];
        }

        $rows = $this->ChatbotModel->attendance_rows($classIds, null, $range['from'], $range['to'], $status);
        $rows = array_values(array_filter($rows, static function ($r) use ($names) {
            return isset($names[(int) $r['student_id']]);
        }));

        $label = $range['label'];

        if (!$rows) {
            $note = ($status === 'Absent' && $label === 'today')
                ? ' Absences are recorded when a class ends, so classes still in session are not counted yet.'
                : '';

            return $this->reply('No ' . $status . ' records ' . $label . '.' . $note, null, $this->suggestions($role, $people));
        }

        $multiDay = $range['from'] !== $range['to'];
        $columns = $multiDay ? ['Date', 'Student', 'Subject'] : ['Student', 'Subject'];
        $out = [];

        foreach (array_slice($rows, 0, 30) as $r) {
            $line = [$names[(int) $r['student_id']], $subjects[(int) $r['class_id']] ?? 'Class'];

            if ($multiDay) {
                array_unshift($line, $this->prettyDate((string) $r['date']));
            }

            $out[] = $line;
        }

        $distinct = count(array_unique(array_column($rows, 'student_id')));

        return $this->reply(
            $distinct . ' student' . ($distinct === 1 ? ' was' : 's were') . ' marked ' . $status . ' ' . $label . ' (' . count($rows) . ' record' . (count($rows) === 1 ? '' : 's') . ').'
                . (count($rows) > 30 ? ' Showing the first 30.' : ''),
            ['columns' => $columns, 'rows' => $out],
            $this->suggestions($role, $people)
        );
    }

    private function topAbsent(string $role, array $classIds, array $people, array $f): array
    {
        if ($role === 'student') {
            return $this->reply('Only teachers and administrators can compare students.', null, $this->suggestions($role, $people));
        }

        $range = $f['range'];
        $limit = $f['limit'] ?? 5;
        $focus = $f['status'] === 'Late' ? 'Late' : 'Absent';

        $names = [];

        foreach ($people as $p) {
            $names[(int) $p['user_id']] = $this->fullName($p);
        }

        $rows = $this->ChatbotModel->attendance_rows($classIds, null, $range['from'] ?? null, $range['to'] ?? null);
        $stats = [];

        foreach ($rows as $r) {
            $id = (int) $r['student_id'];

            if (!isset($names[$id])) {
                continue;
            }

            $stats[$id] = $stats[$id] ?? ['Present' => 0, 'Late' => 0, 'Absent' => 0];
            $status = (string) ($r['status'] ?? '');

            if (isset($stats[$id][$status])) {
                $stats[$id][$status]++;
            }
        }

        $stats = array_filter($stats, static function ($s) use ($focus) {
            return $s[$focus] > 0;
        });

        uasort($stats, static function ($a, $b) use ($focus) {
            return $b[$focus] <=> $a[$focus];
        });

        $label = $range ? $range['label'] : 'all time';

        if (!$stats) {
            return $this->reply('No ' . strtolower($focus) . ' records found (' . $label . ').', null, $this->suggestions($role, $people));
        }

        $out = [];

        foreach (array_slice($stats, 0, $limit, true) as $id => $s) {
            $total = $s['Present'] + $s['Late'] + $s['Absent'];
            $rate = $total ? round((($s['Present'] + $s['Late']) / $total) * 100) : 0;
            $out[] = [$names[$id], $s['Absent'], $s['Late'], $total, $rate . '%'];
        }

        return $this->reply(
            'Students with the most ' . strtolower($focus) . ' records (' . $label . '):',
            ['columns' => ['Student', 'Absent', 'Late', 'Records', 'Attendance rate'], 'rows' => $out],
            $this->suggestions($role, $people)
        );
    }

    private function classReport(string $role, array $classMatches, array $people, array $f): array
    {
        if (count($classMatches) !== 1) {
            $names = array_map(static function ($c) {
                return (string) $c['subject_name'];
            }, $classMatches);

            return $this->reply('Which class do you mean? ' . implode(', ', array_slice($names, 0, 5)) . '.', null, []);
        }

        $class = $classMatches[0];
        $range = $f['range'];
        $names = [];

        foreach ($people as $p) {
            $names[(int) $p['user_id']] = $this->fullName($p);
        }

        $rows = $this->ChatbotModel->attendance_rows([(int) $class['class_id']], $role === 'student' ? ($people[0]['user_id'] ?? 0) : null, $range['from'] ?? null, $range['to'] ?? null);
        $label = $range ? $range['label'] : 'all time';

        if (!$rows) {
            return $this->reply('No attendance records for ' . $class['subject_name'] . ' (' . $label . ').', null, []);
        }

        $count = ['Present' => 0, 'Late' => 0, 'Absent' => 0];
        $perStudent = [];

        foreach ($rows as $r) {
            $status = (string) ($r['status'] ?? '');

            if (!isset($count[$status])) {
                continue;
            }

            $count[$status]++;

            if ($status === 'Absent') {
                $id = (int) $r['student_id'];
                $perStudent[$id] = ($perStudent[$id] ?? 0) + 1;
            }
        }

        $total = array_sum($count);
        $rate = $total ? round((($count['Present'] + $count['Late']) / $total) * 100) : 0;

        $text = $class['subject_name'] . ' attendance (' . $label . '):' . "\n"
            . '• Present: ' . $count['Present'] . "\n• Late: " . $count['Late'] . "\n• Absent: " . $count['Absent'] . "\n"
            . 'Attendance rate: ' . $rate . '% across ' . $total . ' records.';

        $table = null;

        if ($role !== 'student' && $perStudent) {
            arsort($perStudent);
            $out = [];

            foreach (array_slice($perStudent, 0, 5, true) as $id => $n) {
                if (isset($names[$id])) {
                    $out[] = [$names[$id], $n];
                }
            }

            $table = ['columns' => ['Most absences', 'Absent'], 'rows' => $out];
        }

        return $this->reply($text, $table, []);
    }

    private function scheduleReply(string $role, array $classes, array $f): array
    {
        $day = $f['day'];
        $todays = $this->classesOn($classes, $day['code']);

        if ($role === 'admin' && !$classes) {
            return $this->reply('There are no classes yet.', null, []);
        }

        if (!$todays) {
            return $this->reply('No classes ' . $day['label'] . ' (' . $day['date']->format(strpos($day['label'], 'on ') === 0 ? 'M j' : 'l, M j') . ').', null, []);
        }

        $out = [];

        foreach ($todays as $class) {
            $line = [
                $this->clock((string) $class['start_time']) . ' - ' . $this->clock((string) $class['end_time']),
                (string) $class['subject_name'],
                (string) ($class['room'] ?? ''),
            ];

            if ($role !== 'teacher') {
                $line[] = $this->ChatbotModel->user_name((int) ($class['teacher_id'] ?? 0));
            }

            $out[] = $line;
        }

        $columns = ['Time', 'Subject', 'Room'];

        if ($role !== 'teacher') {
            $columns[] = 'Teacher';
        }

        return $this->reply(
            count($todays) . ' class' . (count($todays) === 1 ? '' : 'es') . ' ' . $day['label']
                . ' (' . $day['date']->format(strpos($day['label'], 'on ') === 0 ? 'M j' : 'l, M j') . '):',
            ['columns' => $columns, 'rows' => array_slice($out, 0, 25)],
            []
        );
    }

    private function nextClassReply(array $classes, DateTimeImmutable $now): array
    {
        $timezone = new DateTimeZone('Asia/Manila');

        for ($offset = 0; $offset < 7; $offset++) {
            $date = $now->modify('+' . $offset . ' days');
            $items = [];

            foreach ($this->classesOn($classes, strtoupper($date->format('D'))) as $class) {
                $start = $this->AttendanceModel->parse_clock($class['start_time'] ?? '', $date->format('Y-m-d'), $timezone);
                $end = $this->AttendanceModel->parse_clock($class['end_time'] ?? '', $date->format('Y-m-d'), $timezone);

                if (!$start || !$end || $end <= $now) {
                    continue;
                }

                $items[] = [$start, $end, $class];
            }

            usort($items, static function ($a, $b) {
                return $a[0] <=> $b[0];
            });

            if ($items) {
                [$start, $end, $class] = $items[0];
                $when = $start <= $now
                    ? 'Right now: ' . $class['subject_name'] . ' is in session until ' . $end->format('g:i A')
                    : 'Your next class is ' . $class['subject_name'] . ' ' . ($offset === 0 ? 'today' : ($offset === 1 ? 'tomorrow' : 'on ' . $date->format('l'))) . ' at ' . $start->format('g:i A');

                return $this->reply($when . ($class['room'] ? ' in ' . $class['room'] : '') . '.', null, []);
            }
        }

        return $this->reply('I could not find an upcoming class in the next 7 days.', null, []);
    }

    private function countsReply(string $role, array $classes, array $people): array
    {
        if ($role === 'admin') {
            return $this->reply(
                'School totals:' . "\n"
                . '• Students: ' . $this->ChatbotModel->count_people('student') . "\n"
                . '• Teachers: ' . $this->ChatbotModel->count_people('teacher') . "\n"
                . '• Admin/staff: ' . $this->ChatbotModel->count_people('admin') . "\n"
                . '• Classes: ' . count($classes),
                null,
                []
            );
        }

        if ($role === 'teacher') {
            return $this->reply('You handle ' . count($classes) . ' class' . (count($classes) === 1 ? '' : 'es') . ' with ' . count($people) . ' student' . (count($people) === 1 ? '' : 's') . '.', null, []);
        }

        return $this->reply('You are enrolled in ' . count($classes) . ' class' . (count($classes) === 1 ? '' : 'es') . '.', null, []);
    }

    private function gateReply(string $role, DateTimeImmutable $today): array
    {
        if ($role !== 'admin') {
            return $this->reply('Only administrators can view the gate and camera logs.', null, []);
        }

        $rows = $this->ChatbotModel->gate_rows($today->format('Y-m-d'));

        if (!$rows) {
            return $this->reply('No gate or camera check-ins have been recorded today.', null, []);
        }

        $lastByUser = [];

        foreach (array_reverse($rows) as $r) {
            $lastByUser[(int) $r['user_id']] = strtoupper((string) $r['event_type']);
        }

        $inside = count(array_filter($lastByUser, static function ($e) {
            return $e === 'IN';
        }));

        $out = [];

        foreach (array_slice($rows, 0, 8) as $r) {
            $out[] = [
                $this->ChatbotModel->user_name((int) $r['user_id']),
                ucfirst((string) ($r['role'] ?? '')),
                strtoupper((string) $r['event_type']),
                $this->clock(substr((string) $r['scanned_at'], 11)),
            ];
        }

        return $this->reply(
            count($rows) . ' gate/camera scans today. ' . $inside . ' ' . ($inside === 1 ? 'person is' : 'people are') . ' currently inside.',
            ['columns' => ['Name', 'Role', 'Event', 'Time'], 'rows' => $out],
            []
        );
    }

    private function helpReply(string $role, array $people): array
    {
        $lines = [
            'student' => "I can help with:\n• Your attendance summary, absences, or lates\n• Your attendance in one subject\n• Your class schedule and next class",
            'teacher' => "I can help with:\n• Who is absent today / this week\n• A student's attendance summary or absences\n• Students with the most absences\n• A class attendance summary\n• Your schedule and next class",
            'admin' => "I can help with:\n• Any student's attendance summary or absences\n• Who is absent today / this week\n• Students with the most absences\n• Class attendance summaries\n• Who entered the gate today\n• School totals and schedules",
        ];

        return $this->reply($lines[$role] ?? $lines['student'], null, $this->suggestions($role, $people));
    }

    /* =========================================================
       HELPERS
       ========================================================= */

    private function reply(string $text, $table, array $suggestions): array
    {
        return [
            'text' => $text,
            'table' => $table,
            'suggestions' => array_values($suggestions),
        ];
    }

    private function suggestions(string $role, array $people): array
    {
        if ($role === 'student') {
            return ['My attendance summary', 'How many times was I absent?', 'What is my schedule today?', 'When is my next class?'];
        }

        $sample = $people ? $this->fullName($people[0]) : 'a student';
        $base = ['Who is absent today?', 'Which students have the most absences?', 'Send me a summary of ' . $sample . '\'s absences'];

        if ($role === 'admin') {
            $base[] = 'Who entered the gate today?';
            $base[] = 'How many students are there?';
        } else {
            $base[] = 'What is my schedule today?';
        }

        return $base;
    }

    private function followUps(bool $isSelf): array
    {
        return $isSelf
            ? ['My attendance summary', 'How many times was I late?', 'What is my schedule today?']
            : ['Who is absent today?', 'Which students have the most absences?'];
    }

    private function classesFor(array $user, string $role): array
    {
        $userId = (int) $user['user_id'];

        if ($role === 'teacher') {
            $rows = $this->AttendanceModel->teacher_classes($userId);
        } elseif ($role === 'student') {
            $rows = $this->AttendanceModel->student_classes($userId);
        } else {
            $rows = $this->ChatbotModel->all_classes();
        }

        return is_array($rows) ? array_values($rows) : [];
    }

    private function peopleFor(array $user, string $role, array $classIds): array
    {
        if ($role === 'student') {
            return [[
                'user_id' => (int) $user['user_id'],
                'first_name' => (string) ($user['first_name'] ?? ''),
                'last_name' => (string) ($user['last_name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
            ]];
        }

        return $role === 'teacher'
            ? $this->ChatbotModel->students_of_classes($classIds)
            : $this->ChatbotModel->all_students();
    }

    /** Classes meeting on a weekday code, earliest first. */
    private function classesOn(array $classes, string $code): array
    {
        $found = array_values(array_filter($classes, function ($class) use ($code) {
            return in_array($code, $this->AttendanceModel->parse_days($class['class_days'] ?? ''), true);
        }));

        usort($found, static function ($a, $b) {
            return strcmp((string) $a['start_time'], (string) $b['start_time']);
        });

        return $found;
    }

    private function todayRange(): array
    {
        $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');

        return ['from' => $today, 'to' => $today, 'label' => 'today'];
    }

    private function fullName(array $person): string
    {
        return trim((string) ($person['first_name'] ?? '') . ' ' . (string) ($person['last_name'] ?? ''));
    }

    private function prettyDate(string $date): string
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $d ? $d->format('M j, Y (D)') : $date;
    }

    private function clock(string $time): string
    {
        $d = DateTimeImmutable::createFromFormat('H:i:s', substr($time, 0, 8))
            ?: DateTimeImmutable::createFromFormat('H:i', substr($time, 0, 5));

        return $d ? $d->format('g:i A') : $time;
    }

    private function authenticatedUser(): ?array
    {
        $token = $this->api->get_bearer_token();
        $payload = $token ? $this->api->validate_jwt($token) : null;
        $subject = is_array($payload) ? ($payload['sub'] ?? $payload['id'] ?? null) : null;
        $userId = filter_var($subject, FILTER_VALIDATE_INT);

        if (!$userId || $userId < 1) {
            $this->api->respond_error('Unauthorized. Please sign in again.', 401);
            return null;
        }

        $user = $this->UserModel->find_by('user_id', (int) $userId);

        if (!is_array($user) || !in_array(strtolower((string) ($user['role'] ?? '')), ['student', 'teacher', 'admin'], true)) {
            $this->api->respond_error('The account associated with this token no longer exists.', 401);
            return null;
        }

        return $user;
    }
}

<?php

/**
 * Public RFID kiosk endpoints.
 *
 * The kiosk itself does not require a user login. Each scan is matched to the
 * RFID UID stored on the existing users table. Run the close-sessions endpoint
 * from a trusted scheduler using RFID_CRON_KEY to finalize missing absences.
 */
class PublicRfidApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->database();
        $this->call->library('api');
        $this->call->model('UserModel');
        $this->call->model('AttendanceModel');
    }

    /** Present only within this many minutes after the class starts. */
    private const PRESENT_MINUTES = 15;

    /** After this many minutes a tap is recorded as Absent (class still on). */
    private const LATE_MINUTES = 60;

    /**
     * GET /api/rfid/active-class?room=Room 201
     *
     * Opens by itself while a class is in session in that room.
     * status: present = first 15 minutes (tap = Present)
     *         late    = 15 to 60 minutes (tap = Late)
     *         absent  = after 60 minutes until the class ends (tap = Absent)
     *         closed  = no class in session
     */
    public function active_class()
    {
        $this->api->require_method('GET');

        $query = $this->api->get_query_params();
        $room = trim((string) ($query['room'] ?? ''));
        $now = $this->now();

        // Classes that already ended close automatically: students with no
        // record become Absent (no cron job needed).
        $this->closeEndedClasses();

        $active = [];
        $todays = [];
        $roomClasses = [];
        $next = null;
        $known = $room === '';

        foreach ($this->allClasses() as $class) {
            if ($room !== '') {
                if ($this->roomKey($class['room'] ?? '') !== $this->roomKey($room)) {
                    continue;
                }

                $known = true;
            }

            $window = $this->AttendanceModel->class_window($class, $now);

            if ($room !== '') {
                $roomClasses[] = $this->diagnose($class, $window, $now);
            }

            if ($window === null) {
                continue;
            }

            $todays[] = [
                'subject_code' => (string) ($class['subject_code'] ?? ''),
                'subject_name' => (string) ($class['subject_name'] ?? ''),
                'start_time' => $window['start']->format('H:i:s'),
                'end_time' => $window['end']->format('H:i:s'),
            ];

            if ($now >= $window['start'] && $now < $window['end']) {
                $active[] = $this->shapeActive($class, $window, $now);
            } elseif ($now < $window['start']) {
                if ($next === null || $window['start']->format('H:i:s') < $next['start_time']) {
                    $next = [
                        'subject_code' => (string) ($class['subject_code'] ?? ''),
                        'subject_name' => (string) ($class['subject_name'] ?? ''),
                        'start_time' => $window['start']->format('H:i:s'),
                        'end_time' => $window['end']->format('H:i:s'),
                    ];
                }
            }
        }

        usort($todays, static function ($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        $current = $active[0] ?? null;

        if ($current) {
            $message = 'Class session is open.';
        } elseif (!$known) {
            $message = 'There is no room named "' . $room . '". Choose a room from the list.';
        } elseif ($next) {
            $message = 'No class is open right now. Next: ' . $next['subject_name']
                . ' opens at ' . $this->clock12($next['start_time']) . '.';
        } elseif ($todays) {
            $message = 'Today\'s classes in this room have ended.';
        } else {
            $message = 'No class is scheduled in this room today.';
        }

        $this->api->respond([
            'success' => true,
            'date' => $now->format('Y-m-d'),
            'server_time' => $now->format('Y-m-d H:i:s'),
            'room' => $room,
            'room_known' => $known,
            'rooms' => $this->roomNames(),
            'status' => $current ? $current['status'] : 'closed',
            'class' => $current,
            'active_classes' => $active,
            'today_classes' => $todays,
            'room_classes' => $roomClasses,
            'next_class' => $next,
            'present_minutes' => self::PRESENT_MINUTES,
            'late_minutes' => self::LATE_MINUTES,
            'message' => $message,
        ]);
    }

    /**
     * GET /api/rfid/open-rooms
     * Rooms whose attendance is open right now. The login page uses this to
     * switch to the classroom kiosk automatically.
     */
    public function open_rooms()
    {
        $this->api->require_method('GET');

        $now = $this->now();
        $this->closeEndedClasses();

        $open = [];

        foreach ($this->allClasses() as $class) {
            $window = $this->AttendanceModel->class_window($class, $now);

            if ($window === null || $now < $window['start'] || $now >= $window['end']) {
                continue;
            }

            $shaped = $this->shapeActive($class, $window, $now);
            $open[] = [
                'room' => $shaped['room'],
                'subject_name' => $shaped['subject_name'],
                'status' => $shaped['status'],
                'start_time' => $shaped['start_time'],
                'end_time' => $shaped['end_time'],
            ];
        }

        $this->api->respond([
            'success' => true,
            'server_time' => $now->format('Y-m-d H:i:s'),
            'rooms' => $this->roomNames(),
            'open' => $open,
        ]);
    }

    public function scan()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('public_rfid_' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 30, 60);
        $input = $this->api->body();
        $uid = strtoupper(trim((string) ($input['rfid_uid'] ?? '')));
        $mode = strtolower(trim((string) ($input['mode'] ?? 'class')));
        $room = trim((string) ($input['room'] ?? ''));

        if ($uid === '' || !preg_match('/^[A-Z0-9:_\-]{3,64}$/', $uid)) {
            $this->api->respond_error('A valid RFID card code is required.', 422);
            return;
        }
        if (!in_array($mode, ['class', 'gate'], true)) {
            $this->api->respond_error('Invalid RFID kiosk mode.', 422);
            return;
        }

        $user = $this->db->table('users')->where('rfid_uid', $uid)->get();
        if (!is_array($user) || !empty($user['deleted_at'])) {
            $this->api->respond_error('RFID card is not registered to an active account.', 404);
            return;
        }

        $role = strtolower((string) ($user['role'] ?? ''));
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $stamp = $now->format('Y-m-d H:i:s');
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        if ($mode === 'gate') {
            if (!in_array($role, ['student', 'teacher', 'staff', 'admin'], true)) {
                $this->api->respond_error('This account role cannot use the attendance gate.', 403);
                return;
            }
            $lastLogs = $this->db->table('rfid_gate_logs')
                ->where('user_id', (int) $user['user_id'])
                ->order_by('scanned_at', 'DESC')
                ->limit(1)
                ->get_all();
            $last = is_array($lastLogs) && isset($lastLogs[0]) ? $lastLogs[0] : null;
            if (is_array($last) && strtotime($stamp) - strtotime((string) $last['scanned_at']) < 10) {
                $this->api->respond_error('This card was just scanned. Please wait a few seconds.', 429);
                return;
            }
            $sameDayLast = is_array($last) && substr((string) ($last['scanned_at'] ?? ''), 0, 10) === $now->format('Y-m-d');
            $eventType = $sameDayLast && strtoupper((string) ($last['event_type'] ?? '')) === 'IN' ? 'OUT' : 'IN';
            $inserted = $this->db->table('rfid_gate_logs')->insert([
                'user_id' => (int) $user['user_id'],
                'role' => $role,
                'rfid_uid' => $uid,
                'event_type' => $eventType,
                'scanned_at' => $stamp,
                'device_name' => substr((string) ($input['device_name'] ?? 'Main Gate'), 0, 100),
            ]);
            if ($inserted === false) {
                $this->api->respond_error('Gate attendance could not be saved.', 500);
                return;
            }
            $this->api->respond([
                'success' => true,
                'mode' => 'gate',
                'name' => $name,
                'role' => ucfirst($role),
                'status' => $eventType === 'IN' ? 'Checked In' : 'Checked Out',
                'time' => $stamp,
                'message' => 'Gate ' . strtolower($eventType) . ' recorded.',
            ]);
            return;
        }

        if ($role !== 'student') {
            $this->api->respond_error('Class attendance is for student RFID cards. Teachers and staff should use the gate kiosk.', 403);
            return;
        }

        // Class attendance is always tied to the kiosk's room, so a tap can
        // never be matched to a class in some other room.
        if ($room === '') {
            $this->api->respond_error('This kiosk has no room selected. Choose the room first.', 422);
            return;
        }

        $date = $now->format('Y-m-d');
        $studentId = (int) $user['user_id'];
        $classes = $this->AttendanceModel->student_classes($studentId);
        $active = [];

        foreach (is_array($classes) ? $classes : [] as $class) {
            if ($room !== '' && $this->roomKey($class['room'] ?? '') !== $this->roomKey($room)) {
                continue;
            }

            $window = $this->AttendanceModel->class_window($class, $now);

            if ($window !== null && $now >= $window['start'] && $now < $window['end']) {
                $active[] = [$class, $window];
            }
        }

        if (!$active) {
            $this->api->respond_error($this->wrongRoomMessage($name, is_array($classes) ? $classes : [], $now), 409);
            return;
        }

        // Prefer an open class that has no record yet.
        $chosen = $active[0];

        foreach ($active as $candidate) {
            if (!$this->AttendanceModel->student_class_attendance((int) $candidate[0]['class_id'], $studentId, $date)) {
                $chosen = $candidate;
                break;
            }
        }

        [$class, $window] = $chosen;
        $classId = (int) $class['class_id'];

        // One tap per student per class per day.
        $existing = $this->AttendanceModel->student_class_attendance($classId, $studentId, $date);

        if ($existing) {
            $this->api->respond_error(
                $name . ' is already recorded as ' . $existing['status'] . ' for ' . (string) ($class['subject_name'] ?? 'this class') . '.',
                409
            );
            return;
        }

        $status = $this->statusFor($now, $window['start']);

        $saved = $this->AttendanceModel->record_student_choice(
            $classId,
            $studentId,
            $date,
            $status,
            // An Absent record carries no check-in time.
            $status === 'Absent' ? null : $stamp
        );

        if (!$saved) {
            $this->api->respond_error('Attendance could not be recorded. Please tap again.', 409);
            return;
        }

        $subject = (string) ($class['subject_name'] ?? 'class');

        $messages = [
            'Present' => 'Present recorded for ' . $subject . '.',
            'Late' => 'Marked Late for ' . $subject . ' (tapped after ' . self::PRESENT_MINUTES . ' minutes).',
            'Absent' => 'Marked Absent for ' . $subject . ' (tapped after ' . self::LATE_MINUTES . ' minutes).',
        ];

        $this->api->respond([
            'success' => true,
            'mode' => 'class',
            'name' => $name,
            'role' => 'Student',
            'subject' => $subject,
            'room' => (string) ($class['room'] ?? ''),
            'status' => $status,
            'time' => $stamp,
            'message' => $messages[$status],
        ]);
    }

    /**
     * Protected scheduler endpoint. Run every minute from cron/Task Scheduler.
     * It creates Absent rows for enrolled students after each class has ended.
     */
    public function close_sessions()
    {
        $this->api->require_method('POST');
        $expected = (string) getenv('RFID_CRON_KEY');
        $provided = (string) ($_SERVER['HTTP_X_RFID_CRON_KEY'] ?? '');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            $this->api->respond_error('Forbidden.', 403);
            return;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $today = strtoupper($now->format('D'));
        $date = $now->format('Y-m-d');
        $closed = 0;
        $classes = $this->db->table('classes')->get_all();

        foreach (is_array($classes) ? $classes : [] as $class) {
            $days = array_map(static function ($day) {
                return substr(strtoupper(trim($day)), 0, 3);
            }, explode(',', (string) ($class['class_days'] ?? '')));
            if (!in_array($today, $days, true)) continue;
            $endText = trim((string) ($class['end_time'] ?? ''));
            $end = $endText ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $endText, new DateTimeZone('Asia/Manila')) : false;
            if (!$end || $now < $end) continue;

            $classId = (int) $class['class_id'];
            $enrollments = $this->AttendanceModel->enrollments_for_class($classId);
            foreach (is_array($enrollments) ? $enrollments : [] as $enrollment) {
                $studentId = (int) ($enrollment['student_id'] ?? 0);
                if ($studentId < 1 || $this->AttendanceModel->student_class_attendance($classId, $studentId, $date)) continue;
                if ($this->AttendanceModel->record_student_choice($classId, $studentId, $date, 'Absent', null)) $closed++;
            }
        }

        $this->api->respond(['success' => true, 'date' => $date, 'absent_records_created' => $closed]);
    }

    /* ------------------------------------------------------------
       Helpers
       ------------------------------------------------------------ */

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    }

    /** Present / Late / Absent by minutes since the class started. */
    private function statusFor(DateTimeImmutable $now, DateTimeImmutable $start): string
    {
        $elapsed = $now->getTimestamp() - $start->getTimestamp();

        if ($elapsed <= self::PRESENT_MINUTES * 60) {
            return 'Present';
        }

        if ($elapsed <= self::LATE_MINUTES * 60) {
            return 'Late';
        }

        return 'Absent';
    }

    private function shapeActive(array $class, array $window, DateTimeImmutable $now): array
    {
        $start = $window['start'];
        $statusWord = $this->statusFor($now, $start);

        $teacher = $this->UserModel->find_any(
            'user_id',
            (int) ($class['teacher_id'] ?? 0)
        );

        return [
            'class_id' => (int) $class['class_id'],
            'subject_code' => (string) ($class['subject_code'] ?? ''),
            'subject_name' => (string) ($class['subject_name'] ?? ''),
            'teacher_name' => is_array($teacher)
                ? trim((string) ($teacher['first_name'] ?? '') . ' ' . (string) ($teacher['last_name'] ?? ''))
                : '',
            'room' => (string) ($class['room'] ?? ''),
            'status' => ['Present' => 'present', 'Late' => 'late', 'Absent' => 'absent'][$statusWord],
            'start_time' => $start->format('H:i:s'),
            'end_time' => $window['end']->format('H:i:s'),
            'start' => $start->format('Y-m-d H:i:s'),
            'present_until' => $start->modify('+' . self::PRESENT_MINUTES . ' minutes')->format('Y-m-d H:i:s'),
            'late_until' => $start->modify('+' . self::LATE_MINUTES . ' minutes')->format('Y-m-d H:i:s'),
            'end' => $window['end']->format('Y-m-d H:i:s'),
        ];
    }

    /** Why a class is (or is not) open right now, in plain words. */
    private function diagnose(array $class, $window, DateTimeImmutable $now): array
    {
        $days = $this->AttendanceModel->parse_days($class['class_days'] ?? '');
        $today = strtoupper($now->format('D'));

        if (!$days) {
            $reason = 'No class days are set for this class.';
        } elseif (!in_array($today, $days, true)) {
            $reason = 'Meets on ' . implode(', ', $days) . ' only (today is ' . $today . ').';
        } elseif ($window === null) {
            $reason = 'The start or end time is missing or invalid.';
        } elseif ($now < $window['start']) {
            $reason = 'Starts at ' . $window['start']->format('g:i A') . '.';
        } elseif ($now >= $window['end']) {
            $reason = 'Ended at ' . $window['end']->format('g:i A') . '.';
        } else {
            $reason = 'Open now.';
        }

        return [
            'subject_name' => (string) ($class['subject_name'] ?? ''),
            'subject_code' => (string) ($class['subject_code'] ?? ''),
            'days' => $days ? implode(', ', $days) : '',
            'start_time' => $window ? $window['start']->format('g:i A') : (string) ($class['start_time'] ?? ''),
            'end_time' => $window ? $window['end']->format('g:i A') : (string) ($class['end_time'] ?? ''),
            'reason' => $reason,
        ];
    }

    /**
     * Tell the student where their class really is (or when it starts),
     * instead of a vague "no class" message.
     */
    private function wrongRoomMessage(string $name, array $classes, DateTimeImmutable $now): string
    {
        $next = null;

        foreach ($classes as $class) {
            $window = $this->AttendanceModel->class_window($class, $now);

            if ($window === null) {
                continue;
            }

            $subject = (string) ($class['subject_name'] ?? 'your class');
            $classRoom = trim((string) ($class['room'] ?? ''));

            if ($now >= $window['start'] && $now < $window['end']) {
                return $name . ': your class ' . $subject . ' is open in '
                    . ($classRoom !== '' ? $classRoom : 'another room')
                    . ', not in this room. Please tap there.';
            }

            if ($now < $window['start'] && ($next === null || $window['start'] < $next[1])) {
                $next = [$subject, $window['start']];
            }
        }

        if ($next !== null) {
            return $name . ': you have no class open right now. Your next class, '
                . $next[0] . ', starts at ' . $next[1]->format('g:i A') . '.';
        }

        return $name . ': you have no class open right now.';
    }

    private function allClasses(): array
    {
        $classes = $this->db->table('classes')->get_all();

        return is_array($classes) ? array_values($classes) : [];
    }

    /** "Room 201", "room-201" and "ROOM201" all match. */
    private function roomKey($room): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $room)));
    }

    private function roomNames(): array
    {
        $names = [];

        foreach ($this->allClasses() as $class) {
            $room = trim((string) ($class['room'] ?? ''));

            if ($room !== '') {
                $names[$this->roomKey($room)] = $room;
            }
        }

        natcasesort($names);

        return array_values($names);
    }

    private function clock12(string $time): string
    {
        $dt = DateTimeImmutable::createFromFormat('H:i:s', $time);

        return $dt ? $dt->format('g:i A') : $time;
    }

    private function closeEndedClasses(): void
    {
        try {
            $this->AttendanceModel->close_ended_classes();
        } catch (Throwable $e) {
            // Never block the kiosk because of the housekeeping step.
        }
    }
}

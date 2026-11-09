<?php

/**
 * Small rule-based NLP engine for the school chatbot.
 *
 * Steps: normalise -> tokenise -> detect intent keywords (English + Tagalog)
 * -> extract entities (date range, student name with fuzzy matching, class,
 * status word, top-N). It has no database access; the controller decides
 * what each role is allowed to see.
 */
class ChatbotNlp extends Model
{
    private const MONTHS = [
        'january' => 1, 'jan' => 1, 'february' => 2, 'feb' => 2, 'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4, 'may' => 5, 'june' => 6, 'jun' => 6, 'july' => 7, 'jul' => 7,
        'august' => 8, 'aug' => 8, 'september' => 9, 'sept' => 9, 'sep' => 9,
        'october' => 10, 'oct' => 10, 'november' => 11, 'nov' => 11, 'december' => 12, 'dec' => 12,
    ];

    private const WEEKDAYS = [
        'monday' => 'MON', 'mon' => 'MON', 'tuesday' => 'TUE', 'tue' => 'TUE', 'tues' => 'TUE',
        'wednesday' => 'WED', 'wed' => 'WED', 'thursday' => 'THU', 'thu' => 'THU', 'thurs' => 'THU',
        'friday' => 'FRI', 'fri' => 'FRI', 'saturday' => 'SAT', 'sat' => 'SAT', 'sunday' => 'SUN', 'sun' => 'SUN',
    ];

    /** Words that must never be fuzzy-matched against a person's name. */
    private const STOPWORDS = [
        'the', 'and', 'for', 'was', 'were', 'has', 'have', 'had', 'with', 'from', 'that', 'this',
        'who', 'what', 'when', 'where', 'show', 'send', 'give', 'tell', 'list', 'get', 'how', 'many',
        'much', 'times', 'time', 'student', 'students', 'teacher', 'teachers', 'class', 'classes',
        'absent', 'absence', 'absences', 'late', 'lates', 'present', 'attendance', 'summary',
        'report', 'record', 'records', 'today', 'yesterday', 'tomorrow', 'week', 'month', 'last',
        'this', 'next', 'days', 'day', 'schedule', 'about', 'please', 'can', 'you', 'his', 'her',
        'their', 'sino', 'ilan', 'ang', 'mga', 'sa', 'ng', 'ako', 'akin', 'help', 'data', 'info',
        'name', 'gate', 'entered', 'inside', 'school', 'most', 'top', 'april', 'march', 'june',
    ];

    public function normalize(string $text): string
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        // Keep letters, numbers, apostrophes, slashes, dashes and colons.
        $text = preg_replace("/[^\p{L}\p{N}'\/:\-]+/u", ' ', $text);
        $text = preg_replace("/'s\b/u", '', $text);
        $text = str_replace("'", '', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    public function has(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (preg_match('/(^|\s)' . preg_quote($phrase, '/') . '(\s|$)/u', $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Understand a message. $today is a DateTimeImmutable in Manila time.
     */
    public function parse(string $message, DateTimeImmutable $today): array
    {
        $text = $this->normalize($message);
        $tokens = $text === '' ? [] : explode(' ', $text);

        $absent = $this->has($text, ['absent', 'absents', 'absence', 'absences', 'absenses', 'missed', 'miss', 'lumiban', 'liban', 'absentee', 'absentees', 'hindi pumasok', 'di pumasok']);
        $late = $this->has($text, ['late', 'lates', 'tardy', 'tardiness', 'nahuli', 'huli', 'late arrivals']);
        $present = $this->has($text, ['present', 'attended', 'pumasok', 'on time']);

        $status = null;

        if ($absent && !$late) {
            $status = 'Absent';
        } elseif ($late && !$absent) {
            $status = 'Late';
        } elseif ($present && !$absent && !$late) {
            $status = 'Present';
        }

        $self = $this->has($text, ['my', 'me', 'mine', 'myself', 'ako', 'akin', 'ko', 'i was', 'i am', 'i have', 'i had', 'i got', 'am i', 'have i', 'did i', 'how many times have i']);
        $collective = $this->has($text, ['who', 'sino', 'which student', 'which students', 'list of students', 'all students', 'everyone', 'everybody', 'lahat', 'mga estudyante', 'students who', 'students that', 'students na']);

        $wantsSummary = $this->has($text, ['summary', 'summarize', 'summarise', 'report', 'overview', 'buod', 'record', 'records', 'details', 'history', 'breakdown', 'attendance', 'stats', 'statistics']);

        $limit = null;

        if (preg_match('/\btop (\d{1,2})\b/', $text, $m) || preg_match('/\b(\d{1,2}) (?:students|names)\b/', $text, $m)) {
            $limit = max(1, min(25, (int) $m[1]));
        }

        return [
            'text' => $text,
            'tokens' => $tokens,
            'status' => $status,
            'self' => $self,
            'collective' => $collective,
            'wants_summary' => $wantsSummary,
            'range' => $this->parseRange($text, $today),
            'day' => $this->parseDay($text, $today),
            'limit' => $limit,
            'asks_next' => $this->has($text, ['next class', 'next subject', 'susunod na klase', 'susunod na class', 'next period']),
        ];
    }

    /**
     * What does the person want? $hasPerson / $hasClass come from the
     * entity matching step.
     */
    public function intent(array $f, bool $hasPerson, bool $hasClass, string $role): string
    {
        $t = $f['text'];

        if ($t === '') {
            return 'unknown';
        }

        if ($this->has($t, ['help', 'what can you do', 'commands', 'tulong', 'how to use', 'options', 'what can i ask', 'paano gamitin'])) {
            return 'help';
        }

        if (count($f['tokens']) <= 4 && $this->has($t, ['hi', 'hello', 'hey', 'kumusta', 'good morning', 'good afternoon', 'good evening', 'musta'])) {
            return 'greeting';
        }

        if ($this->has($t, ['thanks', 'thank you', 'salamat', 'ty'])) {
            return 'thanks';
        }

        if ($f['asks_next']) {
            return 'next_class';
        }

        if ($this->has($t, ['schedule', 'timetable', 'iskedyul', 'my classes', 'classes today', 'class today', 'what class', 'what classes', 'klase ko', 'klase ngayon'])) {
            return 'schedule';
        }

        if ($this->has($t, ['gate', 'time in', 'time out', 'checked in', 'checked out', 'entered', 'inside school', 'in school', 'nasa school', 'came in'])) {
            return 'gate';
        }

        if (preg_match('/\b(how many|total|ilan)\b.*\b(student|students|teacher|teachers|admin|admins|staff|accounts|users|classes|subjects|estudyante)\b/u', $t)) {
            return 'counts';
        }

        if ($this->has($t, ['most absences', 'most absent', 'highest absences', 'top absent', 'top absences', 'frequently absent', 'frequent absences', 'chronic', 'at risk', 'at-risk', 'truant', 'truancy', 'many absences', 'worst attendance', 'lowest attendance', 'pinaka absent', 'pinakamaraming absent', 'most late', 'most lates'])) {
            return 'top_absent';
        }

        if ($f['collective'] && $f['status'] !== null && !$hasPerson) {
            return 'who_status';
        }

        if ($hasPerson || ($f['self'] && $role === 'student')) {
            return 'student_report';
        }

        if ($role === 'student' && ($f['status'] !== null || $f['wants_summary'])) {
            return 'student_report';
        }

        if ($hasClass && ($f['wants_summary'] || $f['status'] !== null)) {
            return 'class_report';
        }

        if ($f['status'] !== null && $role !== 'student') {
            return 'who_status';
        }

        return 'unknown';
    }

    /* ---------------------------------------------------------
       Dates
       --------------------------------------------------------- */

    /** @return array{from:string,to:string,label:string}|null */
    public function parseRange(string $text, DateTimeImmutable $today)
    {
        $fmt = static function (DateTimeImmutable $d) {
            return $d->format('Y-m-d');
        };

        $one = static function (DateTimeImmutable $d, string $label) use ($fmt) {
            return ['from' => $fmt($d), 'to' => $fmt($d), 'label' => $label];
        };

        if ($this->has($text, ['today', 'ngayon', 'ngayong araw'])) {
            return $one($today, 'today');
        }

        if ($this->has($text, ['yesterday', 'kahapon'])) {
            return $one($today->modify('-1 day'), 'yesterday');
        }

        if (preg_match('/\b(?:last|past|previous) (\d{1,3}) days?\b/', $text, $m)) {
            $days = max(1, min(365, (int) $m[1]));

            return ['from' => $fmt($today->modify('-' . ($days - 1) . ' days')), 'to' => $fmt($today), 'label' => 'in the last ' . $days . ' days'];
        }

        if ($this->has($text, ['this week', 'ngayong linggo'])) {
            $monday = $today->modify('monday this week');

            return ['from' => $fmt($monday), 'to' => $fmt($monday->modify('+6 days')), 'label' => 'this week'];
        }

        if ($this->has($text, ['last week', 'nakaraang linggo'])) {
            $monday = $today->modify('monday last week');

            return ['from' => $fmt($monday), 'to' => $fmt($monday->modify('+6 days')), 'label' => 'last week'];
        }

        if ($this->has($text, ['this month', 'ngayong buwan'])) {
            $first = $today->modify('first day of this month');

            return ['from' => $fmt($first), 'to' => $fmt($first->modify('last day of this month')), 'label' => 'this month'];
        }

        if ($this->has($text, ['last month', 'nakaraang buwan'])) {
            $first = $today->modify('first day of last month');

            return ['from' => $fmt($first), 'to' => $fmt($first->modify('last day of this month')), 'label' => 'last month'];
        }

        // 2026-11-05
        if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/', $text, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $d = $today->setDate((int) $m[1], (int) $m[2], (int) $m[3]);

            return $one($d, 'on ' . $d->format('M j, Y'));
        }

        // 11/5 or 11/05/2026
        if (preg_match('/\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/', $text, $m)) {
            $year = isset($m[3]) ? (int) $m[3] : (int) $today->format('Y');
            $year = $year < 100 ? 2000 + $year : $year;

            if (checkdate((int) $m[1], (int) $m[2], $year)) {
                $d = $today->setDate($year, (int) $m[1], (int) $m[2]);

                return $one($d, 'on ' . $d->format('M j, Y'));
            }
        }

        // "nov 5", "november 5", "5 nov"
        $monthPattern = implode('|', array_keys(self::MONTHS));

        if (
            preg_match('/\b(' . $monthPattern . ') (\d{1,2})(?:\s?,?\s?(\d{4}))?\b/', $text, $m)
            || preg_match('/\b(\d{1,2}) (' . $monthPattern . ')(?:\s?,?\s?(\d{4}))?\b/', $text, $m2)
        ) {
            if (isset($m2) && !isset($m[1])) {
                $monthName = $m2[2];
                $day = (int) $m2[1];
                $year = isset($m2[3]) ? (int) $m2[3] : null;
            } else {
                $monthName = $m[1];
                $day = (int) $m[2];
                $year = isset($m[3]) ? (int) $m[3] : null;
            }

            $month = self::MONTHS[$monthName];
            $year = $year ?: (int) $today->format('Y');

            if (checkdate($month, $day, $year)) {
                $d = $today->setDate($year, $month, $day);

                return $one($d, 'on ' . $d->format('M j, Y'));
            }
        }

        // "in november", "november" (whole month). "may" needs "in"/"of".
        foreach (self::MONTHS as $name => $number) {
            $pattern = $name === 'may'
                ? '/\b(?:in|of|for|during) may\b/'
                : '/\b' . $name . '\b/';

            if (!preg_match($pattern, $text)) {
                continue;
            }

            $year = (int) $today->format('Y');

            if ($number > (int) $today->format('n')) {
                $year--;
            }

            $first = $today->setDate($year, $number, 1);

            return [
                'from' => $fmt($first),
                'to' => $fmt($first->modify('last day of this month')),
                'label' => 'in ' . $first->format('F Y'),
            ];
        }

        return null;
    }

    /** The day a schedule question is about. @return array{date:DateTimeImmutable,code:string,label:string} */
    public function parseDay(string $text, DateTimeImmutable $today): array
    {
        $codes = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];

        if ($this->has($text, ['tomorrow', 'bukas'])) {
            $d = $today->modify('+1 day');

            return ['date' => $d, 'code' => strtoupper($d->format('D')), 'label' => 'tomorrow'];
        }

        if ($this->has($text, ['yesterday', 'kahapon'])) {
            $d = $today->modify('-1 day');

            return ['date' => $d, 'code' => strtoupper($d->format('D')), 'label' => 'yesterday'];
        }

        foreach (self::WEEKDAYS as $word => $code) {
            if (preg_match('/\b' . $word . '\b/', $text)) {
                $offset = (array_search($code, $codes, true) - array_search(strtoupper($today->format('D')), $codes, true) + 7) % 7;
                $d = $today->modify('+' . $offset . ' days');

                return ['date' => $d, 'code' => $code, 'label' => 'on ' . $d->format('l')];
            }
        }

        return ['date' => $today, 'code' => strtoupper($today->format('D')), 'label' => 'today'];
    }

    /* ---------------------------------------------------------
       Entities
       --------------------------------------------------------- */

    /**
     * Find which person (student) the message talks about.
     * Exact name parts score higher than fuzzy (typo) matches.
     *
     * @param array<int,array{user_id:int,first_name:string,last_name:string}> $people
     * @return array<int,array{person:array,score:int,exact:bool}> best matches (ties kept)
     */
    public function matchPeople(string $text, array $people): array
    {
        $tokens = $text === '' ? [] : explode(' ', $text);
        $tokenSet = array_flip($tokens);
        $scored = [];

        foreach ($people as $person) {
            $first = $this->normalize((string) ($person['first_name'] ?? ''));
            $last = $this->normalize((string) ($person['last_name'] ?? ''));
            $full = trim($first . ' ' . $last);
            $reverse = trim($last . ' ' . $first);
            $score = 0;
            $exact = false;

            if ($full !== '' && (strpos(' ' . $text . ' ', ' ' . $full . ' ') !== false || strpos(' ' . $text . ' ', ' ' . $reverse . ' ') !== false)) {
                $score += 12;
                $exact = true;
            }

            foreach (array_filter(explode(' ', $first . ' ' . $last)) as $part) {
                if (strlen($part) < 3) {
                    continue;
                }

                if (isset($tokenSet[$part])) {
                    $score += 4;
                    $exact = true;
                    continue;
                }

                if (strlen($part) < 4) {
                    continue;
                }

                $allowed = strlen($part) >= 5 ? 2 : 1;

                foreach ($tokens as $token) {
                    if (strlen($token) < 4 || in_array($token, self::STOPWORDS, true) || $token[0] !== $part[0]) {
                        continue;
                    }

                    if (abs(strlen($token) - strlen($part)) <= $allowed && levenshtein($token, $part) <= $allowed) {
                        // A typo still counts, a little less than an exact word.
                        $score += 3;
                        break;
                    }
                }
            }

            if ($score >= 3) {
                $scored[] = ['person' => $person, 'score' => $score, 'exact' => $exact];
            }
        }

        if (!$scored) {
            return [];
        }

        $max = max(array_column($scored, 'score'));

        return array_values(array_filter($scored, static function ($row) use ($max) {
            return $row['score'] === $max;
        }));
    }

    /**
     * Which class does the message mention? Subject-name words and the
     * subject code are compared with the message.
     *
     * @return array<int,array> matching class rows (ties kept)
     */
    public function matchClasses(string $text, array $classes): array
    {
        $tokens = $text === '' ? [] : explode(' ', $text);
        $tokenSet = array_flip($tokens);
        $best = [];
        $bestScore = 0;

        foreach ($classes as $class) {
            $score = 0;
            $code = $this->normalize((string) ($class['subject_code'] ?? ''));

            if ($code !== '' && (isset($tokenSet[$code]) || strpos(' ' . $text . ' ', ' ' . $code . ' ') !== false)) {
                $score += 3;
            }

            foreach (explode(' ', $this->normalize((string) ($class['subject_name'] ?? ''))) as $word) {
                if (strlen($word) < 3 || in_array($word, self::STOPWORDS, true)) {
                    continue;
                }

                if (isset($tokenSet[$word])) {
                    $score++;
                    continue;
                }

                foreach ($tokens as $token) {
                    if (strlen($token) >= 4 && !in_array($token, self::STOPWORDS, true)
                        && (strpos($word, $token) === 0 || strpos($token, $word) === 0)) {
                        $score++;
                        break;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [$class];
            } elseif ($score === $bestScore && $score > 0) {
                $best[] = $class;
            }
        }

        return $bestScore > 0 ? $best : [];
    }
}

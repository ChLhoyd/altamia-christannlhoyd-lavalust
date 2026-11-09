<?php

class AttendanceModel extends Model
{
    protected $table = 'student_attendance';
    protected $primary_key = 'attendance_id';

    private function manilaDate(): string
    {
        return (new DateTimeImmutable(
            'now',
            new DateTimeZone('Asia/Manila')
        ))->format('Y-m-d');
    }

    public function teacher_classes($teacher_id)
    {
        return $this->db->table('classes')
            ->where('teacher_id', (int) $teacher_id)
            ->get_all();
    }

    public function class_by_id($class_id)
    {
        return $this->db->table('classes')
            ->where('class_id', (int) $class_id)
            ->get();
    }

    public function student_classes($student_id)
    {
        $enrollments = $this->db->table('class_enrollments')
            ->where('student_id', (int) $student_id)
            ->get_all();

        $classes = [];

        if (!is_array($enrollments)) {
            return $classes;
        }

        foreach ($enrollments as $enrollment) {
            $classId = (int) ($enrollment['class_id'] ?? 0);

            if ($classId < 1) {
                continue;
            }

            $class = $this->class_by_id($classId);

            if (is_array($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    public function enrollments_for_class($class_id)
    {
        return $this->db->table('class_enrollments')
            ->where('class_id', (int) $class_id)
            ->get_all();
    }

    public function is_student_enrolled($class_id, $student_id)
    {
        return $this->db->table('class_enrollments')
            ->where('class_id', (int) $class_id)
            ->where('student_id', (int) $student_id)
            ->get();
    }

    public function attendance_by_id($attendance_id)
    {
        return $this->db->table('student_attendance')
            ->where('attendance_id', (int) $attendance_id)
            ->get();
    }

    public function attendance_for_student($student_id)
    {
        return $this->db->table('student_attendance')
            ->where('student_id', (int) $student_id)
            ->order_by('date', 'DESC')
            ->get_all();
    }

    public function attendance_for_class_today($class_id)
    {
        return $this->attendance_for_class_on_date(
            $class_id,
            $this->manilaDate()
        );
    }

    public function attendance_for_class_on_date($class_id, $date)
    {
        return $this->db->table('student_attendance')
            ->where('class_id', (int) $class_id)
            ->where('date', (string) $date)
            ->get_all();
    }

    public function student_class_attendance(
        $class_id,
        $student_id,
        $date = null
    ) {
        $date = $date ?: $this->manilaDate();

        return $this->db->table('student_attendance')
            ->where('class_id', (int) $class_id)
            ->where('student_id', (int) $student_id)
            ->where('date', (string) $date)
            ->get();
    }

    /**
     * Class days as 3-letter codes. Accepts "MON,WED", "Mon, Wed",
     * "Monday/Wednesday", "Tues Thurs" and similar.
     */
    public function parse_days($raw)
    {
        $codes = [];

        foreach (preg_split('/[^A-Za-z]+/', (string) $raw) as $token) {
            if (strlen($token) < 3) {
                continue;
            }

            $code = strtoupper(substr($token, 0, 3));

            if (in_array($code, ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'], true)) {
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }

    /**
     * A clock text ("20:00:00", "20:00", "8:00 PM") on a given date.
     */
    public function parse_clock($text, $date, DateTimeZone $timezone)
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        foreach (['H:i:s', 'H:i', 'g:i A', 'g:iA', 'h:i A', 'h:iA', 'g A', 'gA'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat(
                '!Y-m-d ' . $format,
                $date . ' ' . $text,
                $timezone
            );

            $errors = DateTimeImmutable::getLastErrors();

            if ($parsed && (!$errors || ($errors['warning_count'] + $errors['error_count']) === 0)) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Today's schedule window of a class, or null when the class does not
     * meet today or has no valid start/end time.
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}|null
     */
    public function class_window(array $class, DateTimeImmutable $now)
    {
        $timezone = new DateTimeZone('Asia/Manila');

        if (!in_array(strtoupper($now->format('D')), $this->parse_days($class['class_days'] ?? ''), true)) {
            return null;
        }

        $date = $now->format('Y-m-d');
        $start = $this->parse_clock($class['start_time'] ?? '', $date, $timezone);
        $end = $this->parse_clock($class['end_time'] ?? '', $date, $timezone);

        if (!$start || !$end || $end <= $start) {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Once a class has ended today, every enrolled student without a record
     * is marked Absent. Safe to call repeatedly: it only adds missing rows,
     * so no cron job is required.
     *
     * @return int number of Absent records created
     */
    public function close_ended_classes()
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $date = $now->format('Y-m-d');
        $created = 0;

        $classes = $this->db->table('classes')->get_all();

        foreach (is_array($classes) ? $classes : [] as $class) {
            $window = $this->class_window($class, $now);

            if ($window === null || $now < $window['end']) {
                continue;
            }

            $classId = (int) ($class['class_id'] ?? 0);
            $enrollments = $this->enrollments_for_class($classId);

            if ($classId < 1 || !is_array($enrollments) || !$enrollments) {
                continue;
            }

            $marked = [];
            $records = $this->attendance_for_class_on_date($classId, $date);

            foreach (is_array($records) ? $records : [] as $record) {
                $marked[(int) ($record['student_id'] ?? 0)] = true;
            }

            foreach ($enrollments as $enrollment) {
                $studentId = (int) ($enrollment['student_id'] ?? 0);

                if ($studentId < 1 || isset($marked[$studentId])) {
                    continue;
                }

                $student = $this->db->table('users')
                    ->where('user_id', $studentId)
                    ->get();

                if (
                    !is_array($student) ||
                    !empty($student['deleted_at']) ||
                    strtolower((string) ($student['role'] ?? '')) !== 'student'
                ) {
                    continue;
                }

                // Re-check right before inserting to avoid duplicates.
                if ($this->student_class_attendance($classId, $studentId, $date)) {
                    continue;
                }

                $this->db->table('student_attendance')->insert([
                    'class_id' => $classId,
                    'student_id' => $studentId,
                    'date' => $date,
                    'status' => 'Absent',
                    'recorded_by' => (int) ($class['teacher_id'] ?? 0),
                    'check_in_time' => null,
                ]);

                $created++;
            }
        }

        return $created;
    }

    /**
     * Teacher adds or corrects a student's record for a selected date.
     */
    public function save_attendance(
        $class_id,
        $student_id,
        $teacher_id,
        $status,
        $date = null
    ) {
        if (!in_array($status, ['Present', 'Absent', 'Late'], true)) {
            return false;
        }

        $date = $date ?: $this->manilaDate();

        $class = $this->class_by_id($class_id);

        if (
            !is_array($class) ||
            (int) ($class['teacher_id'] ?? 0) !== (int) $teacher_id
        ) {
            return false;
        }

        if (!$this->is_student_enrolled($class_id, $student_id)) {
            return false;
        }

        $existing = $this->student_class_attendance(
            $class_id,
            $student_id,
            $date
        );

        if ($existing) {
            $updates = [
                'status' => $status,
                'recorded_by' => (int) $teacher_id,
            ];

            // An Absent record should not have a check-in timestamp.
            if ($status === 'Absent') {
                $updates['check_in_time'] = null;
            }

            return $this->db->table('student_attendance')
                ->where(
                    'attendance_id',
                    (int) $existing['attendance_id']
                )
                ->update($updates);
        }

        return $this->db->table('student_attendance')->insert([
            'class_id' => (int) $class_id,
            'student_id' => (int) $student_id,
            'date' => (string) $date,
            'status' => $status,
            'recorded_by' => (int) $teacher_id,
            'check_in_time' => null,
        ]);
    }

    /**
     * Save the student's self-check after the controller validates
     * the server's schedule and current time.
     */
    public function record_student_choice(
        $class_id,
        $student_id,
        $date,
        $status,
        $check_in_time = null
    ) {
        if (!in_array($status, ['Present', 'Absent', 'Late'], true)) {
            return false;
        }

        $class = $this->class_by_id($class_id);

        if (!is_array($class)) {
            return false;
        }

        if (!$this->is_student_enrolled($class_id, $student_id)) {
            return false;
        }

        $existing = $this->student_class_attendance(
            $class_id,
            $student_id,
            $date
        );

        // Only one attendance record per student/class/date.
        if ($existing) {
            return false;
        }

        return $this->db->table('student_attendance')->insert([
            'class_id' => (int) $class_id,
            'student_id' => (int) $student_id,
            'date' => (string) $date,
            'status' => $status,
            'recorded_by' => (int) $student_id,
            'check_in_time' => $check_in_time,
        ]);
    }
}
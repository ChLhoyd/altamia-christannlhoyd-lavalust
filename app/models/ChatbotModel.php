<?php

/**
 * Read-only data queries used by the chatbot. The controller decides which
 * class ids / students each role may see and passes them in.
 */
class ChatbotModel extends Model
{
    /** Every active student: user_id, first_name, last_name, email. */
    public function all_students()
    {
        return $this->people_by_role('student');
    }

    public function count_people($role)
    {
        return count($this->people_by_role($role));
    }

    private function people_by_role($role)
    {
        $users = $this->db->table('users')->get_all();
        $out = [];

        foreach (is_array($users) ? $users : [] as $user) {
            if (
                strtolower((string) ($user['role'] ?? '')) !== $role ||
                !empty($user['deleted_at'])
            ) {
                continue;
            }

            $out[] = [
                'user_id' => (int) $user['user_id'],
                'first_name' => (string) ($user['first_name'] ?? ''),
                'last_name' => (string) ($user['last_name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
            ];
        }

        return $out;
    }

    /** Active students enrolled in any of the given classes. */
    public function students_of_classes(array $classIds)
    {
        if (!$classIds) {
            return [];
        }

        $rows = $this->db->table('class_enrollments')
            ->in('class_id', array_map('intval', $classIds))
            ->get_all();

        $ids = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $ids[(int) $row['student_id']] = true;
        }

        $students = [];

        foreach ($this->all_students() as $student) {
            if (isset($ids[$student['user_id']])) {
                $students[] = $student;
            }
        }

        return $students;
    }

    public function all_classes()
    {
        $rows = $this->db->table('classes')->get_all();

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * Attendance rows for the given classes, optionally for one student,
     * a date range and one status.
     */
    public function attendance_rows(array $classIds, $studentId = null, $from = null, $to = null, $status = null)
    {
        if (!$classIds) {
            return [];
        }

        $query = $this->db->table('student_attendance')
            ->in('class_id', array_map('intval', $classIds));

        if ($studentId) {
            $query = $query->where('student_id', (int) $studentId);
        }

        if ($from && $to) {
            $query = $query->between('date', $from, $to);
        }

        if ($status) {
            $query = $query->where('status', $status);
        }

        $rows = $query->order_by('date', 'DESC')->get_all();

        return is_array($rows) ? $rows : [];
    }

    /** Gate / face check-ins of one day (newest first). */
    public function gate_rows($date)
    {
        try {
            $rows = $this->db->table('rfid_gate_logs')
                ->between('scanned_at', $date . ' 00:00:00', $date . ' 23:59:59')
                ->order_by('scanned_at', 'DESC')
                ->get_all();
        } catch (Throwable $e) {
            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    public function user_name($userId)
    {
        $user = $this->db->table('users')->where('user_id', (int) $userId)->get();

        return is_array($user)
            ? trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''))
            : 'Unknown';
    }
}

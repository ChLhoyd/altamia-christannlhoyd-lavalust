<?php

class AppealModel extends Model
{
    protected $table = 'appeals';
    protected $primary_key = 'appeal_id';

    public function for_student($student_id)
    {
        return $this->db->table('appeals')
            ->where('student_id', (int) $student_id)
            ->order_by('submitted_at', 'DESC')
            ->get_all();
    }

    public function all_appeals()
    {
        return $this->db->table('appeals')
            ->order_by('submitted_at', 'DESC')
            ->get_all();
    }

    public function find_appeal($appeal_id)
    {
        return $this->db->table('appeals')
            ->where('appeal_id', (int) $appeal_id)
            ->get();
    }

    public function has_pending_for_attendance($student_id, $attendance_id)
    {
        return $this->db->table('appeals')
            ->where('student_id', (int) $student_id)
            ->where('attendance_id', (int) $attendance_id)
            ->where('status', 'Pending')
            ->get();
    }

    public function create_appeal(
        $student_id,
        $attendance_id,
        $reason,
        $document_name
    ) {
        return $this->db->table('appeals')->insert([
            'student_id' => (int) $student_id,
            'attendance_id' => (int) $attendance_id,
            'reason' => trim((string) $reason),
            'document_url' => (string) $document_name,
            'status' => 'Pending',
        ]);
    }

    public function update_status($appeal_id, $status)
    {
        if (!in_array($status, ['Approved', 'Rejected'], true)) {
            return false;
        }

        return $this->db->table('appeals')
            ->where('appeal_id', (int) $appeal_id)
            ->where('status', 'Pending')
            ->update([
                'status' => $status,
            ]);
    }
}
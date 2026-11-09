<?php

class TeacherStudentNoteModel extends Model
{
    protected $table = 'teacher_student_notes';
    protected $primary_key = 'note_id';

    public function latest_for_author(int $authorId): array
    {
        $rows = $this->db->table($this->table)
            ->where('author_id', $authorId)
            ->order_by('created_at', 'DESC')
            ->get_all();

        return is_array($rows)
            ? array_values($rows)
            : [];
    }

    public function latest_all(): array
    {
        $rows = $this->db->table($this->table)
            ->order_by('created_at', 'DESC')
            ->get_all();

        return is_array($rows)
            ? array_values($rows)
            : [];
    }

    public function create_note(array $data)
    {
        return $this->db->table($this->table)->insert($data);
    }

    public function delete_for_student(int $studentId): void
    {
        $this->db->table($this->table)
            ->where('student_id', $studentId)
            ->delete();
    }
}
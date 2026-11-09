<?php

class TeacherNotesApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->library('api');

        $this->call->model('UserModel');
        $this->call->model('AttendanceModel');
        $this->call->model('TeacherStudentNoteModel');
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/teacher/notes
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $this->api->require_method('GET');

        $teacher = $this->authenticatedTeacher();

        if (!$teacher) {
            return;
        }

        $rows = $this->TeacherStudentNoteModel->latest_for_author(
            (int) $teacher['user_id']
        );

        $notes = $this->decorateNotes($rows);

        $this->api->respond([
            'success' => true,
            'notes' => $notes,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/teacher/notes
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $this->api->require_method('POST');

        $teacher = $this->authenticatedTeacher();

        if (!$teacher) {
            return;
        }

        $input = $this->api->body();

        $studentId = (int) ($input['student_id'] ?? 0);

        $title = trim(
            (string) ($input['title'] ?? '')
        );

        $noteText = trim(
            (string) ($input['note_text'] ?? '')
        );

        $noteType = trim(
            (string) ($input['note_type'] ?? 'General')
        );

        if (
            $studentId < 1 ||
            $title === '' ||
            strlen($title) > 150 ||
            strlen($noteText) < 3 ||
            strlen($noteText) > 5000
        ) {
            $this->api->respond_error(
                'Choose a student, and provide a title and note (3 to 5000 characters).',
                422
            );
            return;
        }

        if (!in_array($noteType, $this->allowedTypes(), true)) {
            $noteType = 'General';
        }

        $student = $this->UserModel->find_by(
            'user_id',
            $studentId
        );

        if (
            !is_array($student) ||
            strtolower((string) ($student['role'] ?? '')) !== 'student'
        ) {
            $this->api->respond_error(
                'The selected student account was not found.',
                404
            );
            return;
        }

        // The teacher no longer picks a class. The student only has to be
        // enrolled in one of this teacher's classes.
        $classId = $this->sharedClassId(
            (int) $teacher['user_id'],
            $studentId
        );

        if ($classId < 1) {
            $this->api->respond_error(
                'You can only add notes to students in your assigned classes.',
                403
            );
            return;
        }

        $saved = $this->TeacherStudentNoteModel->create_note([
            'author_id' => (int) $teacher['user_id'],
            'author_role' => 'teacher',
            'student_id' => $studentId,
            'class_id' => $classId,
            'note_type' => $noteType,
            'title' => $title,
            'note_text' => $noteText,
        ]);

        if ($saved === false) {
            $this->api->respond_error(
                'The student note could not be saved.',
                500
            );
            return;
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Student note saved. The administrator can now view it.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/teacher/notes/update
    |--------------------------------------------------------------------------
    */

    public function update()
    {
        $this->api->require_method('POST');

        $teacher = $this->authenticatedTeacher();

        if (!$teacher) {
            return;
        }

        $input = $this->api->body();

        $note = $this->ownedNote(
            (int) ($input['note_id'] ?? 0),
            (int) $teacher['user_id']
        );

        if (!$note) {
            $this->api->respond_error(
                'That note was not found, or it is not yours to edit.',
                404
            );
            return;
        }

        $title = trim((string) ($input['title'] ?? ''));
        $noteText = trim((string) ($input['note_text'] ?? ''));
        $noteType = trim((string) ($input['note_type'] ?? 'General'));

        if (
            $title === '' ||
            strlen($title) > 150 ||
            strlen($noteText) < 3 ||
            strlen($noteText) > 5000
        ) {
            $this->api->respond_error(
                'Provide a title and note details (3 to 5000 characters).',
                422
            );
            return;
        }

        if (!in_array($noteType, $this->allowedTypes(), true)) {
            $noteType = 'General';
        }

        $this->db->table('teacher_student_notes')
            ->where('note_id', (int) $note['note_id'])
            ->where('author_id', (int) $teacher['user_id'])
            ->update([
                'note_type' => $noteType,
                'title' => $title,
                'note_text' => $noteText,
            ]);

        $this->api->respond([
            'success' => true,
            'message' => 'Student note updated.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/teacher/notes/delete
    |--------------------------------------------------------------------------
    */

    public function delete()
    {
        $this->api->require_method('POST');

        $teacher = $this->authenticatedTeacher();

        if (!$teacher) {
            return;
        }

        $input = $this->api->body();

        $note = $this->ownedNote(
            (int) ($input['note_id'] ?? 0),
            (int) $teacher['user_id']
        );

        if (!$note) {
            $this->api->respond_error(
                'That note was not found, or it is not yours to delete.',
                404
            );
            return;
        }

        $this->db->table('teacher_student_notes')
            ->where('note_id', (int) $note['note_id'])
            ->where('author_id', (int) $teacher['user_id'])
            ->delete();

        $this->api->respond([
            'success' => true,
            'message' => 'Student note deleted.',
        ]);
    }

    private function allowedTypes(): array
    {
        return ['General', 'Academic', 'Behavior', 'Attendance', 'Guidance'];
    }

    private function ownedNote(int $noteId, int $teacherId)
    {
        if ($noteId < 1) {
            return null;
        }

        $note = $this->db->table('teacher_student_notes')
            ->where('note_id', $noteId)
            ->where('author_id', $teacherId)
            ->get();

        return is_array($note) ? $note : null;
    }

    /**
     * First class of this teacher that the student is enrolled in (0 if none).
     */
    private function sharedClassId(int $teacherId, int $studentId): int
    {
        $classes = $this->AttendanceModel->teacher_classes($teacherId);

        foreach (is_array($classes) ? $classes : [] as $class) {
            if (
                $this->AttendanceModel->is_student_enrolled(
                    (int) $class['class_id'],
                    $studentId
                )
            ) {
                return (int) $class['class_id'];
            }
        }

        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | TEACHER AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    private function authenticatedTeacher(): ?array
    {
        return $this->authenticatedUserWithRole('teacher');
    }

    private function authenticatedUserWithRole(
        string $requiredRole
    ): ?array {
        $token = $this->api->get_bearer_token();

        $payload = $token
            ? $this->api->validate_jwt($token)
            : null;

        $subject = is_array($payload)
            ? ($payload['sub'] ?? $payload['id'] ?? null)
            : null;

        $userId = filter_var(
            $subject,
            FILTER_VALIDATE_INT
        );

        if (!$userId || $userId < 1) {
            $this->api->respond_error(
                'Unauthorized. Please sign in again.',
                401
            );
            return null;
        }

        $user = $this->UserModel->find_by(
            'user_id',
            (int) $userId
        );

        if (!is_array($user)) {
            $this->api->respond_error(
                'The account associated with this token no longer exists.',
                401
            );
            return null;
        }

        if (
            strtolower(trim((string) ($user['role'] ?? ''))) !== $requiredRole
        ) {
            $this->api->respond_error(
                'You do not have permission to access this endpoint.',
                403
            );
            return null;
        }

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | NOTE DISPLAY DATA
    |--------------------------------------------------------------------------
    */

    private function decorateNotes(array $rows): array
    {
        $notes = [];

        foreach ($rows as $row) {
            $student = $this->UserModel->find_by(
                'user_id',
                (int) ($row['student_id'] ?? 0)
            );

            $author = $this->UserModel->find_by(
                'user_id',
                (int) ($row['author_id'] ?? 0)
            );

            $class = !empty($row['class_id'])
                ? $this->AttendanceModel->class_by_id(
                    (int) $row['class_id']
                )
                : null;

            $notes[] = [
                'note_id' => (int) ($row['note_id'] ?? 0),

                'student_id' => (int) ($row['student_id'] ?? 0),

                'student_name' => is_array($student)
                    ? trim(
                        ($student['first_name'] ?? '') . ' ' .
                        ($student['last_name'] ?? '')
                    )
                    : 'Student account removed',

                'student_email' => (string) ($student['email'] ?? ''),

                'author_id' => (int) ($row['author_id'] ?? 0),

                'author_name' => is_array($author)
                    ? trim(
                        ($author['first_name'] ?? '') . ' ' .
                        ($author['last_name'] ?? '')
                    )
                    : 'Account removed',

                'author_role' => (string) (
                    $row['author_role'] ?? 'teacher'
                ),

                'class_id' => isset($row['class_id'])
                    ? (int) $row['class_id']
                    : null,

                'subject_name' => is_array($class)
                    ? (string) ($class['subject_name'] ?? '')
                    : '',

                'note_type' => (string) (
                    $row['note_type'] ?? 'General'
                ),

                'title' => (string) ($row['title'] ?? ''),

                'note_text' => (string) ($row['note_text'] ?? ''),

                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $notes;
    }
}
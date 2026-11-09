<?php

class AccountsApiController extends Controller
{
    private $letterDirectory;

    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->library('api');
        $this->call->model('UserModel');
        $this->call->model('AttendanceModel');
        $this->call->model('AppealModel');

        // Private storage: outside public/ so uploaded medical letters
        // cannot be opened by guessing a public URL.
        $this->letterDirectory = rtrim(APP_DIR, '/\\') . DIRECTORY_SEPARATOR
        . 'uploads' . DIRECTORY_SEPARATOR . 'absence_letters' . DIRECTORY_SEPARATOR;
    }

    /* =========================================================
       STUDENT: LIST OWN ABSENCE LETTER REQUESTS
       GET /api/student/appeals
       ========================================================= */

    public function student_appeals()
    {
        $this->api->require_method('GET');

        $student = $this->authenticatedApiUser();

        if (!$student) {
            return;
        }

        if (strtolower((string) $student['role']) !== 'student') {
            $this->api->respond_error('Student access only.', 403);
            return;
        }

        $appeals = $this->AppealModel->for_student(
            (int) $student['user_id']
        );

        $result = [];

        foreach (is_array($appeals) ? $appeals : [] as $appeal) {
            $item = $this->shapeAppeal($appeal);

            if ($item !== null) {
                $result[] = $item;
            }
        }

        $this->api->respond([
            'success' => true,
            'appeals' => $result,
        ]);
    }

    /* =========================================================
       STUDENT: UPLOAD MEDICAL / EXCUSE LETTER
       POST /api/student/appeals
       Requires multipart/form-data:
       attendance_id, reason, medical_letter
       ========================================================= */

    public function submit_appeal()
    {
        $this->api->require_method('POST');

        $student = $this->authenticatedApiUser();

        if (!$student) {
            return;
        }

        if (strtolower((string) $student['role']) !== 'student') {
            $this->api->respond_error('Student access only.', 403);
            return;
        }

        // File uploads submitted through FormData are available via $_POST
        // and $_FILES rather than the JSON request body.
        $studentId = (int) $student['user_id'];
        $attendanceId = (int) ($_POST['attendance_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));

        // Supports PHP installations where mbstring is not enabled.
        $reasonLength = function_exists('mb_strlen')
            ? mb_strlen($reason, 'UTF-8')
            : strlen($reason);

        if ($attendanceId < 1 || $reasonLength < 10 || $reasonLength > 2000) {
            $this->api->respond_error(
                'Select an absent record and give a reason between 10 and 2000 characters.',
                422
            );
            return;
        }

        $attendance = $this->AttendanceModel->attendance_by_id(
            $attendanceId
        );

        if (
            !is_array($attendance) ||
            (int) ($attendance['student_id'] ?? 0) !== $studentId
        ) {
            $this->api->respond_error(
                'You can only submit a letter for your own attendance record.',
                403
            );
            return;
        }

        // This feature is for explaining an absence, not changing an
        // attendance record that was marked Late or Present.
        if (($attendance['status'] ?? '') !== 'Absent') {
            $this->api->respond_error(
                'An absence letter can only be uploaded for a record marked Absent.',
                422
            );
            return;
        }

        if (
            $this->AppealModel->has_pending_for_attendance(
                $studentId,
                $attendanceId
            )
        ) {
            $this->api->respond_error(
                'You already have a pending request for this absent record.',
                409
            );
            return;
        }

        $file = $_FILES['medical_letter'] ?? null;

        if (
            !is_array($file) ||
            (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        ) {
            $this->api->respond_error(
                'Upload your medical or excuse letter as a PDF, JPG, or PNG.',
                422
            );
            return;
        }

        $fileSize = (int) ($file['size'] ?? 0);

        if ($fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
            $this->api->respond_error(
                'The letter must be 5 MB or smaller.',
                422
            );
            return;
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');

        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            $this->api->respond_error(
                'The uploaded file could not be verified.',
                422
            );
            return;
        }

        if (!class_exists('finfo')) {
            $this->api->respond_error(
                'The PHP Fileinfo extension is disabled. Enable fileinfo in PHP and restart Apache.',
                500
            );
            return;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        $allowedTypes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];

        if (!isset($allowedTypes[$mime])) {
            $this->api->respond_error(
                'Only PDF, JPG, and PNG files are allowed.',
                422
            );
            return;
        }

        if (
            !is_dir($this->letterDirectory) &&
            !mkdir($this->letterDirectory, 0750, true) &&
            !is_dir($this->letterDirectory)
        ) {
            $this->api->respond_error(
                'The private letter upload folder could not be created.',
                500
            );
            return;
        }

        try {
            // Random internal name; original filename is never used as a path.
            $storedName = bin2hex(random_bytes(16))
                . '.' . $allowedTypes[$mime];
        } catch (Throwable $e) {
            $this->api->respond_error(
                'Could not prepare a secure file name.',
                500
            );
            return;
        }

        $destination = $this->letterDirectory . $storedName;

        if (!move_uploaded_file($temporaryPath, $destination)) {
            $this->api->respond_error(
                'The letter could not be saved.',
                500
            );
            return;
        }

        $saved = $this->AppealModel->create_appeal(
            $studentId,
            $attendanceId,
            $reason,
            $storedName
        );

        if (!$saved) {
            @unlink($destination);

            $this->api->respond_error(
                'The absence letter request could not be saved.',
                500
            );
            return;
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Your absence letter was submitted. Your teacher can now review it.',
        ]);
    }

    /* =========================================================
       TEACHER: VIEW ABSENCE LETTERS FROM ASSIGNED CLASSES
       GET /api/teacher/appeals
       ========================================================= */

    public function teacher_appeals()
    {
        $this->api->require_method('GET');

        $teacher = $this->authenticatedApiUser();

        if (!$teacher) {
            return;
        }

        if (strtolower((string) $teacher['role']) !== 'teacher') {
            $this->api->respond_error('Teacher access only.', 403);
            return;
        }

        $appeals = $this->AppealModel->all_appeals();
        $result = [];

        foreach (is_array($appeals) ? $appeals : [] as $appeal) {
            $item = $this->shapeAppeal($appeal);

            if ($item === null) {
                continue;
            }

            $class = $this->AttendanceModel->class_by_id(
                (int) $item['class_id']
            );

            // A teacher can only see requests from their own classes.
            if (
                !is_array($class) ||
                (int) ($class['teacher_id'] ?? 0) !== (int) $teacher['user_id']
            ) {
                continue;
            }

            $result[] = $item;
        }

        $this->api->respond([
            'success' => true,
            'appeals' => $result,
        ]);
    }

    /* =========================================================
       TEACHER: APPROVE OR REJECT A LETTER
       POST /api/teacher/appeals/review
       ========================================================= */

    public function review_appeal()
    {
        $this->api->require_method('POST');

        $teacher = $this->authenticatedApiUser();

        if (!$teacher) {
            return;
        }

        if (strtolower((string) $teacher['role']) !== 'teacher') {
            $this->api->respond_error('Teacher access only.', 403);
            return;
        }

        $input = $this->api->body();

        $appealId = (int) ($input['appeal_id'] ?? 0);
        $status = ucfirst(
            strtolower(trim((string) ($input['status'] ?? '')))
        );

        if (
            $appealId < 1 ||
            !in_array($status, ['Approved', 'Rejected'], true)
        ) {
            $this->api->respond_error(
                'Choose a valid absence letter and review status.',
                422
            );
            return;
        }

        $appeal = $this->AppealModel->find_appeal($appealId);

        if (
            !is_array($appeal) ||
            (string) ($appeal['status'] ?? '') !== 'Pending'
        ) {
            $this->api->respond_error(
                'The request was not found or has already been reviewed.',
                409
            );
            return;
        }

        $attendance = $this->AttendanceModel->attendance_by_id(
            (int) $appeal['attendance_id']
        );

        $class = is_array($attendance)
            ? $this->AttendanceModel->class_by_id(
                (int) $attendance['class_id']
            )
            : null;

        if (
            !is_array($class) ||
            (int) ($class['teacher_id'] ?? 0) !== (int) $teacher['user_id']
        ) {
            $this->api->respond_error(
                'You cannot review a letter from a class not assigned to you.',
                403
            );
            return;
        }

        // Important: approving a sick/absence letter does not turn Absent
        // into Present. It only records that the excuse was accepted.
        $updated = $this->AppealModel->update_status($appealId, $status);

        if (!$updated) {
            $this->api->respond_error(
                'The review status could not be saved.',
                500
            );
            return;
        }

        $this->api->respond([
            'success' => true,
            'message' => $status === 'Approved'
                ? 'Absence letter approved. The attendance record remains Absent.'
                : 'Absence letter rejected. The attendance record remains Absent.',
        ]);
    }

    /* =========================================================
       AUTHORIZED LETTER DOWNLOAD
       GET /api/appeal-letter?appeal_id=123
       ========================================================= */

    public function appeal_letter()
    {
        $this->api->require_method('GET');

        $user = $this->authenticatedApiUser();

        if (!$user) {
            return;
        }

        $query = $this->api->get_query_params();
        $appealId = (int) ($query['appeal_id'] ?? 0);

        $appeal = $appealId > 0
            ? $this->AppealModel->find_appeal($appealId)
            : null;

        if (!is_array($appeal) || empty($appeal['document_url'])) {
            $this->api->respond_error('Letter not found.', 404);
            return;
        }

        $attendance = $this->AttendanceModel->attendance_by_id(
            (int) $appeal['attendance_id']
        );

        $class = is_array($attendance)
            ? $this->AttendanceModel->class_by_id(
                (int) $attendance['class_id']
            )
            : null;

        $role = strtolower((string) ($user['role'] ?? ''));

        $isOwnerStudent = $role === 'student'
            && (int) $appeal['student_id'] === (int) $user['user_id'];

        $isAssignedTeacher = $role === 'teacher'
            && is_array($class)
            && (int) ($class['teacher_id'] ?? 0) === (int) $user['user_id'];

        if (!$isOwnerStudent && !$isAssignedTeacher) {
            $this->api->respond_error(
                'You do not have permission to access this letter.',
                403
            );
            return;
        }

        $storedName = basename((string) $appeal['document_url']);

        if (!preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $storedName)) {
            $this->api->respond_error(
                'The saved letter reference is invalid.',
                500
            );
            return;
        }

        $path = $this->letterDirectory . $storedName;

        if (!is_file($path) || !is_readable($path)) {
            $this->api->respond_error(
                'The uploaded letter file could not be found.',
                404
            );
            return;
        }

        $size = filesize($path);

        if ($size === false || $size > 5 * 1024 * 1024) {
            $this->api->respond_error(
                'The stored letter has an invalid size.',
                500
            );
            return;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if (!in_array($mime, [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ], true)) {
            $this->api->respond_error(
                'The stored letter file type is not allowed.',
                500
            );
            return;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            $this->api->respond_error(
                'The letter could not be read.',
                500
            );
            return;
        }

        $extension = $mime === 'application/pdf'
            ? 'pdf'
            : ($mime === 'image/png' ? 'png' : 'jpg');

        $this->api->respond([
            'success' => true,
            'file_name' => 'absence-letter-' . $appealId . '.' . $extension,
            'mime_type' => $mime,
            'content_base64' => base64_encode($content),
        ]);
    }

    /* =========================================================
       TEACHER SETTINGS: LIST STUDENTS IN OWN CLASSES
       GET /api/teacher/accounts
       ========================================================= */

    public function teacher_accounts()
    {
        $this->api->require_method('GET');

        $teacher = $this->authenticatedApiUser();

        if (!$teacher) {
            return;
        }

        if (strtolower((string) $teacher['role']) !== 'teacher') {
            $this->api->respond_error('Teacher access only.', 403);
            return;
        }

        $classes = $this->AttendanceModel->teacher_classes(
            (int) $teacher['user_id']
        );

        $studentIds = [];

        foreach (is_array($classes) ? $classes : [] as $class) {
            $enrollments = $this->AttendanceModel->enrollments_for_class(
                (int) $class['class_id']
            );

            foreach (is_array($enrollments) ? $enrollments : [] as $enrollment) {
                $id = (int) ($enrollment['student_id'] ?? 0);

                if ($id > 0) {
                    $studentIds[$id] = true;
                }
            }
        }

        $accounts = [];

        foreach (array_keys($studentIds) as $studentId) {
            $record = $this->UserModel->find_by(
                'user_id',
                (int) $studentId
            );

            if (
                !is_array($record) ||
                strtolower((string) ($record['role'] ?? '')) !== 'student'
            ) {
                continue;
            }

            $accounts[] = $this->publicUser($record);
        }

        usort($accounts, static function ($a, $b) {
            return strcasecmp(
                $a['last_name'] . $a['first_name'],
                $b['last_name'] . $b['first_name']
            );
        });

        $this->api->respond([
            'success' => true,
            'accounts' => $accounts,
        ]);
    }

    /* =========================================================
       TEACHER SETTINGS: CREATE STUDENT OR TEACHER
       POST /api/teacher/accounts
       ========================================================= */

    public function create_account()
    {
        $this->api->require_method('POST');

        $teacher = $this->authenticatedApiUser();

        if (!$teacher) {
            return;
        }

        if (strtolower((string) $teacher['role']) !== 'teacher') {
            $this->api->respond_error('Teacher access only.', 403);
            return;
        }

        $input = $this->api->body();

        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $role = strtolower(trim((string) ($input['role'] ?? 'student')));
        $contact = trim((string) ($input['contact_number'] ?? ''));
        $classId = (int) ($input['class_id'] ?? 0);

        if (
            $firstName === '' ||
            $lastName === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $this->api->respond_error(
                'Enter first name, last name, and a valid email address.',
                422
            );
            return;
        }

        if (
            mb_strlen($firstName) > 100 ||
            mb_strlen($lastName) > 100 ||
            strlen($email) > 150 ||
            strlen($contact) > 30
        ) {
            $this->api->respond_error(
                'One or more profile fields exceed their allowed length.',
                422
            );
            return;
        }

        if (strlen($password) < 8 || strlen($password) > 200) {
            $this->api->respond_error(
                'Set a temporary password between 8 and 200 characters.',
                422
            );
            return;
        }

        if (!in_array($role, ['student', 'teacher'], true)) {
            $this->api->respond_error(
                'You can create only student or teacher accounts.',
                422
            );
            return;
        }

        if ($this->UserModel->find_any('email', $email)) {
            $this->api->respond_error(
                'That email address is already registered.',
                409
            );
            return;
        }

        if ($role === 'student') {
            if ($classId < 1) {
                $this->api->respond_error(
                    'Choose the class to enroll this student in.',
                    422
                );
                return;
            }

            $ownedClass = $this->AttendanceModel->class_by_id($classId);

            if (
                !is_array($ownedClass) ||
                (int) ($ownedClass['teacher_id'] ?? 0) !== (int) $teacher['user_id']
            ) {
                $this->api->respond_error(
                    'Choose a class assigned to your teacher account.',
                    403
                );
                return;
            }
        } else {
            // A new teacher is created but is not automatically assigned a class.
            $classId = 0;
        }

        $inserted = $this->db->table('users')->insert([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role,
            'contact_number' => $contact !== '' ? $contact : null,
        ]);

        $created = $this->UserModel->find_by('email', $email);

        if (!$created || $inserted === false) {
            $this->api->respond_error(
                'The account could not be created.',
                500
            );
            return;
        }

        if ($role === 'student') {
            $enrolled = $this->db->table('class_enrollments')->insert([
                'class_id' => $classId,
                'student_id' => (int) $created['user_id'],
            ]);

            if ($enrolled === false) {
                $this->db->table('users')
                    ->where('user_id', (int) $created['user_id'])
                    ->delete();

                $this->api->respond_error(
                    'The account was created but class enrollment failed. The account was rolled back.',
                    500
                );
                return;
            }
        }

        $this->api->respond([
            'success' => true,
            'message' => ucfirst($role) . ' account created successfully.',
            'user' => $this->publicUser($created),
        ]);
    }

    /* =========================================================
       TEACHER: DELETE STUDENT ACCOUNT FROM OWN CLASS
       POST /api/teacher/accounts/delete
       ========================================================= */

    public function delete_student_account()
    {
        $this->api->require_method('POST');

        $teacher = $this->authenticatedApiUser();

        if (!$teacher) {
            return;
        }

        if (strtolower((string) $teacher['role']) !== 'teacher') {
            $this->api->respond_error('Teacher access only.', 403);
            return;
        }

        $input = $this->api->body();
        $studentId = (int) ($input['student_id'] ?? 0);

        $student = $studentId > 0
            ? $this->UserModel->find_by('user_id', $studentId)
            : null;

        if (
            !is_array($student) ||
            strtolower((string) ($student['role'] ?? '')) !== 'student'
        ) {
            $this->api->respond_error('Student account not found.', 404);
            return;
        }

        $allowed = false;

        $classes = $this->AttendanceModel->teacher_classes(
            (int) $teacher['user_id']
        );

        foreach (is_array($classes) ? $classes : [] as $class) {
            if (
                $this->AttendanceModel->is_student_enrolled(
                    (int) $class['class_id'],
                    $studentId
                )
            ) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            $this->api->respond_error(
                'You can only remove a student enrolled in one of your classes.',
                403
            );
            return;
        }

        if (!$this->purgeStudentData($studentId)) {
            $this->api->respond_error(
                'The student account could not be completely deleted. Check database foreign-key errors.',
                500
            );
            return;
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Student account and associated attendance data were deleted.',
        ]);
    }

    /* =========================================================
       STUDENT: DELETE OWN ACCOUNT
       POST /api/account/delete
       ========================================================= */

    public function delete_own_student_account()
    {
        $this->api->require_method('POST');

        $student = $this->authenticatedApiUser();

        if (!$student) {
            return;
        }

        if (strtolower((string) $student['role']) !== 'student') {
            $this->api->respond_error(
                'Only a student can use this self-delete endpoint.',
                403
            );
            return;
        }

        if (!$this->purgeStudentData((int) $student['user_id'])) {
            $this->api->respond_error(
                'Your account could not be completely deleted. Check database foreign-key errors.',
                500
            );
            return;
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Your account and associated attendance data were deleted.',
        ]);
    }

    /**
     * Remove student-owned records in foreign-key-safe order.
     * This is permanent account deletion.
     */
    private function purgeStudentData(int $studentId): bool
    {
        // Remove private letter files belonging to the student's appeals.
        $appeals = $this->db->table('appeals')
            ->where('student_id', $studentId)
            ->get_all();

        foreach (is_array($appeals) ? $appeals : [] as $appeal) {
            $name = basename((string) ($appeal['document_url'] ?? ''));

            if (preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $name)) {
                $path = $this->letterDirectory . $name;

                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        // Delete children before deleting the user.
        $this->db->table('appeals')
            ->where('student_id', $studentId)
            ->delete();

        $this->db->table('student_attendance')
            ->where('student_id', $studentId)
            ->delete();

        // Student self-checks can use the student's own ID as recorded_by.
        $this->db->table('student_attendance')
            ->where('recorded_by', $studentId)
            ->delete();

        $this->db->table('class_enrollments')
            ->where('student_id', $studentId)
            ->delete();

        $this->db->table('staff_attendance')
            ->where('staff_id', $studentId)
            ->delete();

        $this->db->table('note_logs')
            ->where('author_id', $studentId)
            ->delete();

        $this->db->table('note_logs')
            ->where('target_user_id', $studentId)
            ->delete();

        $this->db->table('interventions')
            ->where('student_id', $studentId)
            ->delete();

        $this->db->table('interventions')
            ->where('teacher_id', $studentId)
            ->delete();

        // The refresh_tokens table is created by some API-library setups.
        // If it exists with the usual user_id field, remove those tokens too.
        try {
            $this->db->table('refresh_tokens')
                ->where('user_id', $studentId)
                ->delete();
        } catch (Throwable $e) {
            // Continue if this installation stores refresh tokens differently.
        }

        return (bool) $this->db->table('users')
            ->where('user_id', $studentId)
            ->delete();
    }

    private function shapeAppeal(array $appeal): ?array
    {
        $attendance = $this->AttendanceModel->attendance_by_id(
            (int) ($appeal['attendance_id'] ?? 0)
        );

        if (!is_array($attendance)) {
            return null;
        }

        $class = $this->AttendanceModel->class_by_id(
            (int) ($attendance['class_id'] ?? 0)
        );

        $student = $this->UserModel->find_by(
            'user_id',
            (int) ($appeal['student_id'] ?? 0)
        );

        return [
            'appeal_id' => (int) ($appeal['appeal_id'] ?? 0),
            'student_id' => (int) ($appeal['student_id'] ?? 0),

            'student_name' => is_array($student)
                ? trim(
                    (string) ($student['first_name'] ?? '') . ' ' .
                    (string) ($student['last_name'] ?? '')
                )
                : 'Student',

            'student_email' => is_array($student)
                ? (string) ($student['email'] ?? '')
                : '',

            'attendance_id' => (int) ($attendance['attendance_id'] ?? 0),
            'class_id' => (int) ($attendance['class_id'] ?? 0),
            'date' => (string) ($attendance['date'] ?? ''),
            'current_status' => (string) ($attendance['status'] ?? ''),

            'subject_code' => is_array($class)
                ? (string) ($class['subject_code'] ?? '')
                : '',

            'subject_name' => is_array($class)
                ? (string) ($class['subject_name'] ?? '')
                : '',

            'reason' => (string) ($appeal['reason'] ?? ''),
            'status' => (string) ($appeal['status'] ?? 'Pending'),
            'submitted_at' => (string) ($appeal['submitted_at'] ?? ''),
            'has_document' => !empty($appeal['document_url']),
        ];
    }

    private function authenticatedApiUser(): ?array
    {
        $token = $this->api->get_bearer_token();
        $payload = $token ? $this->api->validate_jwt($token) : null;

        $subject = is_array($payload)
            ? ($payload['sub'] ?? $payload['id'] ?? null)
            : null;

        $userId = filter_var($subject, FILTER_VALIDATE_INT);

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

        if (!in_array(
            strtolower((string) ($user['role'] ?? '')),
            ['teacher', 'student'],
            true
        )) {
            $this->api->respond_error(
                'This portal endpoint is restricted to teachers and students.',
                403
            );
            return null;
        }

        return $user;
    }

    private function publicUser(array $user): array
    {
        return [
            'user_id' => (int) ($user['user_id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'first_name' => (string) ($user['first_name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'contact_number' => (string) ($user['contact_number'] ?? ''),
            'role' => strtolower((string) ($user['role'] ?? '')),
        ];
    }
}
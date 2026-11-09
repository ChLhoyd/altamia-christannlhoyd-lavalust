<?php

class AdminApiController extends Controller
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

    public function accounts()
    {
        $this->api->require_method('GET');

        if (!$this->authenticatedAdmin()) {
            return;
        }

        $users = $this->db->table('users')->get_all();
        $classes = $this->db->table('classes')->get_all();
        $enrollments = $this->db->table('class_enrollments')->get_all();

        $classRows = is_array($classes)
            ? array_values($classes)
            : [];

        foreach ($classRows as &$class) {
            $teacher = $this->UserModel->find_by(
                'user_id',
                (int) ($class['teacher_id'] ?? 0)
            );

            $class['teacher_name'] = is_array($teacher)
                ? trim(
                    (string) ($teacher['first_name'] ?? '') . ' ' .
                    (string) ($teacher['last_name'] ?? '')
                )
                : '';
        }

        unset($class);

        $enrolledClasses = [];

        if (is_array($enrollments)) {
            foreach ($enrollments as $enrollment) {
                $studentId = (int) ($enrollment['student_id'] ?? 0);
                $classId = (int) ($enrollment['class_id'] ?? 0);

                if ($studentId > 0 && $classId > 0) {
                    $enrolledClasses[$studentId][] = $classId;
                }
            }
        }

        $accounts = [];
        $deletedAccounts = [];

        if (is_array($users)) {
            foreach ($users as $user) {
                $role = strtolower((string) ($user['role'] ?? ''));

                if (!in_array($role, ['teacher', 'student', 'admin'], true)) {
                    continue;
                }

                // Soft-deleted accounts are listed separately (recoverable).
                if (!empty($user['deleted_at'])) {
                    $deletedAccounts[] = [
                        'user_id' => (int) ($user['user_id'] ?? 0),
                        'first_name' => (string) ($user['first_name'] ?? ''),
                        'last_name' => (string) ($user['last_name'] ?? ''),
                        'email' => (string) ($user['email'] ?? ''),
                        'role' => $role,
                        'deleted_at' => (string) $user['deleted_at'],
                    ];

                    continue;
                }

                $assigned = [];

                $ids = $role === 'student'
                    ? ($enrolledClasses[(int) ($user['user_id'] ?? 0)] ?? [])
                    : $this->teacherClassIds(
                        (int) ($user['user_id'] ?? 0),
                        $classRows
                    );

                foreach ($ids as $id) {
                    foreach ($classRows as $class) {
                        if (
                            (int) ($class['class_id'] ?? 0) === (int) $id
                        ) {
                            $assigned[] = [
                                'class_id' => (int) $class['class_id'],
                                'subject_code' => (string) ($class['subject_code'] ?? ''),
                                'subject_name' => (string) ($class['subject_name'] ?? ''),
                            ];

                            break;
                        }
                    }
                }

                $accounts[] = [
                    'user_id' => (int) ($user['user_id'] ?? 0),
                    'first_name' => (string) ($user['first_name'] ?? ''),
                    'last_name' => (string) ($user['last_name'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                    'contact_number' => (string) ($user['contact_number'] ?? ''),
                    'rfid_uid' => (string) ($user['rfid_uid'] ?? ''),
                    'role' => $role,
                    'assigned_classes' => $assigned,
                ];
            }
        }

        $this->api->respond([
            'success' => true,
            'accounts' => $accounts,
            'deleted_accounts' => $deletedAccounts,
            'classes' => $classRows,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/admin/accounts
    |--------------------------------------------------------------------------
    */

    public function create_account()
    {
        $this->api->require_method('POST');

        if (!$this->authenticatedAdmin()) {
            return;
        }

        $input = $this->api->body();

        $role = strtolower(trim((string) ($input['role'] ?? '')));
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $contactNumber = trim((string) ($input['contact_number'] ?? ''));
        $classId = (int) ($input['class_id'] ?? 0);

        if (!in_array($role, ['teacher', 'student', 'admin'], true)) {
            $this->api->respond_error(
                'Choose Student, Teacher, or Administrator as the account type.',
                422
            );
            return;
        }

        if (
            $firstName === '' ||
            $lastName === '' ||
            strlen($firstName) > 100 ||
            strlen($lastName) > 100
        ) {
            $this->api->respond_error(
                'Enter a valid first name and last name.',
                422
            );
            return;
        }

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150
        ) {
            $this->api->respond_error(
                'Enter a valid email address.',
                422
            );
            return;
        }

        if (strlen($password) < 8 || strlen($password) > 200) {
            $this->api->respond_error(
                'The temporary password must contain at least 8 characters.',
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

        if ($role === 'student' && $classId > 0) {
            $class = $this->AttendanceModel->class_by_id($classId);

            if (!is_array($class)) {
                $this->api->respond_error(
                    'The selected class does not exist.',
                    404
                );
                return;
            }
        }

        $inserted = $this->db->table('users')->insert([
            'email' => $email,
            'password_hash' => password_hash(
                $password,
                PASSWORD_DEFAULT
            ),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role,
            'contact_number' => $contactNumber,
        ]);

        if ($inserted === false) {
            $this->api->respond_error(
                'The account could not be created. Check the users table columns.',
                500
            );
            return;
        }

        $createdUser = $this->UserModel->find_by('email', $email);
        $createdId = (int) ($createdUser['user_id'] ?? 0);

        // Enroll newly created student if a class was selected.
        if ($role === 'student' && $classId > 0 && $createdId > 0) {
            $enrollment = $this->db->table('class_enrollments')->insert([
                'class_id' => $classId,
                'student_id' => $createdId,
            ]);

            if ($enrollment === false) {
                $this->db->table('users')
                    ->where('user_id', $createdId)
                    ->delete();

                $this->api->respond_error(
                    'The student account was not enrolled. The account creation was rolled back.',
                    500
                );
                return;
            }
        }

        $this->api->respond([
            'success' => true,
            'message' => ucfirst($role) . ' account created successfully.',
            'user_id' => $createdId,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/admin/accounts/delete
    |--------------------------------------------------------------------------
    | Soft delete: the account is disabled (cannot sign in, hidden from lists)
    | but ALL of its data is kept, so it can be restored later.
    |--------------------------------------------------------------------------
    */

    public function delete_student()
    {
        $this->api->require_method('POST');

        $admin = $this->authenticatedAdmin();

        if (!$admin) {
            return;
        }

        $input = $this->api->body();

        $accountId = (int) ($input['student_id'] ?? $input['user_id'] ?? 0);

        $account = $accountId > 0
            ? $this->UserModel->find_by('user_id', $accountId)
            : null;

        if (
            !is_array($account) ||
            !in_array(
                strtolower((string) ($account['role'] ?? '')),
                ['student', 'teacher'],
                true
            )
        ) {
            $this->api->respond_error(
                'The selected account was not found.',
                404
            );
            return;
        }

        if (!$this->ensureDeletedColumn()) {
            $this->api->respond_error(
                'The users table needs a deleted_at column. Run: ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL;',
                500
            );
            return;
        }

        $this->db->table('users')
            ->where('user_id', $accountId)
            ->update([
                'deleted_at' => $this->manilaNow(),
            ]);

        // Sign the account out everywhere.
        try {
            $this->db->table('refresh_tokens')
                ->where('user_id', $accountId)
                ->delete();
        } catch (Throwable $ignored) {
            // Optional table/schema.
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Account deleted. It can be restored from "Recently Deleted Accounts".',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/admin/accounts/restore
    |--------------------------------------------------------------------------
    */

    public function restore_account()
    {
        $this->api->require_method('POST');

        if (!$this->authenticatedAdmin()) {
            return;
        }

        $input = $this->api->body();
        $accountId = (int) ($input['user_id'] ?? 0);

        $account = $accountId > 0
            ? $this->UserModel->find_any('user_id', $accountId)
            : null;

        if (!is_array($account) || empty($account['deleted_at'])) {
            $this->api->respond_error(
                'That deleted account was not found.',
                404
            );
            return;
        }

        $this->db->table('users')
            ->where('user_id', $accountId)
            ->update(['deleted_at' => null]);

        $this->api->respond([
            'success' => true,
            'message' => 'Account restored. The user can sign in again.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/admin/accounts/update
    |--------------------------------------------------------------------------
    */

    public function update_account()
    {
        $this->api->require_method('POST');

        if (!$this->authenticatedAdmin()) {
            return;
        }

        $input = $this->api->body();

        $accountId = (int) ($input['user_id'] ?? 0);

        $account = $accountId > 0
            ? $this->UserModel->find_by('user_id', $accountId)
            : null;

        if (
            !is_array($account) ||
            !in_array(
                strtolower((string) ($account['role'] ?? '')),
                ['student', 'teacher'],
                true
            )
        ) {
            $this->api->respond_error(
                'The selected account was not found.',
                404
            );
            return;
        }

        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $contact = trim((string) ($input['contact_number'] ?? ''));
        $rfid = trim((string) ($input['rfid_uid'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if (
            $firstName === '' || $lastName === '' ||
            strlen($firstName) > 100 || strlen($lastName) > 100
        ) {
            $this->api->respond_error(
                'Enter a valid first name and last name.',
                422
            );
            return;
        }

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150
        ) {
            $this->api->respond_error('Enter a valid email address.', 422);
            return;
        }

        if (strlen($contact) > 30 || strlen($rfid) > 64) {
            $this->api->respond_error(
                'One or more fields exceed their allowed length.',
                422
            );
            return;
        }

        $sameEmail = $this->UserModel->find_any('email', $email);

        if (
            is_array($sameEmail) &&
            (int) $sameEmail['user_id'] !== $accountId
        ) {
            $this->api->respond_error(
                'That email address is already registered.',
                409
            );
            return;
        }

        $updates = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'contact_number' => $contact,
            'rfid_uid' => $rfid !== '' ? strtoupper($rfid) : null,
        ];

        if ($rfid !== '') {
            $owner = $this->db->table('users')
                ->where('rfid_uid', strtoupper($rfid))
                ->get();

            if (
                is_array($owner) &&
                (int) ($owner['user_id'] ?? 0) !== $accountId
            ) {
                $this->api->respond_error(
                    'That RFID card is already assigned to another account.',
                    409
                );
                return;
            }
        }

        // Password is optional: blank keeps the current password.
        if ($password !== '') {
            if (strlen($password) < 8 || strlen($password) > 200) {
                $this->api->respond_error(
                    'The new password must contain at least 8 characters.',
                    422
                );
                return;
            }

            $updates['password_hash'] = password_hash(
                $password,
                PASSWORD_DEFAULT
            );
        }

        $this->db->table('users')
            ->where('user_id', $accountId)
            ->update($updates);

        $this->api->respond([
            'success' => true,
            'message' => 'Account updated successfully.',
        ]);
    }


    public function gate_logs()
    {
        $this->api->require_method('GET');
        if (!$this->authenticatedAdmin()) return;

        try {
            $rows = $this->db->table('rfid_gate_logs')
                ->order_by('scanned_at', 'DESC')
                ->limit(250)
                ->get_all();
        } catch (Throwable $e) {
            $this->api->respond_error('Gate log table is not installed. Run the supplied SQL migration.', 500);
            return;
        }

        $logs = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $person = $this->UserModel->find_by('user_id', (int) ($row['user_id'] ?? 0));
            $logs[] = [
                'log_id' => (int) ($row['log_id'] ?? 0),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'name' => is_array($person) ? trim((string) ($person['first_name'] ?? '') . ' ' . (string) ($person['last_name'] ?? '')) : 'Unknown account',
                'email' => (string) ($person['email'] ?? ''),
                'role' => (string) ($row['role'] ?? ''),
                'event_type' => (string) ($row['event_type'] ?? ''),
                'scanned_at' => (string) ($row['scanned_at'] ?? ''),
                'device_name' => (string) ($row['device_name'] ?? ''),
            ];
        }
        $this->api->respond(['success' => true, 'logs' => $logs]);
    }

    private function ensureDeletedColumn(): bool
    {
        try {
            $stmt = $this->db->raw(
                "SHOW COLUMNS FROM `users` LIKE 'deleted_at'"
            );

            if ($stmt && $stmt->fetch()) {
                return true;
            }

            $this->db->raw(
                'ALTER TABLE `users` ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL'
            );

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function manilaNow(): string
    {
        return (new DateTimeImmutable(
            'now',
            new DateTimeZone('Asia/Manila')
        ))->format('Y-m-d H:i:s');
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/admin/rfid
    |--------------------------------------------------------------------------
    | Students and the RFID card (if any) registered to each of them.
    |--------------------------------------------------------------------------
    */

    public function rfid_list()
    {
        $this->api->require_method('GET');

        if (!$this->authenticatedAdmin()) {
            return;
        }

        $users = $this->db->table('users')->get_all();
        $students = [];

        foreach (is_array($users) ? $users : [] as $user) {
            if (
                !in_array(strtolower((string) ($user['role'] ?? '')), ['student', 'teacher', 'staff'], true) ||
                !empty($user['deleted_at'])
            ) {
                continue;
            }

            $students[] = [
                'user_id' => (int) $user['user_id'],
                'first_name' => (string) ($user['first_name'] ?? ''),
                'last_name' => (string) ($user['last_name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'role' => strtolower((string) ($user['role'] ?? '')),
                'rfid_uid' => (string) ($user['rfid_uid'] ?? ''),
            ];
        }

        usort($students, static function ($a, $b) {
            return strcasecmp(
                $a['last_name'] . $a['first_name'],
                $b['last_name'] . $b['first_name']
            );
        });

        $this->api->respond([
            'success' => true,
            'users' => $students,
            'students' => array_values(array_filter($students, static fn($person) => $person['role'] === 'student')),
            'teachers' => array_values(array_filter($students, static fn($person) => $person['role'] === 'teacher')),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/admin/rfid/assign   { user_id, rfid_uid }
    |--------------------------------------------------------------------------
    | Saves the card code used by students, teachers, or staff.
    | An empty rfid_uid removes the card from the account.
    |--------------------------------------------------------------------------
    */

    public function rfid_assign()
    {
        $this->api->require_method('POST');

        if (!$this->authenticatedAdmin()) {
            return;
        }

        $input = $this->api->body();

        $studentId = (int) ($input['user_id'] ?? 0);
        $code = strtoupper(trim((string) ($input['rfid_uid'] ?? '')));

        $student = $studentId > 0
            ? $this->UserModel->find_by('user_id', $studentId)
            : null;

        if (
            !is_array($student) ||
            !in_array(strtolower((string) ($student['role'] ?? '')), ['student', 'teacher', 'staff'], true)
        ) {
            $this->api->respond_error(
                'The selected account was not found.',
                404
            );
            return;
        }

        if ($code !== '') {
            if (!preg_match('/^[A-Z0-9:_\-]{3,64}$/', $code)) {
                $this->api->respond_error(
                    'The RFID code may only contain letters, numbers, colon, dash or underscore (3 to 64 characters).',
                    422
                );
                return;
            }

            $owner = $this->db->table('users')
                ->where('rfid_uid', $code)
                ->get();

            if (
                is_array($owner) &&
                (int) ($owner['user_id'] ?? 0) !== $studentId
            ) {
                $this->api->respond_error(
                    'That RFID card is already assigned to another account.',
                    409
                );
                return;
            }
        }

        $this->db->table('users')
            ->where('user_id', $studentId)
            ->update([
                'rfid_uid' => $code !== '' ? $code : null,
            ]);

        $this->api->respond([
            'success' => true,
            'rfid_uid' => $code,
            'message' => $code !== ''
                ? 'RFID card saved. The student can now use it for RFID attendance.'
                : 'RFID card removed from the student.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/admin/notes
    |--------------------------------------------------------------------------
    */

    public function notes()
    {
        $this->api->require_method('GET');

        if (!$this->authenticatedAdmin()) {
            return;
        }

        $rows = $this->TeacherStudentNoteModel->latest_all();

        $this->api->respond([
            'success' => true,
            'notes' => $this->decorateNotes($rows),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/admin/notes
    |--------------------------------------------------------------------------
    */

    public function create_note()
    {
        $this->api->require_method('POST');

        $admin = $this->authenticatedAdmin();

        if (!$admin) {
            return;
        }

        $input = $this->api->body();

        $studentId = (int) ($input['student_id'] ?? 0);
        $classId = (int) ($input['class_id'] ?? 0);
        $title = trim((string) ($input['title'] ?? ''));
        $noteText = trim((string) ($input['note_text'] ?? ''));
        $noteType = trim((string) ($input['note_type'] ?? 'General'));

        $allowedTypes = [
            'General',
            'Academic',
            'Behavior',
            'Attendance',
            'Guidance',
        ];

        $student = $studentId > 0
            ? $this->UserModel->find_by('user_id', $studentId)
            : null;

        if (
            !is_array($student) ||
            !in_array(strtolower((string) ($student['role'] ?? '')), ['student', 'teacher', 'staff'], true)
        ) {
            $this->api->respond_error(
                'Choose a valid student account.',
                422
            );
            return;
        }

        if (
            $title === '' ||
            strlen($title) > 150 ||
            strlen($noteText) < 3 ||
            strlen($noteText) > 5000
        ) {
            $this->api->respond_error(
                'Enter a title and note details (3 to 5000 characters).',
                422
            );
            return;
        }

        if (!in_array($noteType, $allowedTypes, true)) {
            $noteType = 'General';
        }

        if ($classId > 0) {
            $class = $this->AttendanceModel->class_by_id($classId);

            $enrollments = $this->AttendanceModel->enrollments_for_class(
                $classId
            );

            $isEnrolled = false;

            if (is_array($enrollments)) {
                foreach ($enrollments as $enrollment) {
                    if (
                        (int) ($enrollment['student_id'] ?? 0) === $studentId
                    ) {
                        $isEnrolled = true;
                        break;
                    }
                }
            }

            if (!is_array($class) || !$isEnrolled) {
                $this->api->respond_error(
                    'The selected student is not enrolled in the selected class.',
                    422
                );
                return;
            }
        } else {
            $classId = null;
        }

        $saved = $this->TeacherStudentNoteModel->create_note([
            'author_id' => (int) $admin['user_id'],
            'author_role' => 'admin',
            'student_id' => $studentId,
            'class_id' => $classId,
            'note_type' => $noteType,
            'title' => $title,
            'note_text' => $noteText,
        ]);

        if ($saved === false) {
            $this->api->respond_error(
                'The administrator note could not be saved.',
                500
            );
            return;
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Administrator note saved successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    private function authenticatedAdmin(): ?array
    {
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
            strtolower(trim((string) ($user['role'] ?? ''))) !== 'admin'
        ) {
            $this->api->respond_error(
                'Administrator access is required.',
                403
            );
            return null;
        }

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: TEACHER CLASS IDS
    |--------------------------------------------------------------------------
    */

    private function teacherClassIds(
        int $teacherId,
        array $classes
    ): array {
        $ids = [];

        foreach ($classes as $class) {
            if ((int) ($class['teacher_id'] ?? 0) === $teacherId) {
                $ids[] = (int) ($class['class_id'] ?? 0);
            }
        }

        return array_values(array_filter($ids));
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: FORMAT NOTES
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
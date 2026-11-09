<?php

/**
 * Admin: create / edit / delete class schedules, assign students to classes,
 *        review transfer requests.
 * Teacher: view own schedule data, request a student transfer between classes.
 */
class ClassManagementApiController extends Controller
{
    private const DAY_CODES = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];

    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->library('api');
        $this->call->model('UserModel');
        $this->call->model('AttendanceModel');
    }

    /* =========================================================
       ADMIN: GET /api/admin/classes
       ========================================================= */

    public function admin_index()
    {
        $this->api->require_method('GET');

        if (!$this->authenticated('admin')) {
            return;
        }

        $users = $this->activeUsers();
        $enrollments = $this->rows($this->db->table('class_enrollments')->get_all());

        $classes = [];

        foreach ($this->rows($this->db->table('classes')->get_all()) as $class) {
            $classes[] = $this->shapeClass($class, $users, $enrollments);
        }

        usort($classes, static function ($a, $b) {
            return strcasecmp($a['subject_code'] . $a['subject_name'], $b['subject_code'] . $b['subject_name']);
        });

        $teachers = [];
        $students = [];

        foreach ($users as $user) {
            $role = strtolower((string) ($user['role'] ?? ''));
            $entry = $this->person($user);

            if ($role === 'teacher') {
                $teachers[] = $entry;
            } elseif ($role === 'student') {
                $students[] = $entry;
            }
        }

        $this->api->respond([
            'success' => true,
            'classes' => $classes,
            'teachers' => $teachers,
            'students' => $students,
        ]);
    }

    /* =========================================================
       ADMIN: POST /api/admin/classes   (create)
       ========================================================= */

    public function admin_create()
    {
        $this->api->require_method('POST');

        if (!$this->authenticated('admin')) {
            return;
        }

        $data = $this->validatedClassInput($this->api->body());

        if (is_string($data)) {
            $this->api->respond_error($data, 422);
            return;
        }

        $conflict = $this->scheduleConflict($data, 0);

        if ($conflict !== null) {
            $this->api->respond_error($conflict, 409);
            return;
        }

        // Build the column list BEFORE starting the query chain:
        // onlyColumns() runs its own query, which would reset the chain.
        $data['grace_minutes'] = 10;
        $fields = $this->onlyColumns($data);

        try {
            $this->db->table('classes')->insert($fields);
        } catch (Throwable $e) {
            $this->api->respond_error(
                'The class could not be created: ' . $e->getMessage(),
                500
            );
            return;
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Class schedule created.',
        ]);
    }

    /* =========================================================
       ADMIN: POST /api/admin/classes/update
       ========================================================= */

    public function admin_update()
    {
        $this->api->require_method('POST');

        if (!$this->authenticated('admin')) {
            return;
        }

        $input = $this->api->body();
        $classId = (int) ($input['class_id'] ?? 0);

        if (!is_array($this->AttendanceModel->class_by_id($classId))) {
            $this->api->respond_error('That class was not found.', 404);
            return;
        }

        $data = $this->validatedClassInput($input);

        if (is_string($data)) {
            $this->api->respond_error($data, 422);
            return;
        }

        $conflict = $this->scheduleConflict($data, $classId);

        if ($conflict !== null) {
            $this->api->respond_error($conflict, 409);
            return;
        }

        // The new schedule must not overlap another class of a student
        // who is already enrolled here.
        $clashing = [];

        foreach ($this->rows($this->AttendanceModel->enrollments_for_class($classId)) as $enrollment) {
            $studentId = (int) ($enrollment['student_id'] ?? 0);
            $problem = $this->studentScheduleConflict($studentId, $data + ['class_id' => $classId], [$classId]);

            if ($problem !== null) {
                $student = $this->UserModel->find_any('user_id', $studentId);
                $clashing[] = $this->fullName($student, 'Student #' . $studentId);
            }
        }

        if ($clashing) {
            $this->api->respond_error(
                'This schedule overlaps another class of: ' . implode(', ', array_slice($clashing, 0, 5))
                    . (count($clashing) > 5 ? ' and ' . (count($clashing) - 5) . ' more' : '')
                    . '. Choose a different time or remove them from this class first.',
                409
            );
            return;
        }

        $fields = $this->onlyColumns($data);

        try {
            $this->db->table('classes')
                ->where('class_id', $classId)
                ->update($fields);
        } catch (Throwable $e) {
            $this->api->respond_error(
                'The class could not be updated: ' . $e->getMessage(),
                500
            );
            return;
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Class schedule updated.',
        ]);
    }

    /* =========================================================
       ADMIN: POST /api/admin/classes/delete
       ========================================================= */

    public function admin_delete()
    {
        $this->api->require_method('POST');

        if (!$this->authenticated('admin')) {
            return;
        }

        $input = $this->api->body();
        $classId = (int) ($input['class_id'] ?? 0);

        if (!is_array($this->AttendanceModel->class_by_id($classId))) {
            $this->api->respond_error('That class was not found.', 404);
            return;
        }

        // Never silently destroy attendance history or notes.
        $hasAttendance = $this->db->table('student_attendance')
            ->where('class_id', $classId)
            ->get();

        if (is_array($hasAttendance)) {
            $this->api->respond_error(
                'This class already has attendance records, so it cannot be deleted. Edit it instead, or remove its students.',
                409
            );
            return;
        }

        try {
            $hasNotes = $this->db->table('teacher_student_notes')
                ->where('class_id', $classId)
                ->get();
        } catch (Throwable $e) {
            $hasNotes = null;
        }

        if (is_array($hasNotes)) {
            $this->api->respond_error(
                'This class is linked to student notes, so it cannot be deleted. Edit it instead.',
                409
            );
            return;
        }

        $this->db->table('class_enrollments')->where('class_id', $classId)->delete();

        $this->ensureTransferTable();
        $this->db->table('class_transfer_requests')->where('from_class_id', $classId)->delete();
        $this->db->table('class_transfer_requests')->where('to_class_id', $classId)->delete();

        $this->db->table('classes')->where('class_id', $classId)->delete();

        $this->api->respond([
            'success' => true,
            'message' => 'Class deleted.',
        ]);
    }

    /* =========================================================
       ADMIN: POST /api/admin/classes/enroll   { class_id, student_id }
       ========================================================= */

    public function admin_enroll()
    {
        $this->api->require_method('POST');

        if (!$this->authenticated('admin')) {
            return;
        }

        $input = $this->api->body();
        $classId = (int) ($input['class_id'] ?? 0);
        $studentId = (int) ($input['student_id'] ?? 0);

        if (!is_array($this->AttendanceModel->class_by_id($classId))) {
            $this->api->respond_error('That class was not found.', 404);
            return;
        }

        if (!$this->isStudent($studentId)) {
            $this->api->respond_error('Choose a valid student account.', 422);
            return;
        }

        if ($this->AttendanceModel->is_student_enrolled($classId, $studentId)) {
            $this->api->respond_error('That student is already enrolled in this class.', 409);
            return;
        }

        $overlap = $this->studentScheduleConflict(
            $studentId,
            $this->AttendanceModel->class_by_id($classId),
            []
        );

        if ($overlap !== null) {
            $this->api->respond_error($overlap, 409);
            return;
        }

        $saved = $this->db->table('class_enrollments')->insert([
            'class_id' => $classId,
            'student_id' => $studentId,
        ]);

        if ($saved === false) {
            $this->api->respond_error('The student could not be assigned to the class.', 500);
            return;
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Student assigned to the class.',
        ]);
    }

    /* =========================================================
       ADMIN: POST /api/admin/classes/unenroll
       ========================================================= */

    public function admin_unenroll()
    {
        $this->api->require_method('POST');

        if (!$this->authenticated('admin')) {
            return;
        }

        $input = $this->api->body();
        $classId = (int) ($input['class_id'] ?? 0);
        $studentId = (int) ($input['student_id'] ?? 0);

        if (!$this->AttendanceModel->is_student_enrolled($classId, $studentId)) {
            $this->api->respond_error('That student is not enrolled in this class.', 404);
            return;
        }

        // Attendance history is kept; only the enrollment is removed.
        $this->db->table('class_enrollments')
            ->where('class_id', $classId)
            ->where('student_id', $studentId)
            ->delete();

        $this->api->respond([
            'success' => true,
            'message' => 'Student removed from the class. Past attendance records were kept.',
        ]);
    }

    /* =========================================================
       ADMIN: GET /api/admin/transfers
       ========================================================= */

    public function admin_transfers()
    {
        $this->api->require_method('GET');

        if (!$this->authenticated('admin')) {
            return;
        }

        $this->ensureTransferTable();

        $rows = $this->rows(
            $this->db->table('class_transfer_requests')
                ->order_by('requested_at', 'DESC')
                ->get_all()
        );

        $this->api->respond([
            'success' => true,
            'requests' => $this->shapeTransfers($rows),
        ]);
    }

    /* =========================================================
       ADMIN: POST /api/admin/transfers/review { request_id, status }
       ========================================================= */

    public function admin_review_transfer()
    {
        $this->api->require_method('POST');

        $admin = $this->authenticated('admin');

        if (!$admin) {
            return;
        }

        $this->ensureTransferTable();

        $input = $this->api->body();
        $requestId = (int) ($input['request_id'] ?? 0);
        $status = ucfirst(strtolower(trim((string) ($input['status'] ?? ''))));

        if ($requestId < 1 || !in_array($status, ['Approved', 'Rejected'], true)) {
            $this->api->respond_error('Choose a valid request and decision.', 422);
            return;
        }

        $request = $this->db->table('class_transfer_requests')
            ->where('request_id', $requestId)
            ->get();

        if (!is_array($request) || (string) $request['status'] !== 'Pending') {
            $this->api->respond_error('The request was not found or was already reviewed.', 409);
            return;
        }

        $message = 'Transfer request rejected.';

        if ($status === 'Approved') {
            $studentId = (int) $request['student_id'];
            $fromId = (int) $request['from_class_id'];
            $toId = (int) $request['to_class_id'];

            if (!$this->isStudent($studentId)) {
                $this->api->respond_error('The student account no longer exists.', 409);
                return;
            }

            if (!is_array($this->AttendanceModel->class_by_id($toId))) {
                $this->api->respond_error('The destination class no longer exists.', 409);
                return;
            }

            if (!$this->AttendanceModel->is_student_enrolled($fromId, $studentId)) {
                $this->api->respond_error('The student is no longer enrolled in the original class.', 409);
                return;
            }

            if ($this->AttendanceModel->is_student_enrolled($toId, $studentId)) {
                $this->api->respond_error('The student is already enrolled in the destination class.', 409);
                return;
            }

            $overlap = $this->studentScheduleConflict(
                $studentId,
                $this->AttendanceModel->class_by_id($toId),
                [$fromId]
            );

            if ($overlap !== null) {
                $this->api->respond_error($overlap, 409);
                return;
            }

            $added = $this->db->table('class_enrollments')->insert([
                'class_id' => $toId,
                'student_id' => $studentId,
            ]);

            if ($added === false) {
                $this->api->respond_error('The transfer could not be completed.', 500);
                return;
            }

            $this->db->table('class_enrollments')
                ->where('class_id', $fromId)
                ->where('student_id', $studentId)
                ->delete();

            $message = 'Transfer approved. The student was moved to the new class.';
        }

        $this->db->table('class_transfer_requests')
            ->where('request_id', $requestId)
            ->update([
                'status' => $status,
                'reviewed_by' => (int) $admin['user_id'],
                'reviewed_at' => $this->manilaNow(),
            ]);

        $this->api->respond([
            'success' => true,
            'message' => $message,
        ]);
    }

    /* =========================================================
       TEACHER: GET /api/teacher/transfers
       ========================================================= */

    public function teacher_transfers()
    {
        $this->api->require_method('GET');

        $teacher = $this->authenticated('teacher');

        if (!$teacher) {
            return;
        }

        $this->ensureTransferTable();

        $teacherId = (int) $teacher['user_id'];
        $users = $this->activeUsers();
        $enrollments = $this->rows($this->db->table('class_enrollments')->get_all());

        $myClasses = [];
        $allClasses = [];

        foreach ($this->rows($this->db->table('classes')->get_all()) as $class) {
            $shaped = $this->shapeClass($class, $users, $enrollments);

            $allClasses[] = [
                'class_id' => $shaped['class_id'],
                'subject_code' => $shaped['subject_code'],
                'subject_name' => $shaped['subject_name'],
                'teacher_name' => $shaped['teacher_name'],
                'schedule_time' => $shaped['schedule_time'],
            ];

            if ((int) $shaped['teacher_id'] === $teacherId) {
                $myClasses[] = $shaped;
            }
        }

        $rows = $this->rows(
            $this->db->table('class_transfer_requests')
                ->where('teacher_id', $teacherId)
                ->order_by('requested_at', 'DESC')
                ->get_all()
        );

        $this->api->respond([
            'success' => true,
            'my_classes' => $myClasses,
            'all_classes' => $allClasses,
            'requests' => $this->shapeTransfers($rows),
        ]);
    }

    /* =========================================================
       TEACHER: POST /api/teacher/transfers
       { student_id, from_class_id, to_class_id, reason }
       ========================================================= */

    public function teacher_request_transfer()
    {
        $this->api->require_method('POST');

        $teacher = $this->authenticated('teacher');

        if (!$teacher) {
            return;
        }

        $this->ensureTransferTable();

        $input = $this->api->body();
        $studentId = (int) ($input['student_id'] ?? 0);
        $fromId = (int) ($input['from_class_id'] ?? 0);
        $toId = (int) ($input['to_class_id'] ?? 0);
        $reason = trim((string) ($input['reason'] ?? ''));

        if ($studentId < 1 || $fromId < 1 || $toId < 1) {
            $this->api->respond_error('Choose the student, the current class, and the new class.', 422);
            return;
        }

        if ($fromId === $toId) {
            $this->api->respond_error('The new class must be different from the current class.', 422);
            return;
        }

        if (strlen($reason) > 500) {
            $this->api->respond_error('The reason must be 500 characters or fewer.', 422);
            return;
        }

        $from = $this->AttendanceModel->class_by_id($fromId);

        if (!is_array($from) || (int) ($from['teacher_id'] ?? 0) !== (int) $teacher['user_id']) {
            $this->api->respond_error('You can only transfer students out of your own classes.', 403);
            return;
        }

        if (!$this->isStudent($studentId) || !$this->AttendanceModel->is_student_enrolled($fromId, $studentId)) {
            $this->api->respond_error('That student is not enrolled in the selected class.', 422);
            return;
        }

        if (!is_array($this->AttendanceModel->class_by_id($toId))) {
            $this->api->respond_error('The destination class was not found.', 404);
            return;
        }

        if ($this->AttendanceModel->is_student_enrolled($toId, $studentId)) {
            $this->api->respond_error('That student is already enrolled in the destination class.', 409);
            return;
        }

        $existing = $this->db->table('class_transfer_requests')
            ->where('student_id', $studentId)
            ->where('from_class_id', $fromId)
            ->where('status', 'Pending')
            ->get();

        if (is_array($existing)) {
            $this->api->respond_error('There is already a pending transfer request for this student and class.', 409);
            return;
        }

        $saved = $this->db->table('class_transfer_requests')->insert([
            'teacher_id' => (int) $teacher['user_id'],
            'student_id' => $studentId,
            'from_class_id' => $fromId,
            'to_class_id' => $toId,
            'reason' => $reason !== '' ? $reason : null,
            'status' => 'Pending',
            'requested_at' => $this->manilaNow(),
        ]);

        if ($saved === false) {
            $this->api->respond_error('The transfer request could not be saved.', 500);
            return;
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Transfer request sent to the administrator for approval.',
        ]);
    }

    /* =========================================================
       TEACHER: POST /api/teacher/transfers/cancel { request_id }
       ========================================================= */

    public function teacher_cancel_transfer()
    {
        $this->api->require_method('POST');

        $teacher = $this->authenticated('teacher');

        if (!$teacher) {
            return;
        }

        $this->ensureTransferTable();

        $input = $this->api->body();
        $requestId = (int) ($input['request_id'] ?? 0);

        $request = $this->db->table('class_transfer_requests')
            ->where('request_id', $requestId)
            ->where('teacher_id', (int) $teacher['user_id'])
            ->where('status', 'Pending')
            ->get();

        if (!is_array($request)) {
            $this->api->respond_error('That pending request was not found.', 404);
            return;
        }

        $this->db->table('class_transfer_requests')
            ->where('request_id', $requestId)
            ->delete();

        $this->api->respond([
            'success' => true,
            'message' => 'Transfer request cancelled.',
        ]);
    }

    /* =========================================================
       HELPERS
       ========================================================= */

    /**
     * Validate and normalise class form input.
     * Returns an array on success or an error string.
     */
    private function validatedClassInput(array $input)
    {
        $code = trim((string) ($input['subject_code'] ?? ''));
        $name = trim((string) ($input['subject_name'] ?? ''));
        $room = trim((string) ($input['room'] ?? ''));
        $teacherId = (int) ($input['teacher_id'] ?? 0);

        if ($code === '' || $name === '' || strlen($code) > 50 || strlen($name) > 150) {
            return 'Enter a subject code and subject name.';
        }

        if (strlen($room) > 100) {
            return 'The room name is too long.';
        }

        $teacher = $teacherId > 0 ? $this->UserModel->find_by('user_id', $teacherId) : null;

        if (!is_array($teacher) || strtolower((string) ($teacher['role'] ?? '')) !== 'teacher') {
            return 'Choose the teacher assigned to this class.';
        }

        $daysInput = $input['class_days'] ?? [];

        if (is_string($daysInput)) {
            $daysInput = explode(',', $daysInput);
        }

        $days = [];

        foreach ((array) $daysInput as $day) {
            $code3 = substr(strtoupper(trim((string) $day)), 0, 3);

            if (in_array($code3, self::DAY_CODES, true) && !in_array($code3, $days, true)) {
                $days[] = $code3;
            }
        }

        if (!$days) {
            return 'Choose at least one class day.';
        }

        usort($days, static function ($a, $b) {
            return array_search($a, self::DAY_CODES, true) <=> array_search($b, self::DAY_CODES, true);
        });

        $start = $this->normaliseTime((string) ($input['start_time'] ?? ''));
        $end = $this->normaliseTime((string) ($input['end_time'] ?? ''));

        if ($start === null || $end === null) {
            return 'Enter a valid start time and end time.';
        }

        if ($end <= $start) {
            return 'The end time must be later than the start time.';
        }

        $dayLabels = array_map(static function ($d) {
            return ucfirst(strtolower($d));
        }, $days);

        return [
            'teacher_id' => $teacherId,
            'subject_code' => $code,
            'subject_name' => $name,
            'room' => $room,
            'class_days' => implode(',', $days),
            'start_time' => $start,
            'end_time' => $end,
            // Human readable text used by the existing portal screens.
            'schedule_time' => implode(', ', $dayLabels) . ' · '
                . $this->clock12($start) . ' - ' . $this->clock12($end),
        ];
    }

    /* ---------------------------------------------------------
       Schedule conflicts: one room, one teacher and one student can
       only be in one class at a time, so the kiosks never have to guess.
       --------------------------------------------------------- */

    /** Days + start/end as comparable numbers, or null if incomplete. */
    private function scheduleOf(array $class): ?array
    {
        $timezone = new DateTimeZone('Asia/Manila');
        $days = $this->AttendanceModel->parse_days($class['class_days'] ?? '');
        $start = $this->AttendanceModel->parse_clock($class['start_time'] ?? '', '2000-01-03', $timezone);
        $end = $this->AttendanceModel->parse_clock($class['end_time'] ?? '', '2000-01-03', $timezone);

        if (!$days || !$start || !$end || $end <= $start) {
            return null;
        }

        return [
            'days' => $days,
            'start' => $start->getTimestamp(),
            'end' => $end->getTimestamp(),
        ];
    }

    private function schedulesOverlap(array $a, array $b): bool
    {
        return count(array_intersect($a['days'], $b['days'])) > 0
            && $a['start'] < $b['end']
            && $b['start'] < $a['end'];
    }

    private function describeClass(array $class): string
    {
        $schedule = $this->scheduleOf($class);
        $label = trim((string) ($class['subject_code'] ?? '') . ' ' . (string) ($class['subject_name'] ?? ''));

        if ($schedule === null) {
            return $label;
        }

        return $label . ' (' . implode(', ', $schedule['days']) . ' '
            . $this->clock12FromTimestamp($schedule['start']) . ' - '
            . $this->clock12FromTimestamp($schedule['end']) . ')';
    }

    private function clock12FromTimestamp(int $timestamp): string
    {
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone('Asia/Manila'))
            ->format('g:i A');
    }

    private function roomKey($room): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $room)));
    }

    /** Same room or same teacher at an overlapping time? */
    private function scheduleConflict(array $data, int $ignoreClassId): ?string
    {
        $mine = $this->scheduleOf($data);

        if ($mine === null) {
            return null;
        }

        foreach ($this->rows($this->db->table('classes')->get_all()) as $other) {
            if ((int) ($other['class_id'] ?? 0) === $ignoreClassId) {
                continue;
            }

            $theirs = $this->scheduleOf($other);

            if ($theirs === null || !$this->schedulesOverlap($mine, $theirs)) {
                continue;
            }

            $roomKey = $this->roomKey($data['room'] ?? '');

            if ($roomKey !== '' && $roomKey === $this->roomKey($other['room'] ?? '')) {
                return 'The room "' . trim((string) $data['room']) . '" is already used by '
                    . $this->describeClass($other) . ' at that time. Choose another room or time.';
            }

            if ((int) ($data['teacher_id'] ?? 0) > 0
                && (int) ($data['teacher_id'] ?? 0) === (int) ($other['teacher_id'] ?? 0)) {
                return 'This teacher already teaches ' . $this->describeClass($other)
                    . ' at that time. Choose another teacher or time.';
            }
        }

        return null;
    }

    /** Does a class overlap another class the student is already in? */
    private function studentScheduleConflict(int $studentId, $class, array $ignoreClassIds): ?string
    {
        if (!is_array($class)) {
            return null;
        }

        $mine = $this->scheduleOf($class);

        if ($mine === null) {
            return null;
        }

        $classId = (int) ($class['class_id'] ?? 0);

        foreach ($this->rows($this->AttendanceModel->student_classes($studentId)) as $other) {
            $otherId = (int) ($other['class_id'] ?? 0);

            if ($otherId === $classId || in_array($otherId, $ignoreClassIds, true)) {
                continue;
            }

            $theirs = $this->scheduleOf($other);

            if ($theirs !== null && $this->schedulesOverlap($mine, $theirs)) {
                return 'The student is already in ' . $this->describeClass($other)
                    . ', which overlaps with ' . $this->describeClass($class) . '.';
            }
        }

        return null;
    }

    private function normaliseTime(string $value): ?string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($value), $m)) {
            return null;
        }

        $h = (int) $m[1];
        $i = (int) $m[2];

        if ($h > 23 || $i > 59) {
            return null;
        }

        return sprintf('%02d:%02d:00', $h, $i);
    }

    private function clock12(string $time): string
    {
        $dt = DateTimeImmutable::createFromFormat('H:i:s', $time);

        return $dt ? $dt->format('g:i A') : $time;
    }

    private $classColumnCache = null;

    /** Only write columns that really exist in the classes table. */
    private function onlyColumns(array $data): array
    {
        if ($this->classColumnCache === null) {
            $this->ensureScheduleColumns();
            $this->classColumnCache = [];

            $stmt = $this->db->raw('SHOW COLUMNS FROM `classes`');

            if ($stmt) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $this->classColumnCache[] = (string) $row['Field'];
                }
            }
        }

        return array_intersect_key($data, array_flip($this->classColumnCache));
    }

    /**
     * The kiosks depend on these columns. If an older database is missing
     * one, add it instead of silently dropping the schedule.
     */
    private function ensureScheduleColumns(): void
    {
        $needed = [
            'class_days' => 'VARCHAR(50) NULL DEFAULT NULL',
            'start_time' => 'TIME NULL DEFAULT NULL',
            'end_time' => 'TIME NULL DEFAULT NULL',
            'grace_minutes' => 'INT NOT NULL DEFAULT 10',
            'schedule_time' => 'VARCHAR(120) NULL DEFAULT NULL',
            'room' => 'VARCHAR(100) NULL DEFAULT NULL',
        ];

        foreach ($needed as $column => $definition) {
            $stmt = $this->db->raw('SHOW COLUMNS FROM `classes` LIKE ?', [$column]);

            if ($stmt && $stmt->fetch()) {
                continue;
            }

            $this->db->raw('ALTER TABLE `classes` ADD COLUMN `' . $column . '` ' . $definition);
        }
    }

    private function ensureTransferTable(): void
    {
        $this->db->raw(
            'CREATE TABLE IF NOT EXISTS `class_transfer_requests` (
                `request_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `teacher_id` INT NOT NULL,
                `student_id` INT NOT NULL,
                `from_class_id` INT NOT NULL,
                `to_class_id` INT NOT NULL,
                `reason` VARCHAR(500) NULL DEFAULT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT \'Pending\',
                `requested_at` DATETIME NULL DEFAULT NULL,
                `reviewed_by` INT NULL DEFAULT NULL,
                `reviewed_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`request_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function shapeTransfers(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $student = $this->UserModel->find_any('user_id', (int) $row['student_id']);
            $teacher = $this->UserModel->find_any('user_id', (int) $row['teacher_id']);
            $from = $this->AttendanceModel->class_by_id((int) $row['from_class_id']);
            $to = $this->AttendanceModel->class_by_id((int) $row['to_class_id']);

            $out[] = [
                'request_id' => (int) $row['request_id'],
                'student_id' => (int) $row['student_id'],
                'student_name' => $this->fullName($student, 'Student removed'),
                'teacher_name' => $this->fullName($teacher, 'Teacher removed'),
                'from_class_id' => (int) $row['from_class_id'],
                'from_class' => $this->classLabel($from),
                'to_class_id' => (int) $row['to_class_id'],
                'to_class' => $this->classLabel($to),
                'reason' => (string) ($row['reason'] ?? ''),
                'status' => (string) ($row['status'] ?? 'Pending'),
                'requested_at' => (string) ($row['requested_at'] ?? ''),
                'reviewed_at' => (string) ($row['reviewed_at'] ?? ''),
            ];
        }

        return $out;
    }

    private function classLabel($class): string
    {
        if (!is_array($class)) {
            return 'Class removed';
        }

        $code = trim((string) ($class['subject_code'] ?? ''));
        $name = trim((string) ($class['subject_name'] ?? ''));

        return $code !== '' ? $code . ' — ' . $name : $name;
    }

    private function fullName($user, string $fallback): string
    {
        if (!is_array($user)) {
            return $fallback;
        }

        return trim(
            (string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? '')
        );
    }

    private function shapeClass(array $class, array $users, array $enrollments): array
    {
        $classId = (int) ($class['class_id'] ?? 0);
        $teacherId = (int) ($class['teacher_id'] ?? 0);

        $students = [];

        foreach ($enrollments as $enrollment) {
            if ((int) ($enrollment['class_id'] ?? 0) !== $classId) {
                continue;
            }

            $sid = (int) ($enrollment['student_id'] ?? 0);

            if (isset($users[$sid]) && strtolower((string) $users[$sid]['role']) === 'student') {
                $students[] = $this->person($users[$sid]);
            }
        }

        usort($students, static function ($a, $b) {
            return strcasecmp($a['last_name'] . $a['first_name'], $b['last_name'] . $b['first_name']);
        });

        return [
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'teacher_name' => isset($users[$teacherId]) ? $this->fullName($users[$teacherId], '') : '',
            'subject_code' => (string) ($class['subject_code'] ?? ''),
            'subject_name' => (string) ($class['subject_name'] ?? ''),
            'room' => (string) ($class['room'] ?? ''),
            'class_days' => (string) ($class['class_days'] ?? ''),
            'start_time' => (string) ($class['start_time'] ?? ''),
            'end_time' => (string) ($class['end_time'] ?? ''),
            'schedule_time' => (string) ($class['schedule_time'] ?? ''),
            'students' => $students,
            'student_count' => count($students),
        ];
    }

    private function person(array $user): array
    {
        return [
            'user_id' => (int) ($user['user_id'] ?? 0),
            'first_name' => (string) ($user['first_name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
        ];
    }

    /** Non-deleted users indexed by user_id. */
    private function activeUsers(): array
    {
        $map = [];

        foreach ($this->rows($this->db->table('users')->get_all()) as $user) {
            if (!empty($user['deleted_at'])) {
                continue;
            }

            $map[(int) $user['user_id']] = $user;
        }

        return $map;
    }

    private function isStudent(int $userId): bool
    {
        $user = $userId > 0 ? $this->UserModel->find_by('user_id', $userId) : null;

        return is_array($user) && strtolower((string) ($user['role'] ?? '')) === 'student';
    }

    private function rows($value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function manilaNow(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))
            ->format('Y-m-d H:i:s');
    }

    private function authenticated(string $requiredRole): ?array
    {
        $token = $this->api->get_bearer_token();
        $payload = $token ? $this->api->validate_jwt($token) : null;

        $subject = is_array($payload)
            ? ($payload['sub'] ?? $payload['id'] ?? null)
            : null;

        $userId = filter_var($subject, FILTER_VALIDATE_INT);

        if (!$userId || $userId < 1) {
            $this->api->respond_error('Unauthorized. Please sign in again.', 401);
            return null;
        }

        $user = $this->UserModel->find_by('user_id', (int) $userId);

        if (!is_array($user)) {
            $this->api->respond_error('The account associated with this token no longer exists.', 401);
            return null;
        }

        if (strtolower(trim((string) ($user['role'] ?? ''))) !== $requiredRole) {
            $this->api->respond_error('You do not have permission to access this endpoint.', 403);
            return null;
        }

        return $user;
    }
}

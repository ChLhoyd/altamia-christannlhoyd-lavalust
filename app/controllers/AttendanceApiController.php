<?php

class AttendanceApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->database();
        $this->call->library('api');
        $this->call->model('AttendanceModel');
        $this->call->model('UserModel');
    }

    public function teacher_dashboard()
    {
        $this->api->require_method('GET');

        $teacher = $this->authenticatedApiUser('teacher');

        if (!$teacher) {
            return;
        }

        // Classes that already ended close automatically (missing = Absent).
        $this->AttendanceModel->close_ended_classes();

        $timezone = new DateTimeZone('Asia/Manila');
        $today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');

        $query = $this->api->get_query_params();
        $requestedClassId = (int) ($query['class_id'] ?? 0);
        $requestedDate = trim((string) ($query['date'] ?? $today));
        $allDates = $requestedDate === 'all';
        if (!$allDates && !preg_match('/^\d{4}-\\d{2}-\\d{2}$/', $requestedDate)) {
            $requestedDate = $today;
        }

        $teacherId = (int) $teacher['user_id'];
        $classes = $this->AttendanceModel->teacher_classes($teacherId);
        $classes = is_array($classes) ? array_values($classes) : [];

        $selectedClass = null;

        foreach ($classes as $class) {
            if (
                $requestedClassId > 0 &&
                (int) ($class['class_id'] ?? 0) === $requestedClassId
            ) {
                $selectedClass = $class;
                break;
            }
        }

        if ($selectedClass === null && count($classes) > 0) {
            $selectedClass = $classes[0];
        }

        $students = [];
        $stats = [
            'Present' => 0,
            'Absent' => 0,
            'Late' => 0,
        ];
        $trend = [];

        if (is_array($selectedClass)) {
            $classId = (int) $selectedClass['class_id'];

            $enrollments = $this->AttendanceModel
                ->enrollments_for_class($classId);

            $enrollments = is_array($enrollments) ? $enrollments : [];

            // Includes attendance submitted by students and records
            // manually saved by the teacher for today's date.
            $records = $allDates
                ? $this->db->table('student_attendance')->where('class_id', $classId)->order_by('date', 'DESC')->get_all()
                : $this->AttendanceModel->attendance_for_class_on_date($classId, $requestedDate);

            $records = is_array($records) ? $records : [];
            $recordsByStudent = [];
            foreach ($records as $record) {
                $studentId = (int) ($record['student_id'] ?? 0);
                if ($studentId > 0) {
                    $recordsByStudent[$studentId][] = $record;
                    $status = (string) ($record['status'] ?? '');
                    if (isset($stats[$status])) $stats[$status]++;
                }
            }

            foreach ($enrollments as $enrollment) {
                $studentId = (int) ($enrollment['student_id'] ?? 0);
                if ($studentId < 1) continue;
                $student = $this->UserModel->find_by('user_id', $studentId);
                if (!is_array($student) || strtolower((string) ($student['role'] ?? '')) !== 'student') continue;

                $studentRecords = $recordsByStudent[$studentId] ?? [];
                if ($allDates && $studentRecords) {
                    foreach ($studentRecords as $record) {
                        $students[] = [
                            'attendance_id' => (int) ($record['attendance_id'] ?? 0),
                            'user_id' => $studentId,
                            'first_name' => (string) ($student['first_name'] ?? ''),
                            'last_name' => (string) ($student['last_name'] ?? ''),
                            'email' => (string) ($student['email'] ?? ''),
                            'status' => (string) ($record['status'] ?? ''),
                            'date' => (string) ($record['date'] ?? ''),
                            'check_in_time' => $record['check_in_time'] ?? null,
                        ];
                    }
                } else {
                    if ($allDates) {
                        continue;
                    }
                    $record = $studentRecords[0] ?? [];
                    $students[] = [
                        'attendance_id' => (int) ($record['attendance_id'] ?? 0),
                        'user_id' => $studentId,
                        'first_name' => (string) ($student['first_name'] ?? ''),
                        'last_name' => (string) ($student['last_name'] ?? ''),
                        'email' => (string) ($student['email'] ?? ''),
                        'status' => (string) ($record['status'] ?? ''),
                        'date' => (string) ($record['date'] ?? $requestedDate),
                        'check_in_time' => $record['check_in_time'] ?? null,
                    ];
                }
            }

            // Seven daily chart points, based on Manila date.
            for ($offset = 6; $offset >= 0; $offset--) {
                $dateObject = (new DateTimeImmutable($today, $timezone))
                    ->modify('-' . $offset . ' days');

                $date = $dateObject->format('Y-m-d');

                $dayRecords = $this->AttendanceModel
                    ->attendance_for_class_on_date($classId, $date);

                $dayCounts = [
                    'Present' => 0,
                    'Absent' => 0,
                    'Late' => 0,
                ];

                if (is_array($dayRecords)) {
                    foreach ($dayRecords as $record) {
                        $status = (string) ($record['status'] ?? '');

                        if (isset($dayCounts[$status])) {
                            $dayCounts[$status]++;
                        }
                    }
                }

                $trend[] = [
                    'label' => $dateObject->format('M j'),
                    'date' => $date,
                    'Present' => $dayCounts['Present'],
                    'Absent' => $dayCounts['Absent'],
                    'Late' => $dayCounts['Late'],
                ];
            }
        }

        $this->api->respond([
            'success' => true,
            'user' => $this->publicUser($teacher),
            'date' => $allDates ? 'all' : $requestedDate,
            'classes' => $classes,
            'selected_class' => $selectedClass,
            'students' => $students,
            'stats' => $stats,
            'trend' => $trend,
        ]);
    }

    public function save_attendance()
    {
        $this->api->require_method('POST');

        $teacher = $this->authenticatedApiUser('teacher');

        if (!$teacher) {
            return;
        }

        $input = $this->api->body();

        $classId = (int) ($input['class_id'] ?? 0);
        $studentId = (int) ($input['student_id'] ?? 0);
        $attendanceDate = trim((string) ($input['date'] ?? ''));
        $status = trim((string) ($input['status'] ?? ''));

        if (
            $classId < 1 ||
            $studentId < 1 ||
            !preg_match('/^\d{4}-\\d{2}-\\d{2}$/', $attendanceDate) ||
            !in_array($status, ['Present', 'Absent', 'Late'], true)
        ) {
            $this->api->respond_error(
                'Choose a class, student, and valid status.',
                422
            );
            return;
        }

        $timezone = new DateTimeZone('Asia/Manila');
        $today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
        if ($attendanceDate > $today) {
            $this->api->respond_error('Future attendance cannot be edited.', 422);
            return;
        }

        $saved = $this->AttendanceModel->save_attendance(
            $classId,
            $studentId,
            (int) $teacher['user_id'],
            $status,
            $attendanceDate
        );

        if (!$saved) {
            $this->api->respond_error(
                'Attendance could not be saved. Verify that the class belongs to your account and the student is enrolled.',
                422
            );
            return;
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Attendance saved successfully.',
        ]);
    }

    public function student_dashboard()
    {
        $this->api->require_method('GET');

        $student = $this->authenticatedApiUser('student');

        if (!$student) {
            return;
        }

        // Classes that already ended close automatically (missing = Absent).
        $this->AttendanceModel->close_ended_classes();

        $studentId = (int) $student['user_id'];

        $classes = $this->AttendanceModel->student_classes($studentId);
        $classes = is_array($classes) ? array_values($classes) : [];

        $classMap = [];

        foreach ($classes as &$class) {
            $classId = (int) ($class['class_id'] ?? 0);

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

            // The options are calculated by the server, using Manila time.
            $class['manual_options'] = $this->studentManualOptions(
                $class,
                $studentId
            );

            $classMap[$classId] = $class;
        }

        unset($class);

        $attendance = $this->AttendanceModel
            ->attendance_for_student($studentId);

        $attendance = is_array($attendance) ? array_values($attendance) : [];

        $stats = [
            'total' => count($attendance),
            'Present' => 0,
            'Absent' => 0,
            'Late' => 0,
        ];

        foreach ($attendance as &$record) {
            $classId = (int) ($record['class_id'] ?? 0);

            if (!isset($classMap[$classId])) {
                $class = $this->AttendanceModel->class_by_id($classId);

                if (is_array($class)) {
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

                    $classMap[$classId] = $class;
                }
            }

            $class = $classMap[$classId] ?? [];

            $record['subject_code'] = (string) ($class['subject_code'] ?? '');
            $record['subject_name'] = (string) (
                $class['subject_name'] ?? ('Class ' . $classId)
            );
            $record['schedule_time'] = (string) ($class['schedule_time'] ?? '');
            $record['room'] = (string) ($class['room'] ?? '');
            $record['teacher_name'] = (string) ($class['teacher_name'] ?? '');

            $status = (string) ($record['status'] ?? '');

            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        unset($record);

        $timezone = new DateTimeZone('Asia/Manila');

        $this->api->respond([
            'success' => true,
            'user' => $this->publicUser($student),
            'date' => (new DateTimeImmutable('now', $timezone))->format('Y-m-d'),
            'classes' => $classes,
            'attendance' => $attendance,
            'stats' => $stats,
            'rfid' => $this->rfidStatus($student, $classes),
        ]);
    }

    /* =========================================================
       STUDENT: RFID ATTENDANCE
       POST /api/student/rfid-attendance   { rfid_uid }

       - Only open while one of the student's classes is in session
         (start_time to end_time, on a scheduled class day).
       - Scan within the first 15 minutes  -> Present
       - Scan after 15 minutes until end   -> Late
       - After end_time the class closes automatically; the next
         class opens when its own start_time arrives.
       ========================================================= */

    public function student_rfid_attendance()
    {
        $this->api->require_method('POST');

        $student = $this->authenticatedApiUser('student');

        if (!$student) {
            return;
        }

        $input = $this->api->body();
        $scanned = strtoupper(trim((string) ($input['rfid_uid'] ?? '')));

        if ($scanned === '') {
            $this->api->respond_error('Scan your RFID card first.', 422);
            return;
        }

        $registered = strtoupper(trim((string) ($student['rfid_uid'] ?? '')));

        if ($registered === '') {
            $this->api->respond_error(
                'No RFID card is registered to your account yet. Ask the administrator to register your card.',
                422
            );
            return;
        }

        if (!hash_equals($registered, $scanned)) {
            $this->api->respond_error(
                'This RFID card is not registered to your account.',
                403
            );
            return;
        }

        $studentId = (int) $student['user_id'];
        $classes = $this->AttendanceModel->student_classes($studentId);
        $state = $this->rfidState(
            is_array($classes) ? array_values($classes) : [],
            $studentId
        );

        if ($state['status'] === 'closed') {
            $this->api->respond_error($state['message'], 422);
            return;
        }

        if (!empty($state['recorded'])) {
            $this->api->respond_error(
                'Your attendance for ' . $state['class']['subject_name']
                    . ' is already recorded as ' . $state['recorded'] . '.',
                409
            );
            return;
        }

        $timezone = new DateTimeZone('Asia/Manila');
        $now = new DateTimeImmutable('now', $timezone);

        // Present only within the first 15 minutes of the class.
        $status = $state['status'] === 'open' ? 'Present' : 'Late';

        $saved = $this->AttendanceModel->record_student_choice(
            (int) $state['class']['class_id'],
            $studentId,
            $now->format('Y-m-d'),
            $status,
            $now->format('Y-m-d H:i:s')
        );

        if (!$saved) {
            $this->api->respond_error(
                'Attendance could not be recorded. A record may already exist.',
                409
            );
            return;
        }

        $this->api->respond([
            'success' => true,
            'status' => $status,
            'message' => $status === 'Present'
                ? 'Present recorded for ' . $state['class']['subject_name'] . '.'
                : 'You scanned after the 15-minute window, so you were marked Late for '
                    . $state['class']['subject_name'] . '.',
        ]);
    }

    private const RFID_PRESENT_MINUTES = 15;

    /**
     * Dashboard block describing the RFID panel (computed on the server
     * with Manila time so the browser clock cannot be trusted).
     */
    private function rfidStatus(array $student, array $classes): array
    {
        $state = $this->rfidState($classes, (int) $student['user_id']);

        $out = [
            'has_card' => trim((string) ($student['rfid_uid'] ?? '')) !== '',
            'status' => $state['status'],
            'message' => $state['message'],
            'recorded' => $state['recorded'] ?? null,
            'present_minutes' => self::RFID_PRESENT_MINUTES,
            'class' => null,
            'next_class' => $state['next_class'] ?? null,
            'server_time' => (new DateTimeImmutable(
                'now',
                new DateTimeZone('Asia/Manila')
            ))->format('Y-m-d H:i:s'),
        ];

        if (!empty($state['class'])) {
            $out['class'] = [
                'class_id' => (int) $state['class']['class_id'],
                'subject_code' => (string) ($state['class']['subject_code'] ?? ''),
                'subject_name' => (string) ($state['class']['subject_name'] ?? ''),
                'room' => (string) ($state['class']['room'] ?? ''),
                'start' => $state['start'],
                'present_until' => $state['present_until'],
                'end' => $state['end'],
            ];
        }

        return $out;
    }

    /**
     * Which class (if any) is open for RFID right now.
     *
     * status: open   = class in session, first 15 minutes (Present)
     *         late   = class in session, after 15 minutes (Late)
     *         closed = no class in session
     */
    private function rfidState(array $classes, int $studentId): array
    {
        $timezone = new DateTimeZone('Asia/Manila');
        $now = new DateTimeImmutable('now', $timezone);
        $date = $now->format('Y-m-d');
        $today = strtoupper($now->format('D'));

        $active = [];
        $next = null;

        foreach ($classes as $class) {
            $days = array_map(
                static function ($day) {
                    return substr(strtoupper(trim($day)), 0, 3);
                },
                explode(',', (string) ($class['class_days'] ?? ''))
            );

            $startText = trim((string) ($class['start_time'] ?? ''));
            $endText = trim((string) ($class['end_time'] ?? ''));

            if (!in_array($today, $days, true) || $startText === '' || $endText === '') {
                continue;
            }

            $start = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $date . ' ' . $startText,
                $timezone
            );

            $end = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $date . ' ' . $endText,
                $timezone
            );

            if (!$start || !$end || $end <= $start) {
                continue;
            }

            if ($now >= $start && $now < $end) {
                $active[] = [$class, $start, $end];
            } elseif ($now < $start) {
                if ($next === null || $start < $next[1]) {
                    $next = [$class, $start];
                }
            }
        }

        $nextInfo = $next === null ? null : [
            'subject_code' => (string) ($next[0]['subject_code'] ?? ''),
            'subject_name' => (string) ($next[0]['subject_name'] ?? ''),
            'start' => $next[1]->format('Y-m-d H:i:s'),
        ];

        if (!$active) {
            return [
                'status' => 'closed',
                'next_class' => $nextInfo,
                'message' => $nextInfo
                    ? 'No class is open right now. Next: ' . $nextInfo['subject_name']
                        . ' opens at ' . $next[1]->format('g:i A') . '.'
                    : 'No class is open right now. RFID attendance opens when a class starts.',
            ];
        }

        // Prefer a class that has no record yet.
        $chosen = null;
        $recorded = null;

        foreach ($active as $item) {
            $existing = $this->AttendanceModel->student_class_attendance(
                (int) $item[0]['class_id'],
                $studentId,
                $date
            );

            if (!$existing) {
                $chosen = $item;
                $recorded = null;
                break;
            }

            if ($chosen === null) {
                $chosen = $item;
                $recorded = (string) ($existing['status'] ?? '');
            }
        }

        [$class, $start, $end] = $chosen;
        $presentUntil = $start->modify('+' . self::RFID_PRESENT_MINUTES . ' minutes');
        $isOpen = $now <= $presentUntil;

        return [
            'status' => $isOpen ? 'open' : 'late',
            'class' => $class,
            'start' => $start->format('Y-m-d H:i:s'),
            'present_until' => $presentUntil->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'recorded' => $recorded,
            'next_class' => $nextInfo,
            'message' => $recorded
                ? 'Attendance already recorded: ' . $recorded . '.'
                : ($isOpen
                    ? 'Scan before ' . $presentUntil->format('g:i A') . ' to be marked Present.'
                    : 'The 15-minute Present window has passed. Scanning now marks you Late until '
                        . $end->format('g:i A') . '.'),
        ];
    }

    public function student_manual_attendance()
    {
        $this->api->require_method('POST');

        $student = $this->authenticatedApiUser('student');

        if (!$student) {
            return;
        }

        $input = $this->api->body();

        $studentId = (int) $student['user_id'];
        $classId = (int) ($input['class_id'] ?? 0);
        $status = trim((string) ($input['status'] ?? ''));

        if (
            $classId < 1 ||
            !in_array($status, ['Present', 'Late', 'Absent'], true)
        ) {
            $this->api->respond_error(
                'Choose a valid class and attendance status.',
                422
            );
            return;
        }

        $class = $this->AttendanceModel->class_by_id($classId);

        if (!is_array($class)) {
            $this->api->respond_error('Class not found.', 404);
            return;
        }

        if (!$this->AttendanceModel->is_student_enrolled($classId, $studentId)) {
            $this->api->respond_error(
                'You are not enrolled in this class.',
                403
            );
            return;
        }

        // Never trust the button state alone. Re-check timing on the server.
        $options = $this->studentManualOptions($class, $studentId);

        if (empty($options['configured'])) {
            $this->api->respond_error($options['message'], 422);
            return;
        }

        if (empty($options['is_class_day'])) {
            $this->api->respond_error(
                'This class is not scheduled for today.',
                422
            );
            return;
        }

        if (!empty($options['has_record'])) {
            $this->api->respond_error(
                'Your attendance has already been recorded. Contact your teacher if it needs correction.',
                409
            );
            return;
        }

        if ($status === 'Present' && empty($options['can_present'])) {
            $this->api->respond_error(
                'The on-time Present window is closed or has not opened yet.',
                422
            );
            return;
        }

        if ($status === 'Late' && empty($options['can_late'])) {
            $this->api->respond_error(
                'Late self-check is available only after the grace period and before class ends.',
                422
            );
            return;
        }

        if ($status === 'Absent' && empty($options['can_absent'])) {
            $this->api->respond_error(
                'The Absent option is not available yet.',
                422
            );
            return;
        }

        $timezone = new DateTimeZone('Asia/Manila');
        $now = new DateTimeImmutable('now', $timezone);
        $date = $now->format('Y-m-d');

        $checkInTime = in_array($status, ['Present', 'Late'], true)
            ? $now->format('Y-m-d H:i:s')
            : null;

        $saved = $this->AttendanceModel->record_student_choice(
            $classId,
            $studentId,
            $date,
            $status,
            $checkInTime
        );

        if (!$saved) {
            $this->api->respond_error(
                'Attendance could not be recorded. A record may already exist.',
                409
            );
            return;
        }

        $messages = [
            'Present' => 'Your on-time attendance has been recorded.',
            'Late' => 'Your late attendance has been recorded.',
            'Absent' => 'Your absence has been recorded in the class record for teacher review.',
        ];

        $this->api->respond([
            'success' => true,
            'message' => $messages[$status],
        ]);
    }

    /**
     * Calculate which self-check options are currently allowed.
     *
     * class_days format: MON,TUE,WED,THU,FRI
     * start_time/end_time format: HH:MM:SS
     */
    private function studentManualOptions(array $class, int $studentId): array
    {
        $timezone = new DateTimeZone('Asia/Manila');
        $now = new DateTimeImmutable('now', $timezone);
        $date = $now->format('Y-m-d');

        $daysText = strtoupper(trim((string) ($class['class_days'] ?? '')));
        $startText = trim((string) ($class['start_time'] ?? ''));
        $endText = trim((string) ($class['end_time'] ?? ''));

        $result = [
            'configured' => false,
            'is_class_day' => false,
            'has_record' => false,
            'current_status' => null,
            'can_present' => false,
            'can_late' => false,
            'can_absent' => false,
            'start_time' => $startText,
            'end_time' => $endText,
            'grace_end_time' => null,
            'message' => 'Your teacher needs to configure the class days and times first.',
        ];

        if ($daysText === '' || $startText === '' || $endText === '') {
            return $result;
        }

        $result['configured'] = true;

        $days = array_map(
            static function ($day) {
                return substr(strtoupper(trim($day)), 0, 3);
            },
            explode(',', $daysText)
        );

        if (!in_array(strtoupper($now->format('D')), $days, true)) {
            $result['message'] = 'This class is not scheduled today.';
            return $result;
        }

        $result['is_class_day'] = true;

        $existing = $this->AttendanceModel->student_class_attendance(
            (int) $class['class_id'],
            $studentId,
            $date
        );

        if ($existing) {
            $result['has_record'] = true;
            $result['current_status'] = (string) ($existing['status'] ?? '');
            $result['message'] = 'Attendance already recorded: '
                . $result['current_status'] . '.';

            return $result;
        }

        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $date . ' ' . $startText,
            $timezone
        );

        $end = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $date . ' ' . $endText,
            $timezone
        );

        if (!$start || !$end || $end <= $start) {
            $result['configured'] = false;
            $result['message'] = 'The class schedule is invalid. Please contact your teacher.';
            return $result;
        }

        $graceMinutes = max(
            0,
            (int) ($class['grace_minutes'] ?? 10)
        );

        $graceEnd = $start->modify('+' . $graceMinutes . ' minutes');

        $result['start_time'] = $start->format('H:i');
        $result['end_time'] = $end->format('H:i');
        $result['grace_end_time'] = $graceEnd->format('H:i');

        if ($now < $start) {
            $result['message'] = 'Present opens at '
                . $start->format('g:i A') . '.';

            return $result;
        }

        // Inclusive grace period: Present is allowed through graceEnd.
        if ($now <= $graceEnd) {
            $result['can_present'] = true;
            $result['message'] = 'Confirm Present before '
                . $graceEnd->format('g:i A')
                . ' to record on-time attendance.';

            return $result;
        }

        // After grace, the Present option is no longer offered.
        $result['can_absent'] = true;

        if ($now <= $end) {
            $result['can_late'] = true;
            $result['message'] = 'The on-time window has passed. You may now choose Late or Absent.';
        } else {
            $result['message'] = 'Class has ended. You may report Absent for the teacher to review.';
        }

        return $result;
    }

    private function authenticatedApiUser(string $requiredRole): ?array
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

        // The database primary key is users.user_id.
        $user = $this->UserModel->find_by('user_id', (int) $userId);

        if (!is_array($user)) {
            $this->api->respond_error(
                'The account associated with this token no longer exists.',
                401
            );
            return null;
        }

        $actualRole = strtolower(trim((string) ($user['role'] ?? '')));

        if ($actualRole !== $requiredRole) {
            $this->api->respond_error(
                'You do not have permission to access this endpoint.',
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
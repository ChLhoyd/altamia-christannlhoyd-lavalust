<?php

class Teacher extends Controller
{
    public function __construct()
    {
        parent::__construct();

        // Load database before loading models
        $this->call->database();
        $this->call->library('session');
        $this->call->model('AttendanceModel');
        $this->call->model('UserModel');
    }

    public function index()
    {
        if (
            !$this->session->userdata('logged_in') ||
            $this->session->userdata('role') !== 'teacher'
        ) {
            redirect(site_url('login'));
            return;
        }

        $teacher_id = (int) $this->session->userdata('user_id');

        $classes = $this->AttendanceModel->teacher_classes($teacher_id);

        $selected_class = null;
        $students = [];

        $stats = [
            'Present' => 0,
            'Absent'  => 0,
            'Late'    => 0
        ];

        $class_id = (int) $this->request->get('class_id');

        if (is_array($classes) && $class_id > 0) {
            foreach ($classes as $class) {
                if ((int) $class['class_id'] === $class_id) {
                    $selected_class = $class;
                    break;
                }
            }
        }

        if ($selected_class !== null) {
            $enrollments = $this->AttendanceModel
                ->enrollments_for_class($class_id);

            $records = $this->AttendanceModel
                ->attendance_for_class_today($class_id);

            $statuses = [];

            if (is_array($records)) {
                foreach ($records as $record) {
                    $student_id = (int) $record['student_id'];
                    $status = $record['status'];

                    $statuses[$student_id] = $status;

                    if (isset($stats[$status])) {
                        $stats[$status]++;
                    }
                }
            }

            if (is_array($enrollments)) {
                foreach ($enrollments as $enrollment) {
                    $student = $this->UserModel->find(
                        (int) $enrollment['student_id']
                    );

                    if (
                        is_array($student) &&
                        ($student['role'] ?? '') === 'student'
                    ) {
                        $student['status'] =
                            $statuses[(int) $student['user_id']] ?? '';

                        $students[] = $student;
                    }
                }
            }
        }

        $this->call->view('teacher/dashboard', [
            'name' => trim(
                (string) $this->session->userdata('first_name') . ' ' .
                (string) $this->session->userdata('last_name')
            ),
            'classes' => $classes,
            'selected_class' => $selected_class,
            'students' => $students,
            'stats' => $stats
        ]);
    }

    public function students()
    {
        if (
            !$this->session->userdata('logged_in') ||
            $this->session->userdata('role') !== 'teacher'
        ) {
            redirect(site_url('login'));
            return;
        }

        redirect(site_url('teacher') . '#attendance');
    }
}

<?php

class Student extends Controller
{
    public function __construct()
    {
        parent::__construct();

        // Load database before using AttendanceModel
        $this->call->database();

        // Load session and models
        $this->call->library('session');
        $this->call->model('AttendanceModel');
    }

    public function index()
    {
        // Check if the user is logged in as a student
        if (
            !$this->session->userdata('logged_in') ||
            $this->session->userdata('role') !== 'student'
        ) {
            redirect(site_url('login'));
            return;
        }

        $student_id = (int) $this->session->userdata('user_id');

        // Get this student's attendance records
        $attendance = $this->AttendanceModel
            ->attendance_for_student($student_id);

        // Display student dashboard
        $this->call->view('student/dashboard', [
            'name' => trim(
                $this->session->userdata('first_name') . ' ' .
                $this->session->userdata('last_name')
            ),
            'attendance' => $attendance
        ]);
    }
}
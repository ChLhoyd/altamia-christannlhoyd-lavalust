<?php

class Attendance extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->call->library('session');
        $this->call->model('AttendanceModel');
    }

    public function save()
    {
        if (!$this->session->userdata('logged_in') ||
            $this->session->userdata('role') !== 'teacher') {
            redirect(site_url('login'));
            return;
        }

        if ($this->request->method() !== 'post') {
            redirect(site_url('teacher/dashboard'));
            return;
        }

        $class_id = (int) $this->request->post('class_id');
        $student_id = (int) $this->request->post('student_id');
        $status = (string) $this->request->post('status');

        $saved = $this->AttendanceModel->save_attendance(
            $class_id,
            $student_id,
            (int) $this->session->userdata('user_id'),
            $status
        );

        $this->session->set_userdata(
            'attendance_message',
            $saved
                ? 'Attendance saved successfully.'
                : 'Attendance could not be saved.'
        );

        redirect(site_url(
            'teacher/dashboard?class_id=' . $class_id
        ));
    }
}
<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'middlewares/StudentMiddleware.php';

class StudentController extends Controller
{
    private function studentData()
    {
       return [
            'student_id'  => '2024-0001',
            'name'        => 'Christann Lhoyd P. Altamia',
            'course'      => 'BS Information Technology',
            'year'        => '3rd Year',
            'section'     => '3 F-6',
            'email'       => 'lhoydaltamia@gmail.com',

            'address'     => 'Canubing 1 Calapan City',
            'contact'     => '0993 904 0290',
            'skills'      => 'Database',
            'hobbies'     => 'Gaming, Sports, Watching Movies',

            'description' => 'A BS Information Technology student interested in web development, programming, and learning new technologies.',

            'social_media' => [
            'facebook' => 'https://www.facebook.com/clhoyd.altamia/?viewas=100000686899395',
            ]
        ];
    }


    public function index()
    {
        $data['student'] = $this->studentData();

        $data['title'] = 'Lhoydxyz Panganiban | Student Portal';

        $this->call->view('student/index', $data);
    }


    public function profile()
    {
        $middleware = new StudentMiddleware();

        if ($middleware->handle() !== true) {
        return;
        }

        $data['student'] = $this->studentData();

        $data['title'] = 'Lhoydxyz Panganiban | Student Profile';

        $this->call->view('student/profile', $data);
    }
}    
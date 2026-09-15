<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->library('auth');
    }

    public function login()
    {
        if ($this->request->method() === 'post') {

            $username = $this->request->post('username');
            $password = $this->request->post('password');

            if ($this->auth->login($username, $password)) {
                redirect('products');
                exit;
            }

            $this->call->view('auth/login', [
                'error' => 'Invalid username or password.'
            ]);

            return;
        }

        $this->call->view('auth/login');
    }

    public function logout()
    {
        $this->auth->logout();

        redirect('auth/login');
        exit;
    }
}
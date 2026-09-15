<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class UsersController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->model('UsersModel');
    }

    public function index()
    {
        $users = $this->UsersModel->all();

        $this->call->view('users', [
            'users' => $users
        ]);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') 
        {

            $this->call->model('UsersModel');

            $data = 
            [
                'firstname' => $_POST['firstname'],
                'lastname' => $_POST['lastname'],
                'email' => $_POST['email'],
                'username' => $_POST['username']
            ];

            $this->UsersModel->insert($data);

            redirect('/');
        }

        $this->call->view('CreateUser');
    }
    
    public function edit($id)
    {
        $this->call->model('UsersModel');

        $user = $this->UsersModel->find($id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') 
        {

            $data = 
            [
                'firstname' => $_POST['firstname'],
                'lastname' => $_POST['lastname'],
                'email' => $_POST['email'],
                'username' => $_POST['username']
            ];

            $this->UsersModel->update($id, $data);

            redirect('/');
        }

        $this->call->view('editusers', ['user' => $user]);
    }

    public function delete($id)
    {
        $this->call->model('UsersModel');

        $this->UsersModel->delete($id);

        redirect('/');
    }
}
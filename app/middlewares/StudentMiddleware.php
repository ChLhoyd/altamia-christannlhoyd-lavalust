<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class StudentMiddleware
{
    public function handle()
    {
        // Student access condition
        if (!isset($_SESSION['student_access'])) {
            $_SESSION['student_access'] = true;
        }

        if ($_SESSION['student_access'] === true) {
            return true;
        }

        return false;
    }
}
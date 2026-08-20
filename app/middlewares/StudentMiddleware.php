<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class StudentMiddleware
{
    public function handle()
    {
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        
        if (!isset($_SESSION['student_access'])) {
            $_SESSION['student_access'] = true;
        }

        
        if ($_SESSION['student_access'] === true) {
            return true;
        }

        
        http_response_code(403);

        echo '
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0"
            >

            <title>Unauthorized Access</title>

            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f1f5f9;
                    padding: 40px;
                    text-align: center;
                }

                .box {
                    max-width: 600px;
                    margin: 80px auto;
                    background: white;
                    padding: 40px;
                    border-radius: 16px;
                    box-shadow: 0 10px 30px rgba(0,0,0,.08);
                }

                h2 {
                    color: #172554;
                }

                p {
                    color: #475569;
                }
            </style>
        </head>

        <body>

            <div class="box">

                <h2>
                    Unauthorized Access
                </h2>

                <p>
                    You are not authorized to access this student profile.
                </p>

            </div>

        </body>

        </html>
        ';

        return false;
    }
}
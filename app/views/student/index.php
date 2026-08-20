<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= html_escape($title ?? 'Student Portal'); ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #eef2ff;
            color: #172554;
        }

        /* NAVIGATION BAR */

        .navbar {
            height: 72px;
            background: #172554;
            color: white;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 48px;
        }

        .logo {
            font-size: 21px;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .nav-links a:hover {
            text-decoration: underline;
        }

        /* MAIN CONTENT */

        .page {
            min-height: calc(100vh - 72px);

            display: flex;
            justify-content: center;
            align-items: flex-start;

            padding: 50px 20px;
        }

        .card {
            width: 100%;
            max-width: 860px;

            background: white;

            border-radius: 18px;

            padding: 42px;

            box-shadow:
                0 12px 30px rgba(0, 0, 0, 0.08);
        }

        /* TITLE */

        .title {
            text-align: center;
            margin-bottom: 30px;
        }

        .title h1 {
            margin: 0;

            font-size: 32px;

            color: #172554;
        }

        .title p {
            margin-top: 12px;

            color: #64748b;

            font-size: 15px;
        }

        /* STUDENT DETAILS */

        .student-details {
            background: #f8fafc;

            border-radius: 12px;

            padding: 24px;
        }

        .student-details h2 {
            margin-top: 0;
            margin-bottom: 20px;

            color: #2563eb;

            font-size: 22px;
        }

        .detail-row {
            display: flex;

            justify-content: space-between;
            align-items: center;

            padding: 14px 0;

            border-bottom: 1px solid #e2e8f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .label {
            font-weight: bold;

            color: #334155;
        }

        .value {
            color: #172554;

            text-align: right;
        }

        /* BUTTON */

        .button-container {
            text-align: center;

            margin-top: 25px;
        }

        .profile-button {
            display: inline-block;

            padding: 12px 25px;

            background: #2563eb;

            color: white;

            text-decoration: none;

            border-radius: 7px;

            font-weight: bold;

            transition: background 0.2s ease;
        }

        .profile-button:hover {
            background: #1d4ed8;
        }

        /* FOOTER */

        .footer {
            text-align: center;

            margin-top: 24px;

            color: #64748b;

            font-size: 13px;
        }

        /* MOBILE */

        @media (max-width: 700px) {

            .navbar {
                padding: 0 20px;
            }

            .nav-links {
                gap: 15px;
            }

            .card {
                padding: 25px;
            }

            .detail-row {
                flex-direction: column;

                align-items: flex-start;

                gap: 6px;
            }

            .value {
                text-align: left;
            }

        }

    </style>

</head>

<body>

    <!-- NAVIGATION -->

    <nav class="navbar">

        <div class="logo">
            Student Portal
        </div>

        <div class="nav-links">

            <a href="/student">
                Home
            </a>

            <a href="/student/profile">
                Student Profile
            </a>

        </div>

    </nav>


    <!-- MAIN CONTENT -->

    <main class="page">

        <section class="card">

            <!-- PAGE TITLE -->

            <div class="title">

                <h1>
                    Student Information
                </h1>

                <p>
                    Welcome to my Student Information Page
                </p>

            </div>


            <!-- STUDENT DETAILS -->

            <div class="student-details">

                <h2>
                    Student Details
                </h2>


                <div class="detail-row">

                    <span class="label">
                        Student ID
                    </span>

                    <span class="value">
                        <?= html_escape($student['student_id'] ?? '2024-0001'); ?>
                    </span>

                </div>


                <div class="detail-row">

                    <span class="label">
                        Student Name
                    </span>

                    <span class="value">
                        <?= html_escape($student['name'] ?? 'Christann Lloyd P. Altamia'); ?>
                    </span>

                </div>


                <div class="detail-row">

                    <span class="label">
                        Course
                    </span>

                    <span class="value">
                        <?= html_escape($student['course'] ?? 'BS Information Technology'); ?>
                    </span>

                </div>


                <div class="detail-row">

                    <span class="label">
                        Year Level
                    </span>

                    <span class="value">
                        <?= html_escape($student['year'] ?? '3rd Year'); ?>
                    </span>

                </div>


                <div class="detail-row">

                    <span class="label">
                        Section
                    </span>

                    <span class="value">
                        <?= html_escape($student['section'] ?? '3 F-6'); ?>
                    </span>

                </div>


                <div class="detail-row">

                    <span class="label">
                        Email
                    </span>

                    <span class="value">
                        <?= html_escape($student['email'] ?? 'lhoydaltamia@gmail.com'); ?>
                    </span>

                </div>

            </div>


            <!-- VIEW PROFILE BUTTON -->

            <div class="button-container">

                <a href="/student/profile" class="profile-button">
                    View Student Profile
                </a>

            </div>


            <!-- FOOTER -->

            <div class="footer">

                

            </div>

        </section>

    </main>

</body>

</html>
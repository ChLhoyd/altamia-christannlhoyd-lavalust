<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= html_escape($title); ?></title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #eef2ff;
            color: #1e293b;
            min-height: 100vh;
        }

        .navbar {
            background: #172554;
            padding: 20px 60px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: white;
            font-size: 22px;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            gap: 25px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 15px;
        }

        .nav-links a:hover {
            color: #bfdbfe;
        }

        .container {
            width: 90%;
            max-width: 1000px;
            margin: 60px auto;
        }

        .welcome-card {
            background: white;
            padding: 50px;
            border-radius: 20px;

            box-shadow:
                0 15px 35px rgba(0, 0, 0, 0.08);

            text-align: center;
        }

        .welcome-card h1 {
            font-size: 40px;
            margin-bottom: 15px;
            color: #172554;
        }

        .welcome-card .subtitle {
            color: #64748b;
            font-size: 17px;
            margin-bottom: 35px;
        }

        .student-card {
            background: #f8fafc;
            padding: 25px;
            border-radius: 12px;
            text-align: left;
        }

        .student-card h2 {
            color: #2563eb;
            margin-bottom: 20px;
        }

        .student-row {
            display: flex;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .student-row:last-child {
            border-bottom: none;
        }

        .label {
            font-weight: bold;
            color: #475569;
        }

        .value {
            color: #1e293b;
        }

        .profile-button {
            display: inline-block;
            margin-top: 30px;

            background: #2563eb;
            color: white;

            text-decoration: none;

            padding: 14px 25px;

            border-radius: 9px;

            font-weight: bold;
        }

        .profile-button:hover {
            background: #1d4ed8;
        }

        @media (max-width: 700px) {

            .navbar {
                padding: 20px;
            }

            .nav-links {
                gap: 10px;
            }

            .container {
                width: 95%;
            }

            .welcome-card {
                padding: 30px 20px;
            }

            .welcome-card h1 {
                font-size: 30px;
            }

            .student-row {
                flex-direction: column;
                gap: 5px;
            }

        }

    </style>

</head>

<body>

<nav class="navbar">

    <div class="logo">
        Student Portal
    </div>

    <div class="nav-links">

        <a href="<?= html_escape($studentHomeUrl); ?>">
            Home
        </a>

        <a href="<?= html_escape($studentProfileUrl); ?>">
            Student Profile
        </a>

    </div>

</nav>


<div class="container">

    <div class="welcome-card">

        <h1>
            Student Information
        </h1>

        <p class="subtitle">
            Welcome to my Student Information Page
        </p>


        <div class="student-card">

            <h2>
                Student Details
            </h2>


            <div class="student-row">

                <span class="label">
                    Student ID
                </span>

                <span class="value">
                    <?= html_escape($student['student_id']); ?>
                </span>

            </div>


            <div class="student-row">

                <span class="label">
                    Student Name
                </span>

                <span class="value">
                    <?= html_escape($student['name']); ?>
                </span>

            </div>


            <div class="student-row">

                <span class="label">
                    Course
                </span>

                <span class="value">
                    <?= html_escape($student['course']); ?>
                </span>

            </div>


            <div class="student-row">

                <span class="label">
                    Year Level
                </span>

                <span class="value">
                    <?= html_escape($student['year']); ?>
                </span>

            </div>


            <div class="student-row">

                <span class="label">
                    Section
                </span>

                <span class="value">
                    <?= html_escape($student['section']); ?>
                </span>

            </div>


            <div class="student-row">

                <span class="label">
                    Email
                </span>

                <span class="value">
                    <?= html_escape($student['email']); ?>
                </span>

            </div>

        </div>


        <a
            class="profile-button"
            href="<?= html_escape($studentProfileUrl); ?>"
        >
            View Student Profile
        </a>

    </div>

</div>

</body>

</html>
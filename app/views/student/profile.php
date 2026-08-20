<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= html_escape($title); ?></title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
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
        }

        .nav-links a:hover {
            color: #bfdbfe;
        }

        .container {
            width: 90%;
            max-width: 950px;
            margin: 60px auto;
        }

        .profile-card {
            background: white;
            padding: 45px;
            border-radius: 20px;

            box-shadow:
                0 15px 35px rgba(0, 0, 0, 0.08);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .avatar {
            width: 105px;
            height: 105px;

            margin: 0 auto 20px;

            border-radius: 50%;

            background: #2563eb;
            color: white;

            display: flex;
            justify-content: center;
            align-items: center;

            font-size: 34px;
            font-weight: bold;
        }

        .profile-header h1 {
            font-size: 30px;
            margin-bottom: 8px;
        }

        .profile-header p {
            color: #64748b;
        }

        .information {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .information-box {
            background: #f8fafc;
            padding: 22px;
            border-radius: 12px;
            border-left: 4px solid #2563eb;
        }

        .information-box .label {
            display: block;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 8px;
        }

        .information-box .value {
            color: #334155;
        }

        .back-button {
            display: inline-block;

            margin-top: 35px;

            padding: 13px 22px;

            background: #172554;
            color: white;

            text-decoration: none;

            border-radius: 8px;
        }

        .back-button:hover {
            background: #1e3a8a;
        }

        .protected-message {
            margin-top: 25px;
            padding: 15px;

            background: #ecfdf5;
            border: 1px solid #86efac;

            border-radius: 8px;

            color: #166534;
            text-align: center;
        }

        @media (max-width: 700px) {

            .navbar {
                padding: 20px;
            }

            .information {
                grid-template-columns: 1fr;
            }

            .profile-card {
                padding: 30px 20px;
            }

        }

        .profile-description {
            margin-top: 25px;
            padding: 25px;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid #2563eb;
        }

        .profile-description h2 {
            color: #2563eb;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .profile-description p {
            color: #475569;
            line-height: 1.6;
        }

    </style>

</head>


<body>


<nav class="navbar">

    <div class="logo">
        Student Portal
    </div>

    <div class="nav-links">

        <a href="http://localhost/LavaLust-Test/student">
            Home
        </a>

        <a href="http://localhost/LavaLust-Test/student/profile">
            Student Profile
        </a>

    </div>

</nav>


<div class="container">

    <div class="profile-card">


        <div class="profile-header">

            <div class="avatar">
                LP
            </div>

            <h1>
                <?= html_escape($student['name']); ?>
            </h1>

            <p>
                <?= html_escape($student['course']); ?>
            </p>

        </div>


        <div class="information">


            <div class="information-box">

                <span class="label">
                    Student ID
                </span>

                <span class="value">
                    <?= html_escape($student['student_id']); ?>
                </span>

            </div>


            <div class="information-box">

                <span class="label">
                    Student Name
                </span>

                <span class="value">
                    <?= html_escape($student['name']); ?>
                </span>

            </div>


            <div class="information-box">

                <span class="label">
                    Course
                </span>

                <span class="value">
                    <?= html_escape($student['course']); ?>
                </span>

            </div>


            <div class="information-box">

                <span class="label">
                    Year Level
                </span>

                <span class="value">
                    <?= html_escape($student['year']); ?>
                </span>

            </div>


            <div class="information-box">

                <span class="label">
                    Section
                </span>

                <span class="value">
                    <?= html_escape($student['section']); ?>
                </span>

            </div>


            <div class="information-box">

                <span class="label">
                    Email
                </span>

                <span class="value">
                    <?= html_escape($student['email']); ?>
                </span>

            </div>


            <div class="information-box">

                <span class="label">
                 Address
                </span>

                 <span class="value">
                    <?= $student['address']; ?>
                </span>

            </div>


            <div class="information-box">

                <span class="label">
                    Contact Number
                </span>

                <span class="value">
                    <?= $student['contact']; ?>
                </span>

            </div>


            <div class="information-box">

                <span class="label">
                    Skills
                </span>

                <span class="value">
                    <?= $student['skills']; ?>
                </span>

            </div>


            <div class="information-box">

                <span class="label">
                    Hobbies
                </span>

                <span class="value">
                    <?= $student['hobbies']; ?>
                </span>

            </div>


        </div>


        <div class="profile-description">

            <h2>
                Profile Description
            </h2>

            <p>
                <?= $student['description']; ?>
            </p>

        </div>


            <div class="profile-description">

            <h2>Social Media</h2>

            <p>
                <a href="<?= $student['social_media']['facebook']; ?>" target="_blank">
                Facebook
                </a>
            </p>

        </div>


        <div class="protected-message">

            ✓ Lhoydxyz's Student Profile Access Verified

        <br>

            <small>
                Authorized through StudentMiddleware.
            </small>
        </div>


        <a
            class="back-button"
            href="/LavaLust-Test/student"
        >
            ← Back to Student Home
        </a>


    </div>

</div>


</body>

</html>

<?php
$name = $name ?? 'Teacher';
$classes = is_array($classes ?? null) ? $classes : [];
$selected_class = $selected_class ?? null;
$students = is_array($students ?? null) ? $students : [];

$stats = $stats ?? [
    'Present' => 0,
    'Absent' => 0,
    'Late' => 0
];

function teacher_dashboard_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$selected_class_id = (int) ($selected_class['class_id'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Dashboard | Student Attendance</title>

    <style>
        :root {
            --bg: #f4f6fb;
            --surface: #ffffff;
            --border: #e2e6ef;
            --text: #1c2333;
            --muted: #6b7489;
            --primary: #3b5bdb;
            --primary-soft: #e7ecff;
            --present: #2f9e62;
            --present-soft: #e3f6ec;
            --absent: #e03e5a;
            --absent-soft: #fde7ec;
            --late: #e8a317;
            --late-soft: #fff4d9;
            --radius: 12px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Segoe UI", system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button, select {
            font: inherit;
        }

        .app {
            display: grid;
            grid-template-columns: 260px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            background: var(--surface);
            border-right: 1px solid var(--border);
            padding: 22px 14px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 8px 22px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }

        .brand-icon {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: var(--primary);
            color: white;
            display: grid;
            place-items: center;
            font-size: 17px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .brand h2 {
            font-size: 15px;
        }

        .brand p {
            font-size: 12px;
            color: var(--muted);
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px;
            margin-bottom: 20px;
        }

        .avatar {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: var(--primary-soft);
            color: var(--primary);
            display: grid;
            place-items: center;
            font-size: 18px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .profile-name {
            font-size: 14px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .profile-status {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            color: var(--muted);
            margin-top: 3px;
        }

        .dot {
            width: 8px;
            height: 8px;
            background: var(--present);
            border-radius: 50%;
        }

        .nav-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            padding: 0 12px;
            margin-bottom: 8px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .nav {
            display: grid;
            gap: 6px;
        }

        .nav a, .bottom a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--muted);
            transition: .2s;
        }

        .nav a:hover, .bottom a:hover {
            background: var(--bg);
            color: var(--text);
        }

        .nav a.active {
            color: var(--primary);
            background: var(--primary-soft);
        }

        .nav-icon {
            width: 25px;
            text-align: center;
            font-size: 18px;
        }

        .bottom {
            margin-top: auto;
            padding-top: 18px;
            border-top: 1px solid var(--border);
        }

        .bottom a.logout {
            color: var(--absent);
        }

        .main {
            min-width: 0;
            padding: 28px 34px 40px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 30px;
        }

        .topbar h1 {
            font-size: 23px;
        }

        .topbar p {
            color: var(--muted);
            font-size: 13px;
            margin-top: 3px;
        }

        .date-pill {
            background: white;
            border: 1px solid var(--border);
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            color: var(--muted);
            white-space: nowrap;
        }

        .welcome {
            background: linear-gradient(115deg, #3b5bdb, #627df0);
            color: white;
            border-radius: 16px;
            padding: 26px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .welcome h2 {
            font-size: 22px;
            margin-bottom: 6px;
        }

        .welcome p {
            color: #edf0ff;
            font-size: 14px;
        }

        .welcome-icon {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: rgba(255,255,255,.18);
            display: grid;
            place-items: center;
            font-size: 30px;
            flex-shrink: 0;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 28px 0 14px;
        }

        .section-heading h2 {
            font-size: 17px;
        }

        .section-heading p {
            color: var(--muted);
            font-size: 13px;
            margin-top: 3px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .stat {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 16px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 19px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            line-height: 1.2;
        }

        .stat-note {
            color: var(--muted);
            font-size: 12px;
            margin-top: 7px;
        }

        .stat.present .stat-icon {
            color: var(--present);
            background: var(--present-soft);
        }

        .stat.absent .stat-icon {
            color: var(--absent);
            background: var(--absent-soft);
        }

        .stat.late .stat-icon {
            color: #a87400;
            background: var(--late-soft);
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px;
            margin-top: 20px;
            min-width: 0;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .panel-header h2 {
            font-size: 16px;
        }

        .panel-header p {
            color: var(--muted);
            font-size: 12px;
            margin-top: 4px;
        }

        .class-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .class-select {
            flex: 1;
            min-width: 180px;
            padding: 11px 12px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: white;
            color: var(--text);
            outline: none;
        }

        .class-select:focus {
            border-color: var(--primary);
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 11px 16px;
            border: 0;
            border-radius: 9px;
            background: var(--primary);
            color: white;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }

        .btn:hover {
            filter: brightness(1.08);
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        th, td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
        }

        th {
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            background: #fafbfe;
        }

        td {
            font-size: 13px;
        }

        tbody tr:hover {
            background: #fafbff;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .student-name {
            font-weight: 700;
        }

        .student-email {
            color: var(--muted);
            font-size: 12px;
            margin-top: 3px;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge.present {
            background: var(--present-soft);
            color: var(--present);
        }

        .badge.absent {
            background: var(--absent-soft);
            color: var(--absent);
        }

        .badge.late {
            background: var(--late-soft);
            color: #a87400;
        }

        .badge.pending {
            background: #eef0f5;
            color: #6b7489;
        }

        .empty {
            text-align: center;
            padding: 35px 16px;
            color: var(--muted);
            font-size: 13px;
        }

        .empty-icon {
            font-size: 30px;
            margin-bottom: 8px;
        }

        .footer-note {
            color: var(--muted);
            font-size: 12px;
            margin-top: 22px;
            text-align: center;
        }

        @media (max-width: 1050px) {
            .app {
                grid-template-columns: 220px minmax(0, 1fr);
            }

            .main {
                padding: 24px 20px;
            }

            .stats {
                gap: 10px;
            }

            .stat {
                padding: 16px;
            }
        }

        @media (max-width: 760px) {
            .app {
                grid-template-columns: 1fr;
            }

            .sidebar {
                min-height: auto;
                padding: 14px;
            }

            .brand {
                padding-bottom: 14px;
                margin-bottom: 12px;
            }

            .profile {
                margin-bottom: 12px;
            }

            .nav {
                display: flex;
                overflow-x: auto;
                padding-bottom: 8px;
            }

            .nav a {
                white-space: nowrap;
            }

            .bottom {
                margin-top: 8px;
                display: flex;
                gap: 8px;
                padding-top: 10px;
            }

            .main {
                padding: 20px 14px 30px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
                margin-bottom: 20px;
            }

            .welcome {
                padding: 20px;
            }

            .welcome h2 {
                font-size: 19px;
            }

            .welcome-icon {
                width: 48px;
                height: 48px;
                font-size: 23px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .stat {
                padding: 18px;
            }

            .panel {
                padding: 16px;
            }

            .section-heading {
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
<div class="app">

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">SA</div>
            <div>
                <h2>Student Attendance</h2>
                <p>Management System</p>
            </div>
        </div>

        <div class="profile">
            <div class="avatar">
                <?= teacher_dashboard_escape(
                    strtoupper(substr(trim($name), 0, 1))
                ) ?>
            </div>
            <div>
                <div class="profile-name">
                    <?= teacher_dashboard_escape($name) ?>
                </div>
                <div class="profile-status">
                    <span class="dot"></span> Teacher account
                </div>
            </div>
        </div>

        <div class="nav-label">Workspace</div>

        <nav class="nav">
            <a class="active" href="<?= site_url('teacher') ?>">
                <span class="nav-icon">▦</span>
                Dashboard
            </a>

            <a href="<?= site_url('teacher/students') ?>">
                <span class="nav-icon">♙</span>
                Students
            </a>

            <a href="#classes">
                <span class="nav-icon">▤</span>
                Classes
            </a>

            <a href="#attendance">
                <span class="nav-icon">✓</span>
                Attendance
            </a>
        </nav>

        <div class="bottom">
            <a href="#classes">
                <span class="nav-icon">⚙</span>
                Settings
            </a>

            <a class="logout" href="<?= site_url('logout') ?>">
                <span class="nav-icon">⇥</span>
                Log out
            </a>
        </div>
    </aside>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Teacher Dashboard</h1>
                <p>Manage your classes and monitor student attendance.</p>
            </div>

            <div class="date-pill">
                📅 <?= date('F j, Y') ?>
            </div>
        </header>

        <section class="welcome">
            <div>
                <h2>Welcome back, <?= teacher_dashboard_escape($name) ?>!</h2>
                <p>Here's your attendance overview for today.</p>
            </div>
            <div class="welcome-icon">📚</div>
        </section>

        <div class="section-heading">
            <div>
                <h2>Today's Overview</h2>
                <p>Attendance records for the selected class.</p>
            </div>
        </div>

        <section class="stats">
            <div class="stat present">
                <div class="stat-top">
                    <div class="stat-label">Present</div>
                    <div class="stat-icon">✓</div>
                </div>
                <div class="stat-value">
                    <?= (int) ($stats['Present'] ?? 0) ?>
                </div>
                <div class="stat-note">Students marked present</div>
            </div>

            <div class="stat absent">
                <div class="stat-top">
                    <div class="stat-label">Absent</div>
                    <div class="stat-icon">×</div>
                </div>
                <div class="stat-value">
                    <?= (int) ($stats['Absent'] ?? 0) ?>
                </div>
                <div class="stat-note">Students marked absent</div>
            </div>

            <div class="stat late">
                <div class="stat-top">
                    <div class="stat-label">Late</div>
                    <div class="stat-icon">◷</div>
                </div>
                <div class="stat-value">
                    <?= (int) ($stats['Late'] ?? 0) ?>
                </div>
                <div class="stat-note">Students marked late</div>
            </div>
        </section>

        <section class="panel" id="classes">
            <div class="panel-header">
                <div>
                    <h2>Your Classes</h2>
                    <p>Select a class to view its students and attendance.</p>
                </div>
            </div>

            <?php if (empty($classes)): ?>

                <div class="empty">
                    <div class="empty-icon">📚</div>
                    <strong>No classes assigned yet</strong>
                    <p>Classes assigned to your teacher account will appear here.</p>
                </div>

            <?php else: ?>

                <form class="class-form" action="<?= site_url('teacher') ?>" method="get">
                    <select class="class-select" id="class_id" name="class_id" required>
                        <option value="">Choose a class...</option>

                        <?php foreach ($classes as $class): ?>
                            <option
                                value="<?= (int) $class['class_id'] ?>"
                                <?= $selected_class_id === (int) $class['class_id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= teacher_dashboard_escape(
                                    $class['class_name']
                                    ?? $class['name']
                                    ?? ('Class ' . $class['class_id'])
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button class="btn" type="submit">View Class</button>
                </form>

            <?php endif; ?>
        </section>

        <section class="panel" id="attendance">
            <div class="panel-header">
                <div>
                    <h2>
                        <?= $selected_class
                            ? teacher_dashboard_escape(
                                $selected_class['class_name']
                                ?? $selected_class['name']
                                ?? 'Selected Class'
                            )
                            : 'Student Attendance' ?>
                    </h2>
                    <p>Student list and today's attendance status.</p>
                </div>
            </div>

            <?php if (!$selected_class): ?>

                <div class="empty">
                    <div class="empty-icon">🗓️</div>
                    <strong>Select a class first</strong>
                    <p>Choose one of your classes above to see its student records.</p>
                </div>

            <?php elseif (empty($students)): ?>

                <div class="empty">
                    <div class="empty-icon">👨‍🎓</div>
                    <strong>No students enrolled</strong>
                    <p>Enrolled students will appear in this table.</p>
                </div>

            <?php else: ?>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Today's Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $status = $student['status'] ?? '';
                                $badge_class = 'pending';

                                if ($status === 'Present') {
                                    $badge_class = 'present';
                                } elseif ($status === 'Absent') {
                                    $badge_class = 'absent';
                                } elseif ($status === 'Late') {
                                    $badge_class = 'late';
                                }
                                ?>

                                <tr>
                                    <td>
                                        <div class="student-name">
                                            <?= teacher_dashboard_escape(
                                                trim(
                                                    ($student['first_name'] ?? '') . ' ' .
                                                    ($student['last_name'] ?? '')
                                                ) ?: 'Unnamed Student'
                                            ) ?>
                                        </div>
                                        <div class="student-email">
                                            <?= teacher_dashboard_escape(
                                                $student['email'] ?? ''
                                            ) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= (int) ($student['user_id'] ?? 0) ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $badge_class ?>">
                                            <?= teacher_dashboard_escape(
                                                $status !== '' ? $status : 'Not Recorded'
                                            ) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </section>

        <p class="footer-note">
            Student Attendance Management System · Teacher Portal
        </p>

    </main>
</div>
</body>
</html>

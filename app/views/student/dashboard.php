
<?php
$studentName = $name ?? 'Student';
$attendanceRecords = is_array($attendance ?? null) ? $attendance : [];

$totalRecords = count($attendanceRecords);
$presentCount = 0;
$absentCount = 0;
$lateCount = 0;

foreach ($attendanceRecords as $record) {
    $status = strtolower(trim($record['status'] ?? ''));

    if ($status === 'present') {
        $presentCount++;
    } elseif ($status === 'absent') {
        $absentCount++;
    } elseif ($status === 'late') {
        $lateCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Student Dashboard - SA Attendance</title>

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
            --late: #b78108;
            --late-soft: #fff4d9;
            --radius: 14px;
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, -apple-system, Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button,
        input {
            font: inherit;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        .app {
            display: grid;
            grid-template-columns: 260px minmax(0, 1fr);
            min-height: 100vh;
        }

        /* Sidebar */

        .sidebar {
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 22px 15px;
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 3px 8px 22px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 22px;
        }

        .brand-logo {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 13px;
            background: var(--primary);
            color: #fff;
            font-size: 17px;
            font-weight: 800;
            box-shadow: 0 5px 12px rgba(59, 91, 219, .2);
        }

        .brand-name {
            font-weight: 750;
            font-size: 16px;
            letter-spacing: -.3px;
        }

        .brand-name span {
            color: var(--primary);
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 7px 21px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
            min-width: 0;
        }

        .avatar {
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            border-radius: 13px;
            background: var(--primary-soft);
            color: var(--primary);
            display: grid;
            place-items: center;
            font-size: 19px;
            font-weight: 800;
        }

        .profile-info {
            min-width: 0;
        }

        .profile-name {
            font-size: 14px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }

        .profile-status {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--present);
            box-shadow: 0 0 0 3px var(--present-soft);
        }

        .section-label {
            padding: 0 11px;
            margin-bottom: 9px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.2px;
        }

        .nav {
            display: grid;
            gap: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 13px;
            border-radius: 10px;
            color: #515b70;
            font-size: 13px;
            font-weight: 600;
            transition: background .2s, color .2s;
        }

        .nav-link:hover {
            background: var(--bg);
            color: var(--primary);
        }

        .nav-link.active {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            flex: 0 0 20px;
        }

        .sidebar-bottom {
            display: grid;
            gap: 5px;
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .nav-link.logout {
            color: var(--absent);
        }

        .nav-link.logout:hover {
            background: var(--absent-soft);
        }

        /* Main content */

        .main {
            min-width: 0;
            padding: 27px 32px 40px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 27px;
        }

        .page-title {
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -.6px;
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 13px;
            margin-top: 5px;
        }

        .date-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            color: #505b70;
            font-size: 12px;
            font-weight: 650;
        }

        .date-chip svg {
            width: 17px;
            height: 17px;
            color: var(--primary);
        }

        /* Summary cards */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 19px;
            min-width: 0;
            transition: transform .2s, box-shadow .2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(28, 35, 51, .05);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 16px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 650;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 11px;
        }

        .stat-icon svg {
            width: 19px;
            height: 19px;
        }

        .icon-blue {
            color: var(--primary);
            background: var(--primary-soft);
        }

        .icon-green {
            color: var(--present);
            background: var(--present-soft);
        }

        .icon-red {
            color: var(--absent);
            background: var(--absent-soft);
        }

        .icon-yellow {
            color: var(--late);
            background: var(--late-soft);
        }

        .stat-value {
            font-size: 29px;
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -.7px;
        }

        .stat-note {
            color: var(--muted);
            font-size: 11px;
            margin-top: 7px;
        }

        /* Attendance panel */

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .panel-header {
            padding: 21px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-bottom: 1px solid var(--border);
        }

        .panel-title {
            font-size: 16px;
            font-weight: 800;
        }

        .panel-subtitle {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }

        .search-wrap {
            position: relative;
            width: min(100%, 260px);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 17px;
            height: 17px;
            color: var(--muted);
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 10px 13px 10px 37px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #fff;
            color: var(--text);
            outline: none;
            font-size: 12px;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        thead {
            background: #fafbfe;
        }

        th {
            padding: 14px 22px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .7px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 16px 22px;
            border-top: 1px solid #edf0f6;
            font-size: 12px;
            color: #465066;
            white-space: nowrap;
        }

        tbody tr {
            transition: background .15s;
        }

        tbody tr:hover {
            background: #fafbff;
        }

        .class-id {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 37px;
            padding: 5px 9px;
            border-radius: 7px;
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 750;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 750;
        }

        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .badge.present {
            color: var(--present);
            background: var(--present-soft);
        }

        .badge.absent {
            color: var(--absent);
            background: var(--absent-soft);
        }

        .badge.late {
            color: var(--late);
            background: var(--late-soft);
        }

        .badge.other {
            color: #626d82;
            background: #edf0f5;
        }

        .empty-state {
            padding: 52px 20px;
            text-align: center;
        }

        .empty-icon {
            width: 55px;
            height: 55px;
            margin: 0 auto 15px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: var(--primary-soft);
            color: var(--primary);
        }

        .empty-icon svg {
            width: 27px;
            height: 27px;
        }

        .empty-state h3 {
            font-size: 15px;
            margin-bottom: 6px;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 12px;
        }

        .no-results {
            display: none;
            padding: 24px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        .footer-note {
            color: var(--muted);
            text-align: center;
            font-size: 11px;
            margin-top: 20px;
        }

        /* Dark theme */

        body.dark {
            --bg: #151a26;
            --surface: #1e2534;
            --border: #30394b;
            --text: #edf1fa;
            --muted: #a0abc0;
            --primary: #91a7ff;
            --primary-soft: #2a3659;
            --present: #65d99a;
            --present-soft: #203d32;
            --absent: #ff8297;
            --absent-soft: #492a36;
            --late: #ffd16a;
            --late-soft: #463a25;
            color-scheme: dark;
        }

        body.dark .search-input {
            background: #171d2a;
            color: var(--text);
        }

        body.dark thead {
            background: #1a2030;
        }

        body.dark td {
            border-color: var(--border);
            color: #d0d7e7;
        }

        body.dark tbody tr:hover {
            background: #252d3e;
        }

        body.dark .date-chip {
            color: var(--text);
        }

        body.dark .class-id {
            color: var(--primary);
        }

        body.dark .badge.other {
            background: #30394b;
            color: #d0d7e7;
        }

        /* Responsive layout */

        @media (max-width: 1100px) {
            .main {
                padding: 24px 22px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .app {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
                height: auto;
                padding: 14px;
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }

            .brand {
                padding-bottom: 13px;
                margin-bottom: 13px;
            }

            .profile {
                padding-bottom: 13px;
                margin-bottom: 13px;
            }

            .section-label {
                margin-bottom: 7px;
            }

            .nav {
                display: flex;
                gap: 7px;
                overflow-x: auto;
                padding-bottom: 5px;
            }

            .nav-link {
                flex: 0 0 auto;
                padding: 10px 12px;
            }

            .sidebar-bottom {
                display: flex;
                flex-wrap: wrap;
                margin-top: 10px;
                padding-top: 10px;
            }

            .main {
                padding: 22px 15px 30px;
            }

            .page-title {
                font-size: 22px;
            }

            .panel-header {
                padding: 17px;
            }

            th,
            td {
                padding: 13px 15px;
            }
        }

        @media (max-width: 420px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .stat-card {
                padding: 13px;
            }

            .stat-value {
                font-size: 25px;
            }

            .stat-top {
                align-items: flex-start;
                flex-direction: column-reverse;
                margin-bottom: 10px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .search-wrap {
                width: 100%;
            }
        }
    </style>
</head>

<body>
<div class="app">

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">SA</div>
            <div class="brand-name">SA <span>Attendance</span></div>
        </div>

        <div class="profile">
            <div class="avatar">
                <?= htmlspecialchars(
                    strtoupper(substr(trim($studentName), 0, 1)),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

            <div class="profile-info">
                <div class="profile-name">
                    <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="profile-status">
                    <span class="status-dot"></span>
                    Logged in
                </div>
            </div>
        </div>

        <p class="section-label">STUDENT MENU</p>

        <nav class="nav" aria-label="Student navigation">
            <a class="nav-link" href="<?= site_url('student/dashboard') ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <rect x="3" y="3" width="7" height="9" rx="1.5"/>
                    <rect x="14" y="3" width="7" height="5" rx="1.5"/>
                    <rect x="14" y="12" width="7" height="9" rx="1.5"/>
                    <rect x="3" y="16" width="7" height="5" rx="1.5"/>
                </svg>
                Dashboard
            </a>

            <a class="nav-link active" href="#attendance">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <rect x="4" y="4" width="16" height="17" rx="2"/>
                    <path d="M8 2v4M16 2v4M8.5 13l2.5 2.5 4.5-5"/>
                </svg>
                My Attendance
            </a>
        </nav>

        <div class="sidebar-bottom">
            <a class="nav-link" href="#" id="themeToggle">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"/>
                </svg>
                Toggle Theme
            </a>

            <a class="nav-link logout" href="<?= site_url('logout') ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <path d="m16 17 5-5-5-5"/>
                    <path d="M21 12H9"/>
                </svg>
                Log Out
            </a>
        </div>
    </aside>

    <main class="main">

        <header class="topbar">
            <div>
                <h1 class="page-title">My Attendance</h1>
                <p class="page-subtitle">
                    Welcome back,
                    <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>.
                    Here's your attendance overview.
                </p>
            </div>

            <div class="date-chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                    <path d="M16 3v4M8 3v4M3 10h18"/>
                </svg>
                <?= date('F j, Y') ?>
            </div>
        </header>

        <section class="stats-grid" aria-label="Attendance summary">

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Total Records</span>
                    <span class="stat-icon icon-blue">
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round"
                             aria-hidden="true">
                            <rect x="4" y="4" width="16" height="17" rx="2"/>
                            <path d="M8 2v4M16 2v4M8 11h8M8 15h5"/>
                        </svg>
                    </span>
                </div>
                <div class="stat-value"><?= $totalRecords ?></div>
                <p class="stat-note">Recorded attendance entries</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Present</span>
                    <span class="stat-icon icon-green">
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="m5 12 4 4L19 6"/>
                        </svg>
                    </span>
                </div>
                <div class="stat-value"><?= $presentCount ?></div>
                <p class="stat-note">Days marked present</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Absent</span>
                    <span class="stat-icon icon-red">
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="m18 6-12 12M6 6l12 12"/>
                        </svg>
                    </span>
                </div>
                <div class="stat-value"><?= $absentCount ?></div>
                <p class="stat-note">Days marked absent</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Late</span>
                    <span class="stat-icon icon-yellow">
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round"
                             aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 7v5l3 2"/>
                        </svg>
                    </span>
                </div>
                <div class="stat-value"><?= $lateCount ?></div>
                <p class="stat-note">Days marked late</p>
            </article>

        </section>

        <section class="panel" id="attendance">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Attendance Records</h2>
                    <p class="panel-subtitle">
                        Review your recorded attendance history.
                    </p>
                </div>

                <div class="search-wrap">
                    <svg class="search-icon" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" aria-hidden="true">
                        <circle cx="10.5" cy="10.5" r="7"/>
                        <path d="m16 16 5 5"/>
                    </svg>

                    <input
                        class="search-input"
                        id="searchInput"
                        type="search"
                        placeholder="Search records..."
                        aria-label="Search attendance records"
                    >
                </div>
            </div>

            <?php if (empty($attendanceRecords)): ?>

                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round"
                             aria-hidden="true">
                            <rect x="4" y="4" width="16" height="17" rx="2"/>
                            <path d="M8 2v4M16 2v4M8 11h8M8 15h5"/>
                        </svg>
                    </div>

                    <h3>No attendance records yet</h3>
                    <p>Your attendance records will appear here once they are available.</p>
                </div>

            <?php else: ?>

                <div class="table-wrapper">
                    <table id="studentTable">
                        <thead>
                            <tr>
                                <th>Class ID</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($attendanceRecords as $record): ?>
                            <?php
                            $recordStatus = trim($record['status'] ?? '');
                            $statusClass = strtolower($recordStatus);

                            if (!in_array($statusClass, ['present', 'absent', 'late'], true)) {
                                $statusClass = 'other';
                            }
                            ?>

                            <tr>
                                <td>
                                    <span class="class-id">
                                        <?= (int) ($record['class_id'] ?? 0) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $record['date'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <span class="badge <?= $statusClass ?>">
                                        <span class="badge-dot"></span>
                                        <?= htmlspecialchars(
                                            $recordStatus !== '' ? $recordStatus : 'Unknown',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="no-results" id="noResults">
                    No matching attendance records found.
                </div>

            <?php endif; ?>
        </section>

        <p class="footer-note">
            SA Attendance System &middot; Student Portal
        </p>

    </main>
</div>

<script>
    // Search attendance records
    (function () {
        const searchInput = document.getElementById('searchInput');
        const table = document.getElementById('studentTable');
        const noResults = document.getElementById('noResults');

        if (!searchInput || !table) {
            return;
        }

        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');
            let visibleRows = 0;

            rows.forEach(function (row) {
                const matches = row.textContent.toLowerCase().includes(query);

                row.style.display = matches ? '' : 'none';

                if (matches) {
                    visibleRows++;
                }
            });

            if (noResults) {
                noResults.style.display = visibleRows === 0 ? 'block' : 'none';
            }

            table.style.display = visibleRows === 0 ? 'none' : '';
        });
    })();

    // Toggle light and dark themes
    (function () {
        const themeToggle = document.getElementById('themeToggle');

        if (!themeToggle) {
            return;
        }

        themeToggle.addEventListener('click', function (event) {
            event.preventDefault();

            document.body.classList.toggle('dark');

            const darkMode = document.body.classList.contains('dark');

            try {
                localStorage.setItem('studentDashboardTheme', darkMode ? 'dark' : 'light');
            } catch (error) {
                // Theme still works if browser storage is unavailable.
            }
        });

        try {
            if (localStorage.getItem('studentDashboardTheme') === 'dark') {
                document.body.classList.add('dark');
            }
        } catch (error) {
            // Use the default light theme if browser storage is unavailable.
        }
    })();
</script>

</body>
</html>

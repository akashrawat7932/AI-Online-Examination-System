<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$pdo = db();

/* =========================
   DASHBOARD COUNTS
========================= */

$students = (int)$pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'student'
")->fetchColumn();

$teachers = (int)$pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'teacher'
")->fetchColumn();

$subjects = (int)$pdo->query("
    SELECT COUNT(*)
    FROM subjects
    WHERE status = 'active'
")->fetchColumn();

$questions = (int)$pdo->query("
    SELECT COUNT(*)
    FROM questions
")->fetchColumn();

$exams = (int)$pdo->query("
    SELECT COUNT(*)
    FROM exams
")->fetchColumn();

$active_exams = (int)$pdo->query("
    SELECT COUNT(*)
    FROM exams
    WHERE status = 'active'
")->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard - AI Examination System</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #1f2937;
}

/* ================= SIDEBAR ================= */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100vh;
    background: #111827;
    color: white;
    overflow-y: auto;
}

.logo {
    padding: 23px 20px;
    border-bottom: 1px solid #374151;
}

.logo h2 {
    font-size: 20px;
}

.logo p {
    margin-top: 5px;
    color: #9ca3af;
    font-size: 12px;
}

.menu-title {
    padding: 20px 20px 8px;
    color: #6b7280;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: bold;
}

.nav-menu {
    list-style: none;
}

.nav-menu li {
    margin: 3px 10px;
}

.nav-menu a {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 11px 13px;
    color: #d1d5db;
    text-decoration: none;
    border-radius: 7px;
    font-size: 14px;
}

.nav-menu a:hover,
.nav-menu a.active {
    background: #2563eb;
    color: white;
}

.icon {
    width: 22px;
    text-align: center;
}

/* ================= MAIN ================= */

.main {
    margin-left: 250px;
    min-height: 100vh;
}

/* ================= TOPBAR ================= */

.topbar {
    height: 70px;
    background: white;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 30px;
}

.topbar h1 {
    font-size: 21px;
}

.admin-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #2563eb;
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: bold;
}

.admin-name {
    font-size: 14px;
    font-weight: bold;
}

.admin-role {
    font-size: 11px;
    color: #6b7280;
}

/* ================= CONTENT ================= */

.content {
    padding: 30px;
}

.welcome {
    margin-bottom: 25px;
}

.welcome h2 {
    font-size: 25px;
    margin-bottom: 6px;
}

.welcome p {
    color: #6b7280;
}

/* ================= STATS ================= */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.04);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-icon {
    width: 43px;
    height: 43px;
    border-radius: 9px;
    background: #eff6ff;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 20px;
}

.stat-card h3 {
    font-size: 28px;
    margin-top: 15px;
}

.stat-card p {
    color: #6b7280;
    margin-top: 5px;
    font-size: 13px;
}

/* ================= GRID ================= */

.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

/* ================= CARD ================= */

.card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.04);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-header h3 {
    font-size: 18px;
}

.card-header a {
    color: #2563eb;
    text-decoration: none;
    font-size: 13px;
}

/* ================= QUICK ACTION ================= */

.quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.quick-action {
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    padding: 15px;
    text-decoration: none;
    color: #1f2937;
    transition: 0.2s;
}

.quick-action:hover {
    border-color: #2563eb;
    background: #eff6ff;
}

.quick-action strong {
    display: block;
    margin-bottom: 5px;
}

.quick-action span {
    font-size: 12px;
    color: #6b7280;
}

/* ================= SYSTEM STATUS ================= */

.status-row {
    display: flex;
    justify-content: space-between;
    padding: 14px 0;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}

.status-row:last-child {
    border-bottom: none;
}

.status {
    color: #16a34a;
    font-weight: bold;
}

.status-warning {
    color: #d97706;
    font-weight: bold;
}

/* ================= FOOTER ================= */

.footer {
    margin-top: 30px;
    text-align: center;
    color: #9ca3af;
    font-size: 12px;
}

/* ================= MOBILE ================= */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 750px) {

    .sidebar {
        width: 210px;
    }

    .main {
        margin-left: 210px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .quick-actions {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 550px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
    }

    .topbar {
        padding: 0 15px;
    }

    .content {
        padding: 18px;
    }

}

</style>

</head>

<body>


<!-- ================= SIDEBAR ================= -->

<aside class="sidebar">

    <div class="logo">

        <h2>AI Exam System</h2>

        <p>Administrator Panel</p>

    </div>


    <div class="menu-title">
        Main
    </div>

    <ul class="nav-menu">

        <li>
            <a href="dashboard.php" class="active">
                <span class="icon">🏠</span>
                Dashboard
            </a>
        </li>

    </ul>


    <div class="menu-title">
        User Management
    </div>

    <ul class="nav-menu">

        <li>
            <a href="student_management.php">
                <span class="icon">🎓</span>
                Students
            </a>
        </li>

        <li>
            <a href="teacher_management.php">
                <span class="icon">👨‍🏫</span>
                Teachers
            </a>
        </li>

        <li>
            <a href="user_management.php">
                <span class="icon">👥</span>
                Users
            </a>
        </li>

        <li>
            <a href="role_management.php">
                <span class="icon">🔐</span>
                Roles & Permissions
            </a>
        </li>

    </ul>


    <div class="menu-title">
        Academic
    </div>

    <ul class="nav-menu">

        <li>
            <a href="subject_management.php">
                <span class="icon">📚</span>
                Subjects
            </a>
        </li>

        <li>
            <a href="question_management.php">
                <span class="icon">❓</span>
                Question Bank
            </a>
        </li>

        <li>
            <a href="import_questions.php">
                <span class="icon">📥</span>
                Import Questions
            </a>
        </li>

    </ul>


    <div class="menu-title">
        Examination
    </div>

    <ul class="nav-menu">

        <li>
            <a href="exam_management.php">
                <span class="icon">📝</span>
                Exams
            </a>
        </li>

        <li>
            <a href="exam_schedule.php">
                <span class="icon">📅</span>
                Exam Schedule
            </a>
        </li>

        <li>
            <a href="results.php">
                <span class="icon">📊</span>
                Results
            </a>
        </li>

    </ul>


    <div class="menu-title">
        AI & Security
    </div>

    <ul class="nav-menu">

        <li>
            <a href="proctoring.php">
                <span class="icon">👁️</span>
                AI Proctoring
            </a>
        </li>

        <li>
            <a href="evaluation.php">
                <span class="icon">🤖</span>
                AI Evaluation
            </a>
        </li>

        <li>
            <a href="proctoring_logs.php">
                <span class="icon">🛡️</span>
                Security Logs
            </a>
        </li>

    </ul>


    <div class="menu-title">
        Reports
    </div>

    <ul class="nav-menu">

        <li>
            <a href="reports.php">
                <span class="icon">📈</span>
                Reports
            </a>
        </li>

        <li>
            <a href="audit_logs.php">
                <span class="icon">📋</span>
                Audit Logs
            </a>
        </li>

    </ul>


    <div class="menu-title">
        Account
    </div>

    <ul class="nav-menu">

        <li>
            <a href="profile.php">
                <span class="icon">👤</span>
                Profile
            </a>
        </li>

        <li>
            <a href="../logout.php">
                <span class="icon">🚪</span>
                Logout
            </a>
        </li>

    </ul>

</aside>


<!-- ================= MAIN ================= -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <h1>Admin Dashboard</h1>

        <div class="admin-info">

            <div>

                <div class="admin-name">
                    Administrator
                </div>

                <div class="admin-role">
                    Super Admin
                </div>

            </div>

            <div class="avatar">
                A
            </div>

        </div>

    </header>


    <!-- CONTENT -->

    <section class="content">


        <div class="welcome">

            <h2>Welcome, Admin 👋</h2>

            <p>
                Manage your online examination system from one place.
            </p>

        </div>


        <!-- ================= STATS ================= -->

        <div class="stats">


            <div class="stat-card">

                <div class="stat-top">

                    <div>
                        <span>Students</span>
                    </div>

                    <div class="stat-icon">
                        🎓
                    </div>

                </div>

                <h3>
                    <?= $students ?>
                </h3>

                <p>
                    Registered students
                </p>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <div>
                        <span>Teachers</span>
                    </div>

                    <div class="stat-icon">
                        👨‍🏫
                    </div>

                </div>

                <h3>
                    <?= $teachers ?>
                </h3>

                <p>
                    Registered teachers
                </p>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <div>
                        <span>Subjects</span>
                    </div>

                    <div class="stat-icon">
                        📚
                    </div>

                </div>

                <h3>
                    <?= $subjects ?>
                </h3>

                <p>
                    Active subjects
                </p>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <div>
                        <span>Questions</span>
                    </div>

                    <div class="stat-icon">
                        ❓
                    </div>

                </div>

                <h3>
                    <?= $questions ?>
                </h3>

                <p>
                    Question bank
                </p>

            </div>

        </div>


        <!-- ================= SECOND ROW ================= -->

        <div class="dashboard-grid">


            <!-- QUICK ACTIONS -->

            <div class="card">

                <div class="card-header">

                    <h3>
                        Quick Actions
                    </h3>

                </div>


                <div class="quick-actions">


                    <a
                        href="student_management.php"
                        class="quick-action"
                    >

                        <strong>
                            🎓 Manage Students
                        </strong>

                        <span>
                            Add, edit and manage students
                        </span>

                    </a>


                    <a
                        href="teacher_management.php"
                        class="quick-action"
                    >

                        <strong>
                            👨‍🏫 Manage Teachers
                        </strong>

                        <span>
                            Manage teacher accounts
                        </span>

                    </a>


                    <a
                        href="role_management.php"
                        class="quick-action"
                    >

                        <strong>
                            🔐 Roles & Permissions
                        </strong>

                        <span>
                            Control admin and user rights
                        </span>

                    </a>


                    <a
                        href="import_questions.php"
                        class="quick-action"
                    >

                        <strong>
                            📥 Import Questions
                        </strong>

                        <span>
                            Import MCQs through CSV
                        </span>

                    </a>


                    <a
                        href="exam_management.php"
                        class="quick-action"
                    >

                        <strong>
                            📝 Manage Exams
                        </strong>

                        <span>
                            Create and manage examinations
                        </span>

                    </a>


                    <a
                        href="reports.php"
                        class="quick-action"
                    >

                        <strong>
                            📊 View Reports
                        </strong>

                        <span>
                            Examination analytics and reports
                        </span>

                    </a>


                </div>

            </div>


            <!-- SYSTEM STATUS -->

            <div class="card">

                <div class="card-header">

                    <h3>
                        System Status
                    </h3>

                </div>


                <div class="status-row">

                    <span>
                        Database
                    </span>

                    <span class="status">
                        ● Online
                    </span>

                </div>


                <div class="status-row">

                    <span>
                        Examination System
                    </span>

                    <span class="status">
                        ● Active
                    </span>

                </div>


                <div class="status-row">

                    <span>
                        AI Proctoring
                    </span>

                    <span class="status">
                        ● Ready
                    </span>

                </div>


                <div class="status-row">

                    <span>
                        Active Exams
                    </span>

                    <span class="status-warning">
                        <?= $active_exams ?>
                    </span>

                </div>


                <div class="status-row">

                    <span>
                        Total Exams
                    </span>

                    <strong>
                        <?= $exams ?>
                    </strong>

                </div>

            </div>

        </div>


        <div class="footer">

            AI-Powered Online Examination System
            © <?= date('Y') ?>

        </div>

    </section>

</main>

</body>
</html>
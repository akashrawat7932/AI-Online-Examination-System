<?php 
require_once __DIR__ . '/../config/config.php';
require_role('teacher'); 

// Fetch Teacher Info
$s = db()->prepare('SELECT id, teacher_name, department FROM teachers WHERE user_id = ?');
$s->execute([$_SESSION['user_id']]);
$t = $s->fetch();

if (!$t) {
    exit('Teacher profile not found.');
}

// Fetch Exams from Database
$examStmt = db()->prepare('
    SELECT e.id, e.exam_title, e.duration_minutes, e.status, s.subject_name 
    FROM exams e 
    JOIN subjects s ON s.id = e.subject_id 
    ORDER BY e.id DESC
');
$examStmt->execute();
$exams = $examStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg-main: #f8fafc;
            --bg-surface: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        aside {
            width: 260px;
            background-color: var(--bg-surface);
            border-right: 1px solid var(--border-color);
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            padding-left: 0.5rem;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .nav-link {
            display: block;
            padding: 0.75rem 1rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .nav-link:hover, .nav-link.active {
            background-color: #eef2ff;
            color: var(--primary);
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        header {
            background-color: var(--bg-surface);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background-color 0.2s;
            display: inline-block;
        }

        .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-dark);
        }

        .btn-outline:hover {
            background-color: var(--bg-main);
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
            text-align: center;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            text-align: center;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .container {
            padding: 2rem;
            max-width: 1200px;
        }

        .card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .exam-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .exam-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 1rem;
            background-color: #fafafa;
        }

        .exam-title {
            font-weight: 600;
            font-size: 1rem;
        }

        .subject-name {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .empty-state {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            aside { width: 100%; border-right: none; border-bottom: 1px solid var(--border-color); }
        }
    </style>
</head>
<body>

    <!-- Left Sidebar -->
    <aside>
        <div>
            <div class="brand">Teacher Dashboard</div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
                <li><a href="question_add.php" class="nav-link">Add Question</a></li>
                <li><a href="result.php" class="nav-link">Results</a></li>
                <li><a href="exam_create.php" class="nav-link">Create Exam</a></li>
                <li><a href="import_questions.php" class="nav-link"> Create Exam Upload file</a></li>
            </ul>
        </div>
        <a href="<?= e(BASE_URL) ?>/logout.php" class="btn btn-danger">Logout</a>
    </aside>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Header Bar -->
        <header>
            <h1>Dashboard</h1>
            <div class="user-info">
                <span class="user-name"><?= e($t['teacher_name'] ?? 'Teacher') ?></span>
                <a href="profile.php" class="btn btn-outline">Profile</a>
            </div>
        </header>
<?php 
require_once __DIR__ . '/../config/config.php';
require_role('student');

$s = db()->prepare('SELECT id, student_name, enrollment_no FROM students WHERE user_id = ?');
$s->execute([$_SESSION['user_id']]);
$st = $s->fetch();

$ex = db()->query("SELECT e.id, e.exam_title, s.subject_name 
                   FROM exams e 
                   JOIN subjects s ON s.id = e.subject_id 
                   WHERE e.status IN ('scheduled', 'active') 
                   ORDER BY e.start_datetime")->fetchAll();
$row = db()->prepare('SELECT id, attempt_id from results')->fetchAll();

?>  
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Left Sidebar -->
    <aside>
        <div>
            <div class="brand">Student Portal</div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
            
                <li><a href="result.php" class="nav-link">View Result</a></li>
                <li><a href="#" class="nav-link">Overall Performance</a></li>
            </ul>
        </div>
        <a href="<?= e(BASE_URL) ?>/logout.php" class="btn btn-danger">Logout</a>
    </aside>
 <!-- Main Content Area -->
    <div class="main-content">
        <!-- Top Bar Header -->
        <header>
            <h1>Dashboard</h1>
            <div class="user-info">
                <span class="user-name"><?= e($st['student_name'] ?? 'Student') ?></span>
                <a href="profile.php" class="btn btn-outline">Profile</a>
            </div>
        </header>

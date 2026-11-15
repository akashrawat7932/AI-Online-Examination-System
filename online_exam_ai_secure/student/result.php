<?php include_once 'hearder.php' ?>
<?php 
require_once __DIR__ . '/../config/config.php';
require_role('student');

$user_id = $_SESSION['user_id'];

// 1. Fetch Logged-in Student Info
$s = db()->prepare('SELECT id, student_name, enrollment_no FROM students WHERE user_id = ?');
$s->execute([$user_id]);
$st = $s->fetch();

if (!$st) {
    exit('Student record not found.');
}

// 2. Fetch All Results for Dropdown Select
$s = db()->prepare('
    SELECT r.id, r.attempt_id, e.exam_title, r.result_status 
    FROM results r 
    JOIN exams e ON e.id = r.exam_id 
    WHERE r.student_id = ?
    ORDER BY r.id DESC
');
$s->execute([$st['id']]);
$user_results = $s->fetchAll();

// 3. Get Selected Result (URL parameter 'id' or default to Latest Result)
$selected_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if ($selected_id) {
    $s = db()->prepare('
        SELECT r.*, e.exam_title 
        FROM results r 
        JOIN exams e ON e.id = r.exam_id 
        WHERE r.id = ? AND r.student_id = ?
    ');
    $s->execute([$selected_id, $st['id']]);
    $r = $s->fetch();
} else {
    // Pick the latest attempt if no ID selected
    $r = !empty($user_results) ? $user_results[0] : null;
    if ($r) {
        $s = db()->prepare('
            SELECT r.*, e.exam_title 
            FROM results r 
            JOIN exams e ON e.id = r.exam_id 
            WHERE r.id = ? AND r.student_id = ?
        ');
        $s->execute([$r['id'], $st['id']]);
        $r = $s->fetch();
    }
}

// Pass/Fail Status Check
$is_pass = false;
if ($r) {
    $status = strtolower($r['result_status'] ?? 'pass');
    $is_pass = ($status === 'pass' || (float)$r['percentage'] >= 40);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result - <?= $r ? e($r['exam_title']) : 'No Result' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="result.css"> 

</head>
<body>
  <div class="container">
  
    <div class="result-card">
        
        <!-- <\Logged-in User Dropdown Selector -->
        <?php if (!empty($user_results)): ?>
            <form method="GET" action="result.php">
                <select name="id" class="select-box" onchange="this.form.submit()">
                    <?php foreach ($user_results as $item): ?>
                        <option value="<?= e($item['id']) ?>" <?= ($r && $r['id'] == $item['id']) ? 'selected' : '' ?>>
                            <?= e($item['exam_title']) ?> (Attempt #<?= e($item['attempt_id']) ?> - <?= strtoupper(e($item['result_status'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>

        <?php if ($r): ?>
            <!-- Status Badge -->
            <span class="badge <?= $is_pass ? 'badge-pass' : 'badge-fail' ?>">
                <?= $is_pass ? 'Passed' : 'Failed' ?>
            </span>

            <!-- Exam Title & Student Details -->
            <h1 class="exam-title"><?= e($r['exam_title']) ?></h1>
            <div class="student-meta">
                Student: <strong><?= e($st['student_name']) ?></strong> | Roll: <strong><?= e($st['enrollment_no']) ?></strong>
            </div>

            <!-- Performance Stats -->
            <div class="metrics-grid">
                <div class="metric-box">
                    <div class="metric-label">Score Obtained</div>
                    <div class="metric-value">
                        <?= e((string)$r['obtained_marks']) ?> 
                        <span style="font-size:0.85rem; font-weight:normal; color:var(--text-muted);">/ <?= e((string)$r['total_marks']) ?></span>
                    </div>
                </div>

                <div class="metric-box">
                    <div class="metric-label">Percentage</div>
                    <div class="metric-value"><?= e(number_format((float)$r['percentage'], 2)) ?>%</div>
                </div>

                <div class="metric-box">
                    <div class="metric-label">Grade</div>
                    <div class="metric-value"><?= e($r['grade'] ?? 'N/A') ?></div>
                </div>

                <div class="metric-box">
                    <div class="metric-label">Result Status</div>
                    <div class="metric-value" style="color: <?= $is_pass ? 'var(--success)' : 'var(--danger)' ?>;">
                        <?= strtoupper(e($r['result_status'] ?? ($is_pass ? 'PASS' : 'FAIL'))) ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p style="margin: 2rem 0; color: var(--text-muted);">No results found for your account.</p>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="actions">
            <a href="dashboard.php" class="btn_n btn-primary">Back to Dashboard</a>
            <button onclick="window.print()" class="btn_n btn-secondary">Print Result</button>
        </div>
    </div>
  </div>
  </div>
</body>
</html>
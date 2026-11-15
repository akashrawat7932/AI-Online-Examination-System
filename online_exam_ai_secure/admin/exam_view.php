<?php
require_once __DIR__ . '/../config/config.php';
require_role('teacher');

$exam_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$exam_id) {
    exit('Invalid Exam ID.');
}

$pdo = db();

// 1. Exam & Subject Details Fetch Karein
$stmt = $pdo->prepare('
    SELECT e.*, s.subject_name, s.subject_code 
    FROM exams e 
    JOIN subjects s ON s.id = e.subject_id 
    WHERE e.id = ?
');
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    exit('Exam not found.');
}

// 2. Mapped Questions Fetch Karein
$stmtQ = $pdo->prepare('
    SELECT q.*, eq.marks as question_marks 
    FROM questions q 
    JOIN exam_questions eq ON eq.question_id = q.id 
    WHERE eq.exam_id = ?
');
$stmtQ->execute([$exam_id]);
$questions = $stmtQ->fetchAll();

// 3. Question Options Fetch Karein
$question_ids = array_column($questions, 'id');
$options_by_question = [];

if (!empty($question_ids)) {
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    $stmtOpt = $pdo->prepare("SELECT * FROM question_options WHERE question_id IN ($placeholders) ORDER BY option_label ASC");
    $stmtOpt->execute($question_ids);
    $all_options = $stmtOpt->fetchAll();

    foreach ($all_options as $opt) {
        $options_by_question[$opt['question_id']][] = $opt;
    }
}

// 4. Total Student Attempts Count
$stmtStats = $pdo->prepare('
    SELECT COUNT(*) as total_attempts 
    FROM exam_attempts 
    WHERE exam_id = ? AND status IN ("submitted", "auto_submitted")
');
$stmtStats->execute([$exam_id]);
$stats = $stmtStats->fetch();

$total_marks = array_sum(array_column($questions, 'question_marks'));
// Status Update Handle Karein
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $new_status = $_POST['status'];
    $allowed = ['scheduled', 'active', 'completed'];

    if (in_array($new_status, $allowed, true)) {
        $updateStmt = $pdo->prepare('UPDATE exams SET status = ? WHERE id = ?');
        $updateStmt->execute([$new_status, $exam_id]);
        
        // Page Refresh
        header("Location: exam_view.php?id=" . $exam_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Exam - <?= e($exam['exam_title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --bg-main: #f8fafc;
            --bg-surface: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #16a34a;
            --success-bg: #dcfce7;
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
            padding: 2rem;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
        }

        .btn-outline {
            border: 1px solid var(--border);
            color: var(--text-dark);
            background-color: var(--bg-surface);
        }

        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .exam-header-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .stat-box {
            background-color: #f1f5f9;
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .stat-value {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .question-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .q-header {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .options-list {
            list-style: none;
            margin-top: 0.5rem;
        }

        .option-item {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }

        .option-item.correct {
            background-color: var(--success-bg);
            border-color: var(--success);
            color: var(--success);
            font-weight: 600;
        }

        .badge {
            background-color: #e2e8f0;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-select {
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid var(--border);
    outline: none;
    text-transform: uppercase;
}

/* Dynamic Colors Based on Status */
.status-active {
    background-color: #dcfce7;
    color: #16a34a;
    border-color: #86efac;
}

.status-scheduled {
    background-color: #fef3c7;
    color: #d97706;
    border-color: #fde68a;
}

.status-completed {
    background-color: #e2e8f0;
    color: #475569;
    border-color: #cbd5e1;
}
    </style>
</head>
<body>
<div class="top-bar">
    <a href="dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

    <!-- Status Update Dropdown Form -->
    <form method="post" style="display: inline;">
        <select name="status" onchange="this.form.submit()" class="status-select status-<?= e($exam['status']) ?>">
            <option value="scheduled" <?= $exam['status'] === 'scheduled' ? 'selected' : '' ?>>SCHEDULED</option>
            <option value="active" <?= $exam['status'] === 'active' ? 'selected' : '' ?>>ACTIVE</option>
            <option value="completed" <?= $exam['status'] === 'completed' ? 'selected' : '' ?>>COMPLETED</option>
        </select>
    </form>
</div>


    <!-- Exam Info Summary -->
    <div class="card">
        <h2><?= e($exam['exam_title']) ?></h2>
        <p style="color: var(--text-muted); margin-top: 0.25rem;">Subject: <?= e($exam['subject_name']) ?> (<?= e($exam['subject_code']) ?>)</p>

        <div class="exam-header-grid">
            <div class="stat-box">
                <div class="stat-label">Duration</div>
                <div class="stat-value"><?= e((string)$exam['duration_minutes']) ?> Mins</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Questions</div>
                <div class="stat-value"><?= count($questions) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Marks</div>
                <div class="stat-value"><?= e((string)$total_marks) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Attempts Submitted</div>
                <div class="stat-value"><?= e((string)$stats['total_attempts']) ?></div>
            </div>
        </div>
    </div>

    <!-- Questions List -->
    <h3>Exam Questions</h3>
    <br>

    <?php if (empty($questions)): ?>
        <div class="card">
            <p style="color: var(--text-muted);">Is exam me abhi koi questions link nahi hain.</p>
        </div>
    <?php else: ?>
        <?php foreach ($questions as $index => $q): ?>
            <div class="question-card">
                <div class="q-header">
                    <span>Q<?= $index + 1 ?>. <?= e($q['question_text']) ?></span>
                    <span class="badge"><?= e((string)$q['question_marks']) ?> Marks</span>
                </div>

                <?php if (isset($options_by_question[$q['id']])): ?>
                    <ul class="options-list">
                        <?php foreach ($options_by_question[$q['id']] as $opt): ?>
                            <li class="option-item <?= $opt['is_correct'] ? 'correct' : '' ?>">
                                <span><strong><?= e($opt['option_label']) ?>.</strong> <?= e($opt['option_text']) ?></span>
                                <?php if ($opt['is_correct']): ?>
                                    <span>✓ Correct</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

</body>
</html>
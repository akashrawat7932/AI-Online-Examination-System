<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_role('admin');

$pdo = db();

function evaluation_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $answerId = (int)($_POST['answer_id'] ?? 0);
    $marks = filter_var($_POST['obtained_marks'] ?? null, FILTER_VALIDATE_FLOAT);
    $feedback = trim($_POST['feedback'] ?? '');

    if ($answerId < 1 || $marks === false || $marks < 0) {
        $error = 'Please enter valid marks.';
    } else {
        $check = $pdo->prepare("
            SELECT sa.id, eq.marks AS maximum_marks
            FROM student_answers sa
            INNER JOIN questions q ON q.id = sa.question_id
            INNER JOIN exam_attempts ea ON ea.id = sa.attempt_id
            INNER JOIN exam_questions eq
                ON eq.exam_id = ea.exam_id AND eq.question_id = sa.question_id
            WHERE sa.id = ? AND q.question_type = 'descriptive'
            LIMIT 1
        ");
        $check->execute([$answerId]);
        $answerRow = $check->fetch(PDO::FETCH_ASSOC);

        if (!$answerRow) {
            $error = 'Descriptive answer not found.';
        } elseif ((float)$marks > (float)$answerRow['maximum_marks']) {
            $error = 'Obtained marks cannot exceed maximum marks (' . $answerRow['maximum_marks'] . ').';
        } else {
            try {
                $pdo->beginTransaction();

                $update = $pdo->prepare("
                    UPDATE student_answers
                    SET obtained_marks = ?, evaluation_status = 'evaluated'
                    WHERE id = ?
                ");
                $update->execute([$marks, $answerId]);

                $attemptQuery = $pdo->prepare("
                    SELECT attempt_id FROM student_answers WHERE id = ?
                ");
                $attemptQuery->execute([$answerId]);
                $attemptId = (int)$attemptQuery->fetchColumn();

                $sumQuery = $pdo->prepare("
                    SELECT COALESCE(SUM(obtained_marks), 0)
                    FROM student_answers
                    WHERE attempt_id = ?
                ");
                $sumQuery->execute([$attemptId]);
                $totalObtained = (float)$sumQuery->fetchColumn();

                $updateAttempt = $pdo->prepare("
                    UPDATE exam_attempts
                    SET obtained_marks = ?
                    WHERE id = ?
                ");
                $updateAttempt->execute([$totalObtained, $attemptId]);

                $pdo->commit();
                $message = 'Marks saved successfully.';
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Could not save evaluation. Check your database columns and evaluation_status values.';
            }
        }
    }
}

$filter = trim($_GET['filter'] ?? 'pending');

$sql = "
    SELECT
        sa.id AS answer_id,
        sa.attempt_id,
        sa.answer_text,
        sa.obtained_marks,
        sa.evaluation_status,
        ea.exam_id,
        ea.student_id,
        ea.status AS attempt_status,
        e.exam_title,
        s.student_name,
        s.enrollment_no,
        q.question_text,
        q.model_answer,
        eq.marks AS maximum_marks
    FROM student_answers sa
    INNER JOIN questions q ON q.id = sa.question_id
    INNER JOIN exam_attempts ea ON ea.id = sa.attempt_id
    INNER JOIN exam_questions eq
        ON eq.exam_id = ea.exam_id AND eq.question_id = sa.question_id
    LEFT JOIN exams e ON e.id = ea.exam_id
    LEFT JOIN students s ON s.id = ea.student_id
    WHERE q.question_type = 'descriptive'
";

if ($filter === 'pending') {
    $sql .= " AND (sa.evaluation_status IS NULL OR sa.evaluation_status IN ('pending','not_evaluated'))";
} elseif ($filter === 'evaluated') {
    $sql .= " AND sa.evaluation_status = 'evaluated'";
}

$sql .= " ORDER BY sa.saved_at DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pendingCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM student_answers sa
    INNER JOIN questions q ON q.id = sa.question_id
    WHERE q.question_type = 'descriptive'
      AND (sa.evaluation_status IS NULL OR sa.evaluation_status IN ('pending','not_evaluated'))
")->fetchColumn();

$evaluatedCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM student_answers sa
    INNER JOIN questions q ON q.id = sa.question_id
    WHERE q.question_type = 'descriptive'
      AND sa.evaluation_status = 'evaluated'
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Evaluation | Admin</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#172033;font-family:Arial,sans-serif}
.wrap{max-width:1250px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}
h1{margin:0 0 7px;font-size:27px}.muted{color:#718096;font-size:13px}
.back{background:#172554;color:#fff;padding:11px 16px;border-radius:9px;text-decoration:none}
.stats{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;margin:24px 0}
.stat,.answer{background:#fff;border:1px solid #e6ebf2;border-radius:14px;padding:20px;margin-bottom:16px;box-shadow:0 4px 15px #17255408}
.stat strong{display:block;font-size:26px;margin-top:8px}.stat span{color:#64748b;font-size:13px}
.answer h3{font-size:16px;line-height:1.5}.meta{font-size:12px;color:#64748b;margin:8px 0 15px}
.block{background:#f8fafc;padding:14px;border-radius:9px;margin:12px 0;line-height:1.6;white-space:pre-wrap}
.model{border-left:4px solid #16a34a}.student{border-left:4px solid #2563eb}
label{display:block;font-size:13px;font-weight:bold;margin:12px 0 6px}
input,textarea{width:100%;padding:11px;border:1px solid #d8e0eb;border-radius:8px;font:inherit}
textarea{min-height:75px;resize:vertical}
button{margin-top:12px;background:#2563eb;color:white;border:0;padding:11px 18px;border-radius:8px;cursor:pointer}
.notice{padding:12px 15px;border-radius:9px;margin:16px 0;background:#dcfce7;color:#166534}
.error{background:#fee2e2;color:#991b1b}.tabs{display:flex;gap:10px;margin:18px 0}
.tabs a{padding:10px 14px;background:white;border-radius:8px;text-decoration:none;color:#334155;border:1px solid #e2e8f0}
.tabs a.selected{background:#dbeafe;color:#1d4ed8}
@media(max-width:650px){.wrap{padding:15px}.stats{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>🤖 AI Answer Evaluation</h1>
            <div class="muted">Review descriptive answers and record marks</div>
        </div>
        <a class="back" href="<?= BASE_URL ?>/admin/dashboard.php">← Dashboard</a>
    </div>

    <div class="stats">
        <div class="stat"><span>Pending Descriptive Answers</span><strong><?= $pendingCount ?></strong></div>
        <div class="stat"><span>Evaluated Answers</span><strong><?= $evaluatedCount ?></strong></div>
    </div>

    <?php if ($message): ?><div class="notice"><?= evaluation_h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= evaluation_h($error) ?></div><?php endif; ?>

    <div class="tabs">
        <a class="<?= $filter === 'pending' ? 'selected' : '' ?>" href="?filter=pending">Pending</a>
        <a class="<?= $filter === 'evaluated' ? 'selected' : '' ?>" href="?filter=evaluated">Evaluated</a>
        <a class="<?= $filter === 'all' ? 'selected' : '' ?>" href="?filter=all">All</a>
    </div>

    <?php if (!$answers): ?>
        <div class="answer">No descriptive answers found in this category.</div>
    <?php endif; ?>

    <?php foreach ($answers as $a): ?>
        <div class="answer">
            <div class="meta">
                Attempt #<?= (int)$a['attempt_id'] ?> |
                <?= evaluation_h($a['student_name'] ?? 'Unknown student') ?>
                (<?= evaluation_h($a['enrollment_no'] ?? '') ?>) |
                <?= evaluation_h($a['exam_title'] ?? 'Unknown exam') ?>
            </div>

            <h3><?= nl2br(evaluation_h($a['question_text'] ?? 'Question unavailable')) ?></h3>
            <div class="meta">Maximum Marks: <?= evaluation_h((string)$a['maximum_marks']) ?></div>

            <label>Student Answer</label>
            <div class="block student"><?= nl2br(evaluation_h($a['answer_text'] ?? 'No answer submitted')) ?></div>

            <label>Model Answer / Reference Answer</label>
            <div class="block model"><?= nl2br(evaluation_h($a['model_answer'] ?? 'No model answer provided by teacher')) ?></div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= evaluation_h(csrf_token()) ?>">
                <input type="hidden" name="answer_id" value="<?= (int)$a['answer_id'] ?>">

                <label>Marks Obtained</label>
                <input type="number" name="obtained_marks" min="0"
                       max="<?= (float)$a['maximum_marks'] ?>" step="0.01"
                       value="<?= evaluation_h((string)($a['obtained_marks'] ?? 0)) ?>" required>

                <button type="submit">Save Evaluation</button>
            </form>
            <div class="meta">Current status: <?= evaluation_h($a['evaluation_status'] ?? 'pending') ?></div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
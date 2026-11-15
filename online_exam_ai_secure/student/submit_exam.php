<?php
require_once __DIR__ . '/../config/config.php';
require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid method');
}

verify_csrf();

$aid = filter_input(INPUT_POST, 'attempt_id', FILTER_VALIDATE_INT);
if (!$aid) {
    http_response_code(400);
    exit('Invalid attempt ID');
}

$s = db()->prepare('SELECT id FROM students WHERE user_id = ?');
$s->execute([$_SESSION['user_id']]);
$sid = (int)$s->fetchColumn();

$pdo = db();
$pdo->beginTransaction();

try {
    $a = $pdo->prepare('SELECT * FROM exam_attempts WHERE id = ? AND student_id = ? FOR UPDATE');
    $a->execute([$aid, $sid]);
    $at = $a->fetch();

    if (!$at || !in_array($at['status'], ['started', 'active'])) {
        throw new Exception('Invalid attempt status');
    }

    $q = $pdo->prepare('SELECT eq.question_id, eq.marks, q.question_type FROM exam_questions eq JOIN questions q ON q.id = eq.question_id WHERE eq.exam_id = ?');
    $q->execute([$at['exam_id']]);

    $ins = $pdo->prepare("INSERT INTO student_answers(attempt_id, question_id, selected_option_id, answer_text, obtained_marks, evaluation_status) 
    VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE selected_option_id = VALUES(selected_option_id), answer_text = VALUES(answer_text)");

    $score = 0;
    $total = 0;

    foreach ($q as $x) {
        $qid = (int)$x['question_id'];
        $m = (float)$x['marks'];
        $total += $m;
        $op = null;
        $txt = null;
        $got = 0;
        $st = 'pending';

        if ($x['question_type'] === 'mcq') {
            $op = isset($_POST['answers'][$qid]) ? filter_var($_POST['answers'][$qid], FILTER_VALIDATE_INT) : null;
            if ($op) {
                $z = $pdo->prepare('SELECT is_correct FROM question_options WHERE id = ? AND question_id = ?');
                $z->execute([$op, $qid]);
                $ok = $z->fetchColumn();
                if ($ok === false) {
                    $op = null;
                } elseif ((int)$ok === 1) {
                    $got = $m;
                    $st = 'auto_evaluated';
                } else {
                    $st = 'auto_evaluated';
                }
            }
        } else {
            $txt = trim((string)($_POST['descriptive'][$qid] ?? ''));
            $txt = substr($txt, 0, 20000);
        }

        $ins->execute([$aid, $qid, $op, $txt, $got, $st]);
        $score += $got;
    }

    $u = $pdo->prepare("UPDATE exam_attempts SET submit_time = NOW(), status = 'submitted', total_marks = ?, obtained_marks = ? WHERE id = ?");
    $u->execute([$total, $score, $aid]);

    $pct = $total ? round($score / $total * 100, 2) : 0;
    $g = $pct >= 90 ? 'A+' : ($pct >= 80 ? 'A' : ($pct >= 70 ? 'B' : ($pct >= 60 ? 'C' : ($pct >= 50 ? 'D' : 'F'))));

    $r = $pdo->prepare("INSERT INTO results(attempt_id, student_id, exam_id, total_marks, obtained_marks, percentage, grade, result_status) VALUES(?,?,?,?,?,?,?,?)");
    $r->execute([$aid, $sid, $at['exam_id'], $total, $score, $pct, $g, $pct >= 40 ? 'pass' : 'fail']);

    $pdo->commit();
    header('Location: result.php?attempt_id=' . $aid);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo 'Submission failed safely: ' . htmlspecialchars($e->getMessage());
    exit;
}
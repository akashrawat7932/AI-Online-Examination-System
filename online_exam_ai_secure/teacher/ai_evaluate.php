<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_role('teacher', 'admin');

$attemptId = filter_input(INPUT_GET, 'attempt_id', FILTER_VALIDATE_INT);
if (!$attemptId) {
    exit('Invalid attempt reference.');
}

$pdo = db();

$stmt = $pdo->prepare('
    SELECT da.id, da.question_id, da.answer_text, q.question_text, q.model_answer, eq.marks
    FROM attempt_descriptive_answers da
    JOIN exam_attempts ea ON ea.id = da.attempt_id
    JOIN exam_questions eq ON eq.exam_id = ea.exam_id AND eq.question_id = da.question_id
    JOIN questions q ON q.id = da.question_id
    WHERE da.attempt_id = ?
');
$stmt->execute([$attemptId]);
$answers = $stmt->fetchAll();

$totalAiMarks = 0.0;

foreach ($answers as $ans) {
    $payload = json_encode([
        'question' => $ans['question_text'],
        'answer' => $ans['answer_text'],
        'model_answer' => $ans['model_answer'],
        'max_marks' => (float)$ans['marks']
    ]);

    $ch = curl_init(AI_API_URL . '/evaluate');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_TOKEN
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $result = json_decode($response, true);
        $score = (float)($result['score'] ?? 0.0);
        $feedback = $result['feedback'] ?? '';

        $totalAiMarks += $score;

        $upd = $pdo->prepare('
            UPDATE attempt_descriptive_answers 
            SET ai_suggested_marks = ?, final_marks = ?, ai_feedback = ? 
            WHERE id = ?
        ');
        $upd->execute([$score, $score, $feedback, $ans['id']]);
    }
}

// Update attempt totals
$updAttempt = $pdo->prepare('
    UPDATE exam_attempts 
    SET descriptive_score = ?, total_score = mcq_score + ?, status = "evaluated" 
    WHERE id = ?
');
$updAttempt->execute([$totalAiMarks, $totalAiMarks, $attemptId]);

header('Location: proctor_report.php?attempt_id=' . $attemptId);
exit;
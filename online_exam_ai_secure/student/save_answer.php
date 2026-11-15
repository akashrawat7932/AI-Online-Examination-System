<?php
require_once __DIR__ . '/../config/config.php';
require_role('student');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

verify_csrf();

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$attemptId = (int)($data['attempt_id'] ?? 0);
$answers   = $data['answers'] ?? [];

if ($attemptId <= 0 || !is_array($answers)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request payload'
    ]);
    exit;
}

$pdo = db();

$check = $pdo->prepare("
    SELECT ea.id
    FROM exam_attempts ea
    INNER JOIN students st ON st.id = ea.student_id
    WHERE ea.id = ?
      AND st.user_id = ?
      AND ea.status IN ('started', 'active')
    LIMIT 1
");
$check->execute([$attemptId, $userId]);

if (!$check->fetch()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid examination attempt'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO student_answers
        (
            attempt_id,
            question_id,
            answer_text,
            evaluation_status
        )
        VALUES
        (?, ?, ?, 'pending')

        ON DUPLICATE KEY UPDATE
            answer_text = VALUES(answer_text)
    ");

    foreach ($answers as $item) {
        $questionId = (int)($item['question_id'] ?? 0);
        $answer     = (string)($item['answer'] ?? '');

        if ($questionId <= 0) {
            continue;
        }

        if (mb_strlen($answer) > 10000) {
            $answer = mb_substr($answer, 0, 10000);
        }

        $stmt->execute([
            $attemptId,
            $questionId,
            $answer
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'saved_at' => date('Y-m-d H:i:s')
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Answers could not be saved'
    ]);
}
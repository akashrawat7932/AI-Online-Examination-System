<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_role('student');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]));
}

verify_csrf();

$attemptId = filter_input(
    INPUT_POST,
    'attempt_id',
    FILTER_VALIDATE_INT
);

$deviceId = trim(
    (string)($_POST['device_id'] ?? '')
);

if (
    !$attemptId ||
    $deviceId === '' ||
    strlen($deviceId) > 128
) {
    http_response_code(400);

    exit(json_encode([
        'success' => false,
        'message' => 'Invalid device information'
    ]));
}

$pdo = db();

/*
|--------------------------------------------------------------------------
| Verify that attempt belongs to logged-in student
|--------------------------------------------------------------------------
*/

$check = $pdo->prepare("
    SELECT ea.id
    FROM exam_attempts ea
    INNER JOIN students s
        ON s.id = ea.student_id
    WHERE ea.id = ?
      AND s.user_id = ?
      AND ea.status IN ('started', 'active')
    LIMIT 1
");

$check->execute([
    $attemptId,
    (int)$_SESSION['user_id']
]);

if (!$check->fetch()) {

    http_response_code(403);

    exit(json_encode([
        'success' => false,
        'message' => 'Invalid exam attempt'
    ]));
}

/*
|--------------------------------------------------------------------------
| Server detects IP
|--------------------------------------------------------------------------
*/

$ip = $_SERVER['REMOTE_ADDR'] ?? null;

/*
|--------------------------------------------------------------------------
| Save IP + Device ID
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE exam_attempts
    SET
        ip_address = ?,
        device_id = ?
    WHERE id = ?
");

$stmt->execute([
    $ip,
    $deviceId,
    $attemptId
]);

echo json_encode([
    'success' => true,
    'message' => 'Device registered'
]);
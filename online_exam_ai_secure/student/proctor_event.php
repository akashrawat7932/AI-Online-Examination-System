
<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.'
    ]);
    exit;
}

verify_csrf();

$pdo = db();

$attemptId  = (int)($_POST['attempt_id'] ?? 0);
$eventType  = strtolower(trim($_POST['event_type'] ?? ''));
$message    = trim($_POST['event_message'] ?? '');
$faceCount  = max(0, (int)($_POST['face_count'] ?? 0));
$riskPoints = max(0, (int)($_POST['risk_points'] ?? 0));
$deviceId   = trim($_POST['device_id'] ?? '');
$snapshot   = $_POST['snapshot'] ?? '';

if ($attemptId <= 0 || $eventType === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Attempt ID and event type are required.'
    ]);
    exit;
}

// Old JavaScript event names ko database ENUM values mein convert karo.
$eventAliases = [
    'tab_switch'          => 'tab_switch',
    'fullscreen_exit'    => 'fullscreen_exit',
    'face_missing'       => 'face_missing',
    'multiple_faces'     => 'multiple_faces',
    'camera_off'         => 'camera_off',
    'looking_away'       => 'looking_away',
    'mobile_detected'    => 'phone_detected',
    'phone_detected'     => 'phone_detected',
    'unknown_object'     => 'unknown_object',
    'audio_detected'     => 'audio_detected',
    'suspicious_activity'=> 'suspicious_activity'
];

if (!isset($eventAliases[$eventType])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid event type.'
    ]);
    exit;
}

$eventType = $eventAliases[$eventType];

// Risk points ko database ke risk_level ENUM mein convert karo.
if ($riskPoints >= 10) {
    $riskLevel = 'critical';
} elseif ($riskPoints >= 5) {
    $riskLevel = 'high';
} elseif ($riskPoints >= 3) {
    $riskLevel = 'medium';
} else {
    $riskLevel = 'low';
}

$studentId = 0;
$savedFile = null;

try {
    // Check karo ki attempt isi logged-in student ka hai.
    $check = $pdo->prepare("
        SELECT ea.id, ea.student_id
        FROM exam_attempts ea
        INNER JOIN students s ON s.id = ea.student_id
        WHERE ea.id = ?
          AND s.user_id = ?
          AND ea.status = 'started'
        LIMIT 1
    ");

    $check->execute([
        $attemptId,
        (int)$_SESSION['user_id']
    ]);

    $attempt = $check->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid attempt or exam is not active.'
        ]);
        exit;
    }

    $studentId = (int)$attempt['student_id'];

    $pdo->beginTransaction();

    // 1. Proctoring event save karo.
    $stmt = $pdo->prepare("
        INSERT INTO proctoring_logs
        (
            attempt_id,
            student_id,
            event_type,
            event_message,
            face_count,
            risk_level
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $attemptId,
        $studentId,
        $eventType,
        $message !== '' ? $message : null,
        $faceCount,
        $riskLevel
    ]);

    $logId = (int)$pdo->lastInsertId();

    // 2. Tab switch counter update karo.
    if ($eventType === 'tab_switch') {
        $update = $pdo->prepare("
            UPDATE exam_attempts
            SET tab_switches = tab_switches + 1
            WHERE id = ? AND student_id = ?
        ");

        $update->execute([$attemptId, $studentId]);
    }

    // 3. Device ID save/update karo.
    if ($deviceId !== '') {
        $updateDevice = $pdo->prepare("
            UPDATE exam_attempts
            SET device_id = ?
            WHERE id = ? AND student_id = ?
        ");

        $updateDevice->execute([
            substr($deviceId, 0, 128),
            $attemptId,
            $studentId
        ]);
    }

    // 4. Snapshot available ho to image save karo.
    if ($snapshot !== '') {

        if (!preg_match(
            '/^data:image\/(jpeg|jpg|png|webp);base64,/',
            $snapshot,
            $matches
        )) {
            throw new Exception('Invalid snapshot image format.');
        }

        $extension = strtolower($matches[1]);
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $base64Data = substr($snapshot, strpos($snapshot, ',') + 1);
        $imageData = base64_decode($base64Data, true);

        if ($imageData === false || strlen($imageData) > 5 * 1024 * 1024) {
            throw new Exception('Invalid or oversized snapshot.');
        }

        $imageInfo = @getimagesizefromstring($imageData);

        if ($imageInfo === false) {
            throw new Exception('Snapshot is not a valid image.');
        }

        $uploadDir = __DIR__ . '/../uploads/snapshots/';

        if (!is_dir($uploadDir) &&
            !mkdir($uploadDir, 0755, true)) {
            throw new Exception('Could not create snapshot directory.');
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $savedFile = $uploadDir . $fileName;

        if (file_put_contents($savedFile, $imageData) === false) {
            throw new Exception('Could not save snapshot.');
        }

        $relativePath = 'uploads/snapshots/' . $fileName;

        // 5. Snapshot record database mein save karo.
        $snap = $pdo->prepare("
            INSERT INTO proctoring_snapshots
            (
                attempt_id,
                student_id,
                log_id,
                image_path
            )
            VALUES (?, ?, ?, ?)
        ");

        $snap->execute([
            $attemptId,
            $studentId,
            $logId,
            $relativePath
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Proctoring event saved successfully.',
        'log_id' => $logId,
        'event_type' => $eventType,
        'risk_level' => $riskLevel
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Database transaction fail ho to saved image bhi delete karo.
    if ($savedFile && file_exists($savedFile)) {
        unlink($savedFile);
    }

    error_log('Proctor event error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Could not save proctoring event. Check PHP error log.'
    ]);
}

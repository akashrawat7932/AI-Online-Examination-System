<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_role('admin');

$pdo = db();

function logs_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$eventType = trim($_GET['event_type'] ?? '');

$sql = "
    SELECT
        pl.id,
        pl.attempt_id,
        pl.event_type,
        pl.event_message,
        pl.risk_level,
        ea.student_id,
        ea.exam_id,
        ea.device_id,
        ea.start_time,
        s.student_name,
        s.enrollment_no,
        e.exam_title
    FROM proctoring_logs pl
    LEFT JOIN exam_attempts ea ON ea.id = pl.attempt_id
    LEFT JOIN students s ON s.id = ea.student_id
    LEFT JOIN exams e ON e.id = ea.exam_id
    WHERE 1=1
";

$params = [];

if ($attemptId > 0) {
    $sql .= " AND pl.attempt_id = ?";
    $params[] = $attemptId;
}

if ($eventType !== '') {
    $sql .= " AND pl.event_type = ?";
    $params[] = $eventType;
}

$sql .= " ORDER BY pl.id DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalLogs = (int)$pdo->query("SELECT COUNT(*) FROM proctoring_logs")->fetchColumn();
$totalRisk = (int)$pdo->query("SELECT COALESCE(SUM(risk_level),0) FROM proctoring_logs")->fetchColumn();

$eventTypes = $pdo->query("
    SELECT DISTINCT event_type
    FROM proctoring_logs
    WHERE event_type IS NOT NULL AND event_type <> ''
    ORDER BY event_type
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Security Logs | Admin</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#172033;font-family:Arial,sans-serif}
.wrap{max-width:1350px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}
h1{margin:0 0 7px;font-size:27px}.muted{font-size:13px;color:#718096}
.back{background:#172554;color:white;padding:11px 16px;border-radius:9px;text-decoration:none}
.stats{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin:24px 0}
.stat,.panel{background:white;border:1px solid #e6ebf2;border-radius:14px;padding:20px;box-shadow:0 4px 15px #17255408}
.stat span{font-size:13px;color:#64748b}.stat strong{display:block;font-size:27px;margin-top:8px}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
input,select{padding:11px 13px;border:1px solid #d8e0eb;border-radius:8px;background:white}
button{padding:11px 17px;background:#2563eb;color:white;border:0;border-radius:8px;cursor:pointer}
.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:900px}
th,td{padding:13px 12px;text-align:left;border-bottom:1px solid #edf0f5;font-size:13px;vertical-align:top}
th{background:#f8fafc;color:#475569}tr:hover td{background:#fafcff}
.event{display:inline-block;padding:5px 9px;border-radius:20px;background:#fee2e2;color:#991b1b;font-weight:bold;font-size:11px}
.risk{font-weight:bold;color:#b91c1c}.empty{text-align:center;padding:30px;color:#64748b}
@media(max-width:700px){.wrap{padding:15px}.stats{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>🛡️ Security & Proctoring Logs</h1>
            <div class="muted">Review suspicious events recorded during online examinations</div>
        </div>
        <a class="back" href="<?= BASE_URL ?>/admin/dashboard.php">← Dashboard</a>
    </div>

    <div class="stats">
        <div class="stat"><span>Total Security Events</span><strong><?= $totalLogs ?></strong></div>
        <div class="stat"><span>Total Risk Points</span><strong><?= $totalRisk ?></strong></div>
    </div>

    <div class="panel">
        <form method="GET" class="filters">
            <input type="number" name="attempt_id" min="1" placeholder="Attempt ID"
                   value="<?= $attemptId > 0 ? $attemptId : '' ?>">

            <select name="event_type">
                <option value="">All event types</option>
                <?php foreach ($eventTypes as $type): ?>
                    <option value="<?= logs_h((string)$type) ?>"
                        <?= $eventType === $type ? 'selected' : '' ?>>
                        <?= logs_h((string)$type) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Filter Logs</button>
            <a class="back" href="proctoring_logs.php">Reset</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Attempt</th>
                    <th>Student</th>
                    <th>Exam</th>
                    <th>Event Type</th>
                    <th>Event Details</th>
                    <th>Risk Points</th>
                    <th>IP Address</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$logs): ?>
                    <tr><td colspan="8" class="empty">No security logs found.</td></tr>
                <?php else: foreach ($logs as $log): ?>
                    <tr>
                        <td>#<?= (int)$log['id'] ?></td>
                        <td>
                            <a href="proctoring.php?search=<?= urlencode((string)$log['attempt_id']) ?>">
                                #<?= (int)$log['attempt_id'] ?>
                            </a>
                        </td>
                        <td>
                            <strong><?= logs_h($log['student_name'] ?? 'Unknown') ?></strong><br>
                            <span class="muted"><?= logs_h($log['enrollment_no'] ?? '') ?></span>
                        </td>
                        <td><?= logs_h($log['exam_title'] ?? 'Unknown exam') ?></td>
                        <td><span class="event"><?= logs_h($log['event_type'] ?? 'Unknown') ?></span></td>
                        <td><?= nl2br(logs_h($log['event_message'] ?? '')) ?></td>
                        <td class="risk"><?= $log['risk_level'] ?></td>
                        <td><?= logs_h($log['device_id'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
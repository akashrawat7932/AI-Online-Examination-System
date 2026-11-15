<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_role('admin');

$pdo = db();

function proctor_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "
    SELECT
        ea.id AS attempt_id,
        ea.exam_id,
        ea.student_id,
        ea.start_time,
        ea.submit_time,
        ea.status,
        ea.total_marks,
        ea.obtained_marks,
        ea.tab_switches,
        ea.ip_address,
        ea.device_id,
        e.exam_title,
        s.student_name,
        s.enrollment_no
    FROM exam_attempts ea
    LEFT JOIN exams e ON e.id = ea.exam_id
    LEFT JOIN students s ON s.id = ea.student_id
    WHERE 1=1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (s.student_name LIKE ? OR s.enrollment_no LIKE ? OR e.exam_title LIKE ? OR ea.ip_address LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like);
}

if ($status !== '') {
    $sql .= " AND ea.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY ea.start_time DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAttempts = (int)$pdo->query("SELECT COUNT(*) FROM exam_attempts")->fetchColumn();
$activeAttempts = (int)$pdo->query("SELECT COUNT(*) FROM exam_attempts WHERE status = 'in_progress'")->fetchColumn();
$flaggedAttempts = (int)$pdo->query("SELECT COUNT(*) FROM exam_attempts WHERE tab_switches > 0")->fetchColumn();
$completedAttempts = (int)$pdo->query("SELECT COUNT(*) FROM exam_attempts WHERE submit_time IS NOT NULL")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Proctoring | Admin</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#172033}
.wrap{max-width:1400px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}
h1{margin:0 0 7px;font-size:27px}.muted{color:#718096;font-size:13px}
.back{background:#172554;color:white;padding:11px 16px;border-radius:9px;text-decoration:none}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:25px 0}
.stat,.panel{background:white;border:1px solid #e6ebf2;border-radius:14px;padding:20px;box-shadow:0 4px 15px #17255408}
.stat strong{display:block;font-size:27px;margin-top:10px}.stat span{color:#64748b;font-size:13px}
.panel{overflow:hidden}.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
input,select{padding:11px 13px;border:1px solid #d8e0eb;border-radius:8px;background:white}
button{padding:11px 17px;background:#2563eb;color:white;border:0;border-radius:8px;cursor:pointer}
table{width:100%;border-collapse:collapse;min-width:950px}th,td{padding:13px 12px;text-align:left;border-bottom:1px solid #edf0f5;font-size:13px}
th{background:#f8fafc;color:#475569}tr:hover td{background:#fafcff}
.pill{padding:5px 9px;border-radius:20px;font-size:11px;font-weight:bold;background:#e2e8f0}
.active{background:#dcfce7;color:#166534}.warning{background:#fef3c7;color:#92400e}.done{background:#dbeafe;color:#1e40af}
.table-wrap{overflow-x:auto}.empty{text-align:center;padding:30px;color:#64748b}
@media(max-width:800px){.stats{grid-template-columns:repeat(2,1fr)}.wrap{padding:15px}}
@media(max-width:450px){.stats{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>🛡️ AI Exam Proctoring</h1>
            <div class="muted">Monitor student attempts and suspicious exam activity</div>
        </div>
        <a class="back" href="<?= BASE_URL ?>/admin/dashboard.php">← Dashboard</a>
    </div>

    <div class="stats">
        <div class="stat"><span>Total Attempts</span><strong><?= $totalAttempts ?></strong></div>
        <div class="stat"><span>In Progress</span><strong><?= $activeAttempts ?></strong></div>
        <div class="stat"><span>Attempts with Tab Switches</span><strong><?= $flaggedAttempts ?></strong></div>
        <div class="stat"><span>Submitted Attempts</span><strong><?= $completedAttempts ?></strong></div>
    </div>

    <div class="panel">
        <form class="filters" method="GET">
            <input type="text" name="search" placeholder="Search student, exam, IP..." value="<?= proctor_h($search) ?>">
            <select name="status">
                <option value="">All statuses</option>
                <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>
            <button type="submit">Apply Filters</button>
            <a class="back" href="proctoring.php">Reset</a>
        </form>

        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Attempt</th><th>Student</th><th>Exam</th><th>Status</th>
                <th>Started</th><th>Tab Switches</th><th>IP Address</th><th>Device ID</th><th>Marks</th><th>Logs</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$attempts): ?>
                <tr><td colspan="10" class="empty">No exam attempts found.</td></tr>
            <?php else: foreach ($attempts as $a): ?>
                <tr>
                    <td>#<?= (int)$a['attempt_id'] ?></td>
                    <td>
                        <strong><?= proctor_h($a['student_name'] ?? 'Unknown student') ?></strong><br>
                        <span class="muted"><?= proctor_h($a['enrollment_no'] ?? '') ?></span>
                    </td>
                    <td><?= proctor_h($a['exam_title'] ?? 'Unknown exam') ?></td>
                    <td>
                        <?php $st = strtolower((string)$a['status']); ?>
                        <span class="pill <?= $st === 'in_progress' ? 'active' : (in_array($st, ['submitted','completed'], true) ? 'done' : '') ?>">
                            <?= proctor_h($st ?: 'Unknown') ?>
                        </span>
                    </td>
                    <td><?= proctor_h($a['start_time'] ?? '-') ?></td>
                    <td>
                        <span class="pill <?= (int)$a['tab_switches'] > 0 ? 'warning' : 'active' ?>">
                            <?= (int)$a['tab_switches'] ?>
                        </span>
                    </td>
                    <td><?= proctor_h($a['ip_address'] ?? '-') ?></td>
                    <td><?= proctor_h($a['device_id'] ?? '-') ?></td>
                    <td><?= proctor_h((string)($a['obtained_marks'] ?? 0)) ?> / <?= proctor_h((string)($a['total_marks'] ?? 0)) ?></td>
                    <td><a href="proctoring_logs.php?attempt_id=<?= (int)$a['attempt_id'] ?>">View Logs</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
<?php

require_once __DIR__ . '/../config/config.php';
require_role('admin');

$db = db();

$stmt = $db->prepare("
    SELECT
        u.id AS user_id,
        u.username,
       
        u.status,
        u.created_at,

        t.id AS teacher_id,
        t.teacher_name,
        t.email AS teacher_email,
        t.department

    FROM users u

    LEFT JOIN teachers t
        ON t.user_id = u.id

    WHERE u.role = 'teacher'

    ORDER BY u.id DESC
");

$stmt->execute();

$teachers = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Teacher Management</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}

body {
    background: #f8fafc;
    color: #0f172a;
}

header {
    background: white;
    border-bottom: 1px solid #e2e8f0;
    padding: 18px 30px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.container {
    padding: 30px;
}

.card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}

.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.btn {
    display: inline-block;
    padding: 9px 15px;
    border-radius: 6px;
    text-decoration: none;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: #4f46e5;
    color: white;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-secondary {
    background: #64748b;
    color: white;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

th {
    background: #f1f5f9;
}

.status-active {
    color: #16a34a;
    font-weight: bold;
}

.status-inactive {
    color: #dc2626;
    font-weight: bold;
}

.actions {
    display: flex;
    gap: 6px;
}

@media(max-width: 800px) {

    .card {
        overflow-x: auto;
    }

    table {
        min-width: 800px;
    }

}

</style>

</head>

<body>

<header>

    <h1>Teacher Management</h1>

    <div>

        <a href="dashboard.php"
           class="btn btn-secondary">
            Dashboard
        </a>

        <a href="<?= e(BASE_URL) ?>/logout.php"
           class="btn btn-danger">
            Logout
        </a>

    </div>

</header>


<div class="container">

<div class="card">

<div class="top-bar">

    <h2>Teachers</h2>

    <a href="teacher_add.php"
       class="btn btn-primary">
        + Add Teacher
    </a>

</div>


<?php if (!$teachers): ?>

    <p>No teachers found.</p>

<?php else: ?>

<table>

<thead>

<tr>

    <th>ID</th>
    <th>Username</th>
    <th>Teacher Name</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Department</th>
    <th>Status</th>
    <th>Created</th>
    <th>Action</th>

</tr>

</thead>


<tbody>

<?php foreach ($teachers as $teacher): ?>

<tr>

<td>
<?= e($teacher['user_id']) ?>
</td>

<td>
<?= e($teacher['username']) ?>
</td>

<td>
<?= e($teacher['teacher_name'] ?? '-') ?>
</td>

<td>
<?= e($teacher['teacher_email'] ?? $teacher['user_email'] ?? '-') ?>
</td>

<td>
<?= e($teacher['phone'] ?? '-') ?>
</td>

<td>
<?= e($teacher['department'] ?? '-') ?>
</td>

<td>

<?php if ($teacher['status']): ?>

<span class="status-active">
Active
</span>

<?php else: ?>

<span class="status-inactive">
Inactive
</span>

<?php endif; ?>

</td>

<td>
<?= e($teacher['created_at']) ?>
</td>

<td>

<div class="actions">

<a
href="teacher_edit.php?id=<?= (int)$teacher['user_id'] ?>"
class="btn btn-primary">
Edit
</a>

<a
href="teacher_delete.php?id=<?= (int)$teacher['user_id'] ?>"
class="btn btn-danger"
onclick="return confirm('Delete this teacher?');">
Delete
</a>

</div>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endif; ?>

</div>

</div>

</body>

</html>
<?php

require_once __DIR__ . '/../config/config.php';
require_role('admin');

$db = db();

$stmt = $db->prepare("
    SELECT
        id,
        username,
    
        role,
        status,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$_SESSION['user_id']]);

$user = $stmt->fetch();

if (!$user) {
    exit('Profile not found.');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Profile</title>

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
    max-width: 800px;
    margin: 30px auto;
    padding: 0 20px;
}

.card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

.profile-header {
    text-align: center;
    margin-bottom: 30px;
}

.avatar {
    width: 90px;
    height: 90px;
    border-radius: 50%;

    background: #4f46e5;
    color: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 32px;
    font-weight: bold;

    margin: 0 auto 15px;
}

.profile-header h2 {
    margin-bottom: 5px;
}

.role {
    color: #64748b;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.info-box {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 15px;
}

.label {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 5px;
}

.value {
    font-size: 16px;
    font-weight: 600;
}

.status-active {
    color: #16a34a;
}

.status-inactive {
    color: #dc2626;
}

.buttons {
    margin-top: 30px;
    display: flex;
    gap: 10px;
}

.btn {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 6px;
    text-decoration: none;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: #4f46e5;
    color: white;
}

.btn-secondary {
    background: #64748b;
    color: white;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

@media(max-width: 650px) {

    .info-grid {
        grid-template-columns: 1fr;
    }

    header {
        padding: 15px;
    }

    .container {
        margin-top: 20px;
    }

}

</style>

</head>

<body>


<header>

    <h1>My Profile</h1>

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


<div class="profile-header">

    <div class="avatar">

        <?= e(strtoupper(substr($user['username'], 0, 1))) ?>

    </div>

    <h2>
        <?= e($user['username']) ?>
    </h2>

    <p class="role">
        <?= e(ucfirst($user['role'])) ?>
    </p>

</div>


<div class="info-grid">


<div class="info-box">

    <div class="label">
        Username
    </div>

    <div class="value">
        <?= e($user['username']) ?>
    </div>

</div>


<div class="info-box">

    <div class="label">
        Email
    </div>

    <div class="value">
        <?= e($user['email'] ?? '-') ?>
    </div>

</div>


<div class="info-box">

    <div class="label">
        Phone
    </div>

    <div class="value">
        <?= e($user['phone'] ?? '-') ?>
    </div>

</div>


<div class="info-box">

    <div class="label">
        Role
    </div>

    <div class="value">
        <?= e(ucfirst($user['role'])) ?>
    </div>

</div>


<div class="info-box">

    <div class="label">
        Account Status
    </div>

    <div class="value">

        <?php if ($user['status']): ?>

            <span class="status-active">
                Active
            </span>

        <?php else: ?>

            <span class="status-inactive">
                Inactive
            </span>

        <?php endif; ?>

    </div>

</div>


<div class="info-box">

    <div class="label">
        Account Created
    </div>

    <div class="value">
        <?= e($user['created_at']) ?>
    </div>

</div>


</div>


<div class="buttons">

    <a href="change_password.php"
       class="btn btn-primary">
        Change Password
    </a>

    <a href="dashboard.php"
       class="btn btn-secondary">
        Back to Dashboard
    </a>

</div>


</div>

</div>

</body>

</html>
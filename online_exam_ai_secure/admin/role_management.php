<?php

require_once __DIR__ . '/../config/config.php';

require_role('admin');

$pdo = db();

$message = '';
$message_type = '';

/* =========================
   DELETE ROLE
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_role'])) {

    verify_csrf();

    $role_id = filter_input(
        INPUT_POST,
        'role_id',
        FILTER_VALIDATE_INT
    );

    if (!$role_id) {

        $message = 'Invalid role.';
        $message_type = 'error';

    } else {

        try {

            $check = $pdo->prepare("
                SELECT role_name
                FROM roles
                WHERE id = ?
                LIMIT 1
            ");

            $check->execute([$role_id]);

            $role = $check->fetch(PDO::FETCH_ASSOC);

            if (!$role) {

                $message = 'Role not found.';
                $message_type = 'error';

            } elseif (
                in_array(
                    $role['role_name'],
                    [
                        'super_admin',
                        'admin',
                        'teacher',
                        'student',
                        'proctor',
                        'evaluator',
                        'exam_coordinator'
                    ],
                    true
                )
            ) {

                $message = 'Default system role cannot be deleted.';
                $message_type = 'error';

            } else {

                $delete = $pdo->prepare("
                    DELETE FROM roles
                    WHERE id = ?
                ");

                $delete->execute([$role_id]);

                $message = 'Role deleted successfully.';
                $message_type = 'success';
            }

        } catch (Exception $e) {

            $message = 'Delete failed: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}


/* =========================
   GET ROLES
========================= */

$roles = $pdo->query("
    SELECT
        r.id,
        r.role_name,
        r.description,
        r.is_active,
        r.created_at,
        COUNT(rp.id) AS permission_count
    FROM roles r
    LEFT JOIN role_permissions rp
        ON rp.role_id = r.id
    GROUP BY
        r.id,
        r.role_name,
        r.description,
        r.is_active,
        r.created_at
    ORDER BY r.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Role Management</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    color: #1f2937;
}

.topbar {
    background: #111827;
    color: white;
    padding: 16px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.topbar h2 {
    margin: 0;
}

.top-buttons {
    display: flex;
    gap: 10px;
}

.top-buttons a {
    color: white;
    text-decoration: none;
    background: #374151;
    padding: 9px 15px;
    border-radius: 7px;
}

.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

.header {
    margin-bottom: 25px;
}

.header h1 {
    margin: 0 0 5px;
}

.header p {
    color: #6b7280;
}

.card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    padding: 22px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
}

.actions {
    margin-bottom: 20px;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border: 0;
    border-radius: 7px;
    cursor: pointer;
    text-decoration: none;
    font-weight: 600;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-edit {
    background: #f59e0b;
    color: white;
}

.btn-delete {
    background: #dc2626;
    color: white;
}

.message {
    padding: 14px;
    margin-bottom: 20px;
    border-radius: 8px;
    font-weight: 600;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 14px 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}

th {
    background: #f9fafb;
    font-size: 13px;
}

td {
    font-size: 14px;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #dcfce7;
    color: #166534;
    font-size: 12px;
    font-weight: bold;
}

.actions-cell {
    display: flex;
    gap: 7px;
}

@media(max-width: 800px) {

    .topbar {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }

    .card {
        overflow-x: auto;
    }

    table {
        min-width: 750px;
    }
}

</style>

</head>

<body>


<div class="topbar">

    <h2>AI Exam System - Admin</h2>

    <div class="top-buttons">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="../logout.php">
            Logout
        </a>

    </div>

</div>


<div class="container">


    <div class="header">

        <h1>Role Management</h1>

        <p>
            Manage system roles and their permissions.
        </p>

    </div>


    <?php if ($message !== ''): ?>

        <div class="message <?= e($message_type) ?>">

            <?= e($message) ?>

        </div>

    <?php endif; ?>


    <div class="actions">

        <a
            href="role_add.php"
            class="btn btn-primary"
        >
            + Add New Role
        </a>

    </div>


    <div class="card">

        <table>

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Role</th>

                    <th>Description</th>

                    <th>Permissions</th>

                    <th>Status</th>

                    <th>Created</th>

                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($roles as $role): ?>

                <tr>

                    <td>
                        <?= (int)$role['id'] ?>
                    </td>

                    <td>
                        <strong>
                            <?= e($role['role_name']) ?>
                        </strong>
                    </td>

                    <td>
                        <?= e($role['description'] ?? '') ?>
                    </td>

                    <td>

                        <?= (int)$role['permission_count'] ?>

                        permissions

                    </td>

                    <td>

                        <?php if ($role['is_active']): ?>

                            <span class="badge">
                                Active
                            </span>

                        <?php else: ?>

                            <span>
                                Inactive
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>
                        <?= e($role['created_at']) ?>
                    </td>

                    <td>

                        <div class="actions-cell">

                            <a
                                href="role_edit.php?id=<?= (int)$role['id'] ?>"
                                class="btn btn-edit"
                            >
                                Edit
                            </a>

                            <a
                                href="permission_management.php?role_id=<?= (int)$role['id'] ?>"
                                class="btn btn-primary"
                            >
                                Permissions
                            </a>

                            <form
                                method="post"
                                style="display:inline"
                                onsubmit="return confirm('Delete this role?');"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e(csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="role_id"
                                    value="<?= (int)$role['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    name="delete_role"
                                    class="btn btn-delete"
                                >
                                    Delete
                                </button>

                            </form>

                        </div>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (!$roles): ?>

                <tr>

                    <td colspan="7">
                        No roles found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>

</html>
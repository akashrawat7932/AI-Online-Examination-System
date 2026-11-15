<?php

require_once __DIR__ . '/../config/config.php';

require_role('admin');

$pdo = db();

$message = '';
$message_type = '';

/* =========================
   DELETE USER
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {

        $user_id = filter_input(
            INPUT_POST,
            'user_id',
            FILTER_VALIDATE_INT
        );

        /* Don't allow deleting yourself */
        if ($user_id == ($_SESSION['user_id'] ?? 0)) {

            $message = 'You cannot delete your own account.';
            $message_type = 'error';

        } elseif (!$user_id) {

            $message = 'Invalid user ID.';
            $message_type = 'error';

        } else {

            try {

                $stmt = $pdo->prepare("
                    DELETE FROM users
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([$user_id]);

                $message = 'User deleted successfully.';
                $message_type = 'success';

            } catch (Exception $e) {

                $message = 'Unable to delete user.';
                $message_type = 'error';
            }
        }
    }
}


/* =========================
   GET USERS
========================= */

$stmt = $pdo->query("
    SELECT
        u.id,
        u.username,
        u.role,
        u.role_id,
        u.status,
        u.created_at,
        r.role_name
    FROM users u
    LEFT JOIN roles r
        ON r.id = u.role_id
    ORDER BY u.id DESC
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>User Management</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    background: #f4f7fb;

    font-family: Arial, Helvetica, sans-serif;

    color: #1f2937;
}


/* =========================
   CONTAINER
========================= */

.container {

    max-width: 1250px;

    margin: 35px auto;

    padding: 20px;
}


/* =========================
   CARD
========================= */

.card {

    background: #fff;

    border-radius: 16px;

    padding: 25px;

    box-shadow:
        0 5px 25px rgba(0,0,0,.07);
}


/* =========================
   HEADER
========================= */

.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 25px;

    flex-wrap: wrap;
}

.header h1 {

    margin: 0;

    font-size: 28px;
}

.header p {

    margin: 6px 0 0;

    color: #6b7280;
}


/* =========================
   ADD BUTTON
========================= */

.add-btn {

    display: inline-block;

    background: #2563eb;

    color: #fff;

    padding: 11px 17px;

    border-radius: 8px;

    text-decoration: none;

    font-weight: bold;
}

.add-btn:hover {

    background: #1d4ed8;
}


/* =========================
   MESSAGE
========================= */

.message {

    padding: 13px 16px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-weight: bold;
}

.success {

    background: #dcfce7;

    color: #166534;
}

.error {

    background: #fee2e2;

    color: #991b1b;
}


/* =========================
   TABLE
========================= */

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 900px;
}

thead {

    background: #f8fafc;
}

th {

    text-align: left;

    padding: 14px;

    font-size: 13px;

    color: #475569;

    border-bottom: 1px solid #e5e7eb;
}

td {

    padding: 14px;

    border-bottom: 1px solid #eef2f7;

    font-size: 14px;
}

tbody tr:hover {

    background: #f8fafc;
}


/* =========================
   USERNAME
========================= */

.username {

    font-weight: bold;

    color: #111827;
}


/* =========================
   ROLE
========================= */

.role {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    background: #ede9fe;

    color: #6d28d9;

    font-size: 12px;

    font-weight: bold;
}


/* =========================
   STATUS
========================= */

.status {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}

.active {

    background: #dcfce7;

    color: #166534;
}

.inactive {

    background: #fee2e2;

    color: #991b1b;
}


/* =========================
   ACTIONS
========================= */

.actions {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;
}

.btn {

    display: inline-block;

    padding: 7px 11px;

    border-radius: 7px;

    text-decoration: none;

    border: none;

    cursor: pointer;

    font-size: 12px;

    font-weight: bold;
}

.permissions {

    background: #7c3aed;

    color: #fff;
}

.permissions:hover {

    background: #6d28d9;
}

.edit {

    background: #0ea5e9;

    color: #fff;
}

.delete {

    background: #ef4444;

    color: #fff;
}


/* =========================
   EMPTY
========================= */

.empty {

    text-align: center;

    padding: 40px;

    color: #6b7280;
}


/* =========================
   MOBILE
========================= */

@media (max-width: 700px) {

    .container {

        margin: 10px auto;

        padding: 10px;
    }

    .card {

        padding: 15px;
    }

    .header h1 {

        font-size: 22px;
    }
}

</style>

</head>


<body>


<div class="container">


<div class="card">


<!-- =========================
     HEADER
========================= -->

<div class="header">

    <div>

        <h1>
            User Management
        </h1>

        <p>
            Manage users, roles and individual permissions.
        </p>

    </div>


    <a
        href="user_add.php"
        class="add-btn"
    >
        + Add User
    </a>

</div>


<!-- =========================
     MESSAGE
========================= -->

<?php if ($message): ?>

<div class="message <?= e($message_type) ?>">

    <?= e($message) ?>

</div>

<?php endif; ?>


<!-- =========================
     USERS TABLE
========================= -->

<div class="table-wrapper">

<table>

<thead>

<tr>

    <th>ID</th>

    <th>Username</th>

    <th>Email</th>

    <th>Phone</th>

    <th>Role</th>

    <th>Status</th>

    <th>Created</th>

    <th>Actions</th>

</tr>

</thead>


<tbody>


<?php if (!$users): ?>

<tr>

    <td
        colspan="8"
        class="empty"
    >
        No users found.
    </td>

</tr>

<?php endif; ?>


<?php foreach ($users as $user): ?>


<tr>


    <!-- ID -->

    <td>

        <?= (int)$user['id'] ?>

    </td>


    <!-- USERNAME -->

    <td>

        <span class="username">

            <?= e($user['username']) ?>

        </span>

    </td>


    <!-- EMAIL -->

    <td>

        <?= e($user['email'] ?? '-') ?>

    </td>


    <!-- PHONE -->

    <td>

        <?= e($user['phone'] ?? '-') ?>

    </td>


    <!-- ROLE -->

    <td>

        <span class="role">

            <?= e(
                $user['role_name']
                ?? $user['role']
                ?? 'No Role'
            ) ?>

        </span>

    </td>


    <!-- STATUS -->

    <td>

        <?php if ((int)$user['status'] === 1): ?>

            <span class="status active">
                Active
            </span>

        <?php else: ?>

            <span class="status inactive">
                Inactive
            </span>

        <?php endif; ?>

    </td>


    <!-- CREATED -->

    <td>

        <?= e($user['created_at']) ?>

    </td>


    <!-- ACTIONS -->

    <td>

        <div class="actions">


            <!-- PERMISSIONS -->

            <a
                href="user_permission.php?user_id=<?= (int)$user['id'] ?>"
                class="btn permissions"
            >
                Permissions
            </a>


            <!-- EDIT -->

            <a
                href="user_edit.php?id=<?= (int)$user['id'] ?>"
                class="btn edit"
            >
                Edit
            </a>


            <!-- DELETE -->

            <?php if (
                (int)$user['id']
                !==
                (int)($_SESSION['user_id'] ?? 0)
            ): ?>

            <form
                method="post"
                style="display:inline"
                onsubmit="return confirm(
                    'Are you sure you want to delete this user?'
                );"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="delete"
                >

                <input
                    type="hidden"
                    name="user_id"
                    value="<?= (int)$user['id'] ?>"
                >

                <button
                    type="submit"
                    class="btn delete"
                >
                    Delete
                </button>

            </form>

            <?php endif; ?>


        </div>

    </td>


</tr>


<?php endforeach; ?>


</tbody>

</table>

</div>


</div>

</div>


</body>

</html>
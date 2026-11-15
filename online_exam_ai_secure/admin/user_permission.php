<?php

require_once __DIR__ . '/../config/config.php';

require_role('admin');

$pdo = db();

verify_csrf();

$user_id = isset($_GET['user_id'])
    ? (int) $_GET['user_id']
    : 0;
// \*|--------------------------------------------------------------------------
// | GET ALL ACTIVE USERS
// |--------------------------------------------------------------------------
// */

$stmt = $pdo->query("
    SELECT
        u.id,
        u.username,
        u.email,
        u.phone,
        u.is_active,
        u.role_id,
        r.role_name
    FROM users u
    LEFT JOIN roles r
        ON r.id = u.role_id
    ORDER BY u.username ASC
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| DEFAULT USER
|--------------------------------------------------------------------------
*/

if ($user_id <= 0 && !empty($users)) {
    $user_id = (int) $users[0]['id'];
}


/*
|--------------------------------------------------------------------------
| SELECTED USER
|--------------------------------------------------------------------------
*/

$selected_user = null;

if ($user_id > 0) {

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.username,
            u.email,
            u.phone,
            u.is_active,
            u.role_id,
            r.role_name
        FROM users u
        LEFT JOIN roles r
            ON r.id = u.role_id
        WHERE u.id = ?
        LIMIT 1
    ");

    $stmt->execute([$user_id]);

    $selected_user = $stmt->fetch(PDO::FETCH_ASSOC);

}


/*
|--------------------------------------------------------------------------
| SAVE USER-SPECIFIC PERMISSIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $post_user_id = (int) ($_POST['user_id'] ?? 0);

    $permission_ids = $_POST['permissions'] ?? [];

    if (!is_array($permission_ids)) {
        $permission_ids = [];
    }

    /*
    |--------------------------------------------------------------
    | Validate User
    |--------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            role_id
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$post_user_id]);

    $post_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post_user) {

        $_SESSION['error'] = 'Invalid user selected.';

        header('Location: user_permission.php');
        exit;
    }


    /*
    |--------------------------------------------------------------
    | Clean Permission IDs
    |--------------------------------------------------------------
    */

    $permission_ids = array_map('intval', $permission_ids);

    $permission_ids = array_filter(
        $permission_ids,
        fn($id) => $id > 0
    );

    $permission_ids = array_unique($permission_ids);


    /*
    |--------------------------------------------------------------
    | Get Role Permissions
    |
    | These permissions belong to the user's role.
    | We DO NOT save them inside user_permissions.
    |--------------------------------------------------------------
    */

    $role_permission_ids = [];

    if (!empty($post_user['role_id'])) {

        $stmt = $pdo->prepare("
            SELECT permission_id
            FROM role_permissions
            WHERE role_id = ?
        ");

        $stmt->execute([
            (int) $post_user['role_id']
        ]);

        $role_permission_ids =
            $stmt->fetchAll(PDO::FETCH_COLUMN);

        $role_permission_ids =
            array_map('intval', $role_permission_ids);
    }


    /*
    |--------------------------------------------------------------
    | Only save permissions which are NOT already inherited
    | from the user's role.
    |--------------------------------------------------------------
    */

    $direct_permission_ids = array_diff(
        $permission_ids,
        $role_permission_ids
    );


    /*
    |--------------------------------------------------------------
    | SAVE
    |--------------------------------------------------------------
    */

    try {

        $pdo->beginTransaction();


        /*
        | Delete old direct permissions
        */

        $stmt = $pdo->prepare("
            DELETE FROM user_permissions
            WHERE user_id = ?
        ");

        $stmt->execute([
            $post_user_id
        ]);


        /*
        | Insert new direct permissions
        */

        if (!empty($direct_permission_ids)) {

            $stmt = $pdo->prepare("
                INSERT INTO user_permissions
                (
                    user_id,
                    permission_id
                )
                VALUES
                (
                    ?,
                    ?
                )
            ");

            foreach ($direct_permission_ids as $permission_id) {

                $stmt->execute([
                    $post_user_id,
                    $permission_id
                ]);
            }
        }


        $pdo->commit();


        $_SESSION['success'] =
            'User permissions updated successfully.';

        header(
            'Location: user_permission.php?user_id='
            . $post_user_id
        );

        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['error'] =
            'Permission update failed: '
            . $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| GET ALL PERMISSIONS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        permission_name,
        module,
        description
    FROM permissions
    ORDER BY module ASC, permission_name ASC
");

$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| GROUP PERMISSIONS BY MODULE
|--------------------------------------------------------------------------
*/

$grouped_permissions = [];

foreach ($permissions as $permission) {

    $module = $permission['module'];

    if (!isset($grouped_permissions[$module])) {
        $grouped_permissions[$module] = [];
    }

    $grouped_permissions[$module][] = $permission;
}


/*
|--------------------------------------------------------------------------
| GET DIRECT USER PERMISSIONS
|--------------------------------------------------------------------------
*/

$direct_permissions = [];

if ($user_id > 0) {

    $stmt = $pdo->prepare("
        SELECT permission_id
        FROM user_permissions
        WHERE user_id = ?
    ");

    $stmt->execute([
        $user_id
    ]);

    $direct_permissions =
        $stmt->fetchAll(PDO::FETCH_COLUMN);

    $direct_permissions =
        array_map('intval', $direct_permissions);
}


/*
|--------------------------------------------------------------------------
| GET ROLE PERMISSIONS
|--------------------------------------------------------------------------
*/

$role_permissions = [];

if (
    $selected_user &&
    !empty($selected_user['role_id'])
) {

    $stmt = $pdo->prepare("
        SELECT permission_id
        FROM role_permissions
        WHERE role_id = ?
    ");

    $stmt->execute([
        (int) $selected_user['role_id']
    ]);

    $role_permissions =
        $stmt->fetchAll(PDO::FETCH_COLUMN);

    $role_permissions =
        array_map('intval', $role_permissions);
}


/*
|--------------------------------------------------------------------------
| FLASH MESSAGES
|--------------------------------------------------------------------------
*/

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';

unset($_SESSION['success']);
unset($_SESSION['error']);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>User Permissions</title>


<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 30px;
    font-family:
        Arial,
        Helvetica,
        sans-serif;
    background: #f4f7fb;
    color: #1f2937;
}


/* -------------------------------------------------------
   CONTAINER
------------------------------------------------------- */

.container {
    max-width: 1200px;
    margin: auto;
}


/* -------------------------------------------------------
   HEADER
------------------------------------------------------- */

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0;
    font-size: 28px;
}

.header p {
    margin: 6px 0 0;
    color: #6b7280;
}


/* -------------------------------------------------------
   BUTTON
------------------------------------------------------- */

.btn {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-back {
    background: #374151;
    color: white;
}

.btn-save {
    background: #2563eb;
    color: white;
    font-weight: 600;
}

.btn-save:hover {
    background: #1d4ed8;
}


/* -------------------------------------------------------
   ALERT
------------------------------------------------------- */

.alert {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}


/* -------------------------------------------------------
   CARD
------------------------------------------------------- */

.card {
    background: white;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 22px;
    box-shadow:
        0 4px 15px rgba(0,0,0,0.06);
}


/* -------------------------------------------------------
   USER SELECT
------------------------------------------------------- */

.user-select {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.user-select label {
    font-weight: 600;
}

.user-select select {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: white;
    font-size: 15px;
}


/* -------------------------------------------------------
   USER INFO
------------------------------------------------------- */

.user-info {
    display: grid;
    grid-template-columns:
        repeat(4, 1fr);
    gap: 15px;
    margin-top: 20px;
}

.info-box {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    padding: 15px;
    border-radius: 10px;
}

.info-box small {
    display: block;
    color: #6b7280;
    margin-bottom: 5px;
}

.info-box strong {
    font-size: 15px;
}


/* -------------------------------------------------------
   PERMISSION HEADER
------------------------------------------------------- */

.permission-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.permission-header h2 {
    margin: 0;
    font-size: 20px;
}

.permission-actions {
    display: flex;
    gap: 8px;
}

.small-btn {
    border: none;
    padding: 8px 12px;
    border-radius: 7px;
    cursor: pointer;
    background: #e5e7eb;
    color: #111827;
}

.small-btn:hover {
    background: #d1d5db;
}


/* -------------------------------------------------------
   MODULE
------------------------------------------------------- */

.module {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 18px;
    overflow: hidden;
}

.module-title {
    background: #f8fafc;
    padding: 13px 16px;
    font-size: 16px;
    font-weight: 700;
    text-transform: capitalize;
    border-bottom: 1px solid #e5e7eb;
}

.permission-list {
    display: grid;
    grid-template-columns:
        repeat(2, 1fr);
    gap: 0;
}


/* -------------------------------------------------------
   PERMISSION ITEM
------------------------------------------------------- */

.permission-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.permission-item:nth-child(odd) {
    border-right: 1px solid #f0f0f0;
}

.permission-item input {
    margin-top: 3px;
    width: 17px;
    height: 17px;
    cursor: pointer;
}

.permission-item input:disabled {
    cursor: not-allowed;
}

.permission-content {
    flex: 1;
}

.permission-name {
    font-weight: 600;
    font-size: 14px;
}

.permission-description {
    margin-top: 4px;
    font-size: 12px;
    color: #6b7280;
}

.badge {
    display: inline-block;
    margin-left: 7px;
    padding: 3px 7px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
}

.badge-role {
    background: #dbeafe;
    color: #1d4ed8;
}

.badge-direct {
    background: #dcfce7;
    color: #15803d;
}


/* -------------------------------------------------------
   SAVE FOOTER
------------------------------------------------------- */

.save-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 20px;
}


/* -------------------------------------------------------
   EMPTY
------------------------------------------------------- */

.empty {
    text-align: center;
    padding: 40px;
    color: #6b7280;
}


/* -------------------------------------------------------
   RESPONSIVE
------------------------------------------------------- */

@media (max-width: 900px) {

    .user-info {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .permission-list {
        grid-template-columns: 1fr;
    }

    .permission-item:nth-child(odd) {
        border-right: none;
    }
}


@media (max-width: 600px) {

    body {
        padding: 15px;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .user-info {
        grid-template-columns: 1fr;
    }

    .permission-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

</style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>User Permissions</h1>

            <p>
                Manage role-based and user-specific permissions.
            </p>

        </div>

        <a
            href="user_management.php"
            class="btn btn-back"
        >
            ← Back to Users
        </a>

    </div>


    <!-- SUCCESS -->

    <?php if ($success): ?>

        <div class="alert alert-success">
            <?= e($success) ?>
        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($error): ?>

        <div class="alert alert-error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <?php if (empty($users)): ?>

        <div class="card">

            <div class="empty">
                No users found.
            </div>

        </div>

    <?php else: ?>


        <!-- USER SELECT -->

        <div class="card">

            <div class="user-select">

                <label>
                    Select User
                </label>

                <select
                    onchange="
                        if(this.value){
                            window.location.href =
                            'user_permission.php?user_id='
                            + this.value;
                        }
                    "
                >

                    <?php foreach ($users as $user): ?>

                        <option
                            value="<?= (int)$user['id'] ?>"
                            <?= (
                                $user_id ==
                                $user['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e($user['username']) ?>

                            <?php if (!empty($user['role_name'])): ?>

                                -
                                <?= e($user['role_name']) ?>

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <?php if ($selected_user): ?>

                <div class="user-info">


                    <div class="info-box">

                        <small>
                            Username
                        </small>

                        <strong>
                            <?= e(
                                $selected_user['username']
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-box">

                        <small>
                            Email
                        </small>

                        <strong>
                            <?= e(
                                $selected_user['email']
                                ?: 'Not available'
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-box">

                        <small>
                            Role
                        </small>

                        <strong>
                            <?= e(
                                $selected_user['role_name']
                                ?: 'No Role'
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-box">

                        <small>
                            Status
                        </small>

                        <strong>

                            <?=
                                !empty(
                                    $selected_user['is_active']
                                )
                                ? 'Active'
                                : 'Inactive'
                            ?>

                        </strong>

                    </div>


                </div>

            <?php endif; ?>

        </div>


        <?php if ($selected_user): ?>


            <!-- PERMISSIONS FORM -->

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="user_id"
                    value="<?= (int)$user_id ?>"
                >


                <div class="card">


                    <div class="permission-header">

                        <h2>
                            Permissions
                        </h2>


                        <div class="permission-actions">

                            <button
                                type="button"
                                class="small-btn"
                                onclick="
                                    selectAllPermissions()
                                "
                            >
                                Select All
                            </button>

                            <button
                                type="button"
                                class="small-btn"
                                onclick="
                                    unselectAllPermissions()
                                "
                            >
                                Unselect All
                            </button>

                        </div>

                    </div>


                    <?php if (empty($grouped_permissions)): ?>

                        <div class="empty">
                            No permissions found.
                        </div>

                    <?php else: ?>


                        <?php foreach (
                            $grouped_permissions
                            as $module => $module_permissions
                        ): ?>


                            <div class="module">


                                <div class="module-title">

                                    <?= e($module) ?>

                                </div>


                                <div class="permission-list">


                                    <?php foreach (
                                        $module_permissions
                                        as $permission
                                    ): ?>


                                        <?php

                                        $permission_id =
                                            (int)$permission['id'];

                                        $is_role_permission =
                                            in_array(
                                                $permission_id,
                                                $role_permissions,
                                                true
                                            );

                                        $is_direct_permission =
                                            in_array(
                                                $permission_id,
                                                $direct_permissions,
                                                true
                                            );

                                        $is_checked =
                                            $is_role_permission ||
                                            $is_direct_permission;

                                        ?>


                                        <label
                                            class="permission-item"
                                        >


                                            <input
                                                type="checkbox"

                                                name="permissions[]"

                                                value="<?=
                                                    $permission_id
                                                ?>"

                                                class="permission-checkbox"

                                                <?= $is_checked
                                                    ? 'checked'
                                                    : ''
                                                ?>

                                                <?=
                                                    $is_role_permission
                                                        ? 'disabled'
                                                        : ''
                                                ?>
                                            >


                                            <!--
                                            Disabled role permission
                                            must still be submitted
                                            manually using hidden input.
                                            -->

                                            <?php if (
                                                $is_role_permission
                                            ): ?>

                                                <input
                                                    type="hidden"
                                                    name="permissions[]"
                                                    value="<?=
                                                        $permission_id
                                                    ?>"
                                                >

                                            <?php endif; ?>


                                            <div
                                                class="permission-content"
                                            >

                                                <div
                                                    class="permission-name"
                                                >

                                                    <?= e(
                                                        $permission[
                                                            'permission_name'
                                                        ]
                                                    ) ?>


                                                    <?php if (
                                                        $is_role_permission
                                                    ): ?>

                                                        <span
                                                            class="
                                                                badge
                                                                badge-role
                                                            "
                                                        >
                                                            Role
                                                        </span>

                                                    <?php elseif (
                                                        $is_direct_permission
                                                    ): ?>

                                                        <span
                                                            class="
                                                                badge
                                                                badge-direct
                                                            "
                                                        >
                                                            User
                                                        </span>

                                                    <?php endif; ?>


                                                </div>


                                                <?php if (
                                                    !empty(
                                                        $permission[
                                                            'description'
                                                        ]
                                                    )
                                                ): ?>

                                                    <div
                                                        class="
                                                            permission-description
                                                        "
                                                    >

                                                        <?= e(
                                                            $permission[
                                                                'description'
                                                            ]
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>


                                            </div>


                                        </label>


                                    <?php endforeach; ?>


                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    <div class="save-footer">

                        <button
                            type="submit"
                            class="btn btn-save"
                        >
                            Save Permissions
                        </button>

                    </div>


                </div>


            </form>


        <?php endif; ?>


    <?php endif; ?>


</div>


<script>

/*
|--------------------------------------------------------------------------
| SELECT ALL
|--------------------------------------------------------------------------
*/

function selectAllPermissions()
{
    const checkboxes =
        document.querySelectorAll(
            '.permission-checkbox:not(:disabled)'
        );

    checkboxes.forEach(function (checkbox) {

        checkbox.checked = true;

    });
}


/*
|--------------------------------------------------------------------------
| UNSELECT ALL
|--------------------------------------------------------------------------
*/

function unselectAllPermissions()
{
    const checkboxes =
        document.querySelectorAll(
            '.permission-checkbox:not(:disabled)'
        );

    checkboxes.forEach(function (checkbox) {

        checkbox.checked = false;

    });
}

</script>


</body>

</html>
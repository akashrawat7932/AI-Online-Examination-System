<?php

require_once __DIR__ . '/../config/config.php';

require_role('admin');

$pdo = db();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $role_name = trim(
        (string)($_POST['role_name'] ?? '')
    );

    $description = trim(
        (string)($_POST['description'] ?? '')
    );

    if ($role_name === '') {

        $message = 'Role name is required.';
        $message_type = 'error';

    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $role_name)) {

        $message =
            'Role name can contain only letters, numbers and underscore.';

        $message_type = 'error';

    } else {

        try {

            $check = $pdo->prepare("
                SELECT id
                FROM roles
                WHERE role_name = ?
                LIMIT 1
            ");

            $check->execute([$role_name]);

            if ($check->fetch()) {

                $message = 'Role already exists.';
                $message_type = 'error';

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO roles
                    (
                        role_name,
                        description,
                        is_active
                    )
                    VALUES (?, ?, 1)
                ");

                $stmt->execute([
                    $role_name,
                    $description
                ]);

                header(
                    'Location: role_management.php'
                );

                exit;
            }

        } catch (Exception $e) {

            $message =
                'Unable to create role: ' .
                $e->getMessage();

            $message_type = 'error';
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Add Role</title>

<style>

body {
    margin: 0;
    background: #f4f7fb;
    font-family: Arial, sans-serif;
}

.container {
    max-width: 650px;
    margin: 60px auto;
    padding: 20px;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(0,0,0,.07);
}

h1 {
    margin-top: 0;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

input,
textarea {
    width: 100%;
    padding: 12px;
    margin-bottom: 20px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

button,
a {
    display: inline-block;
    padding: 11px 17px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
}

button {
    background: #2563eb;
    color: white;
}

.back {
    background: #6b7280;
    color: white;
    margin-left: 8px;
}

.message {
    padding: 13px;
    margin-bottom: 20px;
    border-radius: 8px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

</style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>Add New Role</h1>

        <?php if ($message): ?>

            <div class="message <?= e($message_type) ?>">

                <?= e($message) ?>

            </div>

        <?php endif; ?>


        <form method="post">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >


            <label>
                Role Name
            </label>

            <input
                type="text"
                name="role_name"
                placeholder="Example: question_manager"
                required
            >


            <label>
                Description
            </label>

            <textarea
                name="description"
                placeholder="Enter role description"
            ></textarea>


            <button type="submit">
                Create Role
            </button>

            <a
                href="role_management.php"
                class="back"
            >
                Cancel
            </a>

        </form>

    </div>

</div>

</body>

</html>
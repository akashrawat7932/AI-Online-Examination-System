<?php

require_once __DIR__ . '/../config/config.php';
require_role('admin');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();
         
    $enrollment_no = trim($_POST['enrollment_no'] ?? '');
    $student_name  = trim($_POST['student_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $course        = trim($_POST['course'] ?? '');
    $semester      = trim($_POST['semester'] ?? '');

    if ($enrollment_no === '' || $student_name === '') {
        $error = 'Enrollment No and Student Name are required.';
    } else {

        $pdo = db();

        // Username = student name
        $username = $student_name;

        // Default password
        $defaultPassword = $student_name . '@123';

        // Check enrollment number
        $check = $pdo->prepare(
            "SELECT id FROM students WHERE enrollment_no = ? LIMIT 1"
        );
        $check->execute([$enrollment_no]);

        if ($check->fetch()) {

            $error = 'Enrollment number already exists.';

        } else {

            // Check username
            $checkUser = $pdo->prepare(
                "SELECT id FROM users WHERE username = ? LIMIT 1"
            );
            $checkUser->execute([$username]);

            if ($checkUser->fetch()) {

                $error = 'Username already exists. Student name must be unique.';

            } else {

                try {

                    $pdo->beginTransaction();

                    /*
                     * Create student login account
                     */
                    $hashedPassword = password_hash(
                        $defaultPassword,
                        PASSWORD_DEFAULT
                    );

                    $userStmt = $pdo->prepare("
                        INSERT INTO users
                        (
                            username,
                            password_hash,
                            role,
                            status
                        )
                        VALUES (?, ?, 'student', 1)
                    ");

                    $userStmt->execute([
                        $username,
                        $hashedPassword
                        
                    ]);

                    $userId = $pdo->lastInsertId();

                    /*
                     * Create student profile
                     */
                    $studentStmt = $pdo->prepare("
                        INSERT INTO students
                        (
                            user_id,
                            enrollment_no,
                            student_name,
                            email,
                       
                            course,
                            semester
                        )
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");

                    $studentStmt->execute([
                        $userId,
                        $enrollment_no,
                        $student_name,
                        $email ?: null,
                        
                        $course ?: null,
                        $semester ?: null
                    ]);

                    /*
                     * Connect user with student
                     */
                   

                    $pdo->commit();

                    header('Location: student_add.php');
                    exit;

                }catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error = 'Database Error: ' . $e->getMessage();
}
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Add Student</title>

<style>

body {
    font-family: Arial, sans-serif;
    background: #f8fafc;
    margin: 0;
    padding: 30px;
}

.container {
    max-width: 650px;
    margin: auto;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
}

h2 {
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
}

input,
select {
    width: 100%;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    box-sizing: border-box;
}

.btn {
    border: none;
    padding: 11px 18px;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: #4f46e5;
    color: white;
}

.btn-secondary {
    background: #64748b;
    color: white;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.info {
    background: #eef2ff;
    color: #3730a3;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
}

</style>

</head>

<body>

<div class="container">

<h2>Add Student</h2>

<div class="info">
    Login username will be the student's name.<br>
    Default password will be: <strong>StudentName@123</strong>
</div>

<?php if ($error): ?>

<div class="error">
    <?= e($error) ?>
</div>

<?php endif; ?>


<form method="POST">

<input
    type="hidden"
    name="csrf_token"
    value="<?= e(csrf_token()) ?>"
>


<div class="form-group">

<label>Enrollment No *</label>

<input
    type="text"
    name="enrollment_no"
    value="<?= e($_POST['enrollment_no'] ?? '') ?>"
    required
>

</div>


<div class="form-group">

<label>Student Name *</label>

<input
    type="text"
    name="student_name"
    value="<?= e($_POST['student_name'] ?? '') ?>"
    required
>

</div>


<div class="form-group">

<label>Email</label>

<input
    type="email"
    name="email"
    value="<?= e($_POST['email'] ?? '') ?>"
>

</div>


<div class="form-group">

<label>Phone</label>

<input
    type="text"
    name="phone"
    value="<?= e($_POST['phone'] ?? '') ?>"
>

</div>


<div class="form-group">

<label>Course</label>

<input
    type="text"
    name="course"
    value="<?= e($_POST['course'] ?? '') ?>"
>

</div>


<div class="form-group">

<label>Semester</label>

<select name="semester">

<option value="">Select Semester</option>

<?php for ($i = 1; $i <= 8; $i++): ?>

<option
    value="<?= $i ?>"
    <?= (($_POST['semester'] ?? '') == $i) ? 'selected' : '' ?>
>
    Semester <?= $i ?>
</option>

<?php endfor; ?>

</select>

</div>


<button
    type="submit"
    class="btn btn-primary"
>
    Add Student
</button>


<a
    href="student_management.php"
    class="btn btn-secondary"
>
    Cancel
</a>

</form>

</div>

</body>
</html>
<?php

require_once __DIR__ . '/../config/config.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    exit('Invalid student ID.');
}

// Fetch student
$stmt = db()->prepare("
    SELECT
        id,
        enrollment_no,
        student_name,
        gender,
        dob,
        address
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$student = $stmt->fetch();

if (!$student) {
    exit('Student not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $enrollment_no = trim($_POST['enrollment_no'] ?? '');
    $student_name  = trim($_POST['student_name'] ?? '');
    $gender        = trim($_POST['gender'] ?? '');
    $dob           = $_POST['dob'] ?? '';
    $address       = trim($_POST['address'] ?? '');

    if ($enrollment_no === '' || $student_name === '') {

        $error = 'Enrollment No and Student Name are required.';

    } else {

        // Check duplicate enrollment number
        $check = db()->prepare("
            SELECT id
            FROM students
            WHERE enrollment_no = ?
            AND id != ?
            LIMIT 1
        ");

        $check->execute([
            $enrollment_no,
            $id
        ]);

        if ($check->fetch()) {

            $error = 'Enrollment number already exists.';

        } else {

            $update = db()->prepare("
                UPDATE students
                SET
                    enrollment_no = ?,
                    student_name = ?,
                    gender = ?,
                    dob = ?,
                    address = ?
                WHERE id = ?
            ");

            $update->execute([
                $enrollment_no,
                $student_name,
                $gender,
                $dob ?: null,
                $address,
                $id
            ]);

            header('Location: student_management.php');
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<title>Edit Student</title>

<style>

body {
    font-family:Arial,sans-serif;
    background:#f8fafc;
    margin:0;
    padding:30px;
}

.container {
    max-width:650px;
    margin:auto;
    background:white;
    padding:30px;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

h2 {
    margin-bottom:25px;
}

.form-group {
    margin-bottom:18px;
}

label {
    display:block;
    margin-bottom:6px;
    font-weight:600;
}

input,
select,
textarea {
    width:100%;
    padding:11px;
    border:1px solid #d1d5db;
    border-radius:6px;
    box-sizing:border-box;
}

textarea {
    min-height:90px;
}

.btn {
    border:none;
    padding:11px 18px;
    border-radius:6px;
    cursor:pointer;
    text-decoration:none;
    display:inline-block;
}

.btn-primary {
    background:#2563eb;
    color:white;
}

.btn-secondary {
    background:#64748b;
    color:white;
}

.error {
    background:#fee2e2;
    color:#991b1b;
    padding:10px;
    border-radius:6px;
    margin-bottom:15px;
}

</style>

</head>

<body>

<div class="container">

<h2>Edit Student</h2>

<?php if ($error): ?>

<div class="error">
    <?= e($error) ?>
</div>

<?php endif; ?>

<form method="POST">

<input type="hidden"
       name="csrf_token"
       value="<?= e(csrf_token()) ?>">

<div class="form-group">

<label>Enrollment No *</label>

<input type="text"
       name="enrollment_no"
       value="<?= e($_POST['enrollment_no'] ?? $student['enrollment_no']) ?>"
       required>

</div>


<div class="form-group">

<label>Student Name *</label>

<input type="text"
       name="student_name"
       value="<?= e($_POST['student_name'] ?? $student['student_name']) ?>"
       required>

</div>


<div class="form-group">

<label>Gender</label>

<?php
$currentGender = $_POST['gender'] ?? $student['gender'];
?>

<select name="gender">

<option value="">Select Gender</option>

<option value="Male"
    <?= $currentGender === 'Male' ? 'selected' : '' ?>>
    Male
</option>

<option value="Female"
    <?= $currentGender === 'Female' ? 'selected' : '' ?>>
    Female
</option>

<option value="Other"
    <?= $currentGender === 'Other' ? 'selected' : '' ?>>
    Other
</option>

</select>

</div>


<div class="form-group">

<label>Date of Birth</label>

<input type="date"
       name="dob"
       value="<?= e($_POST['dob'] ?? $student['dob']) ?>">

</div>


<div class="form-group">

<label>Address</label>

<textarea name="address"><?= e($_POST['address'] ?? $student['address']) ?></textarea>

</div>


<button type="submit"
        class="btn btn-primary">
    Update Student
</button>

<a href="student_management.php"
   class="btn btn-secondary">
    Cancel
</a>

</form>

</div>

</body>
</html>
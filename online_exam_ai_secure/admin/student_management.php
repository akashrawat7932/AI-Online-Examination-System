<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$db = db();

$stmt = $db->prepare("
    SELECT 
        id,
        enrollment_no,
        student_name,
        course,
        semester,
        email
    FROM students
    ORDER BY id DESC
");
$stmt->execute();

$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>

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

        h1 {
            font-size: 24px;
        }

        h2 {
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

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #f1f5f9;
        }

        tr:hover {
            background: #f8fafc;
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
    <h1>Student Management</h1>

    <div>
        <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        <a href="<?= e(BASE_URL) ?>/logout.php" class="btn btn-danger">
            Logout
        </a>
    </div>
</header>

<div class="container">

    <div class="card">

        <div class="top-bar">
            <h2>Students</h2>

            <a href="student_add.php" class="btn btn-primary">
                + Add Student
            </a>
        </div>

        <?php if (!$students): ?>

            <p>No students found.</p>

        <?php else: ?>

        <table>

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Enrollment No.</th>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Semester</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($students as $student): ?>

                <tr>

                    <td>
                        <?= e($student['id']) ?>
                    </td>

                    <td>
                        <?= e($student['enrollment_no']) ?>
                    </td>

                    <td>
                        <?= e($student['student_name']) ?>
                    </td>

                    <td>
                        <?= e($student['email']) ?>
                    </td>

                    <td>
                        <?= e($student['course']) ?>
                    </td>

                    <td>
                        <?= e($student['semester']) ?>
                    </td>

                    <td>

                        <div class="actions">

                            <a
                                href="student_edit.php?id=<?= e($student['id']) ?>"
                                class="btn btn-primary">
                                Edit
                            </a>

                            <a
                                href="student_delete.php?id=<?= e($student['id']) ?>"
                                class="btn btn-danger"
                                onclick="return confirm('Are you sure you want to delete this student?');">
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
<?php 
require_once __DIR__ . '/../config/config.php';
// Teacher access check
require_role('teacher'); 

// Fetch ALL students results with student name & exam title
$s = db()->prepare('
    SELECT 
        r.id,
        r.attempt_id,
        st.student_name,
        st.enrollment_no,
        e.exam_title,
        r.obtained_marks,
        r.total_marks,
        r.percentage,
        r.grade,
        r.result_status
    FROM results r
    JOIN students st ON st.id = r.student_id
    JOIN exams e ON e.id = r.exam_id
    ORDER BY r.id DESC
');
$s->execute();
$all_results = $s->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Panel - All Student Results</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f8fafc;
            color: #0f172a;
            padding: 2rem;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }

        h1 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #1e293b;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-pass { background-color: #dcfce7; color: #16a34a; }
        .badge-fail { background-color: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>

<div class="container">
    <h1>All Student Exam Results</h1>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Roll No</th>
                    <th>Student Name</th>
                    <th>Exam Title</th>
                    <th>Attempt</th>
                    <th>Score</th>
                    <th>Percentage</th>
                    <th>Grade</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_results)): ?>
                    <?php foreach ($all_results as $row): ?>
                        <?php 
                            $status = strtolower($row['result_status'] ?? 'pass');
                            $is_pass = ($status === 'pass' || (float)$row['percentage'] >= 40);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['enrollment_no'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= htmlspecialchars($row['student_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars($row['exam_title'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>#<?= htmlspecialchars($row['attempt_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?= htmlspecialchars($row['obtained_marks'], ENT_QUOTES, 'UTF-8') ?> / 
                                <?= htmlspecialchars($row['total_marks'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td><?= htmlspecialchars(number_format((float)$row['percentage'], 2), ENT_QUOTES, 'UTF-8') ?>%</td>
                            <td><?= htmlspecialchars($row['grade'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge <?= $is_pass ? 'badge-pass' : 'badge-fail' ?>">
                                    <?= htmlspecialchars(strtoupper($row['result_status']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2rem; color: #64748b;">
                            No student results found in the database.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

require_role('admin');

$pdo = db();

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$exam_id = isset($_GET['exam_id'])
    ? (int) $_GET['exam_id']
    : 0;

$subject_id = isset($_GET['subject_id'])
    ? (int) $_GET['subject_id']
    : 0;

$date_from = trim($_GET['date_from'] ?? '');

$date_to = trim($_GET['date_to'] ?? '');
/*
|--------------------------------------------------------------------------
| GET EXAMS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        e.id,
        e.exam_title,
        e.subject_id,
        s.subject_name
    FROM exams e
    LEFT JOIN subjects s
        ON s.id = e.subject_id
    ORDER BY e.start_datetime DESC
");

$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| GET SUBJECTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        subject_code,
        subject_name
    FROM subjects
    ORDER BY subject_name ASC
");

$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| BASIC SYSTEM COUNTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM students
");

$total_students = (int) $stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM teachers
");

$total_teachers = (int) $stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM subjects
    WHERE status = 'active'
");

$total_subjects = (int) $stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM exams
");

$total_exams = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| DETECT ATTEMPT TABLE
|--------------------------------------------------------------------------
|
| This project commonly uses exam_attempts.
|
*/

$attempt_table_exists = false;

try {

    $stmt = $pdo->query("
        SHOW TABLES LIKE 'exam_attempts'
    ");

    $attempt_table_exists =
        (bool) $stmt->fetchColumn();

} catch (Throwable $e) {

    $attempt_table_exists = false;
}


/*
|--------------------------------------------------------------------------
| REPORT VARIABLES
|--------------------------------------------------------------------------
*/

$total_attempts = 0;

$completed_attempts = 0;

$passed_attempts = 0;

$failed_attempts = 0;

$average_marks = 0;

$average_percentage = 0;

$pass_percentage = 0;

$recent_results = [];


/*
|--------------------------------------------------------------------------
| ATTEMPT REPORT
|--------------------------------------------------------------------------
*/

if ($attempt_table_exists) {

    /*
    |--------------------------------------------------------------------------
    | Build WHERE
    |--------------------------------------------------------------------------
    */

    $where = [];

    $params = [];


    if ($exam_id > 0) {

        $where[] = "ea.exam_id = ?";

        $params[] = $exam_id;
    }


    if ($subject_id > 0) {

        $where[] = "e.subject_id = ?";

        $params[] = $subject_id;
    }


    if ($date_from !== '') {

        $where[] =
            "DATE(ea.created_at) >= ?";

        $params[] = $date_from;
    }


    if ($date_to !== '') {

        $where[] =
            "DATE(ea.created_at) <= ?";

        $params[] = $date_to;
    }


    $where_sql = '';

    if (!empty($where)) {

        $where_sql =
            'WHERE ' .
            implode(' AND ', $where);
    }


    /*
    |--------------------------------------------------------------------------
    | TOTAL ATTEMPTS
    |--------------------------------------------------------------------------
    */

    try {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM exam_attempts ea
            INNER JOIN exams e
                ON e.id = ea.exam_id
            $where_sql
        ");

        $stmt->execute($params);

        $total_attempts =
            (int) $stmt->fetchColumn();

    } catch (Throwable $e) {

        $total_attempts = 0;
    }


    /*
    |--------------------------------------------------------------------------
    | COMPLETED ATTEMPTS
    |--------------------------------------------------------------------------
    */

    try {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM exam_attempts ea
            INNER JOIN exams e
                ON e.id = ea.exam_id

            $where_sql

            AND ea.status = 'submitted'
        ");

        /*
        | If WHERE does not exist, fix AND.
        */

        if ($where_sql === '') {

            $sql = "
                SELECT COUNT(*)
                FROM exam_attempts ea
                INNER JOIN exams e
                    ON e.id = ea.exam_id
                WHERE ea.status = 'submitted'
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute($params);

        } else {

            $stmt->execute($params);
        }

        $completed_attempts =
            (int) $stmt->fetchColumn();

    } catch (Throwable $e) {

        $completed_attempts = 0;
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK RESULT COLUMNS
    |--------------------------------------------------------------------------
    |
    | We use marks_obtained and total_marks if available.
    |
    */

    $attempt_columns = [];

    try {

        $stmt = $pdo->query("
            SHOW COLUMNS FROM exam_attempts
        ");

        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $attempt_columns = array_flip($columns);

    } catch (Throwable $e) {

        $attempt_columns = [];
    }


    $has_marks_obtained =
        isset($attempt_columns['marks_obtained']);

    $has_total_marks =
        isset($attempt_columns['total_marks']);

    $has_percentage =
        isset($attempt_columns['percentage']);


    /*
    |--------------------------------------------------------------------------
    | AVERAGE MARKS
    |--------------------------------------------------------------------------
    */

    if ($has_marks_obtained) {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(
                        AVG(ea.marks_obtained),
                        0
                    )
                FROM exam_attempts ea

                INNER JOIN exams e
                    ON e.id = ea.exam_id

                $where_sql

                " . (
                    $where_sql === ''
                    ? "WHERE ea.status = 'submitted'"
                    : "AND ea.status = 'submitted'"
                ) . "
            ");

            $stmt->execute($params);

            $average_marks =
                (float) $stmt->fetchColumn();

        } catch (Throwable $e) {

            $average_marks = 0;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | AVERAGE PERCENTAGE
    |--------------------------------------------------------------------------
    */

    if ($has_percentage) {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(
                        AVG(ea.percentage),
                        0
                    )
                FROM exam_attempts ea

                INNER JOIN exams e
                    ON e.id = ea.exam_id

                $where_sql

                " . (
                    $where_sql === ''
                    ? "WHERE ea.status = 'submitted'"
                    : "AND ea.status = 'submitted'"
                ) . "
            ");

            $stmt->execute($params);

            $average_percentage =
                (float) $stmt->fetchColumn();

        } catch (Throwable $e) {

            $average_percentage = 0;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | PASS / FAIL
    |--------------------------------------------------------------------------
    */

    if ($has_percentage) {

        try {

            $pass_where = $where;

            $pass_params = $params;

            $pass_where[] =
                "ea.status = 'submitted'";

            $pass_where[] =
                "ea.percentage >= 40";

            $pass_sql =
                'WHERE ' .
                implode(
                    ' AND ',
                    $pass_where
                );

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM exam_attempts ea

                INNER JOIN exams e
                    ON e.id = ea.exam_id

                $pass_sql
            ");

            $stmt->execute($pass_params);

            $passed_attempts =
                (int) $stmt->fetchColumn();


            $fail_where = $where;

            $fail_params = $params;

            $fail_where[] =
                "ea.status = 'submitted'";

            $fail_where[] =
                "ea.percentage < 40";

            $fail_sql =
                'WHERE ' .
                implode(
                    ' AND ',
                    $fail_where
                );

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM exam_attempts ea

                INNER JOIN exams e
                    ON e.id = ea.exam_id

                $fail_sql
            ");

            $stmt->execute($fail_params);

            $failed_attempts =
                (int) $stmt->fetchColumn();

        } catch (Throwable $e) {

            $passed_attempts = 0;

            $failed_attempts = 0;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | PASS PERCENTAGE
    |--------------------------------------------------------------------------
    */

    if ($completed_attempts > 0) {

        $pass_percentage =
            (
                $passed_attempts /
                $completed_attempts
            ) * 100;
    }


    /*
    |--------------------------------------------------------------------------
    | RECENT RESULTS
    |--------------------------------------------------------------------------
    */

    try {

        $student_name_sql =
            "NULL";

        if (
            isset(
                $attempt_columns['student_id']
            )
        ) {

            $student_name_sql =
                "s.student_name";
        }


        $select_marks =
            $has_marks_obtained
                ? "ea.marks_obtained"
                : "NULL AS marks_obtained";


        $select_total =
            $has_total_marks
                ? "ea.total_marks"
                : "NULL AS total_marks";


        $select_percentage =
            $has_percentage
                ? "ea.percentage"
                : "NULL AS percentage";


        if (
            isset(
                $attempt_columns['student_id']
            )
        ) {

            $sql = "
                SELECT

                    ea.id,

                    $student_name_sql
                    AS student_name,

                    e.exam_title,

                    sub.subject_name,

                    $select_marks,

                    $select_total,

                    $select_percentage,

                    ea.status,

                    ea.created_at

                FROM exam_attempts ea

                INNER JOIN exams e
                    ON e.id = ea.exam_id

                LEFT JOIN subjects sub
                    ON sub.id = e.subject_id

                LEFT JOIN students s
                    ON s.id = ea.student_id

                $where_sql

                ORDER BY ea.created_at DESC

                LIMIT 20
            ";

        } else {

            $sql = "
                SELECT

                    ea.id,

                    'Student' AS student_name,

                    e.exam_title,

                    sub.subject_name,

                    $select_marks,

                    $select_total,

                    $select_percentage,

                    ea.status,

                    ea.created_at

                FROM exam_attempts ea

                INNER JOIN exams e
                    ON e.id = ea.exam_id

                LEFT JOIN subjects sub
                    ON sub.id = e.subject_id

                $where_sql

                ORDER BY ea.created_at DESC

                LIMIT 20
            ";
        }


        $stmt = $pdo->prepare($sql);

        $stmt->execute($params);

        $recent_results =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        $recent_results = [];
    }
}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function report_number(float|int $number): string
{
    return number_format(
        (float) $number,
        2
    );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Reports | <?= e(APP_NAME) ?>
</title>


<style>

/*
|--------------------------------------------------------------------------
| RESET
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}

body {
    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f7fb;

    color: #1f2937;
}


/*
|--------------------------------------------------------------------------
| LAYOUT
|--------------------------------------------------------------------------
*/

.container {
    width: 100%;

    max-width: 1400px;

    margin: auto;

    padding: 30px;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 25px;
}

.header h1 {
    margin: 0;

    font-size: 30px;

    color: #111827;
}

.header p {
    margin: 7px 0 0;

    color: #6b7280;
}


/*
|--------------------------------------------------------------------------
| BUTTON
|--------------------------------------------------------------------------
*/

.btn {
    display: inline-block;

    border: none;

    text-decoration: none;

    padding: 10px 16px;

    border-radius: 8px;

    cursor: pointer;

    font-size: 14px;
}

.btn-primary {
    background: #2563eb;

    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-secondary {
    background: #374151;

    color: white;
}

.btn-light {
    background: #e5e7eb;

    color: #111827;
}


/*
|--------------------------------------------------------------------------
| FILTER CARD
|--------------------------------------------------------------------------
*/

.card {
    background: white;

    border-radius: 14px;

    padding: 22px;

    margin-bottom: 25px;

    box-shadow:
        0 4px 16px
        rgba(0,0,0,.06);
}

.filter-grid {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    align-items: end;
}

.form-group label {
    display: block;

    margin-bottom: 7px;

    font-size: 13px;

    font-weight: 600;

    color: #374151;
}

.form-group select,
.form-group input {
    width: 100%;

    padding: 11px 12px;

    border: 1px solid #d1d5db;

    border-radius: 8px;

    background: white;

    font-size: 14px;

    outline: none;
}

.form-group select:focus,
.form-group input:focus {
    border-color: #2563eb;
}


/*
|--------------------------------------------------------------------------
| STAT GRID
|--------------------------------------------------------------------------
*/

.stat-grid {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}

.stat-card {
    background: white;

    border-radius: 14px;

    padding: 22px;

    box-shadow:
        0 4px 16px
        rgba(0,0,0,.06);
}

.stat-label {
    color: #6b7280;

    font-size: 13px;

    margin-bottom: 9px;
}

.stat-value {
    font-size: 29px;

    font-weight: 700;

    color: #111827;
}

.stat-info {
    margin-top: 7px;

    font-size: 12px;

    color: #6b7280;
}


/*
|--------------------------------------------------------------------------
| REPORT GRID
|--------------------------------------------------------------------------
*/

.report-grid {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}

.report-box {
    background: white;

    border-radius: 14px;

    padding: 22px;

    box-shadow:
        0 4px 16px
        rgba(0,0,0,.06);
}

.report-box h3 {
    margin: 0 0 20px;

    font-size: 17px;
}

.big-number {
    font-size: 35px;

    font-weight: 700;

    margin-bottom: 5px;
}

.success-number {
    color: #16a34a;
}

.danger-number {
    color: #dc2626;
}

.blue-number {
    color: #2563eb;
}


/*
|--------------------------------------------------------------------------
| PROGRESS
|--------------------------------------------------------------------------
*/

.progress {
    width: 100%;

    height: 10px;

    background: #e5e7eb;

    border-radius: 20px;

    overflow: hidden;

    margin-top: 12px;
}

.progress-bar {
    height: 100%;

    background: #2563eb;

    border-radius: 20px;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-card {
    background: white;

    border-radius: 14px;

    padding: 22px;

    box-shadow:
        0 4px 16px
        rgba(0,0,0,.06);

    overflow-x: auto;
}

.table-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 18px;
}

.table-header h2 {
    margin: 0;

    font-size: 19px;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 900px;
}

th {
    text-align: left;

    padding: 13px;

    background: #f8fafc;

    color: #475569;

    font-size: 12px;

    text-transform: uppercase;
}

td {
    padding: 14px 13px;

    border-bottom: 1px solid #eef2f7;

    font-size: 13px;
}

tr:hover td {
    background: #fafcff;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.status {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 700;
}

.status-submitted {
    background: #dcfce7;

    color: #166534;
}

.status-other {
    background: #fef3c7;

    color: #92400e;
}

.pass {
    color: #16a34a;

    font-weight: 700;
}

.fail {
    color: #dc2626;

    font-weight: 700;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {
    text-align: center;

    padding: 45px 20px;

    color: #6b7280;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1000px) {

    .filter-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .stat-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .report-grid {
        grid-template-columns:
            1fr;
    }
}

@media (max-width: 600px) {

    .container {
        padding: 15px;
    }

    .header {
        flex-direction: column;

        align-items: flex-start;
    }

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .stat-grid {
        grid-template-columns: 1fr;
    }
}

</style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>
                Examination Reports
            </h1>

            <p>
                Monitor examination performance,
                attempts and results.
            </p>

        </div>


        <a
            href="dashboard.php"
            class="btn btn-secondary"
        >
            ← Dashboard
        </a>

    </div>


    <!-- FILTER -->

    <div class="card">

        <form method="GET">

            <div class="filter-grid">


                <div class="form-group">

                    <label>
                        Exam
                    </label>

                    <select name="exam_id">

                        <option value="0">
                            All Exams
                        </option>

                        <?php foreach ($exams as $exam): ?>

                            <option
                                value="<?= (int)$exam['id'] ?>"
                                <?= $exam_id ===
                                    (int)$exam['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $exam['exam_title']
                                ) ?>

                                <?php if (
                                    !empty(
                                        $exam['subject_name']
                                    )
                                ): ?>

                                    -
                                    <?= e(
                                        $exam['subject_name']
                                    ) ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Subject
                    </label>

                    <select name="subject_id">

                        <option value="0">
                            All Subjects
                        </option>

                        <?php foreach (
                            $subjects
                            as $subject
                        ): ?>

                            <option
                                value="<?=
                                    (int)$subject['id']
                                ?>"
                                <?= $subject_id ===
                                    (int)$subject['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $subject['subject_name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        From Date
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        value="<?= e($date_from) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        To Date
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        value="<?= e($date_to) ?>"
                    >

                </div>


                <div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Apply Filters
                    </button>

                    <a
                        href="reports.php"
                        class="btn btn-light"
                    >
                        Reset
                    </a>

                </div>


            </div>

        </form>

    </div>


    <!-- SYSTEM STATISTICS -->

    <div class="stat-grid">


        <div class="stat-card">

            <div class="stat-label">
                Total Students
            </div>

            <div class="stat-value">
                <?= $total_students ?>
            </div>

            <div class="stat-info">
                Registered students
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Total Teachers
            </div>

            <div class="stat-value">
                <?= $total_teachers ?>
            </div>

            <div class="stat-info">
                Registered teachers
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Active Subjects
            </div>

            <div class="stat-value">
                <?= $total_subjects ?>
            </div>

            <div class="stat-info">
                Available subjects
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Total Exams
            </div>

            <div class="stat-value">
                <?= $total_exams ?>
            </div>

            <div class="stat-info">
                Created examinations
            </div>

        </div>


    </div>


    <!-- PERFORMANCE REPORT -->

    <div class="report-grid">


        <div class="report-box">

            <h3>
                Total Attempts
            </h3>

            <div class="big-number blue-number">
                <?= $total_attempts ?>
            </div>

            <p>
                Students who started an examination.
            </p>

        </div>


        <div class="report-box">

            <h3>
                Completed Attempts
            </h3>

            <div class="big-number">
                <?= $completed_attempts ?>
            </div>

            <p>
                Successfully submitted examinations.
            </p>

        </div>


        <div class="report-box">

            <h3>
                Average Marks
            </h3>

            <div class="big-number">
                <?= report_number($average_marks) ?>
            </div>

            <p>
                Average marks of submitted attempts.
            </p>

        </div>


        <div class="report-box">

            <h3>
                Passed
            </h3>

            <div class="big-number success-number">
                <?= $passed_attempts ?>
            </div>

            <p>
                Percentage ≥ 40%.
            </p>

        </div>


        <div class="report-box">

            <h3>
                Failed
            </h3>

            <div class="big-number danger-number">
                <?= $failed_attempts ?>
            </div>

            <p>
                Percentage below 40%.
            </p>

        </div>


        <div class="report-box">

            <h3>
                Pass Percentage
            </h3>

            <div class="big-number blue-number">

                <?= report_number(
                    $pass_percentage
                ) ?>%

            </div>

            <div class="progress">

                <div
                    class="progress-bar"
                    style="
                        width:
                        <?= min(
                            100,
                            max(
                                0,
                                $pass_percentage
                            )
                        ) ?>%;
                    "
                ></div>

            </div>

        </div>


    </div>


    <!-- RESULTS TABLE -->

    <div class="table-card">


        <div class="table-header">

            <h2>
                Recent Examination Results
            </h2>

        </div>


        <?php if (empty($recent_results)): ?>

            <div class="empty">

                No examination results found.

            </div>

        <?php else: ?>


            <table>

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Student
                        </th>

                        <th>
                            Exam
                        </th>

                        <th>
                            Subject
                        </th>

                        <th>
                            Marks
                        </th>

                        <th>
                            Percentage
                        </th>

                        <th>
                            Result
                        </th>

                        <th>
                            Date
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach (
                    $recent_results
                    as $index => $result
                ): ?>


                    <?php

                    $percentage =
                        $result['percentage'] !== null
                            ? (float)$result['percentage']
                            : null;

                    ?>


                    <tr>

                        <td>
                            <?= $index + 1 ?>
                        </td>


                        <td>
                            <?= e(
                                $result['student_name']
                            ) ?>
                        </td>


                        <td>
                            <?= e(
                                $result['exam_title']
                            ) ?>
                        </td>


                        <td>
                            <?= e(
                                $result['subject_name']
                                ?? '-'
                            ) ?>
                        </td>


                        <td>

                            <?php if (
                                $result['marks_obtained']
                                !== null
                            ): ?>

                                <?= e(
                                    (string)
                                    $result[
                                        'marks_obtained'
                                    ]
                                ) ?>

                                <?php if (
                                    $result['total_marks']
                                    !== null
                                ): ?>

                                    /
                                    <?= e(
                                        (string)
                                        $result[
                                            'total_marks'
                                        ]
                                    ) ?>

                                <?php endif; ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                $percentage !== null
                            ): ?>

                                <?= report_number(
                                    $percentage
                                ) ?>%

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                $percentage !== null &&
                                $percentage >= 40
                            ): ?>

                                <span class="pass">
                                    PASS
                                </span>

                            <?php elseif (
                                $percentage !== null
                            ): ?>

                                <span class="fail">
                                    FAIL
                                </span>

                            <?php else: ?>

                                <span
                                    class="
                                        status
                                        status-other
                                    "
                                >
                                    <?= e(
                                        $result['status']
                                    ) ?>
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= e(
                                date(
                                    'd M Y H:i',
                                    strtotime(
                                        $result['created_at']
                                    )
                                )
                            ) ?>

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
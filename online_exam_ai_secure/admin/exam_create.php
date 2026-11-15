<?php

require_once __DIR__ . '/../config/config.php';
require_role('teacher');

$t = db()->prepare(
    'SELECT id FROM teachers WHERE user_id=?'
);

$t->execute([
    $_SESSION['user_id']
]);

$tid = (int)$t->fetchColumn();

if (!$tid) {
    exit('Teacher profile missing.');
}


$subs = db()->query("
    SELECT
        id,
        subject_code,
        subject_name
    FROM subjects
    WHERE status='active'
    ORDER BY subject_name
")->fetchAll();


$msg = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $sid = filter_input(
        INPUT_POST,
        'subject_id',
        FILTER_VALIDATE_INT
    );

    $title = trim(
        (string)($_POST['exam_title'] ?? '')
    );

    $dur = filter_input(
        INPUT_POST,
        'duration_minutes',
        FILTER_VALIDATE_INT
    );


    try {

        $a = new DateTimeImmutable(
            (string)($_POST['start_datetime'] ?? '')
        );

        $b = new DateTimeImmutable(
            (string)($_POST['end_datetime'] ?? '')
        );

    } catch (Exception $e) {

        $a = null;
        $b = null;
    }


    if (
        !$sid ||
        $title === '' ||
        strlen($title) > 200 ||
        !$dur ||
        $dur < 1 ||
        $dur > 1440 ||
        !$a ||
        !$b ||
        $b <= $a
    ) {

        $msg = 'Invalid exam data.';

    } else {

        $i = db()->prepare("
            INSERT INTO exams
            (
                subject_id,
                teacher_id,
                exam_title,
                duration_minutes,
                start_datetime,
                end_datetime,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, 'scheduled')
        ");

        $i->execute([
            $sid,
            $tid,
            $title,
            $dur,
            $a->format('Y-m-d H:i:s'),
            $b->format('Y-m-d H:i:s')
        ]);

        $msg = 'Exam created successfully.';
    }
}

?>

<!doctype html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Create Exam</title>


<style>

/* =========================
   RESET
========================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


/* =========================
   BODY
========================= */

body {

    font-family:
        Inter,
        Arial,
        Helvetica,
        sans-serif;

    background: #f8fafc;

    color: #0f172a;

    min-height: 100vh;
}


/* =========================
   HEADER
========================= */

.header {

    background: #ffffff;

    border-bottom:
        1px solid #e2e8f0;

    padding: 18px 35px;

    display: flex;

    justify-content: space-between;

    align-items: center;
}


.header-title {

    font-size: 22px;

    font-weight: 700;
}


.header-actions {

    display: flex;

    gap: 10px;
}


/* =========================
   BUTTON
========================= */

.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 10px 16px;

    border-radius: 7px;

    border: none;

    text-decoration: none;

    cursor: pointer;

    font-size: 14px;

    font-weight: 600;

    transition: .2s;
}


.btn-primary {

    background: #4f46e5;

    color: white;
}


.btn-primary:hover {

    background: #4338ca;
}


.btn-secondary {

    background: #64748b;

    color: white;
}


.btn-secondary:hover {

    background: #475569;
}


.btn-danger {

    background: #ef4444;

    color: white;
}


.btn-danger:hover {

    background: #dc2626;
}


/* =========================
   MAIN
========================= */

.main {

    max-width: 850px;

    margin: 40px auto;

    padding: 0 20px;
}


/* =========================
   CARD
========================= */

.card {

    background: #ffffff;

    border:
        1px solid #e2e8f0;

    border-radius: 14px;

    padding: 30px;

    box-shadow:
        0 4px 15px rgba(15, 23, 42, .06);
}


.card-header {

    margin-bottom: 25px;

    padding-bottom: 20px;

    border-bottom:
        1px solid #e2e8f0;
}


.card-header h1 {

    font-size: 25px;

    margin-bottom: 7px;
}


.card-header p {

    color: #64748b;

    font-size: 14px;
}


/* =========================
   MESSAGE
========================= */

.message {

    padding: 13px 15px;

    border-radius: 8px;

    margin-bottom: 22px;

    background: #eef2ff;

    border:
        1px solid #c7d2fe;

    color: #3730a3;

    font-size: 14px;

    font-weight: 600;
}


/* =========================
   FORM
========================= */

.form-group {

    margin-bottom: 20px;
}


.form-row {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 20px;
}


label {

    display: block;

    margin-bottom: 7px;

    color: #334155;

    font-size: 14px;

    font-weight: 600;
}


input,
select {

    width: 100%;

    padding: 12px 13px;

    border:
        1px solid #cbd5e1;

    border-radius: 7px;

    background: white;

    color: #0f172a;

    font-size: 14px;

    outline: none;

    transition: .2s;
}


input:focus,
select:focus {

    border-color: #4f46e5;

    box-shadow:
        0 0 0 3px
        rgba(79, 70, 229, .10);
}


select {

    cursor: pointer;
}


/* =========================
   INPUT ICON STYLE
========================= */

input[type="datetime-local"] {

    cursor: pointer;
}


input[type="number"] {

    appearance: textfield;
}


/* =========================
   HELP TEXT
========================= */

.help {

    margin-top: 6px;

    color: #64748b;

    font-size: 12px;
}


/* =========================
   EXAM INFO
========================= */

.exam-info {

    background: #f8fafc;

    border:
        1px solid #e2e8f0;

    border-radius: 10px;

    padding: 20px;

    margin-top: 10px;

    margin-bottom: 25px;
}


.exam-info-title {

    font-size: 15px;

    font-weight: 700;

    margin-bottom: 15px;

    color: #334155;
}


.info-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;
}


.info-box {

    background: white;

    border:
        1px solid #e2e8f0;

    padding: 13px;

    border-radius: 7px;
}


.info-label {

    display: block;

    font-size: 12px;

    color: #64748b;

    margin-bottom: 4px;
}


.info-value {

    font-weight: 600;

    font-size: 14px;
}


/* =========================
   FORM ACTIONS
========================= */

.form-actions {

    margin-top: 30px;

    padding-top: 20px;

    border-top:
        1px solid #e2e8f0;

    display: flex;

    justify-content: flex-end;

    gap: 10px;
}


.create-btn {

    min-width: 140px;
}


/* =========================
   REQUIRED
========================= */

.required {

    color: #dc2626;
}


/* =========================
   RESPONSIVE
========================= */

@media(max-width: 700px) {

    .header {

        padding: 15px 20px;

        flex-direction: column;

        align-items: flex-start;

        gap: 12px;
    }


    .header-actions {

        width: 100%;
    }


    .main {

        margin: 20px auto;

        padding: 0 12px;
    }


    .card {

        padding: 20px;
    }


    .form-row {

        grid-template-columns: 1fr;

        gap: 0;
    }


    .info-grid {

        grid-template-columns: 1fr;
    }


    .form-actions {

        flex-direction: column;
    }


    .form-actions .btn {

        width: 100%;
    }

}

</style>

</head>


<body>


<!-- =========================
     HEADER
========================= -->

<header class="header">

    <div class="header-title">
        Teacher Panel
    </div>


    <div class="header-actions">

        <a
            href="dashboard.php"
            class="btn btn-secondary"
        >
            Dashboard
        </a>


        <a
            href="<?= e(BASE_URL) ?>/logout.php"
            class="btn btn-danger"
        >
            Logout
        </a>

    </div>

</header>



<!-- =========================
     MAIN
========================= -->

<main class="main">

<div class="card">


<div class="card-header">

    <h1>Create Exam</h1>

    <p>
        Schedule an online examination for your students.
    </p>

</div>



<?php if ($msg): ?>

<div class="message">

    <?= e($msg) ?>

</div>

<?php endif; ?>



<form method="post">

<input
    type="hidden"
    name="csrf_token"
    value="<?= e(csrf_token()) ?>"
>



<!-- =========================
     SUBJECT
========================= -->

<div class="form-group">

<label>
    Subject <span class="required">*</span>
</label>


<select
    name="subject_id"
    required
>

<option value="">
    Select Subject
</option>


<?php foreach ($subs as $s): ?>

<option
    value="<?= (int)$s['id'] ?>"
    <?= (
        isset($_POST['subject_id']) &&
        $_POST['subject_id'] == $s['id']
    ) ? 'selected' : '' ?>
>

<?= e(
    $s['subject_code']
    . ' - '
    . $s['subject_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>



<!-- =========================
     EXAM TITLE
========================= -->

<div class="form-group">

<label>
    Exam Title <span class="required">*</span>
</label>


<input
    type="text"
    name="exam_title"
    maxlength="200"
    value="<?= e($_POST['exam_title'] ?? '') ?>"
    placeholder="Example: Data Structures Mid Term Exam"
    required
>


<div class="help">
    Maximum 200 characters.
</div>

</div>



<!-- =========================
     DURATION
========================= -->

<div class="form-row">


<div class="form-group">

<label>
    Duration <span class="required">*</span>
</label>


<input
    type="number"
    name="duration_minutes"
    min="1"
    max="1440"
    value="<?= e($_POST['duration_minutes'] ?? '') ?>"
    placeholder="Enter duration in minutes"
    required
>


<div class="help">
    Example: 60 = 1 hour.
</div>

</div>



<!-- =========================
     START TIME
========================= -->

<div class="form-group">

<label>
    Start Date & Time
    <span class="required">*</span>
</label>


<input
    type="datetime-local"
    name="start_datetime"
    value="<?= e($_POST['start_datetime'] ?? '') ?>"
    required
>

</div>

</div>



<!-- =========================
     END TIME
========================= -->

<div class="form-group">

<label>
    End Date & Time
    <span class="required">*</span>
</label>


<input
    type="datetime-local"
    name="end_datetime"
    value="<?= e($_POST['end_datetime'] ?? '') ?>"
    required
>


<div class="help">
    End time must be later than start time.
</div>

</div>



<!-- =========================
     INFO
========================= -->

<div class="exam-info">

<div class="exam-info-title">
    Exam Scheduling
</div>


<div class="info-grid">


<div class="info-box">

<span class="info-label">
    Status
</span>

<span class="info-value">
    Scheduled
</span>

</div>


<div class="info-box">

<span class="info-label">
    Duration
</span>

<span class="info-value">
    In Minutes
</span>

</div>


<div class="info-box">

<span class="info-label">
    Exam Type
</span>

<span class="info-value">
    Online
</span>

</div>


</div>

</div>



<!-- =========================
     BUTTONS
========================= -->

<div class="form-actions">


<a
    href="dashboard.php"
    class="btn btn-secondary"
>
    Cancel
</a>


<button
    type="submit"
    class="btn btn-primary create-btn"
>
    Create Exam
</button>


</div>


</form>

</div>

</main>


</body>

</html>
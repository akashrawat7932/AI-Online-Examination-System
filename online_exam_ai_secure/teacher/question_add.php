<?php
require_once __DIR__ . '/../config/config.php';
require_role('teacher');

$t = db()->prepare('SELECT id FROM teachers WHERE user_id=?');
$t->execute([$_SESSION['user_id']]);

$tid = (int)$t->fetchColumn();

if (!$tid) {
    exit('Teacher profile missing.');
}

$subs = db()->query("
    SELECT id, subject_code, subject_name
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

    $type = $_POST['question_type'] ?? '';
    $diff = $_POST['difficulty'] ?? '';

    $q = trim(
        (string)($_POST['question_text'] ?? '')
    );

    $marks = filter_input(
        INPUT_POST,
        'marks',
        FILTER_VALIDATE_FLOAT
    );

    if (
        !$sid ||
        !in_array($type, ['mcq', 'descriptive'], true) ||
        !in_array($diff, ['easy', 'medium', 'hard'], true) ||
        $q === '' ||
        strlen($q) > 10000 ||
        $marks === false ||
        $marks <= 0 ||
        $marks > 100
    ) {

        $msg = 'Invalid data.';

    } else {

        $pdo = db();

        $pdo->beginTransaction();

        try {

            $x = $pdo->prepare("
                SELECT id
                FROM subjects
                WHERE id=?
                AND status='active'
            ");

            $x->execute([$sid]);

            if (!$x->fetch()) {
                throw new Exception();
            }

            $model = $type === 'descriptive'
                ? trim((string)($_POST['model_answer'] ?? ''))
                : null;

            $i = $pdo->prepare("
                INSERT INTO questions
                (
                    subject_id,
                    teacher_id,
                    question_text,
                    question_type,
                    difficulty,
                    marks,
                    model_answer
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $i->execute([
                $sid,
                $tid,
                $q,
                $type,
                $diff,
                $marks,
                $model
            ]);

            $qid = $pdo->lastInsertId();

            if ($type === 'mcq') {

                $opts = $_POST['options'] ?? [];
                $correct = $_POST['correct'] ?? '';

                $oi = $pdo->prepare("
                    INSERT INTO question_options
                    (
                        question_id,
                        option_label,
                        option_text,
                        is_correct
                    )
                    VALUES (?, ?, ?, ?)
                ");

                foreach (['A', 'B', 'C', 'D'] as $l) {

                    $v = trim(
                        (string)($opts[$l] ?? '')
                    );

                    if (
                        $v === '' ||
                        strlen($v) > 500
                    ) {
                        throw new Exception();
                    }

                    $oi->execute([
                        $qid,
                        $l,
                        $v,
                        $l === $correct ? 1 : 0
                    ]);
                }
            }

            $pdo->commit();

            $msg = 'Question saved successfully.';

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $msg = 'Could not save question.';
        }
    }
}
?>
<?php include_once 'header.php' ; ?>

<!doctype html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Add Question</title>


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

    color: #0f172a;
}


.header-right {

    display: flex;

    gap: 10px;

    align-items: center;
}


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

    max-width: 900px;

    margin: 35px auto;

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
        0 4px 15px rgba(15, 23, 42, 0.06);
}


.card-header {

    margin-bottom: 25px;

    border-bottom:
        1px solid #e2e8f0;

    padding-bottom: 20px;
}


.card-header h1 {

    font-size: 24px;

    margin-bottom: 6px;
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

    border-radius: 7px;

    margin-bottom: 20px;

    background: #eef2ff;

    color: #3730a3;

    border:
        1px solid #c7d2fe;

    font-weight: 500;
}


/* =========================
   FORM
========================= */

.form-group {

    margin-bottom: 20px;
}


.form-row {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;
}


label {

    display: block;

    margin-bottom: 7px;

    font-size: 14px;

    font-weight: 600;

    color: #334155;
}


input,
select,
textarea {

    width: 100%;

    padding: 12px 13px;

    border:
        1px solid #cbd5e1;

    border-radius: 7px;

    background: #ffffff;

    color: #0f172a;

    font-size: 14px;

    outline: none;

    transition: .2s;
}


input:focus,
select:focus,
textarea:focus {

    border-color: #4f46e5;

    box-shadow:
        0 0 0 3px rgba(79, 70, 229, .10);
}


textarea {

    min-height: 150px;

    resize: vertical;

    line-height: 1.6;
}


/* =========================
   QUESTION TEXT
========================= */

.question-text {

    min-height: 180px;
}


/* =========================
   MCQ BOX
========================= */

.question-section {

    margin-top: 25px;

    padding: 22px;

    background: #f8fafc;

    border:
        1px solid #e2e8f0;

    border-radius: 10px;
}


.section-title {

    font-size: 16px;

    font-weight: 700;

    margin-bottom: 18px;

    color: #334155;
}


.option-row {

    display: grid;

    grid-template-columns: 45px 1fr;

    gap: 10px;

    align-items: center;

    margin-bottom: 12px;
}


.option-label {

    width: 38px;

    height: 38px;

    border-radius: 50%;

    background: #e0e7ff;

    color: #3730a3;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 700;
}


.correct-box {

    margin-top: 18px;
}


/* =========================
   DESCRIPTIVE
========================= */

#des {

    margin-top: 25px;

    padding: 22px;

    background: #f8fafc;

    border:
        1px solid #e2e8f0;

    border-radius: 10px;
}


/* =========================
   BUTTON AREA
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


.btn-save {

    background: #4f46e5;

    color: white;

    padding: 12px 22px;
}


.btn-save:hover {

    background: #4338ca;
}


/* =========================
   HELPER TEXT
========================= */

.helper {

    margin-top: 5px;

    font-size: 12px;

    color: #64748b;
}


/* =========================
   MOBILE
========================= */

@media (max-width: 700px) {

    .header {

        padding: 15px 20px;

        flex-direction: column;

        align-items: flex-start;

        gap: 12px;
    }


    .header-right {

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


    .form-actions {

        flex-direction: column;
    }


    .btn-save {

        width: 100%;
    }

}

</style>

</head>


<body>


<!-- =========================
     HEADER
========================= -->



<main class="main">

<div class="card">


<div class="card-header">

    <h1>Add Question</h1>

    <p>
        Create MCQ or descriptive questions for your subject.
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


<!-- SUBJECT + MARKS -->

<div class="form-row">


<div class="form-group">

<label>
    Subject
</label>

<select name="subject_id" required>

<option value="">
    Select Subject
</option>

<?php foreach ($subs as $s): ?>

<option value="<?= (int)$s['id'] ?>">

<?= e(
    $s['subject_code']
    . ' - '
    . $s['subject_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="form-group">

<label>
    Marks
</label>

<input
    type="number"
    step="0.01"
    min="0.01"
    max="100"
    name="marks"
    placeholder="Enter marks"
    required
>

</div>

</div>


<!-- QUESTION TYPE + DIFFICULTY -->

<div class="form-row">


<div class="form-group">

<label>
    Question Type
</label>

<select
    name="question_type"
    id="qt"
    onchange="sw()"
>

<option value="mcq">
    MCQ
</option>

<option value="descriptive">
    Descriptive
</option>

</select>

</div>


<div class="form-group">

<label>
    Difficulty
</label>

<select name="difficulty">

<option value="easy">
    Easy
</option>

<option value="medium" selected>
    Medium
</option>

<option value="hard">
    Hard
</option>

</select>

</div>

</div>


<!-- QUESTION -->

<div class="form-group">

<label>
    Question
</label>

<textarea
    class="question-text"
    name="question_text"
    maxlength="10000"
    placeholder="Write your question here..."
    required
></textarea>

<div class="helper">
    Maximum 10,000 characters.
</div>

</div>


<!-- =========================
     MCQ
========================= -->

<div
    id="mcq"
    class="question-section"
>

<div class="section-title">
    MCQ Options
</div>


<?php foreach (['A', 'B', 'C', 'D'] as $l): ?>

<div class="option-row">

    <div class="option-label">
        <?= $l ?>
    </div>

    <input
        name="options[<?= $l ?>]"
        maxlength="500"
        placeholder="Enter option <?= $l ?>"
    >

</div>

<?php endforeach; ?>


<div class="correct-box">

<label>
    Correct Answer
</label>

<select name="correct">

<option value="A">Option A</option>
<option value="B">Option B</option>
<option value="C">Option C</option>
<option value="D">Option D</option>

</select>

</div>

</div>


<!-- =========================
     DESCRIPTIVE
========================= -->

<div
    id="des"
    style="display:none;"
>

<label>
    Model Answer
</label>

<textarea
    name="model_answer"
    maxlength="10000"
    placeholder="Write the model answer here..."
></textarea>

<div class="helper">
    The model answer can be used for descriptive evaluation.
</div>

</div>


<!-- =========================
     BUTTON
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
    class="btn btn-save"
>
    Save Question
</button>

</div>


</form>

</div>

</main>


<script>

function sw() {

    const type =
        document.getElementById('qt').value;

    const mcq =
        document.getElementById('mcq');

    const des =
        document.getElementById('des');


    if (type === 'mcq') {

        mcq.style.display = 'block';

        des.style.display = 'none';

    } else {

        mcq.style.display = 'none';

        des.style.display = 'block';

    }

}

</script>


</body>

</html>
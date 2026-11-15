<?php
require_once __DIR__ . '/../config/config.php';
require_role('teacher'); // teacher ya admin

$pdo = db();
$message = '';
$message_type = '';

/* =========================
   ADD NEW SUBJECT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {

    verify_csrf();

    $subject_code = trim((string)($_POST['subject_code'] ?? ''));
    $subject_name = trim((string)($_POST['subject_name'] ?? ''));

    if ($subject_code === '' || $subject_name === '') {

        $message = "Subject code and subject name are required.";
        $message_type = "error";

    } else {

        try {

            // Check duplicate subject code
            $check = $pdo->prepare("
                SELECT id
                FROM subjects
                WHERE subject_code = ?
                LIMIT 1
            ");
            $check->execute([$subject_code]);

            if ($check->fetch()) {

                $message = "Subject code already exists.";
                $message_type = "error";

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO subjects
                    (subject_code, subject_name, status)
                    VALUES (?, ?, 'active')
                ");

                $stmt->execute([
                    $subject_code,
                    $subject_name
                ]);

                $message = "Subject added successfully.";
                $message_type = "success";
            }

        } catch (Exception $e) {

            $message = "Subject could not be added: " . $e->getMessage();
            $message_type = "error";
        }
    }
}


/* =========================
   IMPORT CSV QUESTIONS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {

    verify_csrf();

    $subject_id = filter_input(
        INPUT_POST,
        'subject_id',
        FILTER_VALIDATE_INT
    );

    if (
        !$subject_id ||
        !isset($_FILES['csv_file']) ||
        $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK
    ) {

        $message = "Please select a valid subject and CSV file.";
        $message_type = "error";

    } else {

        $file = $_FILES['csv_file']['tmp_name'];

        if (!is_uploaded_file($file)) {

            $message = "Invalid file upload.";
            $message_type = "error";

        } else {

            $extension = strtolower(
                pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION)
            );

            if ($extension !== 'csv') {

                $message = "Only CSV files are allowed.";
                $message_type = "error";

            } else {

                $handle = fopen($file, 'r');

                if ($handle === false) {

                    $message = "Failed to open CSV file.";
                    $message_type = "error";

                } else {

                    // Skip header row
                    fgetcsv($handle);

                    $pdo->beginTransaction();

                    try {

                        $stmtQ = $pdo->prepare("
                            INSERT INTO questions
                            (
                                subject_id,
                                question_text,
                                question_type,
                                marks
                            )
                            VALUES (?, ?, 'mcq', ?)
                        ");

                        $stmtO = $pdo->prepare("
                            INSERT INTO question_options
                            (
                                question_id,
                                option_label,
                                option_text,
                                is_correct
                            )
                            VALUES (?, ?, ?, ?)
                        ");

                        $imported = 0;

                        while (
                            ($data = fgetcsv($handle, 2000, ',')) !== false
                        ) {

                            // Required 7 columns
                            if (count($data) < 7) {
                                continue;
                            }

                            $q_text  = trim($data[0]);
                            $opt_a   = trim($data[1]);
                            $opt_b   = trim($data[2]);
                            $opt_c   = trim($data[3]);
                            $opt_d   = trim($data[4]);
                            $correct = strtoupper(trim($data[5]));
                            $marks   = (float)($data[6] ?? 1);

                            if ($q_text === '') {
                                continue;
                            }

                            // Correct option validation
                            if (!in_array(
                                $correct,
                                ['A', 'B', 'C', 'D'],
                                true
                            )) {
                                continue;
                            }

                            // Insert Question
                            $stmtQ->execute([
                                $subject_id,
                                $q_text,
                                $marks
                            ]);

                            $question_id = (int)$pdo->lastInsertId();

                            // Insert Options
                            $options = [
                                'A' => $opt_a,
                                'B' => $opt_b,
                                'C' => $opt_c,
                                'D' => $opt_d
                            ];

                            foreach ($options as $label => $text) {

                                $is_correct =
                                    ($label === $correct) ? 1 : 0;

                                $stmtO->execute([
                                    $question_id,
                                    $label,
                                    $text,
                                    $is_correct
                                ]);
                            }

                            $imported++;
                        }

                        fclose($handle);

                        $pdo->commit();

                        $message =
                            "$imported questions imported successfully.";

                        $message_type = "success";

                    } catch (Exception $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        fclose($handle);

                        $message =
                            "Import failed: " . $e->getMessage();

                        $message_type = "error";
                    }
                }
            }
        }
    }
}


/* =========================
   GET SUBJECTS
========================= */
$subjects = $pdo->query("
    SELECT id, subject_code, subject_name
    FROM subjects
    WHERE status = 'active'
    ORDER BY subject_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include_once 'header.php' ; ?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Import Questions</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #1f2937;
}

/* ================= HEADER ================= */

.topbar {
    background: #111827;
    color: white;
    padding: 16px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.topbar h2 {
    margin: 0;
    font-size: 21px;
}

.topbar-right {
    display: flex;
    gap: 10px;
}

.top-btn {
    text-decoration: none;
    color: white;
    background: #374151;
    padding: 9px 15px;
    border-radius: 7px;
    font-size: 14px;
}

.top-btn:hover {
    background: #4b5563;
}

/* ================= CONTAINER ================= */

.container {
    max-width: 1100px;
    margin: 35px auto;
    padding: 0 20px;
}

.page-title {
    margin-bottom: 25px;
}

.page-title h1 {
    margin: 0 0 7px;
    font-size: 28px;
}

.page-title p {
    margin: 0;
    color: #6b7280;
}

/* ================= GRID ================= */

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

/* ================= CARD ================= */

.card {
    background: white;
    border-radius: 14px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.07);
    border: 1px solid #e5e7eb;
}

.card h2 {
    margin-top: 0;
    margin-bottom: 8px;
    font-size: 21px;
}

.card-description {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 22px;
}

/* ================= FORM ================= */

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 7px;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
    background: white;
    outline: none;
}

.form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

/* ================= FILE ================= */

.file-box {
    border: 2px dashed #cbd5e1;
    padding: 20px;
    border-radius: 10px;
    background: #f8fafc;
}

.file-box input {
    width: 100%;
}

/* ================= BUTTON ================= */

.btn {
    width: 100%;
    border: none;
    padding: 13px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-success {
    background: #16a34a;
    color: white;
}

.btn-success:hover {
    background: #15803d;
}

/* ================= MESSAGE ================= */

.message {
    margin-bottom: 22px;
    padding: 14px 17px;
    border-radius: 9px;
    font-weight: 600;
}

.message.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

/* ================= INFO ================= */

.info-box {
    margin-top: 20px;
    padding: 15px;
    background: #eff6ff;
    border-left: 4px solid #2563eb;
    border-radius: 6px;
    font-size: 13px;
    line-height: 1.6;
}

.csv-format {
    margin-top: 20px;
    padding: 15px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 13px;
    overflow-x: auto;
}

.csv-format strong {
    display: block;
    margin-bottom: 7px;
}

/* ================= MOBILE ================= */

@media (max-width: 800px) {

    .grid {
        grid-template-columns: 1fr;
    }

    .topbar {
        padding: 15px;
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }

    .topbar-right {
        width: 100%;
    }

    .top-btn {
        flex: 1;
        text-align: center;
    }

    .container {
        margin: 25px auto;
    }

}

</style>

</head>

<body>


<!-- ================= HEADER ================= -->

<!-- <div class="topbar">

    <h2>Teacher Panel</h2>

    <div class="topbar-right">

        <a href="dashboard.php" class="top-btn">
            Dashboard
        </a>

        <a href="../logout.php" class="top-btn">
            Logout
        </a>

    </div>

</div> -->


<!-- ================= MAIN ================= -->

<div class="container">

    <div class="page-title">

        <h1>Question Management</h1>

        <p>
            Add subjects and import MCQ questions using CSV file.
        </p>

    </div>


    <?php if ($message !== ''): ?>

        <div class="message <?= e($message_type) ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <div class="grid">


        <!-- ================= ADD SUBJECT ================= -->

        <div class="card">

            <h2>Add New Subject</h2>

            <div class="card-description">
                Create a new subject before importing questions.
            </div>

            <form method="post">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="add_subject"
                    value="1"
                >


                <div class="form-group">

                    <label>
                        Subject Code
                    </label>

                    <input
                        type="text"
                        name="subject_code"
                        class="form-control"
                        placeholder="Example: CS101"
                        maxlength="50"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Subject Name
                    </label>

                    <input
                        type="text"
                        name="subject_name"
                        class="form-control"
                        placeholder="Example: Computer Fundamentals"
                        maxlength="150"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-success"
                >
                    + Add Subject
                </button>

            </form>

        </div>


        <!-- ================= IMPORT QUESTIONS ================= -->

        <div class="card">

            <h2>Import Questions</h2>

            <div class="card-description">
                Select a subject and upload your MCQ CSV file.
            </div>

            <form
                method="post"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >


                <div class="form-group">

                    <label>
                        Select Subject
                    </label>

                    <select
                        name="subject_id"
                        class="form-control"
                        required
                    >

                        <option value="">
                            -- Select Subject --
                        </option>

                        <?php foreach ($subjects as $subject): ?>

                            <option value="<?= (int)$subject['id'] ?>">

                                <?= e($subject['subject_code']) ?>
                                -
                                <?= e($subject['subject_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Select CSV File
                    </label>

                    <div class="file-box">

                        <input
                            type="file"
                            name="csv_file"
                            accept=".csv"
                            required
                        >

                    </div>

                </div>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Import Questions
                </button>

            </form>


            <div class="csv-format">

                <strong>
                    CSV Format:
                </strong>

                Question,
                Option A,
                Option B,
                Option C,
                Option D,
                Correct Answer,
                Marks

                <br><br>

                Example:

                <br>

                What is 2+2?,
                3,
                4,
                5,
                6,
                B,
                1

            </div>


            <div class="info-box">

                <strong>Important:</strong><br>

                Correct Answer me sirf
                <strong>A, B, C ya D</strong>
                likhna hai.

                <br>

                CSV ki first row header honi chahiye.

            </div>

        </div>

    </div>

</div>

</body>
</html>
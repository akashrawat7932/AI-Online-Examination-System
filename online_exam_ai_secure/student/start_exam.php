<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_role('student');

$eid = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$eid) {
    http_response_code(400);
    exit('Invalid Exam ID.');
}

$s = db()->prepare('SELECT id FROM students WHERE user_id = ?');
$s->execute([$_SESSION['user_id']]);
$sid = (int)$s->fetchColumn();

$e = db()->prepare("SELECT * FROM exams WHERE id = ? AND status IN ('scheduled', 'active')");
$e->execute([$eid]);
$exam = $e->fetch();

if (!$sid || !$exam) {
    exit('Exam unavailable or invalid access.');
}

$c = db()->prepare('SELECT id, status FROM exam_attempts WHERE exam_id = ? AND student_id = ?');
$c->execute([$eid, $sid]);
$at = $c->fetch();

if (!$at) {
    $i = db()->prepare('INSERT INTO exam_attempts (exam_id, student_id, start_time, status) VALUES (?, ?, NOW(), "started")');
    $i->execute([$eid, $sid]);
    $aid = (int)db()->lastInsertId();
} elseif ($at['status'] === 'started') {
    $aid = (int)$at['id'];
} else {
    exit("<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px 20px; border-radius: 5px; font-family: Arial, sans-serif; width: fit-content; margin: 20px auto; font-weight: bold;'>
        Exam already submitted.
      </div>");
}

$q = db()->prepare('
    SELECT q.id, q.question_text, q.question_type, eq.marks 
    FROM exam_questions eq 
    JOIN questions q ON q.id = eq.question_id 
    WHERE eq.exam_id = ? 
    ORDER BY eq.question_order ASC
');
$q->execute([$eid]);
$qs = $q->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($exam['exam_title']) ?> - Examination</title>
    <!-- AI Models for Face & Object Detection -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/blazeface"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd"></script>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .proctor-box { position: fixed; top: 10px; right: 10px; background: #fff; border: 2px solid #333; padding: 8px; border-radius: 8px; text-align: center; z-index: 1000; }
        video, canvas { display: block; border-radius: 4px; }
        .warning { color: red; font-weight: bold; }
        
        #startOverlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.95); color: white; display: flex;
            flex-direction: column; justify-content: center; align-items: center; z-index: 9999;
        }
        #startBtn {
            padding: 12px 24px; font-size: 18px; background: #2563eb; color: white;
            border: none; border-radius: 6px; cursor: pointer; margin-top: 15px;
        }
        #warningModal {
            display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0, 0, 0, 0.8); z-index: 10000; justify-content: center; align-items: center;
        }
        .modal-box {
            background: #ffffff; color: #0f172a; padding: 2rem; border-radius: 10px;
            max-width: 420px; width: 90%; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .modal-title { color: #dc2626; font-size: 1.3rem; font-weight: bold; margin-bottom: 0.5rem; }
        .modal-btn {
            margin-top: 1.2rem; padding: 10px 20px; background: #dc2626; color: white;
            border: none; border-radius: 6px; cursor: pointer; font-weight: bold;
        }
    </style>
</head>
<body>

<div id="startOverlay">
    <h2>AI Proctored Examination</h2>
    <p>Camera permission & Fullscreen mode are required to begin this test.</p>
    <button id="startBtn" onclick="initiateExam()">Start Exam & Enable Fullscreen</button>
</div>

<div id="warningModal">
    <div class="modal-box">
        <div class="modal-title" id="modalTitle">⚠️ Warning 1/3</div>
        <p id="modalMsg" style="margin-top:10px; font-size:14px; line-height:1.4;"></p>
        <button class="modal-btn" onclick="closeWarningModal()">I Understand & Return to Exam</button>
    </div>
</div>

<div class="proctor-box">
    <video id="camera" autoplay muted playsinline width="320" height="240"></video>
    <canvas id="snapshot" width="160" height="120" style="display:none;"></canvas>
    <p id="cs" style="margin:4px 0; font-size:12px;">Initializing Proctoring...</p>
    <p id="warning" class="warning" style="font-size:11px;"></p>
</div>

<h1><?= e($exam['exam_title']) ?></h1>

<form method="post" action="submit_exam.php" id="examForm" style="margin-top:20px;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="attempt_id" value="<?= $aid ?>">
    <input type="hidden" name="tab_switches" id="tabSwitchesInput" value="0">
    <input type="hidden" name="fullscreen_exits" id="fsExitsInput" value="0">

    <?php foreach ($qs as $i => $x): ?>
        <fieldset style="margin-bottom:1.5rem;">
            <legend>Question <?= ($i + 1) ?> (<?= $x['marks'] ?> Marks)</legend>
            <p><?= e($x['question_text']) ?></p>

            <?php if ($x['question_type'] === 'mcq'): ?>
                <?php
                $o = db()->prepare('SELECT id, option_label, option_text FROM question_options WHERE question_id = ? ORDER BY option_label');
                $o->execute([$x['id']]);
                foreach ($o as $v):
                ?>
                    <label style="display:block;">
                        <input type="radio" 
                               name="answers[<?= $x['id'] ?>]" 
                               value="<?= $v['id'] ?>"
                               data-question-id="<?= $x['id'] ?>"
                               onchange="answerChanged(<?= $x['id'] ?>, this.value)">
                        <strong><?= e($v['option_label']) ?>.</strong> <?= e($v['option_text']) ?>
                    </label>
                <?php endforeach; ?>
            <?php else: ?>
                <textarea name="descriptive[<?= $x['id'] ?>]" 
                          data-question-id="<?= $x['id'] ?>"
                          oninput="answerChanged(<?= $x['id'] ?>, this.value)"
                          style="width:100%; height:100px;"></textarea>
            <?php endif; ?>
        </fieldset>
    <?php endforeach; ?>

    <button type="submit" id="submitBtn" onclick="return confirm('Submit exam?')">Submit Final Exam</button>
</form>

<script>
window.EXAM_ATTEMPT_ID = <?= (int)$aid ?>;
window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

const attemptId = window.EXAM_ATTEMPT_ID;
const csrfToken = window.CSRF_TOKEN;

let warningCount = 0;
const MAX_WARNINGS = 3;
let fsExits = 0;
let isSubmitting = false;

let faceModel = null;
let objectModel = null;
let lastFaceEvent = 0;
let lastPhoneDetectTime = 0;

// Initialize Camera & AI Proctoring
function initiateExam() {
    navigator.mediaDevices.getUserMedia({ video: true, audio: false })
        .then(stream => {
            const videoElem = document.getElementById('camera');
            videoElem.srcObject = stream;
            document.getElementById('cs').textContent = 'Proctoring Active';
            document.getElementById('cs').style.color = 'green';
            
            document.documentElement.requestFullscreen().catch(() => {});
            document.getElementById('startOverlay').style.display = 'none';

            loadAIModels();
        })
        .catch(err => {
            alert('Camera access is mandatory to write this exam.');
            document.getElementById('cs').textContent = 'Camera Unavailable!';
            document.getElementById('cs').style.color = 'red';
            sendProctorEvent('CAMERA_UNAVAILABLE', 'Camera permission denied or disconnected.', 10, false);
        });
}

// Universal Proctor Violation Handler
function handleViolation(eventType, message, riskPoints = 3, captureSnap = true) {
    if (isSubmitting) return;

    warningCount++;
    document.getElementById('tabSwitchesInput').value = warningCount;
    document.getElementById('warning').textContent = `Warnings: ${warningCount}/${MAX_WARNINGS}`;

    sendProctorEvent(eventType, message, riskPoints, captureSnap);

    if (warningCount >= MAX_WARNINGS) {
        isSubmitting = true;
        alert(`⚠️ Maximum warning limit reached (${MAX_WARNINGS}/${MAX_WARNINGS}). Your exam is being automatically submitted!`);
        document.getElementById('examForm').submit();
    } else {
        showWarningPopup(warningCount, message);
    }
}

function showWarningPopup(count, reason) {
    const remaining = MAX_WARNINGS - count;
    document.getElementById('modalTitle').textContent = `⚠️ Warning ${count} of ${MAX_WARNINGS}`;
    document.getElementById('modalMsg').innerHTML = `<strong>Reason:</strong> ${reason}<br><br>Please do not switch tabs or exit fullscreen mode. You have <strong>${remaining} warning(s)</strong> left before automatic submission.`;
    document.getElementById('warningModal').style.display = 'flex';
}

function closeWarningModal() {
    document.getElementById('warningModal').style.display = 'none';
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
    }
}

// Unified Backend Logger
function sendProctorEvent(eventType, message, riskPoints = 0, takeSnapshot = false) {
    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('attempt_id', String(attemptId));
    fd.append('event_type', eventType);
    fd.append('event_message', message);
    fd.append('risk_points', String(riskPoints));
    fd.append('device_id', getDeviceId());

    if (takeSnapshot) {
        const video = document.getElementById('camera');
        if (video && video.readyState >= 2) {
            const canvas = document.createElement('canvas');
            canvas.width = 320;
            canvas.height = 240;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, 320, 240);
            fd.append('snapshot', canvas.toDataURL('image/jpeg', 0.60));
        }
    }

    fetch('proctor_event.php', {
        method: 'POST',
        body: fd,
        keepalive: true
    }).catch(error => console.error('Proctor event failed:', error));
}

function getDeviceId() {
    let id = localStorage.getItem('exam_device_id');
    if (!id) {
        id = (window.crypto && crypto.randomUUID) 
            ? crypto.randomUUID() 
            : 'device-' + Date.now() + '-' + Math.random().toString(36).substring(2);
        localStorage.setItem('exam_device_id', id);
    }
    return id;
}

// Event Listeners for Tab Switching & Fullscreen Exit
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        handleViolation('TAB_SWITCH', 'Student switched tab or minimized browser window.', 3, false);
    }
});

document.addEventListener('fullscreenchange', () => {
    if (!document.fullscreenElement) {
        fsExits++;
        document.getElementById('fsExitsInput').value = fsExits;
        handleViolation('FULLSCREEN_EXIT', 'Student exited full-screen mode.', 3, true);
    }
});

// Load Models & Start Detection Intervals
async function loadAIModels() {
    try {
        faceModel = await blazeface.load();
        console.log("BlazeFace AI loaded");
    } catch (error) {
        console.error("Face model error:", error);
    }

    try {
        objectModel = await cocoSsd.load();
        console.log("COCO-SSD AI loaded");
    } catch (error) {
        console.error("Object model error:", error);
    }

    setInterval(detectFaces, 3000);
    setInterval(detectMobile, 3000);
}

async function detectFaces() {
    if (!faceModel || isSubmitting) return;
    const video = document.getElementById('camera');
    if (!video || video.readyState < 2) return;

    try {
        const faces = await faceModel.estimateFaces(video, false);
        const now = Date.now();

        if (now - lastFaceEvent < 10000) return;

        if (faces.length === 0) {
            lastFaceEvent = now;
            handleViolation('FACE_MISSING', 'No face detected in camera view.', 3, true);
        } else if (faces.length > 1) {
            lastFaceEvent = now;
            handleViolation('MULTIPLE_FACES', `Multiple faces detected (${faces.length}).`, 8, true);
        }
    } catch (error) {
        console.error('Face detection error:', error);
    }
}

async function detectMobile() {
    if (!objectModel || isSubmitting) return;
    const video = document.getElementById('camera');
    if (!video || video.readyState < 2) return;

    try {
        const objects = await objectModel.detect(video);
        const phone = objects.find(x => x.class === 'cell phone' && x.score >= 0.50);

        if (phone) {
            const now = Date.now();
            if (now - lastPhoneDetectTime > 800) {
                lastPhoneDetectTime = now;
                handleViolation('MOBILE_DETECTED', `Mobile phone detected (${(phone.score * 100).toFixed(0)}% confidence).`, 8, true);
            }
        }
    } catch (error) {
        console.error('Object detection error:', error);
    }
}

// IndexedDB Offline Autosave Logic
const pendingAnswers = new Map();
let syncTimer = null;

function answerChanged(questionId, answer) {
    questionId = Number(questionId);
    const old = pendingAnswers.get(questionId);
    const version = old ? old.version + 1 : 1;

    pendingAnswers.set(questionId, {
        question_id: questionId,
        answer: String(answer),
        version: version
    });

    saveLocalAnswers();
    scheduleSync();
}

const DB_NAME = "AIOnlineExam";
const STORE_NAME = "answers";
let examDB;

function openExamDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = function (event) {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { keyPath: "question_id" });
            }
        };
        request.onsuccess = event => { examDB = event.target.result; resolve(examDB); };
        request.onerror = () => reject(request.error);
    });
}

async function saveLocalAnswers() {
    if (!examDB) await openExamDB();
    const tx = examDB.transaction(STORE_NAME, "readwrite");
    const store = tx.objectStore(STORE_NAME);
    for (const answer of pendingAnswers.values()) {
        store.put(answer);
    }
}

async function loadLocalAnswers() {
    if (!examDB) await openExamDB();
    return new Promise((resolve, reject) => {
        const tx = examDB.transaction(STORE_NAME, "readonly");
        const store = tx.objectStore(STORE_NAME);
        const request = store.getAll();

        request.onsuccess = function () {
            const answers = request.result || [];
            answers.forEach(answer => {
                pendingAnswers.set(Number(answer.question_id), answer);
                const input = document.querySelector(`[data-question-id="${answer.question_id}"]`);
                if (input) {
                    if (input.type === 'radio') {
                        const targetRadio = document.querySelector(`input[name="${input.name}"][value="${answer.answer}"]`);
                        if (targetRadio) targetRadio.checked = true;
                    } else {
                        input.value = answer.answer;
                    }
                }
            });
            resolve(answers);
        };
        request.onerror = () => reject(request.error);
    });
}

function scheduleSync() {
    if (syncTimer) clearTimeout(syncTimer);
    syncTimer = setTimeout(syncAnswers, 5000);
}

async function syncAnswers() {
    if (!navigator.onLine || pendingAnswers.size === 0) return;
    const answers = Array.from(pendingAnswers.values());

    try {
        const response = await fetch("api/save_answers.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                attempt_id: window.EXAM_ATTEMPT_ID,
                answers: answers,
                csrf_token: window.CSRF_TOKEN
            })
        });

        const result = await response.json();
        if (!result.success) return;

        for (const sent of answers) {
            const current = pendingAnswers.get(Number(sent.question_id));
            if (current && current.version === sent.version) {
                pendingAnswers.delete(Number(sent.question_id));
            }
        }
    } catch (error) {
        console.warn("Network error during sync, answers remain in local storage.");
    }
}

window.addEventListener("online", syncAnswers);

document.addEventListener("DOMContentLoaded", async function () {
    await openExamDB();
    await loadLocalAnswers();
    if (navigator.onLine) syncAnswers();
});
</script>
</body>
</html>
<?php
require_once __DIR__ . '/../config/config.php';
require_role('teacher');

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Teacher & User Profile Data Fetch
$stmt = db()->prepare('
    SELECT t.*, t.email 
    FROM teachers t 
    JOIN users u ON u.id = t.user_id 
    WHERE t.user_id = ?
');
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    exit('Teacher profile not found.');
}

// Profile Update Form Handle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_name = trim($_POST['teacher_name'] ?? '');
    $department   = trim($_POST['department'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if (empty($teacher_name) || empty($department)) {
        $error = 'Name and Department are required fields.';
    } else {
        try {
            db()->beginTransaction();

            // 1. Update Teachers Table
            $updateT = db()->prepare('UPDATE teachers SET teacher_name = ?, department = ? WHERE user_id = ?');
            $updateT->execute([$teacher_name, $department, $user_id]);

            // 2. Update Password (Agar fill kiya ho)
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    throw new Exception('Password must be at least 6 characters long.');
                }
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateU = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $updateU->execute([$hash, $user_id]);
            }

            db()->commit();
            $message = 'Profile updated successfully!';

            // Refresh Info
            $stmt->execute([$user_id]);
            $teacher = $stmt->fetch();

        } catch (Exception $e) {
            db()->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>
<?php include_once 'header.php' ; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg-main: #f8fafc;
            --bg-surface: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #16a34a;
            --danger: #dc2626;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-dark);
            
            
           
           
        }

        .profile-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            width: 100%;
            max-width: 480px;
            padding: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }

         .main {

        margin: 10px 200px;

        padding: 0 10px;
    }

        .title {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .btn-back {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #dcfce7;
            color: var(--success);
        }

        .alert-danger {
            background-color: #fee2e2;
            color: var(--danger);
        }

        .form-group {
            align-items:center ;
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
            color: var(--text-dark);
        }

        input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus {
            border-color: var(--primary);
        }

        input[readonly] {
            background-color: #f1f5f9;
            color: var(--text-muted);
            cursor: not-allowed;
        }

        .password-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-box input {
            padding-right: 45px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            cursor: pointer;
            font-size: 18px;
            user-select: none;
        }

        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 0.5rem;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
        }
        
   


   
    </style>
</head>
<body>
<main class="main">


    <div class="profile-card">
      

        <?php if ($message): ?>
            <div class="alert alert-success"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" value="<?= e($teacher['email']) ?>" readonly>
            </div>

            <div class="form-group">
                <label for="teacher_name">Full Name</label>
                <input type="text" id="teacher_name" name="teacher_name" value="<?= e($teacher['teacher_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="department">Department</label>
                <input type="text" id="department" name="department" value="<?= e($teacher['department']) ?>" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password (Optional)</label>
                <div class="password-box">
                    <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current">
                    <span class="toggle-password" onclick="togglePassword()" id="eye">👁️</span>
                </div>
            </div>

            <button type="submit" class="btn-submit">Save Changes</button>
        </form>
    </div>

        </main>
        
    <script>
        function togglePassword() {
            const password = document.getElementById("new_password");
            const eye = document.getElementById("eye");

            if (password.type === "password") {
                password.type = "text";
                eye.innerHTML = "🙈";
            } else {
                password.type = "password";
                eye.innerHTML = "👁️";
            }
        }
    </script>
</body>
</html>
<?php require_once __DIR__ . '/config/config.php';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $u = trim((string)($_POST['username'] ?? ''));
    $p = (string)($_POST['password'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $c = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE username=? AND ip_address=? AND attempted_at>=NOW()-INTERVAL 15 MINUTE");
    $c->execute([$u, $ip]);

    if ($c->fetchColumn() >= 3) $err = 'Too many attempts. Try later.';
    else {
        $s = db()->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
        $s->execute([$u]);
        $x = $s->fetch();
        $ok = $x && $x['status'] === 'active' && password_verify($p, $x['password_hash']);
        $l = db()->prepare("INSERT INTO login_attempts(username,ip_address,success) VALUES(?,?,?)");
        $l->execute([$u, $ip, $ok ? 1 : 0]);
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$x['id'];
            $_SESSION['username'] = $x['username'];
            $_SESSION['role'] = $x['role'];
            header('Location: ' . BASE_URL . '/' . ($x['role'] === 'admin' ? 'admin/dashboard.php' : ($x['role'] === 'teacher' ? 'teacher/dashboard.php' :'student/dashboard.php')))
            ;
            exit;
        }
        $err = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login_Portal</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(
                    rgba(0, 0, 0, 0.55),
                    rgba(0, 0, 0, 0.55)
                ),
                url("https://cdn.pixabay.com/photo/2024/12/28/01/27/ai-generated-9295105_1280.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 40px 30px;
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 18px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.35);
            color: white;
        }

        .logo {
            text-align: center;
            margin-bottom: 15px;
        }

        .logo-circle {
            width: 75px;
            height: 75px;
            margin: auto;
            border-radius: 50%;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 38px;
        }

        h3 {
            text-align: center;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .subtitle {
            text-align: center;
            color: #eeeeee;
            font-size: 14px;
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 7px;
        }

        .mb-3 {
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: none;
            outline: none;
        }

        .password-box {
            position: relative;
        }

        .password-box input {
            padding-right: 50px;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 40px; /* Aligned nicely with input field */
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 20px;
            color: #555;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #0d6efd;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .login-btn:hover {
            background: #0b5ed7;
            transform: translateY(-1px);
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            font-size: 13px;
            color: #eeeeee;
        }

        .error-msg {
            color: #ffdddd;
            background: rgba(255, 0, 0, 0.2);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="logo">
            <div class="logo-circle">🎓</div>
        </div>
        <h3>Portal Login</h3>
        <p class="subtitle">Enter your credentials to continue</p>

        <?php if (!empty($err)): ?>
            <div class="error-msg"><?= e($err) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="mb-3">
                <label for="username">User Name</label>
                <input
                    type="text"
                    name="username"
                    id="username"
                    class="form-control"
                    placeholder="Enter User Name "
                    autocomplete="username"
                    required
                >
            </div>

            <div class="mb-3 password-box">
                <label for="password">Password</label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    class="form-control"
                    placeholder="Enter Password"
                    autocomplete="current-password"
                    required
                >
                <span class="toggle-password" onclick="togglePassword()" id="eye">
                    👁️
                </span>
            </div>

            <button type="submit" class="login-btn">
              Login
            </button>
        </form>
    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById("password");
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
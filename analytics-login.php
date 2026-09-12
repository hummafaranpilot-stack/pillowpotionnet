<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/analytics-auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['password'] ?? '') === ANALYTICS_PASSWORD) {
        // Only a boolean flag goes into the session — never the password itself.
        $_SESSION['pp_analytics_logged_in'] = true;
        session_regenerate_id(true);
        header('Location: /analytics');
        exit;
    }
    $error = 'Wrong password';
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>PillowPotion — Analytics Login</title>
<style>
  * { box-sizing: border-box; }
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0b1220;color:#e6ebf5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
  form{background:#121a2b;padding:36px;border-radius:14px;width:300px;}
  h1{font-size:20px;color:#a855f7;margin:0 0 20px;}
  input{width:100%;padding:10px 12px;margin-bottom:14px;background:#0b1220;border:1px solid #253046;border-radius:8px;color:#e6ebf5;}
  button{width:100%;padding:10px;background:#a855f7;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;}
  .err{color:#f87171;font-size:12px;margin:-8px 0 14px;}
</style></head>
<body>
<form method="post">
  <h1>Analytics Login</h1>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <input type="password" name="password" placeholder="Password" autofocus>
  <button type="submit">Log In</button>
</form>
</body></html>

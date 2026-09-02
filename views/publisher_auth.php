<?php
declare(strict_types=1);

$metaTitle = 'Ad Manager Account';
$metaDescription = 'Log in or set up your Ad Manager account to monitor your advertisements.';

$pdo = Database::getInstance()->getConnection();
$mode = $mode ?? 'login'; // 'login' or 'setup'
$errors = [];

if ($mode === 'setup') {
    $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    if (!$token) {
        redirect('/ads/login');
    }

    $stmt = $pdo->prepare('SELECT * FROM ad_publishers WHERE setup_token = ? AND (token_expires_at IS NULL OR token_expires_at > NOW())');
    $stmt->execute([$token]);
    $publisher = $stmt->fetch();

    if (!$publisher) {
        $errors[] = 'Invalid or expired setup token. Please log in or request a new password link.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $publisher) {
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_ARGON2ID);
            $pdo->prepare('UPDATE ad_publishers SET password_hash = ?, setup_token = NULL, token_expires_at = NULL WHERE id = ?')
                ->execute([$hash, $publisher['id']]);
            $_SESSION['publisher_id'] = (int) $publisher['id'];
            flash('success', 'Your password has been set successfully! Welcome to your Ad Manager account.');
            redirect('/ads/manager');
        }
    }
} elseif ($mode === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!RateLimiter::attempt('publisher_login', clientIp(), 10, 300)) {
            $errors[] = 'Too many login attempts — please wait a few minutes and try again.';
        } else {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            $stmt = $pdo->prepare('SELECT * FROM ad_publishers WHERE email = ?');
            $stmt->execute([$email]);
            $publisher = $stmt->fetch();

            if ($publisher && !empty($publisher['password_hash']) && password_verify($password, $publisher['password_hash'])) {
                $_SESSION['publisher_id'] = (int) $publisher['id'];
                flash('success', 'Welcome back!');
                redirect('/ads/manager');
            } else {
                $errors[] = 'Invalid email address or password. If this is your first time, please use the access link sent to your email upon ad approval.';
            }
        }
    }
}
?>

<div class="container section" style="max-width:480px; margin-top:40px;">
  <div class="card glass-card" style="padding:32px;">
    <?php if ($mode === 'setup'): ?>
      <h2 style="margin-bottom:8px; text-align:center;">Set Your Ad Manager Password</h2>
      <p style="color:var(--ink-dim); text-align:center; font-size:14px; margin-bottom:24px;">Create a password for your account (<strong><?= e($publisher['email'] ?? '') ?></strong>) to access your ad monitoring dashboard.</p>
    <?php else: ?>
      <h2 style="margin-bottom:8px; text-align:center;">Ad Manager Publisher Login</h2>
      <p style="color:var(--ink-dim); text-align:center; font-size:14px; margin-bottom:24px;">Monitor your ad impressions, clicks, CTR, and submit new advertisements.</p>
    <?php endif; ?>

    <?php if ($errors): ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert error"><?= e($err) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($mode === 'setup' && !empty($publisher)): ?>
      <form method="post" action="/ads/setup-password">
        <?= Csrf::field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label for="password">Create Password</label>
        <input type="password" id="password" name="password" required minlength="8">

        <label for="password_confirm">Confirm Password</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8">

        <button type="submit" class="btn btn-gold" style="width:100%; margin-top:16px;">Set Password & Continue</button>
      </form>
    <?php else: ?>
      <form method="post" action="/ads/login">
        <?= Csrf::field() ?>
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="<?= old('email') ?>" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="btn btn-gold" style="width:100%; margin-top:16px;">Log In to Ad Manager</button>
      </form>
      <div style="text-align:center; margin-top:20px;">
        <a href="/advertise" style="color:var(--gold-soft); font-size:14px;">↗ Want to advertise? Place an Order Here</a>
      </div>
    <?php endif; ?>
  </div>
</div>

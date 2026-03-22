<?php
require_once __DIR__ . '/config/session.php';
if (isset($_SESSION['user_id'])) {
    header('Location: /RIDERSAFE_Project/home.php'); exit();
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section class="auth-wrapper">

    <!-- Left panel -->
    <div class="auth-left">
        <div class="auth-left-inner">
            <div class="auth-icon">🔑</div>
            <h1>Reset Password</h1>
            <p>Enter the email linked to your account and we'll walk you through resetting your password.</p>
            <div class="auth-left-features">
                <div class="auth-feat"><span>🔒</span><span>Secure reset process</span></div>
                <div class="auth-feat"><span>📧</span><span>Instructions sent to your email</span></div>
                <div class="auth-feat"><span>⚡</span><span>Back to riding in minutes</span></div>
            </div>
        </div>
    </div>

    <!-- Right panel -->
    <div class="auth-right">
        <div class="auth-card">

            <div class="auth-card-header">
                <h2>Forgot Password?</h2>
                <p class="auth-card-sub">Remembered it? <a href="/RIDERSAFE_Project/login.php">Sign in →</a></p>
            </div>

            <?php if (!empty($_GET['sent'])): ?>
            <div class="auth-error" style="background:rgba(46,204,138,0.12);border-color:rgba(46,204,138,0.3);color:#2ecc8a;">
                ✅ If that email is registered, a reset link has been sent. Check your inbox.
            </div>
            <?php elseif (!empty($_GET['error'])): ?>
            <div class="auth-error">
                ⚠️ <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
            <?php endif; ?>

            <!-- 
                NOTE FOR DEVELOPER:
                This form submits to process/forgot_password_process.php.
                You will need to implement that file with your email-sending logic
                (e.g. using PHPMailer or PHP's mail() function).
                It should:
                  1. Check if the email exists in the users table.
                  2. Generate a secure token, store it with an expiry in a password_resets table.
                  3. Email the user a link: /RIDERSAFE_Project/reset_password.php?token=TOKEN
                  4. Redirect back here with ?sent=1.
            -->
            <form action="/RIDERSAFE_Project/process/forgot_password_process.php" method="POST">
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="you@email.com" autocomplete="email">
                </div>
                <button class="btn btn-primary full" type="submit" style="margin-top:8px;">
                    📧 Send Reset Instructions
                </button>
            </form>

            <div class="auth-divider"><span>or</span></div>

            <a href="/RIDERSAFE_Project/login.php" class="btn btn-secondary full" style="justify-content:center;">
                ← Back to Login
            </a>

        </div>
    </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/config/session.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['account_type'] === 'rider'
        ? '/RIDERSAFE_Project/rider_home.php'
        : '/RIDERSAFE_Project/contact_home.php'));
    exit();
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section class="auth-wrapper">

    <!-- Left panel -->
    <div class="auth-left">
        <div class="auth-left-inner">
            <img src="/RIDERSAFE_Project/assets/logo.png" alt="RiderSafe" class="login-hero-logo">
            <h1>Welcome Back</h1>
            <p>Login and continue your safety monitoring.<br>Your riders are counting on you.</p>
            <div class="auth-left-features">
                <div class="auth-feat"><span>🛡️</span><span>Real-time safety check-ins</span></div>
                <div class="auth-feat"><span>📍</span><span>Live GPS location sharing</span></div>
                <div class="auth-feat"><span>🚨</span><span>Instant SOS alerts</span></div>
            </div>
        </div>
    </div>

    <!-- Right panel -->
    <div class="auth-right">
        <div class="auth-card">

            <div class="auth-card-header">
                <h2>Sign In</h2>
                <p class="auth-card-sub">Don't have an account? <a href="/RIDERSAFE_Project/register.php">Create one free →</a></p>
            </div>

            <?php if (!empty($_GET['error'])): ?>
            <div class="auth-error">
                ⚠️ <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
            <?php endif; ?>

            <form action="/RIDERSAFE_Project/process/login_process.php" method="POST">
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="you@email.com" autocomplete="email">
                </div>
                <div class="input-group">
                    <label>Password</label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="loginPassword" required placeholder="••••••••" autocomplete="current-password">
                        <button type="button" class="show-pw-btn" onclick="togglePw('loginPassword', this)" aria-label="Show password">👁️</button>
                    </div>
                </div>
                <div style="text-align:right; margin-top:-8px; margin-bottom:12px;">
                    <a href="/RIDERSAFE_Project/forgot_password.php" style="font-size:13px; color:rgba(255,255,255,0.45); font-weight:600; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--teal-light)'" onmouseout="this.style.color='rgba(255,255,255,0.45)'">Forgot password?</a>
                </div>
                <button class="btn btn-primary full" type="submit" style="margin-top:8px;">
                    🔐 Login to RiderSafe
                </button>
            </form>

            <div class="auth-divider"><span>or</span></div>

            <a href="/RIDERSAFE_Project/register.php" class="btn btn-secondary full" style="justify-content:center;">
                🚀 Create Free Account
            </a>

        </div>
    </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<style>
.password-wrap { position: relative; display: flex; align-items: center; }
.password-wrap input { width: 100%; padding-right: 44px; box-sizing: border-box; }
.show-pw-btn {
    position: absolute; right: 10px; background: none; border: none;
    cursor: pointer; font-size: 16px; line-height: 1; opacity: 0.45;
    transition: opacity 0.2s; padding: 0; display: flex; align-items: center;
}
.show-pw-btn:hover { opacity: 0.9; }
</style>
<script>
function togglePw(inputId, btn) {
    const inp = document.getElementById(inputId);
    const isHidden = inp.type === 'password';
    inp.type = isHidden ? 'text' : 'password';
    btn.style.opacity = isHidden ? '0.9' : '0.45';
}
</script>
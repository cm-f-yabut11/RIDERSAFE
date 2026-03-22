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
            <div class="auth-icon">🏍️</div>
            <h1>Join RiderSafe</h1>
            <p>Create an account and build your safety network. Stay safe on every ride.</p>
            <div class="auth-left-features">
                <div class="auth-feat"><span>⚡</span><span>Set up in under a minute</span></div>
                <div class="auth-feat"><span>👥</span><span>Add unlimited trusted contacts</span></div>
                <div class="auth-feat"><span>🆓</span><span>Always free — no credit card</span></div>
            </div>
        </div>
    </div>

    <!-- Right panel -->
    <div class="auth-right">
        <div class="auth-card">

            <div class="auth-card-header">
                <h2>Create Account</h2>
                <p class="auth-card-sub">Already have an account? <a href="/RIDERSAFE_Project/login.php">Sign in →</a></p>
            </div>

            <?php if (!empty($_GET['error'])): ?>
            <div class="auth-error">
                ⚠️ <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
            <?php endif; ?>

            <form action="/RIDERSAFE_Project/process/register_process.php" method="POST">
                <div class="input-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" required placeholder="Juan Dela Cruz" autocomplete="name">
                </div>
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="you@email.com" autocomplete="email">
                </div>
                <div class="input-group">
                    <label>Password</label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="regPassword" required placeholder="Min. 8 characters" autocomplete="new-password">
                        <button type="button" class="show-pw-btn" onclick="togglePw('regPassword', this)" aria-label="Show password">👁️</button>
                    </div>
                </div>
                <div class="input-group">
                    <label>Confirm Password</label>
                    <div class="password-wrap">
                        <input type="password" name="password_confirm" id="regPasswordConfirm" required placeholder="Re-enter your password" autocomplete="new-password" oninput="checkMatch()">
                        <button type="button" class="show-pw-btn" onclick="togglePw('regPasswordConfirm', this)" aria-label="Show password">👁️</button>
                    </div>
                    <span id="pwMatchMsg" style="font-size:12px; font-weight:600; margin-top:5px; display:none;"></span>
                </div>
                <div class="input-group">
                    <label>I am a...</label>
                    <div class="role-select">
                        <label><input type="radio" name="account_type" value="rider" checked> 🏍️ Rider</label>
                        <label><input type="radio" name="account_type" value="contact"> 👤 Contact</label>
                    </div>
                </div>
                <button class="btn btn-primary full" type="submit">
                    🚀 Create My Account
                </button>
            </form>

            <p class="auth-link" style="margin-top:16px;">
                By registering you agree to our <a href="#">Terms of Use</a>.
            </p>

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
function checkMatch() {
    const p1  = document.getElementById('regPassword').value;
    const p2  = document.getElementById('regPasswordConfirm').value;
    const msg = document.getElementById('pwMatchMsg');
    if (!p2) { msg.style.display = 'none'; return; }
    if (p1 === p2) {
        msg.textContent = '✅ Passwords match';
        msg.style.color = 'var(--green, #2ecc8a)';
    } else {
        msg.textContent = '❌ Passwords do not match';
        msg.style.color = '#e05252';
    }
    msg.style.display = 'block';
}
// Prevent submit if passwords don't match
document.querySelector('form').addEventListener('submit', function(e) {
    const p1 = document.getElementById('regPassword').value;
    const p2 = document.getElementById('regPasswordConfirm').value;
    if (p1 !== p2) {
        e.preventDefault();
        checkMatch();
        document.getElementById('regPasswordConfirm').focus();
    }
});
</script>
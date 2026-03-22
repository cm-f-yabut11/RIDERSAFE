<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar">
    <div class="nav-flex">
        <a href="/RIDERSAFE_Project/index.php" class="logo-link">
            <img src="/RIDERSAFE_Project/assets/logo.png" alt="RiderSafe Logo" class="nav-logo-img">
            <span class="nav-brand-text">RiderSafe</span>
        </a>

        <div class="nav-links" id="navLinks">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/RIDERSAFE_Project/home.php" class="nav-item">Home</a>

                <?php if ($_SESSION['account_type'] === 'rider'): ?>
                    <a href="/RIDERSAFE_Project/rider_home.php" class="nav-item">Dashboard</a>
                    <a href="/RIDERSAFE_Project/rider_page.php" class="nav-item">Console</a>
                    <a href="/RIDERSAFE_Project/button_page.php" class="nav-item">Safety Button</a>
                <?php else: ?>
                    <a href="/RIDERSAFE_Project/contact_home.php" class="nav-item">Dashboard</a>
                    <a href="/RIDERSAFE_Project/contact_page.php" class="nav-item">Monitoring</a>
                <?php endif; ?>
                
                <a href="/RIDERSAFE_Project/profile.php" class="nav-item">Profile</a>
                <button class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()" title="Toggle dark/light mode" aria-label="Toggle theme">🌙</button>
                <a href="/RIDERSAFE_Project/process/logout.php" class="btn-logout">Logout</a>
            <?php else: ?>
                <a href="/RIDERSAFE_Project/landing.php" class="nav-item">Home</a>
                <a href="/RIDERSAFE_Project/login.php" class="nav-item">Login</a>
                <button class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()" title="Toggle dark/light mode" aria-label="Toggle theme">🌙</button>
                <a href="/RIDERSAFE_Project/register.php" class="btn btn-primary nav-getstarted">Get Started</a>
            <?php endif; ?>
        </div>
        <span class="menu-btn" id="menuBtn">☰</span>
    </div>
</nav>

<style>
.theme-toggle-btn {
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.13);
    border-radius: 8px;
    padding: 5px 10px;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
    line-height: 1;
}
.theme-toggle-btn:hover { background: rgba(255,255,255,0.14); }

/* ── Light mode overrides ── */
body.light-mode {
    --navy-deep:  #f0f4f8;
    --navy-mid:   #e2e8f0;
    --navy-card:  #ffffff;
    --navy-glass: rgba(255,255,255,0.85);
    --text-main:  #1a202c;
    --text-sub:   #4a5568;
    background: #f0f4f8 !important;
    color: #1a202c !important;
}
body.light-mode .navbar        { background: rgba(255,255,255,0.92) !important; border-bottom: 1px solid rgba(0,0,0,0.1) !important; }
body.light-mode .nav-item      { color: #2d3748 !important; }
body.light-mode .nav-item:hover{ color: #1a202c !important; }
body.light-mode .nav-brand-text{ color: #1a202c !important; }
body.light-mode .card,
body.light-mode .dash-card,
body.light-mode .auth-card,
body.light-mode .hub-card-main,
body.light-mode .hub-card-small{ background: #ffffff !important; border-color: rgba(0,0,0,0.08) !important; color: #1a202c !important; }
body.light-mode h1,body.light-mode h2,body.light-mode h3,body.light-mode h4 { color: #1a202c !important; }
body.light-mode p, body.light-mode span, body.light-mode label { color: #4a5568; }
body.light-mode .welcome-banner { background: linear-gradient(135deg, #e2e8f0, #cbd5e0) !important; }
body.light-mode footer { background: #e2e8f0 !important; border-color: rgba(0,0,0,0.08) !important; color: #4a5568 !important; }
body.light-mode .status-row { border-color: rgba(0,0,0,0.07) !important; }
body.light-mode .notif-item { border-color: rgba(0,0,0,0.07) !important; }
body.light-mode input, body.light-mode textarea {
    background: #f7fafc !important;
    border-color: rgba(0,0,0,0.15) !important;
    color: #1a202c !important;
}
body.light-mode .theme-toggle-btn { border-color: rgba(0,0,0,0.15); background: rgba(0,0,0,0.05); }
</style>

<script>
// ── Theme toggle ────────────────────────────────────────
const THEME_KEY = 'rs_theme';

function applyTheme(theme) {
    if (theme === 'light') {
        document.body.classList.add('light-mode');
        const btn = document.getElementById('themeToggleBtn');
        if (btn) btn.textContent = '☀️';
    } else {
        document.body.classList.remove('light-mode');
        const btn = document.getElementById('themeToggleBtn');
        if (btn) btn.textContent = '🌙';
    }
}

function toggleTheme() {
    const current = localStorage.getItem(THEME_KEY) || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    localStorage.setItem(THEME_KEY, next);
    applyTheme(next);

    // Persist to server for cross-device consistency
    fetch('/RIDERSAFE_Project/process/save_theme.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'theme=' + next
    });
}

// Apply on load from localStorage (instant, no flash)
(function() {
    const saved = localStorage.getItem(THEME_KEY) || 'dark';
    applyTheme(saved);
})();
</script>
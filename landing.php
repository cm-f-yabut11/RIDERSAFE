<?php
require_once __DIR__ . '/config/session.php';
// Redirect logged-in users straight to their dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /RIDERSAFE_Project/home.php');
    exit();
}
    
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RiderSafe — Ride Safe, Stay Connected</title>
    <link rel="stylesheet" href="/RIDERSAFE_Project/css/styles.css">
    <script defer src="/RIDERSAFE_Project/js/main.js"></script>
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section class="hero">
    <div class="hero-badge">🚴 Built for Filipino Riders</div>
    <h1>Ride with confidence,<br>stay <span class="accent">safe &amp; connected</span></h1>
    <p class="hero-sub">RiderSafe keeps your loved ones informed with real-time safety check-ins, location pings, and instant SOS alerts — every ride, every time.</p>
    <div class="hero-btns">
        <a href="/RIDERSAFE_Project/register.php" class="btn btn-primary hero-cta-main">
            🚀 Get Started — It's Free
        </a>
        <a href="#how" class="btn btn-secondary">
            ↓ See How It Works
        </a>
    </div>
    <p class="hero-cta-note">Free forever · No credit card · Built for Filipino Riders</p>
    <div class="hero-stats">
        <div class="hero-stat"><div class="hero-stat-icon">🛡️</div><div class="hero-stat-text"><p>Real-Time</p><p>Safety Monitoring</p></div></div>
        <div class="hero-stat"><div class="hero-stat-icon">📍</div><div class="hero-stat-text"><p>Live</p><p>Location Pings</p></div></div>
        <div class="hero-stat"><div class="hero-stat-icon">🚨</div><div class="hero-stat-text"><p>Instant</p><p>SOS Alerts</p></div></div>
        <div class="hero-stat"><div class="hero-stat-icon">👥</div><div class="hero-stat-text"><p>Trusted</p><p>Contact Network</p></div></div>
    </div>
</section>

<div class="section-bg" id="how">
    <div class="section-inner">
        <div class="reveal">
            <span class="section-label">How It Works</span>
            <h2 class="section-title">Set up in minutes, protected forever</h2>
            <p class="section-sub">Four simple steps to get your safety network running.</p>
        </div>
        <div class="steps">
            <div class="step-card reveal"><span class="step-num">01</span><span class="step-icon">👤</span><h3>Create Your Account</h3><p>Register as a rider or trusted contact. Takes less than a minute.</p></div>
            <div class="step-card reveal"><span class="step-num">02</span><span class="step-icon">👥</span><h3>Add Trusted Contacts</h3><p>Link family or friends as emergency contacts so they can monitor you.</p></div>
            <div class="step-card reveal"><span class="step-num">03</span><span class="step-icon">🏍️</span><h3>Start Your Trip</h3><p>Hit "Start Trip" before you ride. Auto check-ins every 30 minutes begin.</p></div>
            <div class="step-card reveal"><span class="step-num">04</span><span class="step-icon">🚨</span><h3>Stay Connected</h3><p>Tap SAFE to confirm you're okay, or trigger SOS to alert contacts immediately.</p></div>
        </div>
    </div>
</div>

<section class="section" id="features">
    <div class="reveal">
        <span class="section-label">Features</span>
        <h2 class="section-title">Everything you need to ride safe</h2>
        <p class="section-sub">Designed specifically for riders and the people who care about them.</p>
    </div>
    <div class="features-grid">
        <div class="feature-card reveal"><div class="feature-icon-wrap">🛡️</div><h3>Automatic Check-ins</h3><p>Pings you every 30 minutes during a trip. Miss one and your contacts are notified automatically.</p></div>
        <div class="feature-card reveal"><div class="feature-icon-wrap">✅</div><h3>One-Tap Safety Button</h3><p>Send a "I'm safe" confirmation to all your trusted contacts with a single tap.</p></div>
        <div class="feature-card reveal"><div class="feature-icon-wrap">📍</div><h3>Location Pings</h3><p>Share your real-time GPS location with contacts so they always know where you are.</p></div>
        <div class="feature-card reveal"><div class="feature-icon-wrap">🚨</div><h3>SOS Emergency Alert</h3><p>In an emergency, activate SOS to immediately broadcast your location to all trusted contacts.</p></div>
        <div class="feature-card reveal"><div class="feature-icon-wrap">🔔</div><h3>Smart Notifications</h3><p>Contacts get real-time alerts for missed check-ins, SOS activations, and safe confirmations.</p></div>
        <div class="feature-card reveal"><div class="feature-icon-wrap">👥</div><h3>Trusted Contact Network</h3><p>Add family or friends as emergency contacts with their own monitoring dashboard.</p></div>
    </div>
</section>

<section class="cta-section">
    <div class="cta-card reveal">
        <h2>Ready to ride safer?</h2>
        <p>Join RiderSafe today — free, fast, and built for every Filipino rider on the road.</p>
        <a href="/RIDERSAFE_Project/register.php" class="btn btn-primary">🚀 Create Free Account</a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

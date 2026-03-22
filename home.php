<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /RIDERSAFE_Project/landing.php'); 
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$user = $conn->query("SELECT fullname, account_type FROM users WHERE id = $user_id")->fetch_assoc();
$firstname = explode(' ', $user['fullname'])[0];

// Dynamic Routing
$dashboard_url = ($user['account_type'] === 'rider') ? 'rider_home.php' : 'contact_home.php';
$dashboard_desc = ($user['account_type'] === 'rider') ? "View ride history and active trip status." : "Track your riders and view safety alerts.";
?>

<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="hub-wrapper">
    <section class="hub-hero">
        <span class="hub-tag">Account Active</span>
        <h1 class="hub-greeting">Hey, <?php echo htmlspecialchars($firstname); ?>! 👋</h1>
        <p class="hub-lead">Welcome to your RiderSafe security hub.</p>
    </section>

    <div class="hub-content">
        <a href="/RIDERSAFE_Project/<?php echo $dashboard_url; ?>" class="hub-card-main glass-effect">
            <div class="card-status-dot"></div>
            <div class="card-icon-box">📊</div>
            <div class="card-info">
                <h3>Main Dashboard</h3>
                <p><?php echo $dashboard_desc; ?></p>
            </div>
            <span class="hub-chevron">→</span>
        </a>

        <div class="hub-grid-secondary">
            <a href="/RIDERSAFE_Project/profile.php" class="hub-card-small glass-effect">
                <div class="card-icon-small">⚙️</div>
                <div class="card-info">
                    <h4>Account Settings</h4>
                    <p>Manage security and profile.</p>
                </div>
            </a>

            <a href="/RIDERSAFE_Project/rider_page.php" class="hub-card-small glass-effect">
                <div class="card-icon-small">👥</div>
                <div class="card-info">
                    <h4>Trusted Contacts</h4>
                    <p>Manage linked accounts.</p>
                </div>
            </a>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
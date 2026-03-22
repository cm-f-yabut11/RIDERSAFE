<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /RIDERSAFE_Project/login.php'); exit();
}
if ($_SESSION['account_type'] !== 'contact') {
    header('Location: /RIDERSAFE_Project/rider_home.php'); exit();
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare('SELECT fullname FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$firstname = explode(' ', $user['fullname'])[0];

$rq = $conn->prepare('SELECT u.id, u.fullname, u.email FROM contact_links cl JOIN users u ON u.id = cl.rider_id WHERE cl.contact_id = ? AND cl.status = \'accepted\' ORDER BY cl.created_at ASC');
$rq->bind_param('i', $user_id); $rq->execute();
$all_riders = $rq->get_result()->fetch_all(MYSQLI_ASSOC);

// Allow switching between riders via ?rider_id=X
$selected_rider_id = isset($_GET['rider_id']) ? (int)$_GET['rider_id'] : ($all_riders[0]['id'] ?? null);
$rider = null;
foreach ($all_riders as $r) { if ((int)$r['id'] === $selected_rider_id) { $rider = $r; break; } }
if (!$rider && $all_riders) $rider = $all_riders[0];
$rider_id = $rider ? (int)$rider['id'] : null;

$last_ping_row = null; $trip_active = 0; $rs_row = null;
$last_ping_str = 'No ping yet'; $ping_status = 'none';

if ($rider_id) {
    $pq = $conn->prepare('SELECT status, latitude, longitude, created_at FROM pings WHERE rider_id = ? ORDER BY created_at DESC LIMIT 1');
    $pq->bind_param('i', $rider_id); $pq->execute();
    $last_ping_row = $pq->get_result()->fetch_assoc();
    if ($last_ping_row) {
        $last_ping_str = date('M d, Y – g:i A', strtotime($last_ping_row['created_at']));
        $ping_status   = $last_ping_row['status'];
    }
    $sq = $conn->prepare('SELECT system_active FROM rider_settings WHERE rider_id = ?');
    $sq->bind_param('i', $rider_id); $sq->execute();
    $rs_row = $sq->get_result()->fetch_assoc();
    $trip_active = $rs_row ? (int)$rs_row['system_active'] : 0;
}

$nq = $conn->prepare('SELECT type, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6');
$nq->bind_param('i', $user_id); $nq->execute();
$notifications = $nq->get_result()->fetch_all(MYSQLI_ASSOC);

// Status badge
if ($ping_status === 'confirmed')         { $badge_txt = '🟢 Rider Safe';    $badge_extra = ''; }
elseif ($ping_status === 'missed')        { $badge_txt = '🔴 Missed Ping';   $badge_extra = 'background:rgba(224,82,82,0.2);border-color:rgba(224,82,82,0.5);color:#e05252;'; }
elseif ($ping_status === 'manual_request'){ $badge_txt = '🚨 SOS Active';    $badge_extra = 'background:rgba(224,82,82,0.2);border-color:rgba(224,82,82,0.5);color:#e05252;'; }
elseif ($trip_active)                     { $badge_txt = '🔵 On Trip';        $badge_extra = ''; }
else                                      { $badge_txt = '⚪ No Active Trip'; $badge_extra = ''; }
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="page-body">
<div class="dashboard">

    <div class="welcome-banner contact-banner" data-emoji="👤">
        <div>
            <p class="welcome-label">👤 Contact Dashboard</p>
            <h1>Hello, <?php echo htmlspecialchars($firstname); ?>! 👋</h1>
            <p><?php echo $rider ? 'You\'re monitoring <strong>' . htmlspecialchars($rider['fullname']) . '</strong>.' : 'No rider linked yet.'; ?></p>
        </div>
        <?php if ($rider): ?>
        <div class="trip-badge" style="<?php echo $badge_extra; ?>"><?php echo $badge_txt; ?></div>
        <?php endif; ?>
    </div>

    <?php if (count($all_riders) > 1): ?>
    <div class="rider-switcher" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:10px 16px;margin-bottom:16px;">
        <span style="font-size:11px;font-weight:800;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:0.07em;margin-right:4px;">👥 Switch Rider:</span>
        <?php foreach ($all_riders as $r): ?>
        <a href="?rider_id=<?php echo $r['id']; ?>"
           style="display:flex;align-items:center;gap:7px;padding:6px 14px;border-radius:20px;border:1px solid <?php echo ((int)$r['id']===(int)$rider['id'])? 'var(--teal-light)' : 'rgba(255,255,255,0.12)'; ?>;background:<?php echo ((int)$r['id']===(int)$rider['id'])? 'rgba(42,107,138,0.3)' : 'rgba(255,255,255,0.05)'; ?>;color:<?php echo ((int)$r['id']===(int)$rider['id'])? 'var(--teal-light)' : 'rgba(255,255,255,0.65)'; ?>;font-size:13px;font-weight:700;text-decoration:none;font-family:'Plus Jakarta Sans',sans-serif;">
            <span style="width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,0.15);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;"><?php echo strtoupper(substr($r['fullname'],0,1)); ?></span>
            <?php echo htmlspecialchars(explode(' ', $r['fullname'])[0]); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($rider): ?>
    <div class="main-grid">
        <div class="card" style="border-top:3px solid var(--teal-light);">
            <div class="card-header">
                <span class="card-title">Rider Status</span>
                <span class="card-link" style="opacity:0.4;cursor:default;">Status</span>
            </div>
            <div class="rider-profile-row">
                <div class="rider-avatar"><?php echo strtoupper(substr($rider['fullname'],0,1)); ?></div>
                <div><div class="rider-name"><?php echo htmlspecialchars($rider['fullname']); ?></div><div class="rider-sub"><?php echo htmlspecialchars($rider['email']); ?></div></div>
            </div>
            <div class="status-row">
                <span class="status-key">Safety Status</span>
                <span class="badge <?php echo $ping_status==='confirmed'?'badge-safe':($ping_status==='missed'||$ping_status==='manual_request'?'badge-missed':'badge-none'); ?>">
                    <?php echo $ping_status==='none'?'No Data':ucfirst(str_replace('_',' ',$ping_status)); ?>
                </span>
            </div>
            <div class="status-row"><span class="status-key">Last Ping</span><span class="status-val"><?php echo htmlspecialchars($last_ping_str); ?></span></div>
            <div class="status-row">
                <span class="status-key">Trip Status</span>
                <span class="badge <?php echo $trip_active?'badge-active':'badge-none'; ?>"><?php echo $trip_active?'🏍️ On Trip':'No Active Trip'; ?></span>
            </div>
            <?php if ($last_ping_row && $last_ping_row['latitude']): ?>
            <div class="status-row"><span class="status-key">Last Location</span><span class="status-val" style="font-size:12px;"><?php echo number_format($last_ping_row['latitude'],4).', '.number_format($last_ping_row['longitude'],4); ?></span></div>
            <?php endif; ?>
        </div>

        <div class="card" style="border-top:3px solid var(--red);">
            <div class="card-header"><span class="card-title" style="color:var(--red);">🚨 Emergency Actions</span></div>
            <p style="font-size:13px;color:rgba(255,255,255,0.5);font-weight:500;margin-bottom:16px;">Immediate actions if you can't reach your rider.</p>
            <a href="tel:" class="emerg-btn e-call">📞 Call Rider</a>
            <a href="sms:" class="emerg-btn e-sms">💬 Send SMS</a>
            <?php if ($last_ping_row && $last_ping_row['latitude']): ?>
                <a href="https://maps.google.com/?q=<?php echo $last_ping_row['latitude'].','.$last_ping_row['longitude']; ?>" target="_blank" class="emerg-btn e-loc">📍 View Last Location</a>
            <?php else: ?>
                <button class="emerg-btn e-loc" disabled style="opacity:0.4;cursor:not-allowed;">📍 No Location Data</button>
            <?php endif; ?>
            <a href="/RIDERSAFE_Project/contact_page.php" class="emerg-btn e-dash">📊 Full Monitoring Dashboard</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Recent Notifications</span><span style="font-size:18px;">🔔</span></div>
        <?php if (count($notifications) > 0): ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:0 28px;">
            <?php foreach ($notifications as $n): ?>
                <div class="notif-item">
                    <div class="notif-dot <?php echo $n['is_read']?'read':''; ?>"></div>
                    <div><div class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></div><div class="notif-time"><?php echo date('M d, Y – g:i A',strtotime($n['created_at'])); ?></div></div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><span>🔔</span>No notifications yet. You'll be alerted if your rider misses a check-in.</div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="no-rider-card">
        <span class="big-icon">🔗</span>
        <h3>No Rider Linked Yet</h3>
        <p>You'll appear here once a rider adds you as their trusted contact.<br>Ask your rider to link you from their Rider Console.</p>
    </div>
    <?php endif; ?>

</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
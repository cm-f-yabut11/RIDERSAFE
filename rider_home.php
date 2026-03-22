<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /RIDERSAFE_Project/login.php'); exit();
}
if ($_SESSION['account_type'] !== 'rider') {
    header('Location: /RIDERSAFE_Project/contact_home.php'); exit();
}

$user_id = (int)$_SESSION['user_id'];

// ── ACTIVITY PATTERN LEARNING LOGIC ──
$anomaly_detected = false;
$history_q = $conn->prepare("SELECT COUNT(id) / NULLIF(COUNT(DISTINCT DATE(created_at)), 0) as avg_missed FROM pings WHERE rider_id = ? AND status = 'missed' AND created_at < CURDATE()");
$history_q->bind_param("i", $user_id); $history_q->execute();
$avg_missed = $history_q->get_result()->fetch_assoc()['avg_missed'] ?? 0;

$today_q = $conn->prepare("SELECT COUNT(id) as today_missed FROM pings WHERE rider_id = ? AND status = 'missed' AND DATE(created_at) = CURDATE()");
$today_q->bind_param("i", $user_id); $today_q->execute();
$today_missed = $today_q->get_result()->fetch_assoc()['today_missed'] ?? 0;

// Flag if today's misses are 3x higher than average (minimum 3 misses to avoid false alarms)
if ($today_missed > ($avg_missed * 3) && $today_missed >= 3) {
    $anomaly_detected = true;
}

$stmt = $conn->prepare('SELECT fullname FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$firstname = explode(' ', $user['fullname'])[0];

$rs = $conn->prepare('SELECT system_active, last_ping_time, ping_interval FROM rider_settings WHERE rider_id = ?');
$rs->bind_param('i', $user_id); $rs->execute();
$settings      = $rs->get_result()->fetch_assoc();
$trip_active   = $settings ? (int)$settings['system_active']  : 0;
$ping_interval = $settings ? (int)$settings['ping_interval']  : 1800; // 1800s = 30 min default
$last_ping     = ($settings && $settings['last_ping_time']) ? date('M d, Y – g:i A', strtotime($settings['last_ping_time'])) : 'No ping yet';

// ping_interval is stored as seconds now
$seconds_remaining = $ping_interval;
if ($trip_active && $settings['last_ping_time']) {
    $elapsed           = time() - strtotime($settings['last_ping_time']);
    $seconds_remaining = max(0, $ping_interval - ($elapsed % $ping_interval));
}

$ps = $conn->prepare('SELECT status FROM pings WHERE rider_id = ? ORDER BY created_at DESC LIMIT 1');
$ps->bind_param('i', $user_id); $ps->execute();
$lpr = $ps->get_result()->fetch_assoc();
$ping_status = $lpr ? ucfirst($lpr['status']) : 'None';

$tc = $conn->prepare('SELECT u.fullname, u.email FROM contact_links cl JOIN users u ON u.id = cl.contact_id WHERE cl.rider_id = ? AND cl.status = \'accepted\' LIMIT 5');
$tc->bind_param('i', $user_id); $tc->execute();
$contacts = $tc->get_result()->fetch_all(MYSQLI_ASSOC);

$nq = $conn->prepare('SELECT type, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$nq->bind_param('i', $user_id); $nq->execute();
$notifications = $nq->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="page-body">
<div class="dashboard">

    <?php if ($anomaly_detected): ?>
    <div class="card" style="background: rgba(224,82,82,0.12); border: 2px solid #e05252; margin-bottom: 22px; border-radius: 16px;">
        <div style="display: flex; align-items: center; gap: 15px; padding: 18px;">
            <span style="font-size: 32px;">⚠️</span>
            <div>
                <h4 style="color: #e05252; margin: 0; font-family: 'Syne', sans-serif; font-weight: 800;">Unusual Activity Warning</h4>
                <p style="margin: 4px 0 0; font-size: 13px; color: rgba(255,255,255,0.7); line-height: 1.4;">
                    You've missed <strong><?php echo $today_missed; ?></strong> pings today. This is significantly higher than your average. Your contacts have been alerted to check on you.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="welcome-banner rider-banner" data-emoji="🏍️">
        <div>
            <p class="welcome-label">🏍️ Rider Dashboard</p>
            <h1>Hey, <?php echo htmlspecialchars($firstname); ?>! 👋</h1>
            <p>Stay safe out there. Your contacts are watching over you.</p>
        </div>
        <div class="trip-badge <?php echo $trip_active ? 'active' : ''; ?>">
            <?php echo $trip_active ? '🟢 Trip Active' : '⚪ No Active Trip'; ?>
        </div>
    </div>

    <?php if ($trip_active): ?>
    <div class="countdown-card" id="countdownCard">
        <div class="cd-card-left">
            <span class="cd-card-icon">⏱️</span>
            <div>
                <p class="cd-card-label" id="cdCardLabel">Next Check-in</p>
                <p class="cd-card-sub">Every <?php
    $h = intdiv($ping_interval, 3600);
    $m = intdiv($ping_interval % 3600, 60);
    $s = $ping_interval % 60;
    $parts = [];
    if ($h) $parts[] = "{$h}h";
    if ($m) $parts[] = "{$m}m";
    if ($s) $parts[] = "{$s}s";
    echo ($parts ? implode(' ', $parts) : '0s');
?> · trip active</p>
            </div>
        </div>
        <div class="cd-card-right">
            <span class="cd-card-time" id="dashCdTime"><?php
                $h_r = intdiv($seconds_remaining, 3600);
                $m_r = intdiv($seconds_remaining % 3600, 60);
                $s_r = $seconds_remaining % 60;
                if ($h_r > 0) {
                    echo str_pad($h_r,2,'0',STR_PAD_LEFT).':'.str_pad($m_r,2,'0',STR_PAD_LEFT).':'.str_pad($s_r,2,'0',STR_PAD_LEFT);
                } else {
                    echo str_pad($m_r,2,'0',STR_PAD_LEFT).':'.str_pad($s_r,2,'0',STR_PAD_LEFT);
                }
            ?></span>
        </div>
    </div>
    <?php endif; ?>

    <p style="font-family:'Syne',sans-serif;font-size:17px;font-weight:800;margin-bottom:14px;">Quick Actions</p>
    <div class="actions-grid">
        <a href="/RIDERSAFE_Project/rider_page.php"  class="action-card a-orange"><span class="a-icon">🏍️</span>Rider Console</a>
        <a href="/RIDERSAFE_Project/button_page.php" class="action-card a-green"><span class="a-icon">✅</span>Safety Button</a>
        
        <div class="action-card a-red" id="sosButton" style="cursor: pointer; position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
            <span class="a-icon">🚨</span>
            <span id="sosText" style="position: relative; z-index: 2; font-weight: 800;">Hold for SOS</span>
            <div id="sosProgress" style="position: absolute; bottom: 0; left: 0; width: 100%; height: 0%; background: rgba(0,0,0,0.3); transition: height 0.1s linear; z-index: 1;"></div>
        </div>

        <a href="/RIDERSAFE_Project/profile.php"     class="action-card a-blue"><span class="a-icon">⚙️</span>My Profile</a>
    </div>

    <div class="bottom-grid">
        <div class="card">
            <div class="card-header"><span class="card-title">Safety Status</span><span style="font-size:20px;">🛡️</span></div>
            <div class="status-row">
                <span class="status-key">Status</span>
                <span class="badge <?php echo $ping_status==='Confirmed'?'badge-safe':($ping_status==='Missed'?'badge-missed':'badge-none'); ?>">
                    <?php echo $ping_status === 'None' ? 'No Data' : htmlspecialchars($ping_status); ?>
                </span>
            </div>
            <div class="status-row"><span class="status-key">Last Ping</span><span class="status-val"><?php echo htmlspecialchars($last_ping); ?></span></div>
            <div class="status-row">
                <span class="status-key">Trip</span>
                <span class="badge <?php echo $trip_active ? 'badge-active' : 'badge-none'; ?>"><?php echo $trip_active ? 'Active' : 'Inactive'; ?></span>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">Trusted Contacts</span>
                <?php if (count($contacts) > 0): ?><a href="/RIDERSAFE_Project/rider_page.php" class="card-link">Manage →</a><?php endif; ?>
            </div>
            <?php if (count($contacts) > 0): ?>
                <?php foreach ($contacts as $c): ?>
                <div class="contact-item">
                    <div class="contact-avatar"><?php echo strtoupper(substr($c['fullname'], 0, 1)); ?></div>
                    <div><div class="contact-name"><?php echo htmlspecialchars($c['fullname']); ?></div><div class="contact-email"><?php echo htmlspecialchars($c['email']); ?></div></div>
                    <span class="contact-linked">✓ Linked</span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><span>👥</span>No trusted contacts yet.<br><a href="/RIDERSAFE_Project/rider_page.php" style="color:var(--orange);font-weight:700;">Add one now →</a></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Recent Activity</span><span style="font-size:18px;">🔔</span></div>
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $n): ?>
                <div class="notif-item">
                    <div class="notif-dot <?php echo $n['is_read'] ? 'read' : ''; ?>"></div>
                    <div><div class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></div><div class="notif-time"><?php echo date('M d, g:i A', strtotime($n['created_at'])); ?></div></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><span>🔔</span>No recent activity yet.</div>
            <?php endif; ?>
        </div>
    </div>

</div>
</div>

<style>
/* CSS Styles Omitted for Brevity (Same as your original file) */
.countdown-card { display: flex; align-items: center; justify-content: space-between; background: rgba(59,158,255,0.08); border: 1px solid rgba(59,158,255,0.25); border-radius: 16px; padding: 16px 22px; margin-bottom: 22px; gap: 16px; }
.cd-card-time { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 900; color: #3b9eff; }
.countdown-card.due { background: rgba(224,82,82,0.15); border-color: rgba(224,82,82,0.55); animation: cardPulse 1s ease infinite; }
@keyframes cardPulse { 0%,100% { box-shadow: none; } 50% { box-shadow: 0 0 0 4px rgba(224,82,82,0.15); } }
</style>

<script>
// ── Live countdown — reads same deadline key as button_page ──
const DASH_INTERVAL     = <?php echo (int)$ping_interval; ?>; // seconds
const DASH_USER_ID      = <?php echo $user_id; ?>;
const DASH_INTERVAL_MS  = DASH_INTERVAL * 1000;
const DASH_DEADLINE_KEY = 'rs_deadline_' + DASH_USER_ID;

<?php if ($trip_active): ?>
const dashTimeEl  = document.getElementById('dashCdTime');
const dashCard    = document.getElementById('countdownCard');
const cdCardLabel = document.getElementById('cdCardLabel');

function dashGetDeadline() { return parseInt(localStorage.getItem(DASH_DEADLINE_KEY) || '0'); }

// Seed from server if deadline not yet stored
if (!dashGetDeadline()) {
    localStorage.setItem(DASH_DEADLINE_KEY, Date.now() + (<?php echo (int)$seconds_remaining; ?> * 1000));
}

function dashGetSecsLeft() {
    const dl = dashGetDeadline();
    if (!dl) return DASH_INTERVAL;
    return Math.max(0, Math.round((dl - Date.now()) / 1000));
}

function dashFormat(s) {
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sc = s % 60;
    if (h > 0) return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(sc).padStart(2,'0');
    return String(m).padStart(2,'0') + ':' + String(sc).padStart(2,'0');
}

function updateDashCard() {
    const secs = dashGetSecsLeft();
    const pct  = secs / DASH_INTERVAL;

    if (secs <= 0) {
        dashTimeEl.textContent  = '00:00';
        dashTimeEl.className    = 'cd-card-time due';
        dashCard.className      = 'countdown-card due';
        cdCardLabel.textContent = '🔔 Check-in Due!';
        return;
    }

    dashTimeEl.textContent = dashFormat(secs);
    if (pct <= 0.1)       { dashTimeEl.className = 'cd-card-time critical'; dashCard.className = 'countdown-card critical'; }
    else if (pct <= 0.25) { dashTimeEl.className = 'cd-card-time urgent';   dashCard.className = 'countdown-card urgent'; }
    else                  { dashTimeEl.className = 'cd-card-time';          dashCard.className = 'countdown-card'; }
}
setInterval(updateDashCard, 1000);
updateDashCard();
<?php endif; ?>

// ── SOS Long Press Logic ──
let sosTimer;
let sosProgressInt;
const sosBtn = document.getElementById('sosButton');
const sosProgress = document.getElementById('sosProgress');
const sosText = document.getElementById('sosText');

function startSOS(e) {
    e.preventDefault();
    let elapsed = 0;
    const duration = 3000; 
    sosText.innerText = "Holding...";
    
    sosProgressInt = setInterval(() => {
        elapsed += 100;
        sosProgress.style.height = (elapsed / duration * 100) + "%";
    }, 100);

    sosTimer = setTimeout(() => {
        clearInterval(sosProgressInt);
        triggerSOS();
    }, duration);
}

function cancelSOS() {
    clearTimeout(sosTimer);
    clearInterval(sosProgressInt);
    sosProgress.style.height = "0%";
    if(sosText.innerText !== "SENT!") sosText.innerText = "Hold for SOS";
}

function triggerSOS() {
    sosText.innerText = "SENT!";
    sosProgress.style.height = "100%";
    sosProgress.style.background = "rgba(40, 167, 69, 0.4)";
    
    fetch('/RIDERSAFE_Project/sos_handler.php', { method: 'POST' })
    .then(res => res.json())
    .then(data => {
        alert("SOS Alert has been sent to your contacts.");
        location.reload();
    }).catch(err => alert("Error sending SOS."));
}

sosBtn.addEventListener('mousedown', startSOS);
sosBtn.addEventListener('touchstart', startSOS);
window.addEventListener('mouseup', cancelSOS);
window.addEventListener('touchend', cancelSOS);

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
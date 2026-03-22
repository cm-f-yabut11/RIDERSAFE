<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /RIDERSAFE_Project/login.php'); exit();
}

$user_id = (int)$_SESSION['user_id'];

// ── AJAX: mark notifications read ─────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'mark_read') {
    $u = $conn->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?');
    $u->bind_param('i', $user_id); $u->execute();
    header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit();
}

// ── AJAX: send check-in request to rider ─────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'send_checkin' && $_SESSION['account_type'] === 'contact') {
    $target_rider_id = isset($_GET['rider_id']) ? (int)$_GET['rider_id'] : null;
    // Verify this contact is actually linked to that rider
    if ($target_rider_id) {
        $verify = $conn->prepare('SELECT rider_id FROM contact_links WHERE contact_id=? AND rider_id=? AND status=\'accepted\'');
        $verify->bind_param('ii', $user_id, $target_rider_id); $verify->execute();
        $lrow = $verify->get_result()->fetch_assoc();
    } else {
        $rlink = $conn->prepare('SELECT rider_id FROM contact_links WHERE contact_id=? AND status=\'accepted\' LIMIT 1');
        $rlink->bind_param('i', $user_id); $rlink->execute();
        $lrow = $rlink->get_result()->fetch_assoc();
    }
    if ($lrow) {
        $sq = $conn->prepare('SELECT fullname FROM users WHERE id=?');
        $sq->bind_param('i', $user_id); $sq->execute();
        $sname = $sq->get_result()->fetch_assoc()['fullname'] ?? 'Your contact';
        $nmsg = '📩 ' . htmlspecialchars($sname) . ' is requesting a safety check-in. Please tap SAFE to confirm you\'re okay.';
        $ni = $conn->prepare('INSERT INTO notifications (user_id,type,message) VALUES (?,\'manual_ping\',?)');
        $ni->bind_param('is', $lrow['rider_id'], $nmsg); $ni->execute();
        header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>'Check-in request sent!']); exit();
    }
    header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'No linked rider found.']); exit();
}

// ── AJAX: live refresh data ───────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'refresh') {
    $rid = ($_SESSION['account_type']==='contact') ? null : $user_id;
    if ($_SESSION['account_type']==='contact') {
        $rq2 = $conn->prepare('SELECT u.id FROM contact_links cl JOIN users u ON u.id=cl.rider_id WHERE cl.contact_id=? AND cl.status=\'accepted\' LIMIT 1');
        $rq2->bind_param('i',$user_id); $rq2->execute();
        $rr = $rq2->get_result()->fetch_assoc(); $rid = $rr ? (int)$rr['id'] : null;
    }
    $out = [];
    if ($rid) {
        $p2 = $conn->prepare('SELECT status,latitude,longitude,created_at FROM pings WHERE rider_id=? ORDER BY created_at DESC LIMIT 1');
        $p2->bind_param('i',$rid); $p2->execute(); $lp=$p2->get_result()->fetch_assoc();
        $s2 = $conn->prepare('SELECT system_active FROM rider_settings WHERE rider_id=?');
        $s2->bind_param('i',$rid); $s2->execute(); $rs2=$s2->get_result()->fetch_assoc();
        $m2 = $conn->prepare('SELECT COUNT(*) c FROM pings WHERE rider_id=? AND status=\'missed\'');
        $m2->bind_param('i',$rid); $m2->execute(); $mc=(int)$m2->get_result()->fetch_assoc()['c'];
        $un = $conn->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0');
        $un->bind_param('i',$user_id); $un->execute(); $uc=(int)$un->get_result()->fetch_assoc()['c'];
        $out=['ping_status'=>$lp?$lp['status']:'none','last_ping_time'=>$lp?date('M d, Y – g:i A',strtotime($lp['created_at'])):'No ping yet','latitude'=>$lp?$lp['latitude']:null,'longitude'=>$lp?$lp['longitude']:null,'trip_active'=>$rs2?(int)$rs2['system_active']:0,'missed_count'=>$mc,'unread'=>$uc];
    }
    header('Content-Type: application/json'); echo json_encode($out); exit();
}

// ── AJAX: export CSV ──────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $rid = ($_SESSION['account_type']==='contact') ? null : $user_id;
    if ($_SESSION['account_type']==='contact') {
        $rq3=$conn->prepare('SELECT u.id FROM contact_links cl JOIN users u ON u.id=cl.rider_id WHERE cl.contact_id=? AND cl.status=\'accepted\' LIMIT 1');
        $rq3->bind_param('i',$user_id);$rq3->execute();$rr3=$rq3->get_result()->fetch_assoc();$rid=$rr3?(int)$rr3['id']:null;
    }
    if ($rid) {
        $eq=$conn->prepare('SELECT status,latitude,longitude,created_at FROM pings WHERE rider_id=? ORDER BY created_at DESC');
        $eq->bind_param('i',$rid);$eq->execute();$rows=$eq->get_result()->fetch_all(MYSQLI_ASSOC);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ping_history_'.date('Y-m-d').'.csv"');
        $f=fopen('php://output','w');
        fputcsv($f,['Status','Latitude','Longitude','Timestamp']);
        foreach($rows as $r) fputcsv($f,[$r['status'],$r['latitude']??'N/A',$r['longitude']??'N/A',$r['created_at']]);
        fclose($f);
    }
    exit();
}

// ── MAIN PAGE DATA ────────────────────────────────────────────────────────
if ($_SESSION['account_type'] === 'contact') {
    // Support multiple linked riders — get the selected one (default: first)
    $all_riders_q = $conn->prepare('SELECT u.id, u.fullname, u.email FROM contact_links cl JOIN users u ON u.id = cl.rider_id WHERE cl.contact_id = ? AND cl.status = \'accepted\' ORDER BY cl.created_at ASC');
    $all_riders_q->bind_param('i', $user_id); $all_riders_q->execute();
    $all_riders = $all_riders_q->get_result()->fetch_all(MYSQLI_ASSOC);

    // Use ?rider_id=X to switch; default to first
    $selected_rider_id = isset($_GET['rider_id']) ? (int)$_GET['rider_id'] : ($all_riders[0]['id'] ?? null);
    // Security: make sure the selected rider is actually linked
    $rider = null;
    foreach ($all_riders as $r) { if ((int)$r['id'] === $selected_rider_id) { $rider = $r; break; } }
    if (!$rider && $all_riders) $rider = $all_riders[0];
} else {
    $all_riders = [];
    $rq = $conn->prepare('SELECT id, fullname, email FROM users WHERE id = ?');
    $rq->bind_param('i', $user_id); $rq->execute();
    $rider = $rq->get_result()->fetch_assoc();
}

$rider_id = $rider ? (int)$rider['id'] : null;
$last_ping_row=null; $trip_active=0; $all_pings=[]; $notifications=[]; $rs_row=null; $missed_total=0;

if ($rider_id) {
    $pq=$conn->prepare('SELECT status,latitude,longitude,created_at FROM pings WHERE rider_id=? ORDER BY created_at DESC LIMIT 1');
    $pq->bind_param('i',$rider_id);$pq->execute();$last_ping_row=$pq->get_result()->fetch_assoc();
    $sq=$conn->prepare('SELECT system_active,ping_interval,last_ping_time FROM rider_settings WHERE rider_id=?');
    $sq->bind_param('i',$rider_id);$sq->execute();$rs_row=$sq->get_result()->fetch_assoc();
    $trip_active=$rs_row?(int)$rs_row['system_active']:0;
    $hq=$conn->prepare('SELECT status,latitude,longitude,created_at FROM pings WHERE rider_id=? ORDER BY created_at DESC LIMIT 100');
    $hq->bind_param('i',$rider_id);$hq->execute();$all_pings=$hq->get_result()->fetch_all(MYSQLI_ASSOC);
    $mq=$conn->prepare('SELECT COUNT(*) c FROM pings WHERE rider_id=? AND status=\'missed\'');
    $mq->bind_param('i',$rider_id);$mq->execute();$missed_total=(int)$mq->get_result()->fetch_assoc()['c'];
    $nq=$conn->prepare('SELECT id,type,message,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
    $nq->bind_param('i',$user_id);$nq->execute();$notifications=$nq->get_result()->fetch_all(MYSQLI_ASSOC);
}

$ping_status   = $last_ping_row ? $last_ping_row['status'] : 'none';
$last_ping_str = $last_ping_row ? date('M d, Y – g:i A',strtotime($last_ping_row['created_at'])) : 'No ping yet';
$unread_count  = count(array_filter($notifications,fn($n)=>!$n['is_read']));
$confirmed_ct  = count(array_filter($all_pings,fn($p)=>$p['status']==='confirmed'));
$missed_ct     = count(array_filter($all_pings,fn($p)=>$p['status']==='missed'));
$sos_ct        = count(array_filter($all_pings,fn($p)=>$p['status']==='manual_request'));


if ($ping_status==='confirmed')         {$sl='SAFE';    $sc='safe';        $se='';}
elseif($ping_status==='missed')         {$sl='MISSED';  $sc='';            $se='background:rgba(224,82,82,0.2);color:var(--red);border:1px solid rgba(224,82,82,0.35);';}
elseif($ping_status==='manual_request') {$sl='SOS!';    $sc='';            $se='background:rgba(224,82,82,0.2);color:var(--red);border:1px solid rgba(224,82,82,0.35);';}
elseif($trip_active)                    {$sl='ON TRIP'; $sc='active-trip'; $se='';}
else                                    {$sl='STANDBY'; $sc='safe';        $se='';}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="page-body">
<main class="dashboard">
<?php if ($rider): ?>

<?php if ($missed_total > 0 && $ping_status === 'missed'): ?>
<div class="cp-alert" id="alertBanner">
    <span style="font-size:22px;flex-shrink:0;">🚨</span>
    <div style="flex:1;font-size:14px;font-weight:600;line-height:1.5;">
        <strong>Missed Check-in Alert!</strong> Your rider has not confirmed their safety.
        Total missed pings: <strong><?php echo $missed_total; ?></strong>. Last expected: <strong><?php echo $last_ping_str; ?></strong>
    </div>
    <button onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:rgba(255,255,255,0.4);font-size:18px;cursor:pointer;padding:0 4px;">✕</button>
</div>
<?php endif; ?>

<div class="cp-refresh-bar">
    <span class="cp-dot"></span>
    <span>Live — refreshes in <strong id="countdown">30</strong>s</span>
    <button class="cp-refresh-btn" onclick="doRefresh()">↻ Now</button>
    <span id="lastUpdated" style="margin-left:auto;font-size:11px;color:rgba(255,255,255,0.25);">Updated just now</span>
</div>

<!-- RIDER SWITCHER (only shown to contacts with multiple riders) -->
<?php if ($_SESSION['account_type'] === 'contact' && count($all_riders) > 1): ?>
<div class="rider-switcher">
    <span class="rs-label">👥 Monitoring:</span>
    <?php foreach ($all_riders as $r): ?>
    <a href="?rider_id=<?php echo $r['id']; ?>"
       class="rs-chip <?php echo ((int)$r['id'] === (int)$rider['id']) ? 'rs-chip-active' : ''; ?>">
        <span class="rs-avatar"><?php echo strtoupper(substr($r['fullname'], 0, 1)); ?></span>
        <?php echo htmlspecialchars(explode(' ', $r['fullname'])[0]); ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- PROFILE HEADER -->
<div class="profile-box" style="margin-bottom:20px;">
    <div class="info" style="display:flex;align-items:center;gap:16px;">
        <div class="rider-avatar" style="width:52px;height:52px;font-size:20px;"><?php echo strtoupper(substr($rider['fullname'],0,1)); ?></div>
        <div>
            <span class="mode-label"><?php echo $_SESSION['account_type']==='contact'?'MONITORING':'RIDER VIEW'; ?></span>
            <h2><?php echo htmlspecialchars($rider['fullname']); ?></h2>
            <p><?php echo htmlspecialchars($rider['email']); ?></p>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <div class="status-container">
            <p class="status-label">Current Status</p>
            <span class="status-badge <?php echo $sc; ?>" id="statusBadge" style="<?php echo $se; ?>"><?php echo $sl; ?></span>
        </div>
    </div>
</div>

<!-- TOP 3 CARDS -->
<div class="dash-grid" style="margin-bottom:20px;">
    <!-- Safety Status -->
    <div class="dash-card">
        <div class="card-icon">🛡️</div>
        <h3>Safety Status</h3>
        <div class="status-row">
            <span class="status-key">Ping Status</span>
            <span class="badge <?php echo $ping_status==='confirmed'?'badge-safe':($ping_status==='missed'||$ping_status==='manual_request'?'badge-missed':'badge-none'); ?>" id="pingBadge">
                <?php echo $ping_status==='none'?'No Data':ucfirst(str_replace('_',' ',$ping_status)); ?>
            </span>
        </div>
        <div class="status-row"><span class="status-key">Last Response</span><span class="status-val" id="lastPingTime" style="font-size:12px;"><?php echo $last_ping_str; ?></span></div>
        <div class="status-row"><span class="status-key">Trip Active</span><span class="badge <?php echo $trip_active?'badge-active':'badge-none'; ?>" id="tripBadge"><?php echo $trip_active?'🏍️ On Trip':'Not Active'; ?></span></div>
        <?php if($rs_row): ?>
        <div class="status-row"><span class="status-key">Check-in Every</span><span class="status-val"><?php echo $rs_row['ping_interval']; ?> mins</span></div>
        <?php endif; ?>
        <?php if($last_ping_row && $last_ping_row['latitude']): ?>
        <div class="status-row"><span class="status-key">Last Location</span><span class="status-val" id="lastCoords" style="font-size:11px;"><?php echo number_format($last_ping_row['latitude'],5).', '.number_format($last_ping_row['longitude'],5); ?></span></div>
        <?php endif; ?>
        <div class="map-placeholder" style="margin-top:14px;" id="tripStatusText">
            <?php echo $trip_active?'🟢 Trip active — checks every '.($rs_row['ping_interval']??30).' minutes.':'⚪ No active trip. Monitoring on standby.'; ?>
        </div>
    </div>

    <!-- Emergency Actions -->
    <div class="dash-card emergency-card">
        <div class="card-icon">🚨</div>
        <h3>Emergency Actions</h3>
        <p style="font-size:13px;color:rgba(255,255,255,0.5);font-weight:500;margin-bottom:16px;">Immediate actions if you can't reach your rider:</p>
        <a href="tel:" class="emerg-btn e-call">📞 Call Rider</a>
        <a href="sms:" class="emerg-btn e-sms">💬 Send SMS</a>
        <?php if($last_ping_row && $last_ping_row['latitude']): ?>
            <a href="https://maps.google.com/?q=<?php echo $last_ping_row['latitude'].','.$last_ping_row['longitude']; ?>" target="_blank" class="emerg-btn e-loc" id="mapLink">📍 Open Last Location in Maps</a>
        <?php else: ?>
            <span class="emerg-btn e-loc" style="opacity:0.4;cursor:not-allowed;" id="mapLink">📍 No Location Data Yet</span>
        <?php endif; ?>
        <?php if($_SESSION['account_type']==='contact'): ?>
        <button class="emerg-btn" id="checkinBtn" onclick="sendCheckin()" style="background:rgba(58,133,168,0.18);color:var(--teal-light);border-color:rgba(58,133,168,0.35);">📩 Request Safety Check-in</button>
        <?php endif; ?>
        <a href="<?php echo $_SESSION['account_type']==='contact'?'/RIDERSAFE_Project/contact_home.php':'/RIDERSAFE_Project/rider_home.php'; ?>" class="emerg-btn e-dash" style="margin-top:2px;">🏠 Back to Dashboard</a>
    </div>

    <!-- Rider Details + Stats -->
    <div class="dash-card">
        <div class="card-icon">👤</div>
        <h3>Rider Details</h3>
        <div class="rider-profile-row" style="margin-bottom:14px;">
            <div class="rider-avatar"><?php echo strtoupper(substr($rider['fullname'],0,1)); ?></div>
            <div><div class="rider-name"><?php echo htmlspecialchars($rider['fullname']); ?></div><div class="rider-sub"><?php echo htmlspecialchars($rider['email']); ?></div></div>
        </div>
        <p class="button-note" style="text-align:left;margin-bottom:14px;">✓ Verified RiderSafe Member</p>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
            <div class="cp-stat cp-stat-safe"><div class="cp-stat-n" id="safeCount"><?php echo $confirmed_ct; ?></div><div class="cp-stat-l">SAFE</div></div>
            <div class="cp-stat cp-stat-missed"><div class="cp-stat-n" id="missedCount"><?php echo $missed_ct; ?></div><div class="cp-stat-l">MISSED</div></div>
            <div class="cp-stat cp-stat-sos"><div class="cp-stat-n" id="sosCount"><?php echo $sos_ct; ?></div><div class="cp-stat-l">SOS</div></div>
        </div>
        <?php if($missed_total > 0): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-top:12px;background:rgba(224,82,82,0.1);border:1px solid rgba(224,82,82,0.2);border-radius:10px;padding:10px 14px;">
            <span style="font-size:18px;">⚠️</span>
            <div><div style="font-size:13px;font-weight:800;color:var(--red);"><?php echo $missed_total; ?> Total Missed Ping<?php echo $missed_total>1?'s':''; ?></div><div style="font-size:11px;color:rgba(255,255,255,0.35);">All-time missed check-ins</div></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- MAP -->
<div class="card" style="margin-bottom:20px;padding:0;overflow:hidden;">
    <div style="padding:16px 24px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,0.07);">
        <span class="card-title">📍 Rider Location Map</span>
        <span id="mapLabel" style="font-size:12px;color:rgba(255,255,255,0.35);font-weight:600;"><?php echo($last_ping_row&&$last_ping_row['latitude'])?'Showing last known location':'No location data yet'; ?></span>
    </div>
    <div style="width:100%;height:360px;background:var(--navy-mid);position:relative;" id="mapWrap">
        <?php if($last_ping_row && $last_ping_row['latitude']): ?>
        <iframe id="mapFrame" width="100%" height="360" style="border:0;display:block;" loading="lazy"
            src="https://maps.google.com/maps?q=<?php echo $last_ping_row['latitude'].','.$last_ping_row['longitude']; ?>&z=15&output=embed"></iframe>
        <?php else: ?>
        <div id="mapPH" style="height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:rgba(255,255,255,0.2);">
            <span style="font-size:48px;">🗺️</span>
            <p style="font-size:14px;font-weight:700;">No location data yet</p>
            <p style="font-size:12px;">Map appears once rider sends a GPS ping</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- HISTORY + NOTIFICATIONS -->
<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:20px;">
    <!-- Ping History Table -->
    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:8px;">
            <span class="card-title">📋 Ping History</span>
            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-left:auto;">
                <div style="display:flex;gap:3px;">
                    <button class="cp-filter active" data-f="all"            onclick="filterPings(this,'all')">All</button>
                    <button class="cp-filter"         data-f="confirmed"     onclick="filterPings(this,'confirmed')">✅ Safe</button>
                    <button class="cp-filter"         data-f="missed"        onclick="filterPings(this,'missed')">⚠️ Missed</button>
                    <button class="cp-filter"         data-f="manual_request" onclick="filterPings(this,'manual_request')">🚨 SOS</button>
                </div>
                <a href="?action=export_csv" class="cp-export">⬇ CSV</a>
            </div>
        </div>
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead><tr style="border-bottom:1px solid rgba(255,255,255,0.08);">
                <th style="font-size:10px;font-weight:800;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:1px;padding:10px 12px;text-align:left;">Status</th>
                <th style="font-size:10px;font-weight:800;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:1px;padding:10px 12px;text-align:left;">Date &amp; Time</th>
                <th style="font-size:10px;font-weight:800;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:1px;padding:10px 12px;text-align:left;">Coordinates</th>
                <th style="font-size:10px;font-weight:800;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:1px;padding:10px 12px;text-align:left;">Map</th>
            </tr></thead>
            <tbody>
            <?php if(count($all_pings)>0): ?>
                <?php foreach($all_pings as $p): ?>
                <tr class="cp-ping-row" data-s="<?php echo $p['status']; ?>" style="border-bottom:1px solid rgba(255,255,255,0.05);transition:background 0.15s;">
                    <td style="padding:9px 12px;">
                        <span class="badge <?php echo $p['status']==='confirmed'?'badge-safe':($p['status']==='missed'?'badge-missed':''); ?>"
                              style="font-size:10px;white-space:nowrap;<?php echo $p['status']==='manual_request'?'background:rgba(245,166,35,0.15);color:var(--orange);border-color:rgba(245,166,35,0.3);':''; ?>">
                            <?php if($p['status']==='confirmed') echo '✅ Safe'; elseif($p['status']==='missed') echo '⚠️ Missed'; else echo '🚨 SOS'; ?>
                        </span>
                    </td>
                    <td style="padding:9px 12px;font-size:12px;color:rgba(255,255,255,0.55);white-space:nowrap;">
                        <?php echo date('M d, Y',strtotime($p['created_at'])); ?><br>
                        <span style="color:rgba(255,255,255,0.3);"><?php echo date('g:i A',strtotime($p['created_at'])); ?></span>
                    </td>
                    <td style="padding:9px 12px;font-size:11px;color:rgba(255,255,255,0.35);">
                        <?php echo $p['latitude']?number_format($p['latitude'],4).', '.number_format($p['longitude'],4):'—'; ?>
                    </td>
                    <td style="padding:9px 12px;">
                        <?php if($p['latitude']): ?>
                        <a href="https://maps.google.com/?q=<?php echo $p['latitude'].','.$p['longitude']; ?>" target="_blank" style="color:var(--teal-light);font-size:17px;text-decoration:none;">📍</a>
                        <?php else: ?>
                        <span style="color:rgba(255,255,255,0.15);">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center;padding:32px;color:rgba(255,255,255,0.25);font-size:13px;">No pings recorded yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
        <p id="filterEmpty" style="display:none;text-align:center;padding:24px;font-size:13px;color:rgba(255,255,255,0.25);">No pings match this filter.</p>
    </div>

    <!-- Notifications -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">🔔 Notifications<?php if($unread_count>0): ?><span class="cp-unread-badge" id="unreadBadge"><?php echo $unread_count; ?></span><?php endif; ?></span>
            <?php if(count($notifications)>0): ?>
            <button class="card-link" onclick="markRead()" style="background:none;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;padding:0;">✓ Mark all read</button>
            <?php endif; ?>
        </div>
        <div id="notifList" style="max-height:460px;overflow-y:auto;">
        <?php if(count($notifications)>0): ?>
            <?php foreach($notifications as $n): ?>
            <div class="notif-item" id="notif-<?php echo $n['id']; ?>">
                <div class="notif-dot <?php echo $n['is_read']?'read':''; ?>"></div>
                <div style="flex:1;">
                    <div class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                    <div class="notif-time"><?php echo date('M d, Y – g:i A',strtotime($n['created_at'])); ?></div>
                </div>
                <span class="badge <?php echo $n['type']==='ping_confirmed'?'badge-safe':($n['type']==='ping_missed'?'badge-missed':'badge-none'); ?>" style="font-size:9px;white-space:nowrap;flex-shrink:0;<?php echo $n['type']==='manual_ping'?'background:rgba(245,166,35,0.15);color:var(--orange);border-color:rgba(245,166,35,0.3);border:1px solid;':''; ?>">
                    <?php if($n['type']==='ping_confirmed') echo '✅ SAFE'; elseif($n['type']==='ping_missed') echo '⚠️ MISSED'; elseif($n['type']==='manual_ping') echo '🚨 SOS'; else echo 'PING'; ?>
                </span>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state"><span>🔔</span>No notifications yet.<br>You'll be alerted when your rider misses a check-in.</div>
        <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>
<div class="no-rider-card">
    <span class="big-icon">🔗</span>
    <h3>No Rider Linked</h3>
    <?php if($_SESSION['account_type']==='contact'): ?>
        <p>You haven't been linked to a rider yet.<br>Ask your rider to add your email from their Rider Console.</p>
        <a href="/RIDERSAFE_Project/contact_home.php" class="btn btn-teal" style="margin-top:20px;">← Back to Dashboard</a>
    <?php else: ?>
        <p>No rider data available.</p>
        <a href="/RIDERSAFE_Project/rider_home.php" class="btn btn-teal" style="margin-top:20px;">← Back to Dashboard</a>
    <?php endif; ?>
</div>
<?php endif; ?>
</main>
</div>

<div id="toast" style="position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(16px);background:rgba(26,51,71,0.97);border:1px solid rgba(255,255,255,0.15);color:white;padding:12px 28px;border-radius:50px;font-size:14px;font-weight:700;opacity:0;transition:all 0.3s;z-index:9999;white-space:nowrap;box-shadow:0 8px 32px rgba(0,0,0,0.45);pointer-events:none;"></div>

<style>
.rider-switcher{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:10px 16px;margin-bottom:16px;}
.rs-label{font-size:11px;font-weight:800;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:0.07em;margin-right:4px;}
.rs-chip{display:flex;align-items:center;gap:7px;padding:6px 14px;border-radius:20px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.65);font-size:13px;font-weight:700;text-decoration:none;transition:all 0.2s;font-family:'Plus Jakarta Sans',sans-serif;}
.rs-chip:hover{background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.25);color:#fff;}
.rs-chip-active{background:rgba(42,107,138,0.3);border-color:var(--teal-light,#3a8598);color:var(--teal-light,#3a8598);}
.rs-avatar{width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;flex-shrink:0;}
.cp-alert{display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,rgba(224,82,82,0.22),rgba(224,82,82,0.1));border:1px solid rgba(224,82,82,0.4);border-left:4px solid var(--red);border-radius:var(--radius);padding:14px 18px;margin-bottom:16px;animation:cpFade .4s ease;}
.cp-refresh-bar{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:8px 16px;margin-bottom:16px;font-size:12px;font-weight:600;color:rgba(255,255,255,0.45);}
.cp-dot{width:8px;height:8px;border-radius:50%;background:var(--green);flex-shrink:0;animation:cpBlink 2s ease-in-out infinite;}
.cp-refresh-btn{background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);color:rgba(255,255,255,0.6);border-radius:6px;padding:4px 12px;font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:700;cursor:pointer;transition:background .2s;}
.cp-refresh-btn:hover{background:rgba(255,255,255,0.13);color:white;}
.cp-stat{border-radius:10px;padding:12px;text-align:center;}
.cp-stat-n{font-size:22px;font-weight:800;line-height:1;}
.cp-stat-l{font-size:10px;color:rgba(255,255,255,0.4);font-weight:700;margin-top:4px;letter-spacing:1px;}
.cp-stat-safe{background:rgba(46,204,138,0.12);border:1px solid rgba(46,204,138,0.2);} .cp-stat-safe .cp-stat-n{color:var(--green);}
.cp-stat-missed{background:rgba(224,82,82,0.12);border:1px solid rgba(224,82,82,0.2);} .cp-stat-missed .cp-stat-n{color:var(--red);}
.cp-stat-sos{background:rgba(245,166,35,0.12);border:1px solid rgba(245,166,35,0.2);} .cp-stat-sos .cp-stat-n{color:var(--orange);}
.cp-filter{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.09);color:rgba(255,255,255,0.45);border-radius:6px;padding:4px 10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s;white-space:nowrap;}
.cp-filter:hover{background:rgba(255,255,255,0.1);color:white;}
.cp-filter.active{background:rgba(245,166,35,0.18);border-color:rgba(245,166,35,0.4);color:var(--orange);}
.cp-export{background:rgba(46,204,138,0.1);border:1px solid rgba(46,204,138,0.22);color:var(--green);border-radius:6px;padding:4px 12px;font-size:11px;font-weight:800;text-decoration:none;white-space:nowrap;transition:background .2s;}
.cp-export:hover{background:rgba(46,204,138,0.2);}
.cp-unread-badge{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:var(--red);color:white;font-size:10px;font-weight:800;vertical-align:middle;margin-left:6px;}
.cp-ping-row:hover{background:rgba(255,255,255,0.04);}
.cp-ping-row.hidden{display:none;}
#notifList::-webkit-scrollbar{width:4px;} #notifList::-webkit-scrollbar-track{background:rgba(255,255,255,0.03);} #notifList::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.12);border-radius:2px;}
@keyframes cpFade{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
@keyframes cpBlink{0%,100%{opacity:1;}50%{opacity:0.25;}}
@media(max-width:960px){
    .dash-grid{grid-template-columns:1fr !important;}
    div[style*="grid-template-columns:1.4fr"]{grid-template-columns:1fr !important;display:grid;}
}
@media(max-width:600px){
    div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr !important;display:grid;}
}
</style>

<script>
function toast(msg){const t=document.getElementById('toast');t.textContent=msg;t.style.opacity='1';t.style.transform='translateX(-50%) translateY(0)';setTimeout(()=>{t.style.opacity='0';t.style.transform='translateX(-50%) translateY(16px)';},3500);}

function filterPings(btn, f){
    document.querySelectorAll('.cp-filter').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    let v=0;
    document.querySelectorAll('.cp-ping-row').forEach(r=>{
        const show=f==='all'||r.dataset.s===f;
        r.classList.toggle('hidden',!show);
        if(show)v++;
    });
    document.getElementById('filterEmpty').style.display=v===0?'block':'none';
}

function markRead(){
    fetch('?action=mark_read').then(r=>r.json()).then(d=>{
        if(d.ok){document.querySelectorAll('.notif-dot:not(.read)').forEach(x=>x.classList.add('read'));const b=document.getElementById('unreadBadge');if(b)b.remove();toast('✓ All notifications marked as read');}
    });
}

function sendCheckin(){
    const btn=document.getElementById('checkinBtn');if(!btn||btn.disabled)return;
    btn.disabled=true;btn.style.opacity='.6';
    fetch('?action=send_checkin').then(r=>r.json()).then(d=>{
        btn.disabled=false;btn.style.opacity='1';toast(d.ok?'📩 '+d.msg:'❌ '+d.msg);
        if(d.ok){btn.textContent='✓ Sent!';setTimeout(()=>{btn.textContent='📩 Request Safety Check-in';},5000);}
    });
}

let cd=30;
const cdEl=document.getElementById('countdown');
const luEl=document.getElementById('lastUpdated');

function doRefresh(){
    cd=30;
    fetch('?action=refresh').then(r=>r.json()).then(d=>{
        applyData(d);
        if(luEl)luEl.textContent='Updated '+new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
    }).catch(()=>toast('⚠️ Refresh failed'));
}

function applyData(d){
    const sb=document.getElementById('statusBadge');
    const pb=document.getElementById('pingBadge');
    const tb=document.getElementById('tripBadge');
    const lt=document.getElementById('lastPingTime');
    if(sb){
        let l='STANDBY',c='safe',s='';
        if(d.ping_status==='confirmed'){l='SAFE';c='safe';}
        else if(d.ping_status==='missed'){l='MISSED';c='';s='background:rgba(224,82,82,0.2);color:var(--red);border:1px solid rgba(224,82,82,0.35);';}
        else if(d.ping_status==='manual_request'){l='SOS!';c='';s='background:rgba(224,82,82,0.2);color:var(--red);border:1px solid rgba(224,82,82,0.35);';}
        else if(d.trip_active){l='ON TRIP';c='active-trip';}
        sb.textContent=l;sb.className='status-badge '+c;sb.style.cssText=s;
    }
    if(pb){const m={confirmed:'✅ Confirmed',missed:'⚠️ Missed',manual_request:'🚨 SOS',none:'No Data'};pb.textContent=m[d.ping_status]||'No Data';pb.className='badge '+(d.ping_status==='confirmed'?'badge-safe':d.ping_status==='missed'?'badge-missed':'badge-none');}
    if(tb){tb.textContent=d.trip_active?'🏍️ On Trip':'Not Active';tb.className='badge '+(d.trip_active?'badge-active':'badge-none');}
    if(lt)lt.textContent=d.last_ping_time;
    if(d.latitude&&d.longitude){
        const src='https://maps.google.com/maps?q='+d.latitude+','+d.longitude+'&z=15&output=embed';
        const fr=document.getElementById('mapFrame');
        if(fr){if(!fr.src.includes(d.latitude))fr.src=src;}
        else{const ph=document.getElementById('mapPH');if(ph){const nf=document.createElement('iframe');nf.id='mapFrame';nf.width='100%';nf.height='360';nf.style.cssText='border:0;display:block;';nf.src=src;ph.replaceWith(nf);const ml=document.getElementById('mapLabel');if(ml)ml.textContent='Showing last known location';}}
        const ml=document.getElementById('mapLink');if(ml&&ml.tagName!=='A'){const a=document.createElement('a');a.id='mapLink';a.className=ml.className;a.style.cssText=ml.style.cssText;a.href='https://maps.google.com/?q='+d.latitude+','+d.longitude;a.target='_blank';a.textContent='📍 Open Last Location in Maps';ml.replaceWith(a);}else if(ml)ml.href='https://maps.google.com/?q='+d.latitude+','+d.longitude;
        const cc=document.getElementById('lastCoords');if(cc)cc.textContent=parseFloat(d.latitude).toFixed(5)+', '+parseFloat(d.longitude).toFixed(5);
    }
    const ub=document.getElementById('unreadBadge');
    if(d.unread>0){if(ub)ub.textContent=d.unread;}else{if(ub)ub.remove();}
}

if(cdEl)setInterval(()=>{cd--;if(cd<=0)doRefresh();else cdEl.textContent=cd;},1000);


</script>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
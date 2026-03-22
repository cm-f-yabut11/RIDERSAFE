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
$msg = '';

// Add contact by email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_email'])) {
    $cemail = trim($_POST['contact_email']);
    $cq = $conn->prepare('SELECT id, fullname, account_type FROM users WHERE email = ? AND id != ?');
    $cq->bind_param('si', $cemail, $user_id); $cq->execute();
    $cuser = $cq->get_result()->fetch_assoc();
    if (!$cuser) {
        $msg = ['type' => 'error', 'text' => 'No user found with that email address.'];
    } elseif ($cuser['account_type'] !== 'contact') {
        $msg = ['type' => 'error', 'text' => 'That user is not registered as a Contact account.'];
    } else {
        $ck = $conn->prepare('SELECT id FROM contact_links WHERE rider_id = ? AND contact_id = ?');
        $ck->bind_param('ii', $user_id, $cuser['id']); $ck->execute(); $ck->store_result();
        if ($ck->num_rows > 0) {
            $msg = ['type' => 'warn', 'text' => htmlspecialchars($cuser['fullname']) . ' is already linked.'];
        } else {
            $ins = $conn->prepare('INSERT INTO contact_links (rider_id, contact_id, status) VALUES (?, ?, \'accepted\')');
            $ins->bind_param('ii', $user_id, $cuser['id']); $ins->execute();
            $msg = ['type' => 'success', 'text' => '✅ ' . htmlspecialchars($cuser['fullname']) . ' added as trusted contact!'];
        }
    }
}

// Remove contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_contact_id'])) {
    $rid = (int)$_POST['remove_contact_id'];
    $del = $conn->prepare('DELETE FROM contact_links WHERE rider_id = ? AND contact_id = ?');
    $del->bind_param('ii', $user_id, $rid); $del->execute();
    $msg = ['type' => 'success', 'text' => 'Contact removed.'];
}

$stmt = $conn->prepare('SELECT fullname FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$rider_name = htmlspecialchars($user['fullname']);

$rs = $conn->prepare('SELECT system_active, ping_interval, last_ping_time FROM rider_settings WHERE rider_id = ?');
$rs->bind_param('i', $user_id); $rs->execute();
$settings = $rs->get_result()->fetch_assoc();
$trip_active   = $settings ? (int)$settings['system_active'] : 0;
$ping_interval = $settings ? (int)$settings['ping_interval'] : 1800; // 1800s = 30 min default

$tc = $conn->prepare('SELECT u.id, u.fullname, u.email FROM contact_links cl JOIN users u ON u.id = cl.contact_id WHERE cl.rider_id = ? AND cl.status = \'accepted\'');
$tc->bind_param('i', $user_id); $tc->execute();
$contacts = $tc->get_result()->fetch_all(MYSQLI_ASSOC);

$pq = $conn->prepare('SELECT status, latitude, longitude, created_at FROM pings WHERE rider_id = ? ORDER BY created_at DESC LIMIT 5');
$pq->bind_param('i', $user_id); $pq->execute();
$recent_pings = $pq->get_result()->fetch_all(MYSQLI_ASSOC);

// Load button customization
$cq = $conn->prepare('SELECT btn_label, btn_color, btn_size, sound_enabled FROM button_customization WHERE rider_id = ?');
$cq->bind_param('i', $user_id); $cq->execute();
$custom = $cq->get_result()->fetch_assoc();

$btn_label     = htmlspecialchars($custom['btn_label']     ?? 'SAFE');
$btn_color     = $custom['btn_color']     ?? '#2ecc8a';
$btn_size      = $custom['btn_size']      ?? 'medium';
$sound_enabled = isset($custom['sound_enabled']) ? (int)$custom['sound_enabled'] : 1;

$preset_labels = ['SAFE', "I'M OK", 'ALL GOOD', 'ALIVE 👍'];
$is_preset     = in_array($btn_label, $preset_labels);

function darkenHex(string $hex, int $amt = 30): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return '#1aaa6e';
    $r = max(0, hexdec(substr($hex,0,2)) - $amt);
    $g = max(0, hexdec(substr($hex,2,2)) - $amt);
    $b = max(0, hexdec(substr($hex,4,2)) - $amt);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}
$btn_color_dark = darkenHex($btn_color);
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="rider-console">
<div class="container" style="padding-top:36px;padding-bottom:60px;">

    <div class="profile-box">
        <div class="info">
            <span class="mode-label">RIDER MODE</span>
            <h2>Welcome, <?php echo $rider_name; ?></h2>
            <p>Active on RiderSafe safety network</p>
        </div>
        <div class="status-container">
            <p class="status-label">Safety Status</p>
            <span class="status-badge <?php echo $trip_active ? 'active-trip' : 'safe'; ?>" id="statusBadge">
                <?php echo $trip_active ? 'ON TRIP' : 'READY TO RIDE'; ?>
            </span>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         COMPACT CHECK-IN INTERVAL PICKER (landscape row)
    ═══════════════════════════════════════════════════════ -->
    <div class="dash-card interval-card" style="margin-bottom:20px;padding:16px 20px;">
        <!-- Single landscape row: icon+label | HH:MM:SS | presets | save -->
        <div class="interval-row">

            <!-- A: Title -->
            <div class="interval-title-col">
                <span style="font-size:20px;line-height:1;">⏱️</span>
                <div>
                    <div class="interval-title">Check-in Interval</div>
                    <div class="interval-current">Now: <strong id="currentIntervalLabel"><?php
                        $h = intdiv($ping_interval, 3600);
                        $m = intdiv($ping_interval % 3600, 60);
                        $s = $ping_interval % 60;
                        $parts = [];
                        if ($h) $parts[] = "{$h}h";
                        if ($m) $parts[] = "{$m}m";
                        if ($s) $parts[] = "{$s}s";
                        echo $parts ? implode(' ', $parts) : '0s';
                    ?></strong></div>
                </div>
            </div>

            <div class="interval-divider"></div>

            <!-- B: H:M:S inline spinners -->
            <div class="interval-hms-inline">
                <div class="hms-spin">
                    <button type="button" class="hms-arrow" onclick="hmsStep('h',1)">▲</button>
                    <input type="number" id="hmsH" class="hms-input" min="0" max="23" value="<?php echo intdiv($ping_interval, 3600); ?>" oninput="hmsClamp(this,0,23)">
                    <button type="button" class="hms-arrow" onclick="hmsStep('h',-1)">▼</button>
                    <span class="hms-label">hr</span>
                </div>
                <span class="hms-colon">:</span>
                <div class="hms-spin">
                    <button type="button" class="hms-arrow" onclick="hmsStep('m',1)">▲</button>
                    <input type="number" id="hmsM" class="hms-input" min="0" max="59" value="<?php echo intdiv($ping_interval % 3600, 60); ?>" oninput="hmsClamp(this,0,59)">
                    <button type="button" class="hms-arrow" onclick="hmsStep('m',-1)">▼</button>
                    <span class="hms-label">min</span>
                </div>
                <span class="hms-colon">:</span>
                <div class="hms-spin">
                    <button type="button" class="hms-arrow" onclick="hmsStep('s',1)">▲</button>
                    <input type="number" id="hmsS" class="hms-input" min="0" max="59" value="<?php echo $ping_interval % 60; ?>" oninput="hmsClamp(this,0,59)">
                    <button type="button" class="hms-arrow" onclick="hmsStep('s',-1)">▼</button>
                    <span class="hms-label">sec</span>
                </div>
            </div>

            <div class="interval-divider"></div>

            <!-- C: Quick presets -->
            <div class="hms-presets">
                <button class="hms-preset" onclick="hmsSetPreset(0,0,30)">30s</button>
                <button class="hms-preset" onclick="hmsSetPreset(0,1,0)">1m</button>
                <button class="hms-preset" onclick="hmsSetPreset(0,5,0)">5m</button>
                <button class="hms-preset" onclick="hmsSetPreset(0,15,0)">15m</button>
                <button class="hms-preset" onclick="hmsSetPreset(0,30,0)">30m</button>
                <button class="hms-preset" onclick="hmsSetPreset(1,0,0)">1h</button>
            </div>

            <div class="interval-divider"></div>

            <!-- D: Save -->
            <div class="interval-save-col">
                <button type="button" class="interval-save-btn" id="intervalSaveBtn" onclick="saveCustomInterval()">💾 Save</button>
                <span class="interval-save-toast" id="intervalSaveToast"></span>
            </div>

        </div>
    </div>
    <!-- ════════════════════════════════════════════════════ -->

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

        <!-- SOS -->
        <div class="dash-card emergency-card">
            <div class="card-icon">🚨</div>
            <h3>Emergency Alert</h3>
            <p>Triggering this will immediately notify all your trusted contacts with your current GPS location.</p>
            <button class="btn btn-sos full" style="margin-top:18px;" onclick="triggerSOS()">ACTIVATE SOS</button>
            <p class="button-note">Press only in real emergencies</p>
        </div>

        <!-- TRIP -->
        <div class="dash-card">
            <div class="card-icon">🏍️</div>
            <h3>Trip Controller</h3>
            <p>Start your trip to begin automated safety check-ins every <span id="tripIntervalDisplay"><?php
                $h = intdiv($ping_interval, 3600);
                $m = intdiv($ping_interval % 3600, 60);
                $s = $ping_interval % 60;
                $parts = [];
                if ($h) $parts[] = "{$h}h";
                if ($m) $parts[] = "{$m}m";
                if ($s) $parts[] = "{$s}s";
                echo $parts ? implode(' ', $parts) : '0s';
            ?></span>.</p>
            <button class="btn btn-teal full" style="margin-top:18px;" id="tripBtn" onclick="toggleTrip()">
                <?php echo $trip_active ? 'END TRIP' : 'START TRIP'; ?>
            </button>
            <div class="map-placeholder" id="tripStatus">
                <?php echo $trip_active ? '🟢 Trip active. Contacts are being updated.' : '⚪ No active trip. Contacts are not being tracked.'; ?>
            </div>
            <?php if ($settings && $settings['last_ping_time']): ?>
            <p style="font-size:12px;color:rgba(255,255,255,0.3);margin-top:10px;text-align:center;">Last ping: <?php echo date('M d, g:i A', strtotime($settings['last_ping_time'])); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:20px;margin-bottom:20px;">

        <!-- CONTACTS -->
        <div class="dash-card">
            <div class="card-icon">👥</div>
            <h3>Trusted Contacts</h3>
            <p style="margin-bottom:16px;">These people receive your safety pings and SOS alerts.</p>

            <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.09);border-radius:12px;padding:16px;margin-bottom:16px;">
                <p style="font-size:13px;font-weight:700;color:rgba(255,255,255,0.65);margin-bottom:10px;">➕ Add Contact by Email</p>
                <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="email" name="contact_email" required placeholder="contact@email.com"
                        style="flex:1;min-width:160px;padding:10px 14px;border-radius:8px;border:1px solid rgba(255,255,255,0.14);background:rgba(255,255,255,0.07);color:white;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;outline:none;">
                    <button type="submit" class="btn btn-teal" style="padding:10px 16px;">Add</button>
                </form>
                <?php if ($msg): ?>
                <p style="font-size:12px;font-weight:700;margin-top:10px;padding:8px 12px;border-radius:8px;
                    <?php if($msg['type']==='success') echo 'background:rgba(46,204,138,0.15);color:var(--green);border:1px solid rgba(46,204,138,0.25);';
                          elseif($msg['type']==='error') echo 'background:rgba(224,82,82,0.15);color:var(--red);border:1px solid rgba(224,82,82,0.25);';
                          else echo 'background:rgba(245,166,35,0.15);color:var(--orange);border:1px solid rgba(245,166,35,0.25);'; ?>">
                    <?php echo $msg['text']; ?>
                </p>
                <?php endif; ?>
                <p style="font-size:11px;color:rgba(255,255,255,0.25);margin-top:8px;">The person must have a Contact account.</p>
            </div>

            <?php if (count($contacts) > 0): ?>
                <?php foreach ($contacts as $c): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.07);">
                    <div class="contact-avatar"><?php echo strtoupper(substr($c['fullname'],0,1)); ?></div>
                    <div style="flex:1;min-width:0;">
                        <div class="contact-name"><?php echo htmlspecialchars($c['fullname']); ?></div>
                        <div class="contact-email"><?php echo htmlspecialchars($c['email']); ?></div>
                    </div>
                    <span class="contact-linked">✓ Linked</span>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Remove this contact?');">
                        <input type="hidden" name="remove_contact_id" value="<?php echo $c['id']; ?>">
                        <button type="submit" style="background:rgba(224,82,82,0.15);border:1px solid rgba(224,82,82,0.3);color:var(--red);border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;">✕</button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><span>👥</span>No contacts linked.<br>Add one using the form above.</div>
            <?php endif; ?>

            <a href="/RIDERSAFE_Project/rider_home.php" class="btn btn-secondary full" style="margin-top:16px;">← Back to Dashboard</a>
        </div>

        <!-- PING HISTORY -->
        <div class="dash-card">
            <div class="card-icon">📋</div>
            <h3>Recent Pings</h3>
            <p style="margin-bottom:16px;">Your last 5 safety check-ins.</p>
            <?php if (count($recent_pings) > 0): ?>
                <?php foreach ($recent_pings as $p): ?>
                <div class="notif-item">
                    <div class="notif-dot" style="background:<?php echo $p['status']==='confirmed'?'var(--green)':($p['status']==='missed'?'var(--red)':'var(--orange)'); ?>;flex-shrink:0;"></div>
                    <div>
                        <div class="notif-msg">
                            <?php if($p['status']==='confirmed') echo '✅ Confirmed Safe';
                                  elseif($p['status']==='missed') echo '⚠️ Missed Check-in';
                                  else echo '🚨 SOS Request'; ?>
                        </div>
                        <?php if ($p['latitude']): ?>
                        <div class="notif-time"><a href="https://maps.google.com/?q=<?php echo $p['latitude'].','.$p['longitude']; ?>" target="_blank" style="color:var(--teal-light);text-decoration:none;">📍 <?php echo number_format($p['latitude'],4).', '.number_format($p['longitude'],4); ?></a></div>
                        <?php endif; ?>
                        <div class="notif-time"><?php echo date('M d, g:i A', strtotime($p['created_at'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state"><span>📋</span>No pings yet.<br>Start a trip and tap SAFE!</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         BUTTON CUSTOMIZATION CARD
    ═══════════════════════════════════════════════════════ -->
    <div class="dash-card" style="margin-bottom:20px;">
        <div class="card-icon">🎨</div>
        <h3>Safety Button Customization</h3>
        <p style="margin-bottom:20px;">Personalize how your safety button looks and behaves on the button page.</p>

        <div class="console-cust-wrap">

            <!-- Live Preview -->
            <div class="console-preview">
                <p class="cust-label" style="text-align:center;">Live Preview</p>
                <div class="preview-btn-wrap">
                    <button class="preview-btn" id="previewBtn"
                        style="background:linear-gradient(135deg,<?php echo $btn_color; ?>,<?php echo $btn_color_dark; ?>);">
                        <span id="previewBtnText"><?php echo $btn_label; ?></span>
                    </button>
                </div>
                <p style="font-size:11px;color:rgba(255,255,255,0.25);text-align:center;margin-top:8px;">This is how your button looks</p>
            </div>

            <!-- Form -->
            <form method="POST" action="/RIDERSAFE_Project/process/button_customize.php" class="console-cust-form">
                <input type="hidden" name="source" value="console">

                <!-- Label -->
                <div class="cust-row">
                    <label class="cust-label">Button Label</label>
                    <div class="cust-chips" id="consoleChips">
                        <?php foreach ($preset_labels as $p): ?>
                        <button type="button" class="chip <?php echo ($btn_label === $p) ? 'chip-active' : ''; ?>"
                            onclick="consoleSelectLabel(<?php echo json_encode($p); ?>, this)">
                            <?php echo htmlspecialchars($p); ?>
                        </button>
                        <?php endforeach; ?>
                        <button type="button" class="chip <?php echo !$is_preset ? 'chip-active' : ''; ?>"
                            onclick="consoleSelectLabel('__custom__', this)">Custom</button>
                    </div>
                    <input type="text" name="btn_label" id="consoleLabelInput"
                        value="<?php echo $btn_label; ?>" maxlength="12"
                        placeholder="Type your label..."
                        class="cust-input <?php echo $is_preset ? 'cust-input-hidden' : ''; ?>"
                        oninput="document.getElementById('previewBtnText').innerText = this.value || 'SAFE'">
                </div>

                <!-- Color -->
                <div class="cust-row">
                    <label class="cust-label">Button Color</label>
                    <div class="cust-colors">
                        <?php
                        $palette = [
                            '#2ecc8a' => 'Emerald',
                            '#3b9eff' => 'Blue',
                            '#f5a623' => 'Amber',
                            '#e05252' => 'Red',
                            '#a855f7' => 'Purple',
                            '#06b6d4' => 'Cyan',
                        ];
                        foreach ($palette as $hex => $name): ?>
                        <button type="button"
                            class="swatch <?php echo ($btn_color === $hex) ? 'swatch-active' : ''; ?>"
                            style="background:<?php echo $hex; ?>;"
                            title="<?php echo $name; ?>"
                            onclick="consoleSelectColor('<?php echo $hex; ?>', this)">
                        </button>
                        <?php endforeach; ?>
                        <label class="swatch swatch-picker <?php echo !array_key_exists($btn_color, $palette) ? 'swatch-active' : ''; ?>"
                            style="background:<?php echo !array_key_exists($btn_color, $palette) ? $btn_color : 'rgba(255,255,255,0.12)'; ?>;"
                            title="Custom Color">
                            <span class="swatch-plus">＋</span>
                            <input type="color" id="consoleColorPicker" value="<?php echo $btn_color; ?>"
                                oninput="consoleSelectColor(this.value, this.parentElement)">
                        </label>
                    </div>
                    <input type="hidden" name="btn_color" id="consoleBtnColor" value="<?php echo $btn_color; ?>">
                </div>

                <!-- Size -->
                <div class="cust-row">
                    <label class="cust-label">Button Size</label>
                    <div class="cust-chips" id="consoleSizeChips">
                        <?php foreach (['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'] as $val => $lbl): ?>
                        <button type="button"
                            class="chip <?php echo $btn_size === $val ? 'chip-active' : ''; ?>"
                            onclick="consoleSelectSize('<?php echo $val; ?>', this)">
                            <?php echo $lbl; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="btn_size" id="consoleBtnSize" value="<?php echo $btn_size; ?>">
                </div>

                <!-- Sound -->
                <div class="cust-row" style="margin-bottom:0;">
                    <label class="cust-label">Click Sound</label>
                    <label class="toggle-wrap">
                        <input type="checkbox" name="sound_enabled" id="consoleSoundToggle" value="1"
                            <?php echo $sound_enabled ? 'checked' : ''; ?>>
                        <span class="toggle-track">
                            <span class="toggle-thumb"></span>
                        </span>
                        <span class="toggle-lbl" id="consoleSoundLabel">
                            <?php echo $sound_enabled ? 'On' : 'Off'; ?>
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn btn-teal" style="width:100%;margin-top:22px;">
                    💾 Save Customization
                </button>

                <?php if (!empty($_GET['saved'])): ?>
                <p style="text-align:center;font-size:13px;color:var(--green);font-weight:700;margin-top:12px;">
                    ✅ Button preferences saved!
                </p>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <!-- ═══════════════════════════════════════════════════ -->

    <div class="dash-card" style="text-align:center;padding:32px;">
        <p style="font-size:15px;color:rgba(255,255,255,0.55);margin-bottom:18px;">Ready to confirm you're safe?</p>
        <a href="/RIDERSAFE_Project/button_page.php" class="btn btn-teal" style="font-size:16px;padding:14px 40px;">✅ Go to Safety Button</a>
    </div>

</div>
</div>


<style>
input[type="email"]::placeholder { color:rgba(255,255,255,0.25); }
input[type="email"]:focus { border-color:var(--teal-light) !important; box-shadow:0 0 0 3px rgba(42,107,138,0.2); }
@media(max-width:700px){
    div[style*="grid-template-columns:1fr 1fr"],
    div[style*="grid-template-columns:1.2fr"] { grid-template-columns:1fr !important; }
}

/* ── Customization inside console ── */
.console-cust-wrap {
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 28px;
    align-items: start;
}
@media(max-width:700px){
    .console-cust-wrap { grid-template-columns: 1fr; }
    .console-preview { display: flex; flex-direction: column; align-items: center; }
}
.console-preview { padding-top: 4px; }
.preview-btn-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 10px 0;
}
.preview-btn {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    border: none;
    cursor: default;
    font-family: 'Syne', sans-serif;
    font-size: 16px;
    font-weight: 900;
    color: #fff;
    letter-spacing: 0.04em;
    box-shadow: 0 8px 28px rgba(0,0,0,0.35);
    transition: background 0.3s, width 0.3s, height 0.3s;
    pointer-events: none;
}

/* Shared customization styles */
.cust-row { margin-bottom: 18px; }
.cust-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 9px;
}
.cust-chips { display: flex; flex-wrap: wrap; gap: 7px; }
.chip {
    padding: 6px 14px;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.13);
    background: rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.65);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.chip:hover { border-color: rgba(255,255,255,0.3); color: #fff; }
.chip-active {
    background: rgba(42,107,138,0.3);
    border-color: var(--teal-light);
    color: var(--teal-light);
}
.cust-input {
    margin-top: 9px;
    width: 100%;
    padding: 9px 13px;
    border-radius: 9px;
    border: 1px solid rgba(255,255,255,0.14);
    background: rgba(255,255,255,0.07);
    color: #fff;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 13px;
    font-weight: 700;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.2s;
}
.cust-input:focus { border-color: var(--teal-light); box-shadow: 0 0 0 3px rgba(42,107,138,0.2); }
.cust-input-hidden { display: none; }
.cust-input::placeholder { color: rgba(255,255,255,0.25); }
.cust-colors { display: flex; flex-wrap: wrap; gap: 9px; align-items: center; }
.swatch {
    width: 32px; height: 32px;
    border-radius: 50%;
    border: 3px solid transparent;
    cursor: pointer;
    transition: transform 0.2s, border-color 0.2s;
    outline: none;
}
.swatch:hover { transform: scale(1.15); }
.swatch-active { border-color: #fff !important; transform: scale(1.1); }
.swatch-picker {
    background: rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
.swatch-plus { font-size: 16px; color: rgba(255,255,255,0.6); line-height:1; pointer-events:none; }
.swatch-picker input[type="color"] {
    position:absolute; opacity:0; width:100%; height:100%; cursor:pointer; border:none; padding:0;
}
/* Toggle */
.toggle-wrap { display:flex; align-items:center; gap:12px; cursor:pointer; }
.toggle-wrap input[type="checkbox"] { display:none; }
.toggle-track {
    width:46px; height:26px;
    background:rgba(255,255,255,0.15);
    border-radius:13px; position:relative; transition:background 0.3s; flex-shrink:0;
}
.toggle-thumb {
    position:absolute; width:20px; height:20px;
    background:#fff; border-radius:50%;
    top:3px; left:3px; transition:transform 0.3s;
}
.toggle-wrap input:checked ~ .toggle-track { background: var(--green); }
.toggle-wrap input:checked ~ .toggle-track .toggle-thumb { transform: translateX(20px); }
.toggle-lbl { font-size:13px; font-weight:700; color:rgba(255,255,255,0.65); }
/* ── Compact Interval Picker — single landscape row ─── */
/* Outer row: all items side-by-side, wraps on small screens */
.interval-row {
    display: flex;
    align-items: center;
    gap: 0;
    flex-wrap: wrap;
    row-gap: 10px;
}
/* Vertical dividers between sections */
.interval-divider {
    width: 1px;
    height: 36px;
    background: rgba(255,255,255,0.1);
    flex-shrink: 0;
    margin: 0 14px;
    align-self: center;
}
/* A: Title col */
.interval-title-col {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.interval-title {
    font-family: 'Syne', sans-serif;
    font-size: 13px;
    font-weight: 800;
    color: white;
    line-height: 1.2;
}
.interval-current {
    font-size: 11px;
    color: rgba(255,255,255,0.35);
    font-weight: 600;
    margin-top: 2px;
}
.interval-current strong { color: #2ecc8a; }
/* B: H:M:S inline row */
.interval-hms-inline {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}
.hms-colon {
    font-size: 18px;
    font-weight: 800;
    color: rgba(255,255,255,0.2);
    line-height: 1;
    margin: 0 1px;
    padding-bottom: 14px; /* align with input, above label */
}
/* Individual spinner column */
.hms-spin {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}
.hms-label {
    font-size: 9px;
    font-weight: 700;
    color: rgba(255,255,255,0.28);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.hms-arrow {
    width: 26px;
    height: 16px;
    border: none;
    background: rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.4);
    border-radius: 4px;
    cursor: pointer;
    font-size: 9px;
    line-height: 1;
    transition: background 0.15s, color 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.hms-arrow:hover  { background: rgba(46,204,138,0.18); color: #2ecc8a; }
.hms-arrow:active { background: rgba(46,204,138,0.32); }
.hms-input {
    width: 40px;
    height: 32px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 7px;
    color: #2ecc8a;
    font-family: 'Syne', sans-serif;
    font-size: 16px;
    font-weight: 900;
    text-align: center;
    outline: none;
    transition: border-color 0.2s, background 0.2s;
    -moz-appearance: textfield;
}
.hms-input::-webkit-outer-spin-button,
.hms-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.hms-input:focus { border-color: rgba(46,204,138,0.55); background: rgba(46,204,138,0.08); }
/* C: Presets */
.hms-presets {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    align-items: center;
}
.hms-preset {
    padding: 4px 9px;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.45);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    white-space: nowrap;
}
.hms-preset:hover  { background: rgba(46,204,138,0.14); color: #2ecc8a; border-color: rgba(46,204,138,0.38); }
.hms-preset.active { background: rgba(46,204,138,0.18); color: #2ecc8a; border-color: rgba(46,204,138,0.45); }
/* D: Save col */
.interval-save-col {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    flex-shrink: 0;
}
.interval-save-btn {
    padding: 7px 16px;
    border-radius: 20px;
    border: 1px solid rgba(46,204,138,0.4);
    background: rgba(46,204,138,0.12);
    color: #2ecc8a;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    white-space: nowrap;
}
.interval-save-btn:hover  { background: rgba(46,204,138,0.22); }
.interval-save-btn:active { transform: scale(0.96); }
.interval-save-btn.saved  { background: rgba(46,204,138,0.07); color: rgba(46,204,138,0.45); cursor: default; }
.interval-save-toast {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 10px;
    font-weight: 700;
    color: #e05252;
    opacity: 0;
    transition: opacity 0.3s;
    min-height: 12px;
}
.interval-save-toast.show { opacity: 1; }

/* Mobile: stack vertically */
@media (max-width: 700px) {
    .interval-divider { display: none; }
    .interval-row     { flex-direction: column; align-items: flex-start; gap: 12px; }
    .interval-title-col,
    .interval-hms-inline,
    .hms-presets,
    .interval-save-col { width: 100%; }
    .interval-hms-inline { justify-content: flex-start; }
    .hms-presets         { justify-content: flex-start; }
}
</style>

<script>
/* ── Existing functions ─────────────────────────────── */
function triggerSOS() {
    if (!confirm('🚨 SEND SOS ALERT NOW?\n\nThis will immediately notify all your trusted contacts with your location.')) return;
    const send = (lat, lng) => {
        fetch('/RIDERSAFE_Project/process/update_ping.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'latitude=' + (lat||'') + '&longitude=' + (lng||'') + '&sos=1'
        }).then(() => alert('🚨 SOS Broadcasted! Stay where you are. Help is on the way.'));
    };
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(p => send(p.coords.latitude, p.coords.longitude), () => send(null, null));
    } else send(null, null);
}

const CONSOLE_USER_ID    = <?php echo $user_id; ?>;
const TRIP_START_KEY_CON = 'rs_tripstart_' + CONSOLE_USER_ID;

let isTripActive = <?php echo $trip_active ? 'true' : 'false'; ?>;

function toggleTrip() {
    isTripActive = !isTripActive;
    document.getElementById('tripBtn').innerText      = isTripActive ? 'END TRIP' : 'START TRIP';
    document.getElementById('statusBadge').innerText  = isTripActive ? 'ON TRIP' : 'READY TO RIDE';
    document.getElementById('statusBadge').className  = 'status-badge ' + (isTripActive ? 'active-trip' : 'safe');
    document.getElementById('tripStatus').innerText   = isTripActive
        ? '🟢 Trip active. Contacts are being updated.'
        : '⚪ No active trip. Contacts are not being tracked.';

    if (isTripActive) {
        // FIX: Record trip start NOW — this is the anchor for all timer pages
        localStorage.setItem(TRIP_START_KEY_CON, Date.now());
    } else {
        // FIX: Only clear trip start on explicit END TRIP — never on navigation/reload
        localStorage.removeItem(TRIP_START_KEY_CON);
        // Also clear any legacy rs_timer_ keys
        Object.keys(localStorage).forEach(k => {
            if (k.startsWith('rs_timer_')) localStorage.removeItem(k);
        });
    }

    fetch('/RIDERSAFE_Project/process/toggle_trip.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'active=' + (isTripActive ? 1 : 0)
    });
}

/* ── Console Customization functions ────────────────── */
function adjustHex(hex, amt) {
    const clamp = v => Math.max(0, Math.min(255, v + amt));
    const r = clamp(parseInt(hex.slice(1,3), 16));
    const g = clamp(parseInt(hex.slice(3,5), 16));
    const b = clamp(parseInt(hex.slice(5,7), 16));
    return '#' + [r,g,b].map(x => x.toString(16).padStart(2,'0')).join('');
}

function consoleSelectLabel(preset, el) {
    document.querySelectorAll('#consoleChips .chip').forEach(c => c.classList.remove('chip-active'));
    el.classList.add('chip-active');
    const inp = document.getElementById('consoleLabelInput');
    if (preset === '__custom__') {
        inp.classList.remove('cust-input-hidden');
        inp.focus();
        document.getElementById('previewBtnText').innerText = inp.value || 'SAFE';
    } else {
        inp.classList.add('cust-input-hidden');
        inp.value = preset;
        document.getElementById('previewBtnText').innerText = preset;
    }
}

function consoleSelectColor(hex, el) {
    document.querySelectorAll('.cust-colors .swatch').forEach(c => c.classList.remove('swatch-active'));
    el.classList.add('swatch-active');
    document.getElementById('consoleBtnColor').value = hex;
    const dark = adjustHex(hex, -30);
    document.getElementById('previewBtn').style.background = `linear-gradient(135deg, ${hex}, ${dark})`;
    if (el.classList.contains('swatch-picker')) el.style.background = hex;
}

function consoleSelectSize(val, el) {
    document.querySelectorAll('#consoleSizeChips .chip').forEach(c => c.classList.remove('chip-active'));
    el.classList.add('chip-active');
    document.getElementById('consoleBtnSize').value = val;
    const sizeMap = { small: '80px', medium: '110px', large: '140px' };
    const px = sizeMap[val];
    document.getElementById('previewBtn').style.width  = px;
    document.getElementById('previewBtn').style.height = px;
}

document.getElementById('consoleSoundToggle').addEventListener('change', function() {
    document.getElementById('consoleSoundLabel').innerText = this.checked ? 'On' : 'Off';
});
/* ── Custom H:M:S Interval Picker ───────────────────── */
function hmsGet() {
    const h = Math.max(0, parseInt(document.getElementById('hmsH').value) || 0);
    const m = Math.max(0, parseInt(document.getElementById('hmsM').value) || 0);
    const s = Math.max(0, parseInt(document.getElementById('hmsS').value) || 0);
    return { h, m, s };
}
function hmsSet(h, m, s) {
    document.getElementById('hmsH').value = String(h).padStart(2,'0');
    document.getElementById('hmsM').value = String(m).padStart(2,'0');
    document.getElementById('hmsS').value = String(s).padStart(2,'0');
}
function hmsClamp(el, min, max) {
    let v = parseInt(el.value);
    if (isNaN(v)) v = min;
    el.value = String(Math.max(min, Math.min(max, v))).padStart(2,'0');
    document.getElementById('intervalSaveBtn').classList.remove('saved');
    document.getElementById('intervalSaveBtn').textContent = '💾 Save Interval';
}
function hmsStep(field, dir) {
    const el = document.getElementById('hms' + field.toUpperCase());
    const max = (field === 'h') ? 23 : 59;
    let v = (parseInt(el.value) || 0) + dir;
    if (v < 0) v = max;
    if (v > max) v = 0;
    el.value = String(v).padStart(2,'0');
    document.getElementById('intervalSaveBtn').classList.remove('saved');
    document.getElementById('intervalSaveBtn').textContent = '💾 Save Interval';
}
function hmsSetPreset(h, m, s) {
    hmsSet(h, m, s);
    document.querySelectorAll('.hms-preset').forEach(b => b.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('intervalSaveBtn').classList.remove('saved');
    document.getElementById('intervalSaveBtn').textContent = '💾 Save Interval';
}
function hmsTotalMinutes() {
    // Convert H:M:S into total minutes (minimum 1, rounded up from seconds)
    const {h, m, s} = hmsGet();
    const totalSecs = (h * 3600) + (m * 60) + s;
    return Math.max(1, Math.ceil(totalSecs / 60));
}
function hmsFormatLabel(h, m, s) {
    const parts = [];
    if (h > 0) parts.push(h + 'h');
    if (m > 0) parts.push(m + 'm');
    if (s > 0) parts.push(s + 's');
    return parts.length ? parts.join(' ') : '0s';
}

function saveCustomInterval() {
    const {h, m, s} = hmsGet();
    const totalSecs  = (h * 3600) + (m * 60) + s;
    if (totalSecs < 1) {
        const toast = document.getElementById('intervalSaveToast');
        toast.textContent = '⚠️ Set at least 1 second';
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
        return;
    }
    const toast   = document.getElementById('intervalSaveToast');
    const saveBtn = document.getElementById('intervalSaveBtn');
    saveBtn.textContent = 'Saving...';
    saveBtn.disabled = true;

    // Send total seconds — backend stores seconds directly now
    fetch('/RIDERSAFE_Project/process/update_interval.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ping_interval=' + totalSecs
    })
    .then(r => r.text())
    .then(raw => {
        saveBtn.disabled = false;
        let data;
        try { data = JSON.parse(raw); }
        catch(e) {
            saveBtn.textContent = '💾 Save Interval';
            toast.textContent = '❌ Server error';
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 4000);
            return;
        }
        if (data.ok) {
            // Reset trip-start anchor so new interval begins from NOW
            localStorage.setItem(TRIP_START_KEY_CON, Date.now());
            Object.keys(localStorage).forEach(k => {
                if (k.startsWith('rs_timer_')) localStorage.removeItem(k);
            });
            saveBtn.textContent = '✅ Saved!';
            saveBtn.classList.add('saved');
            const label = hmsFormatLabel(h, m, s);
            toast.style.color = '#2ecc8a';
            toast.textContent = '✓ Set to ' + label + ' — reloading...';
            toast.classList.add('show');
            setTimeout(() => { window.location.reload(); }, 1400);
        } else {
            saveBtn.textContent = '💾 Save Interval';
            toast.style.color = '#e05252';
            toast.textContent = '❌ ' + (data.error || 'Save failed');
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 4000);
        }
    })
    .catch(err => {
        saveBtn.disabled = false;
        saveBtn.textContent = '💾 Save Interval';
        toast.style.color = '#e05252';
        toast.textContent = '❌ ' + err.message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 4000);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

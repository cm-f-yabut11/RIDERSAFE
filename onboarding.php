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

// Check if rider already has contacts — if so they've likely onboarded
$cq = $conn->prepare('SELECT COUNT(*) c FROM contact_links WHERE rider_id = ? AND status = \'accepted\'');
$cq->bind_param('i', $user_id); $cq->execute();
$contact_count = (int)$cq->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare('SELECT fullname FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id); $stmt->execute();
$firstname = explode(' ', $stmt->get_result()->fetch_assoc()['fullname'])[0];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="onboard-wrap">

    <!-- Progress bar -->
    <div class="ob-progress-track">
        <div class="ob-progress-fill" id="obProgress" style="width:33.3%"></div>
    </div>

    <!-- Step indicators -->
    <div class="ob-steps-row">
        <div class="ob-step-pill active" id="pill1">1 · Add Contact</div>
        <div class="ob-step-pill" id="pill2">2 · Set Interval</div>
        <div class="ob-step-pill" id="pill3">3 · Start Riding</div>
    </div>

    <!-- ── STEP 1: Add a contact ── -->
    <div class="ob-card" id="step1">
        <div class="ob-icon">👥</div>
        <h2>Welcome to RiderSafe, <?php echo htmlspecialchars($firstname); ?>! 👋</h2>
        <p class="ob-sub">Let's get you set up in 3 quick steps. First — add someone who'll watch over you.</p>

        <?php if ($contact_count > 0): ?>
        <div class="ob-already-done">✅ You already have <?php echo $contact_count; ?> trusted contact<?php echo $contact_count > 1 ? 's' : ''; ?> linked!</div>
        <?php endif; ?>

        <div class="ob-form-card">
            <label class="ob-label">Contact's Email Address</label>
            <div style="display:flex;gap:8px;">
                <input type="email" id="obContactEmail" placeholder="contact@email.com"
                    style="flex:1;padding:11px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.14);background:rgba(255,255,255,0.07);color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;outline:none;">
                <button onclick="obAddContact()" class="btn btn-teal" style="padding:11px 18px;white-space:nowrap;">Add →</button>
            </div>
            <p id="obContactMsg" style="font-size:12px;font-weight:700;margin-top:10px;display:none;"></p>
            <p style="font-size:12px;color:rgba(255,255,255,0.3);margin-top:8px;">They must have a Contact account on RiderSafe.</p>
        </div>

        <div class="ob-nav">
            <span></span>
            <button onclick="obGo(2)" class="btn btn-primary">Next: Set Interval →</button>
        </div>
    </div>

    <!-- ── STEP 2: Set interval ── -->
    <div class="ob-card" id="step2" style="display:none;">
        <div class="ob-icon">⏱️</div>
        <h2>How often should we check on you?</h2>
        <p class="ob-sub">Choose how frequently the app will ask you to confirm you're safe.</p>

        <div class="ob-form-card">
            <div class="ob-interval-row">
                <div class="ob-hms">
                    <button onclick="obHmsStep('h',1)">▲</button>
                    <input type="number" id="obH" value="0" min="0" max="23" class="ob-hms-input">
                    <button onclick="obHmsStep('h',-1)">▼</button>
                    <span>hr</span>
                </div>
                <span class="ob-colon">:</span>
                <div class="ob-hms">
                    <button onclick="obHmsStep('m',1)">▲</button>
                    <input type="number" id="obM" value="15" min="0" max="59" class="ob-hms-input">
                    <button onclick="obHmsStep('m',-1)">▼</button>
                    <span>min</span>
                </div>
                <span class="ob-colon">:</span>
                <div class="ob-hms">
                    <button onclick="obHmsStep('s',1)">▲</button>
                    <input type="number" id="obS" value="0" min="0" max="59" class="ob-hms-input">
                    <button onclick="obHmsStep('s',-1)">▼</button>
                    <span>sec</span>
                </div>
            </div>
            <div class="ob-presets">
                <button onclick="obSetPreset(0,0,30)">30s</button>
                <button onclick="obSetPreset(0,1,0)">1m</button>
                <button onclick="obSetPreset(0,5,0)">5m</button>
                <button onclick="obSetPreset(0,15,0)" class="ob-preset-active">15m</button>
                <button onclick="obSetPreset(0,30,0)">30m</button>
                <button onclick="obSetPreset(1,0,0)">1h</button>
            </div>
            <p style="font-size:12px;color:rgba(255,255,255,0.3);margin-top:12px;">💡 For real rides, 15–30 min is recommended. Use 30s for testing.</p>
        </div>

        <div class="ob-nav">
            <button onclick="obGo(1)" class="btn btn-secondary">← Back</button>
            <button onclick="obSaveInterval()" class="btn btn-primary" id="obSaveIntBtn">Save & Continue →</button>
        </div>
    </div>

    <!-- ── STEP 3: Done ── -->
    <div class="ob-card" id="step3" style="display:none;text-align:center;">
        <div class="ob-icon">🏍️</div>
        <h2>You're all set!</h2>
        <p class="ob-sub" style="max-width:400px;margin:0 auto 28px;">Head to the Rider Console to start your first trip, then use the Safety Button to check in. Stay safe out there!</p>

        <div style="display:flex;flex-direction:column;gap:12px;max-width:320px;margin:0 auto;">
            <a href="/RIDERSAFE_Project/rider_page.php" class="btn btn-primary" style="justify-content:center;text-align:center;padding:14px;">
                🏍️ Open Rider Console → Start Trip
            </a>
            <a href="/RIDERSAFE_Project/rider_home.php" class="btn btn-secondary" style="justify-content:center;text-align:center;">
                📊 Go to Dashboard
            </a>
        </div>

        <p style="font-size:12px;color:rgba(255,255,255,0.25);margin-top:24px;">You can revisit this setup anytime from your Profile page.</p>
    </div>

</div>

<style>
.onboard-wrap { max-width:600px; margin:0 auto; padding:40px 20px 80px; }
.ob-progress-track { height:4px; background:rgba(255,255,255,0.08); border-radius:2px; margin-bottom:18px; }
.ob-progress-fill  { height:100%; background:linear-gradient(90deg,var(--teal,#2a6b8a),#2ecc8a); border-radius:2px; transition:width 0.5s ease; }
.ob-steps-row { display:flex; gap:8px; margin-bottom:32px; }
.ob-step-pill { flex:1; text-align:center; padding:8px 10px; border-radius:10px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.04); font-size:12px; font-weight:700; color:rgba(255,255,255,0.35); transition:all 0.3s; }
.ob-step-pill.active { background:rgba(46,204,138,0.15); border-color:rgba(46,204,138,0.4); color:#2ecc8a; }
.ob-card { animation:obFade 0.35s ease; }
@keyframes obFade { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
.ob-icon { font-size:52px; text-align:center; margin-bottom:16px; }
.ob-card h2 { font-family:'Syne',sans-serif; font-size:24px; font-weight:900; text-align:center; margin:0 0 10px; }
.ob-sub { font-size:14px; color:rgba(255,255,255,0.55); text-align:center; line-height:1.6; margin:0 0 28px; }
.ob-label { display:block; font-size:11px; font-weight:800; color:rgba(255,255,255,0.4); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:10px; }
.ob-form-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.09); border-radius:16px; padding:22px; margin-bottom:24px; }
.ob-already-done { background:rgba(46,204,138,0.12); border:1px solid rgba(46,204,138,0.25); color:#2ecc8a; border-radius:10px; padding:10px 16px; font-size:13px; font-weight:700; margin-bottom:16px; text-align:center; }
.ob-nav { display:flex; justify-content:space-between; align-items:center; }
.ob-interval-row { display:flex; align-items:center; gap:8px; justify-content:center; margin-bottom:16px; }
.ob-hms { display:flex; flex-direction:column; align-items:center; gap:4px; }
.ob-hms button { width:32px; height:20px; border:none; background:rgba(255,255,255,0.07); color:rgba(255,255,255,0.5); border-radius:5px; cursor:pointer; font-size:10px; transition:background 0.15s; }
.ob-hms button:hover { background:rgba(46,204,138,0.2); color:#2ecc8a; }
.ob-hms-input { width:52px; height:40px; background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.12); border-radius:9px; color:#2ecc8a; font-family:'Syne',sans-serif; font-size:20px; font-weight:900; text-align:center; outline:none; -moz-appearance:textfield; }
.ob-hms-input::-webkit-outer-spin-button,.ob-hms-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}
.ob-hms span { font-size:10px; color:rgba(255,255,255,0.3); font-weight:700; text-transform:uppercase; }
.ob-colon { font-size:24px; font-weight:900; color:rgba(255,255,255,0.2); padding-bottom:14px; }
.ob-presets { display:flex; gap:6px; flex-wrap:wrap; justify-content:center; }
.ob-presets button { padding:5px 13px; border-radius:20px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.05); color:rgba(255,255,255,0.5); font-size:12px; font-weight:700; cursor:pointer; transition:all 0.15s; font-family:'Plus Jakarta Sans',sans-serif; }
.ob-presets button:hover,.ob-preset-active { background:rgba(46,204,138,0.15)!important; border-color:rgba(46,204,138,0.4)!important; color:#2ecc8a!important; }
</style>

<script>
function obGo(step) {
    document.querySelectorAll('.ob-card').forEach(c => c.style.display = 'none');
    document.getElementById('step' + step).style.display = 'block';
    document.querySelectorAll('.ob-step-pill').forEach((p,i) => p.classList.toggle('active', i < step));
    document.getElementById('obProgress').style.width = (step / 3 * 100) + '%';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function obAddContact() {
    const email = document.getElementById('obContactEmail').value.trim();
    const msg   = document.getElementById('obContactMsg');
    if (!email) return;
    fetch('/RIDERSAFE_Project/process/ob_add_contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'contact_email=' + encodeURIComponent(email)
    })
    .then(r => r.json())
    .then(d => {
        msg.style.display = 'block';
        msg.style.color = d.ok ? '#2ecc8a' : '#e05252';
        msg.textContent = d.message;
        if (d.ok) document.getElementById('obContactEmail').value = '';
    });
}

function obHmsStep(field, dir) {
    const el  = document.getElementById('ob' + field.toUpperCase());
    const max = field === 'h' ? 23 : 59;
    let v = (parseInt(el.value) || 0) + dir;
    if (v < 0) v = max; if (v > max) v = 0;
    el.value = String(v).padStart(2,'0');
}
function obSetPreset(h,m,s) {
    document.getElementById('obH').value = String(h).padStart(2,'0');
    document.getElementById('obM').value = String(m).padStart(2,'0');
    document.getElementById('obS').value = String(s).padStart(2,'0');
    document.querySelectorAll('.ob-presets button').forEach(b => b.classList.remove('ob-preset-active'));
    event.target.classList.add('ob-preset-active');
}

function obSaveInterval() {
    const h = parseInt(document.getElementById('obH').value)||0;
    const m = parseInt(document.getElementById('obM').value)||0;
    const s = parseInt(document.getElementById('obS').value)||0;
    const total = h*3600 + m*60 + s;
    if (total < 1) { alert('Please set at least 1 second.'); return; }
    const btn = document.getElementById('obSaveIntBtn');
    btn.textContent = 'Saving...'; btn.disabled = true;
    fetch('/RIDERSAFE_Project/process/update_interval.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ping_interval=' + total
    })
    .then(r => r.json())
    .then(d => {
        btn.textContent = 'Save & Continue →'; btn.disabled = false;
        if (d.ok) obGo(3);
        else alert('Error saving interval: ' + (d.error || 'Unknown error'));
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

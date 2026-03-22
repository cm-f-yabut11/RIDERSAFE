<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /RIDERSAFE_Project/login.php'); exit();
}

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT fullname, email, account_type, created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$is_rider = $user['account_type'] === 'rider';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="page-body">
<div class="dashboard">

    <h1 class="dash-title">My Profile</h1>

    <!-- ── Account Info + Quick Links ── -->
    <div class="dash-grid" style="margin-bottom:24px;">
        <div class="dash-card">
            <div class="card-icon">👤</div>
            <h3>Account Information</h3>
            <div class="prof-info-row"><span class="prof-key">Name</span><span class="prof-val"><?php echo htmlspecialchars($user['fullname']); ?></span></div>
            <div class="prof-info-row"><span class="prof-key">Email</span><span class="prof-val"><?php echo htmlspecialchars($user['email']); ?></span></div>
            <div class="prof-info-row"><span class="prof-key">Role</span>
                <span class="badge <?php echo $is_rider ? 'badge-active' : 'badge-safe'; ?>" style="font-size:11px;">
                    <?php echo $is_rider ? '🏍️ Rider' : '👤 Contact'; ?>
                </span>
            </div>
            <div class="prof-info-row"><span class="prof-key">Member Since</span><span class="prof-val"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span></div>
        </div>
        <div class="dash-card">
            <div class="card-icon">🔗</div>
            <h3>Quick Links</h3>
            <?php if ($is_rider): ?>
                <a href="/RIDERSAFE_Project/rider_home.php"  class="btn btn-primary  full" style="margin-bottom:10px;">🏠 Dashboard</a>
                <a href="/RIDERSAFE_Project/rider_page.php"  class="btn btn-teal     full" style="margin-bottom:10px;">🏍️ Rider Console</a>
                <a href="/RIDERSAFE_Project/button_page.php" class="btn btn-secondary full" style="margin-bottom:10px;">✅ Safety Button</a>
            <?php else: ?>
                <a href="/RIDERSAFE_Project/contact_home.php" class="btn btn-primary full" style="margin-bottom:10px;">🏠 Dashboard</a>
                <a href="/RIDERSAFE_Project/contact_page.php" class="btn btn-teal    full" style="margin-bottom:10px;">📊 Monitoring View</a>
            <?php endif; ?>
            <a href="/RIDERSAFE_Project/process/logout.php" class="btn btn-secondary full" style="margin-top:4px;">🚪 Logout</a>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TUTORIAL SECTION
    ═══════════════════════════════════════════════════════ -->
    <div class="tut-header">
        <span class="tut-badge">📖 How It Works</span>
        <h2>RiderSafe Tutorial</h2>
        <p>Everything you need to know to stay safe on every ride.</p>
    </div>

    <?php if ($is_rider): ?>
    <!-- ── RIDER TUTORIAL ── -->

    <!-- Progress bar -->
    <div class="tut-progress-wrap">
        <div class="tut-progress-bar" id="tutProgress"></div>
    </div>
    <p class="tut-progress-label" id="tutProgressLabel">Step 1 of 5</p>

    <div class="tut-steps" id="tutSteps">

        <!-- Step 1 -->
        <div class="tut-step active" data-step="1">
            <div class="tut-step-head">
                <div class="tut-step-num">1</div>
                <div>
                    <h3>Add a Trusted Contact</h3>
                    <p class="tut-sub">Your contacts receive alerts when you don't check in or when you trigger SOS.</p>
                </div>
            </div>
            <div class="tut-step-body">
                <div class="tut-step-visual">
                    <div class="tut-mock-card">
                        <div class="tut-mock-label">➕ Add Contact by Email</div>
                        <div class="tut-mock-field">
                            <span>contact@email.com</span>
                            <div class="tut-mock-btn">Add</div>
                        </div>
                        <div class="tut-mock-note">The person must have a Contact account on RiderSafe.</div>
                    </div>
                </div>
                <div class="tut-step-info">
                    <div class="tut-info-item">
                        <span class="tut-info-icon">1️⃣</span>
                        <div>Go to <strong>Rider Console</strong> → Trusted Contacts section.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">2️⃣</span>
                        <div>Enter your contact's email address and press <strong>Add</strong>.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">3️⃣</span>
                        <div>They must already have a <strong>Contact</strong> account registered on RiderSafe.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">✅</span>
                        <div>Once linked, they'll receive all your safety pings and SOS alerts automatically.</div>
                    </div>
                    <a href="/RIDERSAFE_Project/rider_page.php" class="tut-action-btn">Go to Rider Console →</a>
                </div>
            </div>
        </div>

        <!-- Step 2 -->
        <div class="tut-step" data-step="2">
            <div class="tut-step-head">
                <div class="tut-step-num">2</div>
                <div>
                    <h3>Set Your Check-in Interval</h3>
                    <p class="tut-sub">Choose how often the app reminds you to confirm you're safe during a ride.</p>
                </div>
            </div>
            <div class="tut-step-body">
                <div class="tut-step-visual">
                    <div class="tut-mock-card">
                        <div class="tut-mock-label">⏱️ Check-in Interval</div>
                        <div class="tut-hms-demo">
                            <div class="tut-hms-unit"><span class="tut-hms-val">00</span><span class="tut-hms-lbl">hr</span></div>
                            <span class="tut-hms-sep">:</span>
                            <div class="tut-hms-unit"><span class="tut-hms-val">15</span><span class="tut-hms-lbl">min</span></div>
                            <span class="tut-hms-sep">:</span>
                            <div class="tut-hms-unit"><span class="tut-hms-val">00</span><span class="tut-hms-lbl">sec</span></div>
                        </div>
                        <div class="tut-mock-presets">
                            <span>30s</span><span>1m</span><span class="active">15m</span><span>30m</span><span>1h</span>
                        </div>
                    </div>
                </div>
                <div class="tut-step-info">
                    <div class="tut-info-item">
                        <span class="tut-info-icon">⏱️</span>
                        <div>Set hours, minutes, and seconds for your preferred check-in frequency.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">🧪</span>
                        <div>Use <strong>30s</strong> or <strong>1m</strong> presets for testing — great for demos!</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">💡</span>
                        <div>For real rides, <strong>15–30 minutes</strong> is recommended to avoid distraction.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">💾</span>
                        <div>Press <strong>Save Interval</strong> — the timer updates immediately on all pages.</div>
                    </div>
                    <a href="/RIDERSAFE_Project/rider_page.php" class="tut-action-btn">Open Rider Console →</a>
                </div>
            </div>
        </div>

        <!-- Step 3 -->
        <div class="tut-step" data-step="3">
            <div class="tut-step-head">
                <div class="tut-step-num">3</div>
                <div>
                    <h3>Start Your Trip</h3>
                    <p class="tut-sub">Activating the trip starts the safety monitoring system for your contacts.</p>
                </div>
            </div>
            <div class="tut-step-body">
                <div class="tut-step-visual">
                    <div class="tut-mock-card">
                        <div class="tut-mock-label">🏍️ Trip Controller</div>
                        <div class="tut-mock-status inactive">⚪ No active trip</div>
                        <div class="tut-mock-tripbtn" id="tutTripBtn" onclick="tutAnimateTrip()">START TRIP</div>
                        <div class="tut-mock-note" id="tutTripNote">Your contacts are not being tracked.</div>
                    </div>
                </div>
                <div class="tut-step-info">
                    <div class="tut-info-item">
                        <span class="tut-info-icon">🟢</span>
                        <div>Press <strong>START TRIP</strong> in the Rider Console before you ride.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">⏱️</span>
                        <div>The countdown timer begins immediately — visible on the Dashboard and Safety Button page.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">🔄</span>
                        <div>The timer <strong>persists across page navigation and reloads</strong> — it never resets unless you stop the trip.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">🛑</span>
                        <div>Press <strong>END TRIP</strong> when you arrive safely — this stops all monitoring.</div>
                    </div>
                    <a href="/RIDERSAFE_Project/rider_page.php" class="tut-action-btn">Go to Rider Console →</a>
                </div>
            </div>
        </div>

        <!-- Step 4 -->
        <div class="tut-step" data-step="4">
            <div class="tut-step-head">
                <div class="tut-step-num">4</div>
                <div>
                    <h3>Tap the Safety Button</h3>
                    <p class="tut-sub">This is your main check-in action. Tap it every time the timer runs out to confirm you're okay.</p>
                </div>
            </div>
            <div class="tut-step-body">
                <div class="tut-step-visual">
                    <div class="tut-mock-card tut-btn-demo-card">
                        <div class="tut-big-btn" id="tutSafeBtn" onclick="tutAnimateBtn(this)">SAFE</div>
                        <div class="tut-btn-hint">Tap to confirm · Hold 5s for SOS</div>
                        <div class="tut-countdown-demo">
                            <span class="tut-cd-label">Next check-in in</span>
                            <span class="tut-cd-time" id="tutCdTime">14:58</span>
                        </div>
                    </div>
                </div>
                <div class="tut-step-info">
                    <div class="tut-info-item">
                        <span class="tut-info-icon">✅</span>
                        <div><strong>Short tap</strong> = "I'm safe!" — sends a confirmed ping to your contacts.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">🚨</span>
                        <div><strong>Hold 5 seconds</strong> = SOS — immediately alerts all contacts with your GPS location.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">⚠️</span>
                        <div>If you miss a check-in, your contacts are automatically notified after one full interval.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">🎨</span>
                        <div>You can customize the button label, color, size, and press effect anytime.</div>
                    </div>
                    <a href="/RIDERSAFE_Project/button_page.php" class="tut-action-btn">Open Safety Button →</a>
                </div>
            </div>
        </div>

        <!-- Step 5 -->
        <div class="tut-step" data-step="5">
            <div class="tut-step-head">
                <div class="tut-step-num">5</div>
                <div>
                    <h3>What Happens if You Miss a Check-in?</h3>
                    <p class="tut-sub">Understanding the missed ping flow keeps your contacts informed and panic-free.</p>
                </div>
            </div>
            <div class="tut-step-body">
                <div class="tut-step-visual">
                    <div class="tut-mock-card">
                        <div class="tut-flow">
                            <div class="tut-flow-step green">✅ Ping due — tap SAFE</div>
                            <div class="tut-flow-arrow">↓ missed</div>
                            <div class="tut-flow-step orange">⏳ Grace period begins</div>
                            <div class="tut-flow-arrow">↓ still no response</div>
                            <div class="tut-flow-step red">🔴 Contacts notified: MISSED</div>
                            <div class="tut-flow-arrow">↓ next cycle starts</div>
                            <div class="tut-flow-step green">🔁 Timer resets</div>
                        </div>
                    </div>
                </div>
                <div class="tut-step-info">
                    <div class="tut-info-item">
                        <span class="tut-info-icon">🔔</span>
                        <div>When the timer hits zero, a <strong>ping due alert</strong> appears on your screen with a sound.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">⏳</span>
                        <div>You have one full interval as a <strong>grace period</strong> to tap SAFE before it's counted as missed.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">📲</span>
                        <div>Your contacts receive a <strong>"Missed Check-in"</strong> notification and can take action.</div>
                    </div>
                    <div class="tut-info-item">
                        <span class="tut-info-icon">🔁</span>
                        <div>The timer automatically resets and the cycle begins again — monitoring continues.</div>
                    </div>
                    <a href="/RIDERSAFE_Project/rider_home.php" class="tut-action-btn">Go to Dashboard →</a>
                </div>
            </div>
        </div>

    </div><!-- /tut-steps -->

    <!-- Step navigation -->
    <div class="tut-nav">
        <button class="tut-nav-btn" id="tutPrev" onclick="tutNav(-1)" disabled>← Previous</button>
        <div class="tut-dots" id="tutDots">
            <span class="tut-dot active" onclick="tutGoTo(1)"></span>
            <span class="tut-dot" onclick="tutGoTo(2)"></span>
            <span class="tut-dot" onclick="tutGoTo(3)"></span>
            <span class="tut-dot" onclick="tutGoTo(4)"></span>
            <span class="tut-dot" onclick="tutGoTo(5)"></span>
        </div>
        <button class="tut-nav-btn tut-nav-next" id="tutNext" onclick="tutNav(1)">Next →</button>
    </div>

    <?php else: ?>
    <!-- ── CONTACT TUTORIAL ── -->
    <div class="tut-steps tut-contact-grid">

        <div class="tut-step tut-contact-step active">
            <div class="tut-step-head">
                <div class="tut-step-num">1</div>
                <div><h3>Getting Linked to a Rider</h3>
                <p class="tut-sub">A rider must add your email from their Rider Console.</p></div>
            </div>
            <div class="tut-contact-body">
                <div class="tut-info-item"><span class="tut-info-icon">📧</span><div>Make sure the rider uses the same email you registered with on RiderSafe.</div></div>
                <div class="tut-info-item"><span class="tut-info-icon">🔗</span><div>Once linked, you'll appear in their Trusted Contacts list immediately.</div></div>
                <div class="tut-info-item"><span class="tut-info-icon">✅</span><div>No confirmation needed — the link is accepted automatically.</div></div>
            </div>
        </div>

        <div class="tut-step tut-contact-step active">
            <div class="tut-step-head">
                <div class="tut-step-num">2</div>
                <div><h3>Understanding Notifications</h3>
                <p class="tut-sub">You receive three types of alerts from your rider.</p></div>
            </div>
            <div class="tut-contact-body">
                <div class="tut-info-item"><span class="tut-info-icon">🟢</span><div><strong>Safe ping</strong> — rider confirmed they're okay at their check-in time.</div></div>
                <div class="tut-info-item"><span class="tut-info-icon">🔴</span><div><strong>Missed ping</strong> — rider did not respond within the grace period. Check on them.</div></div>
                <div class="tut-info-item"><span class="tut-info-icon">🚨</span><div><strong>SOS alert</strong> — rider manually triggered emergency. Act immediately.</div></div>
            </div>
        </div>

        <div class="tut-step tut-contact-step active">
            <div class="tut-step-head">
                <div class="tut-step-num">3</div>
                <div><h3>Using the Monitoring Dashboard</h3>
                <p class="tut-sub">Your full view of your rider's safety status in real time.</p></div>
            </div>
            <div class="tut-contact-body">
                <div class="tut-info-item"><span class="tut-info-icon">📍</span><div>See the rider's <strong>last known GPS location</strong> on a live map.</div></div>
                <div class="tut-info-item"><span class="tut-info-icon">📊</span><div>View ping history — every Safe, Missed, and SOS event is logged with timestamps.</div></div>
                <div class="tut-info-item"><span class="tut-info-icon">📩</span><div>Send a <strong>manual check-in request</strong> to your rider's notification feed if you're worried.</div></div>
                <a href="/RIDERSAFE_Project/contact_page.php" class="tut-action-btn" style="margin-top:14px;display:inline-block;">Open Monitoring Dashboard →</a>
            </div>
        </div>

        <div class="tut-step tut-contact-step active">
            <div class="tut-step-head">
                <div class="tut-step-num">4</div>
                <div><h3>Emergency Actions</h3>
                <p class="tut-sub">What to do if your rider misses a check-in or sends SOS.</p></div>
            </div>
            <div class="tut-contact-body">
                <div class="tut-info-item"><span class="tut-info-icon">📞</span><div>Use the <strong>Call Rider</strong> button to call them directly from the dashboard.</div></div>
                <div class="tut-info-item"><span class="tut-info-icon">💬</span><div>Send an <strong>SMS</strong> directly from the emergency actions panel.</div></div>
                <div class="tut-info-item"><span class="tut-info-icon">🗺️</span><div>Open their <strong>last GPS location</strong> in Google Maps and share with authorities if needed.</div></div>
            </div>
        </div>

    </div>
    <?php endif; ?>
    <!-- ════════════════════════════════════════════════════ -->

    <!-- ── Danger Zone: Account Deletion ── -->
    <div class="dash-card" style="border:1px solid rgba(224,82,82,0.3);margin-top:8px;">
        <div class="card-icon">⚠️</div>
        <h3 style="color:var(--red);">Danger Zone</h3>
        <p style="font-size:13px;color:rgba(255,255,255,0.5);margin-bottom:16px;line-height:1.6;">
            Permanently delete your account and all associated data — pings, contacts, notifications, and settings.
            <strong style="color:rgba(255,255,255,0.75);">This cannot be undone.</strong>
        </p>
        <button onclick="openDeleteModal()" class="btn" style="background:rgba(224,82,82,0.12);border:1px solid rgba(224,82,82,0.35);color:var(--red);padding:10px 22px;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:background 0.2s;">
            🗑️ Delete My Account
        </button>
    </div>

</div><!-- /dashboard -->
</div><!-- /page-body -->

<!-- ── Delete Account Modal ── -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center;padding:20px;box-sizing:border-box;">
    <div style="background:#1a2233;border:1px solid rgba(224,82,82,0.3);border-radius:22px;max-width:420px;width:100%;padding:32px 28px;box-shadow:0 24px 60px rgba(0,0,0,0.5);">
        <div style="font-size:44px;text-align:center;margin-bottom:16px;">🗑️</div>
        <h3 style="font-family:'Syne',sans-serif;font-size:20px;font-weight:900;color:#e05252;text-align:center;margin:0 0 10px;">Delete Account</h3>
        <p style="font-size:13px;color:rgba(255,255,255,0.55);text-align:center;line-height:1.6;margin:0 0 22px;">
            This will permanently erase your account, all pings, contacts, and notifications.<br>
            Type <strong style="color:white;">DELETE</strong> to confirm.
        </p>
        <input type="text" id="deleteConfirmInput" placeholder='Type "DELETE" to confirm'
            style="width:100%;padding:11px 14px;border-radius:10px;border:1px solid rgba(224,82,82,0.4);background:rgba(224,82,82,0.08);color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;outline:none;box-sizing:border-box;text-align:center;letter-spacing:0.05em;"
            oninput="document.getElementById('deleteConfirmBtn').disabled = this.value !== 'DELETE'; document.getElementById('deleteConfirmBtn').style.opacity = this.value === 'DELETE' ? '1' : '0.4';">
        <div style="display:flex;gap:10px;margin-top:18px;">
            <button onclick="closeDeleteModal()" class="btn btn-secondary" style="flex:1;justify-content:center;">✕ Cancel</button>
            <button id="deleteConfirmBtn" onclick="submitDeleteAccount()" disabled
                style="flex:1;padding:11px;border-radius:10px;border:1px solid rgba(224,82,82,0.4);background:rgba(224,82,82,0.15);color:#e05252;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:800;cursor:pointer;opacity:0.4;">
                🗑️ Confirm Delete
            </button>
        </div>
    </div>
</div>

<style>
#deleteModal.open { display: flex !important; }
</style>
<script>
function openDeleteModal() {
    document.getElementById('deleteModal').classList.add('open');
    document.getElementById('deleteConfirmInput').value = '';
    document.getElementById('deleteConfirmBtn').disabled = true;
    document.getElementById('deleteConfirmBtn').style.opacity = '0.4';
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('open'); }
document.getElementById('deleteModal').addEventListener('click', function(e) { if (e.target === this) closeDeleteModal(); });
function submitDeleteAccount() {
    fetch('/RIDERSAFE_Project/process/delete_account.php', { method:'POST' })
    .then(r => r.json())
    .then(d => {
        if (d.ok) { window.location.href = '/RIDERSAFE_Project/landing.php'; }
        else { alert('Error: ' + (d.error || 'Could not delete account.')); }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>

<style>
/* ── Profile info rows ── */
.prof-info-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.07); }
.prof-info-row:last-child { border-bottom:none; }
.prof-key { font-size:12px; color:rgba(255,255,255,0.45); font-weight:600; }
.prof-val { font-size:13px; font-weight:700; }

/* ── Tutorial header ── */
.tut-header { text-align:center; margin:32px 0 24px; }
.tut-badge { display:inline-block; background:rgba(245,166,35,0.15); color:var(--orange); border:1px solid rgba(245,166,35,0.3); border-radius:50px; padding:4px 16px; font-size:12px; font-weight:800; letter-spacing:1px; margin-bottom:12px; }
.tut-header h2 { font-family:'Syne',sans-serif; font-size:26px; font-weight:800; margin-bottom:8px; }
.tut-header p  { color:rgba(255,255,255,0.5); font-size:14px; font-weight:500; }

/* ── Progress ── */
.tut-progress-wrap { height:4px; background:rgba(255,255,255,0.08); border-radius:2px; margin-bottom:8px; overflow:hidden; }
.tut-progress-bar  { height:100%; background:linear-gradient(90deg,var(--teal),var(--teal-light)); border-radius:2px; transition:width 0.4s ease; width:20%; }
.tut-progress-label { text-align:right; font-size:11px; font-weight:700; color:rgba(255,255,255,0.3); margin-bottom:20px; }

/* ── Step cards ── */
.tut-steps { display:block; }
.tut-step { display:none; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.09); border-radius:20px; padding:28px; margin-bottom:16px; animation:tutFadeIn 0.35s ease; }
.tut-step.active { display:block; }
@keyframes tutFadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }

.tut-step-head { display:flex; align-items:flex-start; gap:16px; margin-bottom:24px; }
.tut-step-num { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,var(--teal),var(--teal-light)); display:flex; align-items:center; justify-content:center; font-family:'Syne',sans-serif; font-size:18px; font-weight:900; color:white; flex-shrink:0; }
.tut-step-head h3 { font-family:'Syne',sans-serif; font-size:18px; font-weight:800; margin:0 0 4px; }
.tut-sub { font-size:13px; color:rgba(255,255,255,0.5); font-weight:500; margin:0; line-height:1.5; }

.tut-step-body { display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start; }
@media(max-width:700px){ .tut-step-body { grid-template-columns:1fr; } }

/* ── Visual mock cards ── */
.tut-step-visual {}
.tut-mock-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:14px; padding:18px; }
.tut-mock-label { font-size:12px; font-weight:800; color:rgba(255,255,255,0.45); margin-bottom:12px; letter-spacing:0.5px; }
.tut-mock-field { display:flex; gap:8px; align-items:center; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:10px 12px; margin-bottom:8px; }
.tut-mock-field span { flex:1; font-size:12px; color:rgba(255,255,255,0.3); }
.tut-mock-btn { background:var(--teal); color:white; font-size:11px; font-weight:800; padding:4px 12px; border-radius:6px; }
.tut-mock-note { font-size:11px; color:rgba(255,255,255,0.25); margin-top:8px; line-height:1.5; }
.tut-mock-status { font-size:13px; font-weight:700; padding:8px 12px; border-radius:8px; margin:10px 0; text-align:center; }
.tut-mock-status.inactive { background:rgba(255,255,255,0.05); color:rgba(255,255,255,0.4); }
.tut-mock-status.active   { background:rgba(46,204,138,0.15); color:var(--green); }
.tut-mock-tripbtn { background:linear-gradient(135deg,var(--teal),var(--teal-light)); color:white; font-family:'Syne',sans-serif; font-size:14px; font-weight:800; text-align:center; padding:12px; border-radius:10px; cursor:pointer; transition:transform 0.15s; }
.tut-mock-tripbtn:hover { transform:scale(1.02); }
.tut-mock-presets { display:flex; gap:6px; flex-wrap:wrap; margin-top:10px; }
.tut-mock-presets span { padding:3px 10px; border-radius:20px; border:1px solid rgba(255,255,255,0.12); background:rgba(255,255,255,0.05); color:rgba(255,255,255,0.45); font-size:11px; font-weight:700; }
.tut-mock-presets span.active { background:rgba(46,204,138,0.2); border-color:rgba(46,204,138,0.45); color:#2ecc8a; }

/* H:M:S mock */
.tut-hms-demo { display:flex; align-items:center; gap:6px; margin:10px 0; justify-content:center; }
.tut-hms-unit { display:flex; flex-direction:column; align-items:center; gap:2px; }
.tut-hms-val { font-family:'Syne',sans-serif; font-size:22px; font-weight:900; color:#2ecc8a; background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.12); border-radius:8px; width:44px; text-align:center; padding:4px 0; }
.tut-hms-lbl { font-size:10px; color:rgba(255,255,255,0.3); font-weight:700; text-transform:uppercase; }
.tut-hms-sep { font-size:20px; font-weight:800; color:rgba(255,255,255,0.25); align-self:center; }

/* Big button demo */
.tut-btn-demo-card { text-align:center; }
.tut-big-btn { width:110px; height:110px; border-radius:50%; background:linear-gradient(135deg,#2ecc8a,#1aaa6e); display:flex; align-items:center; justify-content:center; font-family:'Syne',sans-serif; font-size:18px; font-weight:900; color:white; margin:10px auto; cursor:pointer; box-shadow:0 0 0 12px rgba(46,204,138,0.12),0 6px 24px rgba(46,204,138,0.3); transition:transform 0.15s; }
.tut-big-btn:hover { transform:scale(1.05); }
.tut-big-btn.pressed { transform:scale(0.88); background:linear-gradient(135deg,#1aaa6e,#0d8a56); }
.tut-btn-hint { font-size:11px; color:rgba(255,255,255,0.3); font-weight:600; margin-bottom:10px; }
.tut-countdown-demo { background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.2); border-radius:10px; padding:8px 14px; display:inline-flex; gap:10px; align-items:center; }
.tut-cd-label { font-size:11px; color:rgba(255,255,255,0.4); font-weight:600; }
.tut-cd-time  { font-family:'Syne',sans-serif; font-size:18px; font-weight:900; color:#60a5fa; }

/* Flow diagram */
.tut-flow { display:flex; flex-direction:column; gap:0; }
.tut-flow-step { padding:8px 14px; border-radius:8px; font-size:12px; font-weight:700; text-align:center; }
.tut-flow-step.green  { background:rgba(46,204,138,0.15); color:#2ecc8a; border:1px solid rgba(46,204,138,0.25); }
.tut-flow-step.orange { background:rgba(245,166,35,0.15); color:var(--orange); border:1px solid rgba(245,166,35,0.25); }
.tut-flow-step.red    { background:rgba(224,82,82,0.15); color:#e05252; border:1px solid rgba(224,82,82,0.25); }
.tut-flow-arrow { text-align:center; font-size:11px; color:rgba(255,255,255,0.25); font-weight:600; padding:4px 0; }

/* ── Info items ── */
.tut-step-info {}
.tut-info-item { display:flex; gap:12px; align-items:flex-start; margin-bottom:14px; }
.tut-info-icon { font-size:18px; flex-shrink:0; margin-top:1px; }
.tut-info-item div { font-size:13px; color:rgba(255,255,255,0.65); font-weight:500; line-height:1.6; }
.tut-info-item strong { color:white; font-weight:700; }
.tut-action-btn { display:inline-block; margin-top:6px; padding:9px 20px; background:rgba(46,204,138,0.12); border:1px solid rgba(46,204,138,0.3); color:#2ecc8a; border-radius:10px; font-size:13px; font-weight:700; text-decoration:none; transition:background 0.2s; }
.tut-action-btn:hover { background:rgba(46,204,138,0.22); }

/* ── Step navigation ── */
.tut-nav { display:flex; align-items:center; justify-content:space-between; margin-top:20px; padding:16px 0; }
.tut-nav-btn { padding:10px 22px; border-radius:10px; border:1px solid rgba(255,255,255,0.14); background:rgba(255,255,255,0.06); color:rgba(255,255,255,0.7); font-family:'Plus Jakarta Sans',sans-serif; font-size:13px; font-weight:700; cursor:pointer; transition:background 0.2s, color 0.2s; }
.tut-nav-btn:hover:not(:disabled) { background:rgba(255,255,255,0.12); color:white; }
.tut-nav-btn:disabled { opacity:0.3; cursor:not-allowed; }
.tut-nav-next { background:rgba(46,204,138,0.12); border-color:rgba(46,204,138,0.3); color:#2ecc8a; }
.tut-nav-next:hover:not(:disabled) { background:rgba(46,204,138,0.22); }
.tut-dots { display:flex; gap:8px; }
.tut-dot { width:10px; height:10px; border-radius:50%; background:rgba(255,255,255,0.15); cursor:pointer; transition:background 0.2s, transform 0.2s; }
.tut-dot.active { background:#2ecc8a; transform:scale(1.3); }

/* ── Contact tutorial grid ── */
.tut-contact-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.tut-contact-step { display:block !important; }
.tut-contact-body { margin-top:16px; }
@media(max-width:700px){ .tut-contact-grid { grid-template-columns:1fr; } }
</style>

<script>
/* ── Rider tutorial stepper ── */
let tutCurrent = 1;
const TUT_TOTAL = 5;

function tutGoTo(n) {
    document.querySelectorAll('.tut-step[data-step]').forEach(s => s.classList.remove('active'));
    document.querySelector('.tut-step[data-step="' + n + '"]').classList.add('active');
    document.querySelectorAll('.tut-dot').forEach((d,i) => d.classList.toggle('active', i === n-1));
    document.getElementById('tutPrev').disabled = (n === 1);
    const nextBtn = document.getElementById('tutNext');
    if (n === TUT_TOTAL) {
        nextBtn.textContent = '✅ Done!';
        nextBtn.disabled = true;
    } else {
        nextBtn.textContent = 'Next →';
        nextBtn.disabled = false;
    }
    document.getElementById('tutProgress').style.width = ((n / TUT_TOTAL) * 100) + '%';
    document.getElementById('tutProgressLabel').textContent = 'Step ' + n + ' of ' + TUT_TOTAL;
    tutCurrent = n;
}

function tutNav(dir) { tutGoTo(Math.max(1, Math.min(TUT_TOTAL, tutCurrent + dir))); }

/* ── Trip demo animation ── */
let tutTripActive = false;
function tutAnimateTrip() {
    tutTripActive = !tutTripActive;
    const btn  = document.getElementById('tutTripBtn');
    const note = document.getElementById('tutTripNote');
    const stat = btn.previousElementSibling;
    if (tutTripActive) {
        btn.textContent  = 'END TRIP';
        btn.style.background = 'linear-gradient(135deg,#e05252,#c0392b)';
        stat.textContent = '🟢 Trip active — monitoring started!';
        stat.className   = 'tut-mock-status active';
        note.textContent = 'Your contacts are now being tracked.';
    } else {
        btn.textContent  = 'START TRIP';
        btn.style.background = '';
        stat.textContent = '⚪ No active trip';
        stat.className   = 'tut-mock-status inactive';
        note.textContent = 'Your contacts are not being tracked.';
    }
}

/* ── Safe button demo animation ── */
function tutAnimateBtn(el) {
    el.classList.add('pressed');
    el.textContent = '✓';
    setTimeout(() => { el.classList.remove('pressed'); el.textContent = 'SAFE'; }, 600);
}

/* ── Mini countdown on step 4 ── */
let tutCdSecs = 898;
setInterval(() => {
    const el = document.getElementById('tutCdTime');
    if (!el) return;
    tutCdSecs = Math.max(0, tutCdSecs - 1);
    const m = Math.floor(tutCdSecs / 60), s = tutCdSecs % 60;
    el.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    if (tutCdSecs === 0) tutCdSecs = 898;
}, 1000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
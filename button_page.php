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

// Load trip status and ping interval for timer
$sq = $conn->prepare('SELECT system_active, ping_interval, last_ping_time FROM rider_settings WHERE rider_id = ?');
$sq->bind_param('i', $user_id); $sq->execute();
$rsettings     = $sq->get_result()->fetch_assoc();
$trip_active   = $rsettings ? (int)$rsettings['system_active']  : 0;
$ping_interval = $rsettings ? (int)$rsettings['ping_interval']  : 1800; // 1800s = 30 min default

// Calculate server-side seconds remaining in current interval
// ping_interval is now stored as SECONDS (not minutes)
$php_secs_left = $ping_interval;
if ($trip_active && !empty($rsettings['last_ping_time'])) {
    $elapsed       = time() - strtotime($rsettings['last_ping_time']);
    $php_secs_left = max(0, $ping_interval - ($elapsed % $ping_interval));
}

// Load saved customization
$cq = $conn->prepare('SELECT btn_label, btn_color, btn_color2, btn_gradient, btn_size, sound_enabled, press_effect FROM button_customization WHERE rider_id = ?');
$custom = null;
if ($cq) {
    $cq->bind_param('i', $user_id); $cq->execute();
    $custom = $cq->get_result()->fetch_assoc();
}

$btn_label     = htmlspecialchars($custom['btn_label']    ?? 'SAFE');
$btn_color     = $custom['btn_color']    ?? '#2ecc8a';
$btn_color2    = $custom['btn_color2']   ?? '';
$btn_gradient  = (int)($custom['btn_gradient'] ?? 0);
$btn_size      = $custom['btn_size']     ?? 'medium';
$sound_enabled = isset($custom['sound_enabled']) ? (int)$custom['sound_enabled'] : 1;
$press_effect  = $custom['press_effect'] ?? 'pulse';

$size_map = ['small' => '170px', 'medium' => '210px', 'large' => '250px'];
$btn_px   = $size_map[$btn_size] ?? '210px';

// Build button background
function darkenHex(string $hex, int $amt = 30): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return '#1aaa6e';
    return sprintf('#%02x%02x%02x',
        max(0, hexdec(substr($hex,0,2)) - $amt),
        max(0, hexdec(substr($hex,2,2)) - $amt),
        max(0, hexdec(substr($hex,4,2)) - $amt)
    );
}
if ($btn_gradient && $btn_color2) {
    $btn_bg = "linear-gradient(135deg,{$btn_color},{$btn_color2})";
} else {
    $btn_bg = "linear-gradient(135deg,{$btn_color}," . darkenHex($btn_color) . ")";
}

$palette = ['#2ecc8a','#3b9eff','#f5a623','#e05252','#a855f7','#06b6d4'];
$preset_labels = ['SAFE', "I'M OK", 'ALL GOOD', 'ALIVE 👍'];
$is_preset     = in_array($btn_label, $preset_labels);

$effects = [
    'pulse'    => ['label' => 'Pulse',     'icon' => '💓'],
    'pop'      => ['label' => 'Pop',       'icon' => '💥'],
    'shake'    => ['label' => 'Shake',     'icon' => '📳'],
    'ripple'   => ['label' => 'Ripple',    'icon' => '🌊'],
    'bounce'   => ['label' => 'Bounce',    'icon' => '🏀'],
    'flash'    => ['label' => 'Flash',     'icon' => '⚡'],
];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>


<!-- ════════════════════════════════════════════════════
     CENTERED: Safety Button + Customize trigger
════════════════════════════════════════════════════ -->
<div class="btn-page-wrap">
    <div class="button-card">
        <div class="pulse-ring" id="pulseRing"></div>
        <button id="safeButton" class="big-safe-btn" 
    style="width:<?php echo $btn_px;?>;height:<?php echo $btn_px;?>;background:<?php echo $btn_bg;?>; position: relative; overflow: hidden; user-select: none;">
    
    <div id="sosFill" style="position: absolute; bottom: 0; left: 0; width: 100%; height: 0%; background: rgba(224, 82, 82, 0.7); pointer-events: none; transition: height 0.1s linear; z-index: 1;"></div>
    
    <span id="btnText" style="position: relative; z-index: 2;"><?php echo $btn_label;?></span>
</button>
        <p class="button-subtext" id="btnSubtext">Tap to confirm your safety</p>
        <p class="btn-hint">Your location will be shared with trusted contacts</p>

        <!-- Countdown Timer Banner (shown when trip is active) -->
        <div id="countdownBanner" class="countdown-banner" style="display:none;">
            <span class="cd-label">Next check-in in</span>
            <span class="cd-time" id="cdTime">--:--</span>
        </div>

        <!-- Ping Due Alert Banner (shown when timer hits 0) -->
        <div id="pingDueBanner" class="ping-due-banner" style="display:none;">
            <span class="pd-icon">🔔</span>
            <span class="pd-text">Time to check in! Tap <strong><?php echo $btn_label; ?></strong></span>
        </div>

        <!-- Customize trigger button -->
        <button type="button" class="btn-customize-trigger" onclick="openModal()">
            🎨 Customize Button
        </button>

        <a href="/RIDERSAFE_Project/rider_home.php" class="btn btn-secondary" style="margin-top:12px;width:100%;">← Back to Dashboard</a>
    </div>
</div>

<!-- ════════════════════════════════════════════════════
     MODAL OVERLAY
════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="custModal" onclick="handleOverlayClick(event)">
    <div class="modal-box">

        <!-- Modal Header -->
        <div class="modal-header">
            <div class="modal-title-row">
                <span>🎨</span>
                <div>
                    <h3>Customize Safety Button</h3>
                    <p>Changes preview on your button</p>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeModal()">✕</button>
        </div>

        <!-- Modal Body: preview left + form right -->
        <div class="modal-body">

            <!-- LEFT: Live Button Preview -->
            <div class="modal-preview">
                <span class="modal-preview-label">Live Preview</span>
                <button class="preview-safe-btn" id="previewBtn" type="button"
                    onclick="demoEffect()"
                    style="width:140px;height:140px;font-size:18px;background:<?php echo $btn_bg;?>;">
                    <span id="previewBtnText"><?php echo $btn_label;?></span>
                </button>
                <span class="modal-preview-hint">👆 Tap to preview effect</span>
            </div>

            <!-- RIGHT: Customization Form -->
            <div class="modal-form-pane">
            <form class="cust-form" onsubmit="return false;">

                <!-- ── Label ── -->
                <div class="cust-row">
                    <label class="cust-label">Button Label</label>
                    <div class="cust-chips" id="labelChips">
                        <?php foreach ($preset_labels as $p): ?>
                        <button type="button" class="chip <?php echo ($btn_label===$p)?'chip-active':'';?>"
                            onclick="selectLabel(<?php echo json_encode($p);?>, this)">
                            <?php echo htmlspecialchars($p);?>
                        </button>
                        <?php endforeach;?>
                        <button type="button" class="chip <?php echo !$is_preset?'chip-active':'';?>"
                            onclick="selectLabel('__custom__', this)">Custom</button>
                    </div>
                    <input type="text" name="btn_label" id="customLabelInput"
                        value="<?php echo $btn_label;?>" maxlength="12"
                        placeholder="Type your label..."
                        class="cust-input <?php echo $is_preset?'cust-input-hidden':'';?>"
                        oninput="document.getElementById('btnText').innerText = this.value || 'SAFE'; const _pt3 = getPreviewBtnText(); if(_pt3) _pt3.innerText = this.value || 'SAFE';">
                </div>

                <!-- ── Color (with Gradient toggle) ── -->
                <div class="cust-row">
                    <label class="cust-label">Button Color</label>
                    <div class="color-block">
                        <div class="color-sub-row">
                            <span class="color-sub-label">Color 1</span>
                            <div class="cust-colors" id="paletteRow1">
                                <?php foreach ($palette as $hex):?>
                                <button type="button"
                                    class="swatch <?php echo ($btn_color===$hex)?'swatch-active':'';?>"
                                    style="background:<?php echo $hex;?>;"
                                    onclick="selectColor1('<?php echo $hex;?>', this)">
                                </button>
                                <?php endforeach;?>
                                <label class="swatch swatch-picker <?php echo !in_array($btn_color,$palette)?'swatch-active':'';?>"
                                    style="background:<?php echo !in_array($btn_color,$palette)?$btn_color:'rgba(255,255,255,0.12)';?>;"
                                    title="Custom">
                                    <span class="swatch-plus">＋</span>
                                    <input type="color" id="colorPicker1" value="<?php echo $btn_color;?>"
                                        oninput="selectColor1(this.value, this.parentElement)">
                                </label>
                            </div>
                        </div>
                        <input type="hidden" name="btn_color" id="btnColor1" value="<?php echo $btn_color;?>">

                        <!-- Gradient toggle -->
                        <div class="gradient-toggle-row">
                            <label class="toggle-wrap" style="gap:10px;">
                                <input type="checkbox" name="btn_gradient" id="gradientToggle" value="1"
                                    <?php echo $btn_gradient?'checked':'';?>
                                    onchange="toggleGradientUI()">
                                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                <span class="toggle-lbl">Mixed Gradient</span>
                            </label>
                            <span style="font-size:11px;color:rgba(255,255,255,0.3);">Blend two colors</span>
                        </div>

                        <div class="color-sub-row" id="color2Row" style="<?php echo $btn_gradient?'':'display:none;';?>">
                            <span class="color-sub-label">Color 2</span>
                            <div class="cust-colors" id="paletteRow2">
                                <?php foreach ($palette as $hex):?>
                                <button type="button"
                                    class="swatch <?php echo ($btn_color2===$hex)?'swatch-active':'';?>"
                                    style="background:<?php echo $hex;?>;"
                                    onclick="selectColor2('<?php echo $hex;?>', this)">
                                </button>
                                <?php endforeach;?>
                                <label class="swatch swatch-picker <?php echo ($btn_color2 && !in_array($btn_color2,$palette))?'swatch-active':'';?>"
                                    style="background:<?php echo ($btn_color2 && !in_array($btn_color2,$palette))?$btn_color2:'rgba(255,255,255,0.12)';?>;"
                                    title="Custom">
                                    <span class="swatch-plus">＋</span>
                                    <input type="color" id="colorPicker2" value="<?php echo $btn_color2 ?: '#3b9eff';?>"
                                        oninput="selectColor2(this.value, this.parentElement)">
                                </label>
                            </div>
                            <input type="hidden" name="btn_color2" id="btnColor2" value="<?php echo $btn_color2;?>">
                        </div>
                    </div>
                </div>

                <!-- ── Size ── -->
                <div class="cust-row">
                    <label class="cust-label">Button Size</label>
                    <div class="cust-chips" id="sizeChips">
                        <?php foreach (['small'=>'Small','medium'=>'Medium','large'=>'Large'] as $val=>$lbl):?>
                        <button type="button" class="chip <?php echo $btn_size===$val?'chip-active':'';?>"
                            onclick="selectSize('<?php echo $val;?>', this)">
                            <?php echo $lbl;?>
                        </button>
                        <?php endforeach;?>
                    </div>
                    <input type="hidden" name="btn_size" id="btnSizeInput" value="<?php echo $btn_size;?>">
                </div>

                <!-- ── Press Effect ── -->
                <div class="cust-row">
                    <label class="cust-label">Press Effect</label>
                    <div class="effect-grid" id="effectGrid">
                        <?php foreach ($effects as $key => $ef):?>
                        <button type="button"
                            class="effect-chip <?php echo $press_effect===$key?'effect-active':'';?>"
                            onclick="selectEffect('<?php echo $key;?>', this)"
                            title="<?php echo $ef['label'];?>">
                            <span class="effect-icon"><?php echo $ef['icon'];?></span>
                            <span class="effect-name"><?php echo $ef['label'];?></span>
                        </button>
                        <?php endforeach;?>
                    </div>
                    <input type="hidden" name="press_effect" id="pressEffectInput" value="<?php echo $press_effect;?>">
                </div>

                <!-- ── Sound ── -->
                <div class="cust-row cust-row-inline">
                    <label class="cust-label" style="margin-bottom:0;">Click Sound</label>
                    <label class="toggle-wrap">
                        <input type="checkbox" name="sound_enabled" id="soundToggle" value="1"
                            <?php echo $sound_enabled?'checked':'';?>>
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        <span class="toggle-lbl" id="soundLabel"><?php echo $sound_enabled?'On':'Off';?></span>
                    </label>
                </div>

                <!-- Modal footer buttons -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-reset" onclick="resetDefaults()">↺ Reset</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">✕ Cancel</button>
                    <button type="button" class="btn btn-teal" onclick="saveCustomization()">💾 Save</button>
                </div>
            </form>
            </div><!-- /modal-form-pane -->
        </div><!-- /modal-body -->
    </div><!-- /modal-box -->
</div><!-- /modal-overlay -->

<!-- ── Toast notification ── -->
<div class="save-toast" id="saveToast">✅ Button customization saved!</div>

<style>
/* ── Page Layout ─────────────────────────────────────── */
.btn-page-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 70px);
    padding: 20px;
    box-sizing: border-box;
}

/* ── Customize trigger button ─────────────────────────── */
.btn-customize-trigger {
    width: 100%;
    margin-top: 16px;
    padding: 12px 20px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.85);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    letter-spacing: 0.02em;
}
.btn-customize-trigger:hover {
    background: rgba(255,255,255,0.11);
    border-color: rgba(255,255,255,0.28);
    color: #fff;
}

/* ── Modal Overlay ────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.65);
    backdrop-filter: blur(4px);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    padding: 20px;
    box-sizing: border-box;
}
.modal-overlay.open { display: flex; }
.modal-box {
    background: #1a2233;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 22px;
    width: 100%;
    max-width: 860px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 24px 60px rgba(0,0,0,0.5);
    animation: modalIn 0.25s cubic-bezier(0.34,1.56,0.64,1) forwards;
}
@keyframes modalIn {
    from { opacity:0; transform:scale(0.92) translateY(16px); }
    to   { opacity:1; transform:scale(1)    translateY(0); }
}
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 22px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
}
.modal-title-row { display: flex; align-items: center; gap: 12px; }
.modal-title-row span:first-child { font-size: 26px; }
.modal-title-row h3 { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 800; color: #fff; margin: 0 0 2px; }
.modal-title-row p  { font-size: 12px; color: rgba(255,255,255,0.35); margin: 0; }
.modal-close {
    width: 34px; height: 34px; border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.6);
    font-size: 14px; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.modal-close:hover { background:rgba(224,82,82,0.2); border-color:rgba(224,82,82,0.4); color:#e05252; }
.modal-body { display: grid; grid-template-columns: 240px 1fr; flex: 1; overflow: hidden; min-height: 0; }
.modal-preview {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
    padding: 28px 20px; background: rgba(0,0,0,0.18);
    border-right: 1px solid rgba(255,255,255,0.06); flex-shrink: 0;
}
.modal-preview-label { font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 0.08em; }
.preview-safe-btn {
    border-radius: 50%; border: none; cursor: pointer;
    font-family: 'Syne', sans-serif; font-weight: 900; color: #fff; letter-spacing: 0.04em;
    box-shadow: 0 8px 28px rgba(0,0,0,0.4);
    transition: background 0.3s, width 0.3s, height 0.3s, font-size 0.3s, transform 0.15s;
    display: flex; align-items: center; justify-content: center;
    text-align: center; word-break: break-word; padding: 12px; position: relative;
}
.preview-safe-btn:hover { filter: brightness(1.08); }
.modal-preview-hint { font-size: 11px; color: rgba(255,255,255,0.22); text-align: center; line-height: 1.5; }
.modal-form-pane { overflow-y: auto; padding: 20px 22px; }
@media(max-width:640px){
    .modal-body { grid-template-columns: 1fr; }
    .modal-preview { border-right: none; border-bottom: 1px solid rgba(255,255,255,0.06); padding: 20px; flex-direction: row; justify-content: center; gap: 20px; }
    .modal-preview-hint { display: none; }
}
.modal-footer { display: flex; gap: 10px; margin-top: 20px; }
.modal-footer .btn { flex: 1; }
.btn-reset {
    background: rgba(245,166,35,0.12); border: 1px solid rgba(245,166,35,0.3); color: #f5a623;
    border-radius: 10px; padding: 10px 16px; font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s;
}
.btn-reset:hover { background: rgba(245,166,35,0.22); border-color: rgba(245,166,35,0.5); }
.save-toast {
    position: fixed; bottom: 32px; left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: #1a2233; border: 1px solid rgba(46,204,138,0.4); color: #2ecc8a;
    font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 700;
    padding: 14px 28px; border-radius: 50px; box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    z-index: 2000; opacity: 0; pointer-events: none; transition: opacity 0.3s, transform 0.3s; white-space: nowrap;
}
.save-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.cust-form { display:flex; flex-direction:column; gap:0; }
.cust-row { margin-bottom:18px; }
.cust-row-inline { display:flex; align-items:center; justify-content:space-between; }
.cust-label { display: block; font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 9px; }
.cust-chips { display:flex; flex-wrap:wrap; gap:7px; }
.chip { padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.13); background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.65); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.chip:hover { border-color:rgba(255,255,255,0.3); color:#fff; }
.chip-active { background:rgba(42,107,138,0.3); border-color:var(--teal-light); color:var(--teal-light); }
.cust-input { margin-top: 9px; width:100%; padding: 9px 13px; border-radius:9px; border: 1px solid rgba(255,255,255,0.14); background: rgba(255,255,255,0.07); color:#fff; font-family:'Plus Jakarta Sans',sans-serif; font-size:13px; font-weight:700; outline:none; box-sizing:border-box; transition: border-color 0.2s; }
.cust-input:focus { border-color:var(--teal-light); box-shadow:0 0 0 3px rgba(42,107,138,0.2); }
.cust-input-hidden { display:none; }
.cust-input::placeholder { color:rgba(255,255,255,0.25); }
.color-block { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; padding: 14px; display: flex; flex-direction: column; gap: 12px; }
.color-sub-row { display:flex; flex-direction:column; gap:8px; }
.color-sub-label { font-size:11px; font-weight:700; color:rgba(255,255,255,0.35); }
.cust-colors { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.swatch { width:30px; height:30px; border-radius:50%; border:3px solid transparent; cursor:pointer; transition:transform 0.2s, border-color 0.2s; outline:none; }
.swatch:hover { transform:scale(1.15); }
.swatch-active { border-color:#fff !important; transform:scale(1.1); }
.swatch-picker { background:rgba(255,255,255,0.1); display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden; }
.swatch-plus { font-size:15px; color:rgba(255,255,255,0.6); line-height:1; pointer-events:none; }
.swatch-picker input[type="color"] { position:absolute; opacity:0; width:100%; height:100%; cursor:pointer; border:none; padding:0; }
.gradient-toggle-row { display:flex; align-items:center; justify-content:space-between; padding: 8px 0; border-top: 1px solid rgba(255,255,255,0.06); border-bottom: 1px solid rgba(255,255,255,0.06); }
.effect-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.effect-chip { display: flex; flex-direction:column; align-items:center; gap:5px; padding: 10px 6px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); cursor: pointer; transition: all 0.2s; }
.effect-chip:hover { border-color:rgba(255,255,255,0.25); background:rgba(255,255,255,0.08); }
.effect-active { background:rgba(42,107,138,0.25); border-color:var(--teal-light); }
.effect-icon { font-size:20px; line-height:1; }
.effect-name { font-size:11px; font-weight:700; color:rgba(255,255,255,0.65); font-family:'Plus Jakarta Sans',sans-serif; }
.effect-active .effect-name { color:var(--teal-light); }
.toggle-wrap { display:flex; align-items:center; gap:10px; cursor:pointer; }
.toggle-wrap input[type="checkbox"] { display:none; }
.toggle-track { width:44px; height:24px; background:rgba(255,255,255,0.15); border-radius:12px; position:relative; transition:background 0.3s; flex-shrink:0; }
.toggle-thumb { position:absolute; width:18px; height:18px; background:#fff; border-radius:50%; top:3px; left:3px; transition:transform 0.3s; }
.toggle-wrap input:checked ~ .toggle-track { background:var(--green); }
.toggle-wrap input:checked ~ .toggle-track .toggle-thumb { transform:translateX(20px); }
.toggle-lbl { font-size:13px; font-weight:700; color:rgba(255,255,255,0.65); }
.saved-msg { text-align:center; font-size:13px; color:var(--green); font-weight:700; margin-top:12px; }
.btn-hint  { font-size:12px; color:rgba(255,255,255,0.25); margin-top:16px; }
.pulse-ring { pointer-events: none !important; z-index: 0; }
.btn-customize-trigger { position: relative; z-index: 2; cursor: pointer; }

/* ── Press Effect Keyframes ─────────────────────────── */
@keyframes fx-pop    { 0%{transform:scale(1)} 30%{transform:scale(1.18)} 60%{transform:scale(0.93)} 80%{transform:scale(1.06)} 100%{transform:scale(1)} }
@keyframes fx-shake  { 0%,100%{transform:translateX(0)} 15%{transform:translateX(-10px) rotate(-3deg)} 30%{transform:translateX(10px) rotate(3deg)} 45%{transform:translateX(-8px) rotate(-2deg)} 60%{transform:translateX(8px) rotate(2deg)} 75%{transform:translateX(-4px)} 90%{transform:translateX(4px)} }
@keyframes fx-bounce { 0%,100%{transform:translateY(0) scale(1)} 25%{transform:translateY(-22px) scale(1.05)} 50%{transform:translateY(0) scale(0.96)} 75%{transform:translateY(-10px) scale(1.02)} }
@keyframes fx-flash  { 0%,100%{opacity:1} 20%,60%{opacity:0.15} 40%,80%{opacity:1} }
@keyframes fx-ripple-ring { 0%{transform:scale(0.8);opacity:0.8} 100%{transform:scale(2.2);opacity:0} }
.fx-pop    { animation: fx-pop    0.5s  ease forwards; }
.fx-shake  { animation: fx-shake  0.55s ease forwards; }
.fx-bounce { animation: fx-bounce 0.6s  ease forwards; }
.fx-flash  { animation: fx-flash  0.5s  ease forwards; }

/* ── Countdown Banner ────────────────────────────────── */
.countdown-banner {
    width: 100%; margin-top: 14px; padding: 10px 18px; border-radius: 12px;
    background: rgba(59,158,255,0.1); border: 1px solid rgba(59,158,255,0.25);
    display: flex; align-items: center; justify-content: space-between; gap: 10px; box-sizing: border-box;
}
.cd-label { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.45); text-transform: uppercase; letter-spacing: 0.06em; }
.cd-time  { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 900; color: #3b9eff; letter-spacing: 0.04em; }
.cd-time.urgent   { color: #f5a623; }
.cd-time.critical { color: #e05252; animation: cdPulse 0.8s ease infinite; }
@keyframes cdPulse { 0%,100%{opacity:1} 50%{opacity:0.4} }

/* ── Ping Due Banner ─────────────────────────────────── */
.ping-due-banner {
    width: 100%; margin-top: 14px; padding: 14px 18px; border-radius: 12px;
    background: rgba(224,82,82,0.15); border: 1px solid rgba(224,82,82,0.4);
    display: flex; align-items: center; gap: 12px; box-sizing: border-box;
    animation: bannerPop 0.4s cubic-bezier(0.34,1.56,0.64,1) forwards;
}
@keyframes bannerPop { from{transform:scale(0.92);opacity:0} to{transform:scale(1);opacity:1} }
.pd-icon { font-size: 22px; flex-shrink: 0; }
.pd-text { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.85); line-height: 1.4; }
</style>

<script>
// ── DOM refs ────────────────────────────────────────────────────────────────
const btn        = document.getElementById('safeButton');
const btnText    = document.getElementById('btnText');
const subtext    = document.getElementById('btnSubtext');
const clickSound = new Audio('/RIDERSAFE_Project/button_sound.mp3');
const alertSound = new Audio('/RIDERSAFE_Project/notif_sound.mp3');
const cdBanner   = document.getElementById('countdownBanner');
const cdTime     = document.getElementById('cdTime');
const pdBanner   = document.getElementById('pingDueBanner');
const sosFill    = document.getElementById('sosFill');
const sizeMap         = { small:'170px', medium:'210px', large:'250px' };
const previewSizeMap  = { small:'110px', medium:'140px', large:'168px' };
const previewFontMap  = { small:'14px',  medium:'18px',  large:'22px'  };
function getPreviewBtn()     { return document.getElementById('previewBtn'); }
function getPreviewBtnText() { return document.getElementById('previewBtnText'); }

// ── PHP → JS constants ──────────────────────────────────────────────────────
let currentEffect = <?php echo json_encode($press_effect); ?>;
const TRIP_ACTIVE   = <?php echo $trip_active ? 'true' : 'false'; ?>;
const PING_INTERVAL = <?php echo (int)$ping_interval; ?>; // seconds
const SOUND_ENABLED = <?php echo $sound_enabled ? 'true' : 'false'; ?>;
const INTERVAL_MS   = PING_INTERVAL * 1000;
const USER_ID       = <?php echo $user_id; ?>;
const GRACE_SECS    = 10; // fixed 10-second grace period
const PHP_SECS_LEFT = <?php echo (int)$php_secs_left; ?>;
const BTN_LABEL     = <?php echo json_encode($btn_label); ?>;

// ── Deadline-based timer ─────────────────────────────────────────────────────
// We store an absolute deadline timestamp in localStorage.
// getSecsLeft() = (deadline - now) — counts to zero and STOPS.
// It never auto-wraps. The deadline is only advanced explicitly.
const DEADLINE_KEY   = 'rs_deadline_'   + USER_ID;
const TRIP_START_KEY = 'rs_tripstart_'  + USER_ID; // kept for dashboard compat

function getDeadline()    { const v = parseInt(localStorage.getItem(DEADLINE_KEY)||'0'); return v > 0 ? v : 0; }
function saveDeadline(ms) { localStorage.setItem(DEADLINE_KEY, ms); }
function clearDeadline()  { localStorage.removeItem(DEADLINE_KEY); }
function advanceDeadline(){ saveDeadline(Date.now() + INTERVAL_MS); }

// Keep trip-start in sync for the dashboard
function saveTripStart(ms) { localStorage.setItem(TRIP_START_KEY, ms); }
function clearTripStart()  { localStorage.removeItem(TRIP_START_KEY); }

function getSecsLeft() {
    const dl = getDeadline();
    if (!dl) return PING_INTERVAL;
    return Math.max(0, Math.round((dl - Date.now()) / 1000));
}

// Seed deadline on page load if trip is active
if (TRIP_ACTIVE) {
    if (!getDeadline()) {
        saveDeadline(Date.now() + (PHP_SECS_LEFT * 1000));
        saveTripStart(Date.now() - (INTERVAL_MS - PHP_SECS_LEFT * 1000));
    }
}

// ── Timer state ──────────────────────────────────────────────────────────────
let timerInterval   = null;
let graceInterval   = null;
let graceSecsLeft   = GRACE_SECS;
let pingDue         = false;
let missedSent      = false;
let safeDuringGrace = false;

// ── Helpers ──────────────────────────────────────────────────────────────────
function formatTime(secs) {
    const h = Math.floor(secs / 3600);
    const m = Math.floor((secs % 3600) / 60);
    const s = secs % 60;
    if (h > 0) return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    return String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
}
function adjustHex(hex, amt) {
    const clamp = v => Math.max(0, Math.min(255, v + amt));
    const r = clamp(parseInt(hex.slice(1,3),16));
    const g = clamp(parseInt(hex.slice(3,5),16));
    const b = clamp(parseInt(hex.slice(5,7),16));
    return '#'+[r,g,b].map(x=>x.toString(16).padStart(2,'0')).join('');
}
function rebuildBg() {
    const c1   = document.getElementById('btnColor1').value;
    const c2   = document.getElementById('btnColor2')?.value;
    const grad = document.getElementById('gradientToggle').checked;
    const bg   = (grad && c2) ? `linear-gradient(135deg,${c1},${c2})` : `linear-gradient(135deg,${c1},${adjustHex(c1,-30)})`;
    btn.style.background = bg;
    const _pb = getPreviewBtn(); if (_pb) _pb.style.background = bg;
}

// ── Push notifications ───────────────────────────────────────────────────────
async function requestNotifPermission() {
    if (!('Notification' in window)) return;
    if (Notification.permission === 'default') await Notification.requestPermission();
}
function sendLocalNotification(title, body) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    // Prefer service worker — works even on other tabs
    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type:'SHOW_NOTIFICATION', title, body, icon:'/RIDERSAFE_Project/assets/logo.png', tag:'ridersafe-checkin' });
    } else {
        const n = new Notification(title, { body, icon:'/RIDERSAFE_Project/assets/logo.png', tag:'ridersafe-checkin', requireInteraction:true });
        n.onclick = () => { window.focus(); n.close(); };
    }
}

// ── Press Effects ────────────────────────────────────────────────────────────
function applyEffect(effectKey, target) {
    target.classList.remove('fx-pop','fx-shake','fx-bounce','fx-flash');
    target.querySelectorAll('.ripple-ring').forEach(r => r.remove());
    if (effectKey === 'pulse') {
        target.style.transform = 'scale(0.88)';
        setTimeout(() => { target.style.transform = ''; }, 200);
    } else if (effectKey === 'pop') {
        target.classList.add('fx-pop');
        target.addEventListener('animationend', () => target.classList.remove('fx-pop'), { once:true });
    } else if (effectKey === 'shake') {
        target.classList.add('fx-shake');
        target.addEventListener('animationend', () => target.classList.remove('fx-shake'), { once:true });
    } else if (effectKey === 'ripple') {
        for (let i = 0; i < 3; i++) {
            setTimeout(() => {
                const ring = document.createElement('div');
                ring.className = 'ripple-ring';
                ring.style.cssText = `position:absolute;width:${target.offsetWidth}px;height:${target.offsetHeight}px;border-radius:50%;border:3px solid ${document.getElementById('btnColor1').value};animation:fx-ripple-ring 0.9s ease forwards;pointer-events:none;z-index:0;top:0;left:0;`;
                target.style.position = 'relative';
                target.appendChild(ring);
                ring.addEventListener('animationend', () => ring.remove());
            }, i * 200);
        }
    } else if (effectKey === 'bounce') {
        target.classList.add('fx-bounce');
        target.addEventListener('animationend', () => target.classList.remove('fx-bounce'), { once:true });
    } else if (effectKey === 'flash') {
        target.classList.add('fx-flash');
        target.addEventListener('animationend', () => target.classList.remove('fx-flash'), { once:true });
    }
}
function runPressEffect(effectKey) { applyEffect(effectKey, btn); }
function demoEffect() { const _pb3 = getPreviewBtn(); if (_pb3) applyEffect(currentEffect, _pb3); }

// ── Timer functions ──────────────────────────────────────────────────────────
function startCheckInTimer() {
    if (timerInterval) clearInterval(timerInterval);
    if (graceInterval) { clearInterval(graceInterval); graceInterval = null; }
    pingDue = false; missedSent = false; safeDuringGrace = false;

    // If no deadline set yet, start one now
    if (!getDeadline()) advanceDeadline();

    cdBanner.style.display = 'flex';
    pdBanner.style.display = 'none';
    cdTime.className   = 'cd-time';
    cdTime.textContent = formatTime(getSecsLeft());

    timerInterval = setInterval(() => {
        const secs = getSecsLeft();
        const pct  = secs / PING_INTERVAL;
        if      (pct <= 0.1)  cdTime.className = 'cd-time critical';
        else if (pct <= 0.25) cdTime.className = 'cd-time urgent';
        else                  cdTime.className = 'cd-time';
        cdTime.textContent = formatTime(secs);

        // Only fire onCheckInDue once — when deadline is passed and not already in grace
        if (secs <= 0 && !pingDue) {
            clearInterval(timerInterval);
            timerInterval = null;
            onCheckInDue();
        }
    }, 1000);
}

function stopCheckInTimer() {
    if (timerInterval) clearInterval(timerInterval);
    if (graceInterval) clearInterval(graceInterval);
    timerInterval = graceInterval = null;
    clearDeadline();
    clearTripStart();
    cdBanner.style.display = 'none';
    pdBanner.style.display = 'none';
    pingDue = false; missedSent = false; safeDuringGrace = false;
}

function onCheckInDue() {
    pingDue = true; safeDuringGrace = false; missedSent = false;
    graceSecsLeft = GRACE_SECS;

    cdBanner.style.display = 'none';
    pdBanner.style.display = 'flex';

    if (SOUND_ENABLED) { alertSound.currentTime = 0; alertSound.play().catch(() => {}); }
    if (btn) applyEffect('pulse', btn);

    // Fire browser/SW notification
    sendLocalNotification(
        '⏰ RiderSafe — Check-in Due!',
        `Tap ${BTN_LABEL} now! You have ${GRACE_SECS} seconds before it's marked as missed.`
    );

    // Live countdown in the banner
    const pdText = pdBanner.querySelector('.pd-text');
    function updateGraceBanner() {
        if (pdText) pdText.innerHTML = `Time to check in! Tap <strong>${BTN_LABEL}</strong> — <span style="color:#e05252;font-weight:900;">${graceSecsLeft}s</span> remaining`;
    }
    updateGraceBanner();

    if (graceInterval) clearInterval(graceInterval);
    graceInterval = setInterval(() => {
        graceSecsLeft--;
        updateGraceBanner();

        if (safeDuringGrace) {
            clearInterval(graceInterval); graceInterval = null;
            pdBanner.style.display = 'none';
            pingDue = false;
            advanceDeadline();
            saveTripStart(Date.now());
            startCheckInTimer();
            return;
        }
        if (graceSecsLeft <= 0) {
            clearInterval(graceInterval); graceInterval = null;
            if (!missedSent) { missedSent = true; sendMissedPing(); }
        }
    }, 1000);
}

function sendMissedPing() {
    const doMiss = (lat, lng) => {
        fetch('/RIDERSAFE_Project/process/update_ping.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'latitude='+(lat||'')+'&longitude='+(lng||'')+'&missed=1'
        }).then(() => {
            subtext.innerText = '⚠️ Check-in missed. Contacts have been notified.';
            pdBanner.style.display = 'none';
            setTimeout(() => {
                subtext.innerText = 'Tap to confirm your safety';
                advanceDeadline();
                saveTripStart(Date.now());
                startCheckInTimer();
            }, 3000);
        }).catch(() => {
            // Even on network error, advance and restart so the cycle continues
            advanceDeadline();
            saveTripStart(Date.now());
            startCheckInTimer();
        });
    };
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            p => doMiss(p.coords.latitude, p.coords.longitude),
            ()  => doMiss(null, null),
            { timeout: 5000 }
        );
    } else { doMiss(null, null); }
}

// ── Send Safe Ping ───────────────────────────────────────────────────────────
function sendSafePing() {
    if (pingDue) safeDuringGrace = true;

    const soundOn  = document.getElementById('soundToggle')?.checked ?? SOUND_ENABLED;
    const savedLabel = btnText.innerText;
    const savedBg    = btn.style.background;

    if (soundOn) { clickSound.currentTime = 0; clickSound.play().catch(() => {}); }
    runPressEffect(currentEffect);
    btnText.innerText = '✓';
    subtext.innerText = 'Sending ping...';

    // Reset timer immediately — don't wait for geolocation
    advanceDeadline();
    saveTripStart(Date.now());
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
    // Only restart if not in grace (grace handles its own restart)
    if (!pingDue) startCheckInTimer();

    const doSend = (lat, lng) => {
        fetch('/RIDERSAFE_Project/process/update_ping.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'latitude='+(lat||'')+'&longitude='+(lng||'')
        })
        .then(() => { subtext.innerText = '✅ Safety ping sent to your contacts!'; })
        .catch(() => { subtext.innerText = '⚠️ Ping sent (offline mode)'; });
    };

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            pos => doSend(pos.coords.latitude, pos.coords.longitude),
            ()   => doSend(null, null),
            { timeout: 6000, maximumAge: 30000 }
        );
    } else { doSend(null, null); }

    setTimeout(() => {
        btnText.innerText    = savedLabel;
        subtext.innerText    = 'Tap to confirm your safety';
        btn.style.background = savedBg;
    }, 4000);
}

// ── Auto-start on page load ──────────────────────────────────────────────────
if (TRIP_ACTIVE) {
    requestNotifPermission();
    startCheckInTimer();
} else {
    // No active trip — make it clear the button is in demo mode
    subtext.innerText = 'Start a trip to activate safety pings';
}
window.onTripStarted = function() { requestNotifPermission(); advanceDeadline(); saveTripStart(Date.now()); startCheckInTimer(); };
window.onTripStopped = stopCheckInTimer;

// ── SOS & Long Press ─────────────────────────────────────────────────────────
let sosTimer         = null;
let startTime        = 0;
let isLongPress      = false;
let pressStartedOnBtn = false;
const SOS_DURATION   = 3000;

function handlePressStart(e) {
    if (e.type === 'touchstart') e.preventDefault();
    pressStartedOnBtn = true;
    clearInterval(sosTimer);
    isLongPress = false;
    startTime   = Date.now();

    sosTimer = setInterval(() => {
        const elapsed  = Date.now() - startTime;
        const progress = Math.min((elapsed / SOS_DURATION) * 100, 100);
        if (sosFill) sosFill.style.height = progress + '%';
        if (elapsed >= SOS_DURATION) {
            clearInterval(sosTimer); sosTimer = null;
            isLongPress = true;
            triggerSOS();
        }
    }, 50);
}

function handlePressEnd() {
    if (!pressStartedOnBtn) return;
    pressStartedOnBtn = false;
    if (sosTimer) { clearInterval(sosTimer); sosTimer = null; }
    if (sosFill)  sosFill.style.height = '0%';
    if (!isLongPress) {
        // Always play the visual/sound effect — button feels responsive regardless
        const soundOn = document.getElementById('soundToggle')?.checked ?? SOUND_ENABLED;
        if (soundOn) { clickSound.currentTime = 0; clickSound.play().catch(() => {}); }
        runPressEffect(currentEffect);

        // Check live trip status before doing anything real
        fetch('/RIDERSAFE_Project/process/get_trip_status.php')
            .then(r => r.json())
            .then(d => {
                if (!d.active) {
                    // No trip — show message, no ping, no timer
                    const savedLabel = btnText.innerText;
                    btnText.innerText = '✓';
                    subtext.innerText = '⚠️ Start a trip first to send pings.';
                    setTimeout(() => {
                        btnText.innerText = savedLabel;
                        subtext.innerText = 'Tap to confirm your safety';
                    }, 2500);
                } else {
                    sendSafePing();
                }
            })
            .catch(() => {
                // Network error — fall back to PHP-rendered value
                if (!TRIP_ACTIVE) {
                    const savedLabel = btnText.innerText;
                    btnText.innerText = '✓';
                    subtext.innerText = '⚠️ Start a trip first to send pings.';
                    setTimeout(() => {
                        btnText.innerText = savedLabel;
                        subtext.innerText = 'Tap to confirm your safety';
                    }, 2500);
                } else {
                    sendSafePing();
                }
            });
    }
}

btn.addEventListener('mousedown',  handlePressStart);
btn.addEventListener('touchstart', handlePressStart);
window.addEventListener('mouseup',      handlePressEnd);
window.addEventListener('touchend',     handlePressEnd);
window.addEventListener('touchcancel',  handlePressEnd);

function triggerSOS() {
    // Don't fire SOS if no trip is active
    fetch('/RIDERSAFE_Project/process/get_trip_status.php')
        .then(r => r.json())
        .then(d => {
            if (!d.active) {
                showToast('⚠️ Start a trip first to use SOS.');
                btnText.innerText = btn.dataset.label || 'SAFE';
                return;
            }
            doTriggerSOS();
        })
        .catch(() => {
            if (!TRIP_ACTIVE) { showToast('⚠️ Start a trip first to use SOS.'); return; }
            doTriggerSOS();
        });
}

function doTriggerSOS() {
    if (SOUND_ENABLED) alertSound.play().catch(() => {});
    btnText.innerText = '🚨 SOS';
    const doSOS = (lat, lng) => {
        fetch('/RIDERSAFE_Project/sos_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'latitude='+(lat||'')+'&longitude='+(lng||'')
        })
        .then(res => res.json())
        .then(() => { showToast('🚨 SOS ALERT SENT!'); setTimeout(() => location.reload(), 3000); })
        .catch(() => showToast('SOS failed. Try again.'));
    };
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            pos => doSOS(pos.coords.latitude, pos.coords.longitude),
            ()   => doSOS(null, null),
            { timeout: 5000, maximumAge: 30000 }
        );
    } else { doSOS(null, null); }
}

// ── Customization: Label ─────────────────────────────────────────────────────
function selectLabel(preset, el) {
    document.querySelectorAll('#labelChips .chip').forEach(c => c.classList.remove('chip-active'));
    el.classList.add('chip-active');
    const inp = document.getElementById('customLabelInput');
    if (preset === '__custom__') {
        inp.classList.remove('cust-input-hidden'); inp.focus();
        const lbl = inp.value || 'SAFE';
        document.getElementById('btnText').innerText        = lbl;
        document.getElementById('previewBtnText').innerText = lbl;
    } else {
        inp.classList.add('cust-input-hidden'); inp.value = preset;
        document.getElementById('btnText').innerText        = preset;
        document.getElementById('previewBtnText').innerText = preset;
    }
}

// ── Customization: Colors ────────────────────────────────────────────────────
function selectColor1(hex, el) {
    document.querySelectorAll('#paletteRow1 .swatch').forEach(c => c.classList.remove('swatch-active'));
    el.classList.add('swatch-active');
    document.getElementById('btnColor1').value = hex;
    if (el.classList.contains('swatch-picker')) el.style.background = hex;
    rebuildBg();
}
function selectColor2(hex, el) {
    document.querySelectorAll('#paletteRow2 .swatch').forEach(c => c.classList.remove('swatch-active'));
    el.classList.add('swatch-active');
    document.getElementById('btnColor2').value = hex;
    if (el.classList.contains('swatch-picker')) el.style.background = hex;
    rebuildBg();
}
function toggleGradientUI() {
    document.getElementById('color2Row').style.display = document.getElementById('gradientToggle').checked ? '' : 'none';
    rebuildBg();
}

// ── Customization: Size ──────────────────────────────────────────────────────
function selectSize(val, el) {
    document.querySelectorAll('#sizeChips .chip').forEach(c => c.classList.remove('chip-active'));
    el.classList.add('chip-active');
    document.getElementById('btnSizeInput').value = val;
    btn.style.width  = sizeMap[val];
    btn.style.height = sizeMap[val];
    const _pb4 = getPreviewBtn();
    if (_pb4) { _pb4.style.width = previewSizeMap[val]; _pb4.style.height = previewSizeMap[val]; _pb4.style.fontSize = previewFontMap[val]; }
}

// ── Customization: Press Effect ──────────────────────────────────────────────
function selectEffect(key, el) {
    document.querySelectorAll('#effectGrid .effect-chip').forEach(c => c.classList.remove('effect-active'));
    el.classList.add('effect-active');
    document.getElementById('pressEffectInput').value = key;
    currentEffect = key;
    const _pb2 = getPreviewBtn(); if (_pb2) applyEffect(key, _pb2);
}

// ── Customization: Sound ─────────────────────────────────────────────────────
document.getElementById('soundToggle').addEventListener('change', function() {
    document.getElementById('soundLabel').innerText = this.checked ? 'On' : 'Off';
});

// ── Toast ─────────────────────────────────────────────────────────────────────
function showToast(msg) {
    const t = document.getElementById('saveToast');
    t.innerText = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ── AJAX Save Customization ──────────────────────────────────────────────────
function saveCustomization() {
    const form = document.querySelector('.cust-form');
    const data = new FormData(form);
    data.set('btn_label',     document.getElementById('customLabelInput').value || 'SAFE');
    data.set('btn_color',     document.getElementById('btnColor1').value);
    data.set('btn_color2',    document.getElementById('btnColor2') ? document.getElementById('btnColor2').value : '');
    data.set('btn_gradient',  document.getElementById('gradientToggle').checked ? '1' : '0');
    data.set('btn_size',      document.getElementById('btnSizeInput').value);
    data.set('sound_enabled', document.getElementById('soundToggle').checked ? '1' : '0');
    data.set('press_effect',  document.getElementById('pressEffectInput').value);
    fetch('/RIDERSAFE_Project/process/button_customize.php', { method:'POST', body:data })
    .then(() => { closeModal(); showToast('✅ Button customization saved!'); })
    .catch(() => { closeModal(); showToast('✅ Button customization saved!'); });
}

// ── Reset to Defaults ─────────────────────────────────────────────────────────
function resetDefaults() {
    if (!confirm('Reset button to default settings?')) return;
    const defaultBg = 'linear-gradient(135deg,#2ecc8a,#1aaa6e)';
    document.getElementById('safeButton').style.background  = defaultBg;
    document.getElementById('safeButton').style.width       = '210px';
    document.getElementById('safeButton').style.height      = '210px';
    document.getElementById('btnText').innerText            = 'SAFE';
    document.getElementById('previewBtn').style.background  = defaultBg;
    document.getElementById('previewBtn').style.width       = '140px';
    document.getElementById('previewBtn').style.height      = '140px';
    document.getElementById('previewBtn').style.fontSize    = '18px';
    document.getElementById('previewBtnText').innerText     = 'SAFE';
    document.getElementById('btnColor1').value              = '#2ecc8a';
    document.getElementById('btnSizeInput').value           = 'medium';
    document.getElementById('pressEffectInput').value       = 'pulse';
    const c2 = document.getElementById('btnColor2'); if (c2) c2.value = '';
    document.getElementById('gradientToggle').checked       = false;
    document.getElementById('color2Row').style.display      = 'none';
    document.getElementById('soundToggle').checked          = true;
    document.getElementById('soundLabel').innerText         = 'On';
    document.querySelectorAll('#labelChips .chip').forEach(c => c.classList.remove('chip-active'));
    document.querySelector('#labelChips .chip').classList.add('chip-active');
    document.getElementById('customLabelInput').value       = 'SAFE';
    document.getElementById('customLabelInput').classList.add('cust-input-hidden');
    document.querySelectorAll('#paletteRow1 .swatch').forEach(s => s.classList.remove('swatch-active'));
    document.querySelector('#paletteRow1 .swatch').classList.add('swatch-active');
    document.querySelectorAll('#sizeChips .chip').forEach(c => c.classList.remove('chip-active'));
    document.querySelectorAll('#sizeChips .chip')[1].classList.add('chip-active');
    document.querySelectorAll('#effectGrid .effect-chip').forEach(c => c.classList.remove('effect-active'));
    document.querySelector('#effectGrid .effect-chip').classList.add('effect-active');
    currentEffect = 'pulse';
    const fd = new FormData();
    fd.append('btn_label','SAFE'); fd.append('btn_color','#2ecc8a'); fd.append('btn_color2','');
    fd.append('btn_gradient','0'); fd.append('btn_size','medium');
    fd.append('sound_enabled','1'); fd.append('press_effect','pulse');
    fetch('/RIDERSAFE_Project/process/button_customize.php', { method:'POST', body:fd })
    .then(() => { closeModal(); showToast('↺ Button reset to defaults!'); })
    .catch(() => { closeModal(); showToast('↺ Button reset to defaults!'); });
}

// ── Modal Open / Close ────────────────────────────────────────────────────────
function openModal()  { document.getElementById('custModal').classList.add('open');    document.body.style.overflow = 'hidden'; }
function closeModal() { document.getElementById('custModal').classList.remove('open'); document.body.style.overflow = ''; }
function handleOverlayClick(e) { if (e.target === document.getElementById('custModal')) closeModal(); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php
/**
 * RiderSafe — Cron Job: Missed Ping Detector
 * ============================================
 * Run this every minute via cron:
 *   * * * * * php /path/to/RIDERSAFE_Project/cron_check_pings.php >> /var/log/ridersafe_cron.log 2>&1
 *
 * What it does:
 *   - Finds all riders with an active trip whose last_ping_time is overdue
 *     (i.e. now > last_ping_time + ping_interval + grace_seconds)
 *   - Inserts a 'missed' ping record if one hasn't been inserted for that cycle yet
 *   - Notifies all linked contacts
 *   - Resets last_ping_time so the next cycle begins immediately
 */

// Allow CLI execution only (safety guard)
if (PHP_SAPI !== 'cli' && !isset($_GET['cron_key'])) {
    http_response_code(403);
    exit('Forbidden. Run via CLI or provide cron_key.');
}

// Optional secret key guard for HTTP-based cron triggers (e.g. cPanel cron via URL)
// Set RIDERSAFE_CRON_KEY in your environment or hardcode here
$expected_key = getenv('RIDERSAFE_CRON_KEY') ?: 'change_this_secret_key';
if (PHP_SAPI !== 'cli' && ($_GET['cron_key'] ?? '') !== $expected_key) {
    http_response_code(403);
    exit('Invalid cron key.');
}

require_once __DIR__ . '/config/db.php';

$grace_seconds = 10; // Must match GRACE_SECONDS in button_page.php

$now = time();
$log = [];

// ── Find overdue riders ──────────────────────────────────────────────────────
// Criteria: system_active = 1 AND last_ping_time is set
//           AND (NOW - last_ping_time) > ping_interval + grace_seconds
// We use UNIX_TIMESTAMP for accurate second-level comparison.
$query = $conn->prepare("
    SELECT rs.rider_id, rs.ping_interval, rs.last_ping_time,
           u.fullname
    FROM rider_settings rs
    JOIN users u ON u.id = rs.rider_id
    WHERE rs.system_active = 1
      AND rs.last_ping_time IS NOT NULL
      AND UNIX_TIMESTAMP(NOW()) > UNIX_TIMESTAMP(rs.last_ping_time) + rs.ping_interval + ?
");
$query->bind_param('i', $grace_seconds);
$query->execute();
$overdue = $query->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($overdue as $rider) {
    $rider_id   = (int)$rider['rider_id'];
    $rider_name = $rider['fullname'];

    // ── Guard: make sure we haven't already recorded a missed ping for this cycle ──
    // A cycle boundary = floor((now - tripStart) / interval) * interval + tripStart
    // Simpler: just check if the very latest ping for this rider was inserted after last_ping_time
    // If last ping after last_ping_time already exists → cycle was handled (browser was open)
    $check = $conn->prepare("
        SELECT id FROM pings
        WHERE rider_id = ?
          AND created_at > last_ping_time_val
        LIMIT 1
    ");
    // Use a subquery instead to avoid PHP variable complexity
    $check = $conn->prepare("
        SELECT p.id FROM pings p
        JOIN rider_settings rs ON rs.rider_id = p.rider_id
        WHERE p.rider_id = ?
          AND p.created_at >= rs.last_ping_time
        ORDER BY p.created_at DESC
        LIMIT 1
    ");
    $check->bind_param('i', $rider_id); $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        // A ping already exists for this cycle — skip
        $log[] = "[SKIP] Rider #{$rider_id} ({$rider_name}) — ping already recorded for this cycle.";
        continue;
    }

    // ── Insert missed ping ───────────────────────────────────────────────────
    $ins = $conn->prepare("INSERT INTO pings (rider_id, latitude, longitude, status) VALUES (?, NULL, NULL, 'missed')");
    $ins->bind_param('i', $rider_id); $ins->execute();

    // ── Advance last_ping_time by one interval so next cycle is correct ──────
    $advance = $conn->prepare("
        UPDATE rider_settings
        SET last_ping_time = DATE_ADD(last_ping_time, INTERVAL ping_interval SECOND)
        WHERE rider_id = ?
    ");
    $advance->bind_param('i', $rider_id); $advance->execute();

    // ── Notify all linked contacts ───────────────────────────────────────────
    $notif_msg = "⚠️ {$rider_name} missed their safety check-in. Please check on them. (Detected server-side)";
    $notif = $conn->prepare("
        INSERT INTO notifications (user_id, type, message)
        SELECT contact_id, 'ping_missed', ?
        FROM contact_links
        WHERE rider_id = ? AND status = 'accepted'
    ");
    $notif->bind_param('si', $notif_msg, $rider_id);
    $notif->execute();
    $alerted = $notif->affected_rows;

    $log[] = "[MISSED] Rider #{$rider_id} ({$rider_name}) — {$alerted} contact(s) notified. " . date('Y-m-d H:i:s');
}

if (empty($overdue)) {
    $log[] = "[OK] No overdue riders at " . date('Y-m-d H:i:s');
}

echo implode("\n", $log) . "\n";

<?php require_once __DIR__ . '/../config/session.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RiderSafe — Ride Safe, Stay Connected</title>
    <meta name="theme-color" content="#2a6b8a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="RiderSafe">
    <link rel="manifest" href="/RIDERSAFE_Project/manifest.json">
    <link rel="icon" type="image/png" href="/RIDERSAFE_Project/assets/logo.png">
    <link rel="shortcut icon" type="image/png" href="/RIDERSAFE_Project/assets/logo.png">
    <link rel="apple-touch-icon" href="/RIDERSAFE_Project/assets/logo.png">
    <link rel="stylesheet" href="/RIDERSAFE_Project/css/styles.css">
    <script defer src="/RIDERSAFE_Project/js/main.js"></script>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/RIDERSAFE_Project/sw.js')
                .then(reg => console.log('[SW] Registered:', reg.scope))
                .catch(err => console.warn('[SW] Registration failed:', err));
        });
    }
    </script>

    <?php
    if (isset($_SESSION['user_id']) && isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'rider'):
        $uid = (int)$_SESSION['user_id'];
    ?>
    <script>
    (function() {
        const USER_ID      = <?php echo $uid; ?>;
        const DEADLINE_KEY = 'rs_deadline_' + USER_ID;

        function getDeadline() { return parseInt(localStorage.getItem(DEADLINE_KEY) || '0'); }

        function fireNotification() {
            try {
                const snd = new Audio('/RIDERSAFE_Project/notif_sound.mp3');
                snd.play().catch(() => {});
            } catch(e) {}

            if ('Notification' in window && Notification.permission === 'granted') {
                if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({
                        type: 'SHOW_NOTIFICATION',
                        title: '⏰ RiderSafe — Check-in Due!',
                        body:  'Your safety check-in timer has run out. Open the Safety Button now!',
                        icon:  '/RIDERSAFE_Project/assets/logo.png',
                        tag:   'ridersafe-checkin'
                    });
                } else {
                    const n = new Notification('⏰ RiderSafe — Check-in Due!', {
                        body:               'Your safety check-in timer has run out. Open the Safety Button now!',
                        icon:               '/RIDERSAFE_Project/assets/logo.png',
                        tag:                'ridersafe-checkin',
                        requireInteraction: true
                    });
                    n.onclick = () => {
                        window.focus();
                        window.location.href = '/RIDERSAFE_Project/button_page.php';
                        n.close();
                    };
                }
            }
        }

        function scheduleCheck() {
            const deadline = getDeadline();
            if (!deadline) {
                setTimeout(scheduleCheck, 5000);
                return;
            }
            const msLeft = deadline - Date.now();
            if (msLeft <= 0) {
                const lastFired = parseInt(localStorage.getItem('rs_notif_fired_' + USER_ID) || '0');
                if (lastFired !== deadline) {
                    localStorage.setItem('rs_notif_fired_' + USER_ID, deadline);
                    fireNotification();
                }
                setTimeout(scheduleCheck, 2000);
            } else {
                setTimeout(() => {
                    const lastFired = parseInt(localStorage.getItem('rs_notif_fired_' + USER_ID) || '0');
                    const dl = getDeadline();
                    if (lastFired !== dl && dl <= Date.now() + 500) {
                        localStorage.setItem('rs_notif_fired_' + USER_ID, dl);
                        fireNotification();
                    }
                    scheduleCheck();
                }, msLeft);
            }
        }

        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        setTimeout(scheduleCheck, 1000);
    })();
    </script>
    <?php endif; ?>
</head>
<body>
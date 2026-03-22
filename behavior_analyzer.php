    <?php
    require_once __DIR__ . '/config/db.php';

    function detectUnusualBehavior($rider_id, $conn) {
        // 1. Get average missed pings per day (History)
        $history_query = "SELECT COUNT(id) / COUNT(DISTINCT DATE(created_at)) as avg_missed 
                        FROM pings 
                        WHERE rider_id = ? AND status = 'missed' 
                        AND created_at < CURDATE()";
        $stmt = $conn->prepare($history_query);
        $stmt->bind_param("i", $rider_id);
        $stmt->execute();
        $avg_missed = $stmt->get_result()->fetch_assoc()['avg_missed'] ?? 0;

        // 2. Get missed pings for TODAY
        $today_query = "SELECT COUNT(id) as today_missed 
                        FROM pings 
                        WHERE rider_id = ? AND status = 'missed' 
                        AND DATE(created_at) = CURDATE()";
        $stmt_today = $conn->prepare($today_query);
        $stmt_today->bind_param("i", $rider_id);
        $stmt_today->execute();
        $today_missed = $stmt_today->get_result()->fetch_assoc()['today_missed'];

        // 3. Logic: If today is 2x worse than average, flag it
        if ($today_missed > ($avg_missed * 2) && $today_missed > 5) {
            return true; // Unusual behavior detected
        }
        return false;
    }
    ?>
<?php
// Shared helpers for OrganBridge protected pages.

function require_role($role) {
    session_start();
    if (!isset($_SESSION['email'])) {
        header("Location: auth.php");
        exit();
    }
    if (($_SESSION['role'] ?? '') !== $role) {
        $dashboard = ($_SESSION['role'] ?? '') === 'coordinator' ? 'coordinator_dashboard.php' : 'hospital_dashboard.php';
        header("Location: $dashboard");
        exit();
    }
}

function current_user_name($fallback = 'User') {
    return $_SESSION['full_name'] ?? $_SESSION['name'] ?? $_SESSION['patient_name'] ?? $fallback;
}

function unread_count($conn, $userId) {
    $userId = intval($userId);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM notifications WHERE user_id = $userId AND is_read = 0"));
    return intval($row['c'] ?? 0);
}

function notify_user($conn, $userId, $message, $title = 'OrganBridge Update', $type = 'status_update', $url = null) {
    $userId = intval($userId);
    $titleSafe = mysqli_real_escape_string($conn, $title);
    $messageSafe = mysqli_real_escape_string($conn, $message);
    $typeSafe = mysqli_real_escape_string($conn, $type);
    $urlSql = $url === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $url) . "'";
    mysqli_query($conn, "INSERT INTO notifications (user_id, notification_type, title, message, action_url)
                         VALUES ($userId, '$typeSafe', '$titleSafe', '$messageSafe', $urlSql)");
}

function notify_coordinators($conn, $message, $title = 'Coordinator Alert', $type = 'status_update', $url = null) {
    $users = mysqli_query($conn, "SELECT id FROM users WHERE role = 'coordinator'");
    while ($user = mysqli_fetch_assoc($users)) {
        notify_user($conn, $user['id'], $message, $title, $type, $url);
    }
}

function blood_compatible_flow($donor, $recipient) {
    $map = [
        'O-' => ['O-','O+','A-','A+','B-','B+','AB-','AB+'],
        'O+' => ['O+','A+','B+','AB+'],
        'A-' => ['A-','A+','AB-','AB+'],
        'A+' => ['A+','AB+'],
        'B-' => ['B-','B+','AB-','AB+'],
        'B+' => ['B+','AB+'],
        'AB-' => ['AB-','AB+'],
        'AB+' => ['AB+']
    ];
    return in_array($recipient, $map[$donor] ?? [], true);
}

function city_distance_km($a, $b) {
    if (strtolower(trim($a)) === strtolower(trim($b))) {
        return 10;
    }
    return 250;
}

function role_accent() {
    $role = $_SESSION['role'] ?? 'hospital';
    if ($role === 'coordinator') {
        return '#7c3aed';
    }
    return '#0f7b8c';
}

function score_bar($score) {
    $score = max(0, min(100, floatval($score)));
    $class = $score >= 75 ? 'score-green' : ($score >= 50 ? 'score-amber' : 'score-red');
    return '<div class="score-wrap"><div class="score-bar ' . $class . '" style="--score-width:' . $score . '%;width:' . $score . '%"></div><span>' . round($score, 1) . '%</span></div>';
}

function run_organ_expiry_check($conn) {
    mysqli_query($conn, "UPDATE organ_listings SET status='Expired' WHERE status='Available' AND viable_until IS NOT NULL AND viable_until < NOW()");
    mysqli_query($conn, "UPDATE organs SET availability_status='expired' WHERE id IN (SELECT id FROM organ_listings WHERE status='Expired')");

    $soon = mysqli_query($conn, "SELECT ol.*, h.name AS hospital_name
        FROM organ_listings ol
        JOIN hospitals h ON ol.hospital_id = h.id
        WHERE ol.status='Available'
          AND ol.viable_until IS NOT NULL
          AND ol.viable_until BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 6 HOUR)");
    while ($row = mysqli_fetch_assoc($soon)) {
        $hours = max(0, round((strtotime($row['viable_until']) - time()) / 3600, 1));
        $message = $row['organ_type'] . " (" . $row['blood_group'] . ") from " . $row['hospital_name'] . " expiring in " . $hours . " hours - match urgently";
        $safeMessage = mysqli_real_escape_string($conn, $message);
        $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM notifications WHERE title='Organ expiring soon' AND message='$safeMessage' AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR) LIMIT 1"));
        if (!$exists) {
            notify_coordinators($conn, $message, 'Organ expiring soon', 'status_update', 'coordinator_dashboard.php');
        }
    }
}

function ensure_transfer_events_table($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS transfer_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transfer_id INT NOT NULL,
        status VARCHAR(50) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    mysqli_query($conn, "ALTER TABLE transfer_events ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL");
    mysqli_query($conn, "ALTER TABLE transfer_events ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL");
    mysqli_query($conn, "ALTER TABLE transfer_events ADD COLUMN IF NOT EXISTS location_label VARCHAR(180) NULL");
}

function ensure_transfer_tracking_columns($conn) {
    ensure_transfer_events_table($conn);
    mysqli_query($conn, "ALTER TABLE transfers ADD COLUMN IF NOT EXISTS current_lat DECIMAL(10,7) NULL");
    mysqli_query($conn, "ALTER TABLE transfers ADD COLUMN IF NOT EXISTS current_lng DECIMAL(10,7) NULL");
    mysqli_query($conn, "ALTER TABLE transfers ADD COLUMN IF NOT EXISTS current_location VARCHAR(180) NULL");
    mysqli_query($conn, "ALTER TABLE transfers ADD COLUMN IF NOT EXISTS last_gps_at DATETIME NULL");
}

function city_coordinates($city) {
    $map = [
        'chennai' => [13.0827, 80.2707],
        'bengaluru' => [12.9716, 77.5946],
        'bangalore' => [12.9716, 77.5946],
        'hyderabad' => [17.3850, 78.4867],
        'kochi' => [9.9312, 76.2673],
        'cochin' => [9.9312, 76.2673]
    ];
    $key = strtolower(trim($city ?? ''));
    return $map[$key] ?? [20.5937, 78.9629];
}

function transfer_position($row) {
    if ($row && $row['current_lat'] !== null && $row['current_lng'] !== null && $row['current_lat'] !== '' && $row['current_lng'] !== '') {
        return [floatval($row['current_lat']), floatval($row['current_lng']), $row['current_location'] ?: 'Live GPS location'];
    }
    $origin = city_coordinates($row['donor_city'] ?? '');
    $destination = city_coordinates($row['recipient_city'] ?? '');
    $status = $row['status'] ?? '';
    if (in_array($status, ['Received','Surgery Scheduled','Completed'], true)) {
        return [$destination[0], $destination[1], ($row['recipient_city'] ?? 'Recipient hospital')];
    }
    if ($status === 'In Transit') {
        return [($origin[0] + $destination[0]) / 2, ($origin[1] + $destination[1]) / 2, 'Estimated in-transit location'];
    }
    return [$origin[0], $origin[1], ($row['donor_city'] ?? 'Donor hospital')];
}

function record_transfer_event($conn, $transferId, $status, $notes = '', $lat = null, $lng = null, $location = null) {
    ensure_transfer_events_table($conn);
    $transferId = intval($transferId);
    $statusSafe = mysqli_real_escape_string($conn, $status);
    $notesSafe = mysqli_real_escape_string($conn, $notes);
    $latSql = is_numeric($lat) ? floatval($lat) : 'NULL';
    $lngSql = is_numeric($lng) ? floatval($lng) : 'NULL';
    $locationSql = $location === null || trim($location) === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, trim($location)) . "'";
    mysqli_query($conn, "INSERT INTO transfer_events (transfer_id, status, notes, latitude, longitude, location_label)
                         VALUES ($transferId, '$statusSafe', '$notesSafe', $latSql, $lngSql, $locationSql)");
}

function page_header($title, $name, $backUrl = null) {
    $userId = intval($_SESSION['user_id'] ?? 0);
    global $conn;
    static $expiryChecked = false;
    if ($conn && !$expiryChecked) {
        run_organ_expiry_check($conn);
        $expiryChecked = true;
    }
    $count = $conn ? unread_count($conn, $userId) : 0;
    echo '<header class="topbar" style="border-top:4px solid ' . role_accent() . '">';
    echo '<div class="logo-row"><div class="logo-icon">OB</div><div class="logo-name">OrganBridge</div></div>';
    echo '<div class="topbar-right">';
    echo '<span class="user-pill">' . htmlspecialchars($name) . '</span>';
    echo '<span class="user-pill" style="border-color:' . role_accent() . ';color:' . role_accent() . '">' . htmlspecialchars(ucfirst($_SESSION['role'] ?? 'user')) . '</span>';
    echo '<a href="notifications.php" class="bell-link" aria-label="Notifications"><span class="bell-icon">Alerts</span><span class="count-badge">' . $count . '</span></a>';
    if ($backUrl) {
        echo '<a href="' . htmlspecialchars($backUrl) . '" class="btn btn-outline">Back</a>';
    }
    echo '<a href="logout.php" class="btn btn-teal">Logout</a>';
    echo '</div></header>';
    if (!empty($_SESSION['notification'])) {
        echo '<div class="flash-alert">' . htmlspecialchars($_SESSION['notification']) . '</div>';
        unset($_SESSION['notification']);
    }
}

function html_head($title) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>';
    echo '<title>' . htmlspecialchars($title) . ' - OrganBridge</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"/>';
    echo '<link rel="stylesheet" href="style.css"/></head><body>';
}

function html_end() {
    echo '</body></html>';
}
?>

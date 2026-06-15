<?php
require_once 'ob_flow.php';
session_start();
if (!isset($_SESSION['email'])) { header("Location: auth.php"); exit(); }
include 'db.php';
ensure_transfer_events_table($conn);
ensure_transfer_tracking_columns($conn);
$id = intval($_GET['id'] ?? 0);
$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT t.*, am.match_score, am.decision, ol.organ_type, ol.blood_group AS donor_blood, ol.city AS donor_city, h.name AS hospital_name, req.patient_name, req.blood_group AS recipient_blood, req.city AS recipient_city
    FROM transfers t
    JOIN ai_matches am ON t.match_id=am.id
    JOIN organ_listings ol ON am.organ_id=ol.id
    JOIN hospitals h ON ol.hospital_id=h.id
    JOIN recipients req ON am.recipient_id=req.id
    WHERE t.id=$id LIMIT 1"));
$back = ($_SESSION['role'] ?? '') === 'hospital' ? 'hospital_dashboard.php' : 'transfers.php';
$events = mysqli_query($conn, "SELECT * FROM transfer_events WHERE transfer_id=$id ORDER BY created_at ASC, id ASC");
$position = $row ? transfer_position($row) : [20.5937, 78.9629, 'Unknown location'];
$origin = $row ? city_coordinates($row['donor_city']) : [20.5937, 78.9629];
$destination = $row ? city_coordinates($row['recipient_city']) : [20.5937, 78.9629];
html_head('Transfer Detail');
page_header('Transfer Detail', current_user_name('User'), $back);
?>
<main class="main">
    <div class="page-header"><h1>Transfer Detail</h1><p>Full timeline for one transfer.</p></div>
    <?php if (!$row): ?><div class="alert alert-error">Transfer not found.</div><?php else: ?>
    <div class="card">
        <h3><?php echo htmlspecialchars($row['organ_type']); ?> transfer</h3>
        <p><strong>Hospital:</strong> <?php echo htmlspecialchars($row['hospital_name']); ?>, <?php echo htmlspecialchars($row['donor_city']); ?></p>
        <p><strong>Recipient:</strong> <?php echo htmlspecialchars($row['patient_name']); ?>, <?php echo htmlspecialchars($row['recipient_city']); ?></p>
        <p><strong>Blood:</strong> <?php echo htmlspecialchars($row['donor_blood']); ?> donor to <?php echo htmlspecialchars($row['recipient_blood']); ?> recipient</p>
        <p><strong>AI Score:</strong> <?php echo round($row['match_score'], 1); ?>%</p>
        <p><strong>Current Status:</strong> <?php echo htmlspecialchars($row['status']); ?></p>
        <p><strong>Notes:</strong> <?php echo htmlspecialchars($row['notes'] ?? 'No notes yet.'); ?></p>
        <p><strong>Last Updated:</strong> <?php echo htmlspecialchars($row['updated_at']); ?></p>
    </div>
    <div class="card">
        <h3 class="section-title">GPS Tracking</h3>
        <div class="gps-grid">
            <div class="gps-panel">
                <span class="best-label">Current Location</span>
                <strong><?php echo htmlspecialchars($position[2]); ?></strong>
                <p><?php echo htmlspecialchars(number_format($position[0], 6) . ', ' . number_format($position[1], 6)); ?></p>
                <p><strong>Route:</strong> <?php echo htmlspecialchars($row['donor_city']); ?> -> <?php echo htmlspecialchars($row['recipient_city']); ?></p>
                <p><strong>Last GPS Update:</strong> <?php echo htmlspecialchars($row['last_gps_at'] ?? 'Not updated yet'); ?></p>
                <div class="actions">
                    <a class="btn btn-teal" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($position[0] . ',' . $position[1]); ?>">Open Current Map</a>
                    <a class="btn btn-outline" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&origin=<?php echo urlencode($origin[0] . ',' . $origin[1]); ?>&destination=<?php echo urlencode($destination[0] . ',' . $destination[1]); ?>&travelmode=driving">Open Route</a>
                </div>
            </div>
            <iframe class="gps-map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.openstreetmap.org/export/embed.html?bbox=<?php echo urlencode(($position[1] - 0.08) . ',' . ($position[0] - 0.08) . ',' . ($position[1] + 0.08) . ',' . ($position[0] + 0.08)); ?>&layer=mapnik&marker=<?php echo urlencode($position[0] . ',' . $position[1]); ?>"></iframe>
        </div>
    </div>
    <div class="card">
        <h3 class="section-title">Timeline</h3>
        <div class="timeline">
            <?php
            $steps = ['Pending','Approved','In Transit','Received','Surgery Scheduled','Completed'];
            $currentIndex = array_search($row['status'], $steps, true);
            if ($currentIndex === false) $currentIndex = 0;
            foreach ($steps as $i => $step):
                $class = $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : '');
            ?>
                <div class="timeline-step <?php echo $class; ?>"><?php echo htmlspecialchars($step); ?></div>
            <?php endforeach; ?>
            <?php if ($row['status'] === 'Failed' || $row['status'] === 'Rejected'): ?>
                <div class="timeline-step current"><?php echo htmlspecialchars($row['status']); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <h3 class="section-title">Status History</h3>
        <div class="event-list">
            <?php if ($events && mysqli_num_rows($events) > 0): while ($event = mysqli_fetch_assoc($events)): ?>
                <div class="event-item">
                    <strong><?php echo htmlspecialchars($event['status']); ?></strong>
                    <span><?php echo htmlspecialchars($event['created_at']); ?><?php if ($event['latitude'] !== null && $event['longitude'] !== null): ?> | GPS: <?php echo htmlspecialchars($event['latitude'] . ', ' . $event['longitude']); ?><?php endif; ?></span>
                    <?php if (!empty($event['location_label'])): ?><p><strong>Location:</strong> <?php echo htmlspecialchars($event['location_label']); ?></p><?php endif; ?>
                    <p><?php echo htmlspecialchars($event['notes'] ?: 'No notes added.'); ?></p>
                </div>
            <?php endwhile; else: ?>
                <div class="event-item"><strong><?php echo htmlspecialchars($row['status']); ?></strong><span><?php echo htmlspecialchars($row['updated_at']); ?></span><p><?php echo htmlspecialchars($row['notes'] ?: 'No notes added.'); ?></p></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>
<?php html_end(); ?>

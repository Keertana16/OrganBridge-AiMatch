<?php
require_once 'ob_flow.php';
require_role('coordinator');
include 'db.php';
ensure_transfer_events_table($conn);
ensure_transfer_tracking_columns($conn);
$message = '';
$next = ['Approved' => 'In Transit', 'In Transit' => 'Received', 'Received' => 'Surgery Scheduled', 'Surgery Scheduled' => 'Completed'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['transfer_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $note = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
    $gpsLat = trim($_POST['gps_lat'] ?? '');
    $gpsLng = trim($_POST['gps_lng'] ?? '');
    $gpsLocation = trim($_POST['gps_location'] ?? '');
    $allowed = ['Pending','Approved','In Transit','Received','Surgery Scheduled','Completed','Failed','Rejected'];
    if (in_array($status, $allowed, true)) {
        $details = mysqli_fetch_assoc(mysqli_query($conn, "SELECT t.*, am.organ_id, am.recipient_id, ol.hospital_id AS donor_hospital_id, req.hospital_id AS recipient_hospital_id
                , ol.city AS donor_city, req.city AS recipient_city
            FROM transfers t
            JOIN ai_matches am ON t.match_id=am.id
            JOIN organ_listings ol ON am.organ_id=ol.id
            JOIN recipients req ON am.recipient_id=req.id
            WHERE t.id=$id"));
        $lat = is_numeric($gpsLat) ? floatval($gpsLat) : null;
        $lng = is_numeric($gpsLng) ? floatval($gpsLng) : null;
        if (($lat === null || $lng === null) && $details) {
            $fallback = [
                'status' => $status,
                'donor_city' => $details['donor_city'] ?? '',
                'recipient_city' => $details['recipient_city'] ?? '',
                'current_lat' => null,
                'current_lng' => null,
                'current_location' => null
            ];
            [$lat, $lng, $autoLocation] = transfer_position($fallback);
            if ($gpsLocation === '') {
                $gpsLocation = $autoLocation;
            }
        }
        $locationSafe = mysqli_real_escape_string($conn, $gpsLocation);
        $latSql = is_numeric($lat) ? floatval($lat) : 'NULL';
        $lngSql = is_numeric($lng) ? floatval($lng) : 'NULL';
        mysqli_query($conn, "UPDATE transfers
            SET status='$status',
                notes='$note',
                current_lat=$latSql,
                current_lng=$lngSql,
                current_location=" . ($gpsLocation === '' ? 'NULL' : "'$locationSafe'") . ",
                last_gps_at=NOW()
            WHERE id=$id");
        record_transfer_event($conn, $id, $status, $note, $lat, $lng, $gpsLocation);
        if ($details && $status === 'Completed') {
            mysqli_query($conn, "UPDATE organ_listings SET status='Transferred' WHERE id=" . intval($details['organ_id']));
            mysqli_query($conn, "UPDATE organs SET availability_status='transferred' WHERE id=" . intval($details['organ_id']));
            mysqli_query($conn, "UPDATE recipients SET status='Fulfilled' WHERE id=" . intval($details['recipient_id']));
        }
        if ($details) {
            $recipientUser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM hospitals WHERE id=" . intval($details['recipient_hospital_id'])));
            $hospitalUser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM hospitals WHERE id=" . intval($details['donor_hospital_id'])));
            $title = $status === 'Completed' ? 'Transfer completed' : 'Transfer status updated';
            $msg = $status === 'Completed' ? 'Transfer completed successfully' : "Transfer status updated to: $status";
            if ($recipientUser) notify_user($conn, $recipientUser['user_id'], $msg, $title, $status === 'Completed' ? 'transfer_completed' : 'status_update', "transfer_detail.php?id=$id");
            if ($hospitalUser) notify_user($conn, $hospitalUser['user_id'], $msg, $title, $status === 'Completed' ? 'transfer_completed' : 'status_update', "transfer_detail.php?id=$id");
        }
        $message = "Transfer status and GPS location updated.";
    }
}
$rows = mysqli_query($conn, "SELECT t.*, am.match_score, ol.organ_type, h.name AS hospital_name, req.patient_name, ol.city AS donor_city, req.city AS recipient_city
    FROM transfers t
    JOIN ai_matches am ON t.match_id=am.id
    JOIN organ_listings ol ON am.organ_id=ol.id
    JOIN hospitals h ON ol.hospital_id=h.id
    JOIN recipients req ON am.recipient_id=req.id
    ORDER BY t.updated_at DESC");
html_head('Transfers');
page_header('Transfers', current_user_name('Coordinator'), 'coordinator_dashboard.php');
?>
<main class="main">
    <div class="page-header"><h1>Transfer Tracking</h1><p>Move transfers from approval through completion.</p></div>
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <div class="table-card"><div class="table-scroll"><table class="data-table">
        <thead><tr><th>ID</th><th>Organ</th><th>Recipient</th><th>Hospital</th><th>Score</th><th>Status</th><th>Update</th><th>Detail</th></tr></thead><tbody>
        <?php while ($r = mysqli_fetch_assoc($rows)): $nextStatus = $next[$r['status']] ?? null; ?>
            <?php [$lat, $lng, $label] = transfer_position($r); ?>
            <tr><td>#<?php echo $r['id']; ?></td><td><?php echo htmlspecialchars($r['organ_type']); ?></td><td><?php echo htmlspecialchars($r['patient_name']); ?></td><td><?php echo htmlspecialchars($r['hospital_name']); ?></td><td><?php echo score_bar($r['match_score']); ?></td><td><span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $r['status'])); ?>"><?php echo htmlspecialchars($r['status']); ?></span><br><span class="muted-text"><?php echo htmlspecialchars($label); ?></span></td><td><?php if ($nextStatus): ?><form method="POST" class="transfer-gps-form"><input type="hidden" name="transfer_id" value="<?php echo $r['id']; ?>"/><input type="hidden" name="status" value="<?php echo $nextStatus; ?>"/><input type="hidden" name="gps_lat"/><input type="hidden" name="gps_lng"/><input type="text" name="gps_location" placeholder="Current place / ambulance note"/><input type="text" name="notes" placeholder="Status note"/><button type="button" class="btn btn-outline use-gps">Use My GPS</button><button class="btn btn-teal">Mark <?php echo $nextStatus; ?></button></form><?php if ($r['status'] !== 'Completed'): ?><form method="POST"><input type="hidden" name="transfer_id" value="<?php echo $r['id']; ?>"/><input type="hidden" name="status" value="Failed"/><button class="btn btn-red">Failed</button></form><?php endif; ?><?php else: ?>No action<?php endif; ?></td><td><a class="btn btn-outline" href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($lat . ',' . $lng); ?>" target="_blank" rel="noopener">Map</a><a class="btn btn-outline" href="transfer_detail.php?id=<?php echo $r['id']; ?>">Open</a></td></tr>
        <?php endwhile; ?>
        </tbody></table></div></div>
</main>
<script>
document.querySelectorAll('.use-gps').forEach(function(button) {
    button.addEventListener('click', function() {
        const form = button.closest('form');
        if (!navigator.geolocation) {
            alert('GPS is not available in this browser.');
            return;
        }
        button.textContent = 'Getting GPS...';
        navigator.geolocation.getCurrentPosition(function(position) {
            form.querySelector('[name="gps_lat"]').value = position.coords.latitude.toFixed(7);
            form.querySelector('[name="gps_lng"]').value = position.coords.longitude.toFixed(7);
            const location = form.querySelector('[name="gps_location"]');
            if (!location.value) {
                location.value = 'Live GPS from coordinator device';
            }
            button.textContent = 'GPS Added';
        }, function() {
            alert('Could not read GPS location. You can type the place manually.');
            button.textContent = 'Use My GPS';
        }, {enableHighAccuracy:true, timeout:8000});
    });
});
</script>
<?php html_end(); ?>

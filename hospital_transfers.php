<?php
require_once 'ob_flow.php';
require_role('hospital');
include 'db.php';
ensure_transfer_tracking_columns($conn);
$hospitalId = intval($_SESSION['hospital_id'] ?? 0);
$rows = mysqli_query($conn, "SELECT t.*, am.match_score, ol.organ_type, ol.blood_group, ol.city AS donor_city, donor.name AS donor_hospital, req.patient_name, req.organ_needed, req.city AS recipient_city, req.hospital_id AS recipient_hospital_id
    FROM transfers t
    JOIN ai_matches am ON t.match_id = am.id
    JOIN organ_listings ol ON am.organ_id = ol.id
    JOIN hospitals donor ON ol.hospital_id = donor.id
    JOIN recipients req ON am.recipient_id = req.id
    WHERE ol.hospital_id = $hospitalId OR req.hospital_id = $hospitalId
    ORDER BY t.updated_at DESC");
html_head('My Transfers');
page_header('My Transfers', current_user_name('Hospital'), 'hospital_dashboard.php');
?>
<main class="main">
    <div class="page-header"><h1>My Transfers</h1><p>Transfers connected to your donated organs or your recipient patients.</p></div>
    <div class="table-card"><div class="table-scroll"><table class="data-table">
        <thead><tr><th>Organ</th><th>Patient</th><th>Role</th><th>Score</th><th>Status Timeline</th><th>GPS</th><th>Updated</th><th>Detail</th></tr></thead><tbody>
        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($r = mysqli_fetch_assoc($rows)): ?>
            <?php
                $role = intval($r['recipient_hospital_id']) === $hospitalId ? 'Recipient Hospital' : 'Donor Hospital';
                $steps = ['Approved','In Transit','Received','Surgery Scheduled','Completed'];
                $idx = array_search($r['status'], $steps, true);
                if ($idx === false) $idx = -1;
                [$lat, $lng, $label] = transfer_position($r);
            ?>
            <tr>
                <td><?php echo htmlspecialchars($r['organ_type'] . ' (' . $r['blood_group'] . ')'); ?></td>
                <td><?php echo htmlspecialchars($r['patient_name']); ?></td>
                <td><?php echo htmlspecialchars($role); ?></td>
                <td><?php echo score_bar($r['match_score']); ?></td>
                <td><div class="timeline"><?php foreach ($steps as $i => $step): ?><div class="timeline-step <?php echo $i < $idx ? 'done' : ($i === $idx ? 'current' : ''); ?>"><?php echo htmlspecialchars($step); ?></div><?php endforeach; ?><?php if ($r['status'] === 'Failed'): ?><div class="timeline-step current">Failed</div><?php endif; ?></div></td>
                <td><span class="muted-text"><?php echo htmlspecialchars($label); ?></span><br><a class="btn btn-outline" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($lat . ',' . $lng); ?>">Map</a></td>
                <td><?php echo htmlspecialchars($r['updated_at']); ?></td>
                <td><a class="btn btn-outline" href="transfer_detail.php?id=<?php echo $r['id']; ?>">Open</a></td>
            </tr>
        <?php endwhile; else: ?><tr><td colspan="8">No transfers yet.</td></tr><?php endif; ?>
        </tbody></table></div></div>
</main>
<?php html_end(); ?>

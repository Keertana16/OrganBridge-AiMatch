<?php
require_once 'ob_flow.php';
require_role('coordinator');
include 'db.php';
$coordinatorId = intval($_SESSION['coordinator_id'] ?? 0);
$userId = intval($_SESSION['user_id'] ?? 0);
$organFilter = trim($_GET['organ_type'] ?? '');
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matchId = intval($_POST['match_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $match = mysqli_fetch_assoc(mysqli_query($conn, "SELECT am.*, ol.hospital_id AS donor_hospital_id, req.hospital_id AS recipient_hospital_id
        FROM ai_matches am
        JOIN organ_listings ol ON am.organ_id = ol.id
        JOIN recipients req ON am.recipient_id = req.id
        WHERE am.id = $matchId LIMIT 1"));
    if ($match && $action === 'approve') {
        $autoRejectReason = "Organ already approved for another recipient; only one organ is available.";
        $autoRejectReasonSafe = mysqli_real_escape_string($conn, $autoRejectReason);
        mysqli_query($conn, "UPDATE ai_matches SET decision='Approved', match_status='approved', coordinator_id=$coordinatorId, evaluated_at=NOW() WHERE id=$matchId");
        $otherMatches = mysqli_query($conn, "SELECT am.*, req.hospital_id AS recipient_hospital_id
            FROM ai_matches am
            JOIN recipients req ON am.recipient_id = req.id
            WHERE am.organ_id = " . intval($match['organ_id']) . "
              AND am.id <> $matchId
              AND am.decision = 'Pending'");
        $autoRejected = 0;
        while ($other = mysqli_fetch_assoc($otherMatches)) {
            $otherId = intval($other['id']);
            mysqli_query($conn, "UPDATE ai_matches
                SET decision='Rejected',
                    match_status='rejected',
                    rejection_reason='$autoRejectReasonSafe',
                    coordinator_id=$coordinatorId,
                    evaluated_at=NOW()
                WHERE id=$otherId");
            $otherHospitalUser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM hospitals WHERE id=" . intval($other['recipient_hospital_id'])));
            if ($otherHospitalUser) {
                notify_user($conn, $otherHospitalUser['user_id'], "Patient match was automatically rejected. Reason: $autoRejectReason", "Match auto-rejected", "transfer_rejected", "hospital_dashboard.php");
            }
            $autoRejected++;
        }
        mysqli_query($conn, "UPDATE organ_listings SET status='Matched' WHERE id=" . intval($match['organ_id']));
        mysqli_query($conn, "UPDATE organs SET availability_status='matched' WHERE id=" . intval($match['organ_id']));
        mysqli_query($conn, "UPDATE recipients SET status='Matched' WHERE id=" . intval($match['recipient_id']));
        $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM transfers WHERE match_id=$matchId LIMIT 1"));
        if (!$exists) {
            mysqli_query($conn, "INSERT INTO transfers (match_id, status, notes) VALUES ($matchId, 'Approved', 'Approved by coordinator')");
            $transferId = mysqli_insert_id($conn);
            record_transfer_event($conn, $transferId, 'Approved', 'Approved by coordinator');
        } else {
            record_transfer_event($conn, intval($exists['id']), 'Approved', 'Approved by coordinator');
        }
        $recipientHospitalUser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM hospitals WHERE id=" . intval($match['recipient_hospital_id'])));
        $donorHospitalUser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM hospitals WHERE id=" . intval($match['donor_hospital_id'])));
        if ($recipientHospitalUser) notify_user($conn, $recipientHospitalUser['user_id'], "Your patient has been matched and approved for transfer", "Match approved", "transfer_approved", "hospital_dashboard.php");
        if ($donorHospitalUser && (!$recipientHospitalUser || $donorHospitalUser['user_id'] != $recipientHospitalUser['user_id'])) notify_user($conn, $donorHospitalUser['user_id'], "Your organ has been matched and approved for transfer", "Match approved", "transfer_approved", "hospital_dashboard.php");
        $message = "Match approved and transfer created. Auto-rejected $autoRejected other pending match(es) for this same organ.";
    } elseif ($match && $action === 'reject') {
        $reason = mysqli_real_escape_string($conn, trim($_POST['rejection_reason'] ?? 'Rejected by coordinator'));
        mysqli_query($conn, "UPDATE ai_matches SET decision='Rejected', match_status='rejected', rejection_reason='$reason', coordinator_id=$coordinatorId, evaluated_at=NOW() WHERE id=$matchId");
        mysqli_query($conn, "UPDATE organ_listings SET status='Available' WHERE id=" . intval($match['organ_id']) . " AND status='Matched'");
        mysqli_query($conn, "UPDATE recipients SET status='Waiting' WHERE id=" . intval($match['recipient_id']) . " AND status='Matched'");
        $next = mysqli_fetch_assoc(mysqli_query($conn, "SELECT am.id, am.match_score, req.patient_name
            FROM ai_matches am
            JOIN recipients req ON am.recipient_id = req.id
            WHERE am.organ_id = " . intval($match['organ_id']) . "
              AND am.decision = 'Pending'
              AND am.id <> $matchId
            ORDER BY am.match_score DESC
            LIMIT 1"));
        $recipientHospitalUser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM hospitals WHERE id=" . intval($match['recipient_hospital_id'])));
        if ($recipientHospitalUser) notify_user($conn, $recipientHospitalUser['user_id'], "A patient AI match was rejected after coordinator review. Reason: $reason", "Match rejected", "transfer_rejected", "hospital_dashboard.php");
        $message = $next
            ? "Match rejected. Next ranked match is #{$next['id']} for {$next['patient_name']} at " . round($next['match_score'], 1) . "% and is pending review."
            : "Match rejected. No alternate pending match is available for this organ.";
    }
}
$whereOrgan = '';
if ($organFilter !== '') {
    $whereOrgan = " AND ol.organ_type='" . mysqli_real_escape_string($conn, $organFilter) . "'";
}
$rows = mysqli_query($conn, "SELECT am.*, ol.organ_type, ol.blood_group AS donor_blood, ol.donor_age, ol.city AS donor_city, ol.condition, dh.name AS hospital_name, req.patient_name, req.organ_needed, req.blood_group AS recipient_blood, req.age AS recipient_age, req.city AS recipient_city, req.urgency_level, req.waiting_days, rh.name AS recipient_hospital
    FROM ai_matches am
    JOIN organ_listings ol ON am.organ_id = ol.id
    JOIN recipients req ON am.recipient_id = req.id
    JOIN hospitals dh ON ol.hospital_id = dh.id
    JOIN hospitals rh ON req.hospital_id = rh.id
    WHERE am.decision = 'Pending'
    $whereOrgan
    ORDER BY ol.organ_type ASC, am.organ_id ASC, am.match_score DESC");
$matchesByOrgan = [];
while ($row = mysqli_fetch_assoc($rows)) {
    $matchesByOrgan[$row['organ_type']][] = $row;
}
$organTypes = mysqli_query($conn, "SELECT DISTINCT organ_type FROM organ_listings ORDER BY organ_type");
html_head('Review Matches');
page_header('Review Matches', current_user_name('Coordinator'), 'coordinator_dashboard.php');
?>
<main class="main">
    <div class="page-header"><h1>Review AI Matches</h1><p>Approve or reject ranked matches after human verification.</p></div>
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <form class="card filter-bar" method="GET">
        <div class="field"><label>Organ Type</label><select name="organ_type"><option value="">All organs</option><?php while ($type = mysqli_fetch_assoc($organTypes)): ?><option value="<?php echo htmlspecialchars($type['organ_type']); ?>" <?php echo $organFilter === $type['organ_type'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['organ_type']); ?></option><?php endwhile; ?></select></div>
        <div class="actions"><button class="btn btn-teal" type="submit">Show Organ</button><a class="btn btn-outline" href="review_matches.php">Show All</a></div>
    </form>
    <?php if (!empty($matchesByOrgan)): ?>
        <?php foreach ($matchesByOrgan as $organType => $organRows): ?>
            <?php $best = $organRows[0]; ?>
            <section class="organ-review-section" id="organ-<?php echo htmlspecialchars(strtolower($organType)); ?>">
                <div class="organ-section-header">
                    <div>
                        <h2><?php echo htmlspecialchars($organType); ?> Matches</h2>
                        <p><?php echo count($organRows); ?> pending match(es). Best match: <?php echo htmlspecialchars($best['patient_name']); ?> at <?php echo round(floatval($best['match_score']), 1); ?>%.</p>
                    </div>
                    <?php echo score_bar($best['match_score']); ?>
                </div>
                <div class="table-card"><div class="table-scroll"><table class="data-table review-table">
                    <thead><tr><th>ID</th><th>Organ Details</th><th>Donor Hospital</th><th>Recipient</th><th>Recipient Hospital</th><th>Blood</th><th>Urgency</th><th>Waiting</th><th>Distance</th><th>Score</th><th>Decision</th></tr></thead>
                    <tbody>
                    <?php foreach ($organRows as $r): ?>
                        <tr class="<?php echo intval($r['id']) === intval($best['id']) ? 'best-match-row' : ''; ?>">
                            <td>#<?php echo intval($r['id']); ?><?php if (intval($r['id']) === intval($best['id'])): ?><br><span class="badge badge-approved">Best</span><?php endif; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['organ_type']); ?></strong><br><span class="muted-text"><?php echo htmlspecialchars($r['condition'] . ', donor age ' . intval($r['donor_age']) . ', ' . $r['donor_city']); ?></span></td>
                            <td><?php echo htmlspecialchars($r['hospital_name']); ?></td>
                            <td><strong><?php echo htmlspecialchars($r['patient_name']); ?></strong><br><span class="muted-text"><?php echo htmlspecialchars('Age ' . intval($r['recipient_age']) . ', ' . $r['recipient_city']); ?></span></td>
                            <td><?php echo htmlspecialchars($r['recipient_hospital']); ?></td>
                            <td><span class="badge badge-<?php echo $r['blood_group_compatible'] ? 'approved' : 'rejected'; ?>"><?php echo htmlspecialchars($r['donor_blood'] . ' -> ' . $r['recipient_blood']); ?></span></td>
                            <td><?php echo intval($r['urgency_level']); ?></td>
                            <td><?php echo intval($r['waiting_days']); ?> days</td>
                            <td><?php echo round(floatval($r['distance_km']), 1); ?> km</td>
                            <td><?php echo score_bar($r['match_score']); ?></td>
                            <td>
                                <form method="POST" class="table-actions">
                                    <input type="hidden" name="match_id" value="<?php echo intval($r['id']); ?>"/>
                                    <input name="rejection_reason" placeholder="Reason if rejecting"/>
                                    <button name="action" value="approve" class="btn btn-green">Approve</button>
                                    <button name="action" value="reject" class="btn btn-red">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div></div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card">No pending AI matches are waiting for review.</div>
    <?php endif; ?>
</main>
<?php html_end(); ?>

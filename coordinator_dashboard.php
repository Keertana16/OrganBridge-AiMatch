<?php
require_once 'ob_flow.php';
require_role('coordinator');
include 'db.php';
run_organ_expiry_check($conn);
$name = current_user_name('Coordinator');
$available = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM organ_listings WHERE status = 'Available'"))['c'] ?? 0;
$pendingRequests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM recipients WHERE status = 'Waiting'"))['c'] ?? 0;
$expiringSoon = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM organ_listings WHERE status = 'Available' AND viable_until BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 6 HOUR)"))['c'] ?? 0;
$pendingMatches = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM ai_matches WHERE decision = 'Pending'"))['c'] ?? 0;
$activeTransfers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM transfers WHERE status NOT IN ('Completed','Failed','Rejected')"))['c'] ?? 0;
$matches = mysqli_query($conn, "SELECT am.*, ol.organ_type, ol.blood_group, ol.city AS donor_city, req.patient_name, req.city AS recipient_city
    FROM ai_matches am
    JOIN organ_listings ol ON am.organ_id = ol.id
    JOIN recipients req ON am.recipient_id = req.id
    WHERE am.decision = 'Pending'
      AND NOT EXISTS (SELECT 1 FROM transfers t WHERE t.match_id = am.id)
    ORDER BY ol.organ_type ASC, am.match_score DESC
    LIMIT 10");
$matchesByOrgan = [];
while ($matchRow = mysqli_fetch_assoc($matches)) {
    $matchesByOrgan[$matchRow['organ_type']][] = $matchRow;
}
$soonRows = mysqli_query($conn, "SELECT ol.*, h.name AS hospital_name,
        TIMESTAMPDIFF(MINUTE, NOW(), ol.viable_until) AS minutes_left
    FROM organ_listings ol
    JOIN hospitals h ON ol.hospital_id = h.id
    WHERE ol.status='Available'
      AND ol.viable_until IS NOT NULL
      AND ol.viable_until BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 6 HOUR)
    ORDER BY ol.viable_until ASC");
html_head('Coordinator Dashboard');
page_header('Coordinator Dashboard', $name);
?>
<main class="main">
    <div class="page-header"><h1>Coordinator Dashboard</h1><p>Run matching, review ranked suggestions, and track transfers end to end.</p></div>
    <div class="stats-row">
        <div class="stat-card"><div class="stat-num"><?php echo $available; ?></div><div class="stat-label">Available Organs</div></div>
        <div class="stat-card"><div class="stat-num"><?php echo $pendingRequests; ?></div><div class="stat-label">Waiting Recipients</div></div>
        <div class="stat-card"><div class="stat-num"><?php echo $pendingMatches; ?></div><div class="stat-label">Pending Matches</div></div>
        <div class="stat-card"><div class="stat-num"><?php echo $activeTransfers; ?></div><div class="stat-label">Active Transfers</div></div>
    </div>
    <?php if ($expiringSoon > 0): ?>
        <div class="alert alert-info">Expiring Soon: <?php echo $expiringSoon; ?> organ listing(s) expire within 6 hours.</div>
        <div class="table-card"><div class="table-scroll"><table class="data-table">
            <thead><tr><th>Warning</th><th>Organ</th><th>Blood</th><th>Hospital</th><th>Viable Until</th><th>Time Left</th></tr></thead><tbody>
            <?php while ($soon = mysqli_fetch_assoc($soonRows)): ?>
                <tr><td><span class="badge badge-expired">Expiring Soon</span></td><td><?php echo htmlspecialchars($soon['organ_type']); ?></td><td><?php echo htmlspecialchars($soon['blood_group']); ?></td><td><?php echo htmlspecialchars($soon['hospital_name']); ?></td><td><?php echo htmlspecialchars($soon['viable_until']); ?></td><td><?php echo round(intval($soon['minutes_left']) / 60, 1); ?> hours</td></tr>
            <?php endwhile; ?>
            </tbody></table></div></div>
    <?php endif; ?>
    <div class="card dashboard-actions">
        <a class="btn btn-teal" href="run_matching.php">Run AI Matching</a>
        <a class="btn btn-green" href="review_matches.php">Review Matches</a>
        <a class="btn btn-outline" href="all_organs.php">All Organs</a>
        <a class="btn btn-outline" href="all_recipients.php">All Recipients</a>
        <a class="btn btn-outline" href="transfers.php">Transfers</a>
    </div>
    <h3 class="section-title">Pending Matches Awaiting Decision</h3>
    <?php if (!empty($matchesByOrgan)): ?>
        <?php foreach ($matchesByOrgan as $organType => $organMatches): ?>
            <?php $best = $organMatches[0]; ?>
            <section class="organ-review-section">
                <div class="organ-section-header">
                    <div>
                        <h2><?php echo htmlspecialchars($organType); ?></h2>
                        <p><?php echo count($organMatches); ?> pending match(es). Best: <?php echo htmlspecialchars($best['patient_name']); ?>.</p>
                    </div>
                    <a class="btn btn-teal" href="review_matches.php?organ_type=<?php echo urlencode($organType); ?>">Review <?php echo htmlspecialchars($organType); ?></a>
                </div>
                <div class="table-card"><div class="table-scroll"><table class="data-table">
                    <thead><tr><th>ID</th><th>Blood</th><th>Recipient</th><th>Cities</th><th>Score</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($organMatches as $m): ?>
                        <tr class="<?php echo intval($m['id']) === intval($best['id']) ? 'best-match-row' : ''; ?>">
                            <td>#<?php echo intval($m['id']); ?><?php if (intval($m['id']) === intval($best['id'])): ?><br><span class="badge badge-approved">Best</span><?php endif; ?></td>
                            <td><?php echo htmlspecialchars($m['blood_group']); ?></td>
                            <td><?php echo htmlspecialchars($m['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($m['donor_city'] . ' -> ' . $m['recipient_city']); ?></td>
                            <td><?php echo score_bar($m['match_score']); ?></td>
                            <td><span class="badge badge-pending">Pending</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div></div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card">No pending AI matches.</div>
    <?php endif; ?>
</main>
<?php html_end(); ?>

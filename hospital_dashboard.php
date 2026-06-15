<?php
require_once 'ob_flow.php';
require_role('hospital');
include 'db.php';
$hospitalId = intval($_SESSION['hospital_id'] ?? 0);
$name = current_user_name('Hospital');
$available = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM organ_listings WHERE hospital_id = $hospitalId AND status = 'Available'"))['c'] ?? 0;
$matched = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM organ_listings WHERE hospital_id = $hospitalId AND status = 'Matched'"))['c'] ?? 0;
$transferred = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM organ_listings WHERE hospital_id = $hospitalId AND status = 'Transferred'"))['c'] ?? 0;
$patients = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM recipients WHERE hospital_id = $hospitalId AND status = 'Waiting'"))['c'] ?? 0;
$transfers = mysqli_query($conn, "SELECT t.*, am.match_score, ol.organ_type, ol.blood_group, req.patient_name
    FROM transfers t
    JOIN ai_matches am ON t.match_id = am.id
    JOIN organ_listings ol ON am.organ_id = ol.id
    JOIN recipients req ON am.recipient_id = req.id
    WHERE ol.hospital_id = $hospitalId OR req.hospital_id = $hospitalId
    ORDER BY t.updated_at DESC
    LIMIT 8");
$listings = mysqli_query($conn, "SELECT * FROM organ_listings WHERE hospital_id = $hospitalId ORDER BY organ_type ASC, created_at DESC");
$listingsByOrgan = [];
while ($listing = mysqli_fetch_assoc($listings)) {
    $listingsByOrgan[$listing['organ_type']][] = $listing;
}
$transfersByOrgan = [];
while ($transfer = mysqli_fetch_assoc($transfers)) {
    $transfersByOrgan[$transfer['organ_type']][] = $transfer;
}
html_head('Hospital Dashboard');
page_header('Hospital Dashboard', $name);
?>
<main class="main">
    <div class="page-header"><h1>Hospital Dashboard</h1><p>Manage your donated organs and transfer progress.</p></div>
    <div class="stats-row">
        <div class="stat-card"><div class="stat-num"><?php echo $available; ?></div><div class="stat-label">Available</div></div>
        <div class="stat-card"><div class="stat-num"><?php echo $matched; ?></div><div class="stat-label">Matched</div></div>
        <div class="stat-card"><div class="stat-num"><?php echo $patients; ?></div><div class="stat-label">Waiting Patients</div></div>
        <div class="stat-card"><div class="stat-num"><?php echo unread_count($conn, intval($_SESSION['user_id'])); ?></div><div class="stat-label">Unread Alerts</div></div>
    </div>
    <div class="card dashboard-actions">
        <a class="btn btn-teal" href="add_listing.php">Add Organ</a>
        <a class="btn btn-green" href="add_recipient.php">Add Recipient</a>
        <a class="btn btn-outline" href="my_listings.php">My Listings</a>
        <a class="btn btn-outline" href="my_recipients.php">My Recipients</a>
        <a class="btn btn-outline" href="hospital_transfers.php">My Transfers</a>
    </div>
    <h3 class="section-title">My Organ Listings</h3>
    <?php if (!empty($listingsByOrgan)): ?>
        <?php foreach ($listingsByOrgan as $organType => $organRows): ?>
            <section class="organ-review-section">
                <div class="organ-section-header"><div><h2><?php echo htmlspecialchars($organType); ?></h2><p><?php echo count($organRows); ?> listing(s) from your hospital.</p></div></div>
                <div class="table-card"><div class="table-scroll"><table class="data-table">
                    <thead><tr><th>Blood</th><th>City</th><th>Condition</th><th>Viable Until</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($organRows as $row): ?>
                        <tr><td><?php echo htmlspecialchars($row['blood_group']); ?></td><td><?php echo htmlspecialchars($row['city']); ?></td><td><?php echo htmlspecialchars($row['condition']); ?></td><td><?php echo htmlspecialchars($row['viable_until']); ?></td><td><span class="badge badge-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div></div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card">No organ listings yet.</div>
    <?php endif; ?>
    <h3 class="section-title">Transfer Status Of My Organs</h3>
    <?php if (!empty($transfersByOrgan)): ?>
        <?php foreach ($transfersByOrgan as $organType => $organRows): ?>
            <section class="organ-review-section">
                <div class="organ-section-header"><div><h2><?php echo htmlspecialchars($organType); ?> Transfers</h2><p><?php echo count($organRows); ?> transfer record(s).</p></div></div>
                <div class="table-card"><div class="table-scroll"><table class="data-table">
                    <thead><tr><th>Recipient</th><th>Score</th><th>Status</th><th>Updated</th><th>View</th></tr></thead><tbody>
                    <?php foreach ($organRows as $row): ?>
                        <tr><td><?php echo htmlspecialchars($row['patient_name']); ?></td><td><?php echo score_bar($row['match_score']); ?></td><td><span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td><td><?php echo htmlspecialchars($row['updated_at']); ?></td><td><a class="btn btn-outline" href="transfer_detail.php?id=<?php echo $row['id']; ?>">Open</a></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div></div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card">No transfers yet.</div>
    <?php endif; ?>
</main>
<?php html_end(); ?>

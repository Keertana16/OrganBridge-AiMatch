<?php
require_once 'ob_flow.php';
require_role('hospital');
include 'db.php';
$hospitalId = intval($_SESSION['hospital_id'] ?? 0);
$message = $_SESSION['notification'] ?? '';
unset($_SESSION['notification']);

mysqli_query($conn, "UPDATE organ_listings SET status='Expired' WHERE hospital_id=$hospitalId AND status='Available' AND viable_until IS NOT NULL AND viable_until < NOW()");
mysqli_query($conn, "UPDATE organs SET availability_status='expired' WHERE hospital_id=$hospitalId AND id IN (SELECT id FROM organ_listings WHERE status='Expired')");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    mysqli_query($conn, "DELETE FROM organ_listings WHERE id=$id AND hospital_id=$hospitalId");
    mysqli_query($conn, "DELETE FROM organs WHERE id=$id AND hospital_id=$hospitalId");
    $message = "Listing deleted.";
}

$rows = mysqli_query($conn, "SELECT * FROM organ_listings WHERE hospital_id=$hospitalId ORDER BY created_at DESC");
html_head('My Listings');
page_header('My Listings', current_user_name('Hospital'), 'hospital_dashboard.php');
?>
<main class="main">
    <div class="page-header"><h1>My Organ Listings</h1><p>Only organ listings from your hospital are shown.</p></div>
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <div class="actions" style="margin-bottom:18px"><a class="btn btn-teal" href="add_listing.php">Add Organ</a></div>
    <div class="table-card"><div class="table-scroll"><table class="data-table">
        <thead><tr><th>Organ Type</th><th>Blood Group</th><th>Donor Age</th><th>Condition</th><th>Viable Until</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($r = mysqli_fetch_assoc($rows)): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['organ_type']); ?></td>
                <td><?php echo htmlspecialchars($r['blood_group']); ?></td>
                <td><?php echo intval($r['donor_age']); ?></td>
                <td><?php echo htmlspecialchars($r['condition']); ?></td>
                <td><?php echo htmlspecialchars($r['viable_until']); ?></td>
                <td><span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                <td class="actions">
                    <a class="btn btn-outline" href="edit_listing.php?id=<?php echo $r['id']; ?>">Edit</a>
                    <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><button class="btn btn-red" type="submit">Delete</button></form>
                </td>
            </tr>
        <?php endwhile; else: ?><tr><td colspan="7">No organ listings yet.</td></tr><?php endif; ?>
        </tbody></table></div></div>
</main>
<?php html_end(); ?>

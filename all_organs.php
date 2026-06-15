<?php
require_once 'ob_flow.php';
require_role('coordinator');
include 'db.php';
run_organ_expiry_check($conn);

$organFilter = trim($_GET['organ_type'] ?? '');
$bloodFilter = trim($_GET['blood_group'] ?? '');
$where = ['1=1'];
if ($organFilter !== '') {
    $where[] = "ol.organ_type='" . mysqli_real_escape_string($conn, $organFilter) . "'";
}
if ($bloodFilter !== '') {
    $where[] = "ol.blood_group='" . mysqli_real_escape_string($conn, $bloodFilter) . "'";
}
$whereSql = implode(' AND ', $where);
$rows = mysqli_query($conn, "SELECT ol.*, h.name AS hospital_name
    FROM organ_listings ol
    LEFT JOIN hospitals h ON ol.hospital_id = h.id
    WHERE $whereSql
    ORDER BY ol.created_at DESC");
$organs = mysqli_query($conn, "SELECT DISTINCT organ_type FROM organ_listings ORDER BY organ_type");
$bloods = mysqli_query($conn, "SELECT DISTINCT blood_group FROM organ_listings ORDER BY blood_group");

html_head('All Organs');
page_header('All Organs', current_user_name('Coordinator'), 'coordinator_dashboard.php');
?>
<main class="main">
    <div class="page-header"><h1>All Organ Listings</h1><p>Available and tracked donor organs entered by hospitals.</p></div>
    <form class="card filter-bar" method="GET">
        <div class="field"><label>Organ Type</label><select name="organ_type"><option value="">All organs</option><?php while ($o = mysqli_fetch_assoc($organs)): ?><option value="<?php echo htmlspecialchars($o['organ_type']); ?>" <?php echo $organFilter === $o['organ_type'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($o['organ_type']); ?></option><?php endwhile; ?></select></div>
        <div class="field"><label>Blood Group</label><select name="blood_group"><option value="">All blood groups</option><?php while ($b = mysqli_fetch_assoc($bloods)): ?><option value="<?php echo htmlspecialchars($b['blood_group']); ?>" <?php echo $bloodFilter === $b['blood_group'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['blood_group']); ?></option><?php endwhile; ?></select></div>
        <div class="actions"><button class="btn btn-teal" type="submit">Filter</button><a class="btn btn-outline" href="all_organs.php">Clear</a></div>
    </form>
    <div class="table-card"><div class="table-scroll"><table class="data-table">
        <thead><tr><th>Organ Type</th><th>Blood Group</th><th>Donor Age</th><th>Hospital</th><th>City</th><th>Condition</th><th>Viable Until</th><th>Status</th></tr></thead>
        <tbody>
        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($r = mysqli_fetch_assoc($rows)): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['organ_type']); ?></td>
                <td><?php echo htmlspecialchars($r['blood_group']); ?></td>
                <td><?php echo intval($r['donor_age']); ?></td>
                <td><?php echo htmlspecialchars($r['hospital_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($r['city'] ?? $r['donor_city'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($r['condition']); ?></td>
                <td><?php echo htmlspecialchars($r['viable_until'] ?? 'Not set'); ?></td>
                <td><span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
            </tr>
        <?php endwhile; else: ?><tr><td colspan="8">No organ listings found.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
</main>
<?php html_end(); ?>

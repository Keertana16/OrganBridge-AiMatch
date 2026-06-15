<?php
require_once 'ob_flow.php';
require_role('coordinator');
include 'db.php';
$organFilter = trim($_GET['organ_type'] ?? '');
$bloodFilter = trim($_GET['blood_group'] ?? '');
$where = ['1=1'];
if ($organFilter !== '') {
    $where[] = "r.organ_needed='" . mysqli_real_escape_string($conn, $organFilter) . "'";
}
if ($bloodFilter !== '') {
    $where[] = "r.blood_group='" . mysqli_real_escape_string($conn, $bloodFilter) . "'";
}
$whereSql = implode(' AND ', $where);
$rows = mysqli_query($conn, "SELECT r.*, h.name AS hospital_name
    FROM recipients r
    LEFT JOIN hospitals h ON r.hospital_id = h.id
    WHERE $whereSql
    ORDER BY r.created_at DESC");
$organs = mysqli_query($conn, "SELECT DISTINCT organ_needed FROM recipients ORDER BY organ_needed");
$bloods = mysqli_query($conn, "SELECT DISTINCT blood_group FROM recipients ORDER BY blood_group");
html_head('All Recipients');
page_header('All Recipients', current_user_name('Coordinator'), 'coordinator_dashboard.php');
?>
<main class="main">
    <div class="page-header"><h1>Recipient Patients</h1><p>Patients entered by hospitals. They are not login users.</p></div>
    <form class="card filter-bar" method="GET">
        <div class="field"><label>Organ Type</label><select name="organ_type"><option value="">All organs</option><?php while($o=mysqli_fetch_assoc($organs)): ?><option value="<?php echo htmlspecialchars($o['organ_needed']); ?>" <?php echo $organFilter === $o['organ_needed'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($o['organ_needed']); ?></option><?php endwhile; ?></select></div>
        <div class="field"><label>Blood Group</label><select name="blood_group"><option value="">All blood groups</option><?php while($b=mysqli_fetch_assoc($bloods)): ?><option value="<?php echo htmlspecialchars($b['blood_group']); ?>" <?php echo $bloodFilter === $b['blood_group'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['blood_group']); ?></option><?php endwhile; ?></select></div>
        <div class="actions"><button class="btn btn-teal" type="submit">Filter</button><a class="btn btn-outline" href="all_recipients.php">Clear</a></div>
    </form>
    <div class="table-card"><div class="table-scroll"><table class="data-table"><thead><tr><th>Patient Name</th><th>Organ Needed</th><th>Blood Group</th><th>Age</th><th>Hospital Name</th><th>City</th><th>Urgency</th><th>Waiting Days</th><th>Status</th></tr></thead><tbody>
    <?php if ($rows && mysqli_num_rows($rows) > 0): while($r=mysqli_fetch_assoc($rows)): ?><tr><td><?php echo htmlspecialchars($r['patient_name']); ?></td><td><?php echo htmlspecialchars($r['organ_needed']); ?></td><td><?php echo htmlspecialchars($r['blood_group']); ?></td><td><?php echo intval($r['age']); ?></td><td><?php echo htmlspecialchars($r['hospital_name'] ?? 'N/A'); ?></td><td><?php echo htmlspecialchars($r['city']); ?></td><td><?php echo intval($r['urgency_level']); ?>/5</td><td><?php echo intval($r['waiting_days']); ?></td><td><span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td></tr><?php endwhile; else: ?><tr><td colspan="9">No recipient records found.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</main><?php html_end(); ?>

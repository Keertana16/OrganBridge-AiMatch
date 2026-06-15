<?php
require_once 'ob_flow.php';
require_role('hospital');
include 'db.php';
$hospitalId = intval($_SESSION['hospital_id'] ?? 0);
$rows = mysqli_query($conn, "SELECT * FROM recipients WHERE hospital_id = $hospitalId ORDER BY created_at DESC");
html_head('My Patients');
page_header('My Patients', current_user_name('Hospital'), 'hospital_dashboard.php');
?>
<main class="main">
    <div class="page-header"><h1>My Recipient Patients</h1><p>These patients are managed by your hospital.</p></div>
    <div class="actions" style="margin-bottom:18px"><a class="btn btn-teal" href="add_patient.php">Add Patient</a></div>
    <div class="table-card"><div class="table-scroll"><table class="data-table">
        <thead><tr><th>Patient</th><th>Organ</th><th>Blood</th><th>Age</th><th>City</th><th>Urgency</th><th>Status</th></tr></thead><tbody>
        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($r = mysqli_fetch_assoc($rows)): ?>
            <tr><td><?php echo htmlspecialchars($r['patient_name']); ?></td><td><?php echo htmlspecialchars($r['organ_needed']); ?></td><td><?php echo htmlspecialchars($r['blood_group']); ?></td><td><?php echo intval($r['age']); ?></td><td><?php echo htmlspecialchars($r['city']); ?></td><td><?php echo intval($r['urgency_level']); ?>/5</td><td><span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td></tr>
        <?php endwhile; else: ?><tr><td colspan="7">No patients yet.</td></tr><?php endif; ?>
        </tbody></table></div></div>
</main>
<?php html_end(); ?>

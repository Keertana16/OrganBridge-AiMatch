<?php
require_once 'ob_flow.php';
require_role('hospital');
include 'db.php';
$hospitalId = intval($_SESSION['hospital_id'] ?? 0);
$message = $_SESSION['notification'] ?? '';
unset($_SESSION['notification']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    mysqli_query($conn, "DELETE FROM recipients WHERE id=$id AND hospital_id=$hospitalId");
    $message = "Recipient record deleted.";
}

$rows = mysqli_query($conn, "SELECT * FROM recipients WHERE hospital_id=$hospitalId ORDER BY created_at DESC");
html_head('My Recipients');
page_header('My Recipients', current_user_name('Hospital'), 'hospital_dashboard.php');
?>
<main class="main">
    <div class="page-header"><h1>My Recipients</h1><p>Recipient/patient records added by your hospital.</p></div>
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <div class="actions" style="margin-bottom:18px"><a class="btn btn-teal" href="add_recipient.php">Add Recipient</a></div>
    <div class="table-card"><div class="table-scroll"><table class="data-table">
        <thead><tr><th>Name</th><th>Organ Needed</th><th>Blood Group</th><th>Age</th><th>Urgency</th><th>Waiting Days</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($r = mysqli_fetch_assoc($rows)): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['patient_name']); ?></td>
                <td><?php echo htmlspecialchars($r['organ_needed']); ?></td>
                <td><?php echo htmlspecialchars($r['blood_group']); ?></td>
                <td><?php echo intval($r['age']); ?></td>
                <td><?php echo intval($r['urgency_level']); ?>/5</td>
                <td><?php echo intval($r['waiting_days']); ?></td>
                <td><span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                <td class="actions">
                    <a class="btn btn-outline" href="edit_recipient.php?id=<?php echo $r['id']; ?>">Edit</a>
                    <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>"><button class="btn btn-red">Delete</button></form>
                </td>
            </tr>
        <?php endwhile; else: ?><tr><td colspan="8">No recipient records yet.</td></tr><?php endif; ?>
        </tbody></table></div></div>
</main>
<?php html_end(); ?>

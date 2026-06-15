<?php
require_once 'ob_flow.php';
require_role('hospital');
include 'db.php';
$hospitalId = intval($_SESSION['hospital_id'] ?? 0);
$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $organ = mysqli_real_escape_string($conn, trim($_POST['organ_type'] ?? ''));
    $blood = mysqli_real_escape_string($conn, trim($_POST['blood_group'] ?? ''));
    $age = intval($_POST['donor_age'] ?? 0);
    $city = mysqli_real_escape_string($conn, trim($_POST['city'] ?? ''));
    $condition = mysqli_real_escape_string($conn, trim($_POST['condition'] ?? 'Good'));
    $functionPct = max(0, min(100, floatval($_POST['organ_function_pct'] ?? 85)));
    $until = mysqli_real_escape_string($conn, str_replace('T', ' ', trim($_POST['viable_until'] ?? '')));
    $status = mysqli_real_escape_string($conn, trim($_POST['status'] ?? 'Available'));
    mysqli_query($conn, "UPDATE organ_listings SET organ_type='$organ', blood_group='$blood', donor_age=$age, city='$city', `condition`='$condition', organ_function_pct=$functionPct, viable_until='$until', status='$status' WHERE id=$id AND hospital_id=$hospitalId");
    mysqli_query($conn, "UPDATE organs SET organ_type='$organ', organ_name='$organ', blood_group='$blood', donor_age=$age, donor_city='$city', organ_condition='$condition', availability_status='" . strtolower($status) . "', available_until='$until' WHERE id=$id AND hospital_id=$hospitalId");
    $_SESSION['notification'] = "Listing updated.";
    header("Location: my_listings.php");
    exit();
}

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM organ_listings WHERE id=$id AND hospital_id=$hospitalId LIMIT 1"));
html_head('Edit Listing');
page_header('Edit Listing', current_user_name('Hospital'), 'my_listings.php');
?>
<main class="main">
    <div class="page-header"><h1>Edit Organ Listing</h1><p>Update donor organ availability details.</p></div>
    <?php if (!$row): ?><div class="alert alert-error">Listing not found.</div><?php else: ?>
    <form method="POST" class="card">
        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
        <div class="form-grid">
            <div class="field"><label>Organ Type</label><select name="organ_type" required><?php foreach (['Kidney','Liver','Heart','Lung','Eye','Pancreas'] as $o): ?><option <?php echo $row['organ_type']===$o?'selected':''; ?>><?php echo $o; ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Blood Group</label><select name="blood_group" required><?php foreach (['O+','O-','A+','A-','B+','B-','AB+','AB-'] as $b): ?><option <?php echo $row['blood_group']===$b?'selected':''; ?>><?php echo $b; ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Donor Age</label><input type="number" name="donor_age" value="<?php echo intval($row['donor_age']); ?>"></div>
            <div class="field"><label>Donor City</label><input name="city" value="<?php echo htmlspecialchars($row['city']); ?>" required></div>
            <div class="field"><label>Condition</label><select name="condition"><?php foreach (['Excellent','Good','Marginal'] as $c): ?><option <?php echo $row['condition']===$c?'selected':''; ?>><?php echo $c; ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Organ Function %</label><input type="number" step="0.1" min="0" max="100" name="organ_function_pct" value="<?php echo htmlspecialchars($row['organ_function_pct'] ?? 85); ?>"></div>
            <div class="field"><label>Viable Until</label><input type="datetime-local" name="viable_until" value="<?php echo $row['viable_until'] ? date('Y-m-d\TH:i', strtotime($row['viable_until'])) : ''; ?>" required></div>
            <div class="field"><label>Status</label><select name="status"><?php foreach (['Available','Matched','Expired','Transferred'] as $s): ?><option <?php echo $row['status']===$s?'selected':''; ?>><?php echo $s; ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="actions" style="margin-top:18px"><button class="btn btn-teal">Save Changes</button></div>
    </form>
    <?php endif; ?>
</main>
<?php html_end(); ?>

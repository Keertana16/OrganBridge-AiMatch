<?php
require_once 'ob_flow.php';
require_role('hospital');
include 'db.php';
$hospitalId = intval($_SESSION['hospital_id'] ?? 0);
$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient = mysqli_real_escape_string($conn, trim($_POST['patient_name'] ?? ''));
    $organ = mysqli_real_escape_string($conn, trim($_POST['organ_needed'] ?? ''));
    $blood = mysqli_real_escape_string($conn, trim($_POST['blood_group'] ?? ''));
    $age = intval($_POST['age'] ?? 0);
    $city = mysqli_real_escape_string($conn, trim($_POST['city'] ?? ''));
    $urgency = max(1, min(5, intval($_POST['urgency_level'] ?? 1)));
    $waiting = max(0, intval($_POST['waiting_days'] ?? 0));
    $systolic = intval($_POST['systolic_bp'] ?? 120);
    $diastolic = intval($_POST['diastolic_bp'] ?? 80);
    $gfr = intval($_POST['gfr_score'] ?? 70);
    $creatinine = floatval($_POST['creatinine'] ?? 1);
    $diabetes = intval($_POST['diabetes'] ?? 0) ? 1 : 0;
    $hypertension = intval($_POST['hypertension'] ?? 0) ? 1 : 0;
    $cardiac = intval($_POST['cardiac_stable'] ?? 1) ? 1 : 0;
    $infection = intval($_POST['infection'] ?? 0) ? 1 : 0;
    $prev = max(0, intval($_POST['prev_transplants'] ?? 0));
    $status = mysqli_real_escape_string($conn, trim($_POST['status'] ?? 'Waiting'));
    $notes = mysqli_real_escape_string($conn, trim($_POST['medical_notes'] ?? ''));
    mysqli_query($conn, "UPDATE recipients SET patient_name='$patient', organ_needed='$organ', blood_group='$blood', age=$age, city='$city', urgency_level=$urgency, waiting_days=$waiting, systolic_bp=$systolic, diastolic_bp=$diastolic, gfr_score=$gfr, creatinine=$creatinine, diabetes=$diabetes, hypertension=$hypertension, cardiac_stable=$cardiac, infection=$infection, prev_transplants=$prev, status='$status', medical_history='$notes' WHERE id=$id AND hospital_id=$hospitalId");
    $_SESSION['notification'] = "Recipient record updated.";
    header("Location: my_recipients.php");
    exit();
}

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM recipients WHERE id=$id AND hospital_id=$hospitalId LIMIT 1"));
html_head('Edit Recipient');
page_header('Edit Recipient', current_user_name('Hospital'), 'my_recipients.php');
?>
<main class="main">
    <div class="page-header"><h1>Edit Recipient</h1><p>Update patient need and clinical details.</p></div>
    <?php if (!$row): ?><div class="alert alert-error">Recipient not found.</div><?php else: ?>
    <form method="POST" class="card">
        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
        <div class="form-grid">
            <div class="field"><label>Patient Name</label><input name="patient_name" value="<?php echo htmlspecialchars($row['patient_name']); ?>" required></div>
            <div class="field"><label>Organ Needed</label><select name="organ_needed"><?php foreach (['Kidney','Liver','Heart','Lung','Eye','Pancreas'] as $o): ?><option <?php echo $row['organ_needed']===$o?'selected':''; ?>><?php echo $o; ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Blood Group</label><select name="blood_group"><?php foreach (['O+','O-','A+','A-','B+','B-','AB+','AB-'] as $b): ?><option <?php echo $row['blood_group']===$b?'selected':''; ?>><?php echo $b; ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Age</label><input type="number" name="age" value="<?php echo intval($row['age']); ?>"></div>
            <div class="field"><label>City</label><input name="city" value="<?php echo htmlspecialchars($row['city']); ?>"></div>
            <div class="field"><label>Urgency</label><input type="number" min="1" max="5" name="urgency_level" value="<?php echo intval($row['urgency_level']); ?>"></div>
            <div class="field"><label>Waiting Days</label><input type="number" name="waiting_days" value="<?php echo intval($row['waiting_days']); ?>"></div>
            <div class="field"><label>Systolic BP</label><input type="number" name="systolic_bp" value="<?php echo intval($row['systolic_bp']); ?>"></div>
            <div class="field"><label>Diastolic BP</label><input type="number" name="diastolic_bp" value="<?php echo intval($row['diastolic_bp']); ?>"></div>
            <div class="field"><label>GFR</label><input type="number" name="gfr_score" value="<?php echo intval($row['gfr_score']); ?>"></div>
            <div class="field"><label>Creatinine</label><input type="number" step="0.01" name="creatinine" value="<?php echo htmlspecialchars($row['creatinine']); ?>"></div>
            <div class="field"><label>Diabetes</label><select name="diabetes"><option value="0" <?php echo !$row['diabetes']?'selected':''; ?>>No</option><option value="1" <?php echo $row['diabetes']?'selected':''; ?>>Yes</option></select></div>
            <div class="field"><label>Hypertension</label><select name="hypertension"><option value="0" <?php echo !$row['hypertension']?'selected':''; ?>>No</option><option value="1" <?php echo $row['hypertension']?'selected':''; ?>>Yes</option></select></div>
            <div class="field"><label>Cardiac Stable</label><select name="cardiac_stable"><option value="1" <?php echo $row['cardiac_stable']?'selected':''; ?>>Yes</option><option value="0" <?php echo !$row['cardiac_stable']?'selected':''; ?>>No</option></select></div>
            <div class="field"><label>Infection Present</label><select name="infection"><option value="0" <?php echo !$row['infection']?'selected':''; ?>>No</option><option value="1" <?php echo $row['infection']?'selected':''; ?>>Yes</option></select></div>
            <div class="field"><label>Previous Transplants</label><input type="number" min="0" max="2" name="prev_transplants" value="<?php echo intval($row['prev_transplants']); ?>"></div>
            <div class="field"><label>Status</label><select name="status"><?php foreach (['Waiting','Matched','Fulfilled','Cancelled'] as $s): ?><option <?php echo $row['status']===$s?'selected':''; ?>><?php echo $s; ?></option><?php endforeach; ?></select></div>
            <div class="field full"><label>Medical Notes</label><textarea name="medical_notes"><?php echo htmlspecialchars($row['medical_history']); ?></textarea></div>
        </div>
        <div class="actions" style="margin-top:18px"><button class="btn btn-teal">Save Changes</button></div>
    </form>
    <?php endif; ?>
</main>
<?php html_end(); ?>

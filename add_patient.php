<?php
require_once 'ob_flow.php';
require_role('hospital');
include 'db.php';
html_head('Add Patient');
page_header('Add Patient', current_user_name('Hospital'), 'hospital_dashboard.php');
?>
<main class="main">
    <div class="page-header"><h1>Add Recipient Patient</h1><p>Patients are entered by the hospital. They do not log in.</p></div>
    <form method="POST" action="patient_handler.php" class="card">
        <div class="form-grid">
            <div class="field"><label>Patient Name</label><input name="patient_name" required></div>
            <div class="field"><label>Organ Needed</label><select name="organ_needed" required><?php foreach (['Kidney','Liver','Heart','Lung','Eye','Pancreas'] as $o): ?><option><?php echo $o; ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Blood Group</label><select name="blood_group" required><?php foreach (['O+','O-','A+','A-','B+','B-','AB+','AB-'] as $b): ?><option><?php echo $b; ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Age</label><input type="number" name="age" min="0" max="120"></div>
            <div class="field"><label>City</label><input name="city" required></div>
            <div class="field"><label>Urgency Level 1-5</label><input type="number" name="urgency_level" min="1" max="5" value="3"></div>
            <div class="field"><label>Waiting Days</label><input type="number" name="waiting_days" min="0" value="0"></div>
            <div class="field"><label>Systolic BP</label><input type="number" name="systolic_bp" value="120"></div>
            <div class="field"><label>Diastolic BP</label><input type="number" name="diastolic_bp" value="80"></div>
            <div class="field"><label>GFR Score</label><input type="number" name="gfr_score" value="70"></div>
            <div class="field"><label>Creatinine</label><input type="number" step="0.01" name="creatinine" value="1.00"></div>
            <div class="field"><label>Diabetes</label><select name="diabetes"><option value="0">No</option><option value="1">Yes</option></select></div>
            <div class="field"><label>Hypertension</label><select name="hypertension"><option value="0">No</option><option value="1">Yes</option></select></div>
            <div class="field"><label>Cardiac Stable</label><select name="cardiac_stable"><option value="1">Yes</option><option value="0">No</option></select></div>
            <div class="field"><label>Infection</label><select name="infection"><option value="0">No</option><option value="1">Yes</option></select></div>
            <div class="field"><label>Previous Transplants</label><input type="number" min="0" name="prev_transplants" value="0"></div>
            <div class="field full"><label>Medical Notes</label><textarea name="medical_notes"></textarea></div>
        </div>
        <div class="actions" style="margin-top:18px"><button class="btn btn-teal">Save Patient</button><a class="btn btn-outline" href="hospital_dashboard.php">Cancel</a></div>
    </form>
</main>
<?php html_end(); ?>

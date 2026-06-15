<?php
require_once 'ob_flow.php';
require_role('hospital');
include 'db.php';
html_head('Add Listing');
page_header('Add Listing', current_user_name('Hospital'), 'hospital_dashboard.php');
?>
<main class="main">
    <div class="page-header"><h1>Add Organ Listing</h1><p>List a donor organ for coordinator matching.</p></div>
    <form method="POST" action="add_listing_handler.php" class="card">
        <div class="form-grid">
            <div class="field"><label>Organ Type</label><select name="organ_type" required><?php foreach (['Kidney','Liver','Heart','Lung','Eye','Pancreas'] as $o): ?><option value="<?php echo $o; ?>"><?php echo $o; ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Blood Group</label><select name="blood_group" required><?php foreach (['O+','O-','A+','A-','B+','B-','AB+','AB-'] as $b): ?><option value="<?php echo $b; ?>"><?php echo $b; ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Donor Age</label><input type="number" name="donor_age" min="0" max="120"/></div>
            <div class="field"><label>City</label><input type="text" name="city" required/></div>
            <div class="field"><label>Condition</label><select name="condition"><option>Excellent</option><option selected>Good</option><option>Marginal</option></select></div>
            <div class="field"><label>Organ Function %</label><input type="number" name="organ_function_pct" min="0" max="100" step="0.1" value="85"/></div>
            <div class="field"><label>Viable Until</label><input type="datetime-local" name="viable_until" required/></div>
        </div>
        <div class="actions" style="margin-top:18px"><button class="btn btn-teal" type="submit">Save Listing</button><a class="btn btn-outline" href="hospital_dashboard.php">Cancel</a></div>
    </form>
</main>
<?php html_end(); ?>

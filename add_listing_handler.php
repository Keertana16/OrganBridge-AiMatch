<?php
require_once 'ob_flow.php';
require_role('hospital');
include 'db.php';
$hospitalId = intval($_SESSION['hospital_id'] ?? 0);
$hospitalName = mysqli_real_escape_string($conn, $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'Hospital');
$organ = mysqli_real_escape_string($conn, trim($_POST['organ_type'] ?? ''));
$blood = mysqli_real_escape_string($conn, trim($_POST['blood_group'] ?? ''));
$age = intval($_POST['donor_age'] ?? 0);
$city = mysqli_real_escape_string($conn, trim($_POST['city'] ?? ''));
$condition = mysqli_real_escape_string($conn, trim($_POST['condition'] ?? 'Good'));
$functionPct = max(0, min(100, floatval($_POST['organ_function_pct'] ?? 85)));
$until = mysqli_real_escape_string($conn, str_replace('T', ' ', trim($_POST['viable_until'] ?? '')));
if ($hospitalId <= 0 || $organ === '' || $blood === '' || $city === '' || $until === '') {
    die("Missing required listing fields. <a href='add_listing.php'>Go back</a>");
}
$sqlOld = "INSERT INTO organs (hospital_id, organ_type, organ_name, blood_group, donor_age, donor_city, hospital_name, organ_condition, availability_status, available_until)
           VALUES ($hospitalId, '$organ', '$organ', '$blood', $age, '$city', '$hospitalName', '$condition', 'available', '$until')";
if (!mysqli_query($conn, $sqlOld)) {
    die("Could not save organ: " . mysqli_error($conn));
}
$organId = mysqli_insert_id($conn);
$sqlNew = "INSERT INTO organ_listings (id, hospital_id, organ_type, blood_group, donor_age, city, `condition`, organ_function_pct, viable_until, status)
           VALUES ($organId, $hospitalId, '$organ', '$blood', $age, '$city', '$condition', $functionPct, '$until', 'Available')";
mysqli_query($conn, $sqlNew);
notify_coordinators($conn, "New organ available: $organ ($blood) from " . ($_SESSION['name'] ?? 'Hospital'), "Organ listing added", "organ_added", "run_matching.php");
$_SESSION['notification'] = "Organ listing added.";
header("Location: my_listings.php");
exit();
?>

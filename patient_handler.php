<?php
require_once 'ob_flow.php';
require_role('hospital');
include 'db.php';
$hospitalId = intval($_SESSION['hospital_id'] ?? 0);
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
$notes = mysqli_real_escape_string($conn, trim($_POST['medical_notes'] ?? ''));
if ($hospitalId <= 0 || $patient === '' || $organ === '' || $blood === '') {
    die("Missing required patient fields. <a href='add_patient.php'>Go back</a>");
}
$sql = "INSERT INTO recipients
    (hospital_id, user_id, patient_name, email, organ_needed, blood_group, age, city, urgency_level, waiting_days,
     medical_history, systolic_bp, diastolic_bp, gfr_score, creatinine, diabetes, hypertension,
     cardiac_stable, infection, prev_transplants, status)
    VALUES
    ($hospitalId, NULL, '$patient', NULL, '$organ', '$blood', $age, '$city', $urgency, $waiting,
     '$notes', $systolic, $diastolic, $gfr, $creatinine, $diabetes, $hypertension,
     $cardiac, $infection, $prev, 'Waiting')";
if (!mysqli_query($conn, $sql)) {
    die("Could not save patient: " . mysqli_error($conn));
}
notify_coordinators($conn, "New recipient patient from " . ($_SESSION['name'] ?? 'Hospital') . ": $patient, age $age, $organ needed", "Recipient record added", "recipient_request", "run_matching.php");
header("Location: my_patients.php");
exit();
?>

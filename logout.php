<?php
// ============================================================
//  logout.php  |  Session Destroy & Redirect
//  Clears all session data and sends user back to login
// ============================================================

// STEP 1: Start the session (required before you can destroy it)
session_start();

// STEP 2: Remove all session variables
// This clears everything stored in $_SESSION (email, name, id, etc.)
session_unset();

// STEP 3: Destroy the session completely
// This deletes the session file on the server
session_destroy();

// STEP 4: Redirect the user to the login page
header("Location: auth.php");

// STEP 5: Stop PHP execution after redirect
exit();
?>

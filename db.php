<?php
// ============================================================
//  db.php  |  Database Connection File
//  Include this file in every PHP page that needs the database:
//  Usage:  include 'db.php';
// ============================================================

// ── Connection settings ──────────────────────────────────────
$host     = "localhost";   // XAMPP always uses localhost
$username = "root";        // default XAMPP MySQL username
$password = "";            // default XAMPP MySQL password is empty
$database = "organ_db";   // the database we created

// ── Create the connection ────────────────────────────────────
$conn = mysqli_connect($host, $username, $password, $database);

// ── Check if connection worked ───────────────────────────────
// If mysqli_connect fails, it returns false — we stop and show the error
if (!$conn) {
    die("❌ Connection failed: " . mysqli_connect_error());
}

// If we reach here, connection is successful!
// The $conn variable is now available in any file that includes this.
?>

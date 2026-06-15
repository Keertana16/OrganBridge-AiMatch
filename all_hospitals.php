<?php
require_once 'ob_flow.php';
require_role('coordinator');
include 'db.php';
$rows = mysqli_query($conn, "SELECT h.*, COUNT(ol.id) AS listings FROM hospitals h LEFT JOIN organ_listings ol ON h.id=ol.hospital_id GROUP BY h.id ORDER BY h.created_at DESC");
html_head('All Hospitals');
page_header('All Hospitals', current_user_name('Coordinator'), 'coordinator_dashboard.php');
?>
<main class="main"><div class="page-header"><h1>Registered Hospitals</h1><p>Coordinator-only hospital registry.</p></div><div class="table-card"><div class="table-scroll"><table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>City</th><th>Verified</th><th>Listings</th></tr></thead><tbody><?php while($r=mysqli_fetch_assoc($rows)): ?><tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars($r['email']); ?></td><td><?php echo htmlspecialchars($r['city']); ?></td><td><?php echo $r['verified'] ? 'Yes' : 'No'; ?></td><td><?php echo intval($r['listings']); ?></td></tr><?php endwhile; ?></tbody></table></div></div></main><?php html_end(); ?>

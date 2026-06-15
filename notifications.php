<?php
require_once 'ob_flow.php';
session_start();
if (!isset($_SESSION['email'])) { header("Location: auth.php"); exit(); }
include 'db.php';
$userId = intval($_SESSION['user_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mysqli_query($conn, "UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=$userId");
}
$rows = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id=$userId ORDER BY created_at DESC");
$back = ($_SESSION['role'] ?? '') === 'hospital' ? 'hospital_dashboard.php' : (($_SESSION['role'] ?? '') === 'recipient' ? 'recipient_dashboard.php' : 'coordinator_dashboard.php');
html_head('Notifications');
page_header('Notifications', current_user_name('User'), $back);
?>
<main class="main"><div class="page-header"><h1>Notifications</h1><p>Unread and recent system updates.</p></div><form method="POST" class="card"><button class="btn btn-teal">Mark All As Read</button></form><div class="table-card"><div class="table-scroll"><table class="data-table"><thead><tr><th>Status</th><th>Title</th><th>Message</th><th>Created</th><th>Open</th></tr></thead><tbody><?php if($rows && mysqli_num_rows($rows)>0): while($r=mysqli_fetch_assoc($rows)): ?><tr><td><?php echo $r['is_read'] ? 'Read' : 'Unread'; ?></td><td><?php echo htmlspecialchars($r['title']); ?></td><td><?php echo htmlspecialchars($r['message']); ?></td><td><?php echo htmlspecialchars($r['created_at']); ?></td><td><?php if($r['action_url']): ?><a class="btn btn-outline" href="<?php echo htmlspecialchars($r['action_url']); ?>">Open</a><?php endif; ?></td></tr><?php endwhile; else: ?><tr><td colspan="5">No notifications yet.</td></tr><?php endif; ?></tbody></table></div></div></main><?php html_end(); ?>

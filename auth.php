<?php
// OrganBridge authentication page.
// Hospitals and coordinators use this single page for login and registration.
session_start();
include 'db.php';

// Send an already logged-in user to the correct dashboard.
if (isset($_SESSION['email'], $_SESSION['role'])) {
    $dashboard = $_SESSION['role'] === 'coordinator' ? 'coordinator_dashboard.php' : 'hospital_dashboard.php';
    header("Location: $dashboard");
    exit();
}

$mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';
$requestedRole = $_GET['role'] ?? 'hospital';
$selectedRole = in_array($requestedRole, ['hospital', 'coordinator'], true) ? $requestedRole : 'hospital';
$error = '';

// Save text safely for SQL.
function sql_text($conn, $value) {
    return mysqli_real_escape_string($conn, trim($value ?? ''));
}

// Handle registration for either role.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $mode = 'register';
    $role = in_array(($_POST['role'] ?? ''), ['hospital', 'coordinator'], true) ? $_POST['role'] : 'hospital';
    $selectedRole = $role;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $error = 'Name, email, password, and confirm password are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $emailSafe = sql_text($conn, $email);
        $exists = mysqli_query($conn, "SELECT id FROM users WHERE email='$emailSafe' LIMIT 1");
        if ($exists && mysqli_num_rows($exists) > 0) {
            $error = 'This email is already registered.';
        } else {
            $nameSafe = sql_text($conn, $name);
            $passwordSafe = sql_text($conn, $password);
            $roleSafe = sql_text($conn, $role);
            $citySafe = sql_text($conn, $city);
            $phoneSafe = sql_text($conn, $phone);
            mysqli_query($conn, "INSERT INTO users (name, email, password, role, city, phone, full_name, contact_phone)
                VALUES ('$nameSafe', '$emailSafe', '$passwordSafe', '$roleSafe', '$citySafe', '$phoneSafe', '$nameSafe', '$phoneSafe')");
            $userId = mysqli_insert_id($conn);

            if ($role === 'hospital') {
                $registration = sql_text($conn, $_POST['registration_number'] ?? '');
                mysqli_query($conn, "INSERT INTO hospitals (user_id, name, email, password, city, contact_phone, registration_number, verified)
                    VALUES ($userId, '$nameSafe', '$emailSafe', '$passwordSafe', '$citySafe', '$phoneSafe', '$registration', 1)");
            } else {
                $employee = sql_text($conn, $_POST['employee_id'] ?? '');
                mysqli_query($conn, "INSERT INTO coordinators (user_id, name, email, employee_id, department, permission_level)
                    VALUES ($userId, '$nameSafe', '$emailSafe', '$employee', 'Transplant Coordination', 'admin')");
            }

            $_SESSION['notification'] = 'Account created. Please login.';
            header("Location: auth.php?role=$role");
            exit();
        }
    }
}

// Handle login and role-based redirect.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $mode = 'login';
    $role = in_array(($_POST['role'] ?? ''), ['hospital', 'coordinator'], true) ? $_POST['role'] : 'hospital';
    $selectedRole = $role;
    $email = sql_text($conn, $_POST['email'] ?? '');
    $password = sql_text($conn, $_POST['password'] ?? '');

    $result = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' AND role='$role' LIMIT 1");
    $user = $result ? mysqli_fetch_assoc($result) : null;
    if (!$user || $user['password'] !== $password) {
        $error = 'Invalid email, password, or role.';
    } else {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'] ?? $user['name'];

        if ($user['role'] === 'hospital') {
            $hospital = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM hospitals WHERE user_id=" . intval($user['id']) . " LIMIT 1"));
            $_SESSION['hospital_id'] = $hospital['id'] ?? 0;
            $_SESSION['name'] = $hospital['name'] ?? ($_SESSION['full_name'] ?? 'Hospital');
            header("Location: hospital_dashboard.php");
            exit();
        }

        $coordinator = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM coordinators WHERE user_id=" . intval($user['id']) . " LIMIT 1"));
        $_SESSION['coordinator_id'] = $coordinator['id'] ?? 0;
        $_SESSION['name'] = $coordinator['name'] ?? ($_SESSION['full_name'] ?? 'Coordinator');
        header("Location: coordinator_dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>OrganBridge Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="style.css"/>
    <style>
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:linear-gradient(135deg,#f8fbfc 0%,var(--bg) 58%,#f7faf8 100%)}
        .auth-shell{width:min(1080px,calc(100vw - 48px));background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:var(--shadow-md);display:grid;grid-template-columns:.95fr 1.05fr;overflow:hidden;animation:rise .28s ease both}
        .auth-brand{background:linear-gradient(135deg,var(--teal),#11899b 54%,#0b6674);color:#fff;padding:52px 44px;display:flex;flex-direction:column;justify-content:center;position:relative;overflow:hidden}
        .auth-brand::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.12),transparent 42%);pointer-events:none}
        .brand-mark{width:64px;height:64px;border-radius:18px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.28);display:grid;place-items:center;margin-bottom:22px;position:relative;box-shadow:0 18px 40px rgba(0,0,0,.12);font-size:1.05rem;font-weight:900;color:#fff;letter-spacing:.04em}
        .auth-brand h1{font-family:'Playfair Display',Georgia,serif;font-size:clamp(2rem,4vw,2.55rem);line-height:1.06;margin-bottom:14px;position:relative}
        .auth-brand p{line-height:1.65;opacity:.94;position:relative;max-width:340px}
        .auth-brand p::after{content:"Hospital to coordinator to transfer, tracked in one place.";display:block;margin-top:24px;padding-top:20px;border-top:1px solid rgba(255,255,255,.22);font-weight:800;color:rgba(255,255,255,.92)}
        .auth-panel{padding:44px 38px;display:flex;flex-direction:column;justify-content:center}
        .role-tabs,.auth-tabs{display:flex;gap:8px;margin-bottom:14px}
        .role-tabs a,.auth-tabs a{flex:1;text-align:center;border:1px solid var(--border);border-radius:8px;padding:10px;text-decoration:none;color:var(--text);font-weight:900;background:#fff;transition:all .16s ease}
        .role-tabs a:hover,.auth-tabs a:hover{background:var(--teal-soft);transform:translateY(-1px)}
        .role-tabs a.active,.auth-tabs a.active{background:linear-gradient(135deg,var(--teal),var(--teal-dk));color:#fff;border-color:var(--teal);box-shadow:0 10px 22px rgba(15,123,140,.16)}
        .auth-form{display:grid;grid-template-columns:1fr 1fr;gap:13px}
        .auth-form input{width:100%;border:1px solid var(--border);border-radius:8px;padding:11px;font-family:'Nunito',Arial,sans-serif}
        .auth-form .field:nth-last-child(-n+3),.auth-form .btn{grid-column:1/-1}
        .auth-form .btn{width:100%;margin-top:2px}
        .auth-alert{background:var(--red-soft);color:var(--red);padding:12px;border-radius:8px;margin-bottom:14px;font-weight:900;border:1px solid #f4b6af}
        @media(max-width:760px){body{padding:14px;align-items:flex-start}.auth-shell{width:100%;grid-template-columns:1fr}.auth-brand{padding:30px}.brand-mark{width:54px;height:54px;border-radius:14px;margin-bottom:16px}.auth-panel{padding:24px}.auth-brand p::after{display:none}.auth-form{grid-template-columns:1fr}.auth-form .field,.auth-form .btn{grid-column:1/-1}}
    </style>
</head>
<body>
<div class="auth-shell">
    <section class="auth-brand">
        <div class="brand-mark" aria-hidden="true">
            OB
        </div>
        <h1>OrganBridge</h1>
        <p>Medical organ availability, AI matching, coordinator approval, and transfer tracking for hospitals.</p>
    </section>
    <section class="auth-panel">
        <div class="role-tabs">
            <a class="<?php echo $selectedRole === 'hospital' ? 'active' : ''; ?>" href="auth.php?role=hospital&mode=<?php echo $mode; ?>">Hospital</a>
            <a class="<?php echo $selectedRole === 'coordinator' ? 'active' : ''; ?>" href="auth.php?role=coordinator&mode=<?php echo $mode; ?>">Coordinator</a>
        </div>
        <div class="auth-tabs">
            <a class="<?php echo $mode === 'login' ? 'active' : ''; ?>" href="auth.php?role=<?php echo htmlspecialchars($selectedRole); ?>&mode=login">Login</a>
            <a class="<?php echo $mode === 'register' ? 'active' : ''; ?>" href="auth.php?role=<?php echo htmlspecialchars($selectedRole); ?>&mode=register">Register</a>
        </div>
        <?php if (!empty($_SESSION['notification'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['notification']); unset($_SESSION['notification']); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="auth-alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if ($mode === 'login'): ?>
            <form class="auth-form" method="POST">
                <input type="hidden" name="action" value="login"/>
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($selectedRole); ?>"/>
                <div class="field"><label>Email</label><input type="email" name="email" required/></div>
                <div class="field"><label>Password</label><input type="password" name="password" required/></div>
                <button class="btn btn-teal" type="submit">Login</button>
            </form>
        <?php else: ?>
            <form class="auth-form" method="POST">
                <input type="hidden" name="action" value="register"/>
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($selectedRole); ?>"/>
                <div class="field"><label><?php echo $selectedRole === 'hospital' ? 'Hospital Name' : 'Coordinator Name'; ?></label><input type="text" name="name" required/></div>
                <div class="field"><label>Email</label><input type="email" name="email" required/></div>
                <div class="field"><label>Phone</label><input type="text" name="phone"/></div>
                <div class="field"><label>City</label><input type="text" name="city"/></div>
                <?php if ($selectedRole === 'hospital'): ?>
                    <div class="field"><label>Registration Number</label><input type="text" name="registration_number"/></div>
                <?php else: ?>
                    <div class="field"><label>Employee ID</label><input type="text" name="employee_id"/></div>
                <?php endif; ?>
                <div class="field"><label>Password</label><input type="password" name="password" required/></div>
                <div class="field"><label>Confirm Password</label><input type="password" name="confirm_password" required/></div>
                <button class="btn btn-teal" type="submit">Create Account</button>
            </form>
        <?php endif; ?>
    </section>
</div>
</body>
</html>

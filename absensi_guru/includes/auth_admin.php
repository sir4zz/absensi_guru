<?php
// ============================================
// AUTH CHECK - ADMIN
// File: includes/auth_admin.php
// ============================================
require_once __DIR__ . '/config.php';

// Cek timeout session
if (isset($_SESSION['timeout']) && $_SESSION['timeout']) {
    unset($_SESSION['timeout']);
    redirect('../index.php?error=timeout');
}

// Cek apakah sudah login sebagai admin
if (!isAdminLoggedIn()) {
    session_unset();
    session_destroy();
    redirect('../index.php?error=login_required');
}

// Validasi admin_id ada di database (cegah session palsu)
$admin_id = (int)$_SESSION['admin_id'];
if ($admin_id <= 0) {
    session_unset();
    session_destroy();
    redirect('../index.php?error=invalid_session');
}

$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_unset();
    session_destroy();
    redirect('../index.php?error=invalid_session');
}

$admin = $result->fetch_assoc();
$stmt->close();

// Tambahkan header keamanan di setiap halaman admin
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
?>

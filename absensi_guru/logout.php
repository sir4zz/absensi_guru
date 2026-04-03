<?php
// ============================================
// LOGOUT
// File: logout.php
// ============================================
require_once 'includes/config.php';

// Hapus semua variabel session
session_unset();

// Hapus cookie session di browser
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Hancurkan session di server
session_destroy();

// Redirect ke login dengan header anti-cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
redirect('index.php?logout=1');
?>

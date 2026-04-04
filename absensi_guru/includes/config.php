<?php
// ============================================
// KONFIGURASI DATABASE & KEAMANAN
// File: includes/config.php
// ============================================

// Set timezone ke WIB (Waktu Indonesia Barat)
date_default_timezone_set('Asia/Jakarta');

// ============================================
// SESSION SECURITY — harus sebelum session_start()
// ============================================
ini_set('session.use_strict_mode', 1);       // Tolak session ID yang tidak dibuat server
ini_set('session.use_only_cookies', 1);      // Session hanya lewat cookie, bukan URL
ini_set('session.use_trans_sid', 0);         // Larang session ID di URL
ini_set('session.cookie_httponly', 1);       // Cookie tidak bisa diakses JavaScript
ini_set('session.cookie_samesite', 'Lax');   // Lax agar tidak logout saat pindah tab
ini_set('session.gc_maxlifetime', 7200);     // Session hidup max 2 jam di server

define('SESSION_TIMEOUT', 7200); // Timeout 2 jam tidak aktif

session_start();

// --- Cek timeout tidak aktif ---
// Fingerprint dihapus: user-agent bisa berubah antar request dan menyebabkan logout tidak terduga
if (isset($_SESSION['last_activity']) && (isset($_SESSION['guru_id']) || isset($_SESSION['admin_id']))) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['timeout'] = true;
    }
}

// Update last_activity hanya jika sudah login
if (isset($_SESSION['guru_id']) || isset($_SESSION['admin_id'])) {
    $_SESSION['last_activity'] = time();
}

// --- Catatan keamanan ---
// session_regenerate_id() berkala dihapus karena menyebabkan tab lain logout:
// saat tab pertama regenerate session ID, tab lain yang masih pakai ID lama akan invalid.

// ============================================
// KONFIGURASI LOKASI SEKOLAH & ABSENSI
// ============================================
define('SEKOLAH_LAT',   -6.2011);   // Latitude SMKN 11 Kab. Tangerang
define('SEKOLAH_LNG',   106.393);   // Longitude SMKN 11 Kab. Tangerang
define('RADIUS_METER',  200);       // Radius toleransi absensi (meter)
define('UPLOAD_SELFIE_DIR', '../uploads/selfie/'); // Relatif dari folder guru/

// ============================================
// KONEKSI DATABASE
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // Ganti dengan username MySQL Anda
define('DB_PASS', '');             // Ganti dengan password MySQL Anda
define('DB_NAME', 'absensi_guru');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;padding:20px;background:#fee;border:1px solid #f00;border-radius:8px;">
        <h3>❌ Koneksi Database Gagal</h3>
        <p>' . htmlspecialchars($conn->connect_error) . '</p>
        <p>Pastikan XAMPP MySQL sudah berjalan dan konfigurasi di <code>includes/config.php</code> sudah benar.</p>
    </div>');
}

$conn->set_charset("utf8mb4");

// ============================================
// FUNGSI HELPER
// ============================================
function sanitize($conn, $data) {
    return $conn->real_escape_string(trim($data));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

// Cek login guru
function isLoggedIn() {
    return isset($_SESSION['guru_id']) && !empty($_SESSION['guru_id']);
}

// Cek login admin
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// ============================================
// LAZY CHECK ALPHA
// Dipanggil di setiap halaman dashboard/absensi.
// Jam trigger bisa diatur lewat konstanta JAM_ALPHA.
// ============================================
define('JAM_ALPHA', '12:00:00'); // Jam cut-off alpha
define('BATAS_KLARIFIKASI_HARI', 2); // Batas hari pengajuan klarifikasi

function cekDanInsertAlpha($conn) {
    $today = date('Y-m-d');
    $now   = date('H:i:s');

    // Hanya jalankan jika sudah lewat jam cut-off
    if ($now < JAM_ALPHA) return;

    // Ambil semua guru yang belum punya record absensi hari ini
    $sql = "SELECT id FROM guru WHERE id NOT IN (
                SELECT guru_id FROM absensi WHERE tanggal = '$today'
            )";
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) return;

    while ($row = $result->fetch_assoc()) {
        $conn->query("INSERT IGNORE INTO absensi (guru_id, tanggal, status)
                      VALUES ({$row['id']}, '$today', 'alpha')");
    }
}

function getTodayDate() {
    return date('Y-m-d');
}

function getCurrentTime() {
    return date('H:i:s');
}

function formatDate($date) {
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $d = explode('-', $date);
    return $d[2] . ' ' . $bulan[(int)$d[1]] . ' ' . $d[0];
}

function formatTime($time) {
    if (!$time) return '-';
    return date('H:i', strtotime($time));
}
?>

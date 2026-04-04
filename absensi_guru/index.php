<?php
// ============================================
// HALAMAN LOGIN
// File: index.php
// ============================================
require_once 'includes/config.php';

// Redirect jika sudah login
if (isLoggedIn()) {
    redirect('guru/dashboard.php');
}
if (isAdminLoggedIn()) {
    redirect('admin/dashboard.php');
}

// Tambahkan header anti-cache agar halaman tidak bisa di-back ke dashboard
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$error = '';

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type     = sanitize($conn, $_POST['type'] ?? 'guru');
    $password = $_POST['password'] ?? '';

    if ($type === 'guru') {
        $nip = sanitize($conn, $_POST['nip'] ?? '');
        if (empty($nip) || empty($password)) {
            $error = 'NIP dan password harus diisi.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM guru WHERE nip = ?");
            $stmt->bind_param("s", $nip);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $guru = $result->fetch_assoc();
                if (verifyPassword($password, $guru['password'])) {
                    // Regenerasi session ID saat login (cegah session fixation)
                    session_regenerate_id(true);
                    $_SESSION['guru_id']      = $guru['id'];
                    $_SESSION['guru_nama']    = $guru['nama'];
                    $_SESSION['last_activity'] = time();
                    $stmt->close();
                    redirect('guru/dashboard.php');
                } else {
                    $error = 'Password salah. Silakan coba lagi.';
                }
            } else {
                $error = 'NIP tidak ditemukan.';
            }
            $stmt->close();
        }
    } elseif ($type === 'admin') {
        $username = sanitize($conn, $_POST['username'] ?? '');
        if (empty($username) || empty($password)) {
            $error = 'Username dan password harus diisi.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                if (verifyPassword($password, $admin['password'])) {
                    // Regenerasi session ID saat login
                    session_regenerate_id(true);
                    $_SESSION['admin_id']      = $admin['id'];
                    $_SESSION['admin_nama']    = $admin['nama'];
                    $_SESSION['last_activity'] = time();
                    $stmt->close();
                    redirect('admin/dashboard.php');
                } else {
                    $error = 'Password salah. Silakan coba lagi.';
                }
            } else {
                $error = 'Username tidak ditemukan.';
            }
            $stmt->close();
        }
    }
}

$getError = $_GET['error'] ?? '';
if ($error) {
    $errorMsg = $error;
} elseif ($getError === 'login_required') {
    $errorMsg = 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.';
} elseif ($getError === 'timeout') {
    $errorMsg = 'Sesi Anda telah berakhir karena tidak aktif selama 60 menit. Silakan login kembali.';
} elseif ($getError === 'invalid_session') {
    $errorMsg = 'Sesi tidak valid. Silakan login kembali.';
} else {
    $errorMsg = '';
}

$successMsg = (($_GET['logout'] ?? '') === '1') ? 'Anda berhasil keluar. Sampai jumpa!' : '';

$activeTab = $_POST['type'] ?? 'guru';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Absensi Guru</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-body">
    <div class="login-card">
        <div class="login-logo">
            <div class="icon">🏫</div>
            <h1>Sistem Absensi Guru</h1>
            <p>Selamat datang, silakan login untuk melanjutkan</p>
        </div>

        <?php if ($errorMsg): ?>
        <div class="alert alert-danger">
            <i class="fas fa-circle-exclamation"></i>
            <?= htmlspecialchars($errorMsg) ?>
        </div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($successMsg) ?>
        </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="login-tabs">
            <button class="login-tab <?= $activeTab !== 'admin' ? 'active' : '' ?>" 
                    onclick="switchTab('guru')">
                <i class="fas fa-chalkboard-teacher"></i> Login Guru
            </button>
            <button class="login-tab <?= $activeTab === 'admin' ? 'active' : '' ?>" 
                    onclick="switchTab('admin')">
                <i class="fas fa-user-shield"></i> Login Admin
            </button>
        </div>

        <!-- Form Guru -->
        <div class="tab-content <?= $activeTab !== 'admin' ? 'active' : '' ?>" id="tab-guru">
            <form method="POST">
                <input type="hidden" name="type" value="guru">
                <div class="form-group">
                    <label>NIP (Nomor Induk Pegawai)</label>
                    <div class="input-group">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" name="nip" class="form-control" 
                               placeholder="Masukkan NIP Anda"
                               value="<?= htmlspecialchars($_POST['nip'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Masukkan password">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-full btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Masuk sebagai Guru
                </button>
            </form>
        </div>

        <!-- Form Admin -->
        <div class="tab-content <?= $activeTab === 'admin' ? 'active' : '' ?>" id="tab-admin">
            <form method="POST">
                <input type="hidden" name="type" value="admin">
                <div class="form-group">
                    <label>Username Admin</label>
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="username" class="form-control" 
                               placeholder="Masukkan username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Masukkan password">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-full btn-lg">
                    <i class="fas fa-user-shield"></i> Masuk sebagai Admin
                </button>
            </form>
        </div>

        <p class="text-center mt-3 fs-sm text-muted">
            <i class="fas fa-info-circle"></i>
            Demo: Admin: <strong>admin</strong> / <strong>password</strong> | 
            Guru NIP: <strong>198501012010011001</strong> / <strong>password</strong>
        </p>
    </div>

    <script>
    function switchTab(tab) {
        document.querySelectorAll('.login-tab').forEach((t, i) => {
            t.classList.toggle('active', (tab === 'guru' && i === 0) || (tab === 'admin' && i === 1));
        });
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
    }
    </script>
</body>
</html>

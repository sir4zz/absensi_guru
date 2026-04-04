<?php
// ============================================
// GANTI PASSWORD GURU
// File: guru/ganti_password.php
// ============================================
require_once '../includes/auth_guru.php';

$message     = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_lama  = $_POST['password_lama']  ?? '';
    $password_baru  = $_POST['password_baru']  ?? '';
    $password_ulang = $_POST['password_ulang'] ?? '';

    // Ambil password hash saat ini
    $res  = $conn->query("SELECT password FROM guru WHERE id = $guru_id");
    $data = $res->fetch_assoc();

    if (!verifyPassword($password_lama, $data['password'])) {
        $message     = 'Password lama yang Anda masukkan salah.';
        $messageType = 'danger';
    } elseif (strlen($password_baru) < 6) {
        $message     = 'Password baru minimal 6 karakter.';
        $messageType = 'danger';
    } elseif ($password_baru !== $password_ulang) {
        $message     = 'Konfirmasi password tidak cocok.';
        $messageType = 'danger';
    } elseif (verifyPassword($password_baru, $data['password'])) {
        $message     = 'Password baru tidak boleh sama dengan password lama.';
        $messageType = 'danger';
    } else {
        $hash = hashPassword($password_baru);
        $conn->query("UPDATE guru SET password = '$hash' WHERE id = $guru_id");
        $message     = 'Password berhasil diubah. Silakan gunakan password baru saat login berikutnya.';
        $messageType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - Sistem Absensi</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .password-card {
            max-width: 480px;
            margin: 0 auto;
        }
        .password-strength {
            margin-top: 6px;
            height: 4px;
            border-radius: 99px;
            background: var(--gray-200);
            overflow: hidden;
            transition: all .3s;
        }
        .password-strength-bar {
            height: 100%;
            border-radius: 99px;
            width: 0%;
            transition: width .3s, background .3s;
        }
        .strength-label {
            font-size: 0.75rem;
            margin-top: 4px;
            color: var(--gray-500);
            min-height: 16px;
        }
        .input-eye {
            position: relative;
        }
        .input-eye input {
            padding-right: 42px;
        }
        .input-eye .eye-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            padding: 0;
            font-size: 0.95rem;
            line-height: 1;
        }
        .input-eye .eye-btn:hover {
            color: var(--primary);
        }
        .req-list {
            list-style: none;
            padding: 0;
            margin: 8px 0 0;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .req-list li {
            font-size: 0.75rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color .2s;
        }
        .req-list li.ok {
            color: var(--success);
        }
        .req-list li i {
            font-size: 0.65rem;
        }
    </style>
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <div class="brand-icon">🏫</div>
                <div class="brand-text"><h2>Absensi Guru</h2><p>Portal Guru</p></div>
            </div>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($guru['nama'], 0, 2)) ?></div>
            <div class="user-info">
                <h4><?= htmlspecialchars(explode(',', $guru['nama'])[0]) ?></h4>
                <p>NIP: <?= htmlspecialchars($guru['nip']) ?></p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Menu Utama</div>
                <a href="dashboard.php" class="nav-item"><i class="fas fa-home nav-icon"></i> Dashboard</a>
                <a href="absensi.php" class="nav-item"><i class="fas fa-fingerprint nav-icon"></i> Absensi</a>
                <a href="riwayat.php" class="nav-item"><i class="fas fa-history nav-icon"></i> Riwayat Absensi</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Akun</div>
                <a href="ganti_password.php" class="nav-item active"><i class="fas fa-key nav-icon"></i> Ganti Password</a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-item" onclick="return confirm('Yakin ingin keluar?')">
                <i class="fas fa-sign-out-alt nav-icon"></i> Keluar
            </a>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1>Ganti Password</h1>
                <p>Perbarui kata sandi akun Anda</p>
            </div>
        </div>

        <div class="password-card">

            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'times-circle' ?>"></i>
                <?= $message ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-lock"></i> Ubah Kata Sandi</div>
                </div>
                <div class="card-body">
                    <form method="POST" id="form-password" autocomplete="off">

                        <div class="form-group">
                            <label>Password Lama <span style="color:red">*</span></label>
                            <div class="input-eye">
                                <input type="password" name="password_lama" id="password_lama"
                                       class="form-control" placeholder="Masukkan password saat ini" required>
                                <button type="button" class="eye-btn" onclick="toggleEye('password_lama', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Password Baru <span style="color:red">*</span></label>
                            <div class="input-eye">
                                <input type="password" name="password_baru" id="password_baru"
                                       class="form-control" placeholder="Minimal 6 karakter"
                                       required oninput="checkStrength(this.value); checkMatch()">
                                <button type="button" class="eye-btn" onclick="toggleEye('password_baru', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strength-bar"></div>
                            </div>
                            <div class="strength-label" id="strength-label"></div>
                            <ul class="req-list" id="req-list">
                                <li id="req-len"><i class="fas fa-circle"></i> Min. 6 karakter</li>
                                <li id="req-upper"><i class="fas fa-circle"></i> Huruf kapital</li>
                                <li id="req-num"><i class="fas fa-circle"></i> Angka</li>
                                <li id="req-sym"><i class="fas fa-circle"></i> Simbol</li>
                            </ul>
                        </div>

                        <div class="form-group">
                            <label>Konfirmasi Password Baru <span style="color:red">*</span></label>
                            <div class="input-eye">
                                <input type="password" name="password_ulang" id="password_ulang"
                                       class="form-control" placeholder="Ketik ulang password baru"
                                       required oninput="checkMatch()">
                                <button type="button" class="eye-btn" onclick="toggleEye('password_ulang', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="strength-label" id="match-label"></div>
                        </div>

                        <div style="display:flex; gap:10px; margin-top:8px;">
                            <button type="submit" class="btn btn-primary" id="btn-submit">
                                <i class="fas fa-save"></i> Simpan Password Baru
                            </button>
                            <a href="dashboard.php" class="btn btn-outline">Batal</a>
                        </div>

                    </form>
                </div>
            </div>

            <div class="card" style="margin-top:16px;">
                <div class="card-body" style="padding:16px 20px;">
                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <i class="fas fa-shield-alt" style="color:var(--primary); margin-top:2px;"></i>
                        <div>
                            <strong style="font-size:0.875rem;">Tips Keamanan Password</strong>
                            <ul style="font-size:0.8125rem; color:var(--gray-600); margin:6px 0 0; padding-left:16px; line-height:1.8;">
                                <li>Gunakan kombinasi huruf besar, kecil, angka, dan simbol</li>
                                <li>Jangan gunakan tanggal lahir atau NIP sebagai password</li>
                                <li>Jangan bagikan password kepada siapapun</li>
                                <li>Ganti password secara berkala untuk keamanan akun</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
function toggleEye(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function checkStrength(val) {
    const bar   = document.getElementById('strength-bar');
    const label = document.getElementById('strength-label');

    const hasLen   = val.length >= 6;
    const hasUpper = /[A-Z]/.test(val);
    const hasNum   = /[0-9]/.test(val);
    const hasSym   = /[^A-Za-z0-9]/.test(val);

    // Update requirement indicators
    setReq('req-len',   hasLen);
    setReq('req-upper', hasUpper);
    setReq('req-num',   hasNum);
    setReq('req-sym',   hasSym);

    const score = [hasLen, hasUpper, hasNum, hasSym].filter(Boolean).length;

    const levels = [
        { pct: '0%',   color: '',                    text: '' },
        { pct: '25%',  color: 'var(--danger)',        text: '🔴 Sangat Lemah' },
        { pct: '50%',  color: 'var(--warning)',       text: '🟡 Sedang' },
        { pct: '75%',  color: '#3b82f6',              text: '🔵 Kuat' },
        { pct: '100%', color: 'var(--success)',       text: '🟢 Sangat Kuat' },
    ];

    const lv = val.length === 0 ? levels[0] : levels[score];
    bar.style.width      = lv.pct;
    bar.style.background = lv.color;
    label.textContent    = lv.text;
}

function setReq(id, ok) {
    const el   = document.getElementById(id);
    const icon = el.querySelector('i');
    if (ok) {
        el.classList.add('ok');
        icon.classList.replace('fa-circle', 'fa-check-circle');
    } else {
        el.classList.remove('ok');
        icon.classList.replace('fa-check-circle', 'fa-circle');
    }
}

function checkMatch() {
    const baru   = document.getElementById('password_baru').value;
    const ulang  = document.getElementById('password_ulang').value;
    const label  = document.getElementById('match-label');
    const submit = document.getElementById('btn-submit');

    if (ulang.length === 0) {
        label.textContent = '';
        label.style.color = '';
        return;
    }
    if (baru === ulang) {
        label.textContent = '✅ Password cocok';
        label.style.color = 'var(--success)';
        submit.disabled   = false;
    } else {
        label.textContent = '❌ Password tidak cocok';
        label.style.color = 'var(--danger)';
        submit.disabled   = true;
    }
}

// Reset disabled state on page load
document.getElementById('btn-submit').disabled = false;
</script>
</body>
</html>

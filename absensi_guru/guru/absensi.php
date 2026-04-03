<?php
require_once '../includes/auth_guru.php';

// Lazy check alpha
cekDanInsertAlpha($conn);

$today = getTodayDate();
$message = '';
$messageType = '';

// Support klarifikasi dari tanggal lampau (via riwayat.php)
$tgl_target = sanitize($conn, $_GET['tgl'] ?? $today);
// Validasi format tanggal, fallback ke today jika tidak valid
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_target)) $tgl_target = $today;
$is_past = ($tgl_target !== $today);

$JAM_MASUK_IDEAL  = '07:00';
$JAM_MASUK_MULAI  = '06:45';
$JAM_MASUK_AKHIR  = '08:00';
$JAM_PULANG_IDEAL = '16:00';
$JAM_PULANG_MULAI = '15:45';
$JAM_PULANG_AKHIR = '17:00';

function getKeteranganMasuk($jam) {
    global $JAM_MASUK_IDEAL;
    return (strtotime($jam) <= strtotime($JAM_MASUK_IDEAL)) ? 'Tepat Waktu' : 'Terlambat';
}
function getKeteranganPulang($jam) {
    global $JAM_PULANG_IDEAL;
    return (strtotime($jam) >= strtotime($JAM_PULANG_IDEAL)) ? 'Tepat Waktu' : 'Lebih Awal';
}

// Helper: simpan foto base64 ke file
function saveSelfie($base64data, $guru_id, $type) {
    $uploadDir = __DIR__ . '/../uploads/selfie/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if (preg_match('/^data:image\/(\w+);base64,/', $base64data, $matches)) {
        $ext  = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
        $data = base64_decode(substr($base64data, strpos($base64data, ',') + 1));
        if ($data === false) return null;
        $filename = 'selfie_' . $guru_id . '_' . $type . '_' . date('Ymd_His') . '.' . $ext;
        file_put_contents($uploadDir . $filename, $data);
        return $filename;
    }
    return null;
}

// Helper: simpan file bukti izin/sakit
function saveBukti($file, $guru_id, $type) {
    $uploadDir = __DIR__ . '/../uploads/bukti/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $allowedExt = ['jpg','jpeg','png','pdf'];
    $maxSize    = 5 * 1024 * 1024; // 5MB
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) return null;
    if ($file['size'] > $maxSize) return null;
    $filename = 'bukti_' . $guru_id . '_' . $type . '_' . date('Ymd_His') . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return $filename;
    }
    return null;
}

// Cek absensi hari ini
$cekQ    = $conn->query("SELECT * FROM absensi WHERE guru_id = $guru_id AND tanggal = '$tgl_target'");
$absensi = $cekQ->num_rows > 0 ? $cekQ->fetch_assoc() : null;
$status_hari_ini = $absensi['status'] ?? null;
$sudah_masuk     = !empty($absensi['jam_masuk']);
$sudah_pulang    = !empty($absensi['jam_pulang']);
$sudah_izin      = ($status_hari_ini === 'izin');
$sudah_sakit     = ($status_hari_ini === 'sakit');
$sudah_alpha     = ($status_hari_ini === 'alpha');

// Jika akses tanggal lampau tapi bukan alpha, redirect ke today
if ($is_past && !$sudah_alpha) {
    redirect('absensi.php');
}

// ============================================================
// PROSES POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($conn, $_POST['action'] ?? '');

    if ($action === 'masuk') {
        if ($sudah_izin || $sudah_sakit) {
            $message = 'Anda sudah mengajukan ' . strtoupper($status_hari_ini) . ' hari ini.';
            $messageType = 'warning';
        } elseif ($sudah_masuk) {
            $message = 'Anda sudah melakukan absen masuk hari ini.';
            $messageType = 'warning';
        } else {
            $foto_b64 = $_POST['foto'] ?? '';
            $lat      = floatval($_POST['lat'] ?? 0);
            $lng      = floatval($_POST['lng'] ?? 0);
            $fotoFile = saveSelfie($foto_b64, $guru_id, 'masuk');
            $fotoSql  = $fotoFile ? "'" . sanitize($conn, $fotoFile) . "'" : 'NULL';
            $latSql   = ($lat != 0) ? $lat : 'NULL';
            $lngSql   = ($lng != 0) ? $lng : 'NULL';
            $jam = getCurrentTime();
            $ket = sanitize($conn, getKeteranganMasuk($jam));
            if (!$absensi) {
                $conn->query("INSERT INTO absensi (guru_id, tanggal, status, jam_masuk, keterangan_masuk, foto_masuk, lat_masuk, lng_masuk)
                              VALUES ($guru_id, '$today', 'hadir', '$jam', '$ket', $fotoSql, $latSql, $lngSql)");
            } else {
                $conn->query("UPDATE absensi SET status='hadir', jam_masuk='$jam', keterangan_masuk='$ket',
                              foto_masuk=$fotoSql, lat_masuk=$latSql, lng_masuk=$lngSql
                              WHERE id={$absensi['id']}");
            }
            $icon = ($ket === 'Terlambat') ? '⚠️' : '✅';
            $message = "$icon Absen masuk dicatat pukul " . formatTime($jam) . " — $ket.";
            $messageType = ($ket === 'Terlambat') ? 'warning' : 'success';
        }

    } elseif ($action === 'pulang') {
        if ($sudah_izin || $sudah_sakit) {
            $message = 'Anda sudah mengajukan ' . strtoupper($status_hari_ini) . ' hari ini.';
            $messageType = 'warning';
        } elseif (!$sudah_masuk) {
            $message = 'Anda belum absen masuk. Harap absen masuk terlebih dahulu.';
            $messageType = 'danger';
        } elseif ($sudah_pulang) {
            $message = 'Anda sudah melakukan absen pulang hari ini.';
            $messageType = 'warning';
        } else {
            $foto_b64 = $_POST['foto'] ?? '';
            $lat      = floatval($_POST['lat'] ?? 0);
            $lng      = floatval($_POST['lng'] ?? 0);
            $fotoFile = saveSelfie($foto_b64, $guru_id, 'pulang');
            $fotoSql  = $fotoFile ? "'" . sanitize($conn, $fotoFile) . "'" : 'NULL';
            $latSql   = ($lat != 0) ? $lat : 'NULL';
            $lngSql   = ($lng != 0) ? $lng : 'NULL';
            $jam = getCurrentTime();
            $ket = sanitize($conn, getKeteranganPulang($jam));
            $conn->query("UPDATE absensi SET jam_pulang='$jam', keterangan_pulang='$ket',
                          foto_pulang=$fotoSql, lat_pulang=$latSql, lng_pulang=$lngSql
                          WHERE id={$absensi['id']}");
            $icon = ($ket === 'Lebih Awal') ? '⚠️' : '✅';
            $message = "$icon Absen pulang dicatat pukul " . formatTime($jam) . " — $ket.";
            $messageType = ($ket === 'Lebih Awal') ? 'warning' : 'success';
        }

    } elseif ($action === 'izin') {
        if ($absensi) {
            $message = 'Anda sudah memiliki catatan absensi hari ini.';
            $messageType = 'warning';
        } else {
            $keterangan = sanitize($conn, $_POST['keterangan'] ?? '');
            $buktiSql   = 'NULL';
            if (!empty($_FILES['bukti_file']['tmp_name'])) {
                $buktiFile = saveBukti($_FILES['bukti_file'], $guru_id, 'izin');
                if ($buktiFile) $buktiSql = "'" . sanitize($conn, $buktiFile) . "'";
            }
            $conn->query("INSERT INTO absensi (guru_id, tanggal, status, keterangan, bukti_file) VALUES ($guru_id, '$today', 'izin', '$keterangan', $buktiSql)");
            $message = '✅ Izin berhasil dicatat untuk hari ini.';
            $messageType = 'success';
        }

    } elseif ($action === 'sakit') {
        if ($absensi) {
            $message = 'Anda sudah memiliki catatan absensi hari ini.';
            $messageType = 'warning';
        } else {
            $keterangan = sanitize($conn, $_POST['keterangan'] ?? '');
            $buktiSql   = 'NULL';
            if (!empty($_FILES['bukti_file']['tmp_name'])) {
                $buktiFile = saveBukti($_FILES['bukti_file'], $guru_id, 'sakit');
                if ($buktiFile) $buktiSql = "'" . sanitize($conn, $buktiFile) . "'";
            }
            $conn->query("INSERT INTO absensi (guru_id, tanggal, status, keterangan, bukti_file) VALUES ($guru_id, '$today', 'sakit', '$keterangan', $buktiSql)");
            $message = '✅ Keterangan sakit berhasil dicatat untuk hari ini.';
            $messageType = 'success';
        }

    } elseif ($action === 'klarifikasi') {
        // Guru mengajukan klarifikasi dari status alpha
        if (!$absensi || $status_hari_ini !== 'alpha') {
            $message = 'Hanya absensi dengan status Alpha yang bisa diklarifikasi.';
            $messageType = 'warning';
        } elseif (!empty($absensi['klarifikasi_status'])) {
            $message = 'Anda sudah pernah mengajukan klarifikasi untuk tanggal ini.';
            $messageType = 'warning';
        } else {
            // Cek batas waktu
            $tgl_alpha  = new DateTime($absensi['tanggal']);
            $tgl_batas  = (clone $tgl_alpha)->modify('+' . BATAS_KLARIFIKASI_HARI . ' days');
            $tgl_now    = new DateTime(date('Y-m-d'));
            if ($tgl_now > $tgl_batas) {
                $message = 'Batas waktu pengajuan klarifikasi telah habis.';
                $messageType = 'danger';
            } else {
                $klarifikasi_jenis   = sanitize($conn, $_POST['klarifikasi_jenis'] ?? '');
                $klarifikasi_alasan  = sanitize($conn, $_POST['klarifikasi_alasan'] ?? '');
                if (!in_array($klarifikasi_jenis, ['izin', 'sakit'])) {
                    $message = 'Jenis klarifikasi tidak valid.';
                    $messageType = 'danger';
                } elseif (empty($klarifikasi_alasan)) {
                    $message = 'Alasan klarifikasi wajib diisi.';
                    $messageType = 'danger';
                } else {
                    $klarBuktiSql = 'NULL';
                    if (!empty($_FILES['klarifikasi_bukti']['tmp_name'])) {
                        $klarBuktiFile = saveBukti($_FILES['klarifikasi_bukti'], $guru_id, 'klarifikasi');
                        if ($klarBuktiFile) $klarBuktiSql = "'" . sanitize($conn, $klarBuktiFile) . "'";
                    }
                    $now_ts = date('Y-m-d H:i:s');
                    $conn->query("UPDATE absensi SET
                        klarifikasi_alasan = '$klarifikasi_alasan',
                        klarifikasi_bukti  = $klarBuktiSql,
                        klarifikasi_status = 'pending',
                        klarifikasi_at     = '$now_ts',
                        keterangan         = '$klarifikasi_jenis'
                        WHERE id = {$absensi['id']}");
                    $message = '✅ Pengajuan klarifikasi berhasil dikirim. Menunggu persetujuan admin.';
                    $messageType = 'success';
                }
            }
        }

    } // end elseif ($action === 'klarifikasi')

    $cekQ2 = $conn->query("SELECT * FROM absensi WHERE guru_id = $guru_id AND tanggal = '$tgl_target'");
    $absensi = $cekQ2->num_rows > 0 ? $cekQ2->fetch_assoc() : null;
    $status_hari_ini = $absensi['status'] ?? null;
    $sudah_masuk  = !empty($absensi['jam_masuk']);
    $sudah_pulang = !empty($absensi['jam_pulang']);
    $sudah_izin   = ($status_hari_ini === 'izin');
    $sudah_sakit  = ($status_hari_ini === 'sakit');
    $sudah_alpha  = ($status_hari_ini === 'alpha');
}

$activeForm = $_GET['form'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi - Sistem Absensi Guru</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .absen-big-btn {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 10px; padding: 32px 20px;
            border-radius: 18px; border: 2.5px solid; cursor: pointer;
            transition: all 0.2s; width: 100%; background: white; text-align: center;
        }
        .absen-big-btn.masuk   { border-color: var(--success); }
        .absen-big-btn.pulang  { border-color: var(--danger); }
        .absen-big-btn.izin-btn { border-color: var(--primary); }
        .absen-big-btn.sakit-btn{ border-color: var(--warning); }
        .absen-big-btn:not(:disabled):hover { transform: translateY(-3px); }
        .absen-big-btn.masuk:not(:disabled):hover  { background: var(--success); color: white; box-shadow: 0 10px 28px rgba(16,185,129,.3); }
        .absen-big-btn.pulang:not(:disabled):hover { background: var(--danger);  color: white; box-shadow: 0 10px 28px rgba(239,68,68,.3); }
        .absen-big-btn.izin-btn:not(:disabled):hover  { background: var(--primary); color: white; box-shadow: 0 10px 28px rgba(26,86,219,.3); }
        .absen-big-btn.sakit-btn:not(:disabled):hover { background: var(--warning); color: white; box-shadow: 0 10px 28px rgba(245,158,11,.3); }
        .absen-big-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .absen-big-btn .btn-icon  { font-size: 40px; }
        .absen-big-btn .btn-label { font-size: 1.0625rem; font-weight: 800; }
        .absen-big-btn .btn-sub   { font-size: 0.8125rem; opacity: 0.75; }
        .absen-big-btn.done-masuk  { background: #d1fae5; border-color: var(--success); }
        .absen-big-btn.done-pulang { background: #fee2e2; border-color: var(--danger); }
        .grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; }
        @media(max-width:768px) { .grid-4 { grid-template-columns: 1fr 1fr; } }
        .form-section { background: var(--gray-100); border-radius: 12px; padding: 20px; margin-top: 16px; border: 2px dashed var(--gray-200); animation: slideDown 0.3s ease; }
        .upload-area {
            border: 2px dashed var(--gray-300); border-radius: 12px; padding: 24px 16px;
            text-align: center; cursor: pointer; background: white; transition: all .2s;
        }
        .upload-area:hover { border-color: var(--primary); background: var(--primary-light); }
        .upload-area.has-file { border-color: var(--success); background: #f0fdf4; }
        .upload-icon { font-size: 2rem; margin-bottom: 6px; }
        .upload-text { font-weight: 600; font-size: 0.9rem; color: var(--gray-700); }
        .upload-hint { font-size: 0.775rem; color: var(--gray-400); margin-top: 4px; }
        .bukti-preview-img { max-width: 100%; max-height: 220px; border-radius: 10px; border: 2px solid var(--gray-200); object-fit: contain; }
        .bukti-preview-pdf { display:flex; align-items:center; gap:10px; background:#f8fafc; padding:12px 14px; border-radius:10px; border:1.5px solid var(--gray-200); font-size:0.875rem; }
        .bukti-clear { background:none; border:none; color:var(--danger); cursor:pointer; font-size:0.85rem; margin-top:6px; padding:0; font-weight:600; }

        /* MODAL */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.72); z-index: 9999;
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white; border-radius: 20px; padding: 28px;
            width: 95%; max-width: 460px; box-shadow: 0 20px 60px rgba(0,0,0,.4);
            animation: scaleIn .22s ease;
        }
        @keyframes scaleIn { from{transform:scale(.85);opacity:0} to{transform:scale(1);opacity:1} }
        .modal-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }

        /* KAMERA */
        #camera-video {
            width: 100%; border-radius: 12px; background: #111;
            aspect-ratio: 4/3; object-fit: cover; display: block;
        }
        #camera-canvas { display: none; }
        #foto-preview { width: 100%; border-radius: 12px; display: none; aspect-ratio: 4/3; object-fit: cover; }

        /* GPS */
        .gps-status {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 14px; border-radius: 10px; margin-top: 12px;
            font-size: 0.855rem; font-weight: 600; line-height: 1.4;
        }
        .gps-status.loading { background: #fef3c7; color: #92400e; }
        .gps-status.ok      { background: #d1fae5; color: #065f46; }
        .gps-status.error   { background: #fee2e2; color: #991b1b; }
        .gps-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .gps-status.loading .gps-dot { background: #f59e0b; animation: pulse 1s infinite; }
        .gps-status.ok      .gps-dot { background: #10b981; }
        .gps-status.error   .gps-dot { background: #ef4444; }
        @keyframes pulse { 0%,100%{opacity:1}50%{opacity:.35} }

        /* TOMBOL MODAL */
        .cam-btn-row { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
        .btn-cam { flex: 1; min-width: 100px; padding: 11px 8px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer; font-size: 0.9rem; transition: all .18s; }
        .btn-cam.capture { background: var(--primary); color: white; }
        .btn-cam.retake  { background: var(--gray-200); color: var(--gray-700); }
        .btn-cam.confirm { background: var(--success); color: white; }
        .btn-cam.cancel  { background: var(--gray-100); color: var(--gray-600); border: 1.5px solid var(--gray-300); }
        .btn-cam:disabled { opacity: .4; cursor: not-allowed; }
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
                <a href="absensi.php" class="nav-item active"><i class="fas fa-fingerprint nav-icon"></i> Absensi</a>
                <a href="riwayat.php" class="nav-item"><i class="fas fa-history nav-icon"></i> Riwayat Absensi</a>
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
                <h1><?= $is_past ? 'Klarifikasi Alpha' : 'Absensi Harian' ?></h1>
                <p><?= $is_past ? 'Pengajuan klarifikasi untuk tanggal lampau' : 'Catat kehadiran Anda hari ini' ?></p>
            </div>
            <div class="topbar-date">
                <i class="fas fa-calendar-day"></i>
                <?= formatDate($tgl_target) ?>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <i class="fas fa-<?= $messageType==='success' ? 'check-circle' : ($messageType==='danger' ? 'times-circle' : 'exclamation-triangle') ?>"></i>
            <?= $message ?>
        </div>
        <?php endif; ?>

        <?php if ($sudah_izin || $sudah_sakit): ?>
        <div class="card">
            <div class="card-body" style="text-align:center; padding:40px 20px;">
                <div style="font-size:64px; margin-bottom:16px;"><?= $sudah_izin ? '📋' : '🏥' ?></div>
                <h2 style="font-size:1.375rem; margin-bottom:8px;">
                    Anda tercatat <span style="color:<?= $sudah_izin ? 'var(--primary)' : 'var(--warning)' ?>">
                    <?= $sudah_izin ? 'Izin' : 'Sakit' ?></span> hari ini
                </h2>
                <?php if (!empty($absensi['keterangan'])): ?>
                <p style="color:var(--gray-600); margin-bottom:12px;">
                    Keterangan: <em>"<?= htmlspecialchars($absensi['keterangan']) ?>"</em>
                </p>
                <?php endif; ?>
                <?php if (!empty($absensi['bukti_file'])): ?>
                <?php $buktiExt = strtolower(pathinfo($absensi['bukti_file'], PATHINFO_EXTENSION)); ?>
                <div style="margin-bottom:16px;">
                    <p style="font-size:0.85rem;color:var(--gray-500);margin-bottom:8px;"><i class="fas fa-paperclip"></i> Bukti yang diunggah:</p>
                    <?php if (in_array($buktiExt, ['jpg','jpeg','png'])): ?>
                        <img src="../uploads/bukti/<?= htmlspecialchars($absensi['bukti_file']) ?>"
                             style="max-width:260px;max-height:200px;border-radius:10px;border:2px solid var(--gray-200);object-fit:contain;cursor:pointer;"
                             onclick="this.style.maxWidth=this.style.maxWidth==='100%'?'260px':'100%'">
                        <div style="font-size:0.75rem;color:var(--gray-400);margin-top:4px;">Klik gambar untuk perbesar</div>
                    <?php else: ?>
                        <a href="../uploads/bukti/<?= htmlspecialchars($absensi['bukti_file']) ?>" target="_blank" class="btn btn-outline" style="display:inline-flex;gap:8px;font-size:0.875rem;">
                            <i class="fas fa-file-pdf" style="color:#dc2626;"></i> Lihat Surat / Bukti PDF
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>

        <?php elseif ($sudah_alpha): ?>
        <!-- ===================== TAMPILAN ALPHA ===================== -->
        <?php
            $tgl_alpha        = new DateTime($absensi['tanggal']);
            $tgl_batas        = (clone $tgl_alpha)->modify('+' . BATAS_KLARIFIKASI_HARI . ' days');
            $tgl_sekarang     = new DateTime(date('Y-m-d'));
            $masih_bisa       = $tgl_sekarang <= $tgl_batas;
            $klarifikasi_status = $absensi['klarifikasi_status'] ?? null;
        ?>
        <div class="card">
            <div class="card-body" style="padding:32px 24px;">
                <div style="text-align:center; margin-bottom:24px;">
                    <div style="font-size:64px; margin-bottom:12px;">🚫</div>
                    <h2 style="font-size:1.375rem; color:var(--danger); margin-bottom:6px;">Alpha — Tidak Hadir Tanpa Keterangan</h2>
                    <p style="color:var(--gray-600); font-size:0.875rem;">
                        Tidak ada catatan kehadiran hingga pukul <?= date('H:i', strtotime(JAM_ALPHA)) ?> WIB
                    </p>
                </div>

                <?php if ($klarifikasi_status === 'pending'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i>
                        <div>
                            <strong>Pengajuan klarifikasi sedang menunggu persetujuan admin.</strong><br>
                            <span style="font-size:0.85rem;">Alasan: <?= htmlspecialchars($absensi['klarifikasi_alasan']) ?></span>
                        </div>
                    </div>

                <?php elseif ($klarifikasi_status === 'approved'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Klarifikasi Anda disetujui oleh admin.</strong><br>
                            <span style="font-size:0.85rem;">Status telah diperbarui sesuai pengajuan Anda.</span>
                        </div>
                    </div>

                <?php elseif ($klarifikasi_status === 'rejected'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle"></i>
                        <div>
                            <strong>Klarifikasi ditolak oleh admin.</strong><br>
                            <?php if (!empty($absensi['klarifikasi_catatan_admin'])): ?>
                            <span style="font-size:0.85rem;">Catatan admin: <?= htmlspecialchars($absensi['klarifikasi_catatan_admin']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($masih_bisa): ?>
                    <!-- Form Klarifikasi -->
                    <div style="background:var(--gray-100); border-radius:12px; padding:20px; border:2px dashed var(--danger);">
                        <h4 style="margin-bottom:14px; color:var(--danger);"><i class="fas fa-file-alt"></i> Ajukan Klarifikasi Alpha</h4>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="klarifikasi">
                            <?php if ($is_past): ?>
                            <input type="hidden" name="tgl" value="<?= htmlspecialchars($tgl_target) ?>">
                            <?php endif; ?>
                            <div class="form-group">
                                <label>Ubah Status Menjadi <span style="color:var(--danger);">*</span></label>
                                <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:6px;">
                                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border:2px solid var(--gray-300);border-radius:10px;font-weight:600;flex:1;min-width:120px;" id="label-izin">
                                        <input type="radio" name="klarifikasi_jenis" value="izin" required onchange="updateJenisStyle()">
                                        <span>📋 Izin</span>
                                    </label>
                                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border:2px solid var(--gray-300);border-radius:10px;font-weight:600;flex:1;min-width:120px;" id="label-sakit">
                                        <input type="radio" name="klarifikasi_jenis" value="sakit" required onchange="updateJenisStyle()">
                                        <span>🏥 Sakit</span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Alasan / Keterangan <span style="color:var(--danger);">*</span></label>
                                <textarea name="klarifikasi_alasan" class="form-control" rows="3"
                                    placeholder="Jelaskan alasan ketidakhadiran Anda..." required style="resize:vertical;"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Bukti Pendukung <span style="color:var(--gray-400); font-weight:400;">(opsional — JPG, PNG, PDF maks. 5MB)</span></label>
                                <div class="upload-area" id="upload-area-klarifikasi" onclick="document.getElementById('bukti-klarifikasi').click()">
                                    <div class="upload-icon">📎</div>
                                    <div class="upload-text">Klik untuk pilih file atau foto</div>
                                    <div class="upload-hint">JPG · PNG · PDF · maks. 5MB</div>
                                </div>
                                <input type="file" name="klarifikasi_bukti" id="bukti-klarifikasi" accept=".jpg,.jpeg,.png,.pdf" style="display:none;"
                                       onchange="previewBukti(this,'preview-klarifikasi','upload-area-klarifikasi')">
                                <div id="preview-klarifikasi" style="display:none; margin-top:10px;"></div>
                            </div>
                            <div style="background:#fff3cd; border-radius:8px; padding:12px 14px; font-size:0.825rem; color:#856404; margin-bottom:14px;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Perhatian:</strong> Pengajuan klarifikasi hanya bisa dilakukan <strong>1 kali</strong>.
                                Jika ditolak admin, status Alpha tidak dapat diubah lagi.
                                Batas waktu pengajuan: <strong><?= formatDate($tgl_batas->format('Y-m-d')) ?></strong>.
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button type="submit" class="btn btn-danger"
                                    onclick="return confirm('Yakin ingin mengajukan klarifikasi?\nPengajuan hanya bisa dilakukan 1 kali dan tidak dapat dibatalkan.')">
                                    <i class="fas fa-paper-plane"></i> Kirim Klarifikasi
                                </button>
                                <a href="dashboard.php" class="btn btn-outline">Kembali</a>
                            </div>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-lock"></i>
                        <div>
                            <strong>Batas waktu klarifikasi telah habis.</strong><br>
                            <span style="font-size:0.85rem;">Status Alpha ini tidak dapat lagi diklarifikasi. Batas waktu adalah <?= BATAS_KLARIFIKASI_HARI ?> hari setelah tanggal absen.</span>
                        </div>
                    </div>
                    <div style="text-align:center; margin-top:16px;">
                        <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-fingerprint"></i> Pilih Jenis Kehadiran</div>
                <span class="badge badge-primary"><?= formatDate($today) ?></span>
            </div>
            <div class="card-body">
                <div class="grid-4">

                    <!-- Absen Masuk -->
                    <?php if ($sudah_masuk): ?>
                        <div class="absen-big-btn done-masuk" style="cursor:default;">
                            <span class="btn-icon">✅</span>
                            <span class="btn-label" style="color:var(--success);">Sudah Masuk</span>
                            <span class="btn-sub" style="color:var(--success);"><?= formatTime($absensi['jam_masuk']) ?></span>
                            <?php if ($absensi['keterangan_masuk']): ?>
                            <span class="badge <?= $absensi['keterangan_masuk']==='Terlambat'?'badge-danger':'badge-success' ?>" style="font-size:0.7rem;">
                                <?= $absensi['keterangan_masuk'] ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <button type="button" class="absen-big-btn masuk" onclick="bukaModal('masuk')">
                            <span class="btn-icon">🟢</span>
                            <span class="btn-label" style="color:var(--success);">Absen Masuk</span>
                            <span class="btn-sub" style="color:var(--success);">Tepat waktu: ≤ <?= $JAM_MASUK_IDEAL ?></span>
                        </button>
                    <?php endif; ?>

                    <!-- Absen Pulang -->
                    <?php if ($sudah_pulang): ?>
                        <div class="absen-big-btn done-pulang" style="cursor:default;">
                            <span class="btn-icon">✅</span>
                            <span class="btn-label" style="color:var(--danger);">Sudah Pulang</span>
                            <span class="btn-sub" style="color:var(--danger);"><?= formatTime($absensi['jam_pulang']) ?></span>
                            <?php if ($absensi['keterangan_pulang']): ?>
                            <span class="badge <?= $absensi['keterangan_pulang']==='Lebih Awal'?'badge-warning':'badge-success' ?>" style="font-size:0.7rem;">
                                <?= $absensi['keterangan_pulang'] ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!$sudah_masuk): ?>
                        <button type="button" class="absen-big-btn pulang" disabled>
                            <span class="btn-icon">🔒</span>
                            <span class="btn-label" style="color:var(--gray-400);">Absen Pulang</span>
                            <span class="btn-sub" style="color:var(--gray-400);">Absen masuk dulu</span>
                        </button>
                    <?php else: ?>
                        <button type="button" class="absen-big-btn pulang" onclick="bukaModal('pulang')">
                            <span class="btn-icon">🔴</span>
                            <span class="btn-label" style="color:var(--danger);">Absen Pulang</span>
                            <span class="btn-sub" style="color:var(--danger);">Tepat waktu: ≥ <?= $JAM_PULANG_IDEAL ?></span>
                        </button>
                    <?php endif; ?>

                    <!-- Izin -->
                    <?php if ($sudah_masuk): ?>
                        <button type="button" class="absen-big-btn izin-btn" disabled>
                            <span class="btn-icon">🔒</span>
                            <span class="btn-label" style="color:var(--gray-400);">Izin</span>
                            <span class="btn-sub" style="color:var(--gray-400);">Sudah absen masuk</span>
                        </button>
                    <?php else: ?>
                        <button type="button" class="absen-big-btn izin-btn" onclick="toggleForm('form-izin')">
                            <span class="btn-icon">📋</span>
                            <span class="btn-label" style="color:var(--primary);">Izin</span>
                            <span class="btn-sub" style="color:var(--primary);">Tidak hadir — izin</span>
                        </button>
                    <?php endif; ?>

                    <!-- Sakit -->
                    <?php if ($sudah_masuk): ?>
                        <button type="button" class="absen-big-btn sakit-btn" disabled>
                            <span class="btn-icon">🔒</span>
                            <span class="btn-label" style="color:var(--gray-400);">Sakit</span>
                            <span class="btn-sub" style="color:var(--gray-400);">Sudah absen masuk</span>
                        </button>
                    <?php else: ?>
                        <button type="button" class="absen-big-btn sakit-btn" onclick="toggleForm('form-sakit')">
                            <span class="btn-icon">🏥</span>
                            <span class="btn-label" style="color:var(--warning);">Sakit</span>
                            <span class="btn-sub" style="color:var(--warning);">Tidak hadir — sakit</span>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Form Izin -->
                <div class="form-section" id="form-izin" style="display:none;">
                    <h4 style="margin-bottom:14px; color:var(--primary);"><i class="fas fa-file-alt"></i> Form Izin</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="izin">
                        <div class="form-group">
                            <label>Alasan Izin <span style="color:var(--gray-400); font-weight:400;">(opsional)</span></label>
                            <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Keperluan keluarga, urusan administrasi, dll..." style="resize:vertical;"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Bukti / Surat Izin <span style="color:var(--gray-400); font-weight:400;">(opsional — JPG, PNG, PDF maks. 5MB)</span></label>
                            <div class="upload-area" id="upload-area-izin" onclick="document.getElementById('bukti-izin').click()">
                                <div class="upload-icon">📎</div>
                                <div class="upload-text">Klik untuk pilih file atau foto</div>
                                <div class="upload-hint">JPG · PNG · PDF · maks. 5MB</div>
                            </div>
                            <input type="file" name="bukti_file" id="bukti-izin" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="previewBukti(this,'preview-izin','upload-area-izin')">
                            <div id="preview-izin" style="display:none; margin-top:10px;"></div>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Yakin ingin mengajukan IZIN hari ini?\nSetelah dikonfirmasi, absen masuk/pulang tidak bisa dilakukan.')">
                                <i class="fas fa-check"></i> Konfirmasi Izin
                            </button>
                            <button type="button" class="btn btn-outline" onclick="toggleForm('form-izin')">Batal</button>
                        </div>
                    </form>
                </div>

                <!-- Form Sakit -->
                <div class="form-section" id="form-sakit" style="display:none;">
                    <h4 style="margin-bottom:14px; color:var(--warning);"><i class="fas fa-briefcase-medical"></i> Form Sakit</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="sakit">
                        <div class="form-group">
                            <label>Keterangan Sakit <span style="color:var(--gray-400); font-weight:400;">(opsional)</span></label>
                            <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Demam, flu, sakit kepala, dll..." style="resize:vertical;"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Bukti / Surat Dokter <span style="color:var(--gray-400); font-weight:400;">(opsional — JPG, PNG, PDF maks. 5MB)</span></label>
                            <div class="upload-area" id="upload-area-sakit" onclick="document.getElementById('bukti-sakit').click()">
                                <div class="upload-icon">📎</div>
                                <div class="upload-text">Klik untuk pilih file atau foto</div>
                                <div class="upload-hint">JPG · PNG · PDF · maks. 5MB</div>
                            </div>
                            <input type="file" name="bukti_file" id="bukti-sakit" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="previewBukti(this,'preview-sakit','upload-area-sakit')">
                            <div id="preview-sakit" style="display:none; margin-top:10px;"></div>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <button type="submit" class="btn btn-warning" onclick="return confirm('Yakin ingin melaporkan SAKIT hari ini?\nSetelah dikonfirmasi, absen masuk/pulang tidak bisa dilakukan.')">
                                <i class="fas fa-check"></i> Konfirmasi Sakit
                            </button>
                            <button type="button" class="btn btn-outline" onclick="toggleForm('form-sakit')">Batal</button>
                        </div>
                    </form>
                </div>

                <?php if ($sudah_masuk || $sudah_pulang): ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <?php if ($sudah_masuk): ?>
                        <div>✅ Jam Masuk: <strong><?= formatTime($absensi['jam_masuk']) ?></strong>
                            <?php if ($absensi['keterangan_masuk']): ?>
                            — <span class="badge <?= $absensi['keterangan_masuk']==='Terlambat'?'badge-danger':'badge-success' ?>"><?= $absensi['keterangan_masuk'] ?></span>
                            <?php endif; ?>
                            <?php if (!empty($absensi['lat_masuk'])): ?>
                            <span style="font-size:0.8rem;color:var(--gray-500);margin-left:6px;"><i class="fas fa-map-marker-alt"></i> GPS Tercatat</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($sudah_pulang): ?>
                        <div style="margin-top:4px;">✅ Jam Pulang: <strong><?= formatTime($absensi['jam_pulang']) ?></strong>
                            <?php if ($absensi['keterangan_pulang']): ?>
                            — <span class="badge <?= $absensi['keterangan_pulang']==='Lebih Awal'?'badge-warning':'badge-success' ?>"><?= $absensi['keterangan_pulang'] ?></span>
                            <?php endif; ?>
                            <?php if (!empty($absensi['lat_pulang'])): ?>
                            <span style="font-size:0.8rem;color:var(--gray-500);margin-left:6px;"><i class="fas fa-map-marker-alt"></i> GPS Tercatat</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Jadwal & Aturan -->
        <div class="grid-2">
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-clock"></i> Jadwal Absensi</div></div>
                <div class="card-body" style="padding:16px;">
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <div style="background:var(--success-light);border-radius:10px;padding:14px 16px;">
                            <div style="font-size:0.75rem;color:var(--success);font-weight:700;text-transform:uppercase;margin-bottom:6px;"><i class="fas fa-sign-in-alt"></i> Jam Masuk</div>
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
                                <div>
                                    <div style="font-size:1.5rem;font-weight:800;color:var(--success);"><?= $JAM_MASUK_IDEAL ?></div>
                                    <div style="font-size:0.75rem;color:var(--gray-600);">Jam masuk ideal</div>
                                </div>
                                <div style="text-align:right;font-size:0.8125rem;color:var(--gray-600);">
                                    <div>🟢 Tepat waktu: ≤ <?= $JAM_MASUK_IDEAL ?></div>
                                    <div>🔴 Terlambat: > <?= $JAM_MASUK_IDEAL ?></div>
                                    <div style="font-size:0.75rem;margin-top:4px;">Toleransi: <?= $JAM_MASUK_MULAI ?> – <?= $JAM_MASUK_AKHIR ?></div>
                                </div>
                            </div>
                        </div>
                        <div style="background:var(--danger-light);border-radius:10px;padding:14px 16px;">
                            <div style="font-size:0.75rem;color:var(--danger);font-weight:700;text-transform:uppercase;margin-bottom:6px;"><i class="fas fa-sign-out-alt"></i> Jam Pulang</div>
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
                                <div>
                                    <div style="font-size:1.5rem;font-weight:800;color:var(--danger);"><?= $JAM_PULANG_IDEAL ?></div>
                                    <div style="font-size:0.75rem;color:var(--gray-600);">Jam pulang ideal</div>
                                </div>
                                <div style="text-align:right;font-size:0.8125rem;color:var(--gray-600);">
                                    <div>🟢 Tepat waktu: ≥ <?= $JAM_PULANG_IDEAL ?></div>
                                    <div>🟡 Lebih awal: < <?= $JAM_PULANG_IDEAL ?></div>
                                    <div style="font-size:0.75rem;margin-top:4px;">Toleransi: <?= $JAM_PULANG_MULAI ?> – <?= $JAM_PULANG_AKHIR ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-info-circle"></i> Aturan Absensi</div></div>
                <div class="card-body">
                    <ul style="list-style:none;display:flex;flex-direction:column;gap:10px;">
                        <li style="display:flex;gap:10px;"><span style="color:var(--success);">✅</span> Absen masuk hanya bisa <strong>1 kali per hari</strong></li>
                        <li style="display:flex;gap:10px;"><span style="color:var(--success);">✅</span> Absen pulang hanya setelah <strong>absen masuk</strong></li>
                        <li style="display:flex;gap:10px;"><span style="color:var(--primary);">📋</span> Izin dan Sakit hanya bisa diajukan <strong>sebelum absen masuk</strong></li>
                        <li style="display:flex;gap:10px;"><span style="color:var(--primary);">📸</span> Wajib <strong>selfie</strong> saat absen masuk & pulang</li>
                        <li style="display:flex;gap:10px;"><span style="color:var(--primary);">📍</span> Wajib dalam radius <strong><?= RADIUS_METER ?>m</strong> dari sekolah</li>
                        <li style="display:flex;gap:10px;"><span style="color:var(--danger);">🔒</span> Absensi tidak dapat <strong>diubah</strong> oleh guru</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- ===================== MODAL KAMERA + GPS ===================== -->
<div class="modal-overlay" id="modal-absen">
    <div class="modal-box">
        <div class="modal-title">
            <span id="modal-icon">📸</span>
            <span id="modal-label">Absen Masuk</span>
        </div>

        <video id="camera-video" autoplay playsinline muted></video>
        <canvas id="camera-canvas"></canvas>
        <img id="foto-preview" alt="Foto Selfie">

        <div class="gps-status loading" id="gps-status">
            <span class="gps-dot"></span>
            <span id="gps-text">Mengambil lokasi GPS...</span>
        </div>

        <div class="cam-btn-row">
            <button class="btn-cam capture" id="btn-capture" onclick="ambilFoto()" disabled>
                <i class="fas fa-camera"></i> Ambil Foto
            </button>
            <button class="btn-cam retake" id="btn-retake" onclick="ulangiKamera()" style="display:none;">
                <i class="fas fa-redo"></i> Ulangi
            </button>
            <button class="btn-cam confirm" id="btn-confirm" onclick="konfirmasiAbsen()" style="display:none;" disabled>
                <i class="fas fa-check"></i> Konfirmasi
            </button>
            <button class="btn-cam cancel" onclick="tutupModal()">
                <i class="fas fa-times"></i> Batal
            </button>
        </div>

        <form id="form-absen" method="POST" style="display:none;">
            <input type="hidden" name="action" id="input-action">
            <input type="hidden" name="foto"   id="input-foto">
            <input type="hidden" name="lat"    id="input-lat">
            <input type="hidden" name="lng"    id="input-lng">
        </form>
    </div>
</div>

<script>
const SEKOLAH_LAT = <?= SEKOLAH_LAT ?>;
const SEKOLAH_LNG = <?= SEKOLAH_LNG ?>;
const RADIUS_M    = <?= RADIUS_METER ?>;

let stream = null, fotoDataUrl = null;
let gpsLat = null, gpsLng = null, gpsOk = false;
let currentAction = '';

function hitungJarak(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const dLat = (lat2-lat1)*Math.PI/180;
    const dLng = (lng2-lng1)*Math.PI/180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
    return R*2*Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function bukaModal(action) {
    currentAction = action;
    fotoDataUrl = null; gpsLat = gpsLng = null; gpsOk = false;
    document.getElementById('modal-absen').classList.add('active');
    document.getElementById('input-action').value = action;
    document.getElementById('modal-label').textContent = action === 'masuk' ? 'Absen Masuk' : 'Absen Pulang';
    document.getElementById('modal-icon').textContent  = action === 'masuk' ? '🟢' : '🔴';
    tampilkanVideo();
    mulaiGPS();
}

async function tampilkanVideo() {
    const video = document.getElementById('camera-video');
    const preview = document.getElementById('foto-preview');
    const btnCap = document.getElementById('btn-capture');
    const btnRet = document.getElementById('btn-retake');
    const btnCon = document.getElementById('btn-confirm');

    preview.style.display = 'none';
    video.style.display   = 'block';
    btnRet.style.display  = 'none';
    btnCon.style.display  = 'none';
    btnCap.style.display  = '';
    btnCap.disabled = true;

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width:{ideal:640}, height:{ideal:480} }, audio: false
        });
        video.srcObject = stream;
        video.onloadedmetadata = () => { video.play(); btnCap.disabled = false; };
    } catch(err) {
        alert('❌ Kamera tidak dapat diakses.\nPastikan izin kamera sudah diberikan di browser.\n\nDetail: ' + err.message);
        tutupModal();
    }
}

function mulaiGPS() {
    const el = document.getElementById('gps-status');
    const tx = document.getElementById('gps-text');
    el.className = 'gps-status loading';
    tx.textContent = 'Mengambil lokasi GPS...';
    gpsOk = false; updateTombolKonfirmasi();

    if (!navigator.geolocation) {
        el.className = 'gps-status error';
        tx.textContent = '❌ Browser tidak mendukung GPS.';
        return;
    }
    navigator.geolocation.getCurrentPosition(pos => {
        gpsLat = pos.coords.latitude;
        gpsLng = pos.coords.longitude;
        const jarak = Math.round(hitungJarak(gpsLat, gpsLng, SEKOLAH_LAT, SEKOLAH_LNG));
        if (jarak <= RADIUS_M) {
            el.className = 'gps-status ok';
            tx.textContent = '✅ Lokasi valid — ' + jarak + 'm dari sekolah';
            gpsOk = true;
        } else {
            el.className = 'gps-status error';
            tx.textContent = '❌ Di luar area sekolah — jarak ' + jarak + 'm (maks. ' + RADIUS_M + 'm). Absen tidak dapat dilanjutkan.';
            gpsOk = false;
        }
        updateTombolKonfirmasi();
    }, err => {
        el.className = 'gps-status error';
        const p = {1:'Izin lokasi ditolak. Aktifkan izin lokasi lalu coba lagi.', 2:'Posisi tidak tersedia. Pastikan GPS aktif.', 3:'Waktu habis. Coba lagi.'};
        tx.textContent = '❌ ' + (p[err.code] || 'GPS error.');
        gpsOk = false; updateTombolKonfirmasi();
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
}

function ambilFoto() {
    const video = document.getElementById('camera-video');
    const canvas = document.getElementById('camera-canvas');
    const preview = document.getElementById('foto-preview');
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');
    ctx.translate(canvas.width, 0); ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    fotoDataUrl = canvas.toDataURL('image/jpeg', 0.85);
    preview.src = fotoDataUrl;
    video.style.display = 'none';
    preview.style.display = 'block';
    document.getElementById('btn-capture').style.display = 'none';
    document.getElementById('btn-retake').style.display  = '';
    document.getElementById('btn-confirm').style.display = '';
    if (stream) stream.getTracks().forEach(t => t.stop());
    updateTombolKonfirmasi();
}

function ulangiKamera() { fotoDataUrl = null; tampilkanVideo(); }

function updateTombolKonfirmasi() {
    const btn = document.getElementById('btn-confirm');
    if (btn) btn.disabled = !(fotoDataUrl && gpsOk);
}

function konfirmasiAbsen() {
    if (!fotoDataUrl) { alert('Harap ambil foto selfie terlebih dahulu.'); return; }
    if (!gpsOk) { alert('Lokasi Anda di luar area sekolah. Absen tidak dapat dilakukan.'); return; }
    const label = currentAction === 'masuk' ? 'ABSEN MASUK' : 'ABSEN PULANG';
    if (!confirm('Apakah Anda yakin ingin melakukan ' + label + ' sekarang?')) return;
    document.getElementById('input-foto').value = fotoDataUrl;
    document.getElementById('input-lat').value  = gpsLat || '';
    document.getElementById('input-lng').value  = gpsLng || '';
    const btn = document.getElementById('btn-confirm');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    document.getElementById('form-absen').submit();
}

function tutupModal() {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    document.getElementById('modal-absen').classList.remove('active');
    fotoDataUrl = null;
}

function toggleForm(id) {
    const el = document.getElementById(id);
    const other = id === 'form-izin' ? 'form-sakit' : 'form-izin';
    document.getElementById(other).style.display = 'none';
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
    if (el.style.display === 'block') el.scrollIntoView({behavior:'smooth', block:'nearest'});
}

document.getElementById('modal-absen').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});

function previewBukti(input, previewId, areaId) {
    const preview = document.getElementById(previewId);
    const area    = document.getElementById(areaId);
    const file    = input.files[0];
    if (!file) return;

    // Validasi ukuran
    if (file.size > 5 * 1024 * 1024) {
        alert('❌ File terlalu besar. Maksimal 5MB.');
        input.value = '';
        return;
    }

    area.classList.add('has-file');
    preview.style.display = 'block';

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.innerHTML = `
                <img src="${e.target.result}" class="bukti-preview-img" alt="Preview bukti">
                <br><button type="button" class="bukti-clear" onclick="hapusBukti('${input.id}','${previewId}','${areaId}')">
                    <i class="fas fa-times-circle"></i> Hapus file
                </button>`;
        };
        reader.readAsDataURL(file);
    } else {
        // PDF
        preview.innerHTML = `
            <div class="bukti-preview-pdf">
                <span style="font-size:2rem;">📄</span>
                <div>
                    <div style="font-weight:700;">${file.name}</div>
                    <div style="color:var(--gray-500);font-size:0.8rem;">${(file.size/1024).toFixed(0)} KB</div>
                </div>
            </div>
            <br><button type="button" class="bukti-clear" onclick="hapusBukti('${input.id}','${previewId}','${areaId}')">
                <i class="fas fa-times-circle"></i> Hapus file
            </button>`;
    }
}

function hapusBukti(inputId, previewId, areaId) {
    document.getElementById(inputId).value = '';
    document.getElementById(previewId).style.display = 'none';
    document.getElementById(previewId).innerHTML = '';
    document.getElementById(areaId).classList.remove('has-file');
}

<?php if ($activeForm === 'izin'): ?>
document.addEventListener('DOMContentLoaded', () => { const f=document.getElementById('form-izin'); if(f){f.style.display='block';f.scrollIntoView({behavior:'smooth'});} });
<?php elseif ($activeForm === 'sakit'): ?>
document.addEventListener('DOMContentLoaded', () => { const f=document.getElementById('form-sakit'); if(f){f.style.display='block';f.scrollIntoView({behavior:'smooth'});} });
<?php endif; ?>
</script>
</body>
</html>

<?php
require_once '../includes/auth_guru.php';

// Lazy check alpha
cekDanInsertAlpha($conn);

$today = getTodayDate();

$result = $conn->query("SELECT * FROM absensi WHERE guru_id = $guru_id AND tanggal = '$today'");
$absensi_hari_ini = $result->num_rows > 0 ? $result->fetch_assoc() : null;
$status_hari_ini  = $absensi_hari_ini['status'] ?? null;
$sudah_masuk      = !empty($absensi_hari_ini['jam_masuk']);
$sudah_pulang     = !empty($absensi_hari_ini['jam_pulang']);
$sudah_izin       = ($status_hari_ini === 'izin');
$sudah_sakit      = ($status_hari_ini === 'sakit');
$sudah_alpha      = ($status_hari_ini === 'alpha');

$riwayat = $conn->query("SELECT * FROM absensi WHERE guru_id = $guru_id ORDER BY tanggal DESC, id DESC LIMIT 10");

$bulan_ini     = date('Y-m');
$total_hadir   = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE guru_id=$guru_id AND DATE_FORMAT(tanggal,'%Y-%m')='$bulan_ini' AND status='hadir' AND jam_masuk IS NOT NULL")->fetch_assoc()['t'];
$total_izin    = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE guru_id=$guru_id AND DATE_FORMAT(tanggal,'%Y-%m')='$bulan_ini' AND status='izin'")->fetch_assoc()['t'];
$total_sakit   = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE guru_id=$guru_id AND DATE_FORMAT(tanggal,'%Y-%m')='$bulan_ini' AND status='sakit'")->fetch_assoc()['t'];
$total_lengkap = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE guru_id=$guru_id AND DATE_FORMAT(tanggal,'%Y-%m')='$bulan_ini' AND status='hadir' AND jam_masuk IS NOT NULL AND jam_pulang IS NOT NULL")->fetch_assoc()['t'];
$total_alpha   = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE guru_id=$guru_id AND DATE_FORMAT(tanggal,'%Y-%m')='$bulan_ini' AND status='alpha'")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - Sistem Absensi</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-layout">
    <!-- ===== MOBILE HEADER ===== -->
    <div class="mobile-header">
        <button class="hamburger-btn" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <div class="mh-brand">
            <div class="mh-icon">📊</div>
            <span>Selamat Datang! 👋</span>
        </div>
        <div class="mh-actions">
            <a href="../logout.php" class="mh-logout-btn" onclick="return confirm('Yakin ingin keluar?')" title="Keluar">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
    <div class="sidebar-overlay"></div>
    <!-- ===== END MOBILE HEADER ===== -->

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
                <a href="dashboard.php" class="nav-item active"><i class="fas fa-home nav-icon"></i> Dashboard</a>
                <a href="absensi.php" class="nav-item"><i class="fas fa-fingerprint nav-icon"></i> Absensi</a>
                <a href="riwayat.php" class="nav-item"><i class="fas fa-history nav-icon"></i> Riwayat Absensi</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Akun</div>
                <a href="ganti_password.php" class="nav-item"><i class="fas fa-key nav-icon"></i> Ganti Password</a>
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
                <h1>Selamat Datang! 👋</h1>
                <p><?= htmlspecialchars($guru['nama']) ?></p>
            </div>
            <div class="topbar-date">
                <i class="fas fa-calendar-day"></i>
                <?= formatDate($today) ?>
            </div>
        </div>

        <!-- Status Kehadiran Hari Ini -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-clipboard-check"></i> Status Kehadiran Hari Ini</div>
                <?php if ($sudah_izin): ?>
                    <span class="badge badge-primary"><i class="fas fa-file-alt"></i> Izin</span>
                <?php elseif ($sudah_sakit): ?>
                    <span class="badge badge-warning"><i class="fas fa-briefcase-medical"></i> Sakit</span>
                <?php elseif ($sudah_alpha): ?>
                    <span class="badge badge-danger"><i class="fas fa-user-times"></i> Alpha</span>
                <?php elseif ($sudah_masuk && $sudah_pulang): ?>
                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Hadir Lengkap</span>
                <?php elseif ($sudah_masuk): ?>
                    <span class="badge badge-warning"><i class="fas fa-circle"></i> Sudah Masuk</span>
                <?php else: ?>
                    <span class="badge badge-gray"><i class="fas fa-minus-circle"></i> Belum Absen</span>
                <?php endif; ?>
            </div>
            <div class="card-body">

                <?php if ($sudah_izin || $sudah_sakit): ?>
                <!-- Tampilan Izin / Sakit -->
                <div style="text-align:center; padding: 24px 0;">
                    <div style="font-size:56px; margin-bottom:12px;"><?= $sudah_izin ? '📋' : '🏥' ?></div>
                    <div style="font-size:1.25rem; font-weight:700; color:var(--dark); margin-bottom:6px;">
                        Anda tercatat <span style="color:<?= $sudah_izin ? 'var(--primary)' : 'var(--warning)' ?>"><?= $sudah_izin ? 'Izin' : 'Sakit' ?></span> hari ini
                    </div>
                    <?php if (!empty($absensi_hari_ini['keterangan'])): ?>
                    <div style="color:var(--gray-600); margin-bottom:12px;">
                        Keterangan: <em>"<?= htmlspecialchars($absensi_hari_ini['keterangan']) ?>"</em>
                    </div>
                    <?php endif; ?>
                </div>

                <?php elseif ($sudah_alpha): ?>
                <!-- Tampilan Alpha -->
                <?php
                    $tgl_alpha   = new DateTime($absensi_hari_ini['tanggal']);
                    $tgl_batas   = (clone $tgl_alpha)->modify('+' . BATAS_KLARIFIKASI_HARI . ' days');
                    $tgl_sekarang = new DateTime(date('Y-m-d'));
                    $masih_bisa_klarifikasi = $tgl_sekarang <= $tgl_batas;
                    $klarifikasi_status = $absensi_hari_ini['klarifikasi_status'] ?? null;
                ?>
                <div style="text-align:center; padding:24px 0 16px;">
                    <div style="font-size:56px; margin-bottom:12px;">🚫</div>
                    <div style="font-size:1.25rem; font-weight:700; color:var(--danger); margin-bottom:6px;">
                        Anda tercatat <strong>Alpha</strong> hari ini
                    </div>
                    <div style="color:var(--gray-600); font-size:0.875rem; margin-bottom:16px;">
                        Tidak ada catatan kehadiran hingga pukul <?= date('H:i', strtotime(JAM_ALPHA)) ?> WIB
                    </div>
                    <?php if ($klarifikasi_status === 'pending'): ?>
                        <div class="alert alert-warning" style="display:inline-flex; max-width:420px; text-align:left;">
                            <i class="fas fa-clock"></i>
                            <div><strong>Pengajuan klarifikasi sedang diproses.</strong><br>
                            <span style="font-size:0.85rem;">Mohon tunggu persetujuan dari admin.</span></div>
                        </div>
                    <?php elseif ($klarifikasi_status === 'approved'): ?>
                        <div class="alert alert-success" style="display:inline-flex; max-width:420px; text-align:left;">
                            <i class="fas fa-check-circle"></i>
                            <div><strong>Klarifikasi disetujui admin.</strong><br>
                            <span style="font-size:0.85rem;">Status kehadiran Anda telah diperbarui.</span></div>
                        </div>
                    <?php elseif ($klarifikasi_status === 'rejected'): ?>
                        <div class="alert alert-danger" style="display:inline-flex; max-width:420px; text-align:left;">
                            <i class="fas fa-times-circle"></i>
                            <div><strong>Klarifikasi ditolak admin.</strong><br>
                            <?php if (!empty($absensi_hari_ini['klarifikasi_catatan_admin'])): ?>
                            <span style="font-size:0.85rem;">Catatan: <?= htmlspecialchars($absensi_hari_ini['klarifikasi_catatan_admin']) ?></span>
                            <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($masih_bisa_klarifikasi): ?>
                        <a href="absensi.php?form=klarifikasi" class="btn btn-primary">
                            <i class="fas fa-file-alt"></i> Ajukan Klarifikasi
                        </a>
                        <div style="font-size:0.8rem; color:var(--gray-500); margin-top:8px;">
                            Batas pengajuan: <?= formatDate($tgl_batas->format('Y-m-d')) ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger" style="display:inline-flex; max-width:420px; text-align:left;">
                            <i class="fas fa-lock"></i>
                            <div><strong>Batas waktu klarifikasi telah habis.</strong><br>
                            <span style="font-size:0.85rem;">Alpha ini tidak dapat lagi diklarifikasi.</span></div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php else: ?>
                <!-- Absen Masuk & Pulang -->
                <div class="absensi-actions">
                    <div class="absensi-card <?= $sudah_masuk ? 'done' : '' ?>">
                        <div class="absensi-icon"><?= $sudah_masuk ? '✅' : '🟢' ?></div>
                        <h3>Absen Masuk</h3>
                        <?php if ($sudah_masuk): ?>
                            <div class="time-display"><?= formatTime($absensi_hari_ini['jam_masuk']) ?></div>
                            <?php if ($absensi_hari_ini['keterangan_masuk']): ?>
                            <span class="badge <?= $absensi_hari_ini['keterangan_masuk']==='Terlambat' ? 'badge-danger':'badge-success' ?>">
                                <?= $absensi_hari_ini['keterangan_masuk'] ?>
                            </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Belum absen masuk hari ini</p>
                            <a href="absensi.php" class="btn btn-success btn-full">
                                <i class="fas fa-sign-in-alt"></i> Absen Masuk
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="absensi-card <?= $sudah_pulang ? 'done' : '' ?>">
                        <div class="absensi-icon"><?= $sudah_pulang ? '✅' : '🔴' ?></div>
                        <h3>Absen Pulang</h3>
                        <?php if ($sudah_pulang): ?>
                            <div class="time-display"><?= formatTime($absensi_hari_ini['jam_pulang']) ?></div>
                            <?php if ($absensi_hari_ini['keterangan_pulang']): ?>
                            <span class="badge <?= $absensi_hari_ini['keterangan_pulang']==='Lebih Awal' ? 'badge-warning':'badge-success' ?>">
                                <?= $absensi_hari_ini['keterangan_pulang'] ?>
                            </span>
                            <?php endif; ?>
                        <?php elseif ($sudah_masuk): ?>
                            <p>Silakan absen pulang saat selesai</p>
                            <a href="absensi.php" class="btn btn-danger btn-full">
                                <i class="fas fa-sign-out-alt"></i> Absen Pulang
                            </a>
                        <?php else: ?>
                            <p>Harus absen masuk terlebih dahulu</p>
                            <button class="btn btn-outline btn-full" disabled>
                                <i class="fas fa-lock"></i> Belum Bisa
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tombol Izin & Sakit — hanya muncul jika belum absen sama sekali -->
                <?php if (!$sudah_masuk): ?>
                <div style="border-top:1px solid var(--gray-200); margin-top:16px; padding-top:16px;">
                    <p style="font-size:0.875rem; color:var(--gray-600); margin-bottom:12px; text-align:center;">
                        Tidak hadir hari ini? Ajukan keterangan:
                    </p>
                    <div class="grid-2" style="gap:10px;">
                        <a href="absensi.php?form=izin" class="btn btn-outline"
                           style="border-color:var(--primary);color:var(--primary);justify-content:center;">
                            <i class="fas fa-file-alt"></i> Ajukan Izin
                        </a>
                        <a href="absensi.php?form=sakit" class="btn btn-outline"
                           style="border-color:var(--warning);color:var(--warning);justify-content:center;">
                            <i class="fas fa-briefcase-medical"></i> Lapor Sakit
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>

        <!-- Statistik Bulan Ini -->
        <div class="stats-grid">
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-value"><?= $total_hadir ?></div>
                <div class="stat-label">Hadir Bulan Ini</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                <div class="stat-value"><?= $total_lengkap ?></div>
                <div class="stat-label">Absensi Lengkap</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-value"><?= $total_izin ?></div>
                <div class="stat-label">Izin Bulan Ini</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-briefcase-medical"></i></div>
                <div class="stat-value"><?= $total_sakit ?></div>
                <div class="stat-label">Sakit Bulan Ini</div>
            </div>
            <?php if ($total_alpha > 0): ?>
            <div class="stat-card" style="background:white;border:2px solid var(--danger);">
                <div class="stat-icon" style="background:#fee2e2;color:var(--danger);"><i class="fas fa-user-times"></i></div>
                <div class="stat-value" style="color:var(--danger);"><?= $total_alpha ?></div>
                <div class="stat-label">Alpha Bulan Ini</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Riwayat Absensi Terbaru -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-history"></i> Riwayat Absensi Terbaru</div>
                <a href="riwayat.php" class="btn btn-outline btn-sm">Lihat Semua</a>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Jam Masuk</th>
                                <th>Jam Pulang</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($riwayat->num_rows > 0): while ($row = $riwayat->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= formatDate($row['tanggal']) ?></strong></td>
                                <td>
                                    <?php if ($row['status']==='izin'): ?>
                                        <span class="badge badge-primary">Izin</span>
                                    <?php elseif ($row['status']==='sakit'): ?>
                                        <span class="badge badge-warning">Sakit</span>
                                    <?php elseif ($row['status']==='alpha'): ?>
                                        <span class="badge badge-danger">Alpha</span>
                                    <?php elseif ($row['jam_masuk'] && $row['jam_pulang']): ?>
                                        <span class="badge badge-success">Hadir</span>
                                    <?php elseif ($row['jam_masuk']): ?>
                                        <span class="badge badge-warning">Masuk Saja</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['jam_masuk']): ?>
                                        <span style="color:var(--success);font-weight:600;">
                                            <i class="fas fa-sign-in-alt"></i> <?= formatTime($row['jam_masuk']) ?>
                                        </span>
                                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['jam_pulang']): ?>
                                        <span style="color:var(--danger);font-weight:600;">
                                            <i class="fas fa-sign-out-alt"></i> <?= formatTime($row['jam_pulang']) ?>
                                        </span>
                                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td style="font-size:0.8125rem;color:var(--gray-600);">
                                    <?= htmlspecialchars($row['keterangan'] ?: '-') ?>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" class="table-empty">
                                <div class="empty-icon">📅</div>
                                Belum ada riwayat absensi
                            </td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="../js/mobile.js"></script>
</body>
</html>

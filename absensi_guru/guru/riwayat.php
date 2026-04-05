<?php
require_once '../includes/auth_guru.php';

// Lazy check alpha
cekDanInsertAlpha($conn);

$filter_bulan = sanitize($conn, $_GET['bulan'] ?? date('Y-m'));

$riwayat = $conn->query("
    SELECT * FROM absensi
    WHERE guru_id = $guru_id AND DATE_FORMAT(tanggal,'%Y-%m') = '$filter_bulan'
    ORDER BY tanggal DESC
");

$total_hadir  = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE guru_id=$guru_id AND DATE_FORMAT(tanggal,'%Y-%m')='$filter_bulan' AND status='hadir' AND jam_masuk IS NOT NULL")->fetch_assoc()['t'];
$total_lengkap= $conn->query("SELECT COUNT(*) as t FROM absensi WHERE guru_id=$guru_id AND DATE_FORMAT(tanggal,'%Y-%m')='$filter_bulan' AND status='hadir' AND jam_masuk IS NOT NULL AND jam_pulang IS NOT NULL")->fetch_assoc()['t'];
$total_izin   = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE guru_id=$guru_id AND DATE_FORMAT(tanggal,'%Y-%m')='$filter_bulan' AND status='izin'")->fetch_assoc()['t'];
$total_sakit  = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE guru_id=$guru_id AND DATE_FORMAT(tanggal,'%Y-%m')='$filter_bulan' AND status='sakit'")->fetch_assoc()['t'];
$total_alpha  = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE guru_id=$guru_id AND DATE_FORMAT(tanggal,'%Y-%m')='$filter_bulan' AND status='alpha'")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Absensi - Sistem Absensi Guru</title>
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
            <div class="mh-icon">📅</div>
            <span>Riwayat Absensi</span>
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
                <a href="dashboard.php" class="nav-item"><i class="fas fa-home nav-icon"></i> Dashboard</a>
                <a href="absensi.php" class="nav-item"><i class="fas fa-fingerprint nav-icon"></i> Absensi</a>
                <a href="riwayat.php" class="nav-item active"><i class="fas fa-history nav-icon"></i> Riwayat Absensi</a>
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
                <h1>Riwayat Absensi</h1>
                <p><?= htmlspecialchars($guru['nama']) ?></p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-value"><?= $total_hadir ?></div>
                <div class="stat-label">Hadir</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                <div class="stat-value"><?= $total_lengkap ?></div>
                <div class="stat-label">Absensi Lengkap</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-value"><?= $total_izin ?></div>
                <div class="stat-label">Izin</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-briefcase-medical"></i></div>
                <div class="stat-value"><?= $total_sakit ?></div>
                <div class="stat-label">Sakit</div>
            </div>
            <?php if ($total_alpha > 0): ?>
            <div class="stat-card" style="background:white;border:2px solid var(--danger);">
                <div class="stat-icon" style="background:#fee2e2;color:var(--danger);"><i class="fas fa-user-times"></i></div>
                <div class="stat-value" style="color:var(--danger);"><?= $total_alpha ?></div>
                <div class="stat-label">Alpha</div>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-table"></i> Data Absensi</div>
                <form method="GET" style="display:flex; gap:8px; align-items:center;">
                    <input type="month" name="bulan" value="<?= htmlspecialchars($filter_bulan) ?>"
                           class="form-control" style="width:auto; padding:8px 12px;" onchange="this.form.submit()">
                </form>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Jam Masuk</th>
                                <th>Ket. Masuk</th>
                                <th>Jam Pulang</th>
                                <th>Ket. Pulang</th>
                                <th>Durasi</th>
                                <th>Keterangan / Klarifikasi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no=1; if ($riwayat->num_rows > 0): while ($row=$riwayat->fetch_assoc()):
                            $is_alpha = ($row['status'] === 'alpha');
                            $tgl_batas = (new DateTime($row['tanggal']))->modify('+' . BATAS_KLARIFIKASI_HARI . ' days');
                            $masih_bisa = (new DateTime(date('Y-m-d'))) <= $tgl_batas;
                            $klar_status = $row['klarifikasi_status'] ?? null;
                        ?>
                            <tr style="<?= $is_alpha ? 'background:#fff5f5;' : '' ?>">
                                <td><?= $no++ ?></td>
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
                                    <?php if ($row['keterangan_masuk'] ?? ''): ?>
                                        <span class="badge <?= $row['keterangan_masuk']==='Terlambat'?'badge-danger':'badge-success' ?>">
                                            <?= $row['keterangan_masuk'] ?>
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
                                <td>
                                    <?php if ($row['keterangan_pulang'] ?? ''): ?>
                                        <span class="badge <?= $row['keterangan_pulang']==='Lebih Awal'?'badge-warning':'badge-success' ?>">
                                            <?= $row['keterangan_pulang'] ?>
                                        </span>
                                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($row['jam_masuk'] && $row['jam_pulang']) {
                                        $diff = strtotime($row['jam_pulang']) - strtotime($row['jam_masuk']);
                                        echo '<strong>'.floor($diff/3600).'j '.floor(($diff%3600)/60).'m</strong>';
                                    } else echo '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td style="font-size:0.8125rem; max-width:200px;">
                                    <?php if ($is_alpha): ?>
                                        <?php if ($klar_status === 'pending'): ?>
                                            <span class="badge badge-warning" style="font-size:0.72rem;"><i class="fas fa-clock"></i> Menunggu Persetujuan</span>
                                        <?php elseif ($klar_status === 'approved'): ?>
                                            <span class="badge badge-success" style="font-size:0.72rem;"><i class="fas fa-check"></i> Klarifikasi Disetujui</span>
                                        <?php elseif ($klar_status === 'rejected'): ?>
                                            <span class="badge badge-danger" style="font-size:0.72rem;"><i class="fas fa-times"></i> Klarifikasi Ditolak</span>
                                            <?php if (!empty($row['klarifikasi_catatan_admin'])): ?>
                                            <div style="color:var(--gray-500); margin-top:3px; font-size:0.75rem;">
                                                <?= htmlspecialchars($row['klarifikasi_catatan_admin']) ?>
                                            </div>
                                            <?php endif; ?>
                                        <?php elseif ($masih_bisa): ?>
                                            <a href="absensi.php?tgl=<?= $row['tanggal'] ?>"
                                               class="btn btn-outline btn-sm"
                                               style="font-size:0.75rem; padding:4px 10px; color:var(--danger); border-color:var(--danger);">
                                                <i class="fas fa-file-alt"></i> Ajukan Klarifikasi
                                            </a>
                                            <div style="color:var(--gray-400); font-size:0.72rem; margin-top:3px;">
                                                Batas: <?= formatDate($tgl_batas->format('Y-m-d')) ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400); font-size:0.8rem;"><i class="fas fa-lock"></i> Batas waktu habis</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--gray-600);"><?= htmlspecialchars($row['keterangan'] ?: '-') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="9" class="table-empty">
                                <div class="empty-icon">📅</div>
                                <div>Tidak ada data absensi untuk periode ini</div>
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
<?php
require_once '../includes/auth_admin.php';

// Lazy check alpha
cekDanInsertAlpha($conn);

$today = getTodayDate();

$total_guru   = $conn->query("SELECT COUNT(*) as t FROM guru")->fetch_assoc()['t'];
$hadir        = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE tanggal='$today' AND status='hadir' AND jam_masuk IS NOT NULL")->fetch_assoc()['t'];
$izin         = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE tanggal='$today' AND status='izin'")->fetch_assoc()['t'];
$sakit        = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE tanggal='$today' AND status='sakit'")->fetch_assoc()['t'];
$alpha        = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE tanggal='$today' AND status='alpha'")->fetch_assoc()['t'];
$sudah_pulang = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE tanggal='$today' AND status='hadir' AND jam_pulang IS NOT NULL")->fetch_assoc()['t'];
$belum_absen  = $total_guru - $hadir - $izin - $sakit - $alpha;

// Klarifikasi pending (semua tanggal)
$pending_klarifikasi = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE klarifikasi_status='pending'")->fetch_assoc()['t'];

$semua_guru = $conn->query("
    SELECT g.nama, g.nip, a.status, a.jam_masuk, a.jam_pulang, a.keterangan_masuk, a.keterangan_pulang, a.keterangan, a.klarifikasi_status
    FROM guru g
    LEFT JOIN absensi a ON g.id = a.guru_id AND a.tanggal = '$today'
    ORDER BY g.nama ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Absensi Guru</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <div class="brand-icon">🛡️</div>
                <div class="brand-text"><h2>Admin Panel</h2><p>Sistem Absensi</p></div>
            </div>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar" style="background:linear-gradient(135deg,#f59e0b,#d97706);">AD</div>
            <div class="user-info">
                <h4><?= htmlspecialchars($admin['nama']) ?></h4>
                <p>Administrator</p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Dashboard</div>
                <a href="dashboard.php" class="nav-item active"><i class="fas fa-chart-line nav-icon"></i> Dashboard</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Manajemen</div>
                <a href="guru.php" class="nav-item"><i class="fas fa-chalkboard-teacher nav-icon"></i> Data Guru</a>
                <a href="absensi.php" class="nav-item"><i class="fas fa-clipboard-list nav-icon"></i> Data Absensi</a>
                <a href="klarifikasi.php" class="nav-item" style="position:relative;">
                    <i class="fas fa-file-circle-check nav-icon"></i> Klarifikasi Alpha
                    <?php if ($pending_klarifikasi > 0): ?>
                    <span style="background:var(--danger);color:white;border-radius:99px;padding:1px 7px;font-size:0.7rem;font-weight:700;margin-left:auto;">
                        <?= $pending_klarifikasi ?>
                    </span>
                    <?php endif; ?>
                </a>
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
                <h1>Dashboard Admin</h1>
                <p>Ringkasan kehadiran hari ini</p>
            </div>
            <div class="topbar-date">
                <i class="fas fa-calendar-day"></i> <?= formatDate($today) ?>
            </div>
        </div>

        <?php if ($pending_klarifikasi > 0): ?>
        <div class="alert alert-warning" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <i class="fas fa-bell" style="font-size:1.2rem;"></i>
                <div>
                    <strong><?= $pending_klarifikasi ?> pengajuan klarifikasi Alpha</strong> sedang menunggu persetujuan Anda.
                </div>
            </div>
            <a href="klarifikasi.php" class="btn btn-warning btn-sm" style="white-space:nowrap;">
                <i class="fas fa-arrow-right"></i> Lihat Sekarang
            </a>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?= $total_guru ?></div>
                <div class="stat-label">Total Guru</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-value"><?= $hadir ?></div>
                <div class="stat-label">Hadir</div>
            </div>
            <div class="stat-card" style="background:white; border:1px solid var(--gray-200);">
                <div class="stat-icon" style="background:var(--primary-light); color:var(--primary);"><i class="fas fa-file-alt"></i></div>
                <div class="stat-value"><?= $izin ?></div>
                <div class="stat-label">Izin</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-briefcase-medical"></i></div>
                <div class="stat-value"><?= $sakit ?></div>
                <div class="stat-label">Sakit</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                <div class="stat-value"><?= $alpha ?></div>
                <div class="stat-label">Alpha</div>
            </div>
            <?php if ($belum_absen > 0): ?>
            <div class="stat-card" style="background:white; border:1px solid var(--gray-200);">
                <div class="stat-icon" style="background:#f1f5f9; color:var(--gray-500);"><i class="fas fa-minus-circle"></i></div>
                <div class="stat-value" style="color:var(--gray-500);"><?= $belum_absen ?></div>
                <div class="stat-label">Belum Absen</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Progress -->
        <?php
        $persen_hadir = $total_guru > 0 ? round(($hadir/$total_guru)*100) : 0;
        $persen_izin  = $total_guru > 0 ? round(($izin/$total_guru)*100) : 0;
        $persen_sakit = $total_guru > 0 ? round(($sakit/$total_guru)*100) : 0;
        $persen_alpha = $total_guru > 0 ? round(($alpha/$total_guru)*100) : 0;
        ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-chart-bar"></i> Rekapitulasi Kehadiran Hari Ini</div>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.875rem; margin-bottom:5px;">
                            <span style="color:var(--success); font-weight:600;">✅ Hadir</span>
                            <span><?= $hadir ?> guru (<?= $persen_hadir ?>%)</span>
                        </div>
                        <div style="background:var(--gray-200); border-radius:99px; height:10px;">
                            <div style="background:var(--success); height:100%; width:<?= $persen_hadir ?>%; border-radius:99px;"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.875rem; margin-bottom:5px;">
                            <span style="color:var(--primary); font-weight:600;">📋 Izin</span>
                            <span><?= $izin ?> guru (<?= $persen_izin ?>%)</span>
                        </div>
                        <div style="background:var(--gray-200); border-radius:99px; height:10px;">
                            <div style="background:var(--primary); height:100%; width:<?= $persen_izin ?>%; border-radius:99px;"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.875rem; margin-bottom:5px;">
                            <span style="color:var(--warning); font-weight:600;">🏥 Sakit</span>
                            <span><?= $sakit ?> guru (<?= $persen_sakit ?>%)</span>
                        </div>
                        <div style="background:var(--gray-200); border-radius:99px; height:10px;">
                            <div style="background:var(--warning); height:100%; width:<?= $persen_sakit ?>%; border-radius:99px;"></div>
                        </div>
                    </div>
                    <?php if ($alpha > 0): ?>
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.875rem; margin-bottom:5px;">
                            <span style="color:var(--danger); font-weight:600;">🚫 Alpha</span>
                            <span><?= $alpha ?> guru (<?= $persen_alpha ?>%)</span>
                        </div>
                        <div style="background:var(--gray-200); border-radius:99px; height:10px;">
                            <div style="background:var(--danger); height:100%; width:<?= $persen_alpha ?>%; border-radius:99px;"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabel Status Semua Guru -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-list-check"></i> Status Semua Guru — <?= formatDate($today) ?></div>
                <a href="absensi.php" class="btn btn-outline btn-sm">Lihat Semua Data</a>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Guru</th>
                                <th>NIP</th>
                                <th>Status</th>
                                <th>Jam Masuk</th>
                                <th>Jam Pulang</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no=1; while ($row=$semua_guru->fetch_assoc()): ?>
                            <tr style="<?= $row['status']==='alpha' ? 'background:#fff5f5;' : '' ?>">
                                <td><?= $no++ ?></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;flex-shrink:0;">
                                            <?= strtoupper(substr($row['nama'],0,2)) ?>
                                        </div>
                                        <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                    </div>
                                </td>
                                <td><code><?= htmlspecialchars($row['nip']) ?></code></td>
                                <td>
                                    <?php if ($row['status']==='hadir' && $row['jam_masuk'] && $row['jam_pulang']): ?>
                                        <span class="badge badge-success">Hadir Lengkap</span>
                                    <?php elseif ($row['status']==='hadir' && $row['jam_masuk']): ?>
                                        <span class="badge badge-warning">Masuk Saja</span>
                                    <?php elseif ($row['status']==='izin'): ?>
                                        <span class="badge badge-primary">Izin</span>
                                    <?php elseif ($row['status']==='sakit'): ?>
                                        <span class="badge badge-warning">Sakit</span>
                                    <?php elseif ($row['status']==='alpha'): ?>
                                        <span class="badge badge-danger">Alpha</span>
                                        <?php if ($row['klarifikasi_status']==='pending'): ?>
                                        <span class="badge badge-warning" style="font-size:0.65rem; margin-left:4px;"><i class="fas fa-clock"></i> Pending</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Belum Absen</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['jam_masuk']): ?>
                                        <span style="color:var(--success);font-weight:600;">
                                            <i class="fas fa-sign-in-alt"></i> <?= formatTime($row['jam_masuk']) ?>
                                        </span>
                                        <?php if ($row['keterangan_masuk']): ?>
                                        <span class="badge <?= $row['keterangan_masuk']==='Terlambat'?'badge-danger':'badge-success' ?>" style="font-size:0.65rem;"><?= $row['keterangan_masuk'] ?></span>
                                        <?php endif; ?>
                                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['jam_pulang']): ?>
                                        <span style="color:var(--danger);font-weight:600;">
                                            <i class="fas fa-sign-out-alt"></i> <?= formatTime($row['jam_pulang']) ?>
                                        </span>
                                        <?php if ($row['keterangan_pulang']): ?>
                                        <span class="badge <?= $row['keterangan_pulang']==='Lebih Awal'?'badge-warning':'badge-success' ?>" style="font-size:0.65rem;"><?= $row['keterangan_pulang'] ?></span>
                                        <?php endif; ?>
                                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td style="font-size:0.8125rem;color:var(--gray-600);max-width:150px;">
                                    <?= htmlspecialchars($row['keterangan'] ?: '-') ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>

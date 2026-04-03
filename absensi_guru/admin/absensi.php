<?php
require_once '../includes/auth_admin.php';

// Lazy check alpha
cekDanInsertAlpha($conn);

$filter_tanggal = sanitize($conn, $_GET['tanggal'] ?? '');
$filter_guru    = (int)($_GET['guru_id'] ?? 0);
$filter_bulan   = sanitize($conn, $_GET['bulan'] ?? date('Y-m'));
$filter_status  = sanitize($conn, $_GET['status'] ?? '');

$pending_klarifikasi = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE klarifikasi_status='pending'")->fetch_assoc()['t'];

$where = [];
if ($filter_tanggal) {
    $where[] = "a.tanggal = '$filter_tanggal'";
} else {
    $where[] = "DATE_FORMAT(a.tanggal,'%Y-%m') = '$filter_bulan'";
}
if ($filter_guru)   $where[] = "a.guru_id = $filter_guru";
if ($filter_status) $where[] = "a.status = '$filter_status'";

$whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$data = $conn->query("
    SELECT a.*, g.nama, g.nip
    FROM absensi a JOIN guru g ON a.guru_id = g.id
    $whereClause
    ORDER BY a.tanggal DESC, g.nama ASC
");

$guruList = $conn->query("SELECT id, nama FROM guru ORDER BY nama ASC");

$rows = [];
$cHadir = $cIzin = $cSakit = $cLengkap = $cAlpha = 0;
while ($row = $data->fetch_assoc()) {
    $rows[] = $row;
    if ($row['status']==='hadir' && $row['jam_masuk']) $cHadir++;
    if ($row['status']==='izin')  $cIzin++;
    if ($row['status']==='sakit') $cSakit++;
    if ($row['status']==='alpha') $cAlpha++;
    if ($row['status']==='hadir' && $row['jam_masuk'] && $row['jam_pulang']) $cLengkap++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Absensi - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .selfie-thumb {
            width: 44px; height: 44px; border-radius: 8px; object-fit: cover;
            cursor: pointer; border: 2px solid var(--gray-200);
            transition: transform .15s, border-color .15s;
        }
        .selfie-thumb:hover { transform: scale(1.12); border-color: var(--primary); }
        .selfie-group { display: flex; gap: 6px; align-items: center; }
        .gps-chip {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 0.72rem; padding: 2px 7px; border-radius: 20px;
            background: #e0f2fe; color: #0369a1; font-weight: 600; white-space: nowrap;
        }
        .lightbox {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,.85);
            z-index: 9999; align-items: center; justify-content: center; flex-direction: column; gap: 14px;
        }
        .lightbox.active { display: flex; }
        .lightbox img { max-width: 90vw; max-height: 80vh; border-radius: 14px; box-shadow: 0 8px 40px rgba(0,0,0,.6); }
        .lightbox-meta { color: white; font-size: 0.9rem; text-align: center; }
        .lightbox-close { position: absolute; top: 18px; right: 22px; color: white; font-size: 1.8rem; cursor: pointer; line-height: 1; }
    </style>
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
                <a href="dashboard.php" class="nav-item"><i class="fas fa-chart-line nav-icon"></i> Dashboard</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Manajemen</div>
                <a href="guru.php" class="nav-item"><i class="fas fa-chalkboard-teacher nav-icon"></i> Data Guru</a>
                <a href="absensi.php" class="nav-item active"><i class="fas fa-clipboard-list nav-icon"></i> Data Absensi</a>
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
                <h1>Data Absensi Guru</h1>
                <p>Rekap lengkap kehadiran semua guru</p>
            </div>
        </div>

        <!-- Filter -->
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-filter"></i> Filter Data</div></div>
            <div class="card-body">
                <form method="GET" class="filter-bar" style="flex-wrap:wrap;">
                    <div class="form-group" style="margin-bottom:0; min-width:150px;">
                        <label style="font-size:0.8125rem; margin-bottom:4px;">Bulan</label>
                        <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($filter_bulan) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0; min-width:160px;">
                        <label style="font-size:0.8125rem; margin-bottom:4px;">Tanggal Spesifik</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($filter_tanggal) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0; min-width:180px;">
                        <label style="font-size:0.8125rem; margin-bottom:4px;">Guru</label>
                        <select name="guru_id" class="form-control">
                            <option value="0">Semua Guru</option>
                            <?php $guruList->data_seek(0); while ($g=$guruList->fetch_assoc()): ?>
                            <option value="<?= $g['id'] ?>" <?= $filter_guru==$g['id']?'selected':'' ?>>
                                <?= htmlspecialchars($g['nama']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0; min-width:150px;">
                        <label style="font-size:0.8125rem; margin-bottom:4px;">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="hadir"  <?= $filter_status==='hadir'?'selected':'' ?>>Hadir</option>
                            <option value="izin"   <?= $filter_status==='izin'?'selected':'' ?>>Izin</option>
                            <option value="sakit"  <?= $filter_status==='sakit'?'selected':'' ?>>Sakit</option>
                            <option value="alpha"  <?= $filter_status==='alpha'?'selected':'' ?>>Alpha</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:8px; align-self:flex-end;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
                        <a href="absensi.php" class="btn btn-outline">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Export Excel -->
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-file-excel" style="color:#16a34a;"></i> Export ke Excel</div>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">

                    <!-- Export Mingguan -->
                    <form method="GET" action="export_excel.php" target="_blank" style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;">
                        <input type="hidden" name="tipe" value="mingguan">
                        <input type="hidden" name="guru_id" value="<?= $filter_guru ?>">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:0.8rem;margin-bottom:3px;color:var(--gray-600);">Pilih Minggu</label>
                            <input type="week" name="minggu" class="form-control"
                                   value="<?= date('Y-\WW') ?>"
                                   style="padding:6px 10px;font-size:0.875rem;">
                        </div>
                        <button type="submit" class="btn" style="background:#16a34a;color:white;gap:6px;white-space:nowrap;">
                            <i class="fas fa-file-excel"></i> Export Mingguan
                        </button>
                    </form>

                    <div style="width:1px;background:var(--gray-200);height:36px;align-self:flex-end;"></div>

                    <!-- Export Bulanan -->
                    <form method="GET" action="export_excel.php" target="_blank" style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;">
                        <input type="hidden" name="tipe" value="bulanan">
                        <input type="hidden" name="guru_id" value="<?= $filter_guru ?>">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:0.8rem;margin-bottom:3px;color:var(--gray-600);">Pilih Bulan</label>
                            <input type="month" name="bulan" class="form-control"
                                   value="<?= htmlspecialchars($filter_bulan) ?>"
                                   style="padding:6px 10px;font-size:0.875rem;">
                        </div>
                        <button type="submit" class="btn" style="background:#1d4ed8;color:white;gap:6px;white-space:nowrap;">
                            <i class="fas fa-file-excel"></i> Export Bulanan
                        </button>
                    </form>

                    <div style="width:1px;background:var(--gray-200);height:36px;align-self:flex-end;"></div>

                    <!-- Export Tahunan -->
                    <form method="GET" action="export_excel.php" target="_blank" style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;">
                        <input type="hidden" name="tipe" value="tahunan">
                        <input type="hidden" name="guru_id" value="<?= $filter_guru ?>">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:0.8rem;margin-bottom:3px;color:var(--gray-600);">Pilih Tahun</label>
                            <select name="tahun" class="form-control" style="padding:6px 10px;font-size:0.875rem;">
                                <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                                <option value="<?= $y ?>" <?= $y==date('Y')?'selected':'' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn" style="background:#7c3aed;color:white;gap:6px;white-space:nowrap;">
                            <i class="fas fa-file-excel"></i> Export Tahunan
                        </button>
                    </form>

                </div>
                <p style="margin-top:10px;margin-bottom:0;font-size:0.8rem;color:var(--gray-500);">
                    <i class="fas fa-info-circle"></i>
                    Filter <strong>Guru</strong> di atas akan ikut diterapkan saat export.
                    Export akan membuka tab baru dan otomatis mengunduh file Excel (.xls).
                </p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-list"></i></div>
                <div class="stat-value"><?= count($rows) ?></div>
                <div class="stat-label">Total Record</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-value"><?= $cHadir ?></div>
                <div class="stat-label">Hadir</div>
            </div>
            <div class="stat-card" style="background:white;border:1px solid var(--gray-200);">
                <div class="stat-icon" style="background:var(--primary-light);color:var(--primary);"><i class="fas fa-file-alt"></i></div>
                <div class="stat-value"><?= $cIzin ?></div>
                <div class="stat-label">Izin</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-briefcase-medical"></i></div>
                <div class="stat-value"><?= $cSakit ?></div>
                <div class="stat-label">Sakit</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                <div class="stat-value"><?= $cAlpha ?></div>
                <div class="stat-label">Alpha</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                <div class="stat-value"><?= $cLengkap ?></div>
                <div class="stat-label">Hadir Lengkap</div>
            </div>
        </div>

        <!-- Tabel -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-table"></i> Rekap Absensi</div>
                <span class="badge badge-primary"><?= count($rows) ?> data</span>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tanggal</th>
                                <th>Nama Guru</th>
                                <th>Status</th>
                                <th>Jam Masuk</th>
                                <th>Ket. Masuk</th>
                                <th>Jam Pulang</th>
                                <th>Ket. Pulang</th>
                                <th>Durasi</th>
                                <th>Foto & GPS</th>
                                <th>Bukti Izin/Sakit</th>
                                <th>Keterangan</th>
                                <th>Klarifikasi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($rows)>0): $no=1; foreach ($rows as $row): ?>
                            <tr style="<?= $row['status']==='alpha' ? 'background:#fff5f5;' : '' ?>">
                                <td><?= $no++ ?></td>
                                <td><strong><?= formatDate($row['tanggal']) ?></strong></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div class="user-avatar" style="width:28px;height:28px;font-size:0.7rem;flex-shrink:0;">
                                            <?= strtoupper(substr($row['nama'],0,2)) ?>
                                        </div>
                                        <?= htmlspecialchars($row['nama']) ?>
                                    </div>
                                </td>
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
                                        <span class="badge badge-danger">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['jam_masuk']): ?>
                                        <span style="color:var(--success);font-weight:600;">
                                            <i class="fas fa-sign-in-alt"></i> <?= formatTime($row['jam_masuk']) ?>
                                        </span>
                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['keterangan_masuk']??''): ?>
                                        <span class="badge <?= $row['keterangan_masuk']==='Terlambat'?'badge-danger':'badge-success' ?>">
                                            <?= $row['keterangan_masuk'] ?>
                                        </span>
                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['jam_pulang']): ?>
                                        <span style="color:var(--danger);font-weight:600;">
                                            <i class="fas fa-sign-out-alt"></i> <?= formatTime($row['jam_pulang']) ?>
                                        </span>
                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['keterangan_pulang']??''): ?>
                                        <span class="badge <?= $row['keterangan_pulang']==='Lebih Awal'?'badge-warning':'badge-success' ?>">
                                            <?= $row['keterangan_pulang'] ?>
                                        </span>
                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($row['jam_masuk'] && $row['jam_pulang']) {
                                        $diff = strtotime($row['jam_pulang']) - strtotime($row['jam_masuk']);
                                        echo '<strong>'.floor($diff/3600).'j '.floor(($diff%3600)/60).'m</strong>';
                                    } else echo '<span class="text-muted">—</span>';
                                    ?>
                                </td>
                                <td>
                                    <div class="selfie-group">
                                        <?php if (!empty($row['foto_masuk'])): ?>
                                        <img class="selfie-thumb"
                                             src="../uploads/selfie/<?= htmlspecialchars($row['foto_masuk']) ?>"
                                             alt="Foto Masuk"
                                             title="Foto Masuk"
                                             onclick="bukaLightbox(this.src, '<?= htmlspecialchars($row['nama']) ?>', '<?= formatTime($row['jam_masuk']) ?>', 'Masuk')">
                                        <?php endif; ?>
                                        <?php if (!empty($row['foto_pulang'])): ?>
                                        <img class="selfie-thumb"
                                             src="../uploads/selfie/<?= htmlspecialchars($row['foto_pulang']) ?>"
                                             alt="Foto Pulang"
                                             title="Foto Pulang"
                                             onclick="bukaLightbox(this.src, '<?= htmlspecialchars($row['nama']) ?>', '<?= formatTime($row['jam_pulang']) ?>', 'Pulang')">
                                        <?php endif; ?>
                                        <?php if (empty($row['foto_masuk']) && empty($row['foto_pulang'])): ?>
                                        <span class="text-muted" style="font-size:0.8rem;">—</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($row['lat_masuk'])): ?>
                                    <div style="margin-top:5px;">
                                        <a href="https://www.google.com/maps?q=<?= $row['lat_masuk'] ?>,<?= $row['lng_masuk'] ?>" target="_blank" class="gps-chip">
                                            <i class="fas fa-map-marker-alt"></i> GPS Masuk
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['lat_pulang'])): ?>
                                    <div style="margin-top:3px;">
                                        <a href="https://www.google.com/maps?q=<?= $row['lat_pulang'] ?>,<?= $row['lng_pulang'] ?>" target="_blank" class="gps-chip">
                                            <i class="fas fa-map-marker-alt"></i> GPS Pulang
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['bukti_file'])): ?>
                                    <?php $bExt = strtolower(pathinfo($row['bukti_file'], PATHINFO_EXTENSION)); ?>
                                    <?php if (in_array($bExt, ['jpg','jpeg','png'])): ?>
                                        <img src="../uploads/bukti/<?= htmlspecialchars($row['bukti_file']) ?>"
                                             style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:2px solid var(--gray-200);cursor:pointer;"
                                             title="Klik untuk lihat bukti"
                                             onclick="bukaBuktiLightbox('../uploads/bukti/<?= htmlspecialchars($row['bukti_file']) ?>','<?= htmlspecialchars($row['nama']) ?>')">
                                    <?php else: ?>
                                        <a href="../uploads/bukti/<?= htmlspecialchars($row['bukti_file']) ?>" target="_blank"
                                           style="display:inline-flex;align-items:center;gap:5px;font-size:0.8rem;color:#dc2626;font-weight:600;text-decoration:none;">
                                            <i class="fas fa-file-pdf"></i> Buka PDF
                                        </a>
                                    <?php endif; ?>
                                    <?php else: ?><span class="text-muted" style="font-size:0.8rem;">—</span><?php endif; ?>
                                </td>
                                <td style="font-size:0.8125rem;color:var(--gray-600);max-width:150px;">
                                    <?= htmlspecialchars($row['keterangan'] ?: '—') ?>
                                </td>
                                <td style="font-size:0.8rem; min-width:140px;">
                                    <?php if ($row['status']==='alpha'): ?>
                                        <?php if ($row['klarifikasi_status']==='pending'): ?>
                                            <a href="klarifikasi.php?id=<?= $row['id'] ?>"
                                               class="badge badge-warning" style="text-decoration:none;">
                                                <i class="fas fa-clock"></i> Pending
                                            </a>
                                        <?php elseif ($row['klarifikasi_status']==='approved'): ?>
                                            <span class="badge badge-success"><i class="fas fa-check"></i> Disetujui</span>
                                        <?php elseif ($row['klarifikasi_status']==='rejected'): ?>
                                            <span class="badge badge-danger"><i class="fas fa-times"></i> Ditolak</span>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400); font-size:0.8rem;">Belum diajukan</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="13" class="table-empty">
                                <div class="empty-icon">📋</div>
                                <div>Tidak ada data untuk filter yang dipilih</div>
                            </td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="tutupLightbox()">
    <span class="lightbox-close" onclick="tutupLightbox()">✕</span>
    <img id="lightbox-img" src="" alt="">
    <div class="lightbox-meta" id="lightbox-meta"></div>
</div>

<script>
function bukaLightbox(src, nama, jam, tipe) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox-meta').textContent = nama + ' — ' + tipe + ' pukul ' + jam;
    document.getElementById('lightbox').classList.add('active');
}
function tutupLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') { tutupLightbox(); tutupBuktiLightbox(); } });

function bukaBuktiLightbox(src, nama) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox-meta').textContent = 'Bukti Izin/Sakit — ' + nama;
    document.getElementById('lightbox').classList.add('active');
}
function tutupBuktiLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}
</script>
</body>
</html>

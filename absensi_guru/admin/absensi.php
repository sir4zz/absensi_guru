<?php
require_once '../includes/auth_admin.php';

// Lazy check alpha
cekDanInsertAlpha($conn);

$filter_tanggal = sanitize($conn, $_GET['tanggal'] ?? '');
$filter_guru    = (int)($_GET['guru_id'] ?? 0);
$filter_bulan   = sanitize($conn, $_GET['bulan'] ?? date('Y-m'));
$filter_status  = sanitize($conn, $_GET['status'] ?? '');
// Untuk preview nama file export
$nmBulan  = ['','Januari','Februari','Maret','April','Mei','Juni',
             'Juli','Agustus','September','Oktober','November','Desember'];
$_bParts  = explode('-', $filter_bulan);
$tahunInt = (int)($_bParts[0] ?? date('Y'));
$bulanInt = (int)($_bParts[1] ?? date('n'));

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

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.5); z-index: 8000;
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white; border-radius: 16px;
            padding: 28px 28px 24px; width: 100%; max-width: 440px;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            animation: modalIn .18s ease;
        }
        @keyframes modalIn {
            from { transform: translateY(-18px); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }
        .modal-title {
            font-size: 1.05rem; font-weight: 700; color: var(--gray-800);
            margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
        }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

        /* ===== NOTIFIKASI ===== */
        .notif-bar {
            padding: 12px 18px; border-radius: 10px; font-weight: 600;
            font-size: 0.875rem; display: flex; align-items: center; gap: 10px;
            margin-bottom: 16px; animation: notifIn .25s ease;
        }
        @keyframes notifIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .notif-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .notif-error   { background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; }

        /* ===== TOMBOL AKSI ===== */
        .btn-aksi {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 11px; border-radius: 7px; font-size: 0.78rem;
            font-weight: 600; border: none; cursor: pointer; white-space: nowrap;
            text-decoration: none; transition: filter .15s, transform .1s;
        }
        .btn-aksi:hover { filter: brightness(.93); transform: translateY(-1px); }
        .btn-aksi:active { transform: translateY(0); }
        .btn-edit-status { background: #e0f2fe; color: #0369a1; }
        .btn-upload-bukti { background: #f0fdf4; color: #15803d; }
        .btn-hapus { background: #fff1f2; color: #be123c; }

        /* Upload preview */
        .file-preview {
            display: none; margin-top: 10px;
            font-size: 0.8rem; color: var(--gray-600);
            background: var(--gray-100); border-radius: 7px; padding: 8px 12px;
        }
    </style>
</head>
<body>
<div class="app-layout">
    <!-- ===== MOBILE HEADER ===== -->
    <div class="mobile-header">
        <button class="hamburger-btn" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <div class="mh-brand">
            <div class="mh-icon">✅</div>
            <span>Data Absensi Guru</span>
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

        <!-- Notifikasi -->
        <?php if (!empty($_SESSION['notif'])): ?>
        <?php $notif = $_SESSION['notif']; unset($_SESSION['notif']); ?>
        <div class="notif-bar notif-<?= $notif['type'] === 'success' ? 'success' : 'error' ?>" id="notifBar">
            <i class="fas fa-<?= $notif['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($notif['msg']) ?>
            <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:1rem;color:inherit;line-height:1;">✕</button>
        </div>
        <script>setTimeout(()=>{ var n=document.getElementById('notifBar'); if(n) n.remove(); }, 4000);</script>
        <?php endif; ?>

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

        <!-- Export Excel Multi-Sheet -->
        <div class="card" style="margin-bottom:0; border:2px solid #16a34a22;">
            <div class="card-header" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7); border-bottom:1px solid #bbf7d0;">
                <div class="card-title" style="color:#15803d;">
                    <i class="fas fa-file-excel" style="color:#16a34a;font-size:1.1rem;"></i>
                    Export Laporan Absensi ke Excel
                </div>
                <span style="font-size:0.78rem;color:#166534;background:#bbf7d0;padding:3px 10px;border-radius:99px;font-weight:600;">
                    5 Sheet • Format .xlsx
                </span>
            </div>
            <div class="card-body">

                <!-- Sheet preview badges -->
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
                    <?php
                    $sheets = [
                        ['🗓️','REKAP BULANAN','Kalender absensi semua guru','#1e40af','#dbeafe'],
                        ['📋','DETAIL HARIAN','Jam masuk & pulang lengkap','#065f46','#d1fae5'],
                        ['📍','LOKASI & SELFIE','GPS koordinat & foto selfie','#7c3aed','#ede9fe'],
                        ['📁','IZIN & KLARIFIKASI','Bukti izin & review admin','#92400e','#fef3c7'],
                        ['👩‍🏫','DATA GURU','Daftar guru & nomor SK/SPMT','#1f2937','#f1f5f9'],
                    ];
                    foreach ($sheets as [$icon,$nama,$desc,$fg,$bg]):
                    ?>
                    <div style="display:flex;align-items:center;gap:6px;background:<?= $bg ?>;border:1px solid <?= $fg ?>33;border-radius:8px;padding:5px 11px;">
                        <span style="font-size:1rem;"><?= $icon ?></span>
                        <div>
                            <div style="font-weight:700;font-size:0.75rem;color:<?= $fg ?>;"><?= $nama ?></div>
                            <div style="font-size:0.68rem;color:<?= $fg ?>99;"><?= $desc ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Export Form -->
                <form method="GET" action="export_excel.php" target="_blank"
                      style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;">

                    <div class="form-group" style="margin-bottom:0;min-width:160px;">
                        <label style="font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:4px;display:block;">
                            <i class="fas fa-calendar-alt" style="color:#16a34a;"></i> Pilih Bulan &amp; Tahun
                        </label>
                        <input type="month" name="bulan"
                               value="<?= htmlspecialchars($filter_bulan) ?>"
                               class="form-control"
                               style="padding:8px 12px;font-size:0.9rem;border:1.5px solid #16a34a55;border-radius:8px;min-width:170px;">
                    </div>

                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <label style="font-size:0.8rem;font-weight:600;color:#374151;">
                            <i class="fas fa-file-signature" style="color:#6b7280;"></i> Nama File
                        </label>
                        <div style="font-size:0.78rem;color:#6b7280;background:white;border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;font-family:monospace;">
                            Absensi_Guru_<em id="preview-bulan"><?= $nmBulan[$bulanInt] ?></em>_<em id="preview-tahun"><?= $tahunInt ?></em>.xlsx
                        </div>
                    </div>

                    <button type="submit" class="btn" style="background:linear-gradient(135deg,#16a34a,#15803d);color:white;padding:10px 20px;font-weight:700;gap:8px;border-radius:10px;white-space:nowrap;box-shadow:0 2px 8px #16a34a44;font-size:0.9rem;">
                        <i class="fas fa-download"></i> Download Excel (.xlsx)
                    </button>
                </form>

                <p style="margin-top:10px;margin-bottom:0;font-size:0.78rem;color:#6b7280;">
                    <i class="fas fa-info-circle" style="color:#3b82f6;"></i>
                    File Excel berisi <strong>5 sheet</strong> lengkap: rekap kalender, detail harian, lokasi GPS, data izin/klarifikasi, dan daftar guru.
                    File akan langsung terunduh ke perangkat Anda.
                </p>
            </div>
        </div>

        <script>
        (function(){
            var nmBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            var inp = document.querySelector('input[name="bulan"]');
            if (!inp) return;
            function updatePreview() {
                var v = inp.value; // YYYY-MM
                if (!v) return;
                var parts = v.split('-');
                document.getElementById('preview-bulan').textContent = nmBulan[parseInt(parts[1])] || parts[1];
                document.getElementById('preview-tahun').textContent = parts[0];
            }
            inp.addEventListener('change', updatePreview);
        })();
        </script>

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
                                <th style="text-align:center;">Aksi</th>
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
                                <!-- ===== KOLOM AKSI ===== -->
                                <td style="text-align:center; white-space:nowrap; min-width:160px;">
                                    <div style="display:flex;gap:5px;justify-content:center;flex-wrap:wrap;">
                                        <!-- Edit Status -->
                                        <button class="btn-aksi btn-edit-status"
                                            onclick="bukaModalEditStatus(
                                                <?= $row['id'] ?>,
                                                '<?= htmlspecialchars(addslashes($row['nama'])) ?>',
                                                '<?= $row['status'] ?>',
                                                '<?= htmlspecialchars(addslashes($row['keterangan'] ?? '')) ?>'
                                            )">
                                            <i class="fas fa-pen"></i> Edit Status
                                        </button>
                                        <!-- Upload Bukti -->
                                        <button class="btn-aksi btn-upload-bukti"
                                            onclick="bukaModalUploadBukti(
                                                <?= $row['id'] ?>,
                                                '<?= htmlspecialchars(addslashes($row['nama'])) ?>',
                                                '<?= $row['status'] ?>'
                                            )">
                                            <i class="fas fa-upload"></i> Bukti
                                        </button>
                                        <!-- Hapus -->
                                        <button class="btn-aksi btn-hapus"
                                            onclick="konfirmasiHapus(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama'])) ?>')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="14" class="table-empty">
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


<!-- ================================================================
     MODAL: EDIT STATUS
================================================================ -->
<div class="modal-overlay" id="modalEditStatus">
    <div class="modal-box">
        <div class="modal-title">
            <i class="fas fa-pen" style="color:var(--primary);"></i>
            Edit Status Absensi
            <span id="modalEditNama" style="color:var(--gray-500);font-weight:500;font-size:0.9rem;"></span>
        </div>
        <form method="POST" action="proses_absensi.php">
            <input type="hidden" name="aksi" value="edit_status">
            <input type="hidden" name="id" id="editStatusId">

            <div class="form-group">
                <label style="font-size:0.85rem;font-weight:600;color:var(--gray-700);margin-bottom:6px;display:block;">
                    Status Kehadiran
                </label>
                <select name="status" id="editStatusVal" class="form-control" required>
                    <option value="hadir">✅ Hadir</option>
                    <option value="izin">📄 Izin</option>
                    <option value="sakit">🩺 Sakit</option>
                    <option value="alpha">❌ Alpha</option>
                </select>
            </div>

            <div class="form-group" style="margin-top:14px;">
                <label style="font-size:0.85rem;font-weight:600;color:var(--gray-700);margin-bottom:6px;display:block;">
                    Keterangan <span style="font-weight:400;color:var(--gray-400);">(opsional)</span>
                </label>
                <textarea name="keterangan" id="editKeterangan" class="form-control"
                    rows="3" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="tutupModal('modalEditStatus')">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================
     MODAL: UPLOAD BUKTI
================================================================ -->
<div class="modal-overlay" id="modalUploadBukti">
    <div class="modal-box">
        <div class="modal-title">
            <i class="fas fa-upload" style="color:#15803d;"></i>
            Upload Bukti Izin / Sakit
            <span id="modalBuktiNama" style="color:var(--gray-500);font-weight:500;font-size:0.9rem;"></span>
        </div>

        <div id="buktiStatusInfo" style="margin-bottom:14px;padding:10px 14px;background:#fefce8;border:1px solid #fde68a;border-radius:8px;font-size:0.82rem;color:#92400e;display:none;">
            <i class="fas fa-info-circle"></i>
            Status saat ini: <strong id="buktiStatusLabel"></strong>. Upload bukti dianjurkan untuk izin dan sakit.
        </div>

        <form method="POST" action="proses_absensi.php" enctype="multipart/form-data">
            <input type="hidden" name="aksi" value="upload_bukti">
            <input type="hidden" name="id" id="buktiId">

            <div class="form-group">
                <label style="font-size:0.85rem;font-weight:600;color:var(--gray-700);margin-bottom:6px;display:block;">
                    Pilih File Bukti
                </label>
                <input type="file" name="bukti_file" id="buktiFileInput"
                    class="form-control" accept=".jpg,.jpeg,.png,.pdf"
                    onchange="previewFile(this)">
                <div class="file-preview" id="filePreview">
                    <i class="fas fa-file"></i> <span id="filePreviewName"></span>
                    <span id="filePreviewSize" style="color:var(--gray-400);margin-left:6px;"></span>
                </div>
                <p style="margin-top:8px;margin-bottom:0;font-size:0.78rem;color:var(--gray-400);">
                    Format: JPG, PNG, PDF &nbsp;|&nbsp; Maks. 2 MB
                </p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="tutupModal('modalUploadBukti')">Batal</button>
                <button type="submit" class="btn" style="background:linear-gradient(135deg,#16a34a,#15803d);color:white;">
                    <i class="fas fa-upload"></i> Upload
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Form tersembunyi untuk Hapus -->
<form method="POST" action="proses_absensi.php" id="formHapus" style="display:none;">
    <input type="hidden" name="aksi" value="hapus">
    <input type="hidden" name="id" id="hapusId">
</form>

<script>
// ── Modal helpers ──────────────────────────────────────────────
function tutupModal(id) {
    document.getElementById(id).classList.remove('active');
}
// Tutup modal klik di luar box
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) tutupModal(overlay.id);
    });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        tutupModal('modalEditStatus');
        tutupModal('modalUploadBukti');
    }
});

// ── Modal: Edit Status ─────────────────────────────────────────
function bukaModalEditStatus(id, nama, status, keterangan) {
    document.getElementById('editStatusId').value    = id;
    document.getElementById('modalEditNama').textContent = '— ' + nama;
    document.getElementById('editStatusVal').value   = status;
    document.getElementById('editKeterangan').value  = keterangan;
    document.getElementById('modalEditStatus').classList.add('active');
}

// ── Modal: Upload Bukti ────────────────────────────────────────
function bukaModalUploadBukti(id, nama, status) {
    document.getElementById('buktiId').value  = id;
    document.getElementById('modalBuktiNama').textContent = '— ' + nama;
    document.getElementById('buktiFileInput').value = '';
    document.getElementById('filePreview').style.display = 'none';

    var infoBox = document.getElementById('buktiStatusInfo');
    if (status === 'izin' || status === 'sakit') {
        infoBox.style.display = 'block';
        document.getElementById('buktiStatusLabel').textContent =
            status.charAt(0).toUpperCase() + status.slice(1);
    } else {
        infoBox.style.display = 'none';
    }
    document.getElementById('modalUploadBukti').classList.add('active');
}

// ── Preview file sebelum upload ────────────────────────────────
function previewFile(input) {
    var preview = document.getElementById('filePreview');
    if (input.files && input.files[0]) {
        var f    = input.files[0];
        var size = f.size < 1024*1024
            ? (f.size/1024).toFixed(1) + ' KB'
            : (f.size/(1024*1024)).toFixed(2) + ' MB';
        document.getElementById('filePreviewName').textContent = f.name;
        document.getElementById('filePreviewSize').textContent = size;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

// ── Konfirmasi Hapus ───────────────────────────────────────────
function konfirmasiHapus(id, nama) {
    if (confirm('Hapus data absensi milik:\n"' + nama + '"?\n\nTindakan ini tidak dapat dibatalkan.')) {
        document.getElementById('hapusId').value = id;
        document.getElementById('formHapus').submit();
    }
}
</script>

<script src="../js/mobile.js"></script>
</body>
</html>

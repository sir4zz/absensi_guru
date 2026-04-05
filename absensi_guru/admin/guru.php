<?php
// ============================================
// MANAJEMEN DATA GURU (ADMIN)
// File: admin/guru.php
// ============================================
require_once '../includes/auth_admin.php';

$message = '';
$messageType = '';

// === IZIN ABSEN MASSAL (AJAX) ===
if (isset($_GET['action']) && $_GET['action'] === 'izin_absen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $ids     = $_POST['ids'] ?? [];
    $today   = date('Y-m-d');
    $admin_id = $_SESSION['admin_id'];
    $berhasil = 0;
    $gagal    = 0;
    foreach ($ids as $gid) {
        $gid = (int)$gid;
        if ($gid <= 0) { $gagal++; continue; }
        // Hapus alpha hari ini jika ada (supaya guru bisa absen dari awal)
        $conn->query("DELETE FROM absensi WHERE guru_id=$gid AND tanggal='$today' AND status='alpha'");
        // Upsert izin
        $res = $conn->query("INSERT IGNORE INTO izin_absen (guru_id, tanggal, dibuat_oleh) VALUES ($gid, '$today', $admin_id)");
        if ($res) $berhasil++; else $gagal++;
    }
    echo json_encode(['ok' => true, 'berhasil' => $berhasil, 'gagal' => $gagal]);
    exit;
}

// === CABUT IZIN ABSEN MASSAL (AJAX) ===
if (isset($_GET['action']) && $_GET['action'] === 'cabut_izin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $ids   = $_POST['ids'] ?? [];
    $today = date('Y-m-d');
    $berhasil = 0;
    foreach ($ids as $gid) {
        $gid = (int)$gid;
        if ($gid <= 0) continue;
        $conn->query("DELETE FROM izin_absen WHERE guru_id=$gid AND tanggal='$today'");
        $berhasil++;
    }
    echo json_encode(['ok' => true, 'berhasil' => $berhasil]);
    exit;
}


if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM guru WHERE id = $id");
    $message = 'Data guru berhasil dihapus.';
    $messageType = 'success';
}

// === TAMBAH / EDIT GURU ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $nama   = sanitize($conn, $_POST['nama'] ?? '');
    $nip    = sanitize($conn, $_POST['nip'] ?? '');
    $sk     = sanitize($conn, $_POST['sk'] ?? '');
    $spmt   = sanitize($conn, $_POST['spmt'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validasi
    if (empty($nama) || empty($nip)) {
        $message = 'Nama dan NIP wajib diisi.';
        $messageType = 'danger';
    } else {
        // Cek NIP duplikat
        $cekNip = $conn->query("SELECT id FROM guru WHERE nip = '$nip'" . ($id ? " AND id != $id" : ""));
        if ($cekNip->num_rows > 0) {
            $message = 'NIP sudah digunakan oleh guru lain.';
            $messageType = 'danger';
        } elseif ($id === 0) {
            // TAMBAH BARU
            if (empty($password)) {
                $message = 'Password wajib diisi untuk guru baru.';
                $messageType = 'danger';
            } else {
                $hash = hashPassword($password);
                $conn->query("INSERT INTO guru (nama, nip, sk, spmt, password) VALUES ('$nama', '$nip', '$sk', '$spmt', '$hash')");
                $message = "Guru <strong>$nama</strong> berhasil ditambahkan.";
                $messageType = 'success';
            }
        } else {
            // EDIT
            if (!empty($password)) {
                $hash = hashPassword($password);
                $conn->query("UPDATE guru SET nama='$nama', nip='$nip', sk='$sk', spmt='$spmt', password='$hash' WHERE id=$id");
            } else {
                $conn->query("UPDATE guru SET nama='$nama', nip='$nip', sk='$sk', spmt='$spmt' WHERE id=$id");
            }
            $message = "Data guru <strong>$nama</strong> berhasil diperbarui.";
            $messageType = 'success';
        }
    }
}

// Get data untuk edit
$editGuru = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM guru WHERE id = $editId");
    if ($res->num_rows > 0) $editGuru = $res->fetch_assoc();
}

// Daftar semua guru + status izin hari ini
$today = date('Y-m-d');
$search = sanitize($conn, $_GET['cari'] ?? '');
$whereSearch = $search ? "WHERE g.nama LIKE '%$search%' OR g.nip LIKE '%$search%'" : '';
$guruList = $conn->query("
    SELECT g.*,
           (SELECT COUNT(*) FROM absensi a WHERE a.guru_id = g.id) as total_absensi,
           (SELECT COUNT(*) FROM izin_absen iz WHERE iz.guru_id = g.id AND iz.tanggal = '$today') as punya_izin,
           (SELECT a2.status FROM absensi a2 WHERE a2.guru_id = g.id AND a2.tanggal = '$today') as status_hari_ini
    FROM guru g $whereSearch ORDER BY g.nama ASC
");
$total_izin_hari_ini = $conn->query("SELECT COUNT(*) as t FROM izin_absen WHERE tanggal='$today'")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Guru - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ===== CHECKBOX GURU ===== */
        .guru-cb { width: 17px; height: 17px; cursor: pointer; accent-color: var(--primary); }
        .cb-cell { width: 40px; text-align: center; }

        /* ===== BULK ACTION BAR ===== */
        #bulk-bar {
            display: none;
            align-items: center; gap: 12px; flex-wrap: wrap;
            background: linear-gradient(135deg, #1e40af, #1d4ed8);
            color: white; padding: 12px 20px; border-radius: 14px;
            margin-bottom: 16px; box-shadow: 0 4px 20px rgba(29,78,216,.3);
            animation: slideDown 0.25s ease;
        }
        #bulk-bar.show { display: flex; }
        @keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
        #bulk-bar .bulk-count {
            background: rgba(255,255,255,.2); border-radius: 99px;
            padding: 4px 12px; font-weight: 700; font-size: 0.9rem;
        }
        #bulk-bar .bulk-spacer { flex: 1; }
        .btn-izin {
            background: #10b981; color: white; border: none;
            padding: 9px 18px; border-radius: 10px; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; gap: 8px;
            font-size: 0.9rem; transition: background .18s;
        }
        .btn-izin:hover { background: #059669; }
        .btn-cabut {
            background: rgba(255,255,255,.15); color: white; border: 1.5px solid rgba(255,255,255,.4);
            padding: 9px 18px; border-radius: 10px; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; gap: 8px;
            font-size: 0.9rem; transition: background .18s;
        }
        .btn-cabut:hover { background: rgba(255,255,255,.25); }
        .btn-deselect {
            background: rgba(255,255,255,.1); color: white; border: none;
            padding: 9px 14px; border-radius: 10px; cursor: pointer;
            font-size: 0.85rem; transition: background .18s;
        }
        .btn-deselect:hover { background: rgba(255,255,255,.2); }

        /* ===== BADGE IZIN ===== */
        .badge-izin {
            display: inline-flex; align-items: center; gap: 5px;
            background: #d1fae5; color: #065f46;
            border: 1.5px solid #6ee7b7;
            border-radius: 99px; padding: 3px 10px;
            font-size: 0.72rem; font-weight: 700;
        }
        tr.has-izin { background: #f0fdf4 !important; }

        /* ===== NOTIF TOAST ===== */
        #toast {
            position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(100px);
            background: #1e293b; color: white; padding: 14px 24px; border-radius: 14px;
            font-weight: 600; font-size: 0.92rem; z-index: 9999;
            box-shadow: 0 8px 32px rgba(0,0,0,.3); transition: transform .3s ease;
            display: flex; align-items: center; gap: 10px; white-space: nowrap;
        }
        #toast.show { transform: translateX(-50%) translateY(0); }
        #toast.success { background: #065f46; }
        #toast.error   { background: #991b1b; }

        /* ===== IZIN INFO CARD ===== */
        .izin-info-card {
            display: flex; align-items: center; gap: 12px;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 1.5px solid #6ee7b7; border-radius: 12px;
            padding: 12px 18px; margin-bottom: 16px;
        }
        .izin-info-card .icon { font-size: 1.6rem; }
        .izin-info-card strong { font-size: 1rem; color: #065f46; }
        .izin-info-card p { font-size: 0.82rem; color: #047857; margin: 0; }
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
            <div class="mh-icon">👨‍🏫</div>
            <span>Manajemen Data Guru</span>
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
            <div class="user-avatar" style="background: linear-gradient(135deg, #f59e0b, #d97706);">AD</div>
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
                <a href="guru.php" class="nav-item active"><i class="fas fa-chalkboard-teacher nav-icon"></i> Data Guru</a>
                <a href="absensi.php" class="nav-item"><i class="fas fa-clipboard-list nav-icon"></i> Data Absensi</a>
                <?php
                $pending_klar_guru = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE klarifikasi_status='pending'")->fetch_assoc()['t'];
                ?>
                <a href="klarifikasi.php" class="nav-item" style="position:relative;">
                    <i class="fas fa-file-circle-check nav-icon"></i> Klarifikasi Alpha
                    <?php if ($pending_klar_guru > 0): ?>
                    <span style="background:var(--danger);color:white;border-radius:99px;padding:1px 7px;font-size:0.7rem;font-weight:700;margin-left:auto;">
                        <?= $pending_klar_guru ?>
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
                <h1>Manajemen Data Guru</h1>
                <p>Kelola akun guru yang terdaftar</p>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="import_guru.php" class="btn btn-outline">
                    <i class="fas fa-file-import"></i> Import Excel
                </a>
                <button class="btn btn-primary" onclick="openModal('modal-tambah')">
                    <i class="fas fa-plus"></i> Tambah Guru
                </button>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'times-circle' ?>"></i>
            <?= $message ?>
        </div>
        <?php endif; ?>

        <!-- Info izin aktif hari ini -->
        <?php if ($total_izin_hari_ini > 0): ?>
        <div class="izin-info-card">
            <div class="icon">🟢</div>
            <div>
                <strong><?= $total_izin_hari_ini ?> guru memiliki izin absen khusus hari ini</strong>
                <p>Guru-guru ini diizinkan absen meskipun sudah melewati batas waktu (<?= date('H:i', strtotime(JAM_ALPHA)) ?> WIB).</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bulk action bar (muncul saat ada checkbox terpilih) -->
        <div id="bulk-bar">
            <i class="fas fa-check-square" style="font-size:1.1rem;"></i>
            <span class="bulk-count"><span id="selected-count">0</span> guru dipilih</span>
            <div class="bulk-spacer"></div>
            <button class="btn-izin" onclick="bulkIzin()">
                <i class="fas fa-unlock"></i> Izinkan Absen Hari Ini
            </button>
            <button class="btn-cabut" onclick="bulkCabut()">
                <i class="fas fa-lock"></i> Cabut Izin
            </button>
            <button class="btn-deselect" onclick="deselectAll()">
                <i class="fas fa-times"></i> Batal
            </button>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-users"></i> Daftar Guru (<?= $guruList->num_rows ?>)</div>
                <form method="GET" class="filter-bar">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="cari" class="form-control" placeholder="Cari nama atau NIP..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Cari</button>
                    <?php if ($search): ?><a href="guru.php" class="btn btn-outline btn-sm">Reset</a><?php endif; ?>
                </form>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th class="cb-cell">
                                    <input type="checkbox" class="guru-cb" id="cb-all" title="Pilih semua" onchange="toggleAll(this)">
                                </th>
                                <th>#</th>
                                <th>Nama Guru</th>
                                <th>NIP</th>
                                <th>SK</th>
                                <th>SPMT</th>
                                <th>Total Absensi</th>
                                <th>Status Izin</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; if ($guruList->num_rows > 0): while ($row = $guruList->fetch_assoc()): ?>
                            <tr class="<?= $row['punya_izin'] ? 'has-izin' : '' ?>" data-id="<?= $row['id'] ?>">
                                <td class="cb-cell">
                                    <input type="checkbox" class="guru-cb guru-check"
                                           value="<?= $row['id'] ?>"
                                           data-nama="<?= htmlspecialchars($row['nama']) ?>"
                                           onchange="updateBulkBar()">
                                </td>
                                <td><?= $no++ ?></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap: 10px;">
                                        <div class="user-avatar" style="width:32px; height:32px; font-size:0.75rem; flex-shrink:0;">
                                            <?= strtoupper(substr($row['nama'], 0, 2)) ?>
                                        </div>
                                        <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                    </div>
                                </td>
                                <td><code><?= htmlspecialchars($row['nip']) ?></code></td>
                                <td><?= htmlspecialchars($row['sk'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($row['spmt'] ?: '-') ?></td>
                                <td><span class="badge badge-primary"><?= $row['total_absensi'] ?> hari</span></td>
                                <td>
                                    <?php if ($row['punya_izin']): ?>
                                        <span class="badge-izin"><i class="fas fa-unlock" style="font-size:0.65rem;"></i> Izin Aktif</span>
                                    <?php elseif ($row['status_hari_ini'] === 'hadir'): ?>
                                        <span class="badge" style="background:#d1fae5;color:#065f46;">✅ Hadir</span>
                                    <?php elseif ($row['status_hari_ini'] === 'izin'): ?>
                                        <span class="badge" style="background:#dbeafe;color:#1e40af;">📋 Izin</span>
                                    <?php elseif ($row['status_hari_ini'] === 'sakit'): ?>
                                        <span class="badge" style="background:#fef3c7;color:#92400e;">🏥 Sakit</span>
                                    <?php elseif ($row['status_hari_ini'] === 'alpha'): ?>
                                        <span class="badge badge-danger">🚫 Alpha</span>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);font-size:0.82rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap: 6px;">
                                        <a href="guru.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm" onclick="scrollToForm()">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="guru.php?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm"
                                           onclick="return confirm('Hapus guru <?= htmlspecialchars(addslashes($row['nama'])) ?>?\nSemua data absensinya juga akan terhapus!')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="9" class="table-empty">
                                <div class="empty-icon">👥</div>
                                <div>Belum ada data guru<?= $search ? ' yang sesuai pencarian' : '' ?></div>
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Form Edit (inline, hanya muncul saat edit) -->
        <?php if ($editGuru): ?>
        <div class="card" id="form-section">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-edit"></i> Edit Data Guru</div>
                <a href="guru.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Batal</a>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $editGuru['id'] ?>">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Nama Lengkap <span style="color:red">*</span></label>
                            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($editGuru['nama']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>NIP <span style="color:red">*</span></label>
                            <input type="text" name="nip" class="form-control" value="<?= htmlspecialchars($editGuru['nip']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>SK (Surat Keputusan)</label>
                            <input type="text" name="sk" class="form-control" value="<?= htmlspecialchars($editGuru['sk']) ?>" placeholder="cth: 620 Tahun 2025">
                        </div>
                        <div class="form-group">
                            <label>SPMT</label>
                            <input type="text" name="spmt" class="form-control" value="<?= htmlspecialchars($editGuru['spmt']) ?>" placeholder="cth: 800.1.13.2/20810-Dindikbud/2025">
                        </div>
                        <div class="form-group">
                            <label>Password Baru <small style="color:var(--gray-400)">(kosongkan jika tidak diubah)</small></label>
                            <input type="password" name="password" class="form-control" placeholder="Password baru...">
                        </div>
                    </div>
                    <div style="display:flex; gap: 10px; margin-top: 8px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                        <a href="guru.php" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <script>document.getElementById('form-section').scrollIntoView({behavior:'smooth'});</script>
        <?php endif; ?>
    </main>
</div>

<!-- Modal Tambah Guru -->
<div class="modal-overlay" id="modal-tambah" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-user-plus"></i> Tambah Guru Baru</div>
            <button class="modal-close" onclick="closeModal('modal-tambah')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="id" value="0">
                <div class="form-group">
                    <label>Nama Lengkap <span style="color:red">*</span></label>
                    <input type="text" name="nama" class="form-control" placeholder="cth: Budi Santoso, S.Pd" required>
                </div>
                <div class="form-group">
                    <label>NIP <span style="color:red">*</span></label>
                    <input type="text" name="nip" class="form-control" placeholder="Nomor Induk Pegawai" required>
                </div>
                    <div class="grid-2">
                    <div class="form-group">
                        <label>SK (Surat Keputusan)</label>
                        <input type="text" name="sk" class="form-control" placeholder="cth: 620 Tahun 2025">
                    </div>
                    <div class="form-group">
                        <label>SPMT</label>
                        <input type="text" name="spmt" class="form-control" placeholder="cth: 800.1.13.2/20810-Dindikbud/2025">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password <span style="color:red">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Password login guru" required>
                </div>
                <div class="modal-footer" style="padding: 0; border: none; margin-top: 8px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modal-tambah')">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Guru</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast notifikasi -->
<div id="toast"><span id="toast-icon"></span><span id="toast-msg"></span></div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); });
});
// Auto buka modal jika ada error dari POST tambah
<?php if ($messageType === 'danger' && !$editGuru): ?>
openModal('modal-tambah');
<?php endif; ?>

// ===== CHECKBOX & BULK ACTION =====
function getChecked() {
    return [...document.querySelectorAll('.guru-check:checked')];
}

function updateBulkBar() {
    const checked = getChecked();
    const bar     = document.getElementById('bulk-bar');
    document.getElementById('selected-count').textContent = checked.length;
    if (checked.length > 0) bar.classList.add('show');
    else bar.classList.remove('show');
    // Update "pilih semua" checkbox state
    const all   = document.querySelectorAll('.guru-check');
    const cbAll = document.getElementById('cb-all');
    cbAll.indeterminate = checked.length > 0 && checked.length < all.length;
    cbAll.checked       = checked.length === all.length && all.length > 0;
}

function toggleAll(cbAll) {
    document.querySelectorAll('.guru-check').forEach(cb => cb.checked = cbAll.checked);
    updateBulkBar();
}

function deselectAll() {
    document.querySelectorAll('.guru-check, #cb-all').forEach(cb => cb.checked = false);
    document.getElementById('cb-all').indeterminate = false;
    updateBulkBar();
}

function showToast(msg, type = 'success', durationMs = 3500) {
    const t = document.getElementById('toast');
    const icons = { success: '✅', error: '❌', info: 'ℹ️' };
    document.getElementById('toast-icon').textContent = icons[type] || '';
    document.getElementById('toast-msg').textContent  = msg;
    t.className = 'show ' + type;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.className = '', durationMs);
}

async function bulkIzin() {
    const checked = getChecked();
    if (!checked.length) return;
    const names  = checked.map(c => c.dataset.nama).join(', ');
    const konfirm = confirm(
        `Izinkan ${checked.length} guru berikut untuk absen hari ini?\n\n${names}\n\nIzin hanya berlaku untuk hari ini.`
    );
    if (!konfirm) return;

    const ids = checked.map(c => c.value);
    const fd  = new FormData();
    ids.forEach(id => fd.append('ids[]', id));

    try {
        const res  = await fetch('guru.php?action=izin_absen', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            showToast(`✅ Izin diberikan ke ${data.berhasil} guru${data.gagal ? ` (${data.gagal} gagal)` : ''}.`, 'success');
            setTimeout(() => location.reload(), 1400);
        } else {
            showToast('Terjadi kesalahan.', 'error');
        }
    } catch (e) {
        showToast('Gagal menghubungi server.', 'error');
    }
}

async function bulkCabut() {
    const checked = getChecked();
    if (!checked.length) return;
    const names  = checked.map(c => c.dataset.nama).join(', ');
    const konfirm = confirm(`Cabut izin absen dari ${checked.length} guru berikut?\n\n${names}`);
    if (!konfirm) return;

    const ids = checked.map(c => c.value);
    const fd  = new FormData();
    ids.forEach(id => fd.append('ids[]', id));

    try {
        const res  = await fetch('guru.php?action=cabut_izin', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            showToast(`🔒 Izin dicabut dari ${data.berhasil} guru.`, 'info');
            setTimeout(() => location.reload(), 1400);
        } else {
            showToast('Terjadi kesalahan.', 'error');
        }
    } catch (e) {
        showToast('Gagal menghubungi server.', 'error');
    }
}
</script>

<script src="../js/mobile.js"></script>
</body>
</html>

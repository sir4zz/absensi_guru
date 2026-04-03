<?php
// ============================================
// MANAJEMEN DATA GURU (ADMIN)
// File: admin/guru.php
// ============================================
require_once '../includes/auth_admin.php';

$message = '';
$messageType = '';

// === HAPUS GURU ===
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
    $stmp   = sanitize($conn, $_POST['stmp'] ?? '');
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
                $conn->query("INSERT INTO guru (nama, nip, sk, stmp, password) VALUES ('$nama', '$nip', '$sk', '$stmp', '$hash')");
                $message = "Guru <strong>$nama</strong> berhasil ditambahkan.";
                $messageType = 'success';
            }
        } else {
            // EDIT
            if (!empty($password)) {
                $hash = hashPassword($password);
                $conn->query("UPDATE guru SET nama='$nama', nip='$nip', sk='$sk', stmp='$stmp', password='$hash' WHERE id=$id");
            } else {
                $conn->query("UPDATE guru SET nama='$nama', nip='$nip', sk='$sk', stmp='$stmp' WHERE id=$id");
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

// Daftar semua guru
$search = sanitize($conn, $_GET['cari'] ?? '');
$whereSearch = $search ? "WHERE nama LIKE '%$search%' OR nip LIKE '%$search%'" : '';
$guruList = $conn->query("SELECT g.*, (SELECT COUNT(*) FROM absensi a WHERE a.guru_id = g.id) as total_absensi FROM guru g $whereSearch ORDER BY g.nama ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Guru - Admin</title>
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
            <button class="btn btn-primary" onclick="openModal('modal-tambah')">
                <i class="fas fa-plus"></i> Tambah Guru
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'times-circle' ?>"></i>
            <?= $message ?>
        </div>
        <?php endif; ?>

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
                                <th>#</th>
                                <th>Nama Guru</th>
                                <th>NIP</th>
                                <th>SK</th>
                                <th>STMP</th>
                                <th>Total Absensi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; if ($guruList->num_rows > 0): while ($row = $guruList->fetch_assoc()): ?>
                            <tr>
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
                                <td><?= htmlspecialchars($row['stmp'] ?: '-') ?></td>
                                <td><span class="badge badge-primary"><?= $row['total_absensi'] ?> hari</span></td>
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
                            <tr><td colspan="7" class="table-empty">
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
                            <label>SK</label>
                            <input type="text" name="sk" class="form-control" value="<?= htmlspecialchars($editGuru['sk']) ?>">
                        </div>
                        <div class="form-group">
                            <label>STMP</label>
                            <input type="text" name="stmp" class="form-control" value="<?= htmlspecialchars($editGuru['stmp']) ?>">
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
                        <input type="text" name="sk" class="form-control" placeholder="cth: SK/2024/001">
                    </div>
                    <div class="form-group">
                        <label>STMP</label>
                        <input type="text" name="stmp" class="form-control" placeholder="cth: S1 Pendidikan">
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
</script>
</body>
</html>

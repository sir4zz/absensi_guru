<?php
// ============================================
// HALAMAN REVIEW KLARIFIKASI ALPHA (ADMIN)
// File: admin/klarifikasi.php
// ============================================
require_once '../includes/auth_admin.php';

$message     = '';
$messageType = '';

$pending_klarifikasi = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE klarifikasi_status='pending'")->fetch_assoc()['t'];

// ============================================================
// PROSES APPROVE / REJECT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_absensi = (int)($_POST['id'] ?? 0);
    $aksi       = sanitize($conn, $_POST['aksi'] ?? '');
    $catatan    = sanitize($conn, $_POST['catatan'] ?? '');

    if ($id_absensi && in_array($aksi, ['approve', 'reject'])) {
        // Ambil data klarifikasi
        $absensiQ = $conn->query("SELECT * FROM absensi WHERE id = $id_absensi AND status = 'alpha' AND klarifikasi_status = 'pending'");
        if ($absensiQ->num_rows > 0) {
            $absensi = $absensiQ->fetch_assoc();

            if ($aksi === 'approve') {
                // Ubah status ke izin/sakit sesuai pengajuan guru (tersimpan di kolom keterangan)
                $new_status = in_array($absensi['keterangan'], ['izin','sakit']) ? $absensi['keterangan'] : 'izin';
                $conn->query("UPDATE absensi SET
                    status              = '$new_status',
                    klarifikasi_status  = 'approved',
                    klarifikasi_catatan_admin = '$catatan'
                    WHERE id = $id_absensi");
                $message = "✅ Klarifikasi <strong>" . ucfirst(htmlspecialchars($absensi['keterangan'])) . "</strong> dari guru berhasil <strong>disetujui</strong>. Status absensi diubah menjadi " . ucfirst($new_status) . ".";
                $messageType = 'success';
                $pending_klarifikasi = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE klarifikasi_status='pending'")->fetch_assoc()['t'];

            } else {
                // Reject — status tetap alpha
                $conn->query("UPDATE absensi SET
                    klarifikasi_status        = 'rejected',
                    klarifikasi_catatan_admin = '$catatan'
                    WHERE id = $id_absensi");
                $message = "❌ Klarifikasi ditolak. Status absensi tetap <strong>Alpha</strong>.";
                $messageType = 'danger';
                $pending_klarifikasi = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE klarifikasi_status='pending'")->fetch_assoc()['t'];
            }
        } else {
            $message = 'Data tidak ditemukan atau sudah diproses sebelumnya.';
            $messageType = 'warning';
        }
    }
}

// ============================================================
// FILTER
// ============================================================
$filter_status = sanitize($conn, $_GET['status'] ?? 'pending');
$id_focus      = (int)($_GET['id'] ?? 0); // highlight dari link di absensi.php

$where_klar = match($filter_status) {
    'pending'  => "a.klarifikasi_status = 'pending'",
    'approved' => "a.klarifikasi_status = 'approved'",
    'rejected' => "a.klarifikasi_status = 'rejected'",
    default    => "a.klarifikasi_status IS NOT NULL",
};

$data = $conn->query("
    SELECT a.*, g.nama, g.nip
    FROM absensi a
    JOIN guru g ON a.guru_id = g.id
    WHERE a.klarifikasi_status IS NOT NULL AND $where_klar
    ORDER BY a.klarifikasi_at DESC
");
$rows = [];
while ($row = $data->fetch_assoc()) $rows[] = $row;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klarifikasi Alpha - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .klarifikasi-card {
            background: white;
            border: 1.5px solid var(--gray-200);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 16px;
            transition: box-shadow .2s;
        }
        .klarifikasi-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        .klarifikasi-card.highlight { border-color: var(--warning); box-shadow: 0 0 0 3px rgba(245,158,11,.15); }
        .klarifikasi-card.approved { border-color: var(--success); background: #f0fdf4; }
        .klarifikasi-card.rejected { border-color: var(--danger); background: #fff5f5; }

        .klar-header {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 12px; flex-wrap: wrap; margin-bottom: 14px;
        }
        .klar-guru { display: flex; align-items: center; gap: 12px; }
        .klar-meta { font-size: 0.8125rem; color: var(--gray-500); margin-top: 3px; }
        .klar-body { background: var(--gray-100); border-radius: 10px; padding: 14px 16px; margin-bottom: 14px; }
        .klar-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-bottom: 4px; }
        .klar-value { font-size: 0.9rem; color: var(--dark); }
        .klar-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media(max-width:600px) { .klar-grid { grid-template-columns: 1fr; } }

        .action-form { border-top: 1.5px solid var(--gray-200); padding-top: 14px; }
        .action-form textarea { resize: vertical; min-height: 70px; }
        .action-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }

        .bukti-thumb {
            width: 80px; height: 80px; object-fit: cover;
            border-radius: 8px; border: 2px solid var(--gray-200);
            cursor: pointer; transition: transform .15s;
        }
        .bukti-thumb:hover { transform: scale(1.06); }

        .tab-filter { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
        .tab-filter a {
            padding: 8px 18px; border-radius: 99px; font-size: 0.875rem; font-weight: 600;
            text-decoration: none; border: 1.5px solid var(--gray-300); color: var(--gray-600);
            transition: all .18s;
        }
        .tab-filter a.active { background: var(--primary); border-color: var(--primary); color: white; }
        .tab-filter a:hover:not(.active) { background: var(--gray-100); }

        .lightbox {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,.85);
            z-index: 9999; align-items: center; justify-content: center; flex-direction: column; gap: 12px;
        }
        .lightbox.active { display: flex; }
        .lightbox img { max-width: 90vw; max-height: 82vh; border-radius: 12px; }
        .lightbox-close { position: absolute; top: 18px; right: 22px; color: white; font-size: 1.8rem; cursor: pointer; }
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
            <div class="mh-icon">📋</div>
            <span>Klarifikasi Alpha</span>
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
                <a href="absensi.php" class="nav-item"><i class="fas fa-clipboard-list nav-icon"></i> Data Absensi</a>
                <a href="klarifikasi.php" class="nav-item active" style="position:relative;">
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
                <h1>Klarifikasi Alpha</h1>
                <p>Review pengajuan klarifikasi dari guru</p>
            </div>
            <?php if ($pending_klarifikasi > 0): ?>
            <span class="badge badge-danger" style="font-size:0.9rem; padding:8px 14px;">
                <i class="fas fa-clock"></i> <?= $pending_klarifikasi ?> Menunggu
            </span>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <i class="fas fa-<?= $messageType==='success' ? 'check-circle' : ($messageType==='danger' ? 'times-circle' : 'exclamation-triangle') ?>"></i>
            <?= $message ?>
        </div>
        <?php endif; ?>

        <!-- Tab Filter -->
        <div class="tab-filter">
            <a href="?status=pending"  class="<?= $filter_status==='pending'  ? 'active' : '' ?>">
                <i class="fas fa-clock"></i> Pending
                <?php
                $cnt = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE status='alpha' AND klarifikasi_status='pending'")->fetch_assoc()['t'];
                if ($cnt > 0) echo " ($cnt)";
                ?>
            </a>
            <a href="?status=approved" class="<?= $filter_status==='approved' ? 'active' : '' ?>">
                <i class="fas fa-check"></i> Disetujui
            </a>
            <a href="?status=rejected" class="<?= $filter_status==='rejected' ? 'active' : '' ?>">
                <i class="fas fa-times"></i> Ditolak
            </a>
            <a href="?status=all"      class="<?= $filter_status==='all'      ? 'active' : '' ?>">
                <i class="fas fa-list"></i> Semua
            </a>
        </div>

        <?php if (count($rows) === 0): ?>
        <div class="card">
            <div class="card-body" style="text-align:center; padding:60px 20px;">
                <div style="font-size:56px; margin-bottom:12px;">📭</div>
                <h3 style="color:var(--gray-500); font-weight:600;">Tidak ada pengajuan klarifikasi</h3>
                <p style="color:var(--gray-400); margin-top:4px;">
                    <?= $filter_status==='pending' ? 'Semua pengajuan sudah diproses.' : 'Belum ada data untuk kategori ini.' ?>
                </p>
            </div>
        </div>

        <?php else: ?>
        <?php foreach ($rows as $row):
            $klar_st  = $row['klarifikasi_status'];
            $is_focus = ($id_focus === (int)$row['id']);
            $card_cls = $is_focus ? 'highlight' : ($klar_st === 'approved' ? 'approved' : ($klar_st === 'rejected' ? 'rejected' : ''));
            $bukti_ext = $row['klarifikasi_bukti'] ? strtolower(pathinfo($row['klarifikasi_bukti'], PATHINFO_EXTENSION)) : '';
        ?>
        <div class="klarifikasi-card <?= $card_cls ?>" id="klar-<?= $row['id'] ?>">
            <!-- Header -->
            <div class="klar-header">
                <div class="klar-guru">
                    <div class="user-avatar" style="width:44px;height:44px;font-size:0.9rem;flex-shrink:0;">
                        <?= strtoupper(substr($row['nama'],0,2)) ?>
                    </div>
                    <div>
                        <div style="font-weight:700; font-size:1rem;"><?= htmlspecialchars($row['nama']) ?></div>
                        <div class="klar-meta">
                            NIP: <?= htmlspecialchars($row['nip']) ?>
                            &nbsp;·&nbsp;
                            Tanggal Alpha: <strong><?= formatDate($row['tanggal']) ?></strong>
                            &nbsp;·&nbsp;
                            Diajukan: <?= $row['klarifikasi_at'] ? date('d M Y H:i', strtotime($row['klarifikasi_at'])) : '-' ?>
                        </div>
                    </div>
                </div>
                <div>
                    <?php if ($klar_st === 'pending'): ?>
                        <span class="badge badge-warning"><i class="fas fa-clock"></i> Menunggu Persetujuan</span>
                    <?php elseif ($klar_st === 'approved'): ?>
                        <span class="badge badge-success"><i class="fas fa-check-circle"></i> Disetujui</span>
                    <?php elseif ($klar_st === 'rejected'): ?>
                        <span class="badge badge-danger"><i class="fas fa-times-circle"></i> Ditolak</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detail Pengajuan -->
            <div class="klar-body">
                <div class="klar-grid">
                    <div>
                        <div class="klar-label">Pengajuan Perubahan Status</div>
                        <div class="klar-value">
                            <?php if ($row['keterangan']==='izin'): ?>
                                <span class="badge badge-primary" style="font-size:0.85rem;"><i class="fas fa-file-alt"></i> Izin</span>
                            <?php else: ?>
                                <span class="badge badge-warning" style="font-size:0.85rem;"><i class="fas fa-briefcase-medical"></i> Sakit</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <div class="klar-label">Alasan / Keterangan</div>
                        <div class="klar-value"><?= htmlspecialchars($row['klarifikasi_alasan'] ?: '-') ?></div>
                    </div>
                </div>

                <?php if ($row['klarifikasi_bukti']): ?>
                <div style="margin-top:12px;">
                    <div class="klar-label">Bukti Pendukung</div>
                    <?php if (in_array($bukti_ext, ['jpg','jpeg','png'])): ?>
                        <img src="../uploads/bukti/<?= htmlspecialchars($row['klarifikasi_bukti']) ?>"
                             class="bukti-thumb" alt="Bukti"
                             onclick="bukaLightbox(this.src)">
                        <div style="font-size:0.75rem; color:var(--gray-400); margin-top:4px;">Klik untuk perbesar</div>
                    <?php else: ?>
                        <a href="../uploads/bukti/<?= htmlspecialchars($row['klarifikasi_bukti']) ?>" target="_blank"
                           style="display:inline-flex; align-items:center; gap:6px; font-size:0.875rem; color:#dc2626; font-weight:600; text-decoration:none;">
                            <i class="fas fa-file-pdf"></i> Buka File PDF
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($klar_st === 'pending'): ?>
            <!-- Form Approve / Reject -->
            <div class="action-form">
                <div style="font-size:0.875rem; font-weight:600; color:var(--gray-700); margin-bottom:10px;">
                    <i class="fas fa-gavel"></i> Keputusan Admin
                </div>
                <div class="form-group" style="margin-bottom:8px;">
                    <label style="font-size:0.8125rem;">Catatan untuk Guru <span style="color:var(--gray-400); font-weight:400;">(opsional)</span></label>
                    <textarea class="form-control" id="catatan-<?= $row['id'] ?>" rows="2"
                              placeholder="Contoh: Bukti sudah valid / Alasan tidak memadai..."></textarea>
                </div>
                <div class="action-row">
                    <button type="button" class="btn btn-success"
                            onclick="prosesKlarifikasi(<?= $row['id'] ?>, 'approve')">
                        <i class="fas fa-check"></i> Setujui Klarifikasi
                    </button>
                    <button type="button" class="btn btn-danger"
                            onclick="prosesKlarifikasi(<?= $row['id'] ?>, 'reject')">
                        <i class="fas fa-times"></i> Tolak Klarifikasi
                    </button>
                </div>
                <form id="form-klar-<?= $row['id'] ?>" method="POST" style="display:none;">
                    <input type="hidden" name="id"     value="<?= $row['id'] ?>">
                    <input type="hidden" name="aksi"   id="aksi-<?= $row['id'] ?>">
                    <input type="hidden" name="catatan" id="catatan-hidden-<?= $row['id'] ?>">
                </form>
            </div>

            <?php elseif ($klar_st !== 'pending' && !empty($row['klarifikasi_catatan_admin'])): ?>
            <!-- Tampilkan catatan admin jika sudah diproses -->
            <div style="border-top:1.5px solid var(--gray-200); padding-top:12px; font-size:0.875rem; color:var(--gray-600);">
                <i class="fas fa-comment-alt" style="margin-right:6px;"></i>
                <strong>Catatan Admin:</strong> <?= htmlspecialchars($row['klarifikasi_catatan_admin']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    </main>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="tutupLightbox()">
    <span class="lightbox-close">✕</span>
    <img id="lightbox-img" src="" alt="Bukti">
</div>

<script>
function prosesKlarifikasi(id, aksi) {
    const label = aksi === 'approve' ? 'MENYETUJUI' : 'MENOLAK';
    const catatan = document.getElementById('catatan-' + id).value;
    if (!confirm('Yakin ingin ' + label + ' pengajuan klarifikasi ini?\nTindakan ini tidak dapat dibatalkan.')) return;
    document.getElementById('aksi-' + id).value = aksi;
    document.getElementById('catatan-hidden-' + id).value = catatan;
    document.getElementById('form-klar-' + id).submit();
}

function bukaLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').classList.add('active');
}
function tutupLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') tutupLightbox(); });

// Auto-scroll ke card yang di-highlight
<?php if ($id_focus): ?>
document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('klar-<?= $id_focus ?>');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
<?php endif; ?>
</script>

<script src="../js/mobile.js"></script>
</body>
</html>

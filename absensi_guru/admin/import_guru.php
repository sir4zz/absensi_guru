<?php
// ============================================
// IMPORT DATA GURU DARI EXCEL
// File: admin/import_guru.php
// ============================================
require_once '../includes/auth_admin.php';

// ============================================================
// DOWNLOAD TEMPLATE
// ============================================================
if (isset($_GET['download_template'])) {
    $file = __DIR__ . '/template_import_guru.xlsx';
    if (file_exists($file)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="template_import_guru.xlsx"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-store, no-cache');
        readfile($file);
        exit;
    } else {
        die('File template tidak ditemukan.');
    }
}

// ============================================================
// PROSES IMPORT (POST)
// ============================================================
$results   = [];
$message   = '';
$messageType = '';
$imported  = 0;
$skipped   = 0;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_excel'])) {
    $file = $_FILES['file_excel'];

    // Validasi upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Gagal mengupload file. Kode error: ' . $file['error'];
        $messageType = 'danger';
    } elseif (!in_array(
        mime_content_type($file['tmp_name']),
        ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
         'application/vnd.ms-excel', 'application/octet-stream', 'application/zip']
    ) && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
        $message = 'Format file tidak valid. Harap upload file .xlsx';
        $messageType = 'danger';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $message = 'Ukuran file terlalu besar (maks 5MB).';
        $messageType = 'danger';
    } else {
        // Simpan file sementara
        $tmpPath = sys_get_temp_dir() . '/import_guru_' . time() . '.xlsx';
        move_uploaded_file($file['tmp_name'], $tmpPath);

        // Baca dengan PHP native (ZipArchive + SimpleXML — tanpa library tambahan)
        $rows = readXlsx($tmpPath);
        unlink($tmpPath);

        if (empty($rows) || count($rows) < 2) {
            $message = 'File kosong atau hanya berisi header. Pastikan ada data guru di bawah header.';
            $messageType = 'warning';
        } else {
            // Baris pertama = header, skip
            $headers = array_map('strtolower', array_map('trim', $rows[0]));
            $dataRows = array_slice($rows, 1);

            // Mapping kolom
            $colMap = [];
            foreach (['nama', 'nip', 'sk', 'spmt', 'password'] as $col) {
                foreach ($headers as $i => $h) {
                    // Cocokkan dengan atau tanpa tanda * dan label tambahan
                    if (strpos(strtolower($h), $col) !== false) {
                        $colMap[$col] = $i;
                        break;
                    }
                }
            }

            // Wajib: nama, nip, password
            if (!isset($colMap['nama']) || !isset($colMap['nip']) || !isset($colMap['password'])) {
                $message = 'Kolom wajib tidak ditemukan. Pastikan header file sesuai template (nama, nip, password).';
                $messageType = 'danger';
            } else {
                $defaultPassword = $_POST['default_password'] ?? '';

                foreach ($dataRows as $rowNo => $row) {
                    $lineNo = $rowNo + 2; // +2 karena baris 1 = header, mulai dari baris 2

                    $nama     = trim($row[$colMap['nama']]     ?? '');
                    $nip      = trim($row[$colMap['nip']]      ?? '');
                    $sk       = trim($row[$colMap['sk']]       ?? '');
                    $spmt     = trim($row[$colMap['spmt']]     ?? '');
                    $password = trim($row[$colMap['password']] ?? '');

                    // Gunakan default password jika kolom password kosong
                    if (empty($password) && !empty($defaultPassword)) {
                        $password = $defaultPassword;
                    }

                    // Skip baris kosong
                    if (empty($nama) && empty($nip)) continue;

                    // Validasi
                    if (empty($nama)) {
                        $errors[] = "Baris $lineNo: Nama kosong — dilewati.";
                        $skipped++;
                        continue;
                    }
                    if (empty($nip)) {
                        $errors[] = "Baris $lineNo: NIP kosong ($nama) — dilewati.";
                        $skipped++;
                        continue;
                    }
                    if (empty($password)) {
                        $errors[] = "Baris $lineNo: Password kosong ($nama) — dilewati. Isi kolom password atau set password default.";
                        $skipped++;
                        continue;
                    }

                    // Bersihkan NIP (hilangkan spasi, format angka)
                    $nip = preg_replace('/\s+/', '', $nip);
                    // Jika NIP muncul sebagai float dari Excel (misal 1.98501E+17), konversi
                    if (strpos($nip, 'E') !== false || strpos($nip, 'e') !== false) {
                        $nip = number_format((float)$nip, 0, '', '');
                    }

                    // Escape untuk query
                    $namaSafe  = $conn->real_escape_string($nama);
                    $nipSafe   = $conn->real_escape_string($nip);
                    $skSafe    = $conn->real_escape_string($sk);
                    $spmtSafe  = $conn->real_escape_string($spmt);
                    $hash      = password_hash($password, PASSWORD_DEFAULT);

                    // Cek duplikat NIP
                    $cek = $conn->query("SELECT id FROM guru WHERE nip = '$nipSafe'");
                    if ($cek->num_rows > 0) {
                        $errors[] = "Baris $lineNo: NIP <strong>$nip</strong> ($nama) sudah ada — dilewati.";
                        $skipped++;
                        continue;
                    }

                    // Insert
                    $ok = $conn->query(
                        "INSERT INTO guru (nama, nip, sk, spmt, password)
                         VALUES ('$namaSafe', '$nipSafe', '$skSafe', '$spmtSafe', '$hash')"
                    );

                    if ($ok) {
                        $results[] = ['nama' => $nama, 'nip' => $nip, 'status' => 'success'];
                        $imported++;
                    } else {
                        $errors[] = "Baris $lineNo: Gagal menyimpan $nama — " . $conn->error;
                        $skipped++;
                    }
                }

                if ($imported > 0) {
                    $message = "Import selesai: <strong>$imported guru berhasil ditambahkan</strong>" .
                               ($skipped > 0 ? ", $skipped dilewati." : ".");
                    $messageType = 'success';
                } elseif ($skipped > 0) {
                    $message = "Tidak ada guru yang ditambahkan. $skipped baris dilewati.";
                    $messageType = 'warning';
                } else {
                    $message = 'Tidak ada data yang diproses.';
                    $messageType = 'warning';
                }
            }
        }
    }
}

// ============================================================
// FUNGSI BACA XLSX NATIVE (tanpa library eksternal)
// Menggunakan ZipArchive + SimpleXML bawaan PHP
// ============================================================
function readXlsx($filePath) {
    $rows = [];

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return $rows;

    // Baca shared strings (text cells di-encode sebagai index ke sini)
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ss = simplexml_load_string($ssXml);
        if ($ss) {
            foreach ($ss->si as $si) {
                // Gabungkan semua <t> dalam satu <si> (untuk rich text)
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string)($r->t ?? '');
                }
                if (empty($text)) {
                    $text = (string)($si->t ?? '');
                }
                $sharedStrings[] = $text;
            }
        }
    }

    // Baca sheet pertama
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if (!$sheetXml) return $rows;

    $sheet = simplexml_load_string($sheetXml);
    if (!$sheet) return $rows;

    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        $lastCol = 0;

        foreach ($row->c as $cell) {
            // Tentukan kolom dari atribut r (misal "A1", "B1")
            preg_match('/^([A-Z]+)/', (string)$cell['r'], $m);
            $colLetters = $m[1] ?? 'A';
            $colIndex   = colLettersToIndex($colLetters);

            // Isi gap dengan string kosong
            while ($lastCol < $colIndex - 1) {
                $rowData[] = '';
                $lastCol++;
            }

            $type  = (string)($cell['t'] ?? '');
            $value = (string)($cell->v ?? '');

            if ($type === 's') {
                // Shared string
                $value = $sharedStrings[(int)$value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string)($cell->is->t ?? '');
            }
            // type 'n' atau kosong = angka, gunakan $value langsung

            $rowData[] = $value;
            $lastCol   = $colIndex;
        }

        // Skip baris benar-benar kosong
        if (count(array_filter($rowData, fn($v) => $v !== '')) === 0) continue;

        $rows[] = $rowData;
    }

    return $rows;
}

function colLettersToIndex($letters) {
    $letters = strtoupper($letters);
    $index   = 0;
    $len     = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index;
}

$pending_klar = $conn->query("SELECT COUNT(*) as t FROM absensi WHERE klarifikasi_status='pending'")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data Guru - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .import-zone {
            border: 2.5px dashed var(--gray-300);
            border-radius: 16px;
            padding: 48px 24px;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            background: var(--gray-50);
            position: relative;
        }
        .import-zone:hover, .import-zone.dragover {
            border-color: var(--primary);
            background: #EFF6FF;
        }
        .import-zone input[type=file] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .import-zone-icon { font-size: 3rem; margin-bottom: 12px; }
        .import-zone h3 { color: var(--gray-700); margin-bottom: 6px; }
        .import-zone p  { color: var(--gray-400); font-size: .9rem; }
        .file-chosen { margin-top: 12px; display: none; }
        .file-chosen .file-name {
            display: inline-flex; align-items: center; gap: 8px;
            background: #DBEAFE; color: #1E40AF; padding: 8px 16px;
            border-radius: 99px; font-size: .9rem; font-weight: 600;
        }

        .result-table { width: 100%; border-collapse: collapse; }
        .result-table th, .result-table td {
            padding: 10px 14px; text-align: left;
            border-bottom: 1px solid var(--gray-100); font-size: .9rem;
        }
        .result-table th { background: var(--gray-50); font-weight: 600; color: var(--gray-600); }
        .badge-success { background: #D1FAE5; color: #065F46; padding: 3px 10px; border-radius: 99px; font-size: .8rem; font-weight: 600; }
        .badge-skip    { background: #FEF3C7; color: #92400E; padding: 3px 10px; border-radius: 99px; font-size: .8rem; font-weight: 600; }

        .step-list { counter-reset: step; padding: 0; list-style: none; }
        .step-list li {
            counter-increment: step;
            display: flex; gap: 14px; align-items: flex-start;
            padding: 10px 0; border-bottom: 1px solid var(--gray-100);
        }
        .step-list li:last-child { border-bottom: none; }
        .step-list li::before {
            content: counter(step);
            min-width: 28px; height: 28px; border-radius: 50%;
            background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .85rem; flex-shrink: 0; margin-top: 1px;
        }
        .step-list li p { margin: 0; color: var(--gray-600); font-size: .9rem; }
        .step-list li strong { display: block; color: var(--gray-800); margin-bottom: 2px; }

        .error-list { background: #FFF5F5; border: 1px solid #FED7D7; border-radius: 10px; padding: 14px 18px; margin-top: 12px; }
        .error-list summary { font-weight: 600; color: #C53030; cursor: pointer; }
        .error-list ul { margin: 10px 0 0 0; padding-left: 18px; }
        .error-list li { color: var(--gray-600); font-size: .88rem; margin-bottom: 4px; }
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
                <a href="guru.php" class="nav-item"><i class="fas fa-chalkboard-teacher nav-icon"></i> Data Guru</a>
                <a href="absensi.php" class="nav-item"><i class="fas fa-clipboard-list nav-icon"></i> Data Absensi</a>
                <a href="klarifikasi.php" class="nav-item" style="position:relative;">
                    <i class="fas fa-file-circle-check nav-icon"></i> Klarifikasi Alpha
                    <?php if ($pending_klar > 0): ?>
                    <span style="background:var(--danger);color:white;border-radius:99px;padding:1px 7px;font-size:0.7rem;font-weight:700;margin-left:auto;">
                        <?= $pending_klar ?>
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
                <h1><i class="fas fa-file-import" style="color:var(--primary)"></i> Import Data Guru</h1>
                <p>Upload file Excel untuk menambahkan banyak guru sekaligus</p>
            </div>
            <a href="guru.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Kembali ke Data Guru
            </a>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'triangle-exclamation' : 'times-circle') ?>"></i>
            <?= $message ?>
        </div>
        <?php endif; ?>

        <div class="grid-2" style="gap: 20px; align-items: start;">

            <!-- Kiri: Form Upload -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-upload"></i> Upload File Excel</div>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="form-import">
                            <div class="import-zone" id="drop-zone">
                                <input type="file" name="file_excel" id="file-input" accept=".xlsx" onchange="onFileChange(this)">
                                <div class="import-zone-icon">📊</div>
                                <h3>Klik atau drag & drop file di sini</h3>
                                <p>Format: <strong>.xlsx</strong> · Maks. 5MB</p>
                                <div class="file-chosen" id="file-chosen">
                                    <span class="file-name">
                                        <i class="fas fa-file-excel" style="color:#16A34A"></i>
                                        <span id="file-name-text"></span>
                                    </span>
                                </div>
                            </div>

                            <div class="form-group" style="margin-top: 20px;">
                                <label>
                                    Password Default
                                    <small style="color:var(--gray-400)">(dipakai jika kolom password di Excel kosong)</small>
                                </label>
                                <input type="text" name="default_password" class="form-control"
                                       placeholder="cth: guru123"
                                       value="<?= htmlspecialchars($_POST['default_password'] ?? '') ?>">
                            </div>

                            <button type="submit" class="btn btn-primary btn-full btn-lg" id="btn-import" disabled>
                                <i class="fas fa-file-import"></i> Mulai Import
                            </button>
                        </form>

                        <div style="text-align:center; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-100);">
                            <p style="color:var(--gray-400); font-size:.85rem; margin-bottom: 10px;">Belum punya template?</p>
                            <a href="import_guru.php?download_template=1" class="btn btn-outline">
                                <i class="fas fa-download" style="color:#16A34A"></i> Download Template Excel
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kanan: Panduan -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-circle-info"></i> Cara Import</div>
                    </div>
                    <div class="card-body">
                        <ul class="step-list">
                            <li>
                                <div>
                                    <strong>Download Template</strong>
                                    <p>Klik tombol "Download Template Excel" untuk mendapatkan file dengan format yang benar.</p>
                                </div>
                            </li>
                            <li>
                                <div>
                                    <strong>Isi Data Guru</strong>
                                    <p>Hapus baris contoh, lalu isi data guru mulai dari baris ke-2. Kolom <code>nama</code>, <code>nip</code>, dan <code>password</code> wajib diisi.</p>
                                </div>
                            </li>
                            <li>
                                <div>
                                    <strong>Upload & Import</strong>
                                    <p>Simpan file sebagai <code>.xlsx</code>, upload di form ini, lalu klik "Mulai Import".</p>
                                </div>
                            </li>
                            <li>
                                <div>
                                    <strong>Cek Hasil</strong>
                                    <p>NIP yang sudah terdaftar akan otomatis dilewati. Data yang berhasil langsung bisa login.</p>
                                </div>
                            </li>
                        </ul>

                        <div style="background:#FEF9C3; border-radius:10px; padding: 12px 16px; margin-top: 12px; font-size:.88rem; color:#713F12;">
                            <strong>⚠️ Catatan:</strong> Pastikan tidak ada baris yang digabung (merge cells) di file Excel.
                            NIP duplikat akan dilewati tanpa error — tidak akan menimpa data yang sudah ada.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hasil Import -->
        <?php if (!empty($results) || !empty($errors)): ?>
        <div class="card" style="margin-top: 8px;">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-list-check"></i>
                    Hasil Import
                    <span style="font-weight:400; color:var(--gray-400); font-size:.9rem; margin-left:8px;">
                        <?= $imported ?> berhasil · <?= $skipped ?> dilewati
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($results)): ?>
                <div class="table-wrapper">
                    <table class="result-table">
                        <thead>
                            <tr><th>#</th><th>Nama Guru</th><th>NIP</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($r['nama']) ?></strong></td>
                                <td><code><?= htmlspecialchars($r['nip']) ?></code></td>
                                <td><span class="badge-success">✓ Berhasil</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                <details class="error-list" <?= empty($results) ? 'open' : '' ?>>
                    <summary><i class="fas fa-triangle-exclamation"></i> <?= count($errors) ?> baris dilewati — klik untuk detail</summary>
                    <ul>
                        <?php foreach ($errors as $err): ?>
                        <li><?= $err ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php endif; ?>

                <?php if ($imported > 0): ?>
                <div style="margin-top: 16px;">
                    <a href="guru.php" class="btn btn-primary">
                        <i class="fas fa-users"></i> Lihat Semua Data Guru
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<script>
function onFileChange(input) {
    const chosen = document.getElementById('file-chosen');
    const nameEl = document.getElementById('file-name-text');
    const btn    = document.getElementById('btn-import');
    if (input.files && input.files[0]) {
        nameEl.textContent = input.files[0].name;
        chosen.style.display = 'block';
        btn.disabled = false;
    } else {
        chosen.style.display = 'none';
        btn.disabled = true;
    }
}

// Drag & drop
const zone = document.getElementById('drop-zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('dragover');
    const fi = document.getElementById('file-input');
    const dt = new DataTransfer();
    dt.items.add(e.dataTransfer.files[0]);
    fi.files = dt.files;
    onFileChange(fi);
});

// Loading state saat submit
document.getElementById('form-import').addEventListener('submit', function() {
    const btn = document.getElementById('btn-import');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
});
</script>
</body>
</html>

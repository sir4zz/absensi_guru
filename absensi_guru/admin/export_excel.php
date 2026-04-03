<?php
require_once '../includes/auth_admin.php';

// ============================================================
// PARAMETER EXPORT
// ============================================================
$tipe           = sanitize($conn, $_GET['tipe']   ?? 'bulanan');
$bulan          = sanitize($conn, $_GET['bulan']  ?? date('Y-m'));
$tahun          = sanitize($conn, $_GET['tahun']  ?? date('Y'));
$minggu         = sanitize($conn, $_GET['minggu'] ?? '');
$guru_id_filter = (int)($_GET['guru_id'] ?? 0);

// ============================================================
// BANGUN WHERE & LABEL
// ============================================================
$where = ["1=1"];
if ($guru_id_filter) $where[] = "a.guru_id = $guru_id_filter";

$labelPeriode = '';
$namaFile     = '';

if ($tipe === 'mingguan') {
    if (!$minggu) $minggu = date('Y-\WW');
    $parts    = explode('-W', $minggu);
    $dt       = new DateTime();
    $dt->setISODate((int)$parts[0], (int)$parts[1]);
    $tglMulai = $dt->format('Y-m-d');
    $tglAkhir = (clone $dt)->modify('+6 days')->format('Y-m-d');
    $where[]  = "a.tanggal BETWEEN '$tglMulai' AND '$tglAkhir'";
    $labelPeriode = 'Minggu ' . $dt->format('W') . ' (' . formatDate($tglMulai) . ' s/d ' . formatDate($tglAkhir) . ')';
    $namaFile     = 'Absensi_Mingguan_' . str_replace(['-', 'W'], ['_', 'W'], $minggu);

} elseif ($tipe === 'tahunan') {
    $tahunInt     = (int)$tahun;
    $where[]      = "YEAR(a.tanggal) = $tahunInt";
    $labelPeriode = 'Tahun ' . $tahunInt;
    $namaFile     = 'Absensi_Tahunan_' . $tahunInt;

} else {
    $where[]    = "DATE_FORMAT(a.tanggal,'%Y-%m') = '$bulan'";
    $bParts     = explode('-', $bulan);
    $nmBulan    = ['','Januari','Februari','Maret','April','Mei','Juni',
                   'Juli','Agustus','September','Oktober','November','Desember'];
    $labelPeriode = $nmBulan[(int)$bParts[1]] . ' ' . $bParts[0];
    $namaFile     = 'Absensi_Bulanan_' . str_replace('-', '_', $bulan);
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// ============================================================
// AMBIL DATA
// ============================================================
$result = $conn->query("
    SELECT a.*, g.nama, g.nip
    FROM absensi a
    JOIN guru g ON a.guru_id = g.id
    $whereClause
    ORDER BY a.tanggal ASC, g.nama ASC
");

$rows = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;

// ============================================================
// NAMA GURU FILTER
// ============================================================
$namaGuruFilter = '';
if ($guru_id_filter) {
    $gRes = $conn->query("SELECT nama FROM guru WHERE id = $guru_id_filter");
    if ($gRow = $gRes->fetch_assoc()) {
        $namaGuruFilter = $gRow['nama'];
        $namaFile .= '_' . preg_replace('/[^A-Za-z0-9]/', '_', $namaGuruFilter);
    }
}

// ============================================================
// HITUNG RINGKASAN
// ============================================================
$cHadir = $cIzin = $cSakit = $cLengkap = 0;
foreach ($rows as $r) {
    if ($r['status'] === 'hadir' && $r['jam_masuk'])                     $cHadir++;
    if ($r['status'] === 'izin')                                          $cIzin++;
    if ($r['status'] === 'sakit')                                         $cSakit++;
    if ($r['status'] === 'hadir' && $r['jam_masuk'] && $r['jam_pulang']) $cLengkap++;
}

// ============================================================
// OUTPUT HEADER DOWNLOAD
// ============================================================
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '.xls"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF";

$nmBln = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$kolom = ($tipe === 'tahunan') ? 12 : 11;
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<style>
  body         { font-family: Arial, sans-serif; font-size: 10pt; }
  table        { border-collapse: collapse; width: 100%; }
  th           { background-color: #1e40af; color: #ffffff; font-weight: bold;
                 text-align: center; border: 1px solid #93c5fd; padding: 6px 8px; }
  td           { border: 1px solid #bfdbfe; padding: 5px 8px; vertical-align: middle; }
  tr:nth-child(even) td { background-color: #eff6ff; }
  .judul       { font-size: 13pt; font-weight: bold; text-align: center; }
  .subjudul    { font-size: 10pt; text-align: center; color: #374151; }
  .s-hadir     { background-color: #dcfce7 !important; color: #166534; font-weight: bold; }
  .s-izin      { background-color: #dbeafe !important; color: #1e40af; font-weight: bold; }
  .s-sakit     { background-color: #fef9c3 !important; color: #92400e; font-weight: bold; }
  .k-terlambat { background-color: #fee2e2 !important; color: #991b1b; font-weight: bold; }
  .k-tepat     { background-color: #dcfce7 !important; color: #166534; font-weight: bold; }
  .k-awal      { background-color: #fef9c3 !important; color: #92400e; font-weight: bold; }
  .sum-label   { font-weight: bold; background-color: #eff6ff; padding: 5px 10px; }
  .sum-val     { text-align: center; font-weight: bold; color: #1e40af; padding: 5px 10px; }
  .sec-title   { font-size: 11pt; font-weight: bold; color: #1e3a8a; padding: 4px 0; }
  .center      { text-align: center; }
</style>
</head>
<body>

<p class="judul">LAPORAN ABSENSI GURU<?php if ($namaGuruFilter) echo ' &mdash; ' . strtoupper(htmlspecialchars($namaGuruFilter)); ?></p>
<p class="subjudul">Periode: <?= htmlspecialchars($labelPeriode) ?></p>
<p class="subjudul">Dicetak: <?= date('d/m/Y H:i') ?> WIB</p>

<br>
<p class="sec-title">Ringkasan</p>
<table style="width:auto;">
  <tr>
    <td class="sum-label">Total Record</td><td class="sum-val"><?= count($rows) ?></td>
    <td class="sum-label">Hadir</td><td class="sum-val"><?= $cHadir ?></td>
    <td class="sum-label">Izin</td><td class="sum-val"><?= $cIzin ?></td>
    <td class="sum-label">Sakit</td><td class="sum-val"><?= $cSakit ?></td>
    <td class="sum-label">Hadir Lengkap</td><td class="sum-val"><?= $cLengkap ?></td>
  </tr>
</table>

<br>
<p class="sec-title">Detail Data Absensi</p>
<table>
  <thead>
    <tr>
      <th>No</th>
      <th>Tanggal</th>
      <?php if ($tipe === 'tahunan'): ?><th>Bulan</th><?php endif; ?>
      <th>Nama Guru</th>
      <th>NIP</th>
      <th>Status</th>
      <th>Jam Masuk</th>
      <th>Ket. Masuk</th>
      <th>Jam Pulang</th>
      <th>Ket. Pulang</th>
      <th>Durasi</th>
      <th>Keterangan</th>
    </tr>
  </thead>
  <tbody>
<?php if (empty($rows)): ?>
    <tr>
      <td colspan="<?= $kolom ?>" class="center" style="color:#6b7280;padding:14px;">
        Tidak ada data untuk periode ini.
      </td>
    </tr>
<?php else:
    $no = 1;
    foreach ($rows as $r):
        $durasi = '&mdash;';
        if ($r['jam_masuk'] && $r['jam_pulang']) {
            $diff   = strtotime($r['jam_pulang']) - strtotime($r['jam_masuk']);
            $durasi = floor($diff / 3600) . 'j ' . floor(($diff % 3600) / 60) . 'm';
        }
        $sClass  = '';
        if ($r['status'] === 'hadir') $sClass = 's-hadir';
        elseif ($r['status'] === 'izin')  $sClass = 's-izin';
        elseif ($r['status'] === 'sakit') $sClass = 's-sakit';

        $kmClass = '';
        if (($r['keterangan_masuk'] ?? '') === 'Terlambat')   $kmClass = 'k-terlambat';
        elseif (($r['keterangan_masuk'] ?? '') === 'Tepat Waktu') $kmClass = 'k-tepat';

        $kpClass = '';
        if (($r['keterangan_pulang'] ?? '') === 'Lebih Awal')  $kpClass = 'k-awal';
        elseif (($r['keterangan_pulang'] ?? '') === 'Tepat Waktu') $kpClass = 'k-tepat';
?>
    <tr>
      <td class="center"><?= $no++ ?></td>
      <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
      <?php if ($tipe === 'tahunan'): ?>
      <td class="center"><?= $nmBln[(int)date('n', strtotime($r['tanggal']))] ?></td>
      <?php endif; ?>
      <td><?= htmlspecialchars($r['nama']) ?></td>
      <td><?= htmlspecialchars($r['nip']) ?></td>
      <td class="center <?= $sClass ?>"><?= ucfirst($r['status']) ?></td>
      <td class="center"><?= $r['jam_masuk']  ? formatTime($r['jam_masuk'])  : '&mdash;' ?></td>
      <td class="center <?= $kmClass ?>"><?= htmlspecialchars($r['keterangan_masuk']  ?: '&mdash;') ?></td>
      <td class="center"><?= $r['jam_pulang'] ? formatTime($r['jam_pulang']) : '&mdash;' ?></td>
      <td class="center <?= $kpClass ?>"><?= htmlspecialchars($r['keterangan_pulang'] ?: '&mdash;') ?></td>
      <td class="center"><?= $durasi ?></td>
      <td><?= htmlspecialchars($r['keterangan'] ?: '&mdash;') ?></td>
    </tr>
<?php endforeach; endif; ?>
  </tbody>
</table>

</body>
</html>

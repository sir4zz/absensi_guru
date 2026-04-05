<?php
require_once '../includes/auth_admin.php';

// ============================================================
// PARAMETER
// ============================================================
$bulan_raw = sanitize($conn, $_GET['bulan'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $bulan_raw)) $bulan_raw = date('Y-m');

$bParts   = explode('-', $bulan_raw);
$tahunInt = (int)$bParts[0];
$bulanInt = (int)$bParts[1];
$nmBulan  = ['','Januari','Februari','Maret','April','Mei','Juni',
             'Juli','Agustus','September','Oktober','November','Desember'];
$labelBulan  = $nmBulan[$bulanInt] . ' ' . $tahunInt;
$namaFile    = 'Absensi_Guru_' . $nmBulan[$bulanInt] . '_' . $tahunInt . '.xlsx';
$namaSekolah = 'SMKN 11 KAB. TANGERANG'; // Sesuaikan nama sekolah di sini

$hariDalamBulan = cal_days_in_month(CAL_GREGORIAN, $bulanInt, $tahunInt);

// ============================================================
// AMBIL DATA
// ============================================================
$resultAbsensi = $conn->query("
    SELECT a.*, g.nama, g.nip, g.sk, g.spmt
    FROM absensi a
    JOIN guru g ON a.guru_id = g.id
    WHERE DATE_FORMAT(a.tanggal,'%Y-%m') = '$bulan_raw'
    ORDER BY a.tanggal ASC, g.nama ASC
");
$allAbsensi = [];
while ($r = $resultAbsensi->fetch_assoc()) $allAbsensi[] = $r;

$resultGuru = $conn->query("SELECT * FROM guru ORDER BY nama ASC");
$allGuru = [];
while ($g = $resultGuru->fetch_assoc()) $allGuru[] = $g;

$absensiMap = [];
foreach ($allAbsensi as $a) {
    $absensiMap[$a['guru_id']][$a['tanggal']] = $a;
}

// ============================================================
// SHARED STRINGS REGISTRY
// ============================================================
$sharedStrings = [];
$sharedIndex   = [];

function ss($val) {
    global $sharedStrings, $sharedIndex;
    $val = (string)$val;
    if (!isset($sharedIndex[$val])) {
        $sharedIndex[$val] = count($sharedStrings);
        $sharedStrings[]   = $val;
    }
    return $sharedIndex[$val];
}

// ============================================================
// CELL & ROW HELPERS
// ============================================================
function colLetter($n) {
    $s = '';
    while ($n > 0) { $n--; $s = chr(65 + ($n % 26)) . $s; $n = intdiv($n, 26); }
    return $s;
}
function ca($col, $row) { return colLetter($col) . $row; }

function xmlRow($rowNum, $cells, $height = null) {
    $h = $height ? " ht=\"$height\" customHeight=\"1\"" : '';
    return "<row r=\"$rowNum\"$h>" . implode('', $cells) . '</row>';
}
function cS($addr, $si, $s) { return "<c r=\"$addr\" t=\"s\" s=\"$s\"><v>$si</v></c>"; }
function cN($addr, $val, $s) {
    if ($val === '' || $val === null) return "<c r=\"$addr\" s=\"$s\"/>";
    return "<c r=\"$addr\" t=\"n\" s=\"$s\"><v>" . htmlspecialchars((string)$val, ENT_XML1) . "</v></c>";
}

// ============================================================
// SHEET HEADER HELPER (rows 1-4)
// ============================================================
function sheetHeader(&$rows, &$merges, $title, $namaSekolah, $labelBulan, $totalCols) {
    $r1 = [cS(ca(1,1), ss($title), 1)];
    for ($c=2;$c<=$totalCols;$c++) $r1[] = cS(ca($c,1), ss(''), 1);
    $merges[] = 'A1:' . colLetter($totalCols) . '1';
    $rows .= xmlRow(1, $r1, 24);

    $r2 = [cS(ca(1,2), ss($namaSekolah), 2)];
    for ($c=2;$c<=$totalCols;$c++) $r2[] = cS(ca($c,2), ss(''), 2);
    $merges[] = 'A2:' . colLetter($totalCols) . '2';
    $rows .= xmlRow(2, $r2, 20);

    $r3 = [cS(ca(1,3), ss("Bulan: $labelBulan"), 3)];
    for ($c=2;$c<=$totalCols;$c++) $r3[] = cS(ca($c,3), ss(''), 3);
    $merges[] = 'A3:' . colLetter($totalCols) . '3';
    $rows .= xmlRow(3, $r3, 17);

    $rows .= xmlRow(4, []);
}

function finishSheet($rows, $merges, $colDefs) {
    $mc = count($merges);
    $mXml = '';
    foreach ($merges as $m) $mXml .= "<mergeCell ref=\"$m\"/>";
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheetFormatPr defaultRowHeight="14" customHeight="1"/>
<cols>' . $colDefs . '</cols>
<sheetData>' . $rows . '</sheetData>
<mergeCells count="' . $mc . '">' . $mXml . '</mergeCells>
</worksheet>';
}

// ============================================================
// STYLE HELPERS
// ============================================================
function statusStyle($status, $ketMasuk = '') {
    if ($status === 'hadir') return ($ketMasuk === 'Terlambat') ? 9 : 7;
    if ($status === 'izin')  return 10;
    if ($status === 'sakit') return 11;
    if ($status === 'alpha') return 12;
    return 6;
}

// ============================================================
// SHEET 1 — REKAP BULANAN
// ============================================================
function buildSheet1($allGuru, $absensiMap, $hariDalamBulan, $bulanInt, $tahunInt, $labelBulan, $namaSekolah) {
    $rows = ''; $merges = [];
    $C_NO=1; $C_NAMA=2; $C_NIP=3; $C_D1=4;
    $C_H  = $C_D1 + $hariDalamBulan;
    $C_TL = $C_H+1; $C_I=$C_TL+1; $C_S=$C_I+1; $C_A=$C_S+1;
    $TC   = $C_A;

    sheetHeader($rows, $merges, 'REKAP ABSENSI BULANAN', $namaSekolah, $labelBulan, $TC);

    // Header row
    $hdrs = ['No','Nama Guru','NIP'];
    for ($d=1;$d<=$hariDalamBulan;$d++) $hdrs[] = (string)$d;
    array_push($hdrs, 'H','TL','I','S','A');
    $hC = [];
    foreach ($hdrs as $i=>$h) $hC[] = cS(ca($i+1,5), ss($h), 4);
    $rows .= xmlRow(5, $hC, 18);

    $rowNum = 6;
    foreach ($allGuru as $gi=>$guru) {
        $gid = $guru['id'];
        $cH=$cTL=$cI=$cS=$cA=0;
        $dc = [
            cN(ca($C_NO,$rowNum), $gi+1, 5),
            cS(ca($C_NAMA,$rowNum), ss($guru['nama']), 6),
            cS(ca($C_NIP,$rowNum), ss($guru['nip']), 6),
        ];
        for ($d=1;$d<=$hariDalamBulan;$d++) {
            $tgl = sprintf('%04d-%02d-%02d', $tahunInt, $bulanInt, $d);
            $a   = $absensiMap[$gid][$tgl] ?? null;
            $kode=''; $sId=5;
            if ($a) {
                if ($a['status']==='hadir') {
                    $kode = ($a['keterangan_masuk']==='Terlambat') ? 'TL' : 'H';
                    $sId  = ($kode==='TL') ? 9 : 7;
                    if ($kode==='TL') $cTL++; else $cH++;
                } elseif ($a['status']==='izin')  { $kode='I'; $sId=10; $cI++; }
                elseif ($a['status']==='sakit') { $kode='S'; $sId=11; $cS++; }
                elseif ($a['status']==='alpha') { $kode='A'; $sId=12; $cA++; }
            }
            $dc[] = cS(ca($C_D1+$d-1,$rowNum), ss($kode), $sId);
        }
        $dc[] = cN(ca($C_H,$rowNum), $cH, 13);
        $dc[] = cN(ca($C_TL,$rowNum),$cTL,15);
        $dc[] = cN(ca($C_I,$rowNum), $cI, 16);
        $dc[] = cN(ca($C_S,$rowNum), $cS, 17);
        $dc[] = cN(ca($C_A,$rowNum), $cA, 12);
        $rows .= xmlRow($rowNum, $dc, 15);
        $rowNum++;
    }

    $rowNum++;
    $rows .= xmlRow($rowNum, [cS('A'.$rowNum, ss('Keterangan Kode:'), 18)]); $rowNum++;
    foreach (['H = Hadir','TL = Terlambat','I = Izin','S = Sakit','A = Alpha'] as $k) {
        $rows .= xmlRow($rowNum, [cS('A'.$rowNum, ss($k), 19)]); $rowNum++;
    }

    $cDef = '<col min="1" max="1" width="4" customWidth="1"/>'
          . '<col min="2" max="2" width="28" customWidth="1"/>'
          . '<col min="3" max="3" width="20" customWidth="1"/>';
    for ($d=1;$d<=$hariDalamBulan;$d++)
        $cDef .= '<col min="'.($C_D1+$d-1).'" max="'.($C_D1+$d-1).'" width="3.5" customWidth="1"/>';
    $cDef .= '<col min="'.$C_H.'" max="'.$C_A.'" width="5" customWidth="1"/>';

    return finishSheet($rows, $merges, $cDef);
}

// ============================================================
// SHEET 2 — DETAIL HARIAN
// ============================================================
function buildSheet2($allAbsensi, $labelBulan, $namaSekolah) {
    $rows=''; $merges=[]; $TC=9;
    sheetHeader($rows, $merges, 'DETAIL ABSENSI HARIAN', $namaSekolah, $labelBulan, $TC);

    $hdrs = ['No','Nama Guru','NIP','Tanggal','Status Absensi','Jam Masuk','Ket. Masuk','Jam Pulang','Ket. Pulang'];
    $hC=[]; foreach ($hdrs as $i=>$h) $hC[] = cS(colLetter($i+1).'5', ss($h), 4);
    $rows .= xmlRow(5, $hC, 18);

    $no=1; $rn=6;
    foreach ($allAbsensi as $a) {
        $sId = statusStyle($a['status'], $a['keterangan_masuk']??'');
        $tgl = date('d/m/Y', strtotime($a['tanggal']));
        $jm  = $a['jam_masuk']  ? date('H:i', strtotime($a['jam_masuk']))  : '-';
        $jp  = $a['jam_pulang'] ? date('H:i', strtotime($a['jam_pulang'])) : '-';
        $km  = $a['keterangan_masuk']  ?: '-';
        $kp  = $a['keterangan_pulang'] ?: '-';
        $kmId = ($km==='Terlambat') ? 9 : (($km==='Tepat Waktu') ? 7 : 5);
        $kpId = ($kp==='Lebih Awal') ? 14 : (($kp==='Tepat Waktu') ? 7 : 5);
        $dc = [
            cN('A'.$rn,$no++,5), cS('B'.$rn,ss($a['nama']),6),
            cS('C'.$rn,ss($a['nip']),6), cS('D'.$rn,ss($tgl),5),
            cS('E'.$rn,ss(ucfirst($a['status'])),$sId),
            cS('F'.$rn,ss($jm),5), cS('G'.$rn,ss($km),$kmId),
            cS('H'.$rn,ss($jp),5), cS('I'.$rn,ss($kp),$kpId),
        ];
        $rows .= xmlRow($rn,$dc,15); $rn++;
    }
    if ($no===1) { $rows .= xmlRow(6,[cS('A6',ss('Tidak ada data.'),6)]); $merges[]='A6:'.colLetter($TC).'6'; }

    $cDef = '<col min="1" max="1" width="4" customWidth="1"/>'
          . '<col min="2" max="2" width="28" customWidth="1"/>'
          . '<col min="3" max="3" width="20" customWidth="1"/>'
          . '<col min="4" max="4" width="13" customWidth="1"/>'
          . '<col min="5" max="5" width="14" customWidth="1"/>'
          . '<col min="6" max="6" width="11" customWidth="1"/>'
          . '<col min="7" max="7" width="16" customWidth="1"/>'
          . '<col min="8" max="8" width="11" customWidth="1"/>'
          . '<col min="9" max="9" width="14" customWidth="1"/>';
    return finishSheet($rows, $merges, $cDef);
}

// ============================================================
// SHEET 3 — LOKASI & SELFIE
// ============================================================
function buildSheet3($allAbsensi, $labelBulan, $namaSekolah) {
    $rows=''; $merges=[]; $TC=9;
    sheetHeader($rows, $merges, 'DATA LOKASI DAN SELFIE', $namaSekolah, $labelBulan, $TC);

    $hdrs = ['No','Nama Guru','Tanggal','Foto Masuk','Lat. Masuk','Lng. Masuk','Foto Pulang','Lat. Pulang','Lng. Pulang'];
    $hC=[]; foreach ($hdrs as $i=>$h) $hC[] = cS(colLetter($i+1).'5', ss($h), 4);
    $rows .= xmlRow(5, $hC, 18);

    $no=1; $rn=6;
    foreach ($allAbsensi as $a) {
        if (!$a['lat_masuk'] && !$a['lat_pulang'] && !$a['foto_masuk'] && !$a['foto_pulang']) continue;
        $tgl = date('d/m/Y', strtotime($a['tanggal']));
        $dc = [
            cN('A'.$rn,$no++,5), cS('B'.$rn,ss($a['nama']),6),
            cS('C'.$rn,ss($tgl),5),
            cS('D'.$rn,ss($a['foto_masuk']  ?: '-'),6),
            cS('E'.$rn,ss($a['lat_masuk']   ? number_format((float)$a['lat_masuk'],7)  : '-'),5),
            cS('F'.$rn,ss($a['lng_masuk']   ? number_format((float)$a['lng_masuk'],7)  : '-'),5),
            cS('G'.$rn,ss($a['foto_pulang'] ?: '-'),6),
            cS('H'.$rn,ss($a['lat_pulang']  ? number_format((float)$a['lat_pulang'],7) : '-'),5),
            cS('I'.$rn,ss($a['lng_pulang']  ? number_format((float)$a['lng_pulang'],7) : '-'),5),
        ];
        $rows .= xmlRow($rn,$dc,15); $rn++;
    }
    if ($no===1) { $rows .= xmlRow(6,[cS('A6',ss('Tidak ada data selfie/GPS untuk periode ini.'),6)]); $merges[]='A6:'.colLetter($TC).'6'; }

    $cDef = '<col min="1" max="1" width="4" customWidth="1"/>'
          . '<col min="2" max="2" width="28" customWidth="1"/>'
          . '<col min="3" max="3" width="13" customWidth="1"/>'
          . '<col min="4" max="4" width="35" customWidth="1"/>'
          . '<col min="5" max="5" width="15" customWidth="1"/>'
          . '<col min="6" max="6" width="15" customWidth="1"/>'
          . '<col min="7" max="7" width="35" customWidth="1"/>'
          . '<col min="8" max="8" width="15" customWidth="1"/>'
          . '<col min="9" max="9" width="15" customWidth="1"/>';
    return finishSheet($rows, $merges, $cDef);
}

// ============================================================
// SHEET 4 — IZIN / SAKIT / KLARIFIKASI
// ============================================================
function buildSheet4($allAbsensi, $labelBulan, $namaSekolah) {
    $rows=''; $merges=[]; $TC=8;
    sheetHeader($rows, $merges, 'DATA IZIN / SAKIT / KLARIFIKASI', $namaSekolah, $labelBulan, $TC);

    $hdrs = ['No','Nama Guru','Tanggal','Status','Alasan','Bukti File','Status Klarifikasi','Catatan Admin'];
    $hC=[]; foreach ($hdrs as $i=>$h) $hC[] = cS(colLetter($i+1).'5', ss($h), 4);
    $rows .= xmlRow(5, $hC, 18);

    $no=1; $rn=6;
    foreach ($allAbsensi as $a) {
        $hasKl = !empty($a['klarifikasi_alasan']) || !empty($a['klarifikasi_status']);
        if (!in_array($a['status'],['izin','sakit','alpha']) && !$hasKl) continue;
        $tgl   = date('d/m/Y', strtotime($a['tanggal']));
        $sId   = statusStyle($a['status']);
        $klSt  = $a['klarifikasi_status'] ?: '';
        $klId  = ($klSt==='approved') ? 7 : (($klSt==='rejected') ? 12 : (($klSt==='pending') ? 14 : 5));
        $dc = [
            cN('A'.$rn,$no++,5), cS('B'.$rn,ss($a['nama']),6),
            cS('C'.$rn,ss($tgl),5),
            cS('D'.$rn,ss(ucfirst($a['status'])),$sId),
            cS('E'.$rn,ss($a['klarifikasi_alasan'] ?: ($a['keterangan'] ?: '-')),6),
            cS('F'.$rn,ss($a['klarifikasi_bukti']  ?: ($a['bukti_file'] ?: '-')),6),
            cS('G'.$rn,ss($klSt ? ucfirst($klSt) : '-'),$klId),
            cS('H'.$rn,ss($a['klarifikasi_catatan_admin'] ?: '-'),6),
        ];
        $rows .= xmlRow($rn,$dc,15); $rn++;
    }
    if ($no===1) { $rows .= xmlRow(6,[cS('A6',ss('Tidak ada data izin/sakit/klarifikasi.'),6)]); $merges[]='A6:'.colLetter($TC).'6'; }

    $cDef = '<col min="1" max="1" width="4" customWidth="1"/>'
          . '<col min="2" max="2" width="28" customWidth="1"/>'
          . '<col min="3" max="3" width="13" customWidth="1"/>'
          . '<col min="4" max="4" width="10" customWidth="1"/>'
          . '<col min="5" max="5" width="38" customWidth="1"/>'
          . '<col min="6" max="6" width="32" customWidth="1"/>'
          . '<col min="7" max="7" width="18" customWidth="1"/>'
          . '<col min="8" max="8" width="38" customWidth="1"/>';
    return finishSheet($rows, $merges, $cDef);
}

// ============================================================
// SHEET 5 — DATA GURU
// ============================================================
function buildSheet5($allGuru, $labelBulan, $namaSekolah) {
    $rows=''; $merges=[]; $TC=5;
    sheetHeader($rows, $merges, 'DATA GURU', $namaSekolah, $labelBulan, $TC);

    $hdrs = ['No','Nama Guru','NIP','SK','SPMT'];
    $hC=[]; foreach ($hdrs as $i=>$h) $hC[] = cS(colLetter($i+1).'5', ss($h), 4);
    $rows .= xmlRow(5, $hC, 18);

    $rn=6;
    foreach ($allGuru as $gi=>$g) {
        $dc = [
            cN('A'.$rn,$gi+1,5), cS('B'.$rn,ss($g['nama']),6),
            cS('C'.$rn,ss($g['nip']),6),
            cS('D'.$rn,ss($g['sk']   ?: '-'),6),
            cS('E'.$rn,ss($g['spmt'] ?: '-'),6),
        ];
        $rows .= xmlRow($rn,$dc,15); $rn++;
    }

    $cDef = '<col min="1" max="1" width="4" customWidth="1"/>'
          . '<col min="2" max="2" width="30" customWidth="1"/>'
          . '<col min="3" max="3" width="22" customWidth="1"/>'
          . '<col min="4" max="4" width="20" customWidth="1"/>'
          . '<col min="5" max="5" width="45" customWidth="1"/>';
    return finishSheet($rows, $merges, $cDef);
}

// ============================================================
// STYLES
// ============================================================
function buildStyles() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts>
    <font><sz val="10"/><name val="Arial"/></font>
    <font><sz val="14"/><b/><color rgb="FFFFFFFF"/><name val="Arial"/></font>
    <font><sz val="11"/><b/><color rgb="FFFFFFFF"/><name val="Arial"/></font>
    <font><sz val="10"/><color rgb="FFDBEAFE"/><name val="Arial"/></font>
    <font><sz val="10"/><b/><color rgb="FFFFFFFF"/><name val="Arial"/></font>
    <font><sz val="10"/><name val="Arial"/></font>
    <font><sz val="10"/><b/><color rgb="FF166534"/><name val="Arial"/></font>
    <font><sz val="10"/><b/><color rgb="FFC2410C"/><name val="Arial"/></font>
    <font><sz val="10"/><b/><color rgb="FF0E7490"/><name val="Arial"/></font>
    <font><sz val="10"/><b/><color rgb="FF92400E"/><name val="Arial"/></font>
    <font><sz val="10"/><b/><color rgb="FF991B1B"/><name val="Arial"/></font>
    <font><sz val="10"/><b/><color rgb="FF1E40AF"/><name val="Arial"/></font>
    <font><sz val="9"/><i/><color rgb="FF64748B"/><name val="Arial"/></font>
    <font><sz val="9"/><i/><color rgb="FF374151"/><name val="Arial"/></font>
    <font><sz val="10"/><b/><color rgb="FF5B21B6"/><name val="Arial"/></font>
  </fonts>
  <fills>
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1D4ED8"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF3B82F6"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFEDD5"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFCFFAFE"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF9C3"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFEDE9FE"/></patternFill></fill>
  </fills>
  <borders>
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FF93C5FD"/></left>
      <right style="thin"><color rgb="FF93C5FD"/></right>
      <top style="thin"><color rgb="FF93C5FD"/></top>
      <bottom style="thin"><color rgb="FF93C5FD"/></bottom>
    </border>
    <border>
      <left style="medium"><color rgb="FF1E3A8A"/></left>
      <right style="medium"><color rgb="FF1E3A8A"/></right>
      <top style="medium"><color rgb="FF1E3A8A"/></top>
      <bottom style="medium"><color rgb="FF1E3A8A"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs>
    <!-- 0: default -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <!-- 1: Judul utama (dark blue bg, white bold 14) -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="2" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <!-- 2: Nama sekolah (medium blue bg, white bold 11) -->
    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 3: Bulan (light blue bg, light blue text) -->
    <xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 4: Header kolom (dark blue bg, white bold) -->
    <xf numFmtId="0" fontId="4" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <!-- 5: Data center, light gray bg -->
    <xf numFmtId="0" fontId="5" fillId="5" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 6: Data left -->
    <xf numFmtId="0" fontId="5" fillId="5" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
    <!-- 7: Hadir — green -->
    <xf numFmtId="0" fontId="6" fillId="7" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 8: unused -->
    <xf numFmtId="0" fontId="5" fillId="6" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 9: Terlambat — orange -->
    <xf numFmtId="0" fontId="7" fillId="8" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 10: Izin — cyan -->
    <xf numFmtId="0" fontId="8" fillId="9" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 11: Sakit — yellow -->
    <xf numFmtId="0" fontId="9" fillId="10" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 12: Alpha — red -->
    <xf numFmtId="0" fontId="10" fillId="11" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 13: Total H — blue total bg -->
    <xf numFmtId="0" fontId="11" fillId="12" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 14: Pending / Lebih Awal — purple -->
    <xf numFmtId="0" fontId="14" fillId="13" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 15: Total TL -->
    <xf numFmtId="0" fontId="7" fillId="12" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 16: Total I -->
    <xf numFmtId="0" fontId="8" fillId="12" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 17: Total S -->
    <xf numFmtId="0" fontId="9" fillId="12" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 18: Keterangan label bold -->
    <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
    <!-- 19: Keterangan isi italic gray -->
    <xf numFmtId="0" fontId="13" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
  </cellXfs>
</styleSheet>';
}

// ============================================================
// BUILD SHARED STRINGS XML
// ============================================================
function buildSharedStringsXml() {
    global $sharedStrings;
    $n = count($sharedStrings);
    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
       . " count=\"$n\" uniqueCount=\"$n\">";
    foreach ($sharedStrings as $s)
        $x .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1|ENT_QUOTES, 'UTF-8') . '</t></si>';
    return $x . '</sst>';
}

// ============================================================
// GENERATE ALL SHEET XML (populates shared strings first)
// ============================================================
$sheet1 = buildSheet1($allGuru, $absensiMap, $hariDalamBulan, $bulanInt, $tahunInt, $labelBulan, $namaSekolah);
$sheet2 = buildSheet2($allAbsensi, $labelBulan, $namaSekolah);
$sheet3 = buildSheet3($allAbsensi, $labelBulan, $namaSekolah);
$sheet4 = buildSheet4($allAbsensi, $labelBulan, $namaSekolah);
$sheet5 = buildSheet5($allGuru, $labelBulan, $namaSekolah);

// ============================================================
// ASSEMBLE XLSX ZIP
// ============================================================
$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="REKAP BULANAN"       sheetId="1" r:id="rId1"/>
    <sheet name="DETAIL HARIAN"       sheetId="2" r:id="rId2"/>
    <sheet name="LOKASI &amp; SELFIE" sheetId="3" r:id="rId3"/>
    <sheet name="IZIN &amp; KLARIFIKASI" sheetId="4" r:id="rId4"/>
    <sheet name="DATA GURU"           sheetId="5" r:id="rId5"/>
  </sheets>
</workbook>';

$workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/>
  <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet5.xml"/>
  <Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId7" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"          ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet5.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml"     ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml"            ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

$tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die('Gagal membuat file ZIP sementara.');
}
$zip->addFromString('[Content_Types].xml',         $contentTypes);
$zip->addFromString('_rels/.rels',                 $rootRels);
$zip->addFromString('xl/workbook.xml',             $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels',  $workbookRels);
$zip->addFromString('xl/styles.xml',               buildStyles());
$zip->addFromString('xl/worksheets/sheet1.xml',    $sheet1);
$zip->addFromString('xl/worksheets/sheet2.xml',    $sheet2);
$zip->addFromString('xl/worksheets/sheet3.xml',    $sheet3);
$zip->addFromString('xl/worksheets/sheet4.xml',    $sheet4);
$zip->addFromString('xl/worksheets/sheet5.xml',    $sheet5);
$zip->addFromString('xl/sharedStrings.xml',        buildSharedStringsXml());
$zip->close();

// ============================================================
// SEND FILE TO BROWSER
// ============================================================
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . rawurlencode($namaFile) . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0, no-cache, no-store');
header('Pragma: no-cache');

readfile($tmpFile);
unlink($tmpFile);
exit;

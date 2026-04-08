<?php
require_once '../includes/auth_admin.php';

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: absensi.php');
    exit();
}

$aksi = sanitize($conn, $_POST['aksi'] ?? '');
$id   = (int)($_POST['id'] ?? 0);

if (!$id) {
    $_SESSION['notif'] = ['type' => 'error', 'msg' => 'ID tidak valid.'];
    header('Location: absensi.php');
    exit();
}

// ============================================================
// AKSI 1: EDIT STATUS
// ============================================================
if ($aksi === 'edit_status') {
    $allowed_status = ['hadir', 'izin', 'sakit', 'alpha'];
    $status         = sanitize($conn, $_POST['status'] ?? '');
    $keterangan     = sanitize($conn, $_POST['keterangan'] ?? '');

    if (!in_array($status, $allowed_status)) {
        $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Status tidak valid.'];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE absensi SET status = ?, keterangan = ? WHERE id = ?");
    $stmt->bind_param('ssi', $status, $keterangan, $id);

    if ($stmt->execute()) {
        $_SESSION['notif'] = ['type' => 'success', 'msg' => 'Status absensi berhasil diperbarui.'];
    } else {
        $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Gagal memperbarui status.'];
    }
    $stmt->close();
}

// ============================================================
// AKSI 2: UPLOAD BUKTI
// ============================================================
elseif ($aksi === 'upload_bukti') {
    $upload_dir = '../uploads/bukti/';

    // Buat folder jika belum ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (empty($_FILES['bukti_file']['name'])) {
        $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Tidak ada file yang dipilih.'];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    $file      = $_FILES['bukti_file'];
    $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed   = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_size  = 2 * 1024 * 1024; // 2 MB

    // Validasi ekstensi
    if (!in_array($ext, $allowed)) {
        $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Format file tidak diizinkan. Gunakan JPG, PNG, atau PDF.'];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // Validasi MIME type
    $finfo     = finfo_open(FILEINFO_MIME_TYPE);
    $mime      = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed_mime = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($mime, $allowed_mime)) {
        $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Tipe file tidak valid.'];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // Validasi ukuran
    if ($file['size'] > $max_size) {
        $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Ukuran file melebihi batas 2 MB.'];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // Cek error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Terjadi kesalahan saat upload file.'];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // Hapus file lama jika ada
    $existing = $conn->query("SELECT bukti_file FROM absensi WHERE id = $id")->fetch_assoc();
    if (!empty($existing['bukti_file'])) {
        $old_path = $upload_dir . $existing['bukti_file'];
        if (file_exists($old_path)) {
            unlink($old_path);
        }
    }

    // Generate nama file unik
    $new_name = 'bukti_' . $id . '_' . time() . '.' . $ext;
    $dest     = $upload_dir . $new_name;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $safe_name = sanitize($conn, $new_name);
        $stmt = $conn->prepare("UPDATE absensi SET bukti_file = ? WHERE id = ?");
        $stmt->bind_param('si', $safe_name, $id);
        if ($stmt->execute()) {
            $_SESSION['notif'] = ['type' => 'success', 'msg' => 'Bukti berhasil diunggah.'];
        } else {
            $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Gagal menyimpan path file ke database.'];
        }
        $stmt->close();
    } else {
        $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Gagal memindahkan file ke server.'];
    }
}

// ============================================================
// AKSI 3: HAPUS DATA
// ============================================================
elseif ($aksi === 'hapus') {
    // Hapus file bukti jika ada sebelum hapus record
    $existing = $conn->query("SELECT bukti_file FROM absensi WHERE id = $id")->fetch_assoc();
    if (!empty($existing['bukti_file'])) {
        $file_path = '../uploads/bukti/' . $existing['bukti_file'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    $stmt = $conn->prepare("DELETE FROM absensi WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $_SESSION['notif'] = ['type' => 'success', 'msg' => 'Data absensi berhasil dihapus.'];
    } else {
        $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Gagal menghapus data absensi.'];
    }
    $stmt->close();
}

// Aksi tidak dikenal
else {
    $_SESSION['notif'] = ['type' => 'error', 'msg' => 'Aksi tidak dikenal.'];
}

// Redirect kembali ke halaman absensi dengan query string yang sama jika ada
$referer = $_SERVER['HTTP_REFERER'] ?? 'absensi.php';
// Pastikan redirect ke domain yang sama (anti open redirect)
$parsed = parse_url($referer);
$safe_referer = ($parsed['path'] ?? 'absensi.php');
if (!empty($parsed['query'])) {
    $safe_referer .= '?' . $parsed['query'];
}
header('Location: ' . $safe_referer);
exit();

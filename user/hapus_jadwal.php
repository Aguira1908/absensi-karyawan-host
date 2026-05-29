<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../assets/config/koneksi.php';

$id_karyawan = $_SESSION['user_id'];
$id_jadwal = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

if($id_jadwal > 0) {
    try {
        if($conn_type == 'pdo' || $conn_type == 'mysql') {
            // Cek status dan kepemilikan jadwal
            $stmt = $conn->prepare("SELECT status, tanggal, shift FROM jadwal WHERE id_jadwal = :id AND id_karyawan = :user");
            $stmt->execute([':id' => $id_jadwal, ':user' => $id_karyawan]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($data) {
                if($data['status'] == 'Menunggu') {
                    // Hapus jadwal
                    $stmt = $conn->prepare("DELETE FROM jadwal WHERE id_jadwal = :id AND id_karyawan = :user");
                    $stmt->execute([':id' => $id_jadwal, ':user' => $id_karyawan]);
                    $message = "✅ Jadwal untuk tanggal " . date('d-m-Y', strtotime($data['tanggal'])) . " (Shift " . $data['shift'] . ") berhasil dihapus!";
                } else {
                    $error = "❌ Jadwal tidak dapat dihapus karena sudah diproses admin! Status saat ini: " . $data['status'];
                }
            } else {
                $error = "❌ Jadwal tidak ditemukan atau bukan milik Anda!";
            }
        } else {
            // Oracle OCI8
            $sql = "SELECT status, TO_CHAR(tanggal, 'YYYY-MM-DD') as tgl, shift FROM jadwal WHERE id_jadwal = :id AND id_karyawan = :user";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':id', $id_jadwal);
            oci_bind_by_name($stmt, ':user', $id_karyawan);
            oci_execute($stmt);
            $data = oci_fetch_assoc($stmt);
            oci_free_statement($stmt);
            
            if($data) {
                if($data['STATUS'] == 'Menunggu') {
                    $sql_del = "DELETE FROM jadwal WHERE id_jadwal = :id AND id_karyawan = :user";
                    $stmt_del = oci_parse($conn, $sql_del);
                    oci_bind_by_name($stmt_del, ':id', $id_jadwal);
                    oci_bind_by_name($stmt_del, ':user', $id_karyawan);
                    $result = oci_execute($stmt_del, OCI_COMMIT_ON_SUCCESS);
                    oci_free_statement($stmt_del);
                    
                    if($result) {
                        $message = "✅ Jadwal untuk tanggal " . date('d-m-Y', strtotime($data['TGL'])) . " (Shift " . $data['SHIFT'] . ") berhasil dihapus!";
                    } else {
                        $error = "❌ Gagal menghapus jadwal!";
                    }
                } else {
                    $error = "❌ Jadwal tidak dapat dihapus karena sudah diproses admin! Status saat ini: " . $data['STATUS'];
                }
            } else {
                $error = "❌ Jadwal tidak ditemukan atau bukan milik Anda!";
            }
        }
    } catch(Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
} else {
    $error = "❌ ID jadwal tidak valid!";
}

// Redirect kembali dengan pesan
if($message) {
    header("Location: tambah_jadwal.php?message=" . urlencode($message));
} else {
    header("Location: tambah_jadwal.php?error=" . urlencode($error));
}
exit();
?>
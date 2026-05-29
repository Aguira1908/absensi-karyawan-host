<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../../assets/config/koneksi.php';

$id = $_GET['id'] ?? 0;
if($id) {
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("DELETE FROM karyawan WHERE id_karyawan = :id");
            $stmt->execute([':id'=>$id]);
        } else {
            $stmt = oci_parse($conn, "DELETE FROM karyawan WHERE id_karyawan = :id");
            oci_bind_by_name($stmt, ':id', $id);
            oci_execute($stmt);
            oci_commit($conn);
        }
        $_SESSION['message'] = "Karyawan berhasil dihapus.";
    } catch(Exception $e) {
        $_SESSION['error'] = "Gagal hapus: " . $e->getMessage();
    }
}
header("Location: index.php");
exit();
?>
<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../../assets/config/koneksi.php';

$id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Ambil data absensi
if($conn_type == 'pdo') {
    $stmt = $conn->prepare("SELECT * FROM absensi WHERE id_absensi = :id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = oci_parse($conn, "SELECT * FROM absensi WHERE id_absensi = :id");
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
}
if(!$row) { header("Location: index.php"); exit(); }

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = $_POST['status'];
    $keterangan = $_POST['keterangan'];
    
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("UPDATE absensi SET status = :status, keterangan = :keterangan WHERE id_absensi = :id");
            $stmt->execute([':status'=>$status, ':keterangan'=>$keterangan, ':id'=>$id]);
        } else {
            $stmt = oci_parse($conn, "UPDATE absensi SET status = :status, keterangan = :keterangan WHERE id_absensi = :id");
            oci_bind_by_name($stmt, ':status', $status);
            oci_bind_by_name($stmt, ':keterangan', $keterangan);
            oci_bind_by_name($stmt, ':id', $id);
            oci_execute($stmt);
            oci_commit($conn);
        }
        $success = "Data absensi berhasil diupdate!";
    } catch(Exception $e) {
        $error = "Gagal update: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-white"><h4><i class="fas fa-edit"></i> Edit Absensi</h4></div>
                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert alert-success"><?= $success ?> <a href="index.php">Kembali</a></div>
                    <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label>Status</label>
                            <select name="status" class="form-select">
                                <option value="Hadir" <?= $row['STATUS']=='Hadir'?'selected':'' ?>>Hadir</option>
                                <option value="Terlambat" <?= $row['STATUS']=='Terlambat'?'selected':'' ?>>Terlambat</option>
                                <option value="Sakit" <?= $row['STATUS']=='Sakit'?'selected':'' ?>>Sakit</option>
                                <option value="Izin" <?= $row['STATUS']=='Izin'?'selected':'' ?>>Izin</option>
                                <option value="Alpha" <?= $row['STATUS']=='Alpha'?'selected':'' ?>>Alpha</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"><?= htmlspecialchars($row['KETERANGAN']) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        <a href="index.php" class="btn btn-secondary">Batal</a>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
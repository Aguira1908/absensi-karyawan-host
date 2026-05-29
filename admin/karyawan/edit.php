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

// Ambil data karyawan
if($conn_type == 'pdo') {
    $stmt = $conn->prepare("SELECT * FROM karyawan WHERE id_karyawan = :id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = oci_parse($conn, "SELECT * FROM karyawan WHERE id_karyawan = :id");
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
}
if(!$row) { header("Location: index.php"); exit(); }

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $jabatan = $_POST['jabatan'];
    $no_hp = $_POST['no_hp'];
    $email = $_POST['email'];
    $alamat = $_POST['alamat'];
    $status = $_POST['status'];
    $gaji_pokok = str_replace('.', '', $_POST['gaji_pokok']);
    
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("UPDATE karyawan SET nama=:nama, jabatan=:jabatan, no_hp=:no_hp, email=:email, alamat=:alamat, status=:status, gaji_pokok=:gaji WHERE id_karyawan=:id");
            $stmt->execute([':nama'=>$nama, ':jabatan'=>$jabatan, ':no_hp'=>$no_hp, ':email'=>$email, ':alamat'=>$alamat, ':status'=>$status, ':gaji'=>$gaji_pokok, ':id'=>$id]);
        } else {
            $sql = "UPDATE karyawan SET nama=:nama, jabatan=:jabatan, no_hp=:no_hp, email=:email, alamat=:alamat, status=:status, gaji_pokok=:gaji WHERE id_karyawan=:id";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':nama', $nama);
            oci_bind_by_name($stmt, ':jabatan', $jabatan);
            oci_bind_by_name($stmt, ':no_hp', $no_hp);
            oci_bind_by_name($stmt, ':email', $email);
            oci_bind_by_name($stmt, ':alamat', $alamat);
            oci_bind_by_name($stmt, ':status', $status);
            oci_bind_by_name($stmt, ':gaji', $gaji_pokok);
            oci_bind_by_name($stmt, ':id', $id);
            oci_execute($stmt);
            oci_commit($conn);
        }
        $success = "Data berhasil diupdate!";
        // Refresh data
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("SELECT * FROM karyawan WHERE id_karyawan = :id");
            $stmt->execute([':id'=>$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = oci_parse($conn, "SELECT * FROM karyawan WHERE id_karyawan = :id");
            oci_bind_by_name($stmt, ':id', $id);
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
        }
    } catch(Exception $e) {
        $error = "Gagal update: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Karyawan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>body{background:#f5f5f5} .card{border-radius:15px}</style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-white"><h4><i class="fas fa-edit"></i> Edit Karyawan</h4></div>
                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert alert-success"><?= $success ?> <a href="index.php">Kembali</a></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3"><label>Nama Lengkap</label><input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($row['NAMA']) ?>" required></div>
                            <div class="col-md-6 mb-3"><label>Jabatan</label><input type="text" name="jabatan" class="form-control" value="<?= htmlspecialchars($row['JABATAN']) ?>" required></div>
                            <div class="col-md-6 mb-3"><label>No. HP</label><input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($row['NO_HP']) ?>"></div>
                            <div class="col-md-6 mb-3"><label>Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($row['EMAIL']) ?>"></div>
                            <div class="col-12 mb-3"><label>Alamat</label><textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($row['ALAMAT']) ?></textarea></div>
                            <div class="col-md-4 mb-3"><label>Status</label>
                                <select name="status" class="form-select"><option value="Aktif" <?= $row['STATUS']=='Aktif'?'selected':'' ?>>Aktif</option><option value="Nonaktif" <?= $row['STATUS']=='Nonaktif'?'selected':'' ?>>Nonaktif</option></select>
                            </div>
                            <div class="col-md-4 mb-3"><label>Gaji Pokok</label><input type="text" name="gaji_pokok" class="form-control" value="<?= number_format($row['GAJI_POKOK'],0,',','.') ?>"></div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                        <a href="index.php" class="btn btn-secondary">Batal</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
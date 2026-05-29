<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../../assets/config/koneksi.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $jabatan = $_POST['jabatan'];
    $no_hp = $_POST['no_hp'];
    $email = $_POST['email'];
    $alamat = $_POST['alamat'];
    $gaji_pokok = str_replace('.', '', $_POST['gaji_pokok']);
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    
    // Validasi
    if(empty($nama) || empty($jabatan) || empty($username) || empty($_POST['password'])) {
        $error = "Nama, jabatan, username dan password wajib diisi!";
    } else {
        try {
            if($conn_type == 'pdo') {
                $conn->beginTransaction();
                // Insert karyawan
                $stmt = $conn->prepare("INSERT INTO karyawan (nama, jabatan, no_hp, email, alamat, gaji_pokok) VALUES (:nama, :jabatan, :no_hp, :email, :alamat, :gaji)");
                $stmt->execute([':nama'=>$nama, ':jabatan'=>$jabatan, ':no_hp'=>$no_hp, ':email'=>$email, ':alamat'=>$alamat, ':gaji'=>$gaji_pokok]);
                $id_karyawan = $conn->lastInsertId();
                // Insert users
                $stmt = $conn->prepare("INSERT INTO users (username, password, id_karyawan, is_active) VALUES (:user, :pass, :id, 'Y')");
                $stmt->execute([':user'=>$username, ':pass'=>$password, ':id'=>$id_karyawan]);
                $conn->commit();
                $success = "Karyawan berhasil ditambahkan! Username: $username, Password: ".$_POST['password'];
            } else {
                // OCI8
                $sql = "BEGIN
                            INSERT INTO karyawan (nama, jabatan, no_hp, email, alamat, gaji_pokok) 
                            VALUES (:nama, :jabatan, :no_hp, :email, :alamat, :gaji) RETURNING id_karyawan INTO :id;
                            INSERT INTO users (username, password, id_karyawan, is_active) 
                            VALUES (:user, :pass, :id, 'Y');
                        END;";
                $stmt = oci_parse($conn, $sql);
                oci_bind_by_name($stmt, ':nama', $nama);
                oci_bind_by_name($stmt, ':jabatan', $jabatan);
                oci_bind_by_name($stmt, ':no_hp', $no_hp);
                oci_bind_by_name($stmt, ':email', $email);
                oci_bind_by_name($stmt, ':alamat', $alamat);
                oci_bind_by_name($stmt, ':gaji', $gaji_pokok);
                oci_bind_by_name($stmt, ':user', $username);
                oci_bind_by_name($stmt, ':pass', $password);
                oci_bind_by_name($stmt, ':id', $id_karyawan, 10);
                oci_execute($stmt);
                oci_commit($conn);
                $success = "Karyawan berhasil ditambahkan! Username: $username, Password: ".$_POST['password'];
            }
        } catch(Exception $e) {
            if($conn_type == 'pdo') $conn->rollBack();
            else oci_rollback($conn);
            $error = "Gagal menambah: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Karyawan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>body{background:#f5f5f5} .card{border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1)} .btn-primary{background:#C8A951;border:none} .btn-primary:hover{background:#b8962e}</style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-white">
                    <h4><i class="fas fa-user-plus"></i> Tambah Karyawan</h4>
                </div>
                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert alert-success"><?= $success ?> <a href="index.php">Kembali ke daftar</a></div>
                    <?php else: ?>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Nama Lengkap *</label>
                                <input type="text" name="nama" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Jabatan *</label>
                                <select name="jabatan" class="form-select" required>
                                    <option value="Barista">Barista</option><option value="Kasir">Kasir</option><option value="Kitchen">Kitchen</option>
                                    <option value="Waiters">Waiters</option><option value="Helper kitchen">Helper kitchen</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>No. HP</label>
                                <input type="text" name="no_hp" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="col-12 mb-3">
                                <label>Alamat</label>
                                <textarea name="alamat" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Gaji Pokok</label>
                                <input type="text" name="gaji_pokok" class="form-control" placeholder="3500000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Username (untuk login) *</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Password *</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
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
<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../../assets/config/koneksi.php';

$error = '';
$success = '';

// Ambil daftar karyawan aktif untuk dropdown
if($conn_type == 'pdo') {
    $stmt = $conn->query("SELECT id_karyawan, nama, jabatan FROM karyawan WHERE status = 'Aktif' ORDER BY nama");
    $karyawan = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = oci_parse($conn, "SELECT id_karyawan, nama, jabatan FROM karyawan WHERE status = 'Aktif' ORDER BY nama");
    oci_execute($stmt);
    $karyawan = [];
    while($row = oci_fetch_assoc($stmt)) $karyawan[] = $row;
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_karyawan = $_POST['id_karyawan'];
    $tanggal = $_POST['tanggal'];
    $shift = $_POST['shift'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    $keterangan = $_POST['keterangan'];
    $status = $_POST['status'];
    
    if(empty($id_karyawan) || empty($tanggal) || empty($shift)) {
        $error = "Karyawan, tanggal, dan shift wajib diisi!";
    } else {
        try {
            if($conn_type == 'pdo') {
                $stmt = $conn->prepare("INSERT INTO jadwal (id_karyawan, tanggal, shift, jam_mulai, jam_selesai, keterangan, status, created_by) VALUES (:id, :tanggal, :shift, :jam_mulai, :jam_selesai, :keterangan, :status, :admin)");
                $stmt->execute([
                    ':id' => $id_karyawan,
                    ':tanggal' => $tanggal,
                    ':shift' => $shift,
                    ':jam_mulai' => $jam_mulai,
                    ':jam_selesai' => $jam_selesai,
                    ':keterangan' => $keterangan,
                    ':status' => $status,
                    ':admin' => $_SESSION['user_id']
                ]);
            } else {
                $sql = "INSERT INTO jadwal (id_karyawan, tanggal, shift, jam_mulai, jam_selesai, keterangan, status, created_by) 
                        VALUES (:id, TO_DATE(:tanggal, 'YYYY-MM-DD'), :shift, :jam_mulai, :jam_selesai, :keterangan, :status, :admin)";
                $stmt = oci_parse($conn, $sql);
                oci_bind_by_name($stmt, ':id', $id_karyawan);
                oci_bind_by_name($stmt, ':tanggal', $tanggal);
                oci_bind_by_name($stmt, ':shift', $shift);
                oci_bind_by_name($stmt, ':jam_mulai', $jam_mulai);
                oci_bind_by_name($stmt, ':jam_selesai', $jam_selesai);
                oci_bind_by_name($stmt, ':keterangan', $keterangan);
                oci_bind_by_name($stmt, ':status', $status);
                oci_bind_by_name($stmt, ':admin', $_SESSION['user_id']);
                oci_execute($stmt);
                oci_commit($conn);
            }
            $success = "Jadwal berhasil ditambahkan!";
        } catch(Exception $e) {
            $error = "Gagal menambah: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Jadwal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>body{background:#f5f5f5} .card{border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1)} .btn-primary{background:#C8A951;border:none}</style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-white"><h4><i class="fas fa-plus-circle"></i> Tambah Jadwal</h4></div>
                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert alert-success"><?= $success ?> <a href="index.php">Kembali ke daftar</a></div>
                    <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label>Karyawan *</label>
                            <select name="id_karyawan" class="form-select" required>
                                <option value="">Pilih Karyawan</option>
                                <?php foreach($karyawan as $k): ?>
                                    <option value="<?= $k['ID_KARYAWAN'] ?>"><?= htmlspecialchars($k['NAMA']) ?> (<?= $k['JABATAN'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Tanggal *</label>
                            <input type="date" name="tanggal" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Shift *</label>
                            <select name="shift" class="form-select" required>
                                <option value="Pagi">shift 1  (00:00 - 08:00)</option>
                                <option value="Siang">shift 2 (08:00 - 17:00)</option>
                                <option value="Malam">shift 3 (16:00 - 00:00)</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Jam Mulai</label>
                                <input type="time" name="jam_mulai" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Jam Selesai</label>
                                <input type="time" name="jam_selesai" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Status</label>
                            <select name="status" class="form-select">
                                <option value="Disetujui">Disetujui</option>
                                <option value="Menunggu">Menunggu</option>
                                <option value="Ditolak">Ditolak</option>
                            </select>
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
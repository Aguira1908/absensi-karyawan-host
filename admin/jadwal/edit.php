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

// Ambil data jadwal
if($conn_type == 'pdo') {
    $stmt = $conn->prepare("SELECT * FROM jadwal WHERE id_jadwal = :id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = oci_parse($conn, "SELECT * FROM jadwal WHERE id_jadwal = :id");
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
}
if(!$row) { header("Location: index.php"); exit(); }

// Ambil daftar karyawan
if($conn_type == 'pdo') {
    $stmt = $conn->query("SELECT id_karyawan, nama, jabatan FROM karyawan WHERE status = 'Aktif' ORDER BY nama");
    $karyawan = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = oci_parse($conn, "SELECT id_karyawan, nama, jabatan FROM karyawan WHERE status = 'Aktif' ORDER BY nama");
    oci_execute($stmt);
    $karyawan = [];
    while($row2 = oci_fetch_assoc($stmt)) $karyawan[] = $row2;
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_karyawan = $_POST['id_karyawan'];
    $tanggal = $_POST['tanggal'];
    $shift = $_POST['shift'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    $keterangan = $_POST['keterangan'];
    $status = $_POST['status'];
    
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("UPDATE jadwal SET id_karyawan=:id, tanggal=:tanggal, shift=:shift, jam_mulai=:jam_mulai, jam_selesai=:jam_selesai, keterangan=:keterangan, status=:status, approved_by=:admin, approved_at=SYSDATE WHERE id_jadwal=:jadwal_id");
            $stmt->execute([
                ':id' => $id_karyawan,
                ':tanggal' => $tanggal,
                ':shift' => $shift,
                ':jam_mulai' => $jam_mulai,
                ':jam_selesai' => $jam_selesai,
                ':keterangan' => $keterangan,
                ':status' => $status,
                ':admin' => $_SESSION['user_id'],
                ':jadwal_id' => $id
            ]);
        } else {
            $sql = "UPDATE jadwal SET id_karyawan=:id, tanggal=TO_DATE(:tanggal, 'YYYY-MM-DD'), shift=:shift, jam_mulai=:jam_mulai, jam_selesai=:jam_selesai, keterangan=:keterangan, status=:status, approved_by=:admin, approved_at=SYSDATE WHERE id_jadwal=:jadwal_id";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':id', $id_karyawan);
            oci_bind_by_name($stmt, ':tanggal', $tanggal);
            oci_bind_by_name($stmt, ':shift', $shift);
            oci_bind_by_name($stmt, ':jam_mulai', $jam_mulai);
            oci_bind_by_name($stmt, ':jam_selesai', $jam_selesai);
            oci_bind_by_name($stmt, ':keterangan', $keterangan);
            oci_bind_by_name($stmt, ':status', $status);
            oci_bind_by_name($stmt, ':admin', $_SESSION['user_id']);
            oci_bind_by_name($stmt, ':jadwal_id', $id);
            oci_execute($stmt);
            oci_commit($conn);
        }
        $success = "Jadwal berhasil diupdate!";
        // Refresh data
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("SELECT * FROM jadwal WHERE id_jadwal = :id");
            $stmt->execute([':id'=>$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = oci_parse($conn, "SELECT * FROM jadwal WHERE id_jadwal = :id");
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
    <title>Edit Jadwal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>body{background:#f5f5f5} .card{border-radius:15px}</style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-white"><h4><i class="fas fa-edit"></i> Edit Jadwal</h4></div>
                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert alert-success"><?= $success ?> <a href="index.php">Kembali ke daftar</a></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label>Karyawan *</label>
                            <select name="id_karyawan" class="form-select" required>
                                <?php foreach($karyawan as $k): ?>
                                    <option value="<?= $k['ID_KARYAWAN'] ?>" <?= ($k['ID_KARYAWAN'] == $row['ID_KARYAWAN']) ? 'selected' : '' ?>><?= htmlspecialchars($k['NAMA']) ?> (<?= $k['JABATAN'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Tanggal *</label>
                            <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d', strtotime($row['TANGGAL'])) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Shift *</label>
                            <select name="shift" class="form-select" required>
                                <option value="Pagi" <?= $row['SHIFT']=='Pagi'?'selected':'' ?>>Pagi (08:00 - 16:00)</option>
                                <option value="Siang" <?= $row['SHIFT']=='Siang'?'selected':'' ?>>Siang (12:00 - 20:00)</option>
                                <option value="Malam" <?= $row['SHIFT']=='Malam'?'selected':'' ?>>Malam (16:00 - 00:00)</option>
                                <option value="Lembur" <?= $row['SHIFT']=='Lembur'?'selected':'' ?>>Lembur (sesuai permintaan)</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Jam Mulai</label>
                                <input type="time" name="jam_mulai" class="form-control" value="<?= $row['JAM_MULAI'] ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Jam Selesai</label>
                                <input type="time" name="jam_selesai" class="form-control" value="<?= $row['JAM_SELESAI'] ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($row['KETERANGAN']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Status</label>
                            <select name="status" class="form-select">
                                <option value="Disetujui" <?= $row['STATUS']=='Disetujui'?'selected':'' ?>>Disetujui</option>
                                <option value="Menunggu" <?= $row['STATUS']=='Menunggu'?'selected':'' ?>>Menunggu</option>
                                <option value="Ditolak" <?= $row['STATUS']=='Ditolak'?'selected':'' ?>>Ditolak</option>
                            </select>
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
<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../assets/config/koneksi.php';

$id_karyawan = $_SESSION['id_karyawan'];
$id_jadwal = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';
$data = null;

// Ambil data jadwal yang akan diedit
if($id_jadwal > 0) {
    if($conn_type == 'pdo' || $conn_type == 'mysql') {
        $stmt = $conn->prepare("SELECT * FROM jadwal WHERE id_jadwal = :id AND id_karyawan = :user AND status = 'Menunggu'");
        $stmt->execute([':id' => $id_jadwal, ':user' => $id_karyawan]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT * FROM jadwal WHERE id_jadwal = :id AND id_karyawan = :user AND status = 'Menunggu'";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $id_jadwal);
        oci_bind_by_name($stmt, ':user', $id_karyawan);
        oci_execute($stmt);
        $data = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
    }
    
    if(!$data) {
        $error = "Data tidak ditemukan atau sudah diproses admin!";
    }
} else {
    $error = "ID tidak valid!";
}

// Proses update jadwal
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_jadwal'])) {
    $tanggal = $_POST['tanggal'];
    $shift = $_POST['shift'];
    $keterangan = $_POST['keterangan'] ?? '';
    
    $shift_hours = [
        'Pagi' => ['mulai' => '00:00:00', 'selesai' => '08:00:00'],
        'Siang' => ['mulai' => '08:00:00', 'selesai' => '17:00:00'],
        'Malam' => ['mulai' => '16:00:00', 'selesai' => '00:00:00']
    ];
    
    $jam_mulai = $shift_hours[$shift]['mulai'];
    $jam_selesai = $shift_hours[$shift]['selesai'];
    
    if(empty($tanggal) || empty($shift)) {
        $error = "Tanggal dan shift wajib diisi!";
    } else {
        try {
            if($conn_type == 'pdo' || $conn_type == 'mysql') {
                $stmt = $conn->prepare("UPDATE jadwal SET tanggal = :tanggal, shift = :shift, jam_mulai = :jam_mulai, jam_selesai = :jam_selesai, keterangan = :keterangan WHERE id_jadwal = :id AND id_karyawan = :user AND status = 'Menunggu'");
                $stmt->execute([
                    ':tanggal' => $tanggal,
                    ':shift' => $shift,
                    ':jam_mulai' => $jam_mulai,
                    ':jam_selesai' => $jam_selesai,
                    ':keterangan' => $keterangan,
                    ':id' => $id_jadwal,
                    ':user' => $id_karyawan
                ]);
                $message = "Jadwal berhasil diupdate! Menunggu persetujuan admin.";
                echo "<script>setTimeout(()=>{window.location.href='tambah_jadwal.php'},1500)</script>";
            } else {
                $sql = "UPDATE jadwal SET tanggal = TO_DATE(:tanggal, 'YYYY-MM-DD'), shift = :shift, jam_mulai = :jam_mulai, jam_selesai = :jam_selesai, keterangan = :keterangan WHERE id_jadwal = :id AND id_karyawan = :user AND status = 'Menunggu'";
                $stmt = oci_parse($conn, $sql);
                oci_bind_by_name($stmt, ':tanggal', $tanggal);
                oci_bind_by_name($stmt, ':shift', $shift);
                oci_bind_by_name($stmt, ':jam_mulai', $jam_mulai);
                oci_bind_by_name($stmt, ':jam_selesai', $jam_selesai);
                oci_bind_by_name($stmt, ':keterangan', $keterangan);
                oci_bind_by_name($stmt, ':id', $id_jadwal);
                oci_bind_by_name($stmt, ':user', $id_karyawan);
                $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
                oci_free_statement($stmt);
                
                if($result) {
                    $message = "Jadwal berhasil diupdate! Menunggu persetujuan admin.";
                    echo "<script>setTimeout(()=>{window.location.href='tambah_jadwal.php'},1500)</script>";
                } else {
                    $e = oci_error($conn);
                    throw new Exception($e['message']);
                }
            }
        } catch(Exception $e) {
            $error = "Gagal update: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Jadwal - Ck-Ck Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background: #2C1810; color: white; padding: 12px 0; }
        .btn-logout { background: #dc3545; color: white; padding: 6px 15px; border-radius: 8px; text-decoration: none; font-size: 0.8rem; }
        .card-custom { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .btn-primary { background: #C8A951; border: none; }
        .btn-primary:hover { background: #b8962e; }
        .btn-back { background: #6c757d; border: none; }
    </style>
</head>
<body>
    <nav class="navbar-custom">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div><i class="fas fa-coffee"></i> Ck-Ck Coffee</div>
                <div>
                    <span>Halo, <?= htmlspecialchars($_SESSION['nama'] ?? 'Karyawan') ?></span>
                    <a href="../logout.php" class="btn-logout ms-2">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card-custom">
                    <h4><i class="fas fa-edit"></i> Edit Pengajuan Jadwal</h4>
                    <p class="text-muted">Hanya jadwal dengan status "Menunggu" yang dapat diedit.</p>
                    
                    <?php if($message): ?>
                        <div class="alert alert-success"><?= $message ?></div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if($data && !$message): ?>
                    <form method="POST">
                        <input type="hidden" name="update_jadwal" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal *</label>
                                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d', strtotime($data['TANGGAL'])) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Shift *</label>
                                <select name="shift" class="form-select" required>
                                    <option value="Pagi" <?= ($data['SHIFT'] == 'Pagi') ? 'selected' : '' ?>>🌅 Shift Pagi (00:00 - 08:00)</option>
                                    <option value="Siang" <?= ($data['SHIFT'] == 'Siang') ? 'selected' : '' ?>>☀️ Shift Siang (08:00 - 17:00)</option>
                                    <option value="Malam" <?= ($data['SHIFT'] == 'Malam') ? 'selected' : '' ?>>🌙 Shift Malam (16:00 - 00:00)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"><?= htmlspecialchars($data['KETERANGAN'] ?? '') ?></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                            <a href="tambah_jadwal.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Batal</a>
                        </div>
                    </form>
                    <?php elseif(!$message): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> <?= $error ?: 'Data tidak ditemukan!' ?>
                    </div>
                    <a href="tambah_jadwal.php" class="btn btn-back">Kembali</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
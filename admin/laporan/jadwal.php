<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../../assets/config/koneksi.php';

$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$tahun = substr($bulan, 0, 4);
$bulan_num = substr($bulan, 5, 2);

$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

if($conn_type == 'pdo') {
    $stmt = $conn->prepare("SELECT j.*, k.nama, k.jabatan 
                           FROM jadwal j 
                           JOIN karyawan k ON j.id_karyawan = k.id_karyawan 
                           WHERE EXTRACT(MONTH FROM j.tanggal) = :bulan 
                           AND EXTRACT(YEAR FROM j.tanggal) = :tahun
                           ORDER BY j.tanggal, j.shift");
    $stmt->execute([':bulan' => $bulan_num, ':tahun' => $tahun]);
    $jadwal = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sql = "SELECT j.*, k.nama, k.jabatan 
            FROM jadwal j 
            JOIN karyawan k ON j.id_karyawan = k.id_karyawan 
            WHERE EXTRACT(MONTH FROM j.tanggal) = $bulan_num 
            AND EXTRACT(YEAR FROM j.tanggal) = $tahun
            ORDER BY j.tanggal, j.shift";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $jadwal = [];
    while($row = oci_fetch_assoc($stmt)) $jadwal[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Jadwal - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>body{background:#f5f5f5}</style>
</head>
<body>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-white">
            <h4><i class="fas fa-calendar-alt"></i> Laporan Jadwal Kerja</h4>
            <small>Periode: <?= $nama_bulan[$bulan_num] ?> <?= $tahun ?></small>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label>Bulan</label>
                    <input type="month" name="bulan" class="form-control" value="<?= $bulan ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
                    <a href="jadwal.php" class="btn btn-secondary ms-2">Reset</a>
                    <a href="export_jadwal.php?bulan=<?= $bulan ?>" class="btn btn-success ms-2"><i class="fas fa-file-excel"></i> Excel</a>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr><th>Tanggal</th><th>Karyawan</th><th>Jabatan</th><th>Shift</th><th>Jam Mulai</th><th>Jam Selesai</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($jadwal)): ?>
                            <tr><td colspan="7" class="text-center">Tidak ada data jadwal</td></tr>
                        <?php else: ?>
                            <?php foreach($jadwal as $j): ?>
                            <tr>
                                <td><?= date('d-m-Y', strtotime($j['TANGGAL'])) ?></td>
                                <td><?= htmlspecialchars($j['NAMA']) ?></td>
                                <td><?= $j['JABATAN'] ?></td>
                                <td><?= $j['SHIFT'] ?></td>
                                <td><?= $j['JAM_MULAI'] ?? '-' ?></td>
                                <td><?= $j['JAM_SELESAI'] ?? '-' ?></td>
                                <td><span class="badge bg-<?= $j['STATUS']=='Disetujui'?'success':'warning' ?>"><?= $j['STATUS'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
    </div>
</div>
</body>
</html>
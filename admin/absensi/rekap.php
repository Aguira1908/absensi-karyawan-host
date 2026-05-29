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

if($conn_type == 'pdo') {
    $stmt = $conn->prepare("SELECT k.id_karyawan, k.nama, k.jabatan,
                                   COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as hadir,
                                   COUNT(CASE WHEN a.status = 'Terlambat' THEN 1 END) as terlambat,
                                   COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as sakit,
                                   COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as izin,
                                   COUNT(CASE WHEN a.status = 'Alpha' THEN 1 END) as alpha,
                                   COUNT(a.id_absensi) as total
                            FROM karyawan k
                            LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan 
                                AND EXTRACT(MONTH FROM a.tanggal) = :bulan 
                                AND EXTRACT(YEAR FROM a.tanggal) = :tahun
                            WHERE k.status = 'Aktif'
                            GROUP BY k.id_karyawan, k.nama, k.jabatan
                            ORDER BY k.nama");
    $stmt->execute([':bulan' => $bulan_num, ':tahun' => $tahun]);
    $rekap = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sql = "SELECT k.id_karyawan, k.nama, k.jabatan,
                   SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                   SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) as terlambat,
                   SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                   SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) as izin,
                   SUM(CASE WHEN a.status = 'Alpha' THEN 1 ELSE 0 END) as alpha,
                   COUNT(a.id_absensi) as total
            FROM karyawan k
            LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan 
                AND EXTRACT(MONTH FROM a.tanggal) = $bulan_num 
                AND EXTRACT(YEAR FROM a.tanggal) = $tahun
            WHERE k.status = 'Aktif'
            GROUP BY k.id_karyawan, k.nama, k.jabatan
            ORDER BY k.nama";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $rekap = [];
    while($row = oci_fetch_assoc($stmt)) $rekap[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Absensi - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-white">
            <h4><i class="fas fa-chart-line"></i> Rekap Absensi Bulanan</h4>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label>Bulan</label>
                    <input type="month" name="bulan" class="form-control" value="<?= $bulan ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr><th>Nama</th><th>Jabatan</th><th>Hadir</th><th>Terlambat</th><th>Sakit</th><th>Izin</th><th>Alpha</th><th>Total</th><th>% Kehadiran</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($rekap as $r): 
                            $persen = $r['TOTAL'] > 0 ? round(($r['HADIR'] / $r['TOTAL']) * 100) : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($r['NAMA']) ?></td>
                            <td><?= $r['JABATAN'] ?></td>
                            <td><span class="badge bg-success"><?= $r['HADIR'] ?></span></td>
                            <td><span class="badge bg-warning"><?= $r['TERLAMBAT'] ?></span></td>
                            <td><span class="badge bg-info"><?= $r['SAKIT'] ?></span></td>
                            <td><span class="badge bg-secondary"><?= $r['IZIN'] ?></span></td>
                            <td><span class="badge bg-danger"><?= $r['ALPHA'] ?></span></td>
                            <td><?= $r['TOTAL'] ?></td>
                            <td><?= $persen ?>%</td>
                        ?</tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
    </div>
</div>
</body>
</html>
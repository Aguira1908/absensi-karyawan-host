<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../../assets/config/koneksi.php';

$karyawan = [];
$error = '';

// Ambil data karyawan berdasarkan tipe koneksi
if($conn_type == 'pdo' || $conn_type == 'mysql') {
    // MySQL / PDO
    try {
        $stmt = $conn->query("SELECT 
                                id_karyawan, 
                                nama, 
                                jabatan, 
                                no_hp, 
                                email, 
                                alamat, 
                                status, 
                                gaji_pokok 
                              FROM karyawan 
                              ORDER BY id_karyawan");
        $karyawan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
} else if ($conn_type == 'oci8') {
    // Oracle OCI8
    try {
        $sql = "SELECT 
                    ID_KARYAWAN as id_karyawan, 
                    NAMA as nama, 
                    JABATAN as jabatan, 
                    NO_HP as no_hp, 
                    EMAIL as email, 
                    ALAMAT as alamat, 
                    STATUS as status, 
                    GAJI_POKOK as gaji_pokok 
                FROM karyawan 
                ORDER BY ID_KARYAWAN";
        $stmt = oci_parse($conn, $sql);
        oci_execute($stmt);
        
        while($row = oci_fetch_assoc($stmt)) {
            $karyawan[] = [
                'id_karyawan' => $row['ID_KARYAWAN'] ?? null,
                'nama' => $row['NAMA'] ?? '-',
                'jabatan' => $row['JABATAN'] ?? '-',
                'no_hp' => $row['NO_HP'] ?? '-',
                'email' => $row['EMAIL'] ?? '-',
                'alamat' => $row['ALAMAT'] ?? '-',
                'status' => $row['STATUS'] ?? 'Nonaktif',
                'gaji_pokok' => $row['GAJI_POKOK'] ?? 0
            ];
        }
        oci_free_statement($stmt);
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Hitung statistik
$total_karyawan = count($karyawan);
$total_gaji = 0;
$karyawan_aktif = 0;
foreach($karyawan as $k) {
    $total_gaji += (float)($k['gaji_pokok'] ?? 0);
    if(($k['status'] ?? '') == 'Aktif') $karyawan_aktif++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Karyawan - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h4><i class="fas fa-users"></i> Laporan Data Karyawan</h4>
            <small>Per tanggal: <?= date('d-m-Y H:i:s') ?></small>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="mb-3 no-print">
                <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print</button>
                <a href="../dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>No HP</th>
                            <th>Email</th>
                            <th>Alamat</th>
                            <th>Status</th>
                            <th>Gaji Pokok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($karyawan)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-folder-open fa-2x text-muted mb-2 d-block"></i>
                                    Belum ada data karyawan
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($karyawan as $k): ?>
                                <?php 
                                    $status = $k['status'] ?? 'Nonaktif';
                                    $status_badge = ($status == 'Aktif') ? 'bg-success' : 'bg-danger';
                                    $gaji = 'Rp ' . number_format($k['gaji_pokok'] ?? 0, 0, ',', '.');
                                ?>
                                <tr>
                                    <td><?= $k['id_karyawan'] ?></td>
                                    <td><?= htmlspecialchars($k['nama'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($k['jabatan'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($k['no_hp'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($k['email'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($k['alamat'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $status_badge ?>"><?= $status ?></span>
                                    </td>
                                    <td class="text-end"><?= $gaji ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if(!empty($karyawan)): ?>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="7" class="text-end"><strong>Total Karyawan:</strong></td>
                            <td class="text-end"><strong><?= $total_karyawan ?> orang</strong></td>
                        </tr>
                        <tr>
                            <td colspan="7" class="text-end"><strong>Karyawan Aktif:</strong></td>
                            <td class="text-end"><strong><?= $karyawan_aktif ?> orang</strong></td>
                        </tr>
                        <tr>
                            <td colspan="7" class="text-end"><strong>Total Gaji Pokok:</strong></td>
                            <td class="text-end"><strong>Rp <?= number_format($total_gaji, 0, ',', '.') ?></strong></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../../assets/config/koneksi.php';

$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Query data per bulan
$data_bulanan = [];
for($i=1; $i<=12; $i++) {
    $bulan = sprintf("%02d", $i);
    
    if($conn_type == 'pdo') {
        $stmt = $conn->prepare("SELECT 
                                   COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as hadir,
                                   COUNT(CASE WHEN a.status = 'Terlambat' THEN 1 END) as terlambat,
                                   COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as sakit,
                                   COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as izin,
                                   COUNT(CASE WHEN a.status = 'Alpha' THEN 1 END) as alpha,
                                   COUNT(a.id_absensi) as total_absensi
                                FROM absensi a
                                WHERE EXTRACT(MONTH FROM a.tanggal) = :bulan 
                                AND EXTRACT(YEAR FROM a.tanggal) = :tahun");
        $stmt->execute([':bulan' => $i, ':tahun' => $tahun]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT 
                   SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                   SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) as terlambat,
                   SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                   SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) as izin,
                   SUM(CASE WHEN a.status = 'Alpha' THEN 1 ELSE 0 END) as alpha,
                   COUNT(a.id_absensi) as total_absensi
                FROM absensi a
                WHERE EXTRACT(MONTH FROM a.tanggal) = $i 
                AND EXTRACT(YEAR FROM a.tanggal) = $tahun";
        $stmt = oci_parse($conn, $sql);
        oci_execute($stmt);
        $data = oci_fetch_assoc($stmt);
    }
    
    $total_hadir_efektif = ($data['HADIR'] ?? 0) + ($data['TERLAMBAT'] ?? 0);
    $persen_kehadiran = $data['TOTAL_ABSENSI'] > 0 ? round(($total_hadir_efektif / $data['TOTAL_ABSENSI']) * 100, 2) : 0;
    
    $data_bulanan[$bulan] = [
        'hadir' => $data['HADIR'] ?? 0,
        'terlambat' => $data['TERLAMBAT'] ?? 0,
        'sakit' => $data['SAKIT'] ?? 0,
        'izin' => $data['IZIN'] ?? 0,
        'alpha' => $data['ALPHA'] ?? 0,
        'total_absensi' => $data['TOTAL_ABSENSI'] ?? 0,
        'persen_kehadiran' => $persen_kehadiran
    ];
}

// Header Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Tahunan_{$tahun}.xls");

echo "<table border='1'>";
echo "<tr><th colspan='8' align='center'>LAPORAN RINGKASAN TAHUNAN {$tahun}</th></tr>";
echo "<tr><th>Bulan</th><th>Hadir</th><th>Terlambat</th><th>Sakit</th><th>Izin</th><th>Alpha</th><th>Total Absensi</th><th>% Kehadiran</th></tr>";

foreach($data_bulanan as $bulan => $d) {
    echo "<tr>";
    echo "<td>{$nama_bulan[$bulan]}</td>";
    echo "<td>{$d['hadir']}</td>";
    echo "<td>{$d['terlambat']}</td>";
    echo "<td>{$d['sakit']}</td>";
    echo "<td>{$d['izin']}</td>";
    echo "<td>{$d['alpha']}</td>";
    echo "<td>{$d['total_absensi']}</td>";
    echo "<td>{$d['persen_kehadiran']}%</td>";
    echo "</tr>";
}

// Total
$total_hadir = array_sum(array_column($data_bulanan, 'hadir'));
$total_terlambat = array_sum(array_column($data_bulanan, 'terlambat'));
$total_sakit = array_sum(array_column($data_bulanan, 'sakit'));
$total_izin = array_sum(array_column($data_bulanan, 'izin'));
$total_alpha = array_sum(array_column($data_bulanan, 'alpha'));
$total_absensi = array_sum(array_column($data_bulanan, 'total_absensi'));
$rata_persen = $total_absensi > 0 ? round((($total_hadir + $total_terlambat) / $total_absensi) * 100, 2) : 0;

echo "<tr style='background:#f0f0f0;font-weight:bold'>";
echo "<td>TOTAL</td>";
echo "<td>{$total_hadir}</td>";
echo "<td>{$total_terlambat}</td>";
echo "<td>{$total_sakit}</td>";
echo "<td>{$total_izin}</td>";
echo "<td>{$total_alpha}</td>";
echo "<td>{$total_absensi}</td>";
echo "<td>{$rata_persen}%</td>";
echo "</tr>";

echo "</table>";
exit();
?>
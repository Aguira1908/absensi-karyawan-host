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
$jabatan_filter = isset($_GET['jabatan']) ? $_GET['jabatan'] : '';

$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

if($conn_type == 'pdo') {
    $sql = "SELECT k.id_karyawan, k.nama, k.jabatan, k.no_hp, k.email,
                   COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as hadir,
                   COUNT(CASE WHEN a.status = 'Terlambat' THEN 1 END) as terlambat,
                   COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as sakit,
                   COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as izin,
                   COUNT(CASE WHEN a.status = 'Alpha' THEN 1 END) as alpha,
                   COUNT(a.id_absensi) as total_hari,
                   ROUND(COUNT(CASE WHEN a.status IN ('Hadir', 'Terlambat') THEN 1 END) / NULLIF(COUNT(a.id_absensi), 0) * 100, 2) as persen_kehadiran
            FROM karyawan k
            LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan 
                AND EXTRACT(MONTH FROM a.tanggal) = :bulan 
                AND EXTRACT(YEAR FROM a.tanggal) = :tahun
            WHERE k.status = 'Aktif'";
    
    if($jabatan_filter) $sql .= " AND k.jabatan = :jabatan";
    $sql .= " GROUP BY k.id_karyawan, k.nama, k.jabatan, k.no_hp, k.email ORDER BY k.nama";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':bulan', $bulan_num);
    $stmt->bindParam(':tahun', $tahun);
    if($jabatan_filter) $stmt->bindParam(':jabatan', $jabatan_filter);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $where = "";
    if($jabatan_filter) $where = " AND k.jabatan = '$jabatan_filter'";
    $sql = "SELECT k.id_karyawan, k.nama, k.jabatan, k.no_hp, k.email,
                   SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                   SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) as terlambat,
                   SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                   SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) as izin,
                   SUM(CASE WHEN a.status = 'Alpha' THEN 1 ELSE 0 END) as alpha,
                   COUNT(a.id_absensi) as total_hari,
                   ROUND(SUM(CASE WHEN a.status IN ('Hadir', 'Terlambat') THEN 1 ELSE 0 END) / NULLIF(COUNT(a.id_absensi), 0) * 100, 2) as persen_kehadiran
            FROM karyawan k
            LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan 
                AND EXTRACT(MONTH FROM a.tanggal) = $bulan_num 
                AND EXTRACT(YEAR FROM a.tanggal) = $tahun
            WHERE k.status = 'Aktif' $where
            GROUP BY k.id_karyawan, k.nama, k.jabatan, k.no_hp, k.email
            ORDER BY k.nama";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $data = [];
    while($row = oci_fetch_assoc($stmt)) $data[] = $row;
}

// Header Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Absensi_{$nama_bulan[$bulan_num]}_{$tahun}.xls");

echo "<table border='1'>";
echo "<tr><th colspan='11' align='center'>LAPORAN ABSENSI KARYAWAN</th></tr>";
echo "<tr><th colspan='11' align='center'>Periode: " . $nama_bulan[$bulan_num] . " " . $tahun . "</th></tr>";
echo "<tr><th>No</th><th>Nama Karyawan</th><th>Jabatan</th><th>No HP</th><th>Email</th><th>Hadir</th><th>Terlambat</th><th>Sakit</th><th>Izin</th><th>Alpha</th><th>% Kehadiran</th></tr>";

$no = 1;
foreach($data as $row) {
    echo "<tr>";
    echo "<td>{$no}</td>";
    echo "<td>" . htmlspecialchars($row['NAMA']) . "</td>";
    echo "<td>{$row['JABATAN']}</td>";
    echo "<td>{$row['NO_HP']}</td>";
    echo "<td>{$row['EMAIL']}</td>";
    echo "<td>{$row['HADIR']}</td>";
    echo "<td>{$row['TERLAMBAT']}</td>";
    echo "<td>{$row['SAKIT']}</td>";
    echo "<td>{$row['IZIN']}</td>";
    echo "<td>{$row['ALPHA']}</td>";
    echo "<td>{$row['PERSEN_KEHADIRAN']}%</td>";
    echo "</tr>";
    $no++;
}

echo "</table>";
exit();
?>
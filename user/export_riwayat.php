<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

require_once '../assets/config/koneksi.php';

$id_karyawan = $_SESSION['id_karyawan'];
$bulan_filter = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$tahun = substr($bulan_filter, 0, 4);
$bulan = substr($bulan_filter, 5, 2);

$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Query data
if($conn_type == 'pdo') {
    $where = "a.id_karyawan = :id";
    $params = [':id' => $id_karyawan];
    if($bulan_filter) {
        $where .= " AND EXTRACT(MONTH FROM a.tanggal) = :bulan AND EXTRACT(YEAR FROM a.tanggal) = :tahun";
        $params[':bulan'] = $bulan;
        $params[':tahun'] = $tahun;
    }
    if($status_filter) {
        $where .= " AND a.status = :status";
        $params[':status'] = $status_filter;
    }
    
    $stmt = $conn->prepare("SELECT a.*, k.nama as nama_karyawan, k.jabatan 
                           FROM absensi a 
                           JOIN karyawan k ON a.id_karyawan = k.id_karyawan 
                           WHERE $where 
                           ORDER BY a.tanggal DESC");
    foreach($params as $key => &$val) $stmt->bindParam($key, $val);
    $stmt->execute();
    $absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $where = "a.id_karyawan = $id_karyawan";
    if($bulan_filter) $where .= " AND EXTRACT(MONTH FROM a.tanggal) = $bulan AND EXTRACT(YEAR FROM a.tanggal) = $tahun";
    if($status_filter) $where .= " AND a.status = '$status_filter'";
    
    $sql = "SELECT a.*, k.nama as nama_karyawan, k.jabatan 
            FROM absensi a 
            JOIN karyawan k ON a.id_karyawan = k.id_karyawan 
            WHERE $where 
            ORDER BY a.tanggal DESC";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $absensi = [];
    while($row = oci_fetch_assoc($stmt)) $absensi[] = $row;
}

// Header Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Riwayat_Absensi_{$_SESSION['nama']}_{$nama_bulan[$bulan]}_{$tahun}.xls");

echo "<h2 align='center'>RIWAYAT ABSENSI</h2>";
echo "<h3 align='center'>Nama: {$_SESSION['nama']}</h3>";
echo "<h3 align='center'>Periode: {$nama_bulan[$bulan]} {$tahun}</h3>";
echo "<table border='1'>";
echo "<tr>
        <th>No</th>
        <th>Tanggal</th>
        <th>Hari</th>
        <th>Jam Masuk</th>
        <th>Jam Keluar</th>
        <th>Status</th>
        <th>Terlambat(menit)</th>
        <th>Alamat</th>
      </tr>";

$no = 1;
$hari_indonesia = ['Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu','Sunday'=>'Minggu'];

foreach($absensi as $a) {
    $tgl = strtotime($a['TANGGAL']);
    $hari = $hari_indonesia[date('l', $tgl)] ?? date('l', $tgl);
    
    echo "<tr>";
    echo "<td>{$no}</td>";
    echo "<td>".date('d-m-Y', $tgl)."</td>";
    echo "<td>{$hari}</td>";
    echo "<td>".($a['JAM_MASUK'] ? date('H:i:s', strtotime($a['JAM_MASUK'])) : '-')."</td>";
    echo "<td>".($a['JAM_KELUAR'] ? date('H:i:s', strtotime($a['JAM_KELUAR'])) : '-')."</td>";
    echo "<td>{$a['STATUS']}</td>";
    echo "<td>{$a['TERLAMBAT']}</td>";
    echo "<td>".substr($a['ALAMAT_MASUK'] ?? '-', 0, 100)."</td>";
    echo "</tr>";
    $no++;
}

echo "</table>";
exit();
?>
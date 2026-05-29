<?php
// Konfigurasi database Oracle
$host = 'localhost';
$port = '1521';
$service_name = ''; // Ganti dengan 'XEPDB1' jika perlu
$username = 'SYSTEM';
$password = '123456';

$conn = null;
$conn_type = null;
$error_message = '';

// Cek extension PDO_OCI
if (extension_loaded('pdo_oci')) {
    try {
        $conn = new PDO("oci:dbname=//$host:$port/$service_name;charset=UTF8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $conn_type = 'pdo';
    } catch(PDOException $e) {
        $error_message = $e->getMessage();
    }
}

// Jika PDO gagal, coba OCI8
if ($conn === null && extension_loaded('oci8')) {
    $conn = oci_connect($username, $password, "//$host:$port/$service_name", 'AL32UTF8');
    if ($conn) {
        $conn_type = 'oci8';
        $error_message = '';
    } else {
        $e = oci_error();
        $error_message = $e['message'] ?? 'Unknown error';
    }
}

// Fungsi format tanggal
function formatTanggal($tanggal) {
    if(!$tanggal) return '-';
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $tgl = date('j', strtotime($tanggal));
    $bln = $bulan[(int)date('n', strtotime($tanggal))];
    $thn = date('Y', strtotime($tanggal));
    return "$tgl $bln $thn";
}

function formatJam($datetime) {
    if(!$datetime) return '-';
    return date('H:i:s', strtotime($datetime));
}

function formatRupiah($angka) {
    if(!$angka) return 'Rp 0';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function hitungTerlambat($jam_masuk) {
    if(!$jam_masuk) return 0;
    $batas_normal = strtotime(date('Y-m-d') . ' 08:00:00');
    $jam_masuk_time = strtotime($jam_masuk);
    if($jam_masuk_time > $batas_normal) {
        return round(($jam_masuk_time - $batas_normal) / 60);
    }
    return 0;
}
?>


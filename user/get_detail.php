<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    exit('Akses ditolak');
}

require_once '../assets/config/koneksi.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_karyawan = $_SESSION['id_karyawan'];

if($conn_type == 'pdo') {
    $stmt = $conn->prepare("SELECT a.* FROM absensi a WHERE a.id_absensi = :id AND a.id_karyawan = :karyawan");
    $stmt->execute([':id' => $id, ':karyawan' => $id_karyawan]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = oci_parse($conn, "SELECT * FROM absensi WHERE id_absensi = :id AND id_karyawan = :karyawan");
    oci_bind_by_name($stmt, ':id', $id);
    oci_bind_by_name($stmt, ':karyawan', $id_karyawan);
    oci_execute($stmt);
    $data = oci_fetch_assoc($stmt);
}

if(!$data) {
    echo '<div class="alert alert-danger">Data tidak ditemukan</div>';
    exit();
}
?>
<div class="row">
    <div class="col-md-6">
        <table class="table table-borderless">
            <tr><th width="35%">Tanggal</th><td>: <?= date('d-m-Y', strtotime($data['TANGGAL'])) ?></td></tr>
            <tr><th>Jam Masuk</th><td>: <?= $data['JAM_MASUK'] ? date('H:i:s', strtotime($data['JAM_MASUK'])) : '-' ?></td></tr>
            <tr><th>Jam Keluar</th><td>: <?= $data['JAM_KELUAR'] ? date('H:i:s', strtotime($data['JAM_KELUAR'])) : '-' ?></td></tr>
            <tr><th>Status</th><td>: <?= $data['STATUS'] ?></td></tr>
            <tr><th>Terlambat</th><td>: <?= $data['TERLAMBAT'] ?> menit</td></tr>
            <tr><th>Keterangan</th><td>: <?= nl2br(htmlspecialchars($data['KETERANGAN'] ?? '-')) ?></td></tr>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-borderless">
            <tr><th width="35%">Device Info</th><td>: <?= $data['DEVICE_INFO'] ?? '-' ?></td></tr>
            <tr><th>IP Address</th><td>: <?= $data['IP_ADDRESS'] ?? '-' ?></td></tr>
            <tr><th>Latitude</th><td>: <?= $data['LATITUDE_MASUK'] ?? '-' ?></td></tr>
            <tr><th>Longitude</th><td>: <?= $data['LONGITUDE_MASUK'] ?? '-' ?></td></tr>
            <tr><th>Alamat</th><td>: <small><?= $data['ALAMAT_MASUK'] ?? '-' ?></small></td></tr>
        </table>
    </div>
</div>
<?php if($data['FOTO_MASUK']): ?>
<div class="row">
    <div class="col-md-6 text-center">
        <h6>Foto Masuk</h6>
        <img src="../<?= $data['FOTO_MASUK'] ?>" style="max-width:100%;border-radius:10px;max-height:250px">
    </div>
    <?php if($data['FOTO_PULANG']): ?>
    <div class="col-md-6 text-center">
        <h6>Foto Pulang</h6>
        <img src="../<?= $data['FOTO_PULANG'] ?>" style="max-width:100%;border-radius:10px;max-height:250px">
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
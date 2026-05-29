<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../../assets/config/koneksi.php';

$id = $_GET['id'] ?? 0;

// Fungsi untuk konversi format tanggal Oracle ke string PHP
function oracleDateToPHP($oracle_date) {
    if(empty($oracle_date)) return null;
    
    if(is_string($oracle_date)) {
        $timestamp = strtotime($oracle_date);
        if($timestamp !== false && $timestamp > 0) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        $cleaned = preg_replace('/\.(\d+)\s+(AM|PM)/i', ' $2', $oracle_date);
        $cleaned = str_replace('.', ':', $cleaned);
        $cleaned = preg_replace('/:\d{6}/', '', $cleaned);
        
        $timestamp = strtotime($cleaned);
        if($timestamp !== false && $timestamp > 0) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        $patterns = [
            '/(\d{2})-([A-Z]{3})-(\d{2})\s+(\d{2})\.(\d{2})\.(\d{2})\.\d+\s+(AM|PM)/i',
            '/(\d{2})-([A-Z]{3})-(\d{2})\s+(\d{2})\.(\d{2})\.(\d{2})\s+(AM|PM)/i'
        ];
        
        foreach($patterns as $pattern) {
            if(preg_match($pattern, $oracle_date, $matches)) {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];
                $hour = $matches[4];
                $minute = $matches[5];
                $second = $matches[6];
                $ampm = strtoupper($matches[7] ?? '');
                
                $months = [
                    'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
                    'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
                    'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12'
                ];
                $month_num = $months[strtoupper($month)] ?? '01';
                $year_full = ($year < 70) ? '20' . $year : '19' . $year;
                
                if($ampm == 'PM' && $hour < 12) $hour += 12;
                if($ampm == 'AM' && $hour == 12) $hour = 0;
                
                $hour = str_pad($hour, 2, '0', STR_PAD_LEFT);
                
                return $year_full . '-' . $month_num . '-' . $day . ' ' . $hour . ':' . $minute . ':' . $second;
            }
        }
    }
    
    if(is_object($oracle_date)) {
        if(method_exists($oracle_date, 'load')) {
            $oracle_date = $oracle_date->load();
        } elseif(method_exists($oracle_date, 'read')) {
            $oracle_date = $oracle_date->read($oracle_date->size());
        } else {
            $oracle_date = (string)$oracle_date;
        }
        return oracleDateToPHP($oracle_date);
    }
    
    return null;
}

// Fungsi untuk membaca CLOB/LOB dengan aman
function getClobValue($field) {
    if(empty($field)) return '';
    if(is_string($field)) return $field;
    if(is_object($field)) {
        if(method_exists($field, 'load')) {
            return $field->load();
        } elseif(method_exists($field, 'read')) {
            return $field->read($field->size());
        } else {
            return (string)$field;
        }
    }
    return '';
}

// Fungsi untuk format tanggal tampilan
function formatTanggalDisplay($date_str) {
    if(empty($date_str)) return '-';
    $timestamp = strtotime($date_str);
    if($timestamp !== false && $timestamp > 0) {
        return date('d-m-Y', $timestamp);
    }
    return '-';
}

// Fungsi untuk format jam tampilan
function formatJamDisplay($date_str) {
    if(empty($date_str)) return '-';
    $timestamp = strtotime($date_str);
    if($timestamp !== false && $timestamp > 0) {
        return date('H:i:s', $timestamp);
    }
    return '-';
}

if($conn_type == 'pdo') {
    // PDO MySQL
    $stmt = $conn->prepare("SELECT a.*, k.nama as nama_karyawan, k.jabatan, k.no_hp, k.email 
                           FROM absensi a 
                           JOIN karyawan k ON a.id_karyawan = k.id_karyawan 
                           WHERE a.id_absensi = :id");
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // OCI8 Oracle - Gunakan TO_CHAR untuk konversi langsung
    $sql = "SELECT 
                a.ID_ABSENSI,
                a.ID_KARYAWAN,
                TO_CHAR(a.TANGGAL, 'YYYY-MM-DD') as TANGGAL_STR,
                TO_CHAR(a.JAM_MASUK, 'YYYY-MM-DD HH24:MI:SS') as JAM_MASUK_STR,
                TO_CHAR(a.JAM_KELUAR, 'YYYY-MM-DD HH24:MI:SS') as JAM_KELUAR_STR,
                a.STATUS,
                a.TERLAMBAT,
                a.KETERANGAN,
                a.FOTO_MASUK,
                a.FOTO_PULANG,
                a.LATITUDE,
                a.LONGITUDE,
                a.LATITUDE_PULANG,
                a.LONGITUDE_PULANG,
                a.ALAMAT_MASUK,
                a.ALAMAT_PULANG,
                a.TOTAL_JAM,
                a.JAM_LEMBUR,
                a.DEVICE_INFO,
                a.IP_ADDRESS,
                k.nama as nama_karyawan,
                k.jabatan,
                k.no_hp,
                k.email
            FROM absensi a 
            JOIN karyawan k ON a.id_karyawan = k.id_karyawan 
            WHERE a.id_absensi = :id";
    
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $data = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    
    if($data) {
        // Konversi tanggal dari string hasil TO_CHAR
        if(isset($data['TANGGAL_STR'])) {
            $data['TANGGAL'] = $data['TANGGAL_STR'];
            unset($data['TANGGAL_STR']);
        }
        if(isset($data['JAM_MASUK_STR'])) {
            $data['JAM_MASUK'] = $data['JAM_MASUK_STR'];
            unset($data['JAM_MASUK_STR']);
        }
        if(isset($data['JAM_KELUAR_STR'])) {
            $data['JAM_KELUAR'] = $data['JAM_KELUAR_STR'];
            unset($data['JAM_KELUAR_STR']);
        }
        
        // Konversi CLOB ke string
        if(isset($data['KETERANGAN'])) {
            $data['KETERANGAN'] = getClobValue($data['KETERANGAN']);
        }
        if(isset($data['ALAMAT_MASUK'])) {
            $data['ALAMAT_MASUK'] = getClobValue($data['ALAMAT_MASUK']);
        }
        if(isset($data['ALAMAT_PULANG'])) {
            $data['ALAMAT_PULANG'] = getClobValue($data['ALAMAT_PULANG']);
        }
    }
}

if(!$data) {
    header("Location: index.php");
    exit();
}

// Format tanggal dan jam dengan aman
$tanggal_display = formatTanggalDisplay($data['TANGGAL'] ?? '');
$jam_masuk_display = formatJamDisplay($data['JAM_MASUK'] ?? '');
$jam_keluar_display = formatJamDisplay($data['JAM_KELUAR'] ?? '');

// Ambil nilai dengan default
$nama_karyawan = $data['NAMA_KARYAWAN'] ?? $data['nama_karyawan'] ?? '-';
$jabatan = $data['JABATAN'] ?? $data['jabatan'] ?? '-';
$status = $data['STATUS'] ?? $data['status'] ?? '-';
$terlambat = $data['TERLAMBAT'] ?? $data['terlambat'] ?? 0;
$total_jam = isset($data['TOTAL_JAM']) ? round((float)$data['TOTAL_JAM'], 1) : '-';
$jam_lembur = isset($data['JAM_LEMBUR']) && $data['JAM_LEMBUR'] > 0 ? round((float)$data['JAM_LEMBUR'], 1) . ' jam' : '-';
$keterangan = $data['KETERANGAN'] ?? '-';
$device_info = $data['DEVICE_INFO'] ?? '-';
$ip_address = $data['IP_ADDRESS'] ?? '-';
$latitude_masuk = $data['LATITUDE'] ?? $data['latitude'] ?? '-';
$longitude_masuk = $data['LONGITUDE'] ?? $data['longitude'] ?? '-';
$alamat_masuk = $data['ALAMAT_MASUK'] ?? '-';
$latitude_pulang = $data['LATITUDE_PULANG'] ?? '-';
$longitude_pulang = $data['LONGITUDE_PULANG'] ?? '-';
$alamat_pulang = $data['ALAMAT_PULANG'] ?? '-';
$foto_masuk = $data['FOTO_MASUK'] ?? $data['foto_masuk'] ?? '';
$foto_pulang = $data['FOTO_PULANG'] ?? $data['foto_pulang'] ?? '';

// Status badge
function getStatusBadge($status) {
    $badges = [
        'Hadir' => '<span class="badge bg-success">Hadir</span>',
        'Terlambat' => '<span class="badge bg-warning text-dark">Terlambat</span>',
        'Sakit' => '<span class="badge bg-info">Sakit</span>',
        'Izin' => '<span class="badge bg-secondary">Izin</span>',
        'Alpha' => '<span class="badge bg-danger">Alpha</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Absensi - Admin Ck-Ck Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .detail-container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .card-detail { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .card-header { background: linear-gradient(135deg, #2C1810, #C8A951); color: white; padding: 20px 25px; }
        .card-header h3 { margin: 0; font-size: 1.5rem; }
        .card-body { padding: 25px; }
        .info-section { margin-bottom: 25px; }
        .info-section h5 { color: #2C1810; border-bottom: 2px solid #C8A951; padding-bottom: 8px; margin-bottom: 15px; font-size: 1rem; }
        .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .info-label { width: 180px; font-weight: 600; color: #555; }
        .info-value { flex: 1; color: #333; }
        .foto-container { display: flex; gap: 20px; margin-top: 15px; flex-wrap: wrap; }
        .foto-item { text-align: center; }
        .foto-item img { width: 200px; height: 200px; object-fit: cover; border-radius: 10px; border: 2px solid #ddd; cursor: pointer; transition: transform 0.2s; }
        .foto-item img:hover { transform: scale(1.05); }
        .foto-item p { margin-top: 8px; font-size: 0.8rem; color: #666; }
        .btn-back { background: #6c757d; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-back:hover { background: #5a6268; color: white; }
        .btn-edit { background: #C8A951; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-left: 10px; }
        .btn-edit:hover { background: #b89a3e; color: white; }
        @media (max-width: 768px) {
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 5px; }
            .foto-item img { width: 150px; height: 150px; }
        }
    </style>
</head>
<body>
<div class="detail-container">
    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Monitoring</a>
        <a href="edit.php?id=<?= $id ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit Data</a>
    </div>

    <div class="card-detail">
        <div class="card-header">
            <h3><i class="fas fa-fingerprint"></i> Detail Absensi</h3>
            <p class="mb-0 opacity-75">Informasi lengkap data kehadiran karyawan</p>
        </div>
        <div class="card-body">
            <!-- Informasi Karyawan -->
            <div class="info-section">
                <h5><i class="fas fa-user-circle"></i> Informasi Karyawan</h5>
                <div class="info-row">
                    <div class="info-label">Nama Karyawan</div>
                    <div class="info-value">: <?= htmlspecialchars($nama_karyawan) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Jabatan</div>
                    <div class="info-value">: <?= htmlspecialchars($jabatan) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">No. HP</div>
                    <div class="info-value">: <?= htmlspecialchars($data['NO_HP'] ?? $data['no_hp'] ?? '-') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value">: <?= htmlspecialchars($data['EMAIL'] ?? $data['email'] ?? '-') ?></div>
                </div>
            </div>

            <!-- Informasi Absensi -->
            <div class="info-section">
                <h5><i class="fas fa-calendar-alt"></i> Informasi Absensi</h5>
                <div class="info-row">
                    <div class="info-label">Tanggal</div>
                    <div class="info-value">: <?= $tanggal_display ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Jam Masuk</div>
                    <div class="info-value">: <?= $jam_masuk_display ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Jam Keluar</div>
                    <div class="info-value">: <?= $jam_keluar_display ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Total Jam Kerja</div>
                    <div class="info-value">: <?= $total_jam ?> jam</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Jam Lembur</div>
                    <div class="info-value">: <?= $jam_lembur ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status</div>
                    <div class="info-value">: <?= getStatusBadge($status) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Terlambat</div>
                    <div class="info-value">: <?= $terlambat > 0 ? $terlambat . ' menit' : '-' ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Keterangan</div>
                    <div class="info-value">: <?= $keterangan != '-' ? nl2br(htmlspecialchars($keterangan)) : '-' ?></div>
                </div>
            </div>

            <!-- Informasi Lokasi Masuk -->
            <div class="info-section">
                <h5><i class="fas fa-map-marker-alt"></i> Lokasi Masuk</h5>
                <div class="info-row">
                    <div class="info-label">Latitude</div>
                    <div class="info-value">: <?= htmlspecialchars($latitude_masuk) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Longitude</div>
                    <div class="info-value">: <?= htmlspecialchars($longitude_masuk) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Alamat</div>
                    <div class="info-value">: <?= $alamat_masuk != '-' ? nl2br(htmlspecialchars($alamat_masuk)) : '-' ?></div>
                </div>
            </div>

            <!-- Informasi Lokasi Pulang (jika ada) -->
            <?php if($latitude_pulang != '-' && !empty($latitude_pulang)): ?>
            <div class="info-section">
                <h5><i class="fas fa-map-marked-alt"></i> Lokasi Pulang</h5>
                <div class="info-row">
                    <div class="info-label">Latitude</div>
                    <div class="info-value">: <?= htmlspecialchars($latitude_pulang) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Longitude</div>
                    <div class="info-value">: <?= htmlspecialchars($longitude_pulang) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Alamat</div>
                    <div class="info-value">: <?= $alamat_pulang != '-' ? nl2br(htmlspecialchars($alamat_pulang)) : '-' ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Foto Absensi -->
            <?php if(!empty($foto_masuk)): ?>
            <div class="info-section">
                <h5><i class="fas fa-camera"></i> Foto Absensi</h5>
                <div class="foto-container">
                    <div class="foto-item">
                        <img src="../../<?= htmlspecialchars($foto_masuk) ?>" onclick="showFoto('../../<?= htmlspecialchars($foto_masuk) ?>')" alt="Foto Masuk">
                        <p><i class="fas fa-sign-in-alt"></i> Foto Masuk</p>
                    </div>
                    <?php if(!empty($foto_pulang)): ?>
                    <div class="foto-item">
                        <img src="../../<?= htmlspecialchars($foto_pulang) ?>" onclick="showFoto('../../<?= htmlspecialchars($foto_pulang) ?>')" alt="Foto Pulang">
                        <p><i class="fas fa-sign-out-alt"></i> Foto Pulang</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Informasi Perangkat -->
            <?php if($device_info != '-' || $ip_address != '-'): ?>
            <div class="info-section">
                <h5><i class="fas fa-laptop"></i> Informasi Perangkat</h5>
                <div class="info-row">
                    <div class="info-label">Device Info</div>
                    <div class="info-value">: <?= htmlspecialchars($device_info) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">IP Address</div>
                    <div class="info-value">: <?= htmlspecialchars($ip_address) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Foto -->
<div class="modal fade" id="fotoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-camera"></i> Foto Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalFoto" src="" style="max-width:100%; max-height:70vh; border-radius: 10px;">
            </div>
        </div>
    </div>
</div>

<script>
    function showFoto(src) {
        document.getElementById('modalFoto').src = src;
        new bootstrap.Modal(document.getElementById('fotoModal')).show();
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
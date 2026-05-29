<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// Cek apakah sudah login sebagai user
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../assets/config/koneksi.php';

$today = date('Y-m-d');
// PERBAIKAN: Gunakan user_id sebagai id_karyawan
$id_karyawan = $_SESSION['user_id']; // Ini yang benar
$current_time = date('H:i:s');
$message = '';
$error = '';
$absen_data = null;
$is_already_absen = false;

// Debug - cek id_karyawan
error_log("ID Karyawan: " . $id_karyawan);

// ... (sisa kode tetap sama sampai fungsi formatJamIndonesia)

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

function formatJamIndonesia($oracle_date) {
    $php_date = oracleDateToPHP($oracle_date);
    if($php_date) {
        return date('H:i:s', strtotime($php_date));
    }
    return '-';
}

// AMBIL JADWAL YANG SUDAH DISETUJUI UNTUK HARI INI
if($conn && $conn_type) {
    try {
        // Ambil data karyawan
        if($conn_type == 'pdo' || $conn_type == 'mysql') {
            $stmt = $conn->prepare("SELECT * FROM karyawan WHERE id_karyawan = :id");
            $stmt->execute([':id' => $id_karyawan]);
            $karyawan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Ambil jadwal yang sudah disetujui untuk hari ini
            $stmt = $conn->prepare("SELECT * FROM jadwal WHERE id_karyawan = :id AND tanggal = :tanggal AND status = 'Disetujui'");
            $stmt->execute([':id' => $id_karyawan, ':tanggal' => $today]);
            $jadwal_hari_ini = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Cek absensi hari ini
            $stmt = $conn->prepare("SELECT * FROM absensi WHERE id_karyawan = :id AND DATE(tanggal) = :tanggal");
            $stmt->execute([':id' => $id_karyawan, ':tanggal' => $today]);
            $absen_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } else {
            // Oracle OCI8
            $sql_karyawan = "SELECT * FROM karyawan WHERE id_karyawan = :id_karyawan";
            $stmt = oci_parse($conn, $sql_karyawan);
            oci_bind_by_name($stmt, ':id_karyawan', $id_karyawan);
            oci_execute($stmt);
            $karyawan = oci_fetch_assoc($stmt);
            oci_free_statement($stmt);
            
            // Ambil jadwal yang sudah disetujui untuk hari ini
            $sql_jadwal = "SELECT * FROM jadwal WHERE id_karyawan = :id_karyawan AND TRUNC(tanggal) = TRUNC(SYSDATE) AND status = 'Disetujui'";
            $stmt_jadwal = oci_parse($conn, $sql_jadwal);
            oci_bind_by_name($stmt_jadwal, ':id_karyawan', $id_karyawan);
            oci_execute($stmt_jadwal);
            $jadwal_hari_ini = oci_fetch_assoc($stmt_jadwal);
            oci_free_statement($stmt_jadwal);
            
            // Cek absensi hari ini
            $sql_absen = "SELECT 
                            ID_ABSENSI, ID_KARYAWAN,
                            TO_CHAR(TANGGAL, 'YYYY-MM-DD') as TANGGAL_STR,
                            TO_CHAR(JAM_MASUK, 'YYYY-MM-DD HH24:MI:SS') as JAM_MASUK_STR,
                            TO_CHAR(JAM_KELUAR, 'YYYY-MM-DD HH24:MI:SS') as JAM_KELUAR_STR,
                            STATUS, TERLAMBAT, FOTO_MASUK, FOTO_PULANG,
                            LATITUDE, LONGITUDE, LATITUDE_PULANG, LONGITUDE_PULANG,
                            ALAMAT_MASUK, ALAMAT_PULANG, TOTAL_JAM, JAM_LEMBUR
                          FROM absensi 
                          WHERE id_karyawan = :id_karyawan AND TRUNC(tanggal) = TRUNC(SYSDATE)";
            $stmt_absen = oci_parse($conn, $sql_absen);
            oci_bind_by_name($stmt_absen, ':id_karyawan', $id_karyawan);
            oci_execute($stmt_absen);
            $absen_data = oci_fetch_assoc($stmt_absen);
            oci_free_statement($stmt_absen);
            
            if($absen_data) {
                $absen_data['TANGGAL'] = $absen_data['TANGGAL_STR'] ?? $today;
                $absen_data['JAM_MASUK'] = $absen_data['JAM_MASUK_STR'] ?? null;
                $absen_data['JAM_KELUAR'] = $absen_data['JAM_KELUAR_STR'] ?? null;
            }
        }
        
        // Set jam kerja dari jadwal yang disetujui
        if($jadwal_hari_ini) {
            $shift = $jadwal_hari_ini['SHIFT'];
            $jam_mulai = $jadwal_hari_ini['JAM_MULAI'] ?? '08:00:00';
            $jam_selesai = $jadwal_hari_ini['JAM_SELESAI'] ?? '17:00:00';
            $batas_normal = date('H:i:s', strtotime($jam_mulai) + (15 * 60));
        } else {
            $jam_mulai = '08:00:00';
            $jam_selesai = '17:00:00';
            $batas_normal = '08:15:00';
        }
        
        if($absen_data && is_array($absen_data) && isset($absen_data['JAM_MASUK']) && !empty($absen_data['JAM_MASUK'])) {
            $is_already_absen = true;
        }
        
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// Fungsi menyimpan foto base64
function saveBase64Image($base64, $type, $id_karyawan) {
    if(strpos($base64, 'base64,') !== false) {
        $base64_string = explode(',', $base64);
        $image_data = base64_decode($base64_string[1]);
    } else {
        $image_data = base64_decode($base64);
    }
    
    $dir = __DIR__ . '/../uploads/absensi/';
    if(!file_exists($dir)) mkdir($dir, 0777, true);
    
    $filename = date('Ymd_His') . '_' . $id_karyawan . '_' . $type . '.jpg';
    $filepath = $dir . $filename;
    file_put_contents($filepath, $image_data);
    
    return 'uploads/absensi/' . $filename;
}

// Proses absen masuk
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['absen_masuk'])) {
    $jam_masuk_now = date('Y-m-d H:i:s');
    $jam_masuk_time = date('H:i:s');
    $foto_masuk = $_POST['foto_masuk'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    
    // Cek apakah ada jadwal hari ini
    if(!$jadwal_hari_ini) {
        $error = "❌ Anda belum memiliki jadwal yang disetujui untuk hari ini. Silakan ajukan jadwal terlebih dahulu.";
    } else {
        // Hitung keterlambatan
        $terlambat = 0;
        $status = 'Hadir';
        
        if($jam_masuk_time > $batas_normal) {
            $batas_parts = explode(':', $batas_normal);
            $now_parts = explode(':', $jam_masuk_time);
            $batas_total_menit = ($batas_parts[0] * 60) + $batas_parts[1];
            $now_total_menit = ($now_parts[0] * 60) + $now_parts[1];
            $terlambat = $now_total_menit - $batas_total_menit;
            $status = 'Terlambat';
        }
        
        if(empty($foto_masuk)) {
            $error = "❌ Foto wajib diambil!";
        } elseif(empty($latitude) || empty($longitude)) {
            $error = "❌ Lokasi wajib didapatkan! Silakan aktifkan GPS.";
        } else {
            try {
                $foto_path = saveBase64Image($foto_masuk, 'masuk', $id_karyawan);
                
                if($conn_type == 'pdo' || $conn_type == 'mysql') {
                    $stmt = $conn->prepare("INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, status, terlambat, foto_masuk, latitude, longitude, alamat_masuk) 
                                           VALUES (:id, :tanggal, :jam_masuk, :status, :terlambat, :foto, :lat, :lng, :alamat)");
                    $result = $stmt->execute([
                        ':id' => $id_karyawan,
                        ':tanggal' => $today,
                        ':jam_masuk' => $jam_masuk_now,
                        ':status' => $status,
                        ':terlambat' => $terlambat,
                        ':foto' => $foto_path,
                        ':lat' => $latitude,
                        ':lng' => $longitude,
                        ':alamat' => $alamat
                    ]);
                    
                    if($result) {
                        $message = "✅ Absen masuk berhasil! " . ($terlambat > 0 ? "Anda terlambat {$terlambat} menit." : 'Tepat waktu.');
                        echo "<script>setTimeout(()=>{window.location.href='dashboard.php'},1500)</script>";
                    } else {
                        throw new Exception("Gagal insert ke database");
                    }
                } else {
                    // Oracle OCI8
                    $sql = "INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, status, terlambat, foto_masuk, latitude, longitude, alamat_masuk) 
                           VALUES (:id_karyawan, TO_DATE(:tanggal, 'YYYY-MM-DD'), TO_TIMESTAMP(:jam_masuk, 'YYYY-MM-DD HH24:MI:SS'), 
                           :status, :terlambat, :foto, :lat, :lng, :alamat)";
                    $stmt = oci_parse($conn, $sql);
                    oci_bind_by_name($stmt, ':id_karyawan', $id_karyawan);
                    oci_bind_by_name($stmt, ':tanggal', $today);
                    oci_bind_by_name($stmt, ':jam_masuk', $jam_masuk_now);
                    oci_bind_by_name($stmt, ':status', $status);
                    oci_bind_by_name($stmt, ':terlambat', $terlambat);
                    oci_bind_by_name($stmt, ':foto', $foto_path);
                    oci_bind_by_name($stmt, ':lat', $latitude);
                    oci_bind_by_name($stmt, ':lng', $longitude);
                    oci_bind_by_name($stmt, ':alamat', $alamat);
                    $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
                    oci_free_statement($stmt);
                    
                    if($result) {
                        $message = "✅ Absen masuk berhasil! " . ($terlambat > 0 ? "Anda terlambat {$terlambat} menit." : 'Tepat waktu.');
                        echo "<script>setTimeout(()=>{window.location.href='dashboard.php'},1500)</script>";
                    } else {
                        $e = oci_error($conn);
                        throw new Exception($e['message']);
                    }
                }
                
            } catch(Exception $e) {
                $error = "❌ Gagal absen: " . $e->getMessage();
            }
        }
    }
}

// Proses absen pulang
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['absen_pulang']) && $absen_data && is_array($absen_data)) {
    $jam_keluar_now = date('Y-m-d H:i:s');
    $foto_pulang = $_POST['foto_pulang'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    
    $jam_masuk_php = null;
    if(isset($absen_data['JAM_MASUK']) && !empty($absen_data['JAM_MASUK'])) {
        $jam_masuk_php = $absen_data['JAM_MASUK'];
        if(is_string($jam_masuk_php) && (strpos($jam_masuk_php, '.') !== false || strpos($jam_masuk_php, '-') !== false)) {
            $jam_masuk_php = oracleDateToPHP($jam_masuk_php);
        }
    }
    
    if($jam_masuk_php) {
        $start = new DateTime($jam_masuk_php);
        $end = new DateTime($jam_keluar_now);
        $diff = $start->diff($end);
        $total_jam = round($diff->h + ($diff->i / 60), 2);
        $is_lembur = $total_jam > 8;
        $jam_lembur = $is_lembur ? round($total_jam - 8, 2) : 0;
    } else {
        $total_jam = 0;
        $is_lembur = false;
        $jam_lembur = 0;
    }
    
    if(empty($foto_pulang)) {
        $error = "❌ Foto pulang wajib diambil!";
    } elseif(empty($latitude) || empty($longitude)) {
        $error = "❌ Lokasi wajib didapatkan! Silakan aktifkan GPS.";
    } else {
        try {
            $foto_path = saveBase64Image($foto_pulang, 'pulang', $id_karyawan);
            
            if($conn_type == 'pdo' || $conn_type == 'mysql') {
                $stmt = $conn->prepare("UPDATE absensi SET jam_keluar = :jam_keluar, foto_pulang = :foto, 
                                       latitude_pulang = :lat, longitude_pulang = :lng, alamat_pulang = :alamat,
                                       total_jam = :total_jam, jam_lembur = :jam_lembur 
                                       WHERE id_karyawan = :id AND DATE(tanggal) = :tanggal");
                $result = $stmt->execute([
                    ':jam_keluar' => $jam_keluar_now,
                    ':foto' => $foto_path,
                    ':lat' => $latitude,
                    ':lng' => $longitude,
                    ':alamat' => $alamat,
                    ':total_jam' => $total_jam,
                    ':jam_lembur' => $jam_lembur,
                    ':id' => $id_karyawan,
                    ':tanggal' => $today
                ]);
                
                if($result) {
                    $message = "✅ Absen pulang berhasil! Total kerja {$total_jam} jam" . ($is_lembur ? " (Lembur {$jam_lembur} jam)" : "");
                    echo "<script>setTimeout(()=>{window.location.href='dashboard.php'},1500)</script>";
                }
            } else {
                $sql = "UPDATE absensi SET 
                       jam_keluar = TO_TIMESTAMP(:jam_keluar, 'YYYY-MM-DD HH24:MI:SS'), 
                       foto_pulang = :foto, 
                       latitude_pulang = :lat, 
                       longitude_pulang = :lng, 
                       alamat_pulang = :alamat, 
                       total_jam = :total_jam, 
                       jam_lembur = :jam_lembur 
                       WHERE id_karyawan = :id_karyawan AND TRUNC(tanggal) = TRUNC(SYSDATE)";
                $stmt = oci_parse($conn, $sql);
                oci_bind_by_name($stmt, ':jam_keluar', $jam_keluar_now);
                oci_bind_by_name($stmt, ':foto', $foto_path);
                oci_bind_by_name($stmt, ':lat', $latitude);
                oci_bind_by_name($stmt, ':lng', $longitude);
                oci_bind_by_name($stmt, ':alamat', $alamat);
                oci_bind_by_name($stmt, ':total_jam', $total_jam);
                oci_bind_by_name($stmt, ':jam_lembur', $jam_lembur);
                oci_bind_by_name($stmt, ':id_karyawan', $id_karyawan);
                $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
                oci_free_statement($stmt);
                
                if($result) {
                    $message = "✅ Absen pulang berhasil! Total kerja {$total_jam} jam" . ($is_lembur ? " (Lembur {$jam_lembur} jam)" : "");
                    echo "<script>setTimeout(()=>{window.location.href='dashboard.php'},1500)</script>";
                } else {
                    $e = oci_error($conn);
                    throw new Exception($e['message']);
                }
            }
            
        } catch(Exception $e) {
            $error = "❌ Gagal absen pulang: " . $e->getMessage();
        }
    }
}
?>
<!-- HTML tetap sama seperti sebelumnya -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Absensi - Ck-Ck Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f5f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar-custom { background: #2C1810; color: white; padding: 15px 0; }
        .btn-logout { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; }
        .sidebar { background: white; min-height: calc(100vh - 70px); padding: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .sidebar a { display: block; padding: 12px 15px; color: #333; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #C8A951; color: white; }
        .card-custom { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .shift-card { background: linear-gradient(135deg, #2C1810, #C8A951); color: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .shift-card-warning { background: linear-gradient(135deg, #856404, #ffc107); color: #333; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .btn-camera { background: #C8A951; color: #2C1810; border: none; padding: 12px; border-radius: 10px; font-weight: bold; width: 100%; }
        .btn-success { background: #28a745; border: none; }
        .btn-warning { background: #ffc107; border: none; color: #333; }
        .foto-preview { width: 80px; height: 80px; border-radius: 12px; object-fit: cover; margin-top: 10px; }
        .location-box { background: #f8f9fa; border-radius: 10px; padding: 12px; margin-top: 10px; font-size: 12px; }
        #loading { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; text-align: center; padding-top: 20%; color: white; }
        @media (max-width: 768px) { .sidebar { min-height: auto; margin-bottom: 20px; } }
    </style>
</head>
<body>
    <div id="loading"><i class="fas fa-spinner fa-spin fa-3x"></i><h3 style="margin-top:20px">Memproses...</h3></div>

    <nav class="navbar-custom">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div><i class="fas fa-coffee"></i> Ck-Ck Coffee - Karyawan Panel</div>
                <div>
                    <span>Halo, <?= htmlspecialchars($_SESSION['nama'] ?? 'Karyawan') ?></span> 
                    <a href="../logout.php" class="btn-logout ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <div class="sidebar">
                    <h5 class="mb-3">Menu Karyawan</h5>
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="absen.php" class="active"><i class="fas fa-fingerprint"></i> Absensi</a>
                    <a href="tambah_jadwal.php"><i class="fas fa-calendar-plus"></i> Atur Jadwal</a>
                    <a href="riwayat.php"><i class="fas fa-history"></i> Riwayat Absensi</a>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="content p-3 p-md-4">
                    <h2 class="mb-2"><i class="fas fa-fingerprint"></i> Absensi Hari Ini</h2>
                    <p class="text-muted mb-4"><?= date('l, d F Y') ?> | Jam Sekarang: <strong id="currentTimeDisplay"></strong></p>
                    
                    <?php if($message): ?>
                        <div class="alert alert-success alert-dismissible fade show"><?= $message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    
                    <?php if($jadwal_hari_ini): ?>
                    <div class="shift-card">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4>📅 Jadwal Hari Ini: Shift <?= $jadwal_hari_ini['SHIFT'] ?></h4>
                                <p class="mb-0">Jam Kerja: <?= date('H:i', strtotime($jam_mulai)) ?> - <?= date('H:i', strtotime($jam_selesai)) ?></p>
                                <p class="mb-0 small">Batas normal: <?= date('H:i', strtotime($batas_normal)) ?></p>
                                <?php if(!empty($jadwal_hari_ini['KETERANGAN'])): ?>
                                    <small class="opacity-75">Catatan: <?= htmlspecialchars($jadwal_hari_ini['KETERANGAN']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-4 text-end">
                                <div class="shift-icon" style="font-size: 40px;">⏰</div>
                                <div id="realTimeClock" style="font-size:1.2rem; font-weight:bold">--:--:--</div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="shift-card-warning">
                        <div class="row align-items-center">
                            <div class="col-10">
                                <h4>⚠️ Belum Ada Jadwal</h4>
                                <p class="mb-0">Anda belum memiliki jadwal yang disetujui untuk hari ini.</p>
                                <small>Silakan ajukan jadwal terlebih dahulu melalui menu "Atur Jadwal"</small>
                            </div>
                            <div class="col-2 text-end">
                                <a href="tambah_jadwal.php" class="btn btn-light btn-sm">Ajukan</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($jadwal_hari_ini): ?>
                        <?php if(!$is_already_absen || ($absen_data && is_array($absen_data) && empty($absen_data['JAM_KELUAR']))): ?>
                            <div class="card-custom">
                                <h5><i class="fas fa-camera"></i> Ambil Foto & Lokasi</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="camera-container">
                                            <video id="video" class="camera-preview" style="width:100%; background:#000; border-radius:10px" autoplay playsinline></video>
                                            <canvas id="canvas" style="display:none"></canvas>
                                            <button type="button" id="ambilFoto" class="btn-camera mt-2"><i class="fas fa-camera"></i> Ambil Foto</button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div id="fotoPreview" class="text-center"><p class="text-muted">Belum ada foto</p></div>
                                        <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="getLocation()"><i class="fas fa-map-marker-alt"></i> Dapatkan Lokasi GPS</button>
                                        <div class="location-box">
                                            <div id="locationStatus" class="small">📍 Menunggu lokasi...</div>
                                            <div id="locationInfo" class="location-text mt-1"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-custom">
                            <h5><i class="fas fa-check-circle"></i> Form Absensi</h5>
                            
                            <?php if(!$is_already_absen): ?>
                                <form id="absenMasukForm" method="POST">
                                    <input type="hidden" name="absen_masuk" value="1">
                                    <input type="hidden" id="foto_masuk" name="foto_masuk">
                                    <input type="hidden" id="latitude" name="latitude">
                                    <input type="hidden" id="longitude" name="longitude">
                                    <input type="hidden" id="alamat" name="alamat">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> Pastikan Anda sudah:<br>
                                        ✓ Mengambil foto selfie<br>
                                        ✓ Mendapatkan lokasi GPS<br>
                                        ✓ Berada di dalam area cafe
                                    </div>
                                    <button type="submit" id="btnAbsenMasuk" class="btn btn-success btn-lg w-100" disabled>
                                        <i class="fas fa-sign-in-alt"></i> Absen Masuk
                                    </button>
                                </form>
                                
                            <?php elseif($absen_data && is_array($absen_data) && empty($absen_data['JAM_KELUAR'])): 
                                $jam_masuk_tampil = formatJamIndonesia($absen_data['JAM_MASUK'] ?? null);
                            ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> Anda sudah absen masuk pada pukul <strong><?= $jam_masuk_tampil ?></strong>
                                    <?php if(isset($absen_data['TERLAMBAT']) && $absen_data['TERLAMBAT'] > 0): ?>
                                        <br><i class="fas fa-exclamation-triangle"></i> Terlambat: <?= $absen_data['TERLAMBAT'] ?> menit
                                    <?php endif; ?>
                                </div>
                                <form id="absenPulangForm" method="POST">
                                    <input type="hidden" name="absen_pulang" value="1">
                                    <input type="hidden" id="foto_pulang" name="foto_pulang">
                                    <input type="hidden" id="latitude_pulang" name="latitude">
                                    <input type="hidden" id="longitude_pulang" name="longitude">
                                    <input type="hidden" id="alamat_pulang" name="alamat">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-info-circle"></i> Jangan lupa ambil foto selfie untuk absen pulang!
                                    </div>
                                    <button type="submit" id="btnAbsenPulang" class="btn btn-warning btn-lg w-100" disabled>
                                        <i class="fas fa-sign-out-alt"></i> Absen Pulang
                                    </button>
                                </form>
                                
                            <?php elseif($absen_data && is_array($absen_data) && !empty($absen_data['JAM_KELUAR'])): 
                                $jam_masuk_tampil = formatJamIndonesia($absen_data['JAM_MASUK'] ?? null);
                                $jam_keluar_tampil = formatJamIndonesia($absen_data['JAM_KELUAR'] ?? null);
                            ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                    <h4>✅ Anda sudah menyelesaikan absensi hari ini!</h4>
                                    <p>Masuk: <?= $jam_masuk_tampil ?></p>
                                    <p>Pulang: <?= $jam_keluar_tampil ?></p>
                                    <p>Total Jam: <?= round($absen_data['TOTAL_JAM'] ?? 0, 1) ?> jam</p>
                                    <a href="dashboard.php" class="btn btn-primary mt-3">Kembali ke Dashboard</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateDateTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID');
            if(document.getElementById('realTimeClock')) document.getElementById('realTimeClock').innerHTML = timeStr;
            if(document.getElementById('currentTimeDisplay')) document.getElementById('currentTimeDisplay').innerHTML = timeStr;
        }
        setInterval(updateDateTime, 1000);
        updateDateTime();
        
        let video = document.getElementById('video');
        let canvas = document.getElementById('canvas');
        let stream = null;
        let fotoData = null;
        let fotoTaken = false;
        let lokasiValid = false;
        let userLat = null;
        let userLng = null;
        let userAddress = '';
        
        if(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
                .then(function(s) { stream = s; if(video) { video.srcObject = stream; video.play(); } })
                .catch(function(err) { console.error("Kamera error:", err); const fp = document.getElementById('fotoPreview'); if(fp) fp.innerHTML = '<p class="text-danger">❌ Kamera tidak tersedia</p>'; });
        }
        
        const ambilFotoBtn = document.getElementById('ambilFoto');
        if(ambilFotoBtn) {
            ambilFotoBtn.addEventListener('click', function() {
                if(video && video.videoWidth > 0) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                    fotoData = canvas.toDataURL('image/jpeg', 0.8);
                    let img = document.createElement('img');
                    img.src = fotoData;
                    img.className = 'foto-preview';
                    let preview = document.getElementById('fotoPreview');
                    preview.innerHTML = '';
                    preview.appendChild(img);
                    preview.appendChild(document.createTextNode(' ✅ Foto berhasil diambil'));
                    fotoTaken = true;
                    if(stream) { stream.getTracks().forEach(track => track.stop()); if(video) video.style.display = 'none'; }
                    const fm = document.getElementById('foto_masuk');
                    const fp = document.getElementById('foto_pulang');
                    if(fm) fm.value = fotoData;
                    if(fp) fp.value = fotoData;
                    updateButtons();
                } else { alert("Kamera belum siap"); }
            });
        }
        
        function getLocation() {
            const statusDiv = document.getElementById('locationStatus');
            if(statusDiv) statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mendapatkan lokasi...';
            if(!navigator.geolocation) { if(statusDiv) statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:red"></i> ❌ Browser tidak support GPS'; return; }
            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    userLat = pos.coords.latitude;
                    userLng = pos.coords.longitude;
                    if(statusDiv) statusDiv.innerHTML = '<i class="fas fa-check-circle" style="color:green"></i> ✅ Lokasi berhasil didapatkan';
                    const locInfo = document.getElementById('locationInfo');
                    if(locInfo) locInfo.innerHTML = `Lat: ${userLat.toFixed(6)}, Lng: ${userLng.toFixed(6)}`;
                    const latIn = document.getElementById('latitude');
                    const lngIn = document.getElementById('longitude');
                    const latPul = document.getElementById('latitude_pulang');
                    const lngPul = document.getElementById('longitude_pulang');
                    const alamatIn = document.getElementById('alamat');
                    const alamatPul = document.getElementById('alamat_pulang');
                    if(latIn) latIn.value = userLat;
                    if(lngIn) lngIn.value = userLng;
                    if(latPul) latPul.value = userLat;
                    if(lngPul) lngPul.value = userLng;
                    lokasiValid = true;
                    updateButtons();
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${userLat}&lon=${userLng}&zoom=18`)
                        .then(r => r.json())
                        .then(d => {
                            if(d.display_name) {
                                userAddress = d.display_name.substring(0, 200);
                                if(locInfo) locInfo.innerHTML += `<br><i class="fas fa-location-dot"></i> ${userAddress}`;
                                if(alamatIn) alamatIn.value = userAddress;
                                if(alamatPul) alamatPul.value = userAddress;
                            }
                        });
                },
                function(err) {
                    let errorMsg = "❌ Gagal dapat lokasi";
                    if(err.code === 1) errorMsg = "❌ Izin lokasi ditolak";
                    else if(err.code === 2) errorMsg = "❌ Lokasi tidak tersedia";
                    else if(err.code === 3) errorMsg = "❌ Timeout";
                    if(statusDiv) statusDiv.innerHTML = `<i class="fas fa-exclamation-triangle" style="color:red"></i> ${errorMsg}`;
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }
        
        function updateButtons() {
            const btnMasuk = document.getElementById('btnAbsenMasuk');
            const btnPulang = document.getElementById('btnAbsenPulang');
            if(btnMasuk) btnMasuk.disabled = !(fotoTaken && lokasiValid);
            if(btnPulang) btnPulang.disabled = !(fotoTaken && lokasiValid);
        }
        
        setTimeout(() => getLocation(), 500);
        document.querySelectorAll('form').forEach(form => { form.addEventListener('submit', () => { document.getElementById('loading').style.display = 'block'; }); });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();

// Cek apakah sudah login sebagai user
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../assets/config/koneksi.php';

$id_karyawan = $_SESSION['id_karyawan'];
$today = date('Y-m-d');
$current_time = date('H:i:s');
$message = '';
$error = '';

// Fungsi untuk konversi tanggal Oracle
function formatJamIndonesia($datetime) {
    if(empty($datetime)) return '-';
    try {
        // Jika sudah dalam format string
        if(is_string($datetime)) {
            // Cek jika format Oracle timestamp
            if(strpos($datetime, '.') !== false) {
                $cleaned = preg_replace('/\.(\d+)\s+(AM|PM)/i', ' $2', $datetime);
                $cleaned = str_replace('.', ':', $cleaned);
                $cleaned = preg_replace('/:\d{6}/', '', $cleaned);
                $timestamp = strtotime($cleaned);
                if($timestamp !== false && $timestamp > 0) {
                    return date('H:i:s', $timestamp);
                }
            }
            $timestamp = strtotime($datetime);
            if($timestamp !== false && $timestamp > 0) {
                return date('H:i:s', $timestamp);
            }
        }
        return '-';
    } catch(Exception $e) {
        return '-';
    }
}

function formatTanggalIndonesia($tanggal) {
    if(empty($tanggal)) return '-';
    try {
        if(is_string($tanggal)) {
            $timestamp = strtotime($tanggal);
            if($timestamp !== false && $timestamp > 0) {
                return date('d/m/Y', $timestamp);
            }
        }
        return '-';
    } catch(Exception $e) {
        return '-';
    }
}

// Ambil data karyawan
$nama_karyawan = $_SESSION['nama'] ?? 'Karyawan';
$absensi_hari_ini = null;
$riwayat_terbaru = [];
$statistik = ['hadir' => 0, 'total_lembur' => 0];

if($conn && $conn_type) {
    try {
        if($conn_type == 'pdo' || $conn_type == 'mysql') {
            // Cek absensi hari ini
            $stmt = $conn->prepare("SELECT * FROM absensi WHERE id_karyawan = :id AND tanggal = :tanggal");
            $stmt->execute([':id' => $id_karyawan, ':tanggal' => $today]);
            $absensi_hari_ini = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Ambil 5 riwayat terbaru
            $stmt = $conn->prepare("SELECT * FROM absensi WHERE id_karyawan = :id ORDER BY tanggal DESC LIMIT 5");
            $stmt->execute([':id' => $id_karyawan]);
            $riwayat_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Statistik bulan ini
            $bulan_ini = date('Y-m');
            $stmt = $conn->prepare("SELECT 
                                        COUNT(CASE WHEN status IN ('Hadir', 'Terlambat') THEN 1 END) as hadir,
                                        COALESCE(SUM(jam_lembur), 0) as total_lembur
                                    FROM absensi 
                                    WHERE id_karyawan = :id AND DATE_FORMAT(tanggal, '%Y-%m') = :bulan");
            $stmt->execute([':id' => $id_karyawan, ':bulan' => $bulan_ini]);
            $statistik = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } else if ($conn_type == 'oci8') {
            // Oracle - Gunakan TO_CHAR untuk konversi ke string
            // Cek absensi hari ini
            $sql = "SELECT 
                        ID_ABSENSI,
                        ID_KARYAWAN,
                        TO_CHAR(TANGGAL, 'YYYY-MM-DD') as TANGGAL,
                        TO_CHAR(JAM_MASUK, 'HH24:MI:SS') as JAM_MASUK,
                        TO_CHAR(JAM_KELUAR, 'HH24:MI:SS') as JAM_KELUAR,
                        STATUS,
                        TERLAMBAT,
                        FOTO_MASUK,
                        FOTO_PULANG,
                        TOTAL_JAM,
                        JAM_LEMBUR
                    FROM absensi 
                    WHERE id_karyawan = :id AND TRUNC(tanggal) = TRUNC(SYSDATE)";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':id', $id_karyawan);
            oci_execute($stmt);
            $absensi_hari_ini = oci_fetch_assoc($stmt);
            oci_free_statement($stmt);
            
            // Ambil 5 riwayat terbaru
            $sql = "SELECT * FROM (
                        SELECT 
                            ID_ABSENSI,
                            ID_KARYAWAN,
                            TO_CHAR(TANGGAL, 'YYYY-MM-DD') as TANGGAL,
                            TO_CHAR(JAM_MASUK, 'HH24:MI:SS') as JAM_MASUK,
                            TO_CHAR(JAM_KELUAR, 'HH24:MI:SS') as JAM_KELUAR,
                            STATUS,
                            TERLAMBAT,
                            TOTAL_JAM,
                            JAM_LEMBUR
                        FROM absensi 
                        WHERE id_karyawan = :id 
                        ORDER BY TANGGAL DESC
                    ) WHERE ROWNUM <= 5";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':id', $id_karyawan);
            oci_execute($stmt);
            $riwayat_terbaru = [];
            while($row = oci_fetch_assoc($stmt)) {
                $riwayat_terbaru[] = $row;
            }
            oci_free_statement($stmt);
            
            // Statistik bulan ini
            $bulan_ini = date('Y-m');
            $sql = "SELECT 
                        COUNT(CASE WHEN STATUS IN ('Hadir', 'Terlambat') THEN 1 END) as hadir,
                        COALESCE(SUM(JAM_LEMBUR), 0) as total_lembur
                    FROM absensi 
                    WHERE id_karyawan = :id AND TO_CHAR(TANGGAL, 'YYYY-MM') = :bulan";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':id', $id_karyawan);
            oci_bind_by_name($stmt, ':bulan', $bulan_ini);
            oci_execute($stmt);
            $statistik = oci_fetch_assoc($stmt);
            oci_free_statement($stmt);
            
            // Set default jika null
            $statistik['HADIR'] = $statistik['HADIR'] ?? 0;
            $statistik['TOTAL_LEMBUR'] = $statistik['TOTAL_LEMBUR'] ?? 0;
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Dashboard Karyawan - Ck-Ck Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .navbar-custom { background: #2C1810; color: white; padding: 12px 0; }
        .btn-logout { background: #dc3545; color: white; padding: 6px 15px; border-radius: 8px; text-decoration: none; font-size: 0.8rem; }
        
        .sidebar { background: white; min-height: calc(100vh - 70px); padding: 15px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .sidebar a { display: block; padding: 10px 12px; color: #333; text-decoration: none; border-radius: 8px; margin-bottom: 5px; font-size: 0.9rem; }
        .sidebar a:hover, .sidebar a.active { background: #C8A951; color: white; }
        
        .card-stats { background: white; border-radius: 12px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); height: 100%; }
        .stats-icon { font-size: 2rem; color: #C8A951; margin-bottom: 10px; }
        
        .welcome-banner { background: linear-gradient(135deg, #2C1810, #3d2318); color: white; padding: 15px; border-radius: 12px; margin-bottom: 20px; }
        
        .btn-attendance { background: #C8A951; color: #2C1810; border: none; padding: 12px; border-radius: 8px; font-weight: bold; width: 100%; text-decoration: none; display: inline-block; text-align: center; }
        .btn-attendance:hover { background: #b8962e; color: white; }
        .btn-out { background: #dc3545; color: white; }
        .btn-out:hover { background: #c82333; color: white; }
        
        .alert-custom { position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 300px; animation: slideIn 0.3s; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        .real-time-clock { font-size: 1.5rem; font-weight: bold; font-family: monospace; }
        
        .status-hadir { color: #28a745; font-weight: bold; }
        .status-terlambat { color: #ffc107; font-weight: bold; }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; margin-bottom: 15px; }
            .real-time-clock { font-size: 1.2rem; }
            .stats-icon { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar-custom">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div><i class="fas fa-coffee"></i> Ck-Ck Coffee</div>
                <div>
                    <span>Halo, <?= htmlspecialchars($nama_karyawan) ?></span> 
                    <a href="../logout.php" class="btn-logout ms-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <div class="sidebar">
                    <h5 class="mb-3"><i class="fas fa-bars"></i> Menu Karyawan</h5>
                    <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="absen.php"><i class="fas fa-fingerprint"></i> Absensi</a>
                    <a href="tambah_jadwal.php"><i class="fas fa-calendar-plus"></i> Atur Jadwal</a>
                    <a href="tukar_shift.php"><i class="fas fa-exchange-alt"></i> Tukar Shift</a>
                    <a href="pengajuan.php"><i class="fas fa-file-alt"></i> Cuti/Izin</a>
                    <a href="riwayat.php"><i class="fas fa-history"></i> Riwayat Absensi</a>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="content p-2 p-md-3">
                    <?php if($message): ?>
                        <div class="alert alert-success alert-custom"><?= $message ?></div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-custom"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <div class="welcome-banner">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h5 class="mb-0">Selamat Datang, <?= htmlspecialchars($nama_karyawan) ?>! 👋</h5>
                                <p class="small mb-0">Semangat bekerja! Jangan lupa absen tepat waktu.</p>
                            </div>
                            <div class="col-4 text-end">
                                <div class="real-time-clock" id="realTimeClock">--:--:--</div>
                                <small id="realTimeDate"></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card-stats text-center">
                                <i class="fas fa-calendar-check stats-icon"></i>
                                <?php if(!$absensi_hari_ini): ?>
                                    <h6>Belum Absen Hari Ini</h6>
                                    <p class="small text-muted">Silakan lakukan absensi</p>
                                    <a href="absen.php" class="btn-attendance">
                                        <i class="fas fa-fingerprint"></i> Absen Sekarang
                                    </a>
                                <?php elseif(empty($absensi_hari_ini['JAM_KELUAR'])): ?>
                                    <h6>✅ Sudah Check In</h6>
                                    <p class="small mb-2">
                                        Jam Masuk: <strong><?= $absensi_hari_ini['JAM_MASUK'] ?? '-' ?></strong>
                                    </p>
                                    <?php if(($absensi_hari_ini['TERLAMBAT'] ?? 0) > 0): ?>
                                        <p class="small text-warning">⚠️ Terlambat <?= $absensi_hari_ini['TERLAMBAT'] ?> menit</p>
                                    <?php else: ?>
                                        <p class="small text-success">✅ Tepat waktu</p>
                                    <?php endif; ?>
                                    <a href="absen.php" class="btn-attendance btn-out">
                                        <i class="fas fa-sign-out-alt"></i> Check Out
                                    </a>
                                <?php else: ?>
                                    <h6>✅ Absen Lengkap</h6>
                                    <p class="small mb-0">Masuk: <strong><?= $absensi_hari_ini['JAM_MASUK'] ?? '-' ?></strong></p>
                                    <p class="small mb-0">Keluar: <strong><?= $absensi_hari_ini['JAM_KELUAR'] ?? '-' ?></strong></p>
                                    <?php if(($absensi_hari_ini['JAM_LEMBUR'] ?? 0) > 0): ?>
                                        <p class="small text-success mt-1">⭐ Lembur <?= round($absensi_hari_ini['JAM_LEMBUR'], 1) ?> jam</p>
                                    <?php endif; ?>
                                    <button class="btn-attendance" disabled style="opacity:0.6; cursor:not-allowed;">
                                        <i class="fas fa-check-circle"></i> Selesai
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card-stats text-center">
                                <i class="fas fa-chart-line stats-icon"></i>
                                <h6><?= $statistik['hadir'] ?? $statistik['HADIR'] ?? 0 ?> Hari</h6>
                                <p class="small">Kehadiran bulan <?= date('F Y') ?></p>
                                <hr>
                                <div class="row">
                                    <div class="col-12">
                                        <small>Total Lembur Bulan Ini</small>
                                        <h6><?= round($statistik['total_lembur'] ?? $statistik['TOTAL_LEMBUR'] ?? 0, 1) ?> jam</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-stats mt-2">
                        <h6><i class="fas fa-history"></i> Riwayat Absensi Terbaru</h6>
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jam Masuk</th>
                                        <th>Jam Keluar</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($riwayat_terbaru)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-3">
                                                <i class="fas fa-folder-open text-muted"></i> Belum ada riwayat absensi
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($riwayat_terbaru as $r): ?>
                                            <?php 
                                                $status = $r['STATUS'] ?? '-';
                                                $status_class = '';
                                                if($status == 'Hadir') $status_class = 'text-success';
                                                elseif($status == 'Terlambat') $status_class = 'text-warning';
                                                elseif($status == 'Alpha') $status_class = 'text-danger';
                                            ?>
                                            <tr>
                                                <td><?= $r['TANGGAL'] ?? '-' ?></td>
                                                <td><?= $r['JAM_MASUK'] ?? '-' ?></td>
                                                <td><?= $r['JAM_KELUAR'] ?? '-' ?></td>
                                                <td class="<?= $status_class ?>">
                                                    <?= $status ?>
                                                    <?php if(($r['TERLAMBAT'] ?? 0) > 0): ?>
                                                        <small class="text-danger">(+<?= $r['TERLAMBAT'] ?>')</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="riwayat.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-eye"></i> Lihat Semua Riwayat
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Real time clock
        function updateDateTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', { hour12: false });
            const dateStr = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            
            const clockEl = document.getElementById('realTimeClock');
            const dateEl = document.getElementById('realTimeDate');
            
            if(clockEl) clockEl.innerHTML = timeStr;
            if(dateEl) dateEl.innerHTML = dateStr;
        }
        setInterval(updateDateTime, 1000);
        updateDateTime();
        
        // Auto hide alert
        setTimeout(() => {
            document.querySelectorAll('.alert-custom').forEach(alert => {
                setTimeout(() => alert.remove(), 3000);
            });
        }, 500);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
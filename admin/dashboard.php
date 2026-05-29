<?php
session_start();

// Cek apakah sudah login sebagai admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../assets/config/koneksi.php';

// Inisialisasi statistik
$total_karyawan = 0;
$hadir_hari_ini = 0;
$terlambat_hari_ini = 0;
$izin_hari_ini = 0;
$sakit_hari_ini = 0;
$alpha_hari_ini = 0;
$pending_tukar_shift = 0;
$error = '';

// Fungsi untuk membaca CLOB dari Oracle
function getClobValue($field) {
    if (is_null($field)) return '';
    if (is_string($field)) return $field;
    if (is_object($field)) {
        if (method_exists($field, 'load')) {
            return $field->load();
        } elseif (method_exists($field, 'read')) {
            return $field->read($field->size());
        } else {
            return (string)$field;
        }
    }
    return '';
}

// Ambil data statistik dari database
if($conn && $conn_type) {
    try {
        if($conn_type === 'pdo') {
            // MySQL
            $stmt = $conn->query("SELECT COUNT(*) as total FROM karyawan WHERE status = 'Aktif'");
            $total_karyawan = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $conn->query("SELECT COUNT(*) as total FROM absensi WHERE tanggal = CURDATE() AND status IN ('Hadir', 'Terlambat')");
            $hadir_hari_ini = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $conn->query("SELECT COUNT(*) as total FROM absensi WHERE tanggal = CURDATE() AND status = 'Terlambat'");
            $terlambat_hari_ini = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $conn->query("SELECT COUNT(*) as total FROM absensi WHERE tanggal = CURDATE() AND status = 'Izin'");
            $izin_hari_ini = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $conn->query("SELECT COUNT(*) as total FROM absensi WHERE tanggal = CURDATE() AND status = 'Sakit'");
            $sakit_hari_ini = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $conn->query("SELECT COUNT(*) as total FROM absensi WHERE tanggal = CURDATE() AND status = 'Alpha'");
            $alpha_hari_ini = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $conn->query("SELECT COUNT(*) as total FROM tukar_shift WHERE status = 'Menunggu'");
            $pending_tukar_shift = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } else {
            // OCI8 Oracle
            // Total karyawan aktif
            $stmt = oci_parse($conn, "SELECT COUNT(*) as total FROM karyawan WHERE status = 'Aktif'");
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            $total_karyawan = $row['TOTAL'] ?? 0;
            
            // Total hadir hari ini (Hadir + Terlambat)
            $stmt = oci_parse($conn, "SELECT COUNT(*) as total FROM absensi WHERE TRUNC(tanggal) = TRUNC(SYSDATE) AND status IN ('Hadir', 'Terlambat')");
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            $hadir_hari_ini = $row['TOTAL'] ?? 0;
            
            // Total terlambat hari ini
            $stmt = oci_parse($conn, "SELECT COUNT(*) as total FROM absensi WHERE TRUNC(tanggal) = TRUNC(SYSDATE) AND status = 'Terlambat'");
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            $terlambat_hari_ini = $row['TOTAL'] ?? 0;
            
            // Total izin hari ini
            $stmt = oci_parse($conn, "SELECT COUNT(*) as total FROM absensi WHERE TRUNC(tanggal) = TRUNC(SYSDATE) AND status = 'Izin'");
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            $izin_hari_ini = $row['TOTAL'] ?? 0;
            
            // Total sakit hari ini
            $stmt = oci_parse($conn, "SELECT COUNT(*) as total FROM absensi WHERE TRUNC(tanggal) = TRUNC(SYSDATE) AND status = 'Sakit'");
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            $sakit_hari_ini = $row['TOTAL'] ?? 0;
            
            // Total alpha hari ini
            $stmt = oci_parse($conn, "SELECT COUNT(*) as total FROM absensi WHERE TRUNC(tanggal) = TRUNC(SYSDATE) AND status = 'Alpha'");
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            $alpha_hari_ini = $row['TOTAL'] ?? 0;
            
            // Total tukar shift pending
            $stmt = oci_parse($conn, "SELECT COUNT(*) as total FROM tukar_shift WHERE status = 'Menunggu'");
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            $pending_tukar_shift = $row['TOTAL'] ?? 0;
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// Data untuk chart (7 hari terakhir)
$chart_labels = [];
$chart_data = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($date));
    
    if($conn_type === 'pdo') {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM absensi WHERE tanggal = :tanggal AND status IN ('Hadir', 'Terlambat')");
            $stmt->execute([':tanggal' => $date]);
            $chart_data[] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch(Exception $e) {
            $chart_data[] = 0;
        }
    } else {
        // Oracle
        try {
            $stmt = oci_parse($conn, "SELECT COUNT(*) as total FROM absensi WHERE TRUNC(tanggal) = TRUNC(TO_DATE('$date', 'YYYY-MM-DD')) AND status IN ('Hadir', 'Terlambat')");
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            $chart_data[] = $row['TOTAL'] ?? 0;
        } catch(Exception $e) {
            $chart_data[] = 0;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Admin Dashboard - Ck-Ck Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100%;
            background: linear-gradient(135deg, #1a0f0a 0%, #2C1810 100%);
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header .logo-icon { width: 60px; height: 60px; background: linear-gradient(135deg, #C8A951, #e6b422); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
        .sidebar-header .logo-icon i { font-size: 1.8rem; color: white; }
        .sidebar-header h4 { font-family: 'Playfair Display', serif; font-size: 1.2rem; margin-bottom: 5px; }
        .sidebar-header p { font-size: 0.7rem; opacity: 0.7; }
        
        .sidebar-menu { padding: 20px; }
        .sidebar-menu .menu-title { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; opacity: 0.5; margin-bottom: 15px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s ease; border-radius: 12px; margin-bottom: 5px; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(200,169,81,0.2); color: #C8A951; }
        .sidebar-menu a i { width: 22px; font-size: 1rem; }
        
        .badge-notif { background: #dc3545; color: white; border-radius: 50px; padding: 2px 8px; font-size: 0.7rem; margin-left: auto; }
        
        .main-content { margin-left: 280px; padding: 20px; }
        
        .top-navbar { background: white; border-radius: 20px; padding: 15px 25px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .page-title h2 { font-size: 1.3rem; font-weight: 700; margin-bottom: 5px; }
        .page-title p { font-size: 0.8rem; color: #888; margin: 0; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-name { text-align: right; }
        .user-name .name { font-weight: 600; font-size: 0.9rem; }
        .user-name .role { font-size: 0.7rem; color: #C8A951; }
        .user-avatar { width: 45px; height: 45px; background: linear-gradient(135deg, #C8A951, #e6b422); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .user-avatar i { font-size: 1.2rem; color: white; }
        .btn-logout { background: #dc3545; color: white; padding: 8px 18px; border-radius: 10px; text-decoration: none; font-size: 0.8rem; transition: all 0.3s ease; }
        .btn-logout:hover { background: #c82333; transform: translateY(-2px); }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; display: flex; align-items: center; justify-content: space-between; transition: all 0.3s ease; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .stat-info h3 { font-size: 0.75rem; color: #888; margin-bottom: 5px; }
        .stat-info .number { font-size: 1.5rem; font-weight: 800; color: #2C1810; }
        .stat-icon { width: 50px; height: 50px; background: #fdf8f0; border-radius: 15px; display: flex; align-items: center; justify-content: center; }
        .stat-icon i { font-size: 1.3rem; color: #C8A951; }
        
        .alert-custom { background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 12px; padding: 15px 20px; margin-bottom: 25px; }
        
        .card-custom { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: none; }
        
        @media (max-width: 992px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .stat-card, .card-custom { animation: fadeInUp 0.5s ease forwards; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-mug-hot"></i></div>
            <h4>Ck-Ck Coffee</h4>
            <p>Admin Panel</p>
        </div>
        <div class="sidebar-menu">
            <div class="menu-title">MAIN MENU</div>
            <a href="dashboard.php" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="karyawan/"><i class="fas fa-users"></i> <span>Kelola Karyawan</span></a>
            <a href="jadwal/"><i class="fas fa-calendar"></i> <span>Kelola Jadwal</span></a>
            <a href="absensi/"><i class="fas fa-fingerprint"></i> <span>Monitoring Absensi</span></a>
            <a href="tukar_shift.php"><i class="fas fa-exchange-alt"></i> <span>Tukar Shift</span>
                <?php if($pending_tukar_shift > 0): ?>
                    <span class="badge-notif"><?= $pending_tukar_shift ?></span>
                <?php endif; ?>
            </a>
            <a href="laporan/"><i class="fas fa-chart-line"></i> <span>Laporan</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <div class="page-title">
                <h2><i class="fas fa-tachometer-alt"></i> Dashboard Admin</h2>
                <p>Kelola sistem absensi Ck-Ck Coffee</p>
            </div>
            <div class="user-info">
                <div class="user-name">
                    <div class="name"><?= htmlspecialchars($_SESSION['nama'] ?? 'Administrator') ?></div>
                    <div class="role">Administrator</div>
                </div>
                <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
                <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div class="alert-custom">
            <i class="fas fa-bell"></i> 
            <strong>Selamat Datang, <?= htmlspecialchars($_SESSION['nama'] ?? 'Administrator') ?>!</strong> 
            Anda berhasil login sebagai Administrator.
            <?php if($pending_tukar_shift > 0): ?>
                <br><i class="fas fa-exchange-alt"></i> Terdapat <strong><?= $pending_tukar_shift ?></strong> pengajuan tukar shift yang menunggu persetujuan.
                <a href="tukar_shift.php" class="alert-link">Klik disini untuk review</a>
            <?php endif; ?>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info"><h3>Total Karyawan</h3><div class="number"><?= number_format($total_karyawan) ?></div><small>Aktif</small></div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info"><h3>Hadir Hari Ini</h3><div class="number" style="color:#28a745"><?= number_format($hadir_hari_ini) ?></div><small>Check In</small></div>
                <div class="stat-icon"><i class="fas fa-check-circle" style="color:#28a745"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info"><h3>Terlambat</h3><div class="number" style="color:#ffc107"><?= number_format($terlambat_hari_ini) ?></div><small>Hari Ini</small></div>
                <div class="stat-icon"><i class="fas fa-clock" style="color:#ffc107"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info"><h3>Izin</h3><div class="number" style="color:#6c757d"><?= number_format($izin_hari_ini) ?></div><small>Hari Ini</small></div>
                <div class="stat-icon"><i class="fas fa-calendar-times" style="color:#6c757d"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info"><h3>Sakit</h3><div class="number" style="color:#17a2b8"><?= number_format($sakit_hari_ini) ?></div><small>Hari Ini</small></div>
                <div class="stat-icon"><i class="fas fa-thermometer-half" style="color:#17a2b8"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info"><h3>Alpha</h3><div class="number" style="color:#dc3545"><?= number_format($alpha_hari_ini) ?></div><small>Hari Ini</small></div>
                <div class="stat-icon"><i class="fas fa-times-circle" style="color:#dc3545"></i></div>
            </div>
        </div>

        <div class="card-custom">
            <h5><i class="fas fa-chart-line"></i> Statistik Kehadiran 7 Hari Terakhir</h5>
            <canvas id="attendanceChart" height="100"></canvas>
        </div>

        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <a href="karyawan/" style="text-decoration:none;"><div class="stat-card" style="text-align:center; display:block;"><i class="fas fa-users fa-2x" style="color:#C8A951"></i><h5 class="mt-2">Kelola Karyawan</h5><small>Tambah/edit data karyawan</small></div></a>
            <a href="absensi/" style="text-decoration:none;"><div class="stat-card" style="text-align:center; display:block;"><i class="fas fa-fingerprint fa-2x" style="color:#C8A951"></i><h5 class="mt-2">Monitoring Absensi</h5><small>Pantau absensi karyawan</small></div></a>
            <a href="tukar_shift.php" style="text-decoration:none;"><div class="stat-card" style="text-align:center; display:block;"><i class="fas fa-exchange-alt fa-2x" style="color:#C8A951"></i><h5 class="mt-2">Approval Tukar Shift</h5><small><?= $pending_tukar_shift ?> pengajuan menunggu</small></div></a>
            <a href="laporan/" style="text-decoration:none;"><div class="stat-card" style="text-align:center; display:block;"><i class="fas fa-chart-line fa-2x" style="color:#C8A951"></i><h5 class="mt-2">Laporan</h5><small>Lihat rekap absensi</small></div></a>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Jumlah Kehadiran',
                    data: <?= json_encode($chart_data) ?>,
                    borderColor: '#C8A951',
                    backgroundColor: 'rgba(200,169,81,0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#C8A951',
                    pointBorderColor: 'white',
                    pointRadius: 6,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { backgroundColor: '#2C1810', titleColor: '#C8A951', bodyColor: 'white' }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
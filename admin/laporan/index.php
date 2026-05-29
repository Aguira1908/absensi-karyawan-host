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

// Nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Daftar jabatan untuk filter
$jabatan_list = [];

// Ambil daftar jabatan unik dari database
if($conn && $conn_type) {
    try {
        if($conn_type === 'pdo') {
            $stmt = $conn->query("SELECT DISTINCT jabatan FROM karyawan WHERE status = 'Aktif' ORDER BY jabatan");
            $jabatan_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $stmt = oci_parse($conn, "SELECT DISTINCT jabatan FROM karyawan WHERE status = 'Aktif' ORDER BY jabatan");
            oci_execute($stmt);
            while($row = oci_fetch_assoc($stmt)) {
                $jabatan_list[] = $row['JABATAN'];
            }
        }
    } catch(Exception $e) {}
}

// Query rekap absensi per karyawan
$rekap_absensi = [];
$total_hadir = 0;
$total_terlambat = 0;
$total_sakit = 0;
$total_izin = 0;
$total_alpha = 0;
$rata_kehadiran = 0;

if($conn && $conn_type) {
    try {
        if($conn_type === 'pdo') {
            $sql = "SELECT k.id_karyawan, k.nama, k.jabatan, k.no_hp,
                           COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as hadir,
                           COUNT(CASE WHEN a.status = 'Terlambat' THEN 1 END) as terlambat,
                           COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as sakit,
                           COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as izin,
                           COUNT(CASE WHEN a.status = 'Alpha' THEN 1 END) as alpha,
                           COUNT(CASE WHEN a.status IN ('Hadir', 'Terlambat') THEN 1 END) as hadir_efektif,
                           COUNT(a.id_absensi) as total_hari_kerja,
                           ROUND(COUNT(CASE WHEN a.status IN ('Hadir', 'Terlambat') THEN 1 END) / NULLIF(COUNT(a.id_absensi), 0) * 100, 2) as persen_kehadiran
                    FROM karyawan k
                    LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan 
                        AND EXTRACT(MONTH FROM a.tanggal) = :bulan 
                        AND EXTRACT(YEAR FROM a.tanggal) = :tahun
                    WHERE k.status = 'Aktif'";
            
            if($jabatan_filter) {
                $sql .= " AND k.jabatan = :jabatan";
            }
            
            $sql .= " GROUP BY k.id_karyawan, k.nama, k.jabatan, k.no_hp
                      ORDER BY k.nama";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':bulan', $bulan_num);
            $stmt->bindParam(':tahun', $tahun);
            if($jabatan_filter) {
                $stmt->bindParam(':jabatan', $jabatan_filter);
            }
            $stmt->execute();
            $rekap_absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Total statistik
            $total_hadir = array_sum(array_column($rekap_absensi, 'HADIR'));
            $total_terlambat = array_sum(array_column($rekap_absensi, 'TERLAMBAT'));
            $total_sakit = array_sum(array_column($rekap_absensi, 'SAKIT'));
            $total_izin = array_sum(array_column($rekap_absensi, 'IZIN'));
            $total_alpha = array_sum(array_column($rekap_absensi, 'ALPHA'));
            $rata_kehadiran = count($rekap_absensi) > 0 ? round(array_sum(array_column($rekap_absensi, 'PERSEN_KEHADIRAN')) / count($rekap_absensi), 2) : 0;
        } else {
            // OCI8
            $where = "";
            if($jabatan_filter) $where = " AND k.jabatan = '$jabatan_filter'";
            
            $sql = "SELECT k.id_karyawan, k.nama, k.jabatan, k.no_hp,
                           SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                           SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) as terlambat,
                           SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                           SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) as izin,
                           SUM(CASE WHEN a.status = 'Alpha' THEN 1 ELSE 0 END) as alpha,
                           SUM(CASE WHEN a.status IN ('Hadir', 'Terlambat') THEN 1 ELSE 0 END) as hadir_efektif,
                           COUNT(a.id_absensi) as total_hari_kerja,
                           ROUND(SUM(CASE WHEN a.status IN ('Hadir', 'Terlambat') THEN 1 ELSE 0 END) / NULLIF(COUNT(a.id_absensi), 0) * 100, 2) as persen_kehadiran
                    FROM karyawan k
                    LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan 
                        AND EXTRACT(MONTH FROM a.tanggal) = $bulan_num 
                        AND EXTRACT(YEAR FROM a.tanggal) = $tahun
                    WHERE k.status = 'Aktif' $where
                    GROUP BY k.id_karyawan, k.nama, k.jabatan, k.no_hp
                    ORDER BY k.nama";
            
            $stmt = oci_parse($conn, $sql);
            oci_execute($stmt);
            while($row = oci_fetch_assoc($stmt)) {
                $rekap_absensi[] = $row;
            }
            
            $total_hadir = array_sum(array_column($rekap_absensi, 'HADIR'));
            $total_terlambat = array_sum(array_column($rekap_absensi, 'TERLAMBAT'));
            $total_sakit = array_sum(array_column($rekap_absensi, 'SAKIT'));
            $total_izin = array_sum(array_column($rekap_absensi, 'IZIN'));
            $total_alpha = array_sum(array_column($rekap_absensi, 'ALPHA'));
            $rata_kehadiran = count($rekap_absensi) > 0 ? round(array_sum(array_column($rekap_absensi, 'PERSEN_KEHADIRAN')) / count($rekap_absensi), 2) : 0;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Admin Ck-Ck Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
        }

        /* Sidebar */
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

        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #C8A951, #e6b422);
            border-radius: 50%;
            display: flex;
-align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .sidebar-header .logo-icon i {
            font-size: 1.8rem;
            color: white;
        }

        .sidebar-header h4 {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .sidebar-menu {
            padding: 20px;
        }

        .sidebar-menu .menu-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.5;
            margin-bottom: 15px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 12px;
            margin-bottom: 5px;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(200,169,81,0.2);
            color: #C8A951;
        }

        .sidebar-menu a i {
            width: 22px;
            font-size: 1rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 20px;
        }

        /* Top Navbar */
        .top-navbar {
            background: white;
            border-radius: 20px;
            padding: 15px 25px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .page-title h2 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .page-title p {
            font-size: 0.8rem;
            color: #888;
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            text-align: right;
        }

        .user-name .name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-name .role {
            font-size: 0.7rem;
            color: #C8A951;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #C8A951, #e6b422);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar i {
            font-size: 1.2rem;
            color: white;
        }

        .btn-logout {
            background: #dc3545;
            color: white;
            padding: 8px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: #c82333;
            color: white;
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .stat-info h3 {
            font-size: 0.75rem;
            color: #888;
            margin-bottom: 5px;
        }

        .stat-info .number {
            font-size: 1.5rem;
            font-weight: 800;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: #fdf8f0;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon i {
            font-size: 1.3rem;
        }

        /* Card */
        .card-custom {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: none;
        }

        .card-title {
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        /* Button */
        .btn-primary {
            background: linear-gradient(135deg, #C8A951, #e6b422);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #b8962e, #d4a81e);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }

        /* Filter Form */
        .filter-form {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #C8A951;
            box-shadow: 0 0 0 0.2rem rgba(200,169,81,0.25);
        }

        /* Table */
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-custom thead th {
            background: #f8f9fa;
            padding: 15px;
            font-weight: 600;
            font-size: 0.85rem;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
        }

        .table-custom tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.85rem;
        }

        .table-custom tbody tr:hover {
            background: #fef9f0;
        }

        .table-custom tfoot td {
            background: #f8f9fa;
            padding: 12px;
            font-weight: 600;
        }

        /* Badges */
        .badge-hadir {
            background: #28a745;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-terlambat {
            background: #ffc107;
            color: #333;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-sakit {
            background: #17a2b8;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-izin {
            background: #6c757d;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-alpha {
            background: #dc3545;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Progress Bar */
        .progress-custom {
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar-custom {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        /* Feature Cards */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        .feature-card {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            background: #fef9f0;
        }

        .feature-card i {
            font-size: 2.5rem;
            color: #C8A951;
            margin-bottom: 15px;
        }

        .feature-card h5 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .feature-card p {
            font-size: 0.75rem;
            color: #888;
            margin-bottom: 15px;
        }

        /* Alert */
        .alert-custom {
            background: #f8d7da;
            color: #721c24;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                left: -280px;
            }
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .table-custom {
                font-size: 0.75rem;
            }
            .table-custom thead th,
            .table-custom tbody td {
                padding: 10px;
            }
            .feature-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Print Style */
        @media print {
            .sidebar, .top-navbar, .filter-form, .feature-grid, .btn-print, .btn-logout {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .card-custom {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            .stat-card {
                border: 1px solid #ddd;
                box-shadow: none;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card, .card-custom, .feature-card {
            animation: fadeInUp 0.5s ease forwards;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon">
                <i class="fas fa-mug-hot"></i>
            </div>
            <h4>Ck-Ck Coffee</h4>
            <p>Admin Panel</p>
        </div>
        <div class="sidebar-menu">
            <div class="menu-title">MAIN MENU</div>
            <a href="../dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="../karyawan/">
                <i class="fas fa-users"></i>
                <span>Kelola Karyawan</span>
            </a>
            <a href="../jadwal/">
                <i class="fas fa-calendar"></i>
                <span>Kelola Jadwal</span>
            </a>
            <a href="../absensi/">
                <i class="fas fa-fingerprint"></i>
                <span>Monitoring Absensi</span>
            </a>
            <a href="index.php" class="active">
                <i class="fas fa-chart-line"></i>
                <span>Laporan</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="page-title">
                <h2><i class="fas fa-chart-line"></i> Laporan & Statistik</h2>
                <p>Analisis dan rekap data kehadiran karyawan</p>
            </div>
            <div class="user-info">
                <div class="user-name">
                    <div class="name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                    <div class="role">Administrator</div>
                </div>
                <div class="user-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <a href="../../logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Error Alert -->
        <?php if(isset($error)): ?>
            <div class="alert-custom">
                <i class="fas fa-exclamation-triangle"></i> Error: <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="card-custom">
            <h5 class="card-title"><i class="fas fa-filter"></i> Filter Laporan</h5>
            <form method="GET" class="filter-form">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Periode Bulan</label>
                        <input type="month" name="bulan" class="form-control" value="<?= $bulan ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filter Jabatan</label>
                        <select name="jabatan" class="form-select">
                            <option value="">Semua Jabatan</option>
                            <?php foreach($jabatan_list as $j): ?>
                                <option value="<?= $j ?>" <?= $jabatan_filter==$j?'selected':'' ?>><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Tampilkan
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-sync-alt"></i> Reset
                        </a>
                        <button type="button" onclick="window.print()" class="btn btn-primary ms-2 btn-print">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Statistik Ringkasan -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Hadir</h3>
                    <div class="number" style="color:#28a745"><?= number_format($total_hadir) ?></div>
                    <small>Karyawan</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle" style="color:#28a745"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Terlambat</h3>
                    <div class="number" style="color:#ffc107"><?= number_format($total_terlambat) ?></div>
                    <small>Karyawan</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock" style="color:#ffc107"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Sakit</h3>
                    <div class="number" style="color:#17a2b8"><?= number_format($total_sakit) ?></div>
                    <small>Karyawan</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-thermometer-half" style="color:#17a2b8"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Izin</h3>
                    <div class="number" style="color:#6c757d"><?= number_format($total_izin) ?></div>
                    <small>Karyawan</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-calendar-times" style="color:#6c757d"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Alpha</h3>
                    <div class="number" style="color:#dc3545"><?= number_format($total_alpha) ?></div>
                    <small>Karyawan</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-times-circle" style="color:#dc3545"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Rata-rata Kehadiran</h3>
                    <div class="number" style="color:#C8A951"><?= $rata_kehadiran ?>%</div>
                    <small>Keseluruhan</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-percent" style="color:#C8A951"></i>
                </div>
            </div>
        </div>

        <!-- Tabel Rekap Absensi -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="card-title mb-0"><i class="fas fa-table"></i> Rekap Absensi Karyawan</h5>
                <div>
                    <span class="badge bg-secondary">
                        <i class="fas fa-calendar"></i> <?= $nama_bulan[$bulan_num] ?> <?= $tahun ?>
                    </span>
                    <?php if($jabatan_filter): ?>
                        <span class="badge bg-info ms-2">
                            <i class="fas fa-briefcase"></i> <?= $jabatan_filter ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Karyawan</th>
                            <th>Jabatan</th>
                            <th class="text-center">Hadir</th>
                            <th class="text-center">Terlambat</th>
                            <th class="text-center">Sakit</th>
                            <th class="text-center">Izin</th>
                            <th class="text-center">Alpha</th>
                            <th class="text-center">Total Hari</th>
                            <th class="text-center">% Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($rekap_absensi)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="fas fa-chart-line fa-2x mb-2 d-block text-muted"></i>
                                    Belum ada data absensi untuk periode ini
                                 </td>
                            </tr>
                        <?php else: ?>
                            <?php $no=1; foreach($rekap_absensi as $r): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($r['NAMA']) ?></strong></td>
                                <td><?= $r['JABATAN'] ?></td>
                                <td class="text-center"><span class="badge-hadir"><?= $r['HADIR'] ?? 0 ?></span></td>
                                <td class="text-center"><span class="badge-terlambat"><?= $r['TERLAMBAT'] ?? 0 ?></span></td>
                                <td class="text-center"><span class="badge-sakit"><?= $r['SAKIT'] ?? 0 ?></span></td>
                                <td class="text-center"><span class="badge-izin"><?= $r['IZIN'] ?? 0 ?></span></td>
                                <td class="text-center"><span class="badge-alpha"><?= $r['ALPHA'] ?? 0 ?></span></td>
                                <td class="text-center"><?= $r['TOTAL_HARI_KERJA'] ?? 0 ?></td>
                                <td class="text-center" style="min-width: 120px;">
                                    <?php $persen = round($r['PERSEN_KEHADIRAN'] ?? 0); ?>
                                    <div class="progress-custom">
                                        <div class="progress-bar-custom" style="width: <?= $persen ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= $persen ?>%</small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Total</th>
                            <th class="text-center"><?= $total_hadir ?></th>
                            <th class="text-center"><?= $total_terlambat ?></th>
                            <th class="text-center"><?= $total_sakit ?></th>
                            <th class="text-center"><?= $total_izin ?></th>
                            <th class="text-center"><?= $total_alpha ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Link ke Laporan Lainnya -->
        <div class="feature-grid">
            <div class="feature-card" onclick="window.location.href='jadwal.php?bulan=<?= $bulan ?>'">
                <i class="fas fa-calendar-alt"></i>
                <h5>Laporan Jadwal</h5>
                <p>Lihat rekap jadwal kerja karyawan per bulan</p>
                <small class="text-muted">Klik untuk melihat →</small>
            </div>
            <div class="feature-card" onclick="window.location.href='karyawan.php'">
                <i class="fas fa-users"></i>
                <h5>Laporan Karyawan</h5>
                <p>Data lengkap seluruh karyawan aktif</p>
                <small class="text-muted">Klik untuk melihat →</small>
            </div>
            <div class="feature-card" onclick="window.location.href='tahunan.php?tahun=<?= $tahun ?>'">
                <i class="fas fa-chart-pie"></i>
                <h5>Ringkasan Tahunan</h5>
                <p>Statistik perbandingan antar bulan</p>
                <small class="text-muted">Klik untuk melihat →</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
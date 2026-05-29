<?php
session_start();

// Cek apakah sudah login sebagai user
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../assets/config/koneksi.php';

$id_karyawan = $_SESSION['id_karyawan'];
$bulan_filter = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$tahun = substr($bulan_filter, 0, 4);
$bulan_num = substr($bulan_filter, 5, 2);

// Nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// =============================================
// 1. AMBIL DATA ABSENSI DENGAN FILTER BULAN
// =============================================
$riwayat_absensi = [];
$statistik = [
    'hadir' => 0, 'terlambat' => 0, 'sakit' => 0, 
    'izin' => 0, 'alpha' => 0, 'total_lembur' => 0,
    'total_jam' => 0
];

// Fungsi untuk konversi tanggal Oracle ke string
function convertOracleDateToString($oracle_date) {
    if(empty($oracle_date)) return '';
    if(is_string($oracle_date)) return $oracle_date;
    if(is_object($oracle_date)) {
        if(method_exists($oracle_date, 'load')) {
            $oracle_date = $oracle_date->load();
        } elseif(method_exists($oracle_date, 'read')) {
            $oracle_date = $oracle_date->read($oracle_date->size());
        } else {
            $oracle_date = (string)$oracle_date;
        }
    }
    $timestamp = strtotime($oracle_date);
    if($timestamp !== false && $timestamp > 0) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    return $oracle_date;
}

if($conn && $conn_type) {
    try {
        if($conn_type == 'pdo') {
            // ==================== PDO MySQL ====================
            // Ambil data absensi per bulan
            $stmt = $conn->prepare("SELECT * FROM absensi 
                                   WHERE id_karyawan = :id AND DATE_FORMAT(tanggal, '%Y-%m') = :bulan 
                                   ORDER BY tanggal DESC");
            $stmt->execute([':id' => $id_karyawan, ':bulan' => $bulan_filter]);
            $riwayat_absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Hitung statistik
            $stmt = $conn->prepare("SELECT 
                                        COUNT(CASE WHEN status IN ('Hadir', 'Terlambat') THEN 1 END) as hadir,
                                        COUNT(CASE WHEN status = 'Terlambat' THEN 1 END) as terlambat,
                                        COUNT(CASE WHEN status = 'Sakit' THEN 1 END) as sakit,
                                        COUNT(CASE WHEN status = 'Izin' THEN 1 END) as izin,
                                        COUNT(CASE WHEN status = 'Alpha' THEN 1 END) as alpha,
                                        COALESCE(SUM(jam_lembur), 0) as total_lembur,
                                        COALESCE(SUM(total_jam), 0) as total_jam
                                    FROM absensi 
                                    WHERE id_karyawan = :id AND DATE_FORMAT(tanggal, '%Y-%m') = :bulan");
            $stmt->execute([':id' => $id_karyawan, ':bulan' => $bulan_filter]);
            $statistik_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($statistik_data) {
                $statistik['hadir'] = $statistik_data['hadir'] ?? 0;
                $statistik['terlambat'] = $statistik_data['terlambat'] ?? 0;
                $statistik['sakit'] = $statistik_data['sakit'] ?? 0;
                $statistik['izin'] = $statistik_data['izin'] ?? 0;
                $statistik['alpha'] = $statistik_data['alpha'] ?? 0;
                $statistik['total_lembur'] = $statistik_data['total_lembur'] ?? 0;
                $statistik['total_jam'] = $statistik_data['total_jam'] ?? 0;
            }
            
        } else {
            // ==================== OCI8 Oracle ====================
            $bulan_oracle = $bulan_filter . '-01';
            
            // Ambil data absensi per bulan
            $sql = "SELECT ID_ABSENSI, ID_KARYAWAN, TANGGAL, JAM_MASUK, JAM_KELUAR, 
                           STATUS, TERLAMBAT, FOTO_MASUK, FOTO_PULANG, 
                           TOTAL_JAM, JAM_LEMBUR
                    FROM absensi 
                    WHERE id_karyawan = :id 
                    AND TRUNC(tanggal) BETWEEN TRUNC(TO_DATE(:bulan_awal, 'YYYY-MM-DD')) 
                                          AND LAST_DAY(TRUNC(TO_DATE(:bulan_awal, 'YYYY-MM-DD')))
                    ORDER BY tanggal DESC";
            
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':id', $id_karyawan);
            oci_bind_by_name($stmt, ':bulan_awal', $bulan_oracle);
            oci_execute($stmt);
            
            $riwayat_absensi = [];
            while($row = oci_fetch_assoc($stmt)) {
                // Konversi tanggal
                if(isset($row['TANGGAL']) && is_object($row['TANGGAL'])) {
                    $row['TANGGAL'] = convertOracleDateToString($row['TANGGAL']);
                }
                if(isset($row['JAM_MASUK']) && is_object($row['JAM_MASUK'])) {
                    $row['JAM_MASUK'] = convertOracleDateToString($row['JAM_MASUK']);
                }
                if(isset($row['JAM_KELUAR']) && is_object($row['JAM_KELUAR'])) {
                    $row['JAM_KELUAR'] = convertOracleDateToString($row['JAM_KELUAR']);
                }
                $riwayat_absensi[] = $row;
            }
            oci_free_statement($stmt);
            
            // Hitung statistik untuk Oracle
            $sql_stats = "SELECT 
                                COUNT(CASE WHEN status IN ('Hadir', 'Terlambat') THEN 1 END) as hadir,
                                COUNT(CASE WHEN status = 'Terlambat' THEN 1 END) as terlambat,
                                COUNT(CASE WHEN status = 'Sakit' THEN 1 END) as sakit,
                                COUNT(CASE WHEN status = 'Izin' THEN 1 END) as izin,
                                COUNT(CASE WHEN status = 'Alpha' THEN 1 END) as alpha,
                                NVL(SUM(jam_lembur), 0) as total_lembur,
                                NVL(SUM(total_jam), 0) as total_jam
                          FROM absensi 
                          WHERE id_karyawan = :id 
                          AND TRUNC(tanggal) BETWEEN TRUNC(TO_DATE(:bulan_awal, 'YYYY-MM-DD')) 
                                                AND LAST_DAY(TRUNC(TO_DATE(:bulan_awal, 'YYYY-MM-DD')))";
            
            $stmt_stats = oci_parse($conn, $sql_stats);
            oci_bind_by_name($stmt_stats, ':id', $id_karyawan);
            oci_bind_by_name($stmt_stats, ':bulan_awal', $bulan_oracle);
            oci_execute($stmt_stats);
            
            $statistik_data = oci_fetch_assoc($stmt_stats);
            if($statistik_data) {
                $statistik['hadir'] = (int)($statistik_data['HADIR'] ?? 0);
                $statistik['terlambat'] = (int)($statistik_data['TERLAMBAT'] ?? 0);
                $statistik['sakit'] = (int)($statistik_data['SAKIT'] ?? 0);
                $statistik['izin'] = (int)($statistik_data['IZIN'] ?? 0);
                $statistik['alpha'] = (int)($statistik_data['ALPHA'] ?? 0);
                $statistik['total_lembur'] = (float)($statistik_data['TOTAL_LEMBUR'] ?? 0);
                $statistik['total_jam'] = (float)($statistik_data['TOTAL_JAM'] ?? 0);
            }
            oci_free_statement($stmt_stats);
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// Hitung persentase kehadiran
$total_hari_kerja = date('t', strtotime($bulan_filter . '-01'));
$total_hadir_efektif = $statistik['hadir'];
$persen_kehadiran = $total_hari_kerja > 0 ? round(($total_hadir_efektif / $total_hari_kerja) * 100) : 0;

// Hari dalam bahasa Indonesia
$hari_indonesia = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => "Jum'at",
    'Saturday' => 'Sabtu'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Riwayat Absensi - Ck-Ck Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f5f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        /* Navbar */
        .navbar-custom { background: #2C1810; color: white; padding: 15px 0; }
        .btn-logout { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; }
        .btn-logout:hover { background: #c82333; color: white; }
        
        /* Sidebar */
        .sidebar { background: white; min-height: calc(100vh - 70px); padding: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .sidebar a { display: block; padding: 12px 15px; color: #333; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #C8A951; color: white; }
        
        /* Cards */
        .card-custom { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-custom h5 { margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        
        /* Stats Card */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-item { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 12px; transition: all 0.3s ease; }
        .stat-item:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .stat-number { font-size: 1.8rem; font-weight: 800; }
        .stat-label { font-size: 0.75rem; color: #888; margin-top: 5px; }
        .stat-hadir { color: #28a745; }
        .stat-terlambat { color: #ffc107; }
        .stat-sakit { color: #17a2b8; }
        .stat-izin { color: #6c757d; }
        .stat-alpha { color: #dc3545; }
        .stat-lembur { color: #C8A951; }
        
        /* Progress */
        .progress-container { background: #e0e0e0; border-radius: 10px; height: 8px; overflow: hidden; margin: 10px 0; }
        .progress-fill { background: linear-gradient(90deg, #28a745, #20c997); height: 100%; border-radius: 10px; transition: width 0.5s ease; }
        
        /* Table */
        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom thead th { background: #f8f9fa; padding: 12px; font-weight: 600; font-size: 0.8rem; border-bottom: 2px solid #e0e0e0; }
        .table-custom tbody td { padding: 12px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; }
        .table-custom tbody tr:hover { background: #fef9f0; }
        
        /* Badge Status */
        .status-hadir, .status-terlambat, .status-sakit, .status-izin, .status-alpha {
            padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block;
        }
        .status-hadir { background: #d4edda; color: #155724; }
        .status-terlambat { background: #fff3cd; color: #856404; }
        .status-sakit { background: #d1ecf1; color: #0c5460; }
        .status-izin { background: #e2e3e5; color: #383d41; }
        .status-alpha { background: #f8d7da; color: #721c24; }
        
        /* Filter Form */
        .filter-form { background: #f8f9fa; border-radius: 12px; padding: 15px; margin-bottom: 20px; }
        .btn-filter { background: #C8A951; color: #2C1810; border: none; border-radius: 10px; padding: 10px 20px; font-weight: 600; }
        .btn-filter:hover { background: #b8962e; color: white; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 40px; color: #888; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: 0.5; }
        
        /* Alert */
        .alert-custom { background: #e8f5e9; border-left: 4px solid #28a745; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; margin-bottom: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .table-custom { font-size: 0.7rem; }
            .table-custom thead th, .table-custom tbody td { padding: 8px; }
        }
        
        @media print {
            .sidebar, .navbar-custom, .filter-form, .btn-logout, .btn-filter, .btn-outline-secondary, .btn-outline-primary {
                display: none !important;
            }
            .main-content { margin: 0; padding: 0; }
            .col-md-9 { width: 100%; }
            .card-custom { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar-custom">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div><i class="fas fa-coffee"></i> Ck-Ck Coffee - Karyawan Panel</div>
                <div><span>Halo, <?= htmlspecialchars($_SESSION['nama'] ?? 'Karyawan') ?></span> <a href="../logout.php" class="btn-logout ms-3">Logout</a></div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
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
            
            <!-- Content -->
            <div class="col-md-9">
                <div class="content p-3 p-md-4">
                    <h2 class="mb-2"><i class="fas fa-history"></i> Riwayat Absensi</h2>
                    <p class="text-muted mb-4">Lihat dan pantau riwayat kehadiran Anda</p>
                    
                    <?php if(isset($error) && $error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Filter Form -->
                    <div class="filter-form">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Pilih Bulan</label>
                                <input type="month" name="bulan" class="form-control" value="<?= $bulan_filter ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn-filter w-100"><i class="fas fa-search"></i> Tampilkan</button>
                            </div>
                            <div class="col-md-3">
                                <a href="riwayat.php" class="btn btn-outline-secondary w-100"><i class="fas fa-sync-alt"></i> Reset</a>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-primary w-100" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Statistik Ringkasan -->
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number stat-hadir"><?= number_format($statistik['hadir']) ?></div>
                            <div class="stat-label">✅ Hadir</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number stat-terlambat"><?= number_format($statistik['terlambat']) ?></div>
                            <div class="stat-label">⚠️ Terlambat</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number stat-sakit"><?= number_format($statistik['sakit']) ?></div>
                            <div class="stat-label">🤒 Sakit</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number stat-izin"><?= number_format($statistik['izin']) ?></div>
                            <div class="stat-label">📝 Izin</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number stat-alpha"><?= number_format($statistik['alpha']) ?></div>
                            <div class="stat-label">❌ Alpha</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number stat-lembur"><?= number_format($statistik['total_lembur'], 1) ?></div>
                            <div class="stat-label">⭐ Lembur (Jam)</div>
                        </div>
                    </div>
                    
                    <!-- Ringkasan Kehadiran -->
                    <div class="card-custom">
                        <h5><i class="fas fa-chart-line"></i> Ringkasan Kehadiran</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <small>Total Kehadiran</small>
                                        <small><?= $total_hadir_efektif ?> / <?= $total_hari_kerja ?> hari</small>
                                    </div>
                                    <div class="progress-container">
                                        <div class="progress-fill" style="width: <?= $persen_kehadiran ?>%"></div>
                                    </div>
                                    <div class="text-center mt-1">
                                        <strong><?= $persen_kehadiran ?>%</strong> Kehadiran
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <small>Total Jam Kerja</small>
                                        <small><?= number_format($statistik['total_jam'], 1) ?> jam</small>
                                    </div>
                                    <div class="progress-container">
                                        <div class="progress-fill" style="width: <?= min(100, ($statistik['total_jam'] / 160) * 100) ?>%"></div>
                                    </div>
                                    <div class="text-center mt-1">
                                        <strong><?= number_format($statistik['total_jam'], 1) ?></strong> Jam Kerja
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabel Riwayat Absensi -->
                    <div class="card-custom">
                        <h5><i class="fas fa-table"></i> Data Absensi Bulan <?= $nama_bulan[$bulan_num] ?> <?= $tahun ?></h5>
                        <div class="table-responsive">
                            <table class="table-custom">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Hari</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Status</th>
                                        <th>Terlambat</th>
                                        <th>Lembur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($riwayat_absensi)): ?>
                                        <tr>
                                            <td colspan="7">
                                                <div class="empty-state">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <p>Belum ada data absensi untuk bulan <?= $nama_bulan[$bulan_num] ?> <?= $tahun ?></p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($riwayat_absensi as $r): 
                                            // Parse tanggal dengan aman
                                            $tanggal_str = $r['TANGGAL'];
                                            if(is_object($tanggal_str)) {
                                                $tanggal_str = convertOracleDateToString($tanggal_str);
                                            }
                                            $hari = date('l', strtotime($tanggal_str));
                                            $hari_indo = $hari_indonesia[$hari] ?? $hari;
                                            
                                            // Parse jam masuk
                                            $jam_masuk_str = $r['JAM_MASUK'] ?? '';
                                            if(is_object($jam_masuk_str)) {
                                                $jam_masuk_str = convertOracleDateToString($jam_masuk_str);
                                            }
                                            
                                            // Parse jam keluar
                                            $jam_keluar_str = $r['JAM_KELUAR'] ?? '';
                                            if(is_object($jam_keluar_str)) {
                                                $jam_keluar_str = convertOracleDateToString($jam_keluar_str);
                                            }
                                            
                                            $status = $r['STATUS'] ?? 'Alpha';
                                            $terlambat = $r['TERLAMBAT'] ?? 0;
                                            $jam_lembur = $r['JAM_LEMBUR'] ?? 0;
                                        ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($tanggal_str)) ?></td>
                                            <td><?= $hari_indo ?></td>
                                            <td><?= !empty($jam_masuk_str) ? date('H:i', strtotime($jam_masuk_str)) : '-' ?></td>
                                            <td><?= !empty($jam_keluar_str) ? date('H:i', strtotime($jam_keluar_str)) : '-' ?></td>
                                            <td><span class="status-<?= strtolower($status) ?>"><?= $status ?></span></td>
                                            <td><?= $terlambat > 0 ? '<span class="text-danger">' . $terlambat . ' menit</span>' : '-' ?></td>
                                            <td><?= $jam_lembur > 0 ? '<span class="text-warning">⭐ ' . $jam_lembur . ' jam</span>' : '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Keterangan -->
                    <div class="card-custom">
                        <h5><i class="fas fa-info-circle"></i> Keterangan</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li><span class="status-hadir">Hadir</span> - Hadir tepat waktu</li>
                                    <li><span class="status-terlambat">Terlambat</span> - Terlambat masuk kerja</li>
                                    <li><span class="status-sakit">Sakit</span> - Izin sakit</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li><span class="status-izin">Izin</span> - Izin tidak masuk</li>
                                    <li><span class="status-alpha">Alpha</span> - Tidak hadir tanpa keterangan</li>
                                    <li><span class="stat-lembur">⭐ Lembur</span> - Jam kerja di atas 8 jam</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
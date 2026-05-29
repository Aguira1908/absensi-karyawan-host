<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../../assets/config/koneksi.php';

$message = '';
$error = '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Proses approve/reject
if(isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    $new_status = ($action == 'approve') ? 'Disetujui' : 'Ditolak';
    
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("UPDATE jadwal SET status = :status WHERE id_jadwal = :id");
            $stmt->execute([':status' => $new_status, ':id' => $id]);
            $message = "Jadwal berhasil di" . ($action == 'approve' ? 'setujui' : 'tolak');
        } else {
            $stmt = oci_parse($conn, "UPDATE jadwal SET status = :status WHERE id_jadwal = :id");
            oci_bind_by_name($stmt, ':status', $new_status);
            oci_bind_by_name($stmt, ':id', $id);
            oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
            oci_free_statement($stmt);
            $message = "Jadwal berhasil di" . ($action == 'approve' ? 'setujui' : 'tolak');
        }
        echo "<script>setTimeout(()=>{window.location.href='index.php'},1000)</script>";
    } catch(Exception $e) {
        $error = "Gagal update: " . $e->getMessage();
    }
}

// Ambil data jadwal yang diajukan karyawan
$jadwal = [];
if($conn_type == 'pdo') {
    $sql = "SELECT j.*, k.nama as nama_karyawan, k.jabatan 
            FROM jadwal j 
            JOIN karyawan k ON j.id_karyawan = k.id_karyawan 
            ORDER BY FIELD(j.status, 'Menunggu', 'Disetujui', 'Ditolak'), j.tanggal DESC";
    if($status_filter) {
        $sql = "SELECT j.*, k.nama as nama_karyawan, k.jabatan 
                FROM jadwal j 
                JOIN karyawan k ON j.id_karyawan = k.id_karyawan 
                WHERE j.status = :status 
                ORDER BY j.tanggal DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':status' => $status_filter]);
    } else {
        $stmt = $conn->query($sql);
    }
    $jadwal = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sql = "SELECT j.*, TO_CHAR(j.tanggal, 'YYYY-MM-DD') as tgl, k.nama as nama_karyawan, k.jabatan 
            FROM jadwal j 
            JOIN karyawan k ON j.id_karyawan = k.id_karyawan 
            ORDER BY CASE j.status WHEN 'Menunggu' THEN 1 WHEN 'Disetujui' THEN 2 ELSE 3 END, j.tanggal DESC";
    if($status_filter) {
        $sql = "SELECT j.*, TO_CHAR(j.tanggal, 'YYYY-MM-DD') as tgl, k.nama as nama_karyawan, k.jabatan 
                FROM jadwal j 
                JOIN karyawan k ON j.id_karyawan = k.id_karyawan 
                WHERE j.status = :status 
                ORDER BY j.tanggal DESC";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':status', $status_filter);
    } else {
        $stmt = oci_parse($conn, $sql);
    }
    oci_execute($stmt);
    while($row = oci_fetch_assoc($stmt)) {
        $jadwal[] = $row;
    }
    oci_free_statement($stmt);
}

// Statistik
$total_menunggu = 0;
$total_disetujui = 0;
$total_ditolak = 0;
foreach($jadwal as $j) {
    if($j['STATUS'] == 'Menunggu') $total_menunggu++;
    elseif($j['STATUS'] == 'Disetujui') $total_disetujui++;
    elseif($j['STATUS'] == 'Ditolak') $total_ditolak++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Kelola Pengajuan Jadwal - Admin Ck-Ck Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        
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
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        .sidebar-header .logo-icon i { font-size: 1.8rem; color: white; }
        .sidebar-header h4 { font-family: 'Playfair Display', serif; font-size: 1.2rem; margin-bottom: 5px; }
        .sidebar-header p { font-size: 0.7rem; opacity: 0.7; }
        .sidebar-menu { padding: 20px; }
        .sidebar-menu .menu-title { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; opacity: 0.5; margin-bottom: 15px; }
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
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(200,169,81,0.2); color: #C8A951; }
        .sidebar-menu a i { width: 22px; font-size: 1rem; }
        
        /* Main Content */
        .main-content { margin-left: 280px; padding: 20px; }
        
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
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; text-align: center; transition: all 0.3s ease; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .stat-card h5 { font-size: 0.9rem; color: #666; margin-bottom: 10px; }
        .stat-card h2 { font-size: 2rem; font-weight: 800; }
        .stat-card .text-warning { color: #ffc107; }
        .stat-card .text-success { color: #28a745; }
        .stat-card .text-danger { color: #dc3545; }
        
        /* Card */
        .card-custom { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: none; }
        .card-title { font-weight: 700; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        
        /* Table */
        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom thead th { background: #f8f9fa; padding: 12px; font-weight: 600; font-size: 0.85rem; border-bottom: 2px solid #e0e0e0; }
        .table-custom tbody td { padding: 12px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; }
        .table-custom tbody tr:hover { background: #fef9f0; }
        
        /* Badges */
        .badge-menunggu { background: #ffc107; color: #333; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .badge-disetujui { background: #28a745; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .badge-ditolak { background: #dc3545; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        
        /* Filter */
        .filter-form { background: #f8f9fa; border-radius: 15px; padding: 15px; margin-bottom: 20px; display: flex; justify-content: flex-end; }
        .form-select { width: auto; min-width: 150px; border-radius: 10px; border: 1px solid #e0e0e0; padding: 8px 15px; }
        
        /* Button */
        .btn-sm { padding: 5px 12px; border-radius: 8px; font-size: 0.75rem; margin: 0 3px; }
        
        @media (max-width: 992px) { .sidebar { left: -280px; } .main-content { margin-left: 0; } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .table-custom { font-size: 0.75rem; } }
        
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .stat-card, .card-custom { animation: fadeInUp 0.5s ease forwards; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-mug-hot"></i></div>
            <h4>Ck-Ck Coffee</h4>
            <p>Admin Panel</p>
        </div>
        <div class="sidebar-menu">
            <div class="menu-title">MAIN MENU</div>
            <a href="../dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="../karyawan/"><i class="fas fa-users"></i> <span>Kelola Karyawan</span></a>
            <a href="index.php" class="active"><i class="fas fa-calendar"></i> <span>Pengajuan Jadwal</span></a>
            <a href="../absensi/"><i class="fas fa-fingerprint"></i> <span>Monitoring Absensi</span></a>
            <a href="../tukar_shift.php"><i class="fas fa-exchange-alt"></i> <span>Tukar Shift</span></a>
            <a href="../laporan/"><i class="fas fa-chart-line"></i> <span>Laporan</span></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="page-title">
                <h2><i class="fas fa-calendar-check"></i> Kelola Pengajuan Jadwal</h2>
                <p>Setujui atau tolak pengajuan jadwal shift dari karyawan</p>
            </div>
            <div class="user-info">
                <div class="user-name">
                    <div class="name"><?= htmlspecialchars($_SESSION['nama'] ?? 'Administrator') ?></div>
                    <div class="role">Administrator</div>
                </div>
                <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
                <a href="../../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <?php if($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h5><i class="fas fa-clock"></i> Menunggu</h5>
                <h2 class="text-warning"><?= $total_menunggu ?></h2>
                <small>Perlu persetujuan</small>
            </div>
            <div class="stat-card">
                <h5><i class="fas fa-check-circle"></i> Disetujui</h5>
                <h2 class="text-success"><?= $total_disetujui ?></h2>
                <small>Sudah disetujui</small>
            </div>
            <div class="stat-card">
                <h5><i class="fas fa-times-circle"></i> Ditolak</h5>
                <h2 class="text-danger"><?= $total_ditolak ?></h2>
                <small>Pengajuan ditolak</small>
            </div>
        </div>

        <!-- Daftar Pengajuan -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="card-title mb-0"><i class="fas fa-list"></i> Daftar Pengajuan Jadwal</h5>
                <div class="filter-form p-0">
                    <select class="form-select" onchange="location.href='?status='+this.value">
                        <option value="">📋 Semua Status</option>
                        <option value="Menunggu" <?= $status_filter=='Menunggu'?'selected':'' ?>>⏳ Menunggu</option>
                        <option value="Disetujui" <?= $status_filter=='Disetujui'?'selected':'' ?>>✅ Disetujui</option>
                        <option value="Ditolak" <?= $status_filter=='Ditolak'?'selected':'' ?>>❌ Ditolak</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Karyawan</th>
                            <th>Jabatan</th>
                            <th>Shift</th>
                            <th>Jam Kerja</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($jadwal)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-calendar-times fa-2x mb-2 d-block text-muted"></i>
                                    Belum ada pengajuan jadwal
                                 </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($jadwal as $j): 
                                $status = $j['STATUS'];
                                $badge_class = $status == 'Disetujui' ? 'badge-disetujui' : ($status == 'Ditolak' ? 'badge-ditolak' : 'badge-menunggu');
                                $badge_icon = $status == 'Disetujui' ? '✅' : ($status == 'Ditolak' ? '❌' : '⏳');
                            ?>
                            <tr>
                                <td><?= date('d-m-Y', strtotime($j['TANGGAL'])) ?></td>
                                <td><strong><?= htmlspecialchars($j['NAMA_KARYAWAN']) ?></strong></td>
                                <td><?= htmlspecialchars($j['JABATAN']) ?></td>
                                <td><?= $j['SHIFT'] ?></td>
                                <td><?= $j['JAM_MULAI'] ?? '-' ?> - <?= $j['JAM_SELESAI'] ?? '-' ?></td>
                                <td><?= htmlspecialchars($j['KETERANGAN'] ?? '-') ?></td>
                                <td><span class="<?= $badge_class ?>"><?= $badge_icon ?> <?= $status ?></span></td>
                                <td>
                                    <?php if($status == 'Menunggu'): ?>
                                        <a href="?action=approve&id=<?= $j['ID_JADWAL'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Setujui jadwal ini?')">
                                            <i class="fas fa-check"></i> Setujui
                                        </a>
                                        <a href="?action=reject&id=<?= $j['ID_JADWAL'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tolak jadwal ini?')">
                                            <i class="fas fa-times"></i> Tolak
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                 </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto hide alert
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 3000);
            });
        }, 500);
    </script>
</body>
</html>
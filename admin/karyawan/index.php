<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../../assets/config/koneksi.php';

$message = '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Hitung total data
if($conn_type == 'pdo') {
    if($search) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM karyawan WHERE nama LIKE :search OR jabatan LIKE :search OR no_hp LIKE :search");
        $stmt->execute([':search' => "%$search%"]);
    } else {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM karyawan");
    }
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Ambil data
    if($search) {
        $stmt = $conn->prepare("SELECT * FROM karyawan WHERE nama LIKE :search OR jabatan LIKE :search OR no_hp LIKE :search ORDER BY id_karyawan DESC OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY");
        $stmt->bindParam(':search', $search_val);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $search_val = "%$search%";
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT * FROM karyawan ORDER BY id_karyawan DESC OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY");
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    }
    $karyawan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil statistik karyawan
    $stmt = $conn->query("SELECT COUNT(*) as total FROM karyawan WHERE status = 'Aktif'");
    $aktif = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM karyawan WHERE status = 'Tidak Aktif'");
    $tidak_aktif = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Hitung jumlah berdasarkan jabatan
    $stmt = $conn->query("SELECT jabatan, COUNT(*) as jumlah FROM karyawan GROUP BY jabatan");
    $jabatan_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // OCI8
    if($search) {
        $sql = "SELECT * FROM karyawan WHERE nama LIKE '%$search%' OR jabatan LIKE '%$search%' OR no_hp LIKE '%$search%' ORDER BY id_karyawan DESC";
    } else {
        $sql = "SELECT * FROM karyawan ORDER BY id_karyawan DESC";
    }
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $karyawan = [];
    while($row = oci_fetch_assoc($stmt)) {
        $karyawan[] = $row;
    }
    $total = count($karyawan);
    
    $sql = "SELECT COUNT(*) as total FROM karyawan WHERE status = 'Aktif'";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    $aktif = $row['TOTAL'] ?? 0;
    
    $sql = "SELECT COUNT(*) as total FROM karyawan WHERE status = 'Tidak Aktif'";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    $tidak_aktif = $row['TOTAL'] ?? 0;
    
    $sql = "SELECT jabatan, COUNT(*) as jumlah FROM karyawan GROUP BY jabatan";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $jabatan_stats = [];
    while($row = oci_fetch_assoc($stmt)) {
        $jabatan_stats[] = $row;
    }
}
$total_pages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Karyawan - Admin Ck-Ck Coffee</title>
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
            align-items: center;
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
            color: #2C1810;
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
            color: #C8A951;
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

        /* Search Form */
        .search-form {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-control {
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
        }

        .form-control:focus {
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

        /* Badges */
        .badge-aktif {
            background: #28a745;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-tidak-aktif {
            background: #dc3545;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Action Buttons */
        .btn-action {
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            margin: 0 2px;
        }

        /* Pagination */
        .pagination-custom {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination-custom .page-link {
            padding: 8px 15px;
            border-radius: 10px;
            color: #2C1810;
            border: 1px solid #e0e0e0;
            text-decoration: none;
        }

        .pagination-custom .page-link:hover {
            background: #C8A951;
            color: white;
            border-color: #C8A951;
        }

        .pagination-custom .active .page-link {
            background: #C8A951;
            color: white;
            border-color: #C8A951;
        }

        /* Jabatan Chart */
        .jabatan-list {
            margin-top: 10px;
        }

        .jabatan-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .jabatan-name {
            font-weight: 500;
            font-size: 0.85rem;
        }

        .jabatan-count {
            background: #fdf8f0;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #C8A951;
        }

        /* Alert */
        .alert-custom {
            background: #d4edda;
            color: #155724;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                left: -280px;
            }
            .main-content {
                margin-left: 0;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            .btn-action span {
                display: none;
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

        .stat-card, .card-custom {
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
            <a href="index.php" class="active">
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
            <a href="../laporan/">
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
                <h2><i class="fas fa-users"></i> Manajemen Karyawan</h2>
                <p>Kelola data karyawan Ck-Ck Coffee</p>
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

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Karyawan</h3>
                    <div class="number"><?= number_format($total) ?></div>
                    <small>Keseluruhan</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Aktif</h3>
                    <div class="number" style="color:#28a745"><?= $aktif ?></div>
                    <small>Bekerja</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Tidak Aktif</h3>
                    <div class="number" style="color:#dc3545"><?= $tidak_aktif ?></div>
                    <small>Nonaktif</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-slash"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Jabatan</h3>
                    <div class="number"><?= count($jabatan_stats) ?></div>
                    <small>Total Jabatan</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
            </div>
        </div>

        <!-- Alert Message -->
        <?php if($message): ?>
            <div class="alert-custom">
                <i class="fas fa-check-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Form Cari & Tombol Tambah -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="card-title mb-0"><i class="fas fa-search"></i> Data Karyawan</h5>
                <a href="tambah.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Karyawan
                </a>
            </div>
            
            <form method="GET" class="search-form">
                <div class="row g-3">
                    <div class="col-md-8">
                        <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan nama, jabatan, atau nomor HP..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary me-2" type="submit">
                            <i class="fas fa-search"></i> Cari
                        </button>
                        <?php if($search): ?>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Tabel Karyawan -->
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>No. HP</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($karyawan)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-user-times fa-2x mb-2 d-block text-muted"></i>
                                    Tidak ada data karyawan
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($karyawan as $row): ?>
                            <tr>
                                <td><?= $row['ID_KARYAWAN'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['NAMA']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($row['JABATAN']) ?></td>
                                <td><?= htmlspecialchars($row['NO_HP']) ?></td>
                                <td><?= htmlspecialchars($row['EMAIL']) ?></td>
                                <td>
                                    <span class="<?= $row['STATUS'] == 'Aktif' ? 'badge-aktif' : 'badge-tidak-aktif' ?>">
                                        <?= $row['STATUS'] ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?= $row['ID_KARYAWAN'] ?>" class="btn btn-sm btn-warning btn-action" title="Edit">
                                        <i class="fas fa-edit"></i> <span>Edit</span>
                                    </a>
                                    <a href="hapus.php?id=<?= $row['ID_KARYAWAN'] ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Yakin ingin menghapus karyawan ini?')" title="Hapus">
                                        <i class="fas fa-trash"></i> <span>Hapus</span>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination-custom">
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                    <a class="page-link <?= $i==$page?'active':'' ?>" 
                       href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Statistik Jabatan -->
        <div class="card-custom">
            <h5 class="card-title"><i class="fas fa-chart-pie"></i> Statistik Jabatan</h5>
            <div class="jabatan-list">
                <?php foreach($jabatan_stats as $jabatan): ?>
                <div class="jabatan-item">
                    <span class="jabatan-name">
                        <i class="fas fa-briefcase text-muted me-2"></i>
                        <?= htmlspecialchars($jabatan['JABATAN']) ?>
                    </span>
                    <span class="jabatan-count"><?= $jabatan['JUMLAH'] ?> orang</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
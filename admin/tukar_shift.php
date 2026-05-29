<?php
session_start();

// Cek apakah sudah login sebagai admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../assets/config/koneksi.php';

$message = '';
$error = '';

// =============================================
// 1. AMBIL SEMUA PENGAJUAN TUKAR SHIFT YANG MENUNGGU
// =============================================
$pengajuan = [];
if($conn && $conn_type) {
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("SELECT ts.*, 
                                           k1.nama as nama_pengaju, k1.shift_default as shift_pengaju,
                                           k2.nama as nama_tujuan, k2.shift_default as shift_tujuan
                                    FROM tukar_shift ts 
                                    JOIN karyawan k1 ON ts.id_karyawan_pengaju = k1.id_karyawan 
                                    JOIN karyawan k2 ON ts.id_karyawan_tujuan = k2.id_karyawan 
                                    WHERE ts.status = 'Menunggu'
                                    ORDER BY ts.created_at ASC");
            $stmt->execute();
            $pengajuan = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// =============================================
// 2. PROSES APPROVE
// =============================================
if(isset($_GET['approve'])) {
    $id = $_GET['approve'];
    
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("SELECT * FROM tukar_shift WHERE id_tukar = :id");
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($data) {
                // Update status pengajuan
                $stmt = $conn->prepare("UPDATE tukar_shift SET status = 'Disetujui' WHERE id_tukar = :id");
                $stmt->execute([':id' => $id]);
                
                // Hapus jadwal khusus yang sudah ada untuk tanggal tersebut (jika ada)
                $stmt = $conn->prepare("DELETE FROM jadwal_khusus WHERE id_karyawan = :id_karyawan AND tanggal = :tanggal");
                $stmt->execute([':id_karyawan' => $data['ID_KARYAWAN_PENGAJU'], ':tanggal' => $data['TANGGAL']]);
                $stmt->execute([':id_karyawan' => $data['ID_KARYAWAN_TUJUAN'], ':tanggal' => $data['TANGGAL']]);
                
                // Buat jadwal khusus untuk tanggal tersebut
                // Jadwal untuk pengaju (menggunakan shift tujuan)
                $stmt = $conn->prepare("INSERT INTO jadwal_khusus (id_karyawan, tanggal, shift, jenis, id_tukar) 
                                       VALUES (:id_karyawan, :tanggal, :shift, 'Tukar Shift', :id_tukar)");
                $stmt->execute([
                    ':id_karyawan' => $data['ID_KARYAWAN_PENGAJU'],
                    ':tanggal' => $data['TANGGAL'],
                    ':shift' => $data['SHIFT_TUJUAN'],
                    ':id_tukar' => $id
                ]);
                
                // Jadwal untuk tujuan (menggunakan shift pengaju)
                $stmt = $conn->prepare("INSERT INTO jadwal_khusus (id_karyawan, tanggal, shift, jenis, id_tukar) 
                                       VALUES (:id_karyawan, :tanggal, :shift, 'Tukar Shift', :id_tukar)");
                $stmt->execute([
                    ':id_karyawan' => $data['ID_KARYAWAN_TUJUAN'],
                    ':tanggal' => $data['TANGGAL'],
                    ':shift' => $data['SHIFT_PENGAJU'],
                    ':id_tukar' => $id
                ]);
            }
        }
    } catch(Exception $e) {
        $error = "Gagal menyetujui: " . $e->getMessage();
    }
    header("Location: tukar_shift.php?msg=approved");
    exit();
}

// =============================================
// 3. PROSES REJECT
// =============================================
if(isset($_GET['reject'])) {
    $id = $_GET['reject'];
    
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("UPDATE tukar_shift SET status = 'Ditolak' WHERE id_tukar = :id");
            $stmt->execute([':id' => $id]);
        }
    } catch(Exception $e) {
        $error = "Gagal menolak: " . $e->getMessage();
    }
    header("Location: tukar_shift.php?msg=rejected");
    exit();
}

// =============================================
// 4. AMBIL SEMUA RIWAYAT PENGAJUAN (SUDAH DIPROSES)
// =============================================
$riwayat = [];
if($conn && $conn_type) {
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("SELECT ts.*, 
                                           k1.nama as nama_pengaju, k1.shift_default as shift_pengaju,
                                           k2.nama as nama_tujuan, k2.shift_default as shift_tujuan
                                    FROM tukar_shift ts 
                                    JOIN karyawan k1 ON ts.id_karyawan_pengaju = k1.id_karyawan 
                                    JOIN karyawan k2 ON ts.id_karyawan_tujuan = k2.id_karyawan 
                                    WHERE ts.status != 'Menunggu'
                                    ORDER BY ts.created_at DESC
                                    LIMIT 20");
            $stmt->execute();
            $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(Exception $e) {
        // Error handling
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Approval Tukar Shift - Admin Ck-Ck Coffee</title>
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
        
        /* Badge Notifikasi */
        .badge-notif {
            background: #dc3545;
            color: white;
            border-radius: 50px;
            padding: 2px 8px;
            font-size: 0.7rem;
            margin-left: auto;
        }
        
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
        
        /* Cards */
        .card-custom { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: none; }
        .card-title { font-weight: 700; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        
        /* Buttons */
        .btn-approve { background: #28a745; color: white; border: none; padding: 8px 20px; border-radius: 8px; transition: all 0.3s ease; }
        .btn-approve:hover { background: #218838; transform: translateY(-2px); }
        .btn-reject { background: #dc3545; color: white; border: none; padding: 8px 20px; border-radius: 8px; transition: all 0.3s ease; }
        .btn-reject:hover { background: #c82333; transform: translateY(-2px); }
        
        /* Badge */
        .badge-menunggu { background: #ffc107; color: #333; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .badge-disetujui { background: #28a745; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .badge-ditolak { background: #dc3545; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        
        /* Pengajuan Card */
        .pengajuan-card { background: #fef9f0; border-radius: 16px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #C8A951; transition: all 0.3s ease; }
        .pengajuan-card:hover { transform: translateX(5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 40px; color: #888; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: 0.5; }
        
        /* Table */
        .table-custom { width: 100%; }
        .table-custom thead th { background: #f8f9fa; padding: 12px; font-weight: 600; font-size: 0.8rem; border-bottom: 2px solid #e0e0e0; }
        .table-custom tbody td { padding: 12px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar { left: -280px; }
            .main-content { margin-left: 0; }
        }
        @media (max-width: 768px) {
            .pengajuan-card .row > div { margin-bottom: 10px; }
            .table-custom { font-size: 0.75rem; }
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
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
            <a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="../karyawan/"><i class="fas fa-users"></i> <span>Kelola Karyawan</span></a>
            <a href="../jadwal/"><i class="fas fa-calendar"></i> <span>Kelola Jadwal</span></a>
            <a href="../absensi/"><i class="fas fa-fingerprint"></i> <span>Monitoring Absensi</span></a>
            <a href="tukar_shift.php" class="active">
                <i class="fas fa-exchange-alt"></i> <span>Tukar Shift</span>
                <?php if(count($pengajuan) > 0): ?>
                    <span class="badge-notif"><?= count($pengajuan) ?></span>
                <?php endif; ?>
            </a>
            <a href="../laporan/"><i class="fas fa-chart-line"></i> <span>Laporan</span></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="page-title">
                <h2><i class="fas fa-exchange-alt"></i> Approval Tukar Shift</h2>
                <p>Setujui atau tolak permintaan tukar shift karyawan</p>
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

        <!-- Alert Messages -->
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'approved'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> ✅ Pengajuan tukar shift berhasil disetujui! Jadwal telah diperbarui.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'rejected'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-times-circle"></i> ❌ Pengajuan tukar shift ditolak.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- Pengajuan Menunggu -->
        <div class="card-custom">
            <h5 class="card-title"><i class="fas fa-clock"></i> Pengajuan Menunggu 
                <span class="badge bg-warning text-dark ms-2"><?= count($pengajuan) ?></span>
            </h5>
            
            <?php if(empty($pengajuan)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>Tidak ada pengajuan tukar shift yang menunggu</p>
                </div>
            <?php else: ?>
                <?php foreach($pengajuan as $p): ?>
                    <div class="pengajuan-card">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="text-center">
                                        <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                        <div class="small">Pengaju</div>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($p['NAMA_PENGAJU']) ?></strong>
                                        <div class="small text-muted">
                                            Shift: <?= $p['SHIFT_PENGAJU'] ?> 
                                            <?= $p['SHIFT_PENGAJU'] == 'Pagi' ? '🌅' : ($p['SHIFT_PENGAJU'] == 'Siang' ? '☀️' : '🌙') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 text-center">
                                <i class="fas fa-arrow-right fa-2x text-muted"></i>
                                <div class="small">Tukar Shift</div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="text-center">
                                        <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                        <div class="small">Tujuan</div>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($p['NAMA_TUJUAN']) ?></strong>
                                        <div class="small text-muted">
                                            Shift: <?= $p['SHIFT_TUJUAN'] ?> 
                                            <?= $p['SHIFT_TUJUAN'] == 'Pagi' ? '🌅' : ($p['SHIFT_TUJUAN'] == 'Siang' ? '☀️' : '🌙') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 text-end">
                                <span class="badge-menunggu"><i class="fas fa-clock"></i> Menunggu</span>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> Tanggal: <?= date('d F Y', strtotime($p['TANGGAL'])) ?>
                                </small>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> Diajukan: <?= date('d M Y H:i', strtotime($p['CREATED_AT'])) ?>
                                </small>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="fas fa-exchange-alt"></i> <?= $p['SHIFT_PENGAJU'] ?> → <?= $p['SHIFT_TUJUAN'] ?>
                                </small>
                            </div>
                            <div class="col-12 mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-comment"></i> Alasan: <?= htmlspecialchars($p['ALASAN']) ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="mt-3 d-flex gap-2 justify-content-end">
                            <a href="?approve=<?= $p['ID_TUKAR'] ?>" class="btn btn-approve" onclick="return confirm('Setujui tukar shift ini? Jadwal akan otomatis diperbarui.')">
                                <i class="fas fa-check"></i> Setujui
                            </a>
                            <a href="?reject=<?= $p['ID_TUKAR'] ?>" class="btn btn-reject" onclick="return confirm('Tolak tukar shift ini?')">
                                <i class="fas fa-times"></i> Tolak
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Riwayat Pengajuan -->
        <div class="card-custom">
            <h5 class="card-title"><i class="fas fa-history"></i> Riwayat Pengajuan</h5>
            
            <?php if(empty($riwayat)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>Belum ada riwayat pengajuan</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Pengaju</th>
                                <th>Tujuan</th>
                                <th>Shift</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($riwayat as $r): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($r['TANGGAL'])) ?></td>
                                    <td><?= htmlspecialchars($r['NAMA_PENGAJU']) ?></td>
                                    <td><?= htmlspecialchars($r['NAMA_TUJUAN']) ?></td>
                                    <td><?= $r['SHIFT_PENGAJU'] ?> → <?= $r['SHIFT_TUJUAN'] ?></td>
                                    <td>
                                        <?php if($r['STATUS'] == 'Disetujui'): ?>
                                            <span class="badge-disetujui"><i class="fas fa-check"></i> Disetujui</span>
                                        <?php else: ?>
                                            <span class="badge-ditolak"><i class="fas fa-times"></i> Ditolak</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Informasi -->
        <div class="card-custom">
            <h5 class="card-title"><i class="fas fa-info-circle"></i> Informasi Tukar Shift</h5>
            <div class="row">
                <div class="col-md-6">
                    <ul class="mb-0">
                        <li><i class="fas fa-check-circle text-success"></i> Setelah menyetujui, jadwal shift akan otomatis diperbarui</li>
                        <li><i class="fas fa-clock"></i> Jadwal khusus akan berlaku hanya untuk tanggal yang diminta</li>
                        <li><i class="fas fa-bell"></i> Karyawan akan mendapat notifikasi jika pengajuan diproses</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="mb-0">
                        <li><i class="fas fa-users"></i> Pastikan koordinasi antar karyawan sudah dilakukan</li>
                        <li><i class="fas fa-calendar"></i> Pengajuan hanya untuk satu tanggal tertentu</li>
                        <li><i class="fas fa-undo-alt"></i> Shift akan kembali normal setelah tanggal tersebut</li>
                    </ul>
                </div>
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
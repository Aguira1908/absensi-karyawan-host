<?php
session_start();

// Cek apakah sudah login sebagai user
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../assets/config/koneksi.php';

$id_karyawan = $_SESSION['id_karyawan'];
$message = '';
$error = '';

// =============================================
// 1. PROSES PENGAJUAN CUTI/IZIN
// =============================================
if(isset($_POST['ajukan'])) {
    $jenis = $_POST['jenis'];
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];
    $alasan = $_POST['alasan'];
    
    // Validasi tanggal
    if($tanggal_mulai < date('Y-m-d')) {
        $error = '❌ Tanggal mulai tidak boleh kurang dari hari ini!';
    } elseif($tanggal_selesai < $tanggal_mulai) {
        $error = '❌ Tanggal selesai harus lebih besar dari tanggal mulai!';
    } else {
        try {
            if($conn_type == 'pdo') {
                // Cek apakah sudah ada pengajuan di tanggal yang sama
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengajuan_cuti 
                                       WHERE id_karyawan = :id AND status = 'Menunggu' 
                                       AND ((tanggal_mulai BETWEEN :mulai AND :selesai) OR (tanggal_selesai BETWEEN :mulai AND :selesai))");
                $stmt->execute([
                    ':id' => $id_karyawan,
                    ':mulai' => $tanggal_mulai,
                    ':selesai' => $tanggal_selesai
                ]);
                $cek = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($cek['TOTAL'] > 0) {
                    $error = '❌ Anda sudah memiliki pengajuan yang masih menunggu pada periode tersebut!';
                } else {
                    $stmt = $conn->prepare("INSERT INTO pengajuan_cuti (id_karyawan, jenis, tanggal_mulai, tanggal_selesai, alasan, status) 
                                           VALUES (:id, :jenis, :mulai, :selesai, :alasan, 'Menunggu')");
                    $stmt->execute([
                        ':id' => $id_karyawan,
                        ':jenis' => $jenis,
                        ':mulai' => $tanggal_mulai,
                        ':selesai' => $tanggal_selesai,
                        ':alasan' => $alasan
                    ]);
                    
                    $message = '✅ Pengajuan ' . ($jenis == 'Cuti' ? 'cuti' : 'izin') . ' berhasil dikirim! Menunggu persetujuan admin.';
                    echo "<script>setTimeout(()=>{window.location.href='pengajuan.php'},2000)</script>";
                }
            }
        } catch(Exception $e) {
            $error = "Gagal menyimpan pengajuan: " . $e->getMessage();
        }
    }
}

// =============================================
// 2. AMBIL RIWAYAT PENGAJUAN
// =============================================
$riwayat_pengajuan = [];
if($conn && $conn_type) {
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("SELECT * FROM pengajuan_cuti WHERE id_karyawan = :id ORDER BY created_at DESC");
            $stmt->execute([':id' => $id_karyawan]);
            $riwayat_pengajuan = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(Exception $e) {
        // Error handling
    }
}

// =============================================
// 3. HITUNG SISA CUTI (Asumsi 12 hari per tahun)
// =============================================
$sisa_cuti = 12;
$cuti_digunakan = 0;

foreach($riwayat_pengajuan as $r) {
    if($r['JENIS'] == 'Cuti' && ($r['STATUS'] == 'Disetujui' || $r['STATUS'] == 'Menunggu')) {
        $mulai = new DateTime($r['TANGGAL_MULAI']);
        $selesai = new DateTime($r['TANGGAL_SELESAI']);
        $diff = $mulai->diff($selesai);
        $cuti_digunakan += $diff->days + 1;
    }
}
$sisa_cuti = max(0, 12 - $cuti_digunakan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Pengajuan Cuti/Izin - Ck-Ck Coffee</title>
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
        
        /* Buttons */
        .btn-primary { background: #C8A951; border: none; }
        .btn-primary:hover { background: #b8962e; }
        
        /* Form */
        .form-control, .form-select { border-radius: 10px; border: 1px solid #ddd; padding: 12px; }
        .form-control:focus, .form-select:focus { border-color: #C8A951; box-shadow: 0 0 0 0.2rem rgba(200,169,81,0.25); }
        
        /* Badge Status */
        .badge-menunggu { background: #ffc107; color: #333; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; }
        .badge-disetujui { background: #28a745; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; }
        .badge-ditolak { background: #dc3545; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; }
        
        /* Info Card */
        .info-card { background: linear-gradient(135deg, #2C1810, #C8A951); color: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .info-number { font-size: 2rem; font-weight: bold; }
        
        /* History Item */
        .history-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .history-item:last-child { border-bottom: none; }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; margin-bottom: 20px; }
            .history-item { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar-custom">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-coffee"></i> Ck-Ck Coffee - Karyawan Panel
                </div>
                <div>
                    <span>Halo, <?= htmlspecialchars($_SESSION['nama']) ?></span>
                    <a href="../logout.php" class="btn-logout ms-3">Logout</a>
                </div>
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
                    <!-- Alert Messages -->
                    <?php if($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Info Sisa Cuti -->
                    <div class="info-card">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4>📋 Sisa Cuti Tahunan</h4>
                                <p class="mb-0">Periode: Januari - Desember 2025</p>
                            </div>
                            <div class="col-4 text-end">
                                <div class="info-number"><?= $sisa_cuti ?> Hari</div>
                                <small>Dari 12 hari</small>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: <?= ($cuti_digunakan / 12) * 100 ?>%"></div>
                        </div>
                        <small class="mt-2 d-block">Terpakai: <?= $cuti_digunakan ?> hari</small>
                    </div>
                    
                    <!-- Form Pengajuan -->
                    <div class="card-custom">
                        <h5><i class="fas fa-paper-plane"></i> Ajukan Cuti / Izin</h5>
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jenis Pengajuan</label>
                                    <select name="jenis" class="form-select" required>
                                        <option value="">-- Pilih Jenis --</option>
                                        <option value="Cuti">🌴 Cuti Tahunan</option>
                                        <option value="Izin">📝 Izin</option>
                                        <option value="Sakit">🤒 Sakit</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Tanggal Mulai</label>
                                    <input type="date" name="tanggal_mulai" class="form-control" min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Tanggal Selesai</label>
                                    <input type="date" name="tanggal_selesai" class="form-control" min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Alasan</label>
                                    <textarea name="alasan" class="form-control" rows="3" placeholder="Jelaskan alasan pengajuan cuti/izin..." required></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="ajukan" class="btn btn-primary w-100">
                                        <i class="fas fa-paper-plane"></i> Ajukan
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Riwayat Pengajuan -->
                    <div class="card-custom">
                        <h5><i class="fas fa-history"></i> Riwayat Pengajuan</h5>
                        <?php if(empty($riwayat_pengajuan)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada pengajuan cuti/izin</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($riwayat_pengajuan as $r): ?>
                                <div class="history-item">
                                    <div>
                                        <strong><?= $r['JENIS'] ?></strong>
                                        <div class="small text-muted">
                                            <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($r['TANGGAL_MULAI'])) ?> - <?= date('d M Y', strtotime($r['TANGGAL_SELESAI'])) ?>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            <i class="fas fa-comment"></i> <?= htmlspecialchars($r['ALASAN']) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php 
                                        $status_class = '';
                                        $status_text = $r['STATUS'];
                                        if($status_text == 'Menunggu') $status_class = 'badge-menunggu';
                                        elseif($status_text == 'Disetujui') $status_class = 'badge-disetujui';
                                        else $status_class = 'badge-ditolak';
                                        ?>
                                        <span class="<?= $status_class ?>">
                                            <?php if($status_text == 'Menunggu'): ?>
                                                <i class="fas fa-clock"></i> Menunggu
                                            <?php elseif($status_text == 'Disetujui'): ?>
                                                <i class="fas fa-check"></i> Disetujui
                                            <?php else: ?>
                                                <i class="fas fa-times"></i> Ditolak
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Informasi -->
                    <div class="card-custom">
                        <h5><i class="fas fa-info-circle"></i> Informasi</h5>
                        <ul class="mb-0">
                            <li>Pengajuan cuti minimal H-7 dari tanggal mulai</li>
                            <li>Pengajuan izin minimal H-1 dari tanggal mulai</li>
                            <li>Setelah diajukan, admin akan melakukan verifikasi</li>
                            <li>Status pengajuan dapat dilihat di halaman ini</li>
                            <li>Cuti tahunan: 12 hari per tahun</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
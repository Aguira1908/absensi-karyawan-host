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
// 1. AMBIL DATA KARYAWAN
// =============================================
$karyawan = [];
$shift_default = '';

if($conn && $conn_type) {
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("SELECT * FROM karyawan WHERE id_karyawan = :id");
            $stmt->execute([':id' => $id_karyawan]);
            $karyawan = $stmt->fetch(PDO::FETCH_ASSOC);
            $shift_default = $karyawan['SHIFT_DEFAULT'];
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// =============================================
// 2. AMBIL DAFTAR KARYAWAN LAIN UNTUK TUJUAN TUKAR SHIFT
// =============================================
$karyawan_lain = [];
if($conn && $conn_type) {
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("SELECT id_karyawan, nama, shift_default FROM karyawan WHERE id_karyawan != :id AND status = 'Aktif' ORDER BY nama");
            $stmt->execute([':id' => $id_karyawan]);
            $karyawan_lain = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// =============================================
// 3. PROSES PENGAJUAN TUKAR SHIFT
// =============================================
if(isset($_POST['ajukan'])) {
    $id_tujuan = $_POST['id_tujuan'];
    $tanggal = $_POST['tanggal'];
    $alasan = $_POST['alasan'];
    
    // Validasi tanggal tidak boleh kurang dari hari ini
    if($tanggal < date('Y-m-d')) {
        $error = '❌ Tanggal tukar shift tidak boleh kurang dari hari ini!';
    } else {
        // Ambil shift pengaju
        $shift_pengaju = $shift_default;
        
        // Ambil shift tujuan
        $shift_tujuan = '';
        foreach($karyawan_lain as $k) {
            if($k['ID_KARYAWAN'] == $id_tujuan) {
                $shift_tujuan = $k['SHIFT_DEFAULT'];
                break;
            }
        }
        
        // Cek apakah sudah ada pengajuan untuk tanggal yang sama
        try {
            if($conn_type == 'pdo') {
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tukar_shift 
                                       WHERE id_karyawan_pengaju = :id AND tanggal = :tanggal AND status IN ('Menunggu', 'Disetujui')");
                $stmt->execute([':id' => $id_karyawan, ':tanggal' => $tanggal]);
                $cek = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($cek['TOTAL'] > 0) {
                    $error = '❌ Anda sudah mengajukan tukar shift untuk tanggal tersebut!';
                } else {
                    $stmt = $conn->prepare("INSERT INTO tukar_shift (id_karyawan_pengaju, id_karyawan_tujuan, shift_pengaju, shift_tujuan, tanggal, alasan, status) 
                                           VALUES (:pengaju, :tujuan, :shift_pengaju, :shift_tujuan, :tanggal, :alasan, 'Menunggu')");
                    $stmt->execute([
                        ':pengaju' => $id_karyawan,
                        ':tujuan' => $id_tujuan,
                        ':shift_pengaju' => $shift_pengaju,
                        ':shift_tujuan' => $shift_tujuan,
                        ':tanggal' => $tanggal,
                        ':alasan' => $alasan
                    ]);
                    
                    $message = '<div class="alert alert-success">✅ Pengajuan tukar shift berhasil dikirim! Menunggu persetujuan admin.</div>';
                    echo "<script>setTimeout(()=>{window.location.href='tukar_shift.php'},2000)</script>";
                }
            }
        } catch(Exception $e) {
            $error = "Gagal menyimpan pengajuan: " . $e->getMessage();
        }
    }
}

// =============================================
// 4. AMBIL RIWAYAT PENGAJUAN
// =============================================
$riwayat_pengajuan = [];
if($conn && $conn_type) {
    try {
        if($conn_type == 'pdo') {
            $stmt = $conn->prepare("SELECT ts.*, k.nama as nama_tujuan 
                                   FROM tukar_shift ts 
                                   JOIN karyawan k ON ts.id_karyawan_tujuan = k.id_karyawan 
                                   WHERE ts.id_karyawan_pengaju = :id 
                                   ORDER BY ts.created_at DESC");
            $stmt->execute([':id' => $id_karyawan]);
            $riwayat_pengajuan = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(Exception $e) {
        // Error handling
    }
}

// Definisi shift untuk tampilan
$shift_hours = [
    'Pagi' => ['icon' => '🌅', 'jam' => '07:00 - 15:00'],
    'Siang' => ['icon' => '☀️', 'jam' => '15:00 - 23:00'],
    'Malam' => ['icon' => '🌙', 'jam' => '23:00 - 07:00']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Tukar Shift - Ck-Ck Coffee</title>
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
        .badge-menunggu { background: #ffc107; color: #333; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; }
        .badge-disetujui { background: #28a745; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; }
        .badge-ditolak { background: #dc3545; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; }
        
        /* Shift Info */
        .shift-info-card { background: linear-gradient(135deg, #2C1810, #C8A951); color: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .shift-icon { font-size: 2rem; }
        
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
                    
                    <!-- Info Shift Saat Ini -->
                    <div class="shift-info-card">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4>Shift Anda Saat Ini: <?= $shift_default ?> <?= $shift_hours[$shift_default]['icon'] ?? '' ?></h4>
                                <p class="mb-0">Jam Kerja: <?= $shift_hours[$shift_default]['jam'] ?? '-' ?></p>
                                <small>Anda dapat mengajukan tukar shift dengan karyawan lain</small>
                            </div>
                            <div class="col-4 text-end">
                                <div class="shift-icon">🔄</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Pengajuan Tukar Shift -->
                    <div class="card-custom">
                        <h5><i class="fas fa-paper-plane"></i> Ajukan Tukar Shift</h5>
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pilih Karyawan Tujuan</label>
                                    <select name="id_tujuan" class="form-select" required>
                                        <option value="">-- Pilih Karyawan --</option>
                                        <?php foreach($karyawan_lain as $k): ?>
                                            <option value="<?= $k['ID_KARYAWAN'] ?>">
                                                <?= htmlspecialchars($k['NAMA']) ?> (Shift: <?= $k['SHIFT_DEFAULT'] ?> <?= $shift_hours[$k['SHIFT_DEFAULT']]['icon'] ?? '' ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Tukar Shift</label>
                                    <input type="date" name="tanggal" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                                    <small class="text-muted">Minimal H+1 dari hari ini</small>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Alasan Tukar Shift</label>
                                    <textarea name="alasan" class="form-control" rows="3" placeholder="Sebutkan alasan ingin tukar shift..." required></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="ajukan" class="btn btn-primary w-100">
                                        <i class="fas fa-paper-plane"></i> Ajukan Tukar Shift
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Riwayat Pengajuan -->
                    <div class="card-custom">
                        <h5><i class="fas fa-history"></i> Riwayat Pengajuan Tukar Shift</h5>
                        <?php if(empty($riwayat_pengajuan)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada pengajuan tukar shift</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($riwayat_pengajuan as $r): ?>
                                <div class="history-item">
                                    <div>
                                        <strong><?= date('d M Y', strtotime($r['TANGGAL'])) ?></strong>
                                        <div class="small text-muted">
                                            <i class="fas fa-user"></i> Dengan: <?= htmlspecialchars($r['NAMA_TUJUAN']) ?>
                                        </div>
                                        <div class="small text-muted">
                                            <i class="fas fa-exchange-alt"></i> Shift <?= $r['SHIFT_PENGAJU'] ?> → Shift <?= $r['SHIFT_TUJUAN'] ?>
                                        </div>
                                        <?php if($r['ALASAN']): ?>
                                            <div class="small text-muted mt-1">
                                                <i class="fas fa-comment"></i> Alasan: <?= htmlspecialchars($r['ALASAN']) ?>
                                            </div>
                                        <?php endif; ?>
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
                    
                    <!-- Informasi Tambahan -->
                    <div class="card-custom">
                        <h5><i class="fas fa-info-circle"></i> Informasi Tukar Shift</h5>
                        <ul class="mb-0">
                            <li>Pengajuan tukar shift harus diajukan minimal H-1 dari tanggal yang diminta</li>
                            <li>Setelah diajukan, admin akan melakukan verifikasi</li>
                            <li>Jika disetujui, shift Anda akan otomatis berubah untuk tanggal tersebut</li>
                            <li>Pastikan koordinasi dengan karyawan yang dituju sebelum mengajukan</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
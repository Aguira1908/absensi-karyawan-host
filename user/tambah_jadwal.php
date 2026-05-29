<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../assets/config/koneksi.php';

$id_karyawan = $_SESSION['id_karyawan'];
$message = '';
$error = '';
$success = '';

// Proses simpan jadwal
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_jadwal'])) {
    $tanggal = $_POST['tanggal'];
    $shift = $_POST['shift'];
    $keterangan = $_POST['keterangan'] ?? '';
    
    // Tentukan jam berdasarkan shift
    $shift_hours = [
        'Pagi' => ['mulai' => '00:00:00', 'selesai' => '08:00:00', 'icon' => '🌅', 'desc' => 'Shift Pagi (00:00 - 08:00)'],
        'Siang' => ['mulai' => '08:00:00', 'selesai' => '17:00:00', 'icon' => '☀️', 'desc' => 'Shift Siang (08:00 - 17:00)'],
        'Malam' => ['mulai' => '16:00:00', 'selesai' => '00:00:00', 'icon' => '🌙', 'desc' => 'Shift Malam (16:00 - 00:00)']
    ];
    
    $jam_mulai = $shift_hours[$shift]['mulai'];
    $jam_selesai = $shift_hours[$shift]['selesai'];
    $created_by = $id_karyawan;
    
    // Validasi tanggal tidak boleh kurang dari hari ini
    if(strtotime($tanggal) < strtotime(date('Y-m-d'))) {
        $error = "❌ Tanggal tidak boleh kurang dari hari ini!";
    } elseif(empty($tanggal) || empty($shift)) {
        $error = "❌ Tanggal dan shift wajib diisi!";
    } else {
        // Cek apakah sudah ada pengajuan untuk tanggal tersebut
        $sudah_ada = false;
        if($conn_type == 'pdo' || $conn_type == 'mysql') {
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jadwal WHERE id_karyawan = :id AND tanggal = :tanggal");
            $stmt->execute([':id' => $id_karyawan, ':tanggal' => $tanggal]);
            $cek = $stmt->fetch(PDO::FETCH_ASSOC);
            if($cek['total'] > 0) $sudah_ada = true;
        } else {
            $sql = "SELECT COUNT(*) as total FROM jadwal WHERE id_karyawan = :id AND TRUNC(tanggal) = TO_DATE(:tanggal, 'YYYY-MM-DD')";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':id', $id_karyawan);
            oci_bind_by_name($stmt, ':tanggal', $tanggal);
            oci_execute($stmt);
            $cek = oci_fetch_assoc($stmt);
            oci_free_statement($stmt);
            if($cek['TOTAL'] > 0) $sudah_ada = true;
        }
        
        if($sudah_ada) {
            $error = "❌ Anda sudah mengajukan jadwal untuk tanggal tersebut!";
        } else {
            try {
                if($conn_type == 'pdo' || $conn_type == 'mysql') {
                    $stmt = $conn->prepare("INSERT INTO jadwal (id_karyawan, tanggal, shift, jam_mulai, jam_selesai, keterangan, status, created_by, created_at) 
                                           VALUES (:id, :tanggal, :shift, :jam_mulai, :jam_selesai, :keterangan, 'Menunggu', :created_by, NOW())");
                    $stmt->execute([
                        ':id' => $id_karyawan,
                        ':tanggal' => $tanggal,
                        ':shift' => $shift,
                        ':jam_mulai' => $jam_mulai,
                        ':jam_selesai' => $jam_selesai,
                        ':keterangan' => $keterangan,
                        ':created_by' => $created_by
                    ]);
                    $success = "✅ Jadwal berhasil diajukan! Menunggu persetujuan admin.";
                } else {
                    $sql = "INSERT INTO jadwal (id_karyawan, tanggal, shift, jam_mulai, jam_selesai, keterangan, status, created_by, created_at) 
                           VALUES (:id_karyawan, TO_DATE(:tanggal, 'YYYY-MM-DD'), :shift, :jam_mulai, :jam_selesai, :keterangan, 'Menunggu', :created_by, SYSDATE)";
                    $stmt = oci_parse($conn, $sql);
                    
                    oci_bind_by_name($stmt, ':id_karyawan', $id_karyawan);
                    oci_bind_by_name($stmt, ':tanggal', $tanggal);
                    oci_bind_by_name($stmt, ':shift', $shift);
                    oci_bind_by_name($stmt, ':jam_mulai', $jam_mulai);
                    oci_bind_by_name($stmt, ':jam_selesai', $jam_selesai);
                    oci_bind_by_name($stmt, ':keterangan', $keterangan);
                    oci_bind_by_name($stmt, ':created_by', $created_by);
                    
                    $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
                    oci_free_statement($stmt);
                    
                    if($result) {
                        $success = "✅ Jadwal berhasil diajukan! Menunggu persetujuan admin.";
                    } else {
                        $e = oci_error($conn);
                        throw new Exception($e['message']);
                    }
                }
            } catch(Exception $e) {
                $error = "❌ Gagal menyimpan: " . $e->getMessage();
            }
        }
    }
}

// Ambil semua jadwal yang sudah diajukan
$jadwal_saya = [];
if($conn_type == 'pdo' || $conn_type == 'mysql') {
    $stmt = $conn->prepare("SELECT * FROM jadwal WHERE id_karyawan = :id ORDER BY tanggal DESC");
    $stmt->execute([':id' => $id_karyawan]);
    $jadwal_saya = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sql = "SELECT j.*, TO_CHAR(j.tanggal, 'YYYY-MM-DD') as tgl_str 
            FROM jadwal j 
            WHERE j.id_karyawan = :id_karyawan 
            ORDER BY j.tanggal DESC";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id_karyawan', $id_karyawan);
    oci_execute($stmt);
    
    while($row = oci_fetch_assoc($stmt)) {
        if(isset($row['TGL_STR'])) {
            $row['TANGGAL'] = $row['TGL_STR'];
            unset($row['TGL_STR']);
        }
        $jadwal_saya[] = $row;
    }
    oci_free_statement($stmt);
}

// Hitung statistik
$stat_menunggu = 0;
$stat_disetujui = 0;
$stat_ditolak = 0;
foreach($jadwal_saya as $j) {
    $status = $j['STATUS'] ?? 'Menunggu';
    if($status == 'Menunggu') $stat_menunggu++;
    elseif($status == 'Disetujui') $stat_disetujui++;
    elseif($status == 'Ditolak') $stat_ditolak++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Atur Jadwal - Ck-Ck Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        
        .navbar-custom { background: #2C1810; color: white; padding: 12px 0; }
        .btn-logout { background: #dc3545; color: white; padding: 6px 15px; border-radius: 8px; text-decoration: none; font-size: 0.8rem; }
        
        .sidebar { background: white; min-height: calc(100vh - 70px); padding: 15px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .sidebar a { display: block; padding: 10px 12px; color: #333; text-decoration: none; border-radius: 8px; margin-bottom: 5px; font-size: 0.9rem; transition: all 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: #C8A951; color: white; }
        .sidebar a i { width: 25px; margin-right: 8px; }
        
        .card-custom { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card-header-custom { border-bottom: 2px solid #f0f0f0; padding-bottom: 12px; margin-bottom: 20px; font-weight: 700; }
        
        .btn-primary { background: linear-gradient(135deg, #C8A951, #e6b422); border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; transition: all 0.3s; }
        .btn-primary:hover { background: #b8962e; transform: translateY(-2px); }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: linear-gradient(135deg, #f8f9fa, #fff); border-radius: 15px; padding: 15px; text-align: center; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2rem; font-weight: 800; }
        .stat-number.menunggu { color: #ffc107; }
        .stat-number.disetujui { color: #28a745; }
        .stat-number.ditolak { color: #dc3545; }
        .stat-label { font-size: 0.8rem; color: #666; margin-top: 5px; }
        
        .badge-menunggu { background: #ffc107; color: #333; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-disetujui { background: #28a745; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-ditolak { background: #dc3545; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        
        .form-control, .form-select { border-radius: 10px; border: 1px solid #e0e0e0; padding: 10px 15px; transition: all 0.3s; }
        .form-control:focus, .form-select:focus { border-color: #C8A951; box-shadow: 0 0 0 0.2rem rgba(200,169,81,0.25); }
        
        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom thead th { background: #f8f9fa; padding: 12px; font-weight: 600; font-size: 0.85rem; border-bottom: 2px solid #e0e0e0; }
        .table-custom tbody td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; vertical-align: middle; }
        .table-custom tbody tr:hover { background: #fef9f0; }
        
        .btn-group-sm .btn { padding: 5px 10px; border-radius: 8px; margin: 0 2px; }
        
        @media (max-width: 768px) {
            .stats-grid { gap: 10px; }
            .stat-number { font-size: 1.5rem; }
            .table-custom { font-size: 0.75rem; }
            .table-custom thead th, .table-custom tbody td { padding: 8px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card-custom, .stat-card { animation: fadeInUp 0.5s ease forwards; }
    </style>
</head>
<body>
    <nav class="navbar-custom">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div><i class="fas fa-coffee"></i> Ck-Ck Coffee</div>
                <div>
                    <span>Halo, <?= htmlspecialchars($_SESSION['nama'] ?? 'Karyawan') ?></span>
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
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="absen.php"><i class="fas fa-fingerprint"></i> Absensi</a>
                    <a href="tambah_jadwal.php" class="active"><i class="fas fa-calendar-plus"></i> Atur Jadwal</a>
                    <a href="riwayat.php"><i class="fas fa-history"></i> Riwayat Absensi</a>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="content p-3 p-md-4">
                    <h2 class="mb-2"><i class="fas fa-calendar-plus"></i> Atur Jadwal Kerja</h2>
                    <p class="text-muted mb-4">Ajukan jadwal shift yang Anda inginkan. Admin akan menyetujui jadwal Anda.</p>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= $success ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle"></i> <?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    
                    <!-- Statistik -->
                    <div class="stats-grid">
                        <div class="stat-card"><div class="stat-number menunggu"><?= $stat_menunggu ?></div><div class="stat-label">⏳ Menunggu</div></div>
                        <div class="stat-card"><div class="stat-number disetujui"><?= $stat_disetujui ?></div><div class="stat-label">✅ Disetujui</div></div>
                        <div class="stat-card"><div class="stat-number ditolak"><?= $stat_ditolak ?></div><div class="stat-label">❌ Ditolak</div></div>
                    </div>
                    
                    <!-- Form Ajukan Jadwal -->
                    <div class="card-custom">
                        <div class="card-header-custom"><i class="fas fa-pen"></i> Ajukan Jadwal Baru</div>
                        <form method="POST">
                            <input type="hidden" name="simpan_jadwal" value="1">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">📅 Tanggal *</label>
                                    <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                                    <small class="text-muted">Minimal H+1 dari hari ini</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">🔄 Shift *</label>
                                    <select name="shift" class="form-select" required id="shiftSelect">
                                        <option value="Pagi">🌅 Shift Pagi (00:00 - 08:00)</option>
                                        <option value="Siang" selected>☀️ Shift Siang (08:00 - 17:00)</option>
                                        <option value="Malam">🌙 Shift Malam (16:00 - 00:00)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">⏰ Jam Mulai</label>
                                    <input type="text" id="jam_mulai_display" class="form-control" readonly style="background:#f5f5f5">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">⏰ Jam Selesai</label>
                                    <input type="text" id="jam_selesai_display" class="form-control" readonly style="background:#f5f5f5">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">📝 Keterangan (opsional)</label>
                                <textarea name="keterangan" class="form-control" rows="2" placeholder="Alasan jika ingin tukar shift atau keterangan lain"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Ajukan Jadwal</button>
                        </form>
                    </div>
                    
                    <!-- Riwayat Pengajuan -->
                    <div class="card-custom">
                        <div class="card-header-custom"><i class="fas fa-list"></i> Riwayat Pengajuan Jadwal</div>
                        <?php if(empty($jadwal_saya)): ?>
                            <div class="text-center py-4 text-muted"><i class="fas fa-calendar-times fa-3x mb-2 d-block"></i><p>Belum ada pengajuan jadwal</p><small>Silakan ajukan jadwal di form di atas</small></div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table-custom">
                                    <thead><tr><th>No</th><th>Tanggal</th><th>Shift</th><th>Jam Kerja</th><th>Status</th><th>Aksi</th></tr></thead>
                                    <tbody>
                                        <?php $no=1; foreach($jadwal_saya as $j): 
                                            $status = $j['STATUS'] ?? 'Menunggu';
                                            $badge_class = $status == 'Disetujui' ? 'badge-disetujui' : ($status == 'Ditolak' ? 'badge-ditolak' : 'badge-menunggu');
                                            $badge_icon = $status == 'Disetujui' ? '✅' : ($status == 'Ditolak' ? '❌' : '⏳');
                                            $can_edit = ($status == 'Menunggu');
                                        ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= date('d-m-Y', strtotime($j['TANGGAL'])) ?></td>
                                            <td><?= $j['SHIFT'] ?></td>
                                            <td><?= $j['JAM_MULAI'] ?? '-' ?> - <?= $j['JAM_SELESAI'] ?? '-' ?></td>
                                            <td><span class="<?= $badge_class ?>"><?= $badge_icon ?> <?= $status ?></span></td>
                                            <td>
                                                <?php if($can_edit): ?>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="edit_jadwal.php?id=<?= $j['ID_JADWAL'] ?>" class="btn btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                                        <a href="hapus_jadwal.php?id=<?= $j['ID_JADWAL'] ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus jadwal ini?')" title="Hapus"><i class="fas fa-trash"></i></a>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 small text-muted"><i class="fas fa-info-circle"></i> Hanya jadwal dengan status "Menunggu" yang dapat diedit/dihapus.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Informasi -->
                    <div class="card-custom">
                        <div class="card-header-custom"><i class="fas fa-info-circle"></i> Informasi</div>
                        <ul class="mb-0">
                            <li>📌 Pengajuan jadwal akan diproses oleh admin</li>
                            <li>⏳ Status "Menunggu" berarti jadwal belum diproses admin</li>
                            <li>✅ Status "Disetujui" berarti jadwal sudah disetujui dan Anda bisa absen</li>
                            <li>❌ Status "Ditolak" berarti jadwal ditolak, silakan ajukan ulang</li>
                            <li>✏️ Hanya jadwal dengan status "Menunggu" yang bisa diedit/dihapus</li>
                            <li>📱 Absensi hanya bisa dilakukan jika ada jadwal yang disetujui</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const shiftHours = {
            'Pagi': { mulai: '00:00:00', selesai: '08:00:00' },
            'Siang': { mulai: '08:00:00', selesai: '17:00:00' },
            'Malam': { mulai: '16:00:00', selesai: '00:00:00' }
        };
        
        const shiftSelect = document.getElementById('shiftSelect');
        const jamMulaiDisplay = document.getElementById('jam_mulai_display');
        const jamSelesaiDisplay = document.getElementById('jam_selesai_display');
        
        function updateJamDisplay() {
            const shift = shiftSelect.value;
            if(shiftHours[shift]) {
                jamMulaiDisplay.value = shiftHours[shift].mulai;
                jamSelesaiDisplay.value = shiftHours[shift].selesai;
            }
        }
        
        shiftSelect.addEventListener('change', updateJamDisplay);
        updateJamDisplay();
        
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const dateInput = document.querySelector('input[name="tanggal"]');
        if(dateInput) dateInput.min = tomorrow.toISOString().split('T')[0];
        
        setTimeout(() => { document.querySelectorAll('.alert').forEach(a => setTimeout(() => a.remove(), 3000)); }, 500);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
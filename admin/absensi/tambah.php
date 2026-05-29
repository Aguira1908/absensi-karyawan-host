<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}
include '../../assets/config/koneksi.php';

$error = '';

// Ambil data karyawan aktif
$karyawan = [];
if($conn && $conn_type) {
    try {
        if ($conn_type === 'pdo') {
            $sql = "SELECT * FROM karyawan WHERE status = 'Aktif' ORDER BY nama";
            $stmt = $conn->query($sql);
            $karyawan = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT * FROM karyawan WHERE status = 'Aktif' ORDER BY nama";
            $stmt = oci_parse($conn, $sql);
            oci_execute($stmt);
            while($row = oci_fetch_assoc($stmt)) {
                $new_row = [];
                foreach($row as $k => $v) {
                    $new_row[strtoupper($k)] = $v;
                }
                $karyawan[] = $new_row;
            }
        }
    } catch(Exception $e) {
        $error = "Gagal mengambil data karyawan: " . $e->getMessage();
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if($conn && $conn_type) {
        try {
            $id_karyawan = $_POST['id_karyawan'];
            $tanggal = $_POST['tanggal'];
            $jam_masuk = $_POST['tanggal'] . ' ' . $_POST['jam_masuk'];
            $jam_keluar = !empty($_POST['jam_keluar']) ? $_POST['tanggal'] . ' ' . $_POST['jam_keluar'] : null;
            $status = $_POST['status'];
            $keterangan = !empty($_POST['keterangan']) ? $_POST['keterangan'] : '';
            
            // Hitung keterlambatan jika jam_masuk > 08:00
            $terlambat = 0;
            if($jam_masuk && strtotime($jam_masuk) > strtotime($tanggal . ' 08:00:00')) {
                $terlambat = round((strtotime($jam_masuk) - strtotime($tanggal . ' 08:00:00')) / 60);
            }
            
            if ($conn_type === 'pdo') {
                // PDO version - tidak perlu menyebut id_absensi karena trigger akan mengisi
                $sql = "INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, jam_keluar, status, terlambat, keterangan) 
                        VALUES (:id_karyawan, :tanggal, TO_TIMESTAMP(:jam_masuk, 'YYYY-MM-DD HH24:MI:SS'), 
                                TO_TIMESTAMP(:jam_keluar, 'YYYY-MM-DD HH24:MI:SS'), :status, :terlambat, :keterangan)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':id_karyawan' => $id_karyawan,
                    ':tanggal' => $tanggal,
                    ':jam_masuk' => $jam_masuk,
                    ':jam_keluar' => $jam_keluar,
                    ':status' => $status,
                    ':terlambat' => $terlambat,
                    ':keterangan' => $keterangan
                ]);
            } else {
                // OCI8 version - tidak perlu menyebut id_absensi karena trigger akan mengisi
                $jam_keluar_val = $jam_keluar ? "TO_TIMESTAMP('$jam_keluar', 'YYYY-MM-DD HH24:MI:SS')" : "NULL";
                $keterangan_val = addslashes($keterangan);
                
                $sql = "INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, jam_keluar, status, terlambat, keterangan) 
                        VALUES ($id_karyawan, TO_DATE('$tanggal', 'YYYY-MM-DD'), 
                                TO_TIMESTAMP('$jam_masuk', 'YYYY-MM-DD HH24:MI:SS'), 
                                $jam_keluar_val, '$status', $terlambat, '$keterangan_val')";
                $stmt = oci_parse($conn, $sql);
                $result = oci_execute($stmt);
                
                if(!$result) {
                    $e = oci_error($stmt);
                    throw new Exception($e['message']);
                }
            }
            header("Location: index.php?msg=added");
            exit();
        } catch(Exception $e) {
            $error = "Gagal menyimpan absensi: " . $e->getMessage();
        }
    } else {
        $error = "Koneksi database bermasalah";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Absensi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-control { width: 100%; padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px; }
        .form-control:focus { outline: none; border-color: #6F4E37; }
        .btn-primary { background: linear-gradient(135deg, #6F4E37, #5A3A2A); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-2px); }
        .alert-danger { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 30px; }
        .card-header { background: linear-gradient(135deg, #6F4E37, #5A3A2A); color: white; padding: 20px; font-size: 1.2rem; font-weight: bold; }
        .card-header i { margin-right: 10px; }
        .card-body { padding: 25px; }
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .navbar { background: linear-gradient(135deg, #6F4E37, #5A3A2A); padding: 1rem 2rem; }
        .navbar-brand { color: white; font-size: 1.5rem; font-weight: bold; text-decoration: none; }
        .navbar-brand i { margin-right: 10px; }
        .btn-back { background: #6c757d; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; float: right; }
        .btn-back:hover { opacity: 0.8; }
        select.form-control { cursor: pointer; }
        textarea.form-control { resize: vertical; }
        .text-muted { color: #6c757d; font-size: 12px; margin-top: 5px; display: block; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-coffee"></i> Cafe Operasional System
        </div>
    </nav>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-fingerprint"></i> Tambah Absensi
                <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Karyawan *</label>
                        <select name="id_karyawan" class="form-control" required>
                            <option value="">Pilih Karyawan</option>
                            <?php foreach($karyawan as $row): ?>
                            <option value="<?= $row['ID_KARYAWAN'] ?>">
                                <?= htmlspecialchars($row['NAMA']) ?> - <?= htmlspecialchars($row['JABATAN']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Tanggal *</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Jam Masuk *</label>
                        <input type="time" name="jam_masuk" class="form-control" value="<?= date('H:i') ?>" required>
                        <small class="text-muted">Jam kerja dimulai pukul 08:00</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Jam Keluar</label>
                        <input type="time" name="jam_keluar" class="form-control">
                        <small class="text-muted">Kosongkan jika belum pulang</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" class="form-control" required>
                            <option value="Hadir">Hadir</option>
                            <option value="Sakit">Sakit</option>
                            <option value="Izin">Izin</option>
                            <option value="Alpha">Alpha</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Catatan tambahan (opsional)"></textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Simpan Absensi
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../../assets/config/koneksi.php';

$message = '';
$error = '';
$tanggal_filter = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$karyawan_filter = isset($_GET['karyawan']) ? (int)$_GET['karyawan'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Hitung statistik absensi hari ini
$stats = ['hadir'=>0, 'terlambat'=>0, 'sakit'=>0, 'izin'=>0, 'alpha'=>0, 'total'=>0];

// Fungsi untuk membaca CLOB/LOB
if (!function_exists('getClobValue')) {
    function getClobValue($field) {
        if (empty($field)) return '';
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
}

// Fungsi untuk konversi tanggal Oracle ke format PHP
if (!function_exists('convertOracleDateToString')) {
    function convertOracleDateToString($oracle_date) {
        if(empty($oracle_date)) return '';
        if(is_string($oracle_date)) {
            // Coba parse langsung
            $timestamp = strtotime($oracle_date);
            if($timestamp !== false && $timestamp > 0) {
                return date('Y-m-d H:i:s', $timestamp);
            }
            
            // Format Oracle timestamp: "18-MAY-26 12.59.47.000000 AM"
            $cleaned = preg_replace('/\.(\d+)\s+(AM|PM)/i', ' $2', $oracle_date);
            $cleaned = str_replace('.', ':', $cleaned);
            $cleaned = preg_replace('/:\d{6}/', '', $cleaned);
            
            $timestamp = strtotime($cleaned);
            if($timestamp !== false && $timestamp > 0) {
                return date('Y-m-d H:i:s', $timestamp);
            }
            
            // Pattern matching manual
            $patterns = [
                '/(\d{2})-([A-Z]{3})-(\d{2})\s+(\d{2})\.(\d{2})\.(\d{2})\.\d+\s+(AM|PM)/i',
                '/(\d{2})-([A-Z]{3})-(\d{2})\s+(\d{2})\.(\d{2})\.(\d{2})\s+(AM|PM)/i'
            ];
            
            foreach($patterns as $pattern) {
                if(preg_match($pattern, $oracle_date, $matches)) {
                    $day = $matches[1];
                    $month = $matches[2];
                    $year = $matches[3];
                    $hour = $matches[4];
                    $minute = $matches[5];
                    $second = $matches[6];
                    $ampm = strtoupper($matches[7] ?? '');
                    
                    $months = [
                        'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
                        'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
                        'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12'
                    ];
                    $month_num = $months[strtoupper($month)] ?? '01';
                    $year_full = ($year < 70) ? '20' . $year : '19' . $year;
                    
                    if($ampm == 'PM' && $hour < 12) $hour += 12;
                    if($ampm == 'AM' && $hour == 12) $hour = 0;
                    
                    $hour = str_pad($hour, 2, '0', STR_PAD_LEFT);
                    $minute = str_pad($minute, 2, '0', STR_PAD_LEFT);
                    $second = str_pad($second, 2, '0', STR_PAD_LEFT);
                    
                    return $year_full . '-' . $month_num . '-' . $day . ' ' . $hour . ':' . $minute . ':' . $second;
                }
            }
        }
        
        if(is_object($oracle_date)) {
            if(method_exists($oracle_date, 'load')) {
                $oracle_date = $oracle_date->load();
            } elseif(method_exists($oracle_date, 'read')) {
                $oracle_date = $oracle_date->read($oracle_date->size());
            } else {
                $oracle_date = (string)$oracle_date;
            }
            return convertOracleDateToString($oracle_date);
        }
        
        return '';
    }
}

// Handle update status absensi
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $id_absensi = (int)$_POST['id_absensi'];
    $new_status = $_POST['status'];
    
    try {
        if($conn_type == 'pdo' || $conn_type == 'mysql') {
            $stmt = $conn->prepare("UPDATE absensi SET status = :status WHERE id_absensi = :id");
            $stmt->execute([':status' => $new_status, ':id' => $id_absensi]);
            $message = "Status absensi berhasil diupdate";
        } else if ($conn_type == 'oci8') {
            $stmt = oci_parse($conn, "UPDATE absensi SET status = :status WHERE id_absensi = :id");
            oci_bind_by_name($stmt, ':status', $new_status);
            oci_bind_by_name($stmt, ':id', $id_absensi);
            $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
            oci_free_statement($stmt);
            if($result) {
                $message = "Status absensi berhasil diupdate";
            } else {
                $e = oci_error($conn);
                throw new Exception($e['message']);
            }
        }
    } catch(Exception $e) {
        $error = "Gagal update status: " . $e->getMessage();
    }
}

// Handle hapus absensi
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_absensi'])) {
    $id_absensi = (int)$_POST['id_absensi'];
    
    try {
        if($conn_type == 'pdo' || $conn_type == 'mysql') {
            // Ambil data foto untuk dihapus
            $stmt = $conn->prepare("SELECT foto_masuk, foto_pulang FROM absensi WHERE id_absensi = :id");
            $stmt->execute([':id' => $id_absensi]);
            $foto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($foto) {
                if(!empty($foto['foto_masuk'])) {
                    $file_path = __DIR__ . '/../../' . $foto['foto_masuk'];
                    if(file_exists($file_path)) unlink($file_path);
                }
                if(!empty($foto['foto_pulang'])) {
                    $file_path = __DIR__ . '/../../' . $foto['foto_pulang'];
                    if(file_exists($file_path)) unlink($file_path);
                }
            }
            
            $stmt = $conn->prepare("DELETE FROM absensi WHERE id_absensi = :id");
            $stmt->execute([':id' => $id_absensi]);
            $message = "Data absensi berhasil dihapus";
        } else if ($conn_type == 'oci8') {
            $stmt = oci_parse($conn, "SELECT foto_masuk, foto_pulang FROM absensi WHERE id_absensi = :id");
            oci_bind_by_name($stmt, ':id', $id_absensi);
            oci_execute($stmt);
            $foto = oci_fetch_assoc($stmt);
            oci_free_statement($stmt);
            
            if($foto) {
                if(!empty($foto['FOTO_MASUK'])) {
                    $file_path = __DIR__ . '/../../' . $foto['FOTO_MASUK'];
                    if(file_exists($file_path)) unlink($file_path);
                }
                if(!empty($foto['FOTO_PULANG'])) {
                    $file_path = __DIR__ . '/../../' . $foto['FOTO_PULANG'];
                    if(file_exists($file_path)) unlink($file_path);
                }
            }
            
            $stmt = oci_parse($conn, "DELETE FROM absensi WHERE id_absensi = :id");
            oci_bind_by_name($stmt, ':id', $id_absensi);
            $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
            oci_free_statement($stmt);
            
            if($result) {
                $message = "Data absensi berhasil dihapus";
            } else {
                $e = oci_error($conn);
                throw new Exception($e['message']);
            }
        }
    } catch(Exception $e) {
        $error = "Gagal hapus data: " . $e->getMessage();
    }
}

// ==================== AMBIL DATA ABSENSI ====================
$absensi = [];
$karyawan_list = [];
$total = 0;
$total_pages = 0;

if ($conn_type == 'mysql' || $conn_type == 'pdo') {
    // ==================== MySQL / PDO ====================
    try {
        // Total karyawan aktif
        $stmt = $conn->query("SELECT COUNT(*) as total FROM karyawan WHERE status = 'Aktif'");
        $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Statistik absensi berdasarkan status
        $stmt = $conn->prepare("SELECT status, COUNT(*) as jumlah FROM absensi WHERE DATE(tanggal) = :tanggal GROUP BY status");
        $stmt->execute([':tanggal' => $tanggal_filter]);
        $absen_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($absen_stats as $stat) {
            $status = $stat['status'];
            $jumlah = $stat['jumlah'];
            if($status == 'Hadir') $stats['hadir'] = $jumlah;
            elseif($status == 'Terlambat') $stats['terlambat'] = $jumlah;
            elseif($status == 'Sakit') $stats['sakit'] = $jumlah;
            elseif($status == 'Izin') $stats['izin'] = $jumlah;
            elseif($status == 'Alpha') $stats['alpha'] = $jumlah;
        }
        
        // Build query with filters
        $where = "WHERE DATE(a.tanggal) = :tanggal";
        $params = [':tanggal' => $tanggal_filter];
        if($karyawan_filter > 0) {
            $where .= " AND a.id_karyawan = :karyawan";
            $params[':karyawan'] = $karyawan_filter;
        }
        if($status_filter != '') {
            $where .= " AND a.status = :status";
            $params[':status'] = $status_filter;
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM absensi a $where";
        $stmt = $conn->prepare($countSql);
        foreach($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $total_pages = ($limit > 0) ? ceil($total / $limit) : 0;
        
        // Get data with pagination
        $sql = "SELECT a.*, k.nama as nama_karyawan, k.jabatan 
                FROM absensi a 
                JOIN karyawan k ON a.id_karyawan = k.id_karyawan 
                $where 
                ORDER BY a.jam_masuk DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        foreach($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get karyawan list for filter
        $stmt = $conn->query("SELECT id_karyawan, nama FROM karyawan WHERE status = 'Aktif' ORDER BY nama");
        $karyawan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
    
} else if ($conn_type == 'oci8') {
    // ==================== Oracle OCI8 ====================
    try {
        // 1. TOTAL KARYAWAN AKTIF
        $stmt = oci_parse($conn, "SELECT COUNT(*) as total FROM karyawan WHERE status = 'Aktif'");
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $stats['total'] = $row['TOTAL'] ?? 0;
        oci_free_statement($stmt);
        
        // Format tanggal untuk Oracle
        $tanggal_oracle = date('Y-m-d', strtotime($tanggal_filter));
        
        // 2. STATISTIK ABSENSI
        $sql_stats = "SELECT status, COUNT(*) as jumlah FROM absensi WHERE TRUNC(tanggal) = TO_DATE('$tanggal_oracle', 'YYYY-MM-DD') GROUP BY status";
        $stmt_stats = oci_parse($conn, $sql_stats);
        if ($stmt_stats) {
            oci_execute($stmt_stats);
            while($row = oci_fetch_assoc($stmt_stats)) {
                $status = $row['STATUS'];
                $jumlah = (int)$row['JUMLAH'];
                if($status == 'Hadir') $stats['hadir'] = $jumlah;
                elseif($status == 'Terlambat') $stats['terlambat'] = $jumlah;
                elseif($status == 'Sakit') $stats['sakit'] = $jumlah;
                elseif($status == 'Izin') $stats['izin'] = $jumlah;
                elseif($status == 'Alpha') $stats['alpha'] = $jumlah;
            }
            oci_free_statement($stmt_stats);
        }
        
        // 3. BUILD WHERE CLAUSE
        $where_sql = "";
        if($karyawan_filter > 0) {
            $where_sql .= " AND a.id_karyawan = $karyawan_filter";
        }
        if($status_filter != '') {
            $where_sql .= " AND a.status = '" . addslashes($status_filter) . "'";
        }
        
        // 4. HITUNG TOTAL DATA
        $sql_count = "SELECT COUNT(*) as total FROM absensi a 
                      WHERE TRUNC(a.tanggal) = TO_DATE('$tanggal_oracle', 'YYYY-MM-DD') $where_sql";
        $stmt_count = oci_parse($conn, $sql_count);
        if ($stmt_count) {
            oci_execute($stmt_count);
            $row_count = oci_fetch_assoc($stmt_count);
            $total = $row_count['TOTAL'] ?? 0;
            $total_pages = ($limit > 0) ? ceil($total / $limit) : 0;
            oci_free_statement($stmt_count);
        }
        
        // 5. AMBIL DATA DENGAN PAGINATION
        if ($total > 0) {
            $start = $offset + 1;
            $end = $offset + $limit;
            
            $sql = "SELECT * FROM (
                        SELECT 
                            a.ID_ABSENSI,
                            a.ID_KARYAWAN,
                            TO_CHAR(a.TANGGAL, 'YYYY-MM-DD') as TANGGAL_STR,
                            TO_CHAR(a.JAM_MASUK, 'HH24:MI:SS') as JAM_MASUK_STR,
                            TO_CHAR(a.JAM_KELUAR, 'HH24:MI:SS') as JAM_KELUAR_STR,
                            a.STATUS,
                            a.TERLAMBAT,
                            a.FOTO_MASUK,
                            a.FOTO_PULANG,
                            a.LATITUDE,
                            a.LONGITUDE,
                            a.LATITUDE_PULANG,
                            a.LONGITUDE_PULANG,
                            a.ALAMAT_MASUK,
                            a.ALAMAT_PULANG,
                            a.TOTAL_JAM,
                            a.JAM_LEMBUR,
                            k.nama as nama_karyawan,
                            k.jabatan,
                            ROWNUM rn
                        FROM absensi a 
                        JOIN karyawan k ON a.id_karyawan = k.id_karyawan 
                        WHERE TRUNC(a.tanggal) = TO_DATE('$tanggal_oracle', 'YYYY-MM-DD') $where_sql 
                        ORDER BY a.JAM_MASUK DESC
                    ) WHERE rn BETWEEN $start AND $end";
            
            $stmt = oci_parse($conn, $sql);
            if ($stmt && oci_execute($stmt)) {
                while ($row = oci_fetch_assoc($stmt)) {
                    // Gunakan string hasil TO_CHAR
                    $row['TANGGAL'] = $row['TANGGAL_STR'] ?? $tanggal_filter;
                    $row['JAM_MASUK'] = $row['JAM_MASUK_STR'] ?? '';
                    $row['JAM_KELUAR'] = $row['JAM_KELUAR_STR'] ?? '';
                    
                    // Hapus field temporary
                    unset($row['TANGGAL_STR']);
                    unset($row['JAM_MASUK_STR']);
                    unset($row['JAM_KELUAR_STR']);
                    
                    // Konversi CLOB ke string untuk alamat
                    if (isset($row['ALAMAT_MASUK'])) {
                        $row['ALAMAT_MASUK'] = getClobValue($row['ALAMAT_MASUK']);
                    }
                    if (isset($row['ALAMAT_PULANG'])) {
                        $row['ALAMAT_PULANG'] = getClobValue($row['ALAMAT_PULANG']);
                    }
                    
                    $absensi[] = $row;
                }
                oci_free_statement($stmt);
            }
        }
        
        // 6. AMBIL DAFTAR KARYAWAN UNTUK FILTER
        $stmt = oci_parse($conn, "SELECT id_karyawan, nama FROM karyawan WHERE status = 'Aktif' ORDER BY nama");
        if ($stmt) {
            oci_execute($stmt);
            while($row = oci_fetch_assoc($stmt)) {
                $karyawan_list[] = [
                    'ID_KARYAWAN' => $row['ID_KARYAWAN'],
                    'NAMA' => $row['NAMA']
                ];
            }
            oci_free_statement($stmt);
        }
        
    } catch(Exception $e) {
        $error = "Database error: " . $e->getMessage();
        $absensi = [];
        $karyawan_list = [];
        $total_pages = 0;
        $total = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Absensi - Admin Ck-Ck Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        .sidebar { position: fixed; left: 0; top: 0; width: 280px; height: 100%; background: linear-gradient(135deg, #1a0f0a 0%, #2C1810 100%); color: white; z-index: 1000; overflow-y: auto; transition: left 0.3s; }
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header .logo-icon { width: 60px; height: 60px; background: linear-gradient(135deg, #C8A951, #e6b422); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
        .sidebar-header .logo-icon i { font-size: 1.8rem; color: white; }
        .sidebar-header h4 { font-size: 1.2rem; margin-bottom: 5px; }
        .sidebar-menu { padding: 20px; }
        .sidebar-menu .menu-title { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; color: rgba(255,255,255,0.5); padding: 0 15px; margin-bottom: 15px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; margin-bottom: 5px; transition: all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(200,169,81,0.2); color: #C8A951; }
        
        .main-content { margin-left: 280px; padding: 20px; transition: margin-left 0.3s; }
        .top-navbar { background: white; border-radius: 20px; padding: 15px 25px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); flex-wrap: wrap; gap: 15px; }
        .page-title h2 { font-size: 1.3rem; font-weight: 700; margin-bottom: 5px; }
        .page-title p { font-size: 0.8rem; color: #6c757d; margin: 0; }
        .user-info { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .user-name { text-align: right; }
        .user-name .name { font-weight: 600; }
        .user-name .role { font-size: 0.7rem; color: #6c757d; }
        .user-avatar { width: 40px; height: 40px; background: #C8A951; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; }
        .btn-logout { background: #dc3545; color: white; padding: 8px 18px; border-radius: 10px; text-decoration: none; font-size: 0.8rem; transition: all 0.3s; }
        .btn-logout:hover { background: #c82333; color: white; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-info h3 { font-size: 0.8rem; color: #6c757d; margin-bottom: 5px; }
        .stat-info .number { font-size: 1.5rem; font-weight: 800; }
        .stat-icon i { font-size: 2rem; opacity: 0.5; }
        
        .card-custom { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card-custom h5 { margin-bottom: 20px; font-weight: 600; }
        .filter-form { background: #f8f9fa; border-radius: 15px; padding: 20px; }
        .btn-primary { background: #C8A951; border: none; padding: 10px 20px; }
        .btn-primary:hover { background: #b89a3e; }
        
        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom thead th { background: #f8f9fa; padding: 12px; border-bottom: 2px solid #e0e0e0; font-weight: 600; font-size: 0.85rem; }
        .table-custom tbody td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; vertical-align: middle; }
        .table-custom tbody tr:hover { background: #fef9f0; }
        
        .badge-hadir { background: #28a745; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; display: inline-block; }
        .badge-terlambat { background: #ffc107; color: #333; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; display: inline-block; }
        .badge-sakit { background: #17a2b8; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; display: inline-block; }
        .badge-izin { background: #6c757d; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; display: inline-block; }
        .badge-alpha { background: #dc3545; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; display: inline-block; }
        
        .foto-preview { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; cursor: pointer; transition: transform 0.2s; }
        .foto-preview:hover { transform: scale(1.1); }
        .pagination-custom { display: flex; justify-content: center; gap: 5px; margin-top: 20px; flex-wrap: wrap; }
        .page-link { padding: 8px 15px; border-radius: 10px; color: #2C1810; border: 1px solid #e0e0e0; text-decoration: none; transition: all 0.3s; display: inline-block; background: white; }
        .page-link:hover { background: #C8A951; color: white; }
        .page-link.active { background: #C8A951; color: white; border-color: #C8A951; }
        
        .menu-toggle { display: none; position: fixed; top: 20px; left: 20px; z-index: 1001; background: #C8A951; color: white; padding: 10px 15px; border-radius: 8px; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        
        .export-buttons { display: flex; gap: 10px; margin-bottom: 15px; justify-content: flex-end; }
        .btn-export { padding: 8px 15px; border-radius: 8px; text-decoration: none; font-size: 0.8rem; color: white; }
        .btn-export-excel { background: #28a745; }
        .btn-export-excel:hover { background: #218838; color: white; }
        .btn-export-pdf { background: #dc3545; }
        .btn-export-pdf:hover { background: #c82333; color: white; }
        
        @media (max-width: 992px) { 
            .sidebar { left: -280px; }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
        }
        @media (max-width: 768px) { 
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .table-custom { font-size: 0.7rem; }
            .table-custom thead th, .table-custom tbody td { padding: 8px; }
            .top-navbar { flex-direction: column; align-items: flex-start; }
            .user-info { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body>
    <div class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i> Menu</div>
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-mug-hot"></i></div>
            <h4>Ck-Ck Coffee</h4>
            <p>Admin Panel</p>
        </div>
        <div class="sidebar-menu">
            <div class="menu-title">MAIN MENU</div>
            <a href="../dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="../karyawan/"><i class="fas fa-users"></i> <span>Kelola Karyawan</span></a>
            <a href="../jadwal/"><i class="fas fa-calendar"></i> <span>Kelola Jadwal</span></a>
            <a href="index.php" class="active"><i class="fas fa-fingerprint"></i> <span>Monitoring Absensi</span></a>
            <a href="../tukar_shift.php"><i class="fas fa-exchange-alt"></i> <span>Tukar Shift</span></a>
            <a href="../laporan/"><i class="fas fa-chart-line"></i> <span>Laporan</span></a>
            
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <div class="page-title">
                <h2><i class="fas fa-fingerprint"></i> Monitoring Absensi</h2>
                <p>Pantau dan kelola data kehadiran karyawan</p>
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
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Karyawan</h3>
                    <div class="number"><?= $stats['total'] ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Hadir</h3>
                    <div class="number" style="color:#28a745"><?= $stats['hadir'] ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle" style="color:#28a745"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Terlambat</h3>
                    <div class="number" style="color:#ffc107"><?= $stats['terlambat'] ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-clock" style="color:#ffc107"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Sakit</h3>
                    <div class="number" style="color:#17a2b8"><?= $stats['sakit'] ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-thermometer-half" style="color:#17a2b8"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Izin</h3>
                    <div class="number" style="color:#6c757d"><?= $stats['izin'] ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-times" style="color:#6c757d"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Alpha</h3>
                    <div class="number" style="color:#dc3545"><?= $stats['alpha'] ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-times-circle" style="color:#dc3545"></i></div>
            </div>
        </div>

        <div class="card-custom">
            <h5><i class="fas fa-filter"></i> Filter Data</h5>
            <form method="GET" class="filter-form">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal_filter) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Karyawan</label>
                        <select name="karyawan" class="form-select">
                            <option value="0">Semua Karyawan</option>
                            <?php foreach($karyawan_list as $k): ?>
                                <option value="<?= $k['ID_KARYAWAN'] ?>" <?= $karyawan_filter==$k['ID_KARYAWAN']?'selected':'' ?>><?= htmlspecialchars($k['NAMA']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="Hadir" <?= $status_filter=='Hadir'?'selected':'' ?>>Hadir</option>
                            <option value="Terlambat" <?= $status_filter=='Terlambat'?'selected':'' ?>>Terlambat</option>
                            <option value="Sakit" <?= $status_filter=='Sakit'?'selected':'' ?>>Sakit</option>
                            <option value="Izin" <?= $status_filter=='Izin'?'selected':'' ?>>Izin</option>
                            <option value="Alpha" <?= $status_filter=='Alpha'?'selected':'' ?>>Alpha</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search"></i> Filter</button>
                        <a href="index.php" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                <h5><i class="fas fa-table"></i> Data Absensi</h5>
                <div class="export-buttons">
                    <a href="export_excel.php?tanggal=<?= urlencode($tanggal_filter) ?>&karyawan=<?= $karyawan_filter ?>&status=<?= urlencode($status_filter) ?>" class="btn-export btn-export-excel"><i class="fas fa-file-excel"></i> Export Excel</a>
                    <a href="export_pdf.php?tanggal=<?= urlencode($tanggal_filter) ?>&karyawan=<?= $karyawan_filter ?>&status=<?= urlencode($status_filter) ?>" class="btn-export btn-export-pdf"><i class="fas fa-file-pdf"></i> Export PDF</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Karyawan</th>
                            <th>Jam Masuk</th>
                            <th>Jam Keluar</th>
                            <th>Total Jam</th>
                            <th>Status</th>
                            <th>Foto Masuk</th>
                            <th>Foto Pulang</th>
                            <th>Lokasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($absensi)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-4">
                                    <i class="fas fa-folder-open fa-2x text-muted mb-2 d-block"></i>
                                    Tidak ada data absensi untuk periode ini
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($absensi as $a): 
                                $status_class = '';
                                $status = $a['STATUS'] ?? '-';
                                if($status == 'Hadir') $status_class = 'badge-hadir';
                                elseif($status == 'Terlambat') $status_class = 'badge-terlambat';
                                elseif($status == 'Sakit') $status_class = 'badge-sakit';
                                elseif($status == 'Izin') $status_class = 'badge-izin';
                                elseif($status == 'Alpha') $status_class = 'badge-alpha';
                                
                                $total_jam = isset($a['TOTAL_JAM']) ? round((float)$a['TOTAL_JAM'], 1) : '-';
                                $jam_lembur = isset($a['JAM_LEMBUR']) ? $a['JAM_LEMBUR'] : 0;
                                
                                // Format tanggal - gunakan string langsung
                                $tanggal_display = '-';
                                if(isset($a['TANGGAL']) && !empty($a['TANGGAL'])) {
                                    if(is_string($a['TANGGAL'])) {
                                        $tanggal_display = date('d-m-Y', strtotime($a['TANGGAL']));
                                    } else {
                                        $tanggal_display = '-';
                                    }
                                }
                                
                                // Format jam masuk
                                $jam_masuk_display = '-';
                                if(isset($a['JAM_MASUK']) && !empty($a['JAM_MASUK'])) {
                                    if(is_string($a['JAM_MASUK'])) {
                                        $jam_masuk_display = date('H:i:s', strtotime($a['JAM_MASUK']));
                                    }
                                }
                                
                                // Format jam keluar
                                $jam_keluar_display = '-';
                                if(isset($a['JAM_KELUAR']) && !empty($a['JAM_KELUAR'])) {
                                    if(is_string($a['JAM_KELUAR'])) {
                                        $jam_keluar_display = date('H:i:s', strtotime($a['JAM_KELUAR']));
                                    }
                                }
                                
                                $nama_karyawan = $a['NAMA_KARYAWAN'] ?? '-';
                                $jabatan = $a['JABATAN'] ?? '-';
                                $terlambat = isset($a['TERLAMBAT']) ? (int)$a['TERLAMBAT'] : 0;
                                $foto_masuk = $a['FOTO_MASUK'] ?? '';
                                $foto_pulang = $a['FOTO_PULANG'] ?? '';
                                $alamat_masuk = $a['ALAMAT_MASUK'] ?? '';
                                $id_absensi = $a['ID_ABSENSI'] ?? 0;
                            ?>
                            <tr>
                                <td class="text-center"><?= $id_absensi ?></td>
                                <td><?= $tanggal_display ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($nama_karyawan) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($jabatan) ?></small>
                                </td>
                                <td><?= $jam_masuk_display ?></td>
                                <td><?= $jam_keluar_display ?></td>
                                <td>
                                    <?= $total_jam ?> jam
                                    <?php if($jam_lembur > 0): ?>
                                        <br><small class="text-success">Lembur <?= $jam_lembur ?> jam</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?= $status_class ?>"><?= $status ?></span>
                                    <?= $terlambat > 0 ? "<br><small class='text-danger'>Telat {$terlambat} menit</small>" : '' ?>
                                </td>
                                <td class="text-center">
                                    <?php if(!empty($foto_masuk)): ?>
                                        <img src="../../<?= htmlspecialchars($foto_masuk) ?>" class="foto-preview" onclick="showFoto('<?= htmlspecialchars($foto_masuk) ?>')" title="Klik untuk lihat foto" alt="Foto Masuk">
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if(!empty($foto_pulang)): ?>
                                        <img src="../../<?= htmlspecialchars($foto_pulang) ?>" class="foto-preview" onclick="showFoto('<?= htmlspecialchars($foto_pulang) ?>')" title="Klik untuk lihat foto" alt="Foto Pulang">
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!empty($alamat_masuk)): ?>
                                        <small><i class="fas fa-map-marker-alt"></i> <?= strlen($alamat_masuk) > 40 ? substr($alamat_masuk, 0, 40) . '...' : htmlspecialchars($alamat_masuk) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-info" onclick="detailAbsensi(<?= $id_absensi ?>)" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editStatusModal" data-id="<?= $id_absensi ?>" data-status="<?= $status ?>" title="Edit Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $id_absensi ?>)" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($total_pages > 1): ?>
            <div class="pagination-custom">
                <?php if($page > 1): ?>
                    <a class="page-link" href="?page=<?= $page-1 ?>&tanggal=<?= urlencode($tanggal_filter) ?>&karyawan=<?= $karyawan_filter ?>&status=<?= urlencode($status_filter) ?>">« Prev</a>
                <?php endif; ?>
                
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                for($i=$start_page; $i<=$end_page; $i++): 
                ?>
                    <a class="page-link <?= $i==$page?'active':'' ?>" href="?page=<?= $i ?>&tanggal=<?= urlencode($tanggal_filter) ?>&karyawan=<?= $karyawan_filter ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                    <a class="page-link" href="?page=<?= $page+1 ?>&tanggal=<?= urlencode($tanggal_filter) ?>&karyawan=<?= $karyawan_filter ?>&status=<?= urlencode($status_filter) ?>">Next »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="mt-3 text-muted small">
                <i class="fas fa-info-circle"></i> Menampilkan <?= count($absensi) ?> dari <?= $total ?> data
            </div>
        </div>
    </div>

    <!-- Modal Edit Status -->
    <div class="modal fade" id="editStatusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Status Absensi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="id_absensi" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="Hadir">Hadir</option>
                                <option value="Terlambat">Terlambat</option>
                                <option value="Sakit">Sakit</option>
                                <option value="Izin">Izin</option>
                                <option value="Alpha">Alpha</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash"></i> Konfirmasi Hapus</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="delete_absensi" value="1">
                        <input type="hidden" name="id_absensi" id="delete_id">
                        <p>Apakah Anda yakin ingin menghapus data absensi ini?</p>
                        <p class="text-danger"><small>Tindakan ini tidak dapat dibatalkan dan akan menghapus foto-foto terkait.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Foto -->
    <div class="modal fade" id="fotoModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Foto Absensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="fotoPreview" src="" style="max-width:100%; border-radius: 10px;">
                </div>
            </div>
        </div>
    </div>

    <script>
        function showFoto(path) {
            const fotoPreview = document.getElementById('fotoPreview');
            if(fotoPreview) {
                fotoPreview.src = '../../' + path;
                new bootstrap.Modal(document.getElementById('fotoModal')).show();
            }
        }
        
        function detailAbsensi(id) {
            window.location.href = 'detail.php?id=' + id;
        }
        
        function confirmDelete(id) {
            const deleteId = document.getElementById('delete_id');
            if(deleteId) deleteId.value = id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        const editStatusModal = document.getElementById('editStatusModal');
        if(editStatusModal) {
            editStatusModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const status = button.getAttribute('data-status');
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_status').value = status;
            });
        }
        
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if(menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
        
        document.addEventListener('click', function(event) {
            if(window.innerWidth <= 992 && sidebar && menuToggle) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickOnToggle = menuToggle.contains(event.target);
                if(!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
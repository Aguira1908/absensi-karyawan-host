<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}
include '../../assets/config/koneksi.php';

echo "<h1>Debug: Cek Data Jadwal</h1>";

if($conn && $conn_type) {
    echo "<p>Koneksi berhasil, tipe: <strong>" . strtoupper($conn_type) . "</strong></p>";
    
    try {
        if ($conn_type === 'pdo') {
            $sql = "SELECT j.*, k.nama, k.jabatan 
                    FROM jadwal j 
                    JOIN karyawan k ON j.id_karyawan = k.id_karyawan 
                    ORDER BY j.tanggal DESC";
            $stmt = $conn->query($sql);
            $jadwal = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT j.*, k.nama, k.jabatan 
                    FROM jadwal j 
                    JOIN karyawan k ON j.id_karyawan = k.id_karyawan 
                    ORDER BY j.tanggal DESC";
            $stmt = oci_parse($conn, $sql);
            oci_execute($stmt);
            $jadwal = [];
            while($row = oci_fetch_assoc($stmt)) {
                $jadwal[] = $row;
            }
        }
        
        echo "<h2>Jumlah Data: " . count($jadwal) . "</h2>";
        
        if(count($jadwal) > 0) {
            echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
            echo "<tr>";
            // Tampilkan header kolom
            foreach(array_keys($jadwal[0]) as $key) {
                echo "<th>" . htmlspecialchars($key) . "</th>";
            }
            echo "</tr>";
            
            foreach($jadwal as $row) {
                echo "<tr>";
                foreach($row as $value) {
                    echo "<td>" . htmlspecialchars($value ?? '-') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: red;'>Tidak ada data jadwal di database!</p>";
        }
        
    } catch(Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>Koneksi database gagal!</p>";
}

echo "<br><br>";
echo "<a href='index.php'>Kembali ke Manajemen Jadwal</a>";
?>
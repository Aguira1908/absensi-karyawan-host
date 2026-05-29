<?php
echo "<h1>Diagnosis Koneksi Oracle</h1>";

// 1. Cek Extension
echo "<h2>1. Cek Extension PHP</h2>";
$extensions = [
    'PDO' => extension_loaded('pdo'),
    'PDO_OCI' => extension_loaded('pdo_oci'),
    'OCI8' => extension_loaded('oci8'),
];

foreach($extensions as $name => $loaded) {
    $status = $loaded ? '✓ Tersedia' : '✗ Tidak Tersedia';
    $color = $loaded ? 'green' : 'red';
    echo "<p style='color: $color'>$name: $status</p>";
}

// 2. Coba koneksi
echo "<h2>2. Coba Koneksi ke Oracle</h2>";

$configs = [
    ['XE', 'localhost/XE'],
    ['XEPDB1', 'localhost/XEPDB1'],
    ['localhost/XE', 'localhost/XE'],
];

$username = 'cafe_user';
$password = 'password';

foreach($configs as $config) {
    $service = $config[0];
    $conn_str = $config[1];
    
    echo "<h3>Mencoba dengan: $service</h3>";
    
    if(extension_loaded('pdo_oci')) {
        try {
            $conn = new PDO("oci:dbname=//$conn_str;charset=UTF8", $username, $password);
            echo "<p style='color: green'>✓ PDO Koneksi BERHASIL dengan service: $service</p>";
            
            // Test query
            $stmt = $conn->query("SELECT 'Connected' as status FROM DUAL");
            $result = $stmt->fetch();
            echo "<p>Query test: " . $result['STATUS'] . "</p>";
            
            echo "<p style='color: green; font-weight: bold'>✅ Gunakan service_name = '$service' di file koneksi.php</p>";
            break;
        } catch(PDOException $e) {
            echo "<p style='color: red'>✗ PDO Gagal: " . $e->getMessage() . "</p>";
        }
    }
    
    if(function_exists('oci_connect')) {
        $conn = oci_connect($username, $password, $conn_str, 'AL32UTF8');
        if($conn) {
            echo "<p style='color: green'>✓ OCI8 Koneksi BERHASIL dengan service: $service</p>";
            echo "<p style='color: green; font-weight: bold'>✅ Gunakan service_name = '$service' di file koneksi.php</p>";
            break;
        } else {
            $e = oci_error();
            echo "<p style='color: red'>✗ OCI8 Gagal: " . ($e['message'] ?? 'Unknown error') . "</p>";
        }
    }
}

echo "<h2>3. Informasi Oracle Listener</h2>";
echo "<p>Jalankan perintah di Command Prompt (Admin):</p>";
echo "<code>lsnrctl services</code>";
echo "<br><br>";
echo "<p>Atau cek status listener:</p>";
echo "<code>lsnrctl status</code>";
?>
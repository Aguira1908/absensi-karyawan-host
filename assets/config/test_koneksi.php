<?php
require_once 'koneksi.php';

echo "<h2>Status Koneksi</h2>";
if ($conn) {
    echo "<p style='color:green'>✅ Koneksi berhasil! Type: " . $conn_type . "</p>";
    
    // Test query sederhana
    if ($conn_type == 'mysql' || $conn_type == 'pdo') {
        $stmt = $conn->query("SELECT 'Connected!' as status FROM DUAL");
        $result = $stmt->fetch();
        echo "<p>Query test: " . $result['STATUS'] . "</p>";
    } else if ($conn_type == 'oci8') {
        $stmt = oci_parse($conn, "SELECT 'Connected!' as status FROM DUAL");
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        echo "<p>Query test: " . $row['STATUS'] . "</p>";
        oci_free_statement($stmt);
    }
} else {
    echo "<p style='color:red'>❌ Koneksi gagal: " . htmlspecialchars($error_message) . "</p>";
    echo "<h3>Debug Info:</h3>";
    echo "<ul>";
    echo "<li>PDO_OCI: " . (extension_loaded('pdo_oci') ? 'Loaded' : 'Not loaded') . "</li>";
    echo "<li>OCI8: " . (extension_loaded('oci8') ? 'Loaded' : 'Not loaded') . "</li>";
    echo "<li>PDO: " . (extension_loaded('pdo') ? 'Loaded' : 'Not loaded') . "</li>";
    echo "</ul>";
}
?>
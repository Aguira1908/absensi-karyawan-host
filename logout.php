<?php
session_start();

// Hapus session dari database
if(isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    require_once __DIR__ . '/assets/config/koneksi.php';
    
    if($conn && $conn_type == 'pdo') {
        $session_id = session_id();
        $stmt = $conn->prepare("UPDATE user_sessions SET status = 'ended', ended_at = NOW() WHERE session_id = :session_id");
        $stmt->execute([':session_id' => $session_id]);
    }
}

// Hapus semua session
session_unset();
session_destroy();

// Redirect ke halaman login
header("Location: index.php");
exit();
?>
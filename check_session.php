<?php
// File ini bisa di-include di setiap halaman untuk mengecek session
function checkUserSession($conn) {
    global $conn_type;
    
    if(!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $session_id = session_id();
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    if($conn && $conn_type == 'pdo') {
        $stmt = $conn->prepare("SELECT * FROM user_sessions 
                               WHERE session_id = :session_id 
                               AND user_id = :user_id 
                               AND role = :role 
                               AND status = 'active'");
        $stmt->execute([
            ':session_id' => $session_id,
            ':user_id' => $user_id,
            ':role' => $role
        ]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$session) {
            // Session tidak valid, logout
            session_unset();
            session_destroy();
            header("Location: login.php?msg=session_expired");
            exit();
        }
        
        // Update last activity
        $stmt = $conn->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = :session_id");
        $stmt->execute([':session_id' => $session_id]);
    }
    
    return true;
}
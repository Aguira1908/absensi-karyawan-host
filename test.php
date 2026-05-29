<?php
session_start();

// Fungsi untuk mengecek session aktif
function isUserLoggedIn($user_id, $role, $conn) {
    $query = "SELECT session_id, last_activity FROM user_sessions WHERE user_id = '$user_id' AND role = '$role' AND status = 'active'";
    // Sesuaikan dengan tipe database
    return false; // Implementasi sederhana
}

// Jika sudah login, redirect sesuai role
if(isset($_SESSION['user_id'])) {
    if($_SESSION['role'] == 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
}

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    include 'assets/config/koneksi.php';
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    $hashed_password = md5($password);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $session_id = session_id();
    
    $login_success = false;
    $is_admin = false;
    $user_data = null;
    
    // Coba login ke database jika koneksi tersedia
    if($conn && $conn_type) {
        try {
            if ($conn_type === 'pdo') {
                // Cek admin
                $stmt = $conn->prepare("SELECT * FROM admin WHERE username = :username AND password = :password");
                $stmt->execute([':username' => $username, ':password' => $hashed_password]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($admin) {
                    $login_success = true;
                    $is_admin = true;
                    $user_data = $admin;
                    $user_data['ID'] = $admin['ID_ADMIN'];
                } else {
                    // Cek user karyawan
                    $stmt = $conn->prepare("SELECT u.*, k.nama FROM users u 
                                           JOIN karyawan k ON u.id_karyawan = k.id_karyawan 
                                           WHERE u.username = :username AND u.password = :password AND u.is_active = 'Y'");
                    $stmt->execute([':username' => $username, ':password' => $hashed_password]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if($user) {
                        $login_success = true;
                        $user_data = $user;
                        $user_data['ID'] = $user['ID_USER'];
                    }
                }
            } else {
                // OCI8
                $sql = "SELECT * FROM admin WHERE username = :username AND password = :password";
                $stmt = oci_parse($conn, $sql);
                oci_bind_by_name($stmt, ':username', $username);
                oci_bind_by_name($stmt, ':password', $hashed_password);
                oci_execute($stmt);
                $admin = oci_fetch_assoc($stmt);
                
                if($admin) {
                    $login_success = true;
                    $is_admin = true;
                    $user_data = $admin;
                    $user_data['ID'] = $admin['ID_ADMIN'];
                } else {
                    $sql = "SELECT u.*, k.nama FROM users u 
                            JOIN karyawan k ON u.id_karyawan = k.id_karyawan 
                            WHERE u.username = :username AND u.password = :password AND u.is_active = 'Y'";
                    $stmt = oci_parse($conn, $sql);
                    oci_bind_by_name($stmt, ':username', $username);
                    oci_bind_by_name($stmt, ':password', $hashed_password);
                    oci_execute($stmt);
                    $user = oci_fetch_assoc($stmt);
                    if($user) {
                        $login_success = true;
                        $user_data = $user;
                        $user_data['ID'] = $user['ID_USER'];
                    }
                }
            }
        } catch(Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    // FALLBACK: Jika database tidak terkoneksi atau data tidak ditemukan, gunakan hardcoded
    if(!$login_success) {
        if($username == 'admin' && $password == 'admin123') {
            $login_success = true;
            $is_admin = true;
            $user_data = ['ID' => 1, 'NAMA' => 'Administrator'];
        } elseif($username == 'user' && $password == 'user123') {
            $login_success = true;
            $is_admin = false;
            $user_data = ['ID' => 1, 'NAMA' => 'Karyawan Demo', 'ID_KARYAWAN' => 1];
        }
    }
    
    if($login_success) {
        // Cek apakah user sudah login di tempat lain (optional)
        if($conn && $conn_type == 'pdo') {
            // Cek session aktif untuk user ini
            $stmt = $conn->prepare("SELECT * FROM user_sessions WHERE user_id = :user_id AND role = :role AND status = 'active'");
            $stmt->execute([':user_id' => $user_data['ID'], ':role' => $is_admin ? 'admin' : 'user']);
            $existing_session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($existing_session) {
                // Tawarkan untuk logout session sebelumnya
                $error = 'Akun ini sedang aktif digunakan di perangkat lain. Apakah Anda ingin tetap login dan mengeluarkan yang lain?';
                // Simpan data login sementara
                $_SESSION['pending_login'] = [
                    'user_id' => $user_data['ID'],
                    'role' => $is_admin ? 'admin' : 'user',
                    'nama' => $user_data['NAMA'],
                    'id_karyawan' => $user_data['ID_KARYAWAN'] ?? null,
                    'username' => $username,
                    'is_admin' => $is_admin
                ];
                // Tampilkan opsi
                echo '<!DOCTYPE html>
                <html>
                <head>
                    <title>Konfirmasi Login</title>
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                    <style>
                        body{font-family:Arial,sans-serif;background:#2C1810;display:flex;justify-content:center;align-items:center;min-height:100vh}
                        .confirm-card{background:white;border-radius:20px;padding:30px;max-width:400px;text-align:center}
                        .confirm-card i{font-size:50px;color:#ffc107;margin-bottom:20px}
                        .btn-primary{background:#C8A951;color:white;padding:10px 20px;border:none;border-radius:10px;cursor:pointer;margin:5px}
                        .btn-secondary{background:#6c757d;color:white;padding:10px 20px;border:none;border-radius:10px;cursor:pointer;margin:5px}
                    </style>
                </head>
                <body>
                    <div class="confirm-card">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Akun Sedang Aktif</h3>
                        <p>Akun ini sedang digunakan di perangkat lain. Apakah Anda ingin tetap login?</p>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="force_login" value="1">
                            <button type="submit" class="btn-primary">Ya, Login dan Keluarkan yang Lain</button>
                        </form>
                        <a href="index.php" class="btn-secondary">Batal</a>
                    </div>
                </body>
                </html>';
                exit();
            }
        }
        
        // Proses login normal
        $_SESSION['user_id'] = $user_data['ID'];
        $_SESSION['role'] = $is_admin ? 'admin' : 'user';
        $_SESSION['nama'] = $user_data['NAMA'] ?? ($is_admin ? 'Administrator' : 'Karyawan');
        $_SESSION['id_karyawan'] = $user_data['ID_KARYAWAN'] ?? null;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $ip_address;
        
        // Simpan session ke database
        if($conn && $conn_type == 'pdo') {
            // Nonaktifkan session lama
            $stmt = $conn->prepare("UPDATE user_sessions SET status = 'ended', ended_at = NOW() 
                                   WHERE user_id = :user_id AND role = :role AND status = 'active'");
            $stmt->execute([':user_id' => $user_data['ID'], ':role' => $is_admin ? 'admin' : 'user']);
            
            // Buat session baru
            $stmt = $conn->prepare("INSERT INTO user_sessions (session_id, user_id, role, username, ip_address, user_agent, login_time, status) 
                                   VALUES (:session_id, :user_id, :role, :username, :ip, :agent, NOW(), 'active')");
            $stmt->execute([
                ':session_id' => $session_id,
                ':user_id' => $user_data['ID'],
                ':role' => $is_admin ? 'admin' : 'user',
                ':username' => $username,
                ':ip' => $ip_address,
                ':agent' => $user_agent
            ]);
        }
        
        if($is_admin) {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: user/dashboard.php");
        }
        exit();
    } else {
        $error = 'Username atau password salah!';
    }
}

// Proses force login (keluarkan session lain)
if(isset($_POST['force_login']) && isset($_SESSION['pending_login'])) {
    $pending = $_SESSION['pending_login'];
    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    if($conn && $conn_type == 'pdo') {
        // Hapus session lama
        $stmt = $conn->prepare("UPDATE user_sessions SET status = 'force_ended', ended_at = NOW() 
                               WHERE user_id = :user_id AND role = :role AND status = 'active'");
        $stmt->execute([':user_id' => $pending['user_id'], ':role' => $pending['role']]);
        
        // Buat session baru
        $stmt = $conn->prepare("INSERT INTO user_sessions (session_id, user_id, role, username, ip_address, user_agent, login_time, status) 
                               VALUES (:session_id, :user_id, :role, :username, :ip, :agent, NOW(), 'active')");
        $stmt->execute([
            ':session_id' => $session_id,
            ':user_id' => $pending['user_id'],
            ':role' => $pending['role'],
            ':username' => $pending['username'],
            ':ip' => $ip_address,
            ':agent' => $user_agent
        ]);
    }
    
    $_SESSION['user_id'] = $pending['user_id'];
    $_SESSION['role'] = $pending['role'];
    $_SESSION['nama'] = $pending['nama'];
    $_SESSION['id_karyawan'] = $pending['id_karyawan'];
    $_SESSION['username'] = $pending['username'];
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $ip_address;
    
    unset($_SESSION['pending_login']);
    
    if($pending['is_admin']) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Login - Ck-Ck Coffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a0f0a 0%, #2c1a12 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card{
            background: white;
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        .login-card:hover{
            transform: translateY(-5px);
        }
        .login-header{
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header i{
            font-size: 60px;
            color: #C8A951;
            margin-bottom: 20px;
        }
        .login-header h2{
            color: #2C1810;
            font-weight: 700;
            font-family: 'Playfair Display', serif;
        }
        .login-header p{
            color: #666;
            font-size: 0.9rem;
        }
        .form-group{
            margin-bottom: 20px;
        }
        .form-group label{
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }
        .form-control{
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-control:focus{
            outline: none;
            border-color: #C8A951;
            box-shadow: 0 0 0 3px rgba(200,169,81,0.25);
        }
        .btn-primary{
            width: 100%;
            background: linear-gradient(135deg, #C8A951, #e6b422);
            color: #2C1810;
            padding: 12px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary:hover{
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,169,81,0.3);
        }
        .alert-danger{
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.85rem;
        }
        .alert-warning{
            background: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.85rem;
            border-left: 4px solid #ffc107;
        }
        .info-demo{
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            font-size: 12px;
            text-align: center;
        }
        code{
            background: #e9ecef;
            padding: 2px 5px;
            border-radius: 4px;
            color: #C8A951;
        }
        .back-link{
            text-align: center;
            margin-top: 15px;
        }
        .back-link a{
            color: #C8A951;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .back-link a:hover{
            text-decoration: underline;
        }
        @media (max-width: 480px){
            .login-card{
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-mug-hot"></i>
            <h2>Ck-Ck Coffee</h2>
            <p>Sistem Operasional Cafe</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn-primary">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="info-demo">
            <i class="fas fa-info-circle"></i>
            <p><strong>Silakan untuk karyawan terlebih dahulu minta info login ke admin</strong></p>
            <hr style="margin: 10px 0;">
            <small>Demo: <code>admin</code> / <code>admin123</code> | <code>user</code> / <code>user123</code></small>
        </div>
        
        <div class="back-link">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
        </div>
    </div>
</body>
</html>
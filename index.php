<?php
session_start();

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
$pending_message = '';

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
        // Cek apakah user sudah login di tempat lain
        if($conn && $conn_type == 'pdo') {
            try {
                $stmt = $conn->prepare("SELECT * FROM user_sessions WHERE user_id = :user_id AND role = :role AND status = 'active'");
                $stmt->execute([':user_id' => $user_data['ID'], ':role' => $is_admin ? 'admin' : 'user']);
                $existing_session = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($existing_session) {
                    // Simpan data login sementara untuk force login
                    $_SESSION['pending_login'] = [
                        'user_id' => $user_data['ID'],
                        'role' => $is_admin ? 'admin' : 'user',
                        'nama' => $user_data['NAMA'],
                        'id_karyawan' => $user_data['ID_KARYAWAN'] ?? null,
                        'username' => $username,
                        'is_admin' => $is_admin
                    ];
                    $pending_message = 'Akun ini sedang aktif digunakan di perangkat lain. Apakah Anda ingin tetap login dan mengeluarkan yang lain?';
                }
            } catch(Exception $e) {
                // Tabel mungkin belum ada, lanjutkan login normal
            }
        }
        
        // Jika tidak ada pending session, langsung login
        if(empty($pending_message)) {
            // Proses login normal
            $_SESSION['user_id'] = $user_data['ID'];
            $_SESSION['role'] = $is_admin ? 'admin' : 'user';
            $_SESSION['nama'] = $user_data['NAMA'] ?? ($is_admin ? 'Administrator' : 'Karyawan');
            $_SESSION['id_karyawan'] = $user_data['ID_KARYAWAN'] ?? null;
            $_SESSION['username'] = $username;
            $_SESSION['login_time'] = time();
            $_SESSION['ip_address'] = $ip_address;
            
            // Simpan session ke database jika tabel tersedia
            if($conn && $conn_type == 'pdo') {
                try {
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
                } catch(Exception $e) {
                    // Abaikan error jika tabel belum ada
                }
            }
            
            if($is_admin) {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: user/dashboard.php");
            }
            exit();
        }
    } else {
        $error = 'Username atau password salah!';
    }
}

// Proses force login (keluarkan session lain)
if(isset($_POST['force_login']) && isset($_SESSION['pending_login'])) {
    include 'assets/config/koneksi.php';
    
    $pending = $_SESSION['pending_login'];
    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    if($conn && $conn_type == 'pdo') {
        try {
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
        } catch(Exception $e) {
            // Abaikan error
        }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Ck-Ck Coffee - Sistem Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }

        .app-container {
            max-width: 450px;
            margin: 0 auto;
            background: #ffffff;
            min-height: 100vh;
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        /* Status Bar */
        .status-bar {
            padding: 12px 20px;
            background: white;
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 500;
            border-bottom: 1px solid #f0f0f0;
        }

        /* Header */
        .header {
            padding: 20px;
            background: white;
            text-align: center;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #2C1810, #C8A951);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(200,169,81,0.4); }
            50% { box-shadow: 0 0 0 15px rgba(200,169,81,0); }
        }

        .logo-icon i {
            font-size: 2rem;
            color: white;
        }

        .logo-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: #2C1810;
        }

        .tagline {
            font-size: 0.8rem;
            color: #C8A951;
            margin-top: 5px;
            letter-spacing: 1px;
        }

        /* Navigasi Tabs */
        .nav-tabs-custom {
            display: flex;
            background: white;
            padding: 0 20px;
            gap: 30px;
            border-bottom: 1px solid #f0f0f0;
        }

        .nav-tab {
            padding: 12px 0;
            font-weight: 600;
            color: #888;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
            background: none;
            border: none;
            font-size: 0.95rem;
        }

        .nav-tab.active {
            color: #C8A951;
        }

        .nav-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: #C8A951;
            border-radius: 2px;
        }

        /* Content Panel */
        .content-panel {
            display: none;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .content-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Card */
        .card-custom {
            background: white;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }

        /* About Section */
        .hero-about {
            background: linear-gradient(135deg, #2C1810, #C8A951);
            border-radius: 24px;
            padding: 35px 24px;
            text-align: center;
            color: white;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .hero-about::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .hero-about h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }

        .hero-about p {
            font-size: 0.95rem;
            opacity: 0.95;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
            line-height: 1.6;
        }

        .btn-join {
            background: white;
            color: #2C1810;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            text-decoration: none;
        }

        .btn-join:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        /* Quote Section */
        .quote-section {
            text-align: center;
            padding: 20px;
            background: #fdf8f0;
            border-radius: 20px;
            margin-bottom: 20px;
        }

        .quote-section i {
            font-size: 2rem;
            color: #C8A951;
            margin-bottom: 10px;
        }

        .quote-section p {
            font-style: italic;
            color: #555;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .quote-author {
            margin-top: 10px;
            font-weight: 600;
            color: #2C1810;
        }

        /* Info Cards */
        .info-grid {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-card {
            flex: 1;
            background: #f8f9fa;
            border-radius: 16px;
            padding: 15px;
            text-align: center;
        }

        .info-card i {
            font-size: 1.5rem;
            color: #C8A951;
            margin-bottom: 8px;
        }

        .info-card h4 {
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .info-card p {
            font-size: 0.7rem;
            color: #888;
        }

        /* Login Form */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #666;
            margin-bottom: 8px;
            display: block;
        }

        .form-control-custom {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #e8e8e8;
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: #C8A951;
            background: white;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #2C1810, #C8A951);
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            color: white;
            width: 100%;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,169,81,0.3);
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.85rem;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.85rem;
            border-left: 4px solid #ffc107;
        }

        .info-demo {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            font-size: 0.7rem;
            text-align: center;
        }

        .info-demo code {
            background: #e9ecef;
            padding: 2px 5px;
            border-radius: 4px;
            color: #C8A951;
        }

        .back-link {
            text-align: center;
            margin-top: 15px;
        }

        .back-link a {
            color: #C8A951;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        /* Privacy Footer */
        .privacy-footer {
            padding: 20px;
            background: #fafafa;
            border-top: 1px solid #f0f0f0;
            font-size: 0.7rem;
            color: #999;
            text-align: center;
            line-height: 1.5;
        }

        /* Force Login Modal */
        .modal-force {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .modal-force-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 350px;
            text-align: center;
        }
        .modal-force-content i {
            font-size: 50px;
            color: #ffc107;
            margin-bottom: 20px;
        }
        .btn-force {
            padding: 10px 20px;
            border-radius: 10px;
            margin: 5px;
            border: none;
            cursor: pointer;
        }
        .btn-force-primary {
            background: #C8A951;
            color: white;
        }
        .btn-force-secondary {
            background: #6c757d;
            color: white;
        }

        @media (max-width: 480px) {
            .app-container { max-width: 100%; }
            .hero-about h2 { font-size: 1.6rem; }
            .logo-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <?php if($pending_message): ?>
    <!-- Modal Konfirmasi Force Login -->
    <div class="modal-force">
        <div class="modal-force-content">
            <i class="fas fa-exclamation-triangle"></i>
            <h4>Akun Sedang Aktif</h4>
            <p><?= $pending_message ?></p>
            <form method="POST" style="display: inline;">
                <button type="submit" name="force_login" class="btn-force btn-force-primary">Ya, Login dan Keluarkan yang Lain</button>
            </form>
            <a href="index.php" class="btn-force btn-force-secondary" style="text-decoration:none;">Batal</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="app-container">
        <!-- Status Bar -->
        <div class="status-bar">
            <span></span>
            <span></span>
        </div>

        <!-- Header -->
        <div class="header">
            <div class="logo-icon">
                <i class="fas fa-mug-hot"></i>
            </div>
            <div class="logo-title">Ck-Ck Coffee</div>
        </div>

        <!-- Navigasi Tabs -->
        <div class="nav-tabs-custom">
            <button class="nav-tab active" data-panel="about">✨ About</button>
            <button class="nav-tab" data-panel="login">🔐 Login</button>
        </div>

        <!-- Panel About -->
        <div class="content-panel active" id="panel-about">
            <div class="hero-about">
                <h2>✨ Selamat Datang ✨</h2>
                <p>Yuk, catat kehadiranmu dengan mudah dan cepat!</p>
                <button class="btn-join" onclick="switchToLogin()">
                    <i class="fas fa-arrow-right"></i> Mulai Absen Yuk!
                </button>
            </div>

            <div class="quote-section">
                <i class="fas fa-quote-right"></i>
                <p>Good coffee, good teamwork, good day<br>"Semangat bekerja, jangan lupa senyum hari ini"</p>
                <div class="quote-author">— Manajemen Ck-Ck Coffee</div>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <i class="fas fa-clock"></i>
                    <h4>Disiplin Waktu</h4>
                    <p>Waktu adalah uang</p>
                </div>
                <div class="info-card">
                    <i class="fas fa-users"></i>
                    <h4>Utamakan adab & etika</h4>
                    <p></p>
                </div>
                <div class="info-card">
                    <i class="fas fa-coffee"></i>
                    <h4>Free makan & es teh</h4>
                    <p>Setiap Shift</p>
                </div>
            </div>
        </div>

        <!-- Panel Login -->
        <div class="content-panel" id="panel-login">
            <div class="card-custom">
                <div class="text-center mb-3">
                    <i class="fas fa-mug-hot" style="font-size: 2.5rem; color: #C8A951;"></i>
                    <h3 style="font-family: 'Playfair Display', serif; margin-top: 10px;">Login</h3>
                    <p style="font-size: 0.8rem; color: #666;">Akses Sistem Absensi Ck-Ck Coffee</p>
                </div>
                
                <?php if($error && !$pending_message): ?>
                    <div class="alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control-custom" placeholder="Masukkan username" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control-custom" placeholder="Masukkan password" required>
                    </div>
                    <button type="submit" class="btn-primary-custom">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="info-demo">
                    <i class="fas fa-info-circle"></i>
                    <p><strong>Silakan untuk karyawan terlebih dahulu minta info login ke admin</strong></p>
                    <hr style="margin: 10px 0;">
                </div>
                
                <div class="back-link">
                    <a href="index.php"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
                </div>
            </div>
        </div>

        <!-- Privacy Footer -->
        <div class="privacy-footer">
            <p><i class="fas fa-shield-alt"></i> Sistem absensi digital Ck-Ck Coffee</p>
            <p style="margin-top: 8px;">© Ck-Ck Coffee</p>
        </div>
    </div>

    <script>
        // Tab Navigation
        const tabs = document.querySelectorAll('.nav-tab');
        const panels = document.querySelectorAll('.content-panel');
        
        function switchPanel(panelId) {
            panels.forEach(panel => {
                panel.classList.remove('active');
                if(panel.id === `panel-${panelId}`) {
                    panel.classList.add('active');
                }
            });
            
            tabs.forEach(tab => {
                tab.classList.remove('active');
                if(tab.dataset.panel === panelId) {
                    tab.classList.add('active');
                }
            });
            
            window.location.hash = panelId;
        }
        
        function switchToLogin() {
            switchPanel('login');
        }
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                switchPanel(tab.dataset.panel);
            });
        });
        
        if(window.location.hash) {
            const hash = window.location.hash.substring(1);
            if(['about', 'login'].includes(hash)) {
                switchPanel(hash);
            }
        }
        
        // Animasi scroll elements
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if(entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.info-card, .quote-section').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.5s ease';
            observer.observe(el);
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// login.php - BİLDİRİM FİLTRESİ VE GÜVENLİK İÇİN GÜNCELLENDİ
session_start();
include 'db.php';

$error = "";

if (isset($_POST['login'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    // 1. ÖNCE RESTORAN SAHİBİ (VEYA ADMİN) TABLOSUNA BAK
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $row = $stmt->fetch();

    $login_success = false;
    $target_redirect = "";

    // A) KULLANICI (DÜKKAN SAHİBİ) GİRİŞİ
    if ($row) {
        // Şifre Kontrolü (Hem Hash'li hem de Düz Metin şifreleri kabul eder)
        if (password_verify($pass, $row['password']) || $pass === $row['password']) {
            $login_success = true;
            
            // Aktiflik Kontrolü
            if (isset($row['is_active']) && $row['is_active'] == 0) {
                $error = "Hesabınız pasif duruma getirilmiştir. Yönetici ile iletişime geçin.";
                $login_success = false;
            } else {
                // Session Atamaları
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['store_name'] = $row['store_name'];
                
                // Dükkan sahibi olduğu için staff_id 0 (Bildirimleri her türlü görür)
                $_SESSION['staff_id'] = 0; 

                // ZORUNLU ŞİFRE DEĞİŞTİRME KONTROLÜ (Şifremi unuttum sonrası)
                if (isset($row['password_reset_required']) && $row['password_reset_required'] == 1) {
                    header("Location: change_password.php"); 
                    exit;
                }

                $target_redirect = ($row['role'] == 'admin') ? "admin.php" : "panel.php";
            }
        }
    } 
    
    // B) PERSONEL GİRİŞİ (GARSON, MUTFAK, KASA)
    // Eğer kullanıcı tablosunda bulunamadıysa veya şifre uymadıysa buraya bakar
    if (!$login_success) {
        $stmt2 = $pdo->prepare("SELECT * FROM staff WHERE username = ?");
        $stmt2->execute([$user]);
        $staff = $stmt2->fetch();

        if ($staff) {
            // Personel Şifre Kontrolü
            if (password_verify($pass, $staff['password']) || $pass === $staff['password']) {
                
                // Personelin bağlı olduğu dükkan sahibi Pasif mi?
                $ownerCheck = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
                $ownerCheck->execute([$staff['user_id']]);
                $ownerActive = $ownerCheck->fetchColumn();

                if ($ownerActive !== false && $ownerActive == 0) {
                    $error = "Restoran hesabı pasif olduğu için giriş yapılamaz.";
                } else {
                    // Session Atamaları
                    $_SESSION['user_id'] = $staff['user_id']; // Dükkan sahibinin ID'si (Verileri çekmek için)
                    $_SESSION['role'] = $staff['role'];       // Rolü (waiter, kitchen vs.)
                    
                    // !!! BU SATIR BİLDİRİM FİLTRESİ İÇİN HAYATİDİR !!!
                    $_SESSION['staff_id'] = $staff['id'];     
                    
                    $_SESSION['staff_name'] = $staff['name'];

                    // Role göre yönlendirme
                    if ($staff['role'] == 'kitchen') { $target_redirect = "kitchen.php"; }
                    elseif ($staff['role'] == 'waiter') { $target_redirect = "waiter.php"; }
                    elseif ($staff['role'] == 'cashier') { $target_redirect = "cashier.php"; }
                    
                    $login_success = true;
                }
            }
        }
    }

    // YÖNLENDİRME İŞLEMİ
    if ($login_success && !empty($target_redirect)) {
        header("Location: " . $target_redirect);
        exit;
    } elseif (empty($error)) {
        $error = "Kullanıcı adı veya şifre hatalı!";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Yönetim Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border-radius: 15px;
            overflow: hidden;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .login-header {
            background-color: #343a40;
            color: white;
            padding: 25px;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="card login-card">
        <div class="login-header">
            <h3 class="mb-1"><i class="bi bi-shield-lock"></i> Giriş Yap</h3>
            <small class="text-white-50">Restoran Yönetim Sistemi</small>
        </div>
        <div class="card-body p-4">
            
            <?php if($error): ?>
                <div class="alert alert-danger text-center"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control form-control-lg" required autofocus placeholder="Kullanıcı adınız">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary">Şifre</label>
                    <input type="password" name="password" class="form-control form-control-lg" required placeholder="******">
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100 btn-lg fw-bold mb-3 shadow-sm">GİRİŞ YAP</button>
            </form>

            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                <a href="register.php" class="text-decoration-none fw-bold small">Hesap Oluştur</a>
                <a href="forgot_password.php" class="text-decoration-none text-secondary small">Şifremi Unuttum</a>
            </div>

        </div>
    </div>

</body>
</html>
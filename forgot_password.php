<?php
// forgot_password.php - TEK KULLANIMLIK ŞİFRE VE ZORUNLU DEĞİŞİKLİK
session_start();
include 'db.php';

$message = "";
$msg_type = "";

if (isset($_POST['reset_password'])) {
    $email = trim($_POST['email']);

    // 1. E-posta sistemde kayıtlı mı?
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Rastgele geçici şifre üret (8 karakter)
        $temp_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
        
        // 3. Şifreyi Hashle (Güvenlik için)
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        // 4. Veritabanını Güncelle: Şifreyi yaz VE password_reset_required = 1 yap
        $update = $pdo->prepare("UPDATE users SET password = ?, password_reset_required = 1 WHERE id = ?");
        $result = $update->execute([$hashed_password, $user['id']]);

        if ($result) {
            // 5. E-posta Gönderimi
            $to = $email;
            $subject = "Geçici Şifreniz - Restoran Paneli";
            $txt = "Merhaba " . $user['store_name'] . ",\n\n" .
                   "Hesabınız için şifre sıfırlama talebinde bulundunuz.\n\n" .
                   "Geçici Şifreniz: " . $temp_password . "\n\n" .
                   "Güvenliğiniz için bu şifre TEK KULLANIMLIKTIR.\n" .
                   "Giriş yaptıktan sonra sistem sizi otomatik olarak yeni şifre belirleme sayfasına yönlendirecektir.";
            
            $headers = "From: info@qrmasa.net" . "\r\n" . // Buraya kendi domain mailinizi yazın
                       "Reply-To: info@qrmasa.net" . "\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            // Localhost'ta mail gitmez, sunucuda gider.
            if(mail($to, $subject, $txt, $headers)) {
                $message = "Geçici şifreniz e-posta adresinize gönderildi. Lütfen gelen kutunuzu kontrol edin.";
                $msg_type = "success";
            } else {
                // Localhost testi için şifreyi ekrana basıyoruz (Canlıya alınca burayı silin)
                $message = "Şifre oluşturuldu ancak sunucu mail gönderemedi. (Test Amaçlı Geçici Şifre: <b>$temp_password</b>)";
                $msg_type = "warning";
            }
        } else {
            $message = "Veritabanı hatası oluştu.";
            $msg_type = "danger";
        }

    } else {
        $message = "Bu e-posta adresiyle kayıtlı bir restoran bulunamadı.";
        $msg_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifremi Unuttum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card { 
            width: 100%; 
            max-width: 400px; 
            border-radius: 15px; 
            border: none; 
        }
        .card-header { 
            background-color: #343a40; 
            color: white; 
            padding: 20px; 
            text-align: center; 
            border-top-left-radius: 15px !important;
            border-top-right-radius: 15px !important;
        }
    </style>
</head>
<body>

    <div class="card shadow-lg">
        <div class="card-header">
            <h3 class="mb-0"><i class="bi bi-shield-lock-fill"></i> Şifremi Unuttum</h3>
            <small class="text-white-50">Hesabınıza geçici şifre gönderilecek</small>
        </div>
        <div class="card-body p-4">
            
            <?php if($message): ?>
                <div class="alert alert-<?php echo $msg_type; ?> text-center shadow-sm">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary">Kayıtlı E-Posta Adresiniz</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control form-control-lg" required placeholder="ornek@mail.com">
                    </div>
                </div>
                <button type="submit" name="reset_password" class="btn btn-primary w-100 btn-lg fw-bold">
                    <i class="bi bi-send-fill me-2"></i> Şifreyi Sıfırla
                </button>
            </form>
            
            <div class="text-center mt-4 border-top pt-3">
                <a href="login.php" class="text-decoration-none text-secondary fw-bold">
                    <i class="bi bi-arrow-left-circle"></i> Giriş Ekranına Dön
                </a>
            </div>
        </div>
    </div>

</body>
</html>
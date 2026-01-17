<?php
// change_password.php - ZORUNLU ŞİFRE DEĞİŞTİRME EKRANI
session_start();
include 'db.php';

// Güvenlik: Giriş yapmamışsa login'e at
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// Eğer zaten şifresini değiştirmişse (0 ise) panele gönder, burada işi yok
$stmt = $pdo->prepare("SELECT password_reset_required, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['password_reset_required'] == 0) {
    header("Location: " . ($user['role'] == 'admin' ? 'admin.php' : 'panel.php'));
    exit;
}

$msg = "";

if (isset($_POST['update_pass'])) {
    $p1 = $_POST['pass1'];
    $p2 = $_POST['pass2'];

    if ($p1 !== $p2) {
        $msg = "Şifreler uyuşmuyor!";
    } elseif (strlen($p1) < 5) {
        $msg = "Şifre en az 5 karakter olmalı.";
    } else {
        // Yeni şifreyi Hashle
        $new_hash = password_hash($p1, PASSWORD_DEFAULT);
        
        // Şifreyi güncelle VE reset zorunluluğunu (1) kaldır (0 yap)
        $upd = $pdo->prepare("UPDATE users SET password = ?, password_reset_required = 0 WHERE id = ?");
        $upd->execute([$new_hash, $_SESSION['user_id']]);

        // Panele yönlendir
        echo "<script>alert('Şifreniz başarıyla güncellendi!'); window.location.href='" . ($user['role'] == 'admin' ? 'admin.php' : 'panel.php') . "';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Şifre Belirle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background-color: #e9ecef; height: 100vh; display: flex; align-items: center; justify-content: center; }</style>
</head>
<body>
    <div class="card shadow p-4" style="width:100%; max-width:400px;">
        <h4 class="text-center mb-3 text-danger">⚠️ Şifre Yenileme</h4>
        <p class="text-center text-muted small">Güvenliğiniz için geçici şifrenizi değiştirmeniz gerekmektedir.</p>
        
        <?php if($msg): ?><div class="alert alert-danger text-center"><?php echo $msg; ?></div><?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label>Yeni Şifre</label>
                <input type="text" name="pass1" class="form-control" required autofocus placeholder="Yeni şifreniz">
            </div>
            <div class="mb-3">
                <label>Yeni Şifre (Tekrar)</label>
                <input type="text" name="pass2" class="form-control" required placeholder="Tekrar girin">
            </div>
            <button type="submit" name="update_pass" class="btn btn-danger w-100">Şifreyi Güncelle ve Devam Et</button>
        </form>
    </div>
</body>
</html>
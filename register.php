<?php
include 'db.php';
$message = ""; $message_type = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $store_name = trim($_POST['store_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']); // Yeni
    $password = $_POST['password'];

    if(empty($store_name) || empty($username) || empty($password)){
        $message = "Lütfen tüm alanları doldurun."; $message_type = "danger";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        
        if($check->rowCount() > 0){
            $message = "Bu kullanıcı adı zaten alınmış."; $message_type = "danger";
        } else {
            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
            // E-postayı da ekliyoruz
            $stmt = $pdo->prepare("INSERT INTO users (store_name, username, email, password) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$store_name, $username, $email, $hashed_pass])) {
                $user_id = $pdo->lastInsertId();
                $path = "uploads/" . $user_id;
                if (!file_exists($path)) { mkdir($path, 0777, true); }
                $message = "Kayıt başarılı! Yönlendiriliyorsunuz..."; $message_type = "success";
                header("Refresh:2; url=login.php");
            } else {
                $message = "Bir hata oluştu."; $message_type = "danger";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kayıt Ol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #e9ecef; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .register-card { width: 100%; max-width: 450px; border-radius: 15px; overflow: hidden; }
        .card-header-custom { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="card register-card shadow-lg border-0">
        <div class="card-header-custom">
            <h3 class="mb-0">Kayıt Ol</h3>
        </div>
        <div class="card-body p-4">
            <?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div><?php endif; ?>
            
            <form method="post">
                <div class="mb-3"><label>Restoran Adı</label><input type="text" name="store_name" class="form-control" required></div>
                <div class="mb-3"><label>Kullanıcı Adı</label><input type="text" name="username" class="form-control" required></div>
                <div class="mb-3"><label>E-posta Adresi</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3"><label>Şifre</label><input type="password" name="password" class="form-control" required></div>
                <div class="d-grid gap-2 mt-4"><button type="submit" class="btn btn-primary btn-lg">Kayıt Ol</button></div>
            </form>
            <div class="text-center mt-3"><a href="login.php" class="text-muted">Giriş Yap</a></div>
        </div>
    </div>
</body>
</html>
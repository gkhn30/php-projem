<?php
$host = 'localhost';
$dbname = 'qrmasane_qr_menu_db';
$username = 'qrmasane_qr_menu_db'; // Kendi kullanıcı adınız
$password = 'Gokhan+30'; // Kendi şifreniz

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    $pdo = new PDO($dsn, $username, $password);
    
    // Hata modunu aç
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Türkçe karakter ayarları
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET COLLATION_CONNECTION = 'utf8mb4_turkish_ci'");
    
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
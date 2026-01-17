<?php
// fix_id.php - ID ÇAKIŞMASINI DÜZELTME ARACI
session_start();
include 'db.php';

echo "<h1>🛠 VERİTABANI ONARILIYOR...</h1>";

if (!isset($_SESSION['user_id'])) {
    die("<h3 style='color:red'>HATA: Lütfen önce Mutfak Personeli olarak giriş yapın, sonra bu sayfayı yenileyin.</h3>");
}

// Şu anki kullanıcının ID'sini al (Bu ID'yi referans alacağız)
$correct_id = $_SESSION['user_id'];

echo "Referans Alınan Restoran ID: <strong style='color:blue; font-size:20px;'>$correct_id</strong><br><br>";

try {
    // 1. Tüm masaları bu restorana aktar
    $sql1 = "UPDATE restaurant_tables SET user_id = ?";
    $pdo->prepare($sql1)->execute([$correct_id]);
    echo "✅ Masalar güncellendi.<br>";

    // 2. Tüm personelleri bu restorana aktar
    $sql2 = "UPDATE staff SET user_id = ?";
    $pdo->prepare($sql2)->execute([$correct_id]);
    echo "✅ Personeller (Garson/Mutfak) güncellendi.<br>";

    // 3. Tüm ürünleri ve kategorileri bu restorana aktar
    $sql3 = "UPDATE products SET user_id = ?";
    $pdo->prepare($sql3)->execute([$correct_id]);
    
    $sql4 = "UPDATE categories SET user_id = ?";
    $pdo->prepare($sql4)->execute([$correct_id]);
    echo "✅ Menü ve Ürünler güncellendi.<br>";

    // 4. Tüm siparişleri bu restorana aktar
    $sql5 = "UPDATE orders SET user_id = ?";
    $pdo->prepare($sql5)->execute([$correct_id]);
    echo "✅ Siparişler güncellendi.<br>";

    // 5. Mutfak durumlarını resetle (Görünür olması için)
    $sql6 = "UPDATE orders SET kitchen_status = 'pending' WHERE status = 'active'";
    $pdo->query($sql6);
    echo "✅ Mutfak bildirimleri resetlendi.<br>";

    echo "<hr><h2 style='color:green'>🎉 İŞLEM TAMAM!</h2>";
    echo "<a href='kitchen.php' style='font-size:20px; font-weight:bold; background:green; color:white; padding:10px; text-decoration:none;'>MUTFAK PANELİNE GİT >></a>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>HATA: " . $e->getMessage() . "</h3>";
}
?>
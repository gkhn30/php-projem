<?php
session_start();
echo "<h1>Sistem Kimlik Kontrolü</h1>";
echo "Giriş Yapan Rol: " . (isset($_SESSION['role']) ? $_SESSION['role'] : "YOK") . "<br>";
echo "Dükkan ID (User ID): " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "YOK") . "<br>";
echo "--------------------------------<br>";
echo "<b>Personel ID (Staff ID): " . (isset($_SESSION['staff_id']) ? $_SESSION['staff_id'] : "YOK (HATA BURADA!)") . "</b><br>";
echo "Personel Adı: " . (isset($_SESSION['staff_name']) ? $_SESSION['staff_name'] : "YOK") . "<br>";
?>
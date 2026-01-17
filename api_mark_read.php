<?php
// api_mark_read.php - DUYURU OKUNDU İŞARETLEME
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['ann_id'])) {
    echo json_encode(['status' => 'error']);
    exit;
}

$user_id = $_SESSION['user_id'];
$ann_id = $_POST['ann_id'];

try {
    // Daha önce okunup okunmadığını kontrol etmeye gerek yok, INSERT IGNORE veya normal INSERT
    // Unique key (user_id, ann_id) olduğu için hata verirse zaten kayıtlıdır.
    
    $stmt = $pdo->prepare("INSERT INTO announcement_reads (user_id, announcement_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $ann_id]);
    
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    // Zaten okunduysa hata verir, ama işlem başarılı sayılır bizim için
    echo json_encode(['status' => 'success']);
}
?>
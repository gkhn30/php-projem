<?php
// api_cashier.php - KASA VERİLERİNİ ANLIK VEREN API
ob_start();
session_start();
include 'db.php';
ob_end_clean();

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

// Güvenlik
if (!isset($_SESSION['role'])) { echo "[]"; exit; }

// Yetki Kontrolü
$allowed_roles = ['cashier', 'restaurant', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) { echo "[]"; exit; }

// Hedef Kullanıcı (Admin İzleme Desteği)
$target_user_id = $_SESSION['user_id'];
if (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'restaurant') && isset($_GET['target_id'])) {
    $target_user_id = $_GET['target_id'];
}

try {
    // Aktif Siparişleri Çek
    $sql = "SELECT o.id, o.table_id, o.total_amount, t.table_name 
            FROM orders o 
            LEFT JOIN restaurant_tables t ON o.table_id = t.id 
            WHERE o.user_id = ? AND o.status = 'active' 
            ORDER BY o.id ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$target_user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $output = [];
    
    foreach($orders as $order) {
        // Siparişin içindeki ürünleri çek
        $items_stmt = $pdo->prepare("SELECT product_name, quantity, price FROM order_items WHERE order_id = ?");
        $items_stmt->execute([$order['id']]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $output[] = [
            'id' => $order['id'],
            'table_id' => $order['table_id'],
            'table_name' => $order['table_name'] ?? 'Masa #' . $order['id'],
            'total_amount' => number_format($order['total_amount'], 2),
            'items' => $items
        ];
    }
    
    echo json_encode($output, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([]);
}
?>
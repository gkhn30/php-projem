<?php
// api_kitchen.php - BİLDİRİM SİSTEMİ DÜZELTİLDİ
ob_start();
session_start();
include 'db.php';
ob_end_clean();

date_default_timezone_set('Europe/Istanbul');
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // Cache'i öldür

if (!isset($_SESSION['role'])) { echo "[]"; exit; }

// Hangi Dükkan?
$target_user_id = $_SESSION['user_id'];
if (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'restaurant') && isset($_GET['target_id'])) {
    $target_user_id = $_GET['target_id'];
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    // 1. MUTFAK İÇİN: Bekleyen Siparişleri Getir
    if ($action == 'get_pending') {
        $sql = "SELECT o.id, o.created_at, o.updated_at, t.table_name, o.kitchen_status, o.status
                FROM orders o
                LEFT JOIN restaurant_tables t ON o.table_id = t.id
                WHERE o.user_id = ? 
                AND o.status = 'active'
                AND (o.kitchen_status = 'pending' OR o.kitchen_status IS NULL OR o.kitchen_status = '')
                ORDER BY o.updated_at ASC"; // En son güncellenen en sona
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$target_user_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $output = [];
        foreach($orders as $order) {
            $masa = !empty($order['table_name']) ? $order['table_name'] : "Masa #" . $order['id'];
            $db_time = !empty($order['updated_at']) ? $order['updated_at'] : $order['created_at'];
            $saat = $db_time ? date("H:i", strtotime($db_time)) : date("H:i");

            $items = $pdo->prepare("SELECT product_name, quantity FROM order_items WHERE order_id = ?");
            $items->execute([$order['id']]);
            $p_items = $items->fetchAll(PDO::FETCH_ASSOC);
            if (count($p_items) == 0) $p_items[] = ['product_name' => '⚠️ İÇERİK YOK', 'quantity' => 0];

            $output[] = ['id' => $order['id'], 'table_name' => $masa, 'formatted_time' => $saat, 'items' => $p_items];
        }
        echo json_encode($output, JSON_UNESCAPED_UNICODE);
    }
    
    // 2. GARSON İÇİN: Hazır Olan Siparişleri Kontrol Et
    elseif ($action == 'check_ready') {
        // Sadece durumu 'ready' olanları çek
        $sql = "SELECT o.id, t.table_name 
                FROM orders o 
                LEFT JOIN restaurant_tables t ON o.table_id = t.id 
                WHERE o.kitchen_status = 'ready' AND o.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$target_user_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    }

    // 3. İŞLEMLER (Hazırla / Teslim Et)
    elseif ($action == 'set_ready' && isset($_POST['order_id'])) {
        $pdo->prepare("UPDATE orders SET kitchen_status = 'ready' WHERE id = ?")->execute([$_POST['order_id']]);
        echo json_encode(['status'=>'success']);
    }
    elseif ($action == 'set_served' && isset($_POST['order_id'])) {
        $pdo->prepare("UPDATE orders SET kitchen_status = 'served' WHERE id = ?")->execute([$_POST['order_id']]);
        echo json_encode(['status'=>'success']);
    }

} catch (Exception $e) { echo json_encode([]); }
?>
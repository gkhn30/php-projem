<?php
// api_order.php - MUTFAK VE KASA İÇİN GARANTİLİ KAYIT
ob_start();
session_start();
include 'db.php';
ob_end_clean();

date_default_timezone_set('Europe/Istanbul');
$simdi = date("Y-m-d H:i:s");

// Hataları ekrana basma, JSON bozmasın
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
// Cache Önleme
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['role'])) { echo json_encode(['status'=>'error', 'message'=>'Giriş yapın']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];

if (isset($data['table_id']) && isset($data['items'])) {
    $table_id = $data['table_id'];
    $items = $data['items'];
    
    try {
        // Masa açık mı kontrol et
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE table_id = ? AND status = 'active'");
        $stmt->execute([$table_id]);
        $order = $stmt->fetch();
        
        $order_id = 0;
        
        if ($order) {
            $order_id = $order['id'];
            // Sipariş güncellendi, saati ve durumu güncelle
            $pdo->prepare("UPDATE orders SET updated_at = ?, kitchen_status = 'pending' WHERE id = ?")->execute([$simdi, $order_id]);
        } else {
            // Yeni Sipariş
            $waiter_id = isset($_SESSION['staff_id']) ? $_SESSION['staff_id'] : 0;
            
            // DİKKAT: kitchen_status = 'pending' olarak kaydediyoruz
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, table_id, status, created_at, updated_at, kitchen_status, waiter_id) VALUES (?, ?, 'active', ?, ?, 'pending', ?)");
            $stmt->execute([$user_id, $table_id, $simdi, $simdi, $waiter_id]);
            $order_id = $pdo->lastInsertId();
            
            // Masayı dolu yap
            $pdo->prepare("UPDATE restaurant_tables SET status = 1 WHERE id = ?")->execute([$table_id]);
        }
        
        // Ürünleri ekle
        $total_add = 0;
        foreach ($items as $item) {
            $p_id = $item['id'];
            $qty = $item['qty'];
            
            $stmt_p = $pdo->prepare("SELECT name, price FROM products WHERE id = ?");
            $stmt_p->execute([$p_id]);
            $prod_db = $stmt_p->fetch();
            
            if ($prod_db) {
                $price = $prod_db['price'];
                $name = $prod_db['name'];
                
                $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$order_id, $p_id, $name, $qty, $price]);
                
                $total_add += ($price * $qty);
            }
        }
        
        // Toplam tutarı güncelle
        $pdo->prepare("UPDATE orders SET total_amount = total_amount + ? WHERE id = ?")->execute([$total_add, $order_id]);
        
        echo json_encode(['status'=>'success']);
        
    } catch (Exception $e) {
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
    }
} else {
    echo json_encode(['status'=>'error', 'message'=>'Eksik veri']);
}
?>
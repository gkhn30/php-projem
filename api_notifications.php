<?php
// api_notifications.php - DEDEKTİF MODU (SEBEBİ EKRANA YAZAR)
header('Content-Type: application/json');
// Tarayıcı önbelleğini (Cache) tamamen kapat
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
include 'db.php';

$response = ['status' => 'none'];

if (!isset($_SESSION['user_id'])) { echo json_encode($response); exit; }

$user_id = $_SESSION['user_id']; 
$my_role = $_SESSION['role'];    
// Personel ID'sini al (Sayıya çevir ki "19" ile 19 karışmasın)
$my_id = isset($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : 0; 

try {
    // 1. MUTFAK KISMI (AYNI)
    if ($my_role == 'admin' || $my_role == 'kitchen') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'active' AND is_read_kitchen = 0");
        $stmt->execute([$user_id]);
        $n = $stmt->fetchColumn();
        if ($n > 0) {
            echo json_encode(['status'=>'new', 'title'=>'Yeni Sipariş!', 'text'=>$n.' yeni sipariş.', 'msg_id'=>time()]);
            exit;
        }
    }

    // 2. GARSON KISMI
    if ($my_role == 'admin' || $my_role == 'waiter') {
        
        $sql = "SELECT o.id, o.table_id, t.table_name, t.assigned_waiter_id 
                FROM orders o 
                JOIN restaurant_tables t ON o.table_id = t.id 
                WHERE o.user_id = ? AND o.status = 'ready' AND o.notification_sent = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll();

        foreach ($orders as $ord) {
            $should_send = false;
            $debug_reason = ""; // Ekrana yazılacak sebep

            $db_raw = $ord['assigned_waiter_id'];

            // ADMİN KONTROLÜ
            if ($my_role == 'admin') {
                $should_send = true;
                $debug_reason = "[Yönetici olduğun için]";
            } 
            // GARSON KONTROLÜ
            else {
                // A) NULL veya BOŞ ise
                if (is_null($db_raw) || trim($db_raw) === '') {
                    // Test için bunu FALSE yapıyorum. Sahipsiz masalar kimseye gitmesin.
                    $should_send = false; 
                    $debug_reason = "[Masa Sahipsiz]";
                }
                // B) '0' ise
                elseif (trim($db_raw) === '0') {
                    $should_send = true;
                    $debug_reason = "[Masa Herkese Açık]";
                }
                // C) ID Eşleşmesi
                else {
                    $ids = explode(',', $db_raw);
                    // Dizi içindeki her elemanı temizle ve sayıya çevir
                    $clean_ids = [];
                    foreach($ids as $i) { $clean_ids[] = (int)trim($i); }

                    if (in_array($my_id, $clean_ids)) {
                        $should_send = true;
                        $debug_reason = "[Eşleşme: Senin ID($my_id) Listede Var(" . implode(',', $clean_ids) . ")]";
                    } else {
                        // Gönderme
                        $should_send = false;
                        $debug_reason = "[Eşleşmedi: Senin ID($my_id) Listede Yok(" . implode(',', $clean_ids) . ")]";
                    }
                }
            }

            // EĞER GÖNDERİLECEKSE
            if ($should_send) {
                echo json_encode([
                    'status' => 'new',
                    'title'  => 'Sipariş Hazır!',
                    // DEBUG BİLGİSİNİ MESAJIN İÇİNE GÖMÜYORUM:
                    'text'   => '<b>' . $ord['table_name'] . '</b> masası hazır. <br><small style="color:red; font-size:10px;">' . $debug_reason . '</small>',
                    'msg_id' => $ord['id'] . '_ready' // Benzersiz ID
                ]);
                exit; 
            }
        }
    }

} catch (Exception $e) {}

echo json_encode($response);
?>
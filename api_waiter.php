<?php
// api_waiter.php - GÜNCEL (PHP KONTROLLÜ)
ob_start();
session_start();
include 'db.php';
ob_end_clean();

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['role'])) { echo json_encode([]); exit; }

$user_id = $_SESSION['user_id'];
$waiter_id = isset($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : 0; // Sayıya çevir
$my_role = $_SESSION['role'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'get_tables') {
    try {
        // 1. O dükkanın TÜM masalarını çek
        $sql = "SELECT id, table_name, status, assigned_waiter_id FROM restaurant_tables WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $all_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $my_tables = [];

        foreach ($all_tables as $tbl) {
            $show_table = false;

            // Admin veya Restoran Sahibi ise hepsini gör
            if ($my_role == 'admin' || $my_role == 'restaurant') {
                $show_table = true;
            } 
            // Garson ise KONTROL ET
            else {
                $db_data = $tbl['assigned_waiter_id'];

                if (empty($db_data)) {
                    // Veri boşsa (NULL veya ""), masa HERKESE aittir.
                    $show_table = true; 
                } else {
                    // Virgülle ayır, boşlukları temizle, sayıya çevir
                    $ids = explode(',', $db_data);
                    $ids = array_map('intval', array_map('trim', $ids));

                    // A) Listede 0 varsa -> HERKES
                    if (in_array(0, $ids)) {
                        $show_table = true;
                    }
                    // B) Benim ID'm listede varsa -> BENİM
                    elseif (in_array($waiter_id, $ids)) {
                        $show_table = true;
                    }
                }
            }

            // Eğer yetkim varsa listeye ekle
            if ($show_table) {
                // Gereksiz veriyi silip gönderelim
                unset($tbl['assigned_waiter_id']); 
                $my_tables[] = $tbl;
            }
        }
        
        echo json_encode($my_tables);

    } catch (Exception $e) {
        echo json_encode([]);
    }
}
?>
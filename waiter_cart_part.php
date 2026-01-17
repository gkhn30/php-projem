<?php
// waiter_cart_part.php - SON HALİ
// Bu dosya waiter.php içinde include edilir.

if(isset($selected_table_id) && $selected_table_id) {
    // Aktif sipariş ID'sini ve toplamı bul
    $order_q = $pdo->prepare("SELECT id, total_amount FROM orders WHERE table_id = ? AND status = 'active'");
    $order_q->execute([$selected_table_id]);
    $active_order = $order_q->fetch();

    if ($active_order) {
        // Sipariş detaylarını çek
        $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id DESC");
        $items->execute([$active_order['id']]);
        
        echo '<div class="list-group list-group-flush mb-3">';
        foreach($items as $item) {
            echo '
            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-secondary rounded-pill me-1">'.$item['quantity'].'x</span>
                    <span class="small fw-bold">'.htmlspecialchars($item['product_name']).'</span>
                </div>
                <div class="d-flex align-items-center">
                    <span class="small me-2">'.number_format($item['price'] * $item['quantity'], 2).'</span>
                    <a href="waiter.php?table='.$selected_table_id.'&del_item='.$item['id'].'" class="text-danger" onclick="return confirm(\'Bu ürünü silmek istediğinize emin misiniz?\')">
                        <i class="bi bi-x-circle-fill"></i>
                    </a>
                </div>
            </div>';
        }
        echo '</div>';
        
        // Alt toplam alanı
        echo '<div class="mt-auto border-top pt-3 text-center">';
        echo '<h4 class="text-primary fw-bold">Toplam: '.number_format($active_order['total_amount'], 2).' ₺</h4>';
        echo '</div>';
    } else {
        // Sipariş yoksa
        echo '<div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">';
        echo '<i class="bi bi-basket3 display-4 mb-3"></i>';
        echo '<p>Henüz sipariş eklenmemiş.</p>';
        echo '</div>';
    }
} else {
    echo '<p class="text-center mt-4">Lütfen işlem yapmak için bir masa seçin.</p>';
}
?>
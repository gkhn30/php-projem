<?php
session_start();
include 'db.php';

// Güvenlik
if (!isset($_SESSION['role'])) { header("Location: login.php"); exit; }

$waiter_id = isset($_SESSION['staff_id']) ? $_SESSION['staff_id'] : 0;
$user_id = $_SESSION['user_id'];
$my_role = $_SESSION['role']; 
$selected_table_id = isset($_GET['table']) ? $_GET['table'] : null;

// --- AJAX: MASA DURUMLARINI CANLI ÇEKME ---
if (isset($_GET['ajax_tables'])) {
    header('Content-Type: application/json');
    if ($my_role == 'admin' || $my_role == 'restaurant') {
        $sql = "SELECT id, status FROM restaurant_tables WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
    } else {
        $sql = "SELECT id, status FROM restaurant_tables WHERE user_id = ? AND (assigned_waiter_id = ? OR assigned_waiter_id IS NULL)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $waiter_id]);
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- GÜVENLİ SİLME İŞLEMİ ---
if (isset($_GET['del_item']) && $selected_table_id) {
    $del_id = $_GET['del_item'];
    $stmt_check = $pdo->prepare("SELECT oi.order_id, oi.price, oi.quantity, o.kitchen_status FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.id = ? AND o.user_id = ?");
    $stmt_check->execute([$del_id, $user_id]);
    $item = $stmt_check->fetch();

    if ($item) {
        $is_started = ($item['kitchen_status'] == 'ready' || $item['kitchen_status'] == 'served');
        if ($my_role == 'waiter' && $is_started) {
            echo "<script>alert('Mutfak onayı alan ürün silinemez!'); window.location.href = 'waiter.php?table=$selected_table_id';</script>";
            exit;
        }
        $deduct = $item['price'] * $item['quantity'];
        $pdo->prepare("UPDATE orders SET total_amount = total_amount - ? WHERE id = ?")->execute([$deduct, $item['order_id']]);
        $pdo->prepare("DELETE FROM order_items WHERE id = ?")->execute([$del_id]);
    }
    header("Location: waiter.php?table=" . $selected_table_id); exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Garson Ekranı</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; padding-bottom: 120px; }
        
        /* --- PARLAK VE ÇERÇEVESİZ MASA TASARIMI --- */
        .table-box { 
            height: 100px; 
            border-radius: 15px; 
            display: flex; flex-direction: column; justify-content: center; align-items: center; 
            color: white; font-weight: bold; cursor: pointer; transition: all 0.2s; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.15); border: none !important; 
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        .table-box:active { transform: scale(0.95); }
        .table-empty { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); } /* Parlak Yeşil */
        .table-occupied { background: linear-gradient(135deg, #dc3545 0%, #f86b7d 100%); } /* Parlak Kırmızı */

        /* Diğer Stiller */
        .cat-nav { overflow-x: auto; white-space: nowrap; padding: 10px 0; scrollbar-width: none; }
        .cat-nav::-webkit-scrollbar { display: none; }
        .cat-btn { display: inline-block; padding: 8px 20px; margin-right: 8px; border-radius: 20px; background: white; color: #333; border: 1px solid #ddd; font-weight: 500; cursor: pointer; }
        .cat-btn.active { background: #0d6efd; color: white; border-color: #0d6efd; }
        .product-card { background: white; border-radius: 12px; padding: 10px; height: 100%; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between; border: 1px solid #eee; transition: all 0.2s; }
        .product-card.selected { border: 2px solid #0d6efd; background-color: #f0f7ff; }
        .btn-qty { width: 35px; height: 35px; border-radius: 50%; border: none; font-weight: bold; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .cart-bar { position: fixed; bottom: 0; left: 0; width: 100%; background: white; border-top: 1px solid #ccc; padding: 15px; box-shadow: 0 -4px 10px rgba(0,0,0,0.1); z-index: 1050; display: none; justify-content: space-between; align-items: center; }
        
        /* SAAT STİLİ */
        #liveClock { font-family: 'Courier New', monospace; font-size: 1.2rem; letter-spacing: 1px; text-shadow: 0 0 5px rgba(255,255,255,0.3); }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid">
        <div class="d-flex align-items-center">
            <?php if($selected_table_id): ?>
                <a href="waiter.php" class="btn btn-outline-light btn-sm me-2"><i class="bi bi-arrow-left"></i> Geri</a>
                <span class="navbar-brand mb-0 h1 fs-6">Masa <?php 
                    $t = $pdo->prepare("SELECT table_name FROM restaurant_tables WHERE id=?"); 
                    $t->execute([$selected_table_id]); 
                    echo htmlspecialchars($t->fetchColumn()); 
                ?></span>
            <?php else: ?>
                <span class="navbar-brand mb-0 h1"><i class="bi bi-person-badge"></i> Garson</span>
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div id="liveClock" class="text-white fw-bold d-none d-sm-block me-2">--:--</div>
            
            <button id="soundBtn" class="btn btn-sm btn-success fw-bold" onclick="toggleSound()">
                <i class="bi bi-volume-up-fill" id="soundIcon"></i>
            </button>

            <a href="logout.php" class="btn btn-sm btn-danger"><i class="bi bi-power"></i></a>
        </div>
    </div>
</nav>

<div class="container-fluid py-3">
    
    <?php if(!$selected_table_id): ?>
        <div class="row g-3">
            <?php
            if ($my_role == 'admin' || $my_role == 'restaurant') {
                $tables = $pdo->prepare("SELECT * FROM restaurant_tables WHERE user_id = ? ORDER BY table_name ASC");
                $tables->execute([$user_id]);
            } else {
                $tables = $pdo->prepare("SELECT * FROM restaurant_tables WHERE user_id = ? AND (assigned_waiter_id = ? OR assigned_waiter_id IS NULL) ORDER BY table_name ASC");
                $tables->execute([$user_id, $waiter_id]);
            }

            while($tbl = $tables->fetch()):
                $statusClass = ($tbl['status'] == 1) ? 'table-occupied' : 'table-empty';
                $statusText = ($tbl['status'] == 1) ? 'DOLU' : 'BOŞ';
            ?>
            <div class="col-4 col-md-3 col-lg-2">
                <a href="waiter.php?table=<?php echo $tbl['id']; ?>" class="text-decoration-none">
                    <div id="table-box-<?php echo $tbl['id']; ?>" class="table-box <?php echo $statusClass; ?>">
                        <i class="bi bi-ui-checks-grid fs-3 mb-1"></i>
                        <span><?php echo htmlspecialchars($tbl['table_name']); ?></span>
                        <small id="status-text-<?php echo $tbl['id']; ?>" style="font-size: 0.7rem; opacity: 0.9;">
                            <?php echo $statusText; ?>
                        </small>
                    </div>
                </a>
            </div>
            <?php endwhile; ?>
        </div>

    <?php else: ?>
        <?php 
        $current_total = 0; $active_order_id = 0;
        $order_q = $pdo->prepare("SELECT id, total_amount FROM orders WHERE table_id = ? AND status = 'active'");
        $order_q->execute([$selected_table_id]);
        $active_order = $order_q->fetch();
        if($active_order) { $current_total = $active_order['total_amount']; $active_order_id = $active_order['id']; }
        ?>
        <div class="alert alert-secondary d-flex justify-content-between align-items-center py-2 px-3 mb-3">
            <span>Tutar: <strong><?php echo number_format($current_total, 2); ?> ₺</strong></span>
            <?php if($active_order_id): ?>
                <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#orderDetailModal">Detay / Sil</button>
            <?php endif; ?>
        </div>

        <div class="cat-nav mb-3">
            <button class="cat-btn active" onclick="filterCategory('all', this)">Tümü</button>
            <?php
            $cats = $pdo->prepare("SELECT * FROM categories WHERE user_id = ?");
            $cats->execute([$user_id]);
            foreach($cats->fetchAll() as $c) { echo '<button class="cat-btn" onclick="filterCategory('.$c['id'].', this)">'.htmlspecialchars($c['name']).'</button>'; }
            ?>
        </div>

        <div class="row g-3 pb-5">
            <?php
            $products = $pdo->prepare("SELECT * FROM products WHERE user_id = ? AND is_active = 1");
            $products->execute([$user_id]);
            while($p = $products->fetch()):
            ?>
            <div class="col-6 col-sm-4 col-lg-3 product-item" data-cat="<?php echo $p['category_id']; ?>">
                <div class="product-card" id="card-<?php echo $p['id']; ?>">
                    <div class="text-center" onclick="changeQty(<?php echo $p['id']; ?>, 1, '<?php echo $p['name']; ?>', <?php echo $p['price']; ?>)">
                        <div style="font-size:0.9rem; font-weight:700; margin-bottom:5px;"><?php echo htmlspecialchars($p['name']); ?></div>
                        <div style="color:#dc3545; font-weight:bold;"><?php echo number_format($p['price'], 2); ?> ₺</div>
                    </div>
                    <div class="d-flex justify-content-center gap-2 mt-2" id="qty-control-<?php echo $p['id']; ?>" style="visibility: hidden;">
                        <button class="btn-qty bg-light text-dark" onclick="changeQty(<?php echo $p['id']; ?>, -1, '<?php echo $p['name']; ?>', <?php echo $p['price']; ?>)">-</button>
                        <span style="font-weight:bold; font-size:1.2rem; min-width:25px; text-align:center;" id="qty-<?php echo $p['id']; ?>">0</span>
                        <button class="btn-qty bg-primary text-white" onclick="changeQty(<?php echo $p['id']; ?>, 1, '<?php echo $p['name']; ?>', <?php echo $p['price']; ?>)">+</button>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <div class="cart-bar" id="cartBar">
            <div style="font-weight:bold; font-size:1.1rem;"><span id="cart-count">0</span> Ürün | <span id="cart-total">0.00</span> ₺</div>
            <button class="btn btn-success fw-bold px-4" onclick="submitOrder()">ONAYLA <i class="bi bi-check-circle"></i></button>
        </div>

        <div class="modal fade" id="orderDetailModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Sipariş Detayı</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php include 'waiter_cart_part.php'; ?></div></div></div></div>
    <?php endif; ?>
</div>

<audio id="waiterSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3"></audio>

<div class="modal fade" id="readyModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-success text-white"><div class="modal-header border-0"><h5 class="modal-title w-100 text-center fw-bold">🔔 MUTFAKTAN BİLDİRİM</h5></div><div class="modal-body text-center"><h2 id="readyTableName" class="display-4 fw-bold">Masa X</h2><p class="fs-5">Sipariş Hazır!</p></div><div class="modal-footer justify-content-center border-0"><button type="button" class="btn btn-light btn-lg fw-bold w-100" onclick="confirmServed()">TESLİM ALDIM</button></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let cart = {}; const tableId = <?php echo $selected_table_id ? $selected_table_id : 'null'; ?>;
    
    // --- SAAT ---
    function updateClock() {
        const now = new Date();
        document.getElementById('liveClock').innerText = now.toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'});
    }
    setInterval(updateClock, 1000); updateClock();

    // --- SES KONTROLÜ (MUTFAK STİLİ: YEŞİL / KIRMIZI BUTON) ---
    let soundEnabled = localStorage.getItem('soundEnabled') !== 'false';
    const soundIcon = document.getElementById('soundIcon');
    const soundBtn = document.getElementById('soundBtn');
    const audioEl = document.getElementById('waiterSound');

    function updateSoundIcon() {
        if(soundEnabled) {
            // Açık: Yeşil Buton, Sesli İkon
            soundBtn.className = 'btn btn-sm btn-success fw-bold';
            soundIcon.className = 'bi bi-volume-up-fill';
        } else {
            // Kapalı: Kırmızı Buton, Sessiz İkon
            soundBtn.className = 'btn btn-sm btn-danger fw-bold';
            soundIcon.className = 'bi bi-volume-mute-fill';
        }
    }
    function toggleSound() {
        soundEnabled = !soundEnabled;
        localStorage.setItem('soundEnabled', soundEnabled);
        updateSoundIcon();
    }
    updateSoundIcon();

    // --- OTOMATİK MASA GÜNCELLEME ---
    <?php if(!$selected_table_id): ?>
    function updateTableStatus() {
        fetch('waiter.php?ajax_tables=1')
        .then(response => response.json())
        .then(data => {
            data.forEach(table => {
                let box = document.getElementById('table-box-' + table.id);
                let txt = document.getElementById('status-text-' + table.id);
                if (box) {
                    if (table.status == 1) {
                        box.className = 'table-box table-occupied';
                        if(txt) txt.innerText = 'DOLU';
                    } else {
                        box.className = 'table-box table-empty';
                        if(txt) txt.innerText = 'BOŞ';
                    }
                }
            });
        }).catch(err => {});
    }
    setInterval(updateTableStatus, 3000);
    <?php endif; ?>

    // --- SİPARİŞ FONKSİYONLARI ---
    function filterCategory(catId, btn) {
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active');
        document.querySelectorAll('.product-item').forEach(p => { p.style.display = (catId === 'all' || p.getAttribute('data-cat') == catId) ? 'block' : 'none'; });
    }
    function changeQty(pId, change, name, price) {
        if (!cart[pId]) cart[pId] = { qty: 0, price: price };
        cart[pId].qty += change; if (cart[pId].qty < 0) cart[pId].qty = 0;
        document.getElementById('qty-' + pId).innerText = cart[pId].qty;
        const cDiv = document.getElementById('qty-control-' + pId), cCard = document.getElementById('card-' + pId);
        if (cart[pId].qty > 0) { cDiv.style.visibility = 'visible'; cCard.classList.add('selected'); } else { cDiv.style.visibility = 'hidden'; cCard.classList.remove('selected'); delete cart[pId]; }
        updateCartBar();
    }
    function updateCartBar() {
        let count = 0, total = 0;
        for (let id in cart) { count += cart[id].qty; total += cart[id].qty * cart[id].price; }
        const bar = document.getElementById('cartBar');
        if (count > 0) { bar.style.display = 'flex'; document.getElementById('cart-count').innerText = count; document.getElementById('cart-total').innerText = total.toFixed(2); } else { bar.style.display = 'none'; }
    }
    function submitOrder() {
        if (!tableId) return;
        let items = []; for (let id in cart) { items.push({ id: id, qty: cart[id].qty }); }
        if (items.length === 0) return;
        const btn = document.querySelector('#cartBar button'); btn.disabled = true; btn.innerText = 'Kaydediliyor...';
        fetch('api_order.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ table_id: tableId, items: items }) })
        .then(r => r.json()).then(d => { if (d.status === 'success') location.reload(); else { alert(d.message); btn.disabled = false; btn.innerText = 'ONAYLA'; } });
    }
    
    // --- MUTFAK BİLDİRİM ---
    let readyOrderId = null; const readyModal = new bootstrap.Modal(document.getElementById('readyModal'));
    function checkKitchenStatus() {
        fetch('api_kitchen.php?action=check_ready').then(r => r.json()).then(data => {
            if (data.length > 0) { 
                const order = data[0]; 
                readyOrderId = order.id; 
                document.getElementById('readyTableName').innerText = order.table_name; 
                if(soundEnabled) { audioEl.play().catch(e=>{}); }
                readyModal.show(); 
            }
        });
    }
    function confirmServed() {
        if (!readyOrderId) return;
        let formData = new FormData(); formData.append('order_id', readyOrderId);
        fetch('api_kitchen.php?action=set_served', { method: 'POST', body: formData }).then(r => r.json()).then(d => { readyModal.hide(); setTimeout(checkKitchenStatus, 1000); });
    }
    setInterval(checkKitchenStatus, 5000);
</script>
</body>
</html>
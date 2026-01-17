<?php
// cashier.php - CACHE ÖNLEME EKLİ
session_start();
include 'db.php';

if (!isset($_SESSION['role'])) { header("Location: login.php"); exit; }

$allowed_roles = ['cashier', 'restaurant', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) { die("Yetkisiz Erişim."); }

$user_id = $_SESSION['user_id'];
$link_suffix = "";
$js_api_suffix = "";

if (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'restaurant') && isset($_GET['target_id'])) {
    $user_id = $_GET['target_id'];
    $link_suffix = "&target_id=" . $_GET['target_id'];
    $js_api_suffix = "?target_id=" . $_GET['target_id'];
}

// ÖDEME İŞLEMİ
if (isset($_GET['pay_order'])) {
    $o_id = $_GET['pay_order'];
    $t_id = $_GET['tbl'];
    $method = $_GET['method']; 
    
    $pdo->prepare("UPDATE orders SET status = 'completed', payment_method = ?, updated_at = NOW() WHERE id = ?")->execute([$method, $o_id]);
    $pdo->prepare("UPDATE restaurant_tables SET status = 0 WHERE id = ?")->execute([$t_id]);
    
    header("Location: cashier.php?v=1" . $link_suffix); exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kasa Paneli</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .order-card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.2s; height: 100%; }
        .order-card:hover { transform: translateY(-5px); }
        .card-header { font-weight: bold; font-size: 1.1rem; }
        .btn-pay { width: 48%; }
        .masonry-container { display: flex; flex-wrap: wrap; margin-right: -15px; margin-left: -15px; }
        .masonry-item { padding: 15px; width: 100%; }
        @media (min-width: 768px) { .masonry-item { width: 50%; } }
        @media (min-width: 1200px) { .masonry-item { width: 33.33%; } }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-primary mb-4 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1"><i class="bi bi-wallet2"></i> Kasa Paneli</span>
        <div class="d-flex align-items-center">
            <span id="clock" class="text-white fw-bold me-3">00:00</span>
            <?php if(isset($_GET['target_id'])): ?>
                <a href="panel.php?target_id=<?php echo $_GET['target_id']; ?>" class="btn btn-sm btn-light text-primary fw-bold">Panele Dön</a>
            <?php else: ?>
                <a href="logout.php" class="btn btn-sm btn-light text-primary fw-bold">Çıkış</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container">
    <div id="orders-container" class="masonry-container">
        <div class="col-12 text-center text-muted mt-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">Siparişler Bekleniyor...</p>
        </div>
    </div>
</div>

<audio id="alertSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3"></audio>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const apiSuffix = "<?php echo $js_api_suffix; ?>";
    const linkSuffix = "<?php echo $link_suffix; ?>";
    let knownOrders = []; 

    function updateClock() { document.getElementById('clock').innerText = new Date().toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'}); }
    setInterval(updateClock, 1000); updateClock();

    function fetchOrders() {
        // CACHE ÖNLEME İÇİN TIMESTAMPS EKLENDİ
        let url = 'api_cashier.php' + (apiSuffix ? apiSuffix : '?v=1');
        url += '&t=' + Date.now();
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('orders-container');
                let html = '';
                
                let currentIds = data.map(o => o.id);
                let isNew = currentIds.some(id => !knownOrders.includes(id));
                if (isNew && knownOrders.length > 0) { document.getElementById('alertSound').play().catch(e=>{}); }
                knownOrders = currentIds;

                if (data.length === 0) {
                    container.innerHTML = '<div class="col-12 text-center text-muted mt-5" style="width:100%;"><h4>Ödeme Bekleyen Masa Yok</h4><i class="bi bi-check-circle display-1 text-success"></i></div>';
                    return;
                }

                data.forEach(order => {
                    let itemsHtml = '';
                    order.items.forEach(item => {
                        let totalItemPrice = (parseFloat(item.price) * parseFloat(item.quantity)).toFixed(2);
                        itemsHtml += `<li class="list-group-item d-flex justify-content-between px-0"><span>${item.quantity}x ${item.product_name}</span><span class="fw-bold">${totalItemPrice}</span></li>`;
                    });

                    let urlNakit = `cashier.php?pay_order=${order.id}&tbl=${order.table_id}&method=nakit${linkSuffix}`;
                    let urlKart = `cashier.php?pay_order=${order.id}&tbl=${order.table_id}&method=kredi_karti${linkSuffix}`;

                    html += `
                    <div class="masonry-item">
                        <div class="card order-card">
                            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between">
                                <span>${order.table_name}</span>
                                <span class="text-primary">#${order.id}</span>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <ul class="list-group list-group-flush mb-3 flex-grow-1">${itemsHtml}</ul>
                                <h3 class="text-center fw-bold text-dark mb-4">${order.total_amount} ₺</h3>
                                <div class="d-flex justify-content-between mt-auto">
                                    <a href="${urlNakit}" class="btn btn-success btn-pay" onclick="return confirm('Nakit ödeme alındı mı?')"><i class="bi bi-cash-coin"></i> Nakit</a>
                                    <a href="${urlKart}" class="btn btn-primary btn-pay" onclick="return confirm('Kart ödemesi alındı mı?')"><i class="bi bi-credit-card"></i> Kart</a>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });
                container.innerHTML = html;
            }).catch(err => console.error(err));
    }

    setInterval(fetchOrders, 5000);
    fetchOrders(); 
</script>

</body>
</html>
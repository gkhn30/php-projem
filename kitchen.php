<?php
// kitchen.php - SES SORUNU ÇÖZÜLDÜ (BAŞLAT BUTONLU)
session_start();
include 'db.php';

if (!isset($_SESSION['role'])) { header("Location: login.php"); exit; }

$allowed_roles = ['kitchen', 'restaurant', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) { die("Bu sayfaya erişim yetkiniz yok."); }

$view_user_id = $_SESSION['user_id']; 
if (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'restaurant') && isset($_GET['target_id'])) {
    $view_user_id = $_GET['target_id'];
}

// Ses dosyasını çek
$user_data = $pdo->prepare("SELECT notification_sound FROM users WHERE id = ?");
$user_data->execute([$view_user_id]);
$sound_file = $user_data->fetchColumn();
if (!$sound_file) $sound_file = 'sound1'; 

// Ses Linkleri
$sounds = [
    'sound1' => 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3',
    'sound2' => 'https://assets.mixkit.co/active_storage/sfx/1003/1003-preview.mp3',
    'sound3' => 'https://assets.mixkit.co/active_storage/sfx/236/236-preview.mp3',
    'sound4' => 'https://assets.mixkit.co/active_storage/sfx/995/995-preview.mp3'
];
$active_sound_url = isset($sounds[$sound_file]) ? $sounds[$sound_file] : $sounds['sound1'];

$api_params = "?target_id=" . $view_user_id; 
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Mutfak Ekranı</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #212529; color: white; }
        .order-card { background-color: #343a40; border: 2px solid #495057; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); animation: slideIn 0.5s ease-out; }
        .order-card.new-order { border-color: #ffc107; box-shadow: 0 0 20px rgba(255, 193, 7, 0.4); }
        .card-header { background-color: #ffc107; color: #212529; font-weight: bold; font-size: 1.2rem; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0; }
        .order-time { font-size: 0.9rem; background: #212529; color: #ffc107; padding: 2px 8px; border-radius: 5px; }
        .product-list { list-style: none; padding: 0; margin: 0; }
        .product-item { padding: 10px; border-bottom: 1px solid #495057; font-size: 1.1rem; display: flex; justify-content: space-between; }
        .product-item:last-child { border-bottom: none; }
        .qty-badge { background-color: #dc3545; color: white; padding: 2px 10px; border-radius: 50px; font-weight: bold; }
        .btn-ready { width: 100%; border-radius: 0 0 8px 8px; font-weight: bold; padding: 15px; font-size: 1.2rem; }
        .masonry-container { column-count: 1; column-gap: 20px; }
        @media (min-width: 768px) { .masonry-container { column-count: 2; } }
        @media (min-width: 1200px) { .masonry-container { column-count: 3; } }
        .card-break { display: inline-block; width: 100%; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Başlatma Ekranı Stili */
        #startOverlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
    </style>
</head>
<body>

<div id="startOverlay">
    <div class="mb-4"><i class="bi bi-fire text-warning" style="font-size: 5rem;"></i></div>
    <h2 class="text-white mb-4">Mutfak Paneli Hazır</h2>
    <button class="btn btn-warning btn-lg fw-bold px-5 py-3" onclick="startSystem()">
        <i class="bi bi-play-circle-fill"></i> SİSTEMİ BAŞLAT
    </button>
    <small class="text-white-50 mt-3">Sesli bildirimlerin çalışması için butona basınız.</small>
</div>

<nav class="navbar navbar-dark bg-dark border-bottom border-secondary mb-4">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1"><i class="bi bi-fire text-warning"></i> Mutfak Ekranı</span>
        
        <div class="d-flex align-items-center">
            <div class="form-check form-switch text-white me-3">
                <input class="form-check-input" type="checkbox" id="soundToggle" checked onchange="toggleSound()">
                <label class="form-check-label" for="soundToggle"><i class="bi bi-volume-up"></i> Ses</label>
            </div>
            <span id="clock" class="fw-bold fs-4 text-white">00:00</span>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div id="orders-container" class="masonry-container">
        </div>
</div>

<audio id="alertSound" src="<?php echo $active_sound_url; ?>"></audio>

<script>
    const apiSuffix = "<?php echo $api_params; ?>";
    let knownOrders = []; 
    let isSoundOn = true;
    let isFirstLoad = true; // Sayfa ilk açıldığında ses çalmasın diye kontrol
    let loopInterval = null;

    function updateClock() { document.getElementById('clock').innerText = new Date().toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'}); }
    setInterval(updateClock, 1000); updateClock();

    function toggleSound() {
        isSoundOn = document.getElementById('soundToggle').checked;
    }

    // SİSTEMİ BAŞLAT VE SES MOTORUNU AÇ
    function startSystem() {
        // 1. Kullanıcı etkileşimi ile sesi aç
        const audio = document.getElementById('alertSound');
        audio.volume = 0.1; 
        audio.play().then(() => {
            audio.pause();
            audio.currentTime = 0;
            audio.volume = 1; // Sesi normale döndür
        }).catch(e => console.log("Ses hatası: " + e));

        // 2. Arayüzü temizle
        document.getElementById('startOverlay').style.display = 'none';

        // 3. Veri çekmeyi başlat
        document.getElementById('orders-container').innerHTML = '<div class="text-center text-muted mt-5"><div class="spinner-border text-warning" role="status"></div><br>Siparişler Yükleniyor...</div>';
        fetchOrders();
        loopInterval = setInterval(fetchOrders, 5000);
    }

    function fetchOrders() {
        fetch('api_kitchen.php?action=get_pending' + "&" + apiSuffix.replace('?', '') + '&t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('orders-container');
                let html = '';
                
                let currentIds = data.map(o => o.id);
                
                // --- SES MANTIĞI (DÜZELTİLDİ) ---
                if (!isFirstLoad) {
                    // Listedeki ID'lerden herhangi biri, bildiğimiz listede yoksa -> YENİ SİPARİŞ VARDIR
                    let hasNew = currentIds.some(id => !knownOrders.includes(id));
                    
                    if (hasNew && isSoundOn) { 
                        document.getElementById('alertSound').play().catch(e=>{ console.log("Oynatma engellendi"); });
                    }
                }
                
                knownOrders = currentIds;
                isFirstLoad = false; // Artık ilk yükleme bitti, sonraki her yeni ID ses çalar.

                if (data.length === 0) { 
                    container.innerHTML = '<div class="text-center text-secondary mt-5 card-break w-100"><h4>Aktif Sipariş Yok</h4><p>Şu an bekleyen sipariş bulunmuyor.</p></div>'; 
                    return; 
                }

                data.forEach(order => {
                    let itemsHtml = '';
                    order.items.forEach(item => { itemsHtml += `<li class="product-item"><span>${item.product_name}</span> <span class="qty-badge">x${item.quantity}</span></li>`; });
                    
                    // Yeni eklenen kartlara animasyon sınıfı ekle (Opsiyonel)
                    html += `
                    <div class="card-break">
                        <div class="order-card">
                            <div class="card-header">
                                <span>${order.table_name}</span>
                                <span class="order-time"><i class="bi bi-clock"></i> ${order.formatted_time}</span>
                            </div>
                            <ul class="product-list">${itemsHtml}</ul>
                            <button onclick="setReady(${order.id})" class="btn btn-success btn-ready">HAZIR <i class="bi bi-check-lg"></i></button>
                        </div>
                    </div>`;
                });
                container.innerHTML = html;
            }).catch(err => console.error(err));
    }

    function setReady(orderId) {
        if(!confirm('Sipariş hazırlandı mı?')) return;
        let formData = new FormData(); formData.append('order_id', orderId);
        fetch('api_kitchen.php?action=set_ready', { method: 'POST', body: formData }).then(r => r.json()).then(d => { fetchOrders(); });
    }
</script>
</body>
</html>
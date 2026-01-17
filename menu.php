<?php
// menu.php - SCROLL SPY (OTOMATİK KATEGORİ SEÇİMİ) GERİ GELDİ
session_start();
include 'db.php';

$store_code = isset($_GET['store']) ? $_GET['store'] : (isset($_GET['id']) ? $_GET['id'] : '');
if (!$store_code) { die("Restoran bulunamadı."); }

if (is_numeric($store_code)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE slug = ?");
}

$stmt->execute([$store_code]);
$store = $stmt->fetch();

if (!$store) { die("Böyle bir restoran yok."); }
$rest_id = $store['id'];

// Renk Varsayılanları
$bg_color = $store['color_bg'] ?? '#f8f9fa';
$header_color = $store['color_header'] ?? '#ffffff';
$text_color = $store['color_text'] ?? '#333333';
$btn_color = $store['color_btn'] ?? '#ff5e62';
$card_color = $store['color_card'] ?? '#ffffff';

// Slider Resimleri
$slider_query = $pdo->prepare("SELECT * FROM slider_images WHERE user_id = ? ORDER BY id DESC");
$slider_query->execute([$rest_id]);
$sliders = $slider_query->fetchAll();

// DUYURU KONTROLÜ
$show_announcement = false;
if (isset($store['ann_active']) && $store['ann_active'] == 1) {
    $now = date("Y-m-d H:i:s");
    $start = $store['ann_start'];
    $end = $store['ann_end'];
    
    if ((!empty($store['ann_title']) || !empty($store['ann_message'])) && 
        ($start == NULL || $now >= $start) && 
        ($end == NULL || $now <= $end)) {
        $show_announcement = true;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($store['store_name']); ?> Menü</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --bg-color: <?php echo $bg_color; ?>;
            --header-bg: <?php echo $header_color; ?>;
            --text-color: <?php echo $text_color; ?>;
            --btn-color: <?php echo $btn_color; ?>;
            --card-bg: <?php echo $card_color; ?>;
        }

        html { scroll-behavior: smooth; } /* Tıklayınca yumuşak kayma */
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-color); color: var(--text-color); padding-bottom: 80px; }
        
        .hero-header { position: relative; background: var(--header-bg); padding: 20px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
        .hero-bg { background: var(--btn-color); height: 120px; position: absolute; top: 0; left: 0; width: 100%; z-index: 0; opacity: 0.8; }
        .store-logo { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 4px solid var(--header-bg); position: relative; z-index: 1; background: white; margin-top: 40px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .store-title { font-weight: 700; color: var(--text-color); margin-bottom: 5px; position: relative; z-index: 1; }
        
        .btn-action { border-radius: 20px; padding: 6px 20px; font-size: 0.9rem; margin: 5px; color: var(--text-color); border: 1px solid var(--text-color); background: transparent; text-decoration: none; display: inline-block; transition: all 0.3s; cursor: pointer; }
        .btn-action:hover { background: var(--btn-color); color: white; border-color: var(--btn-color); }
        
        .social-links { margin-top: 15px; position: relative; z-index: 1; }
        .social-links a { font-size: 1.5rem; margin: 0 10px; color: var(--text-color); transition: color 0.3s; text-decoration: none; }
        .social-links a:hover { color: var(--btn-color); }

        .slider-section { background: var(--card-bg); padding-bottom: 20px; margin-bottom: 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .slider-title { text-align: center; font-weight: 700; color: var(--text-color); margin: 15px 0 10px 0; text-transform: uppercase; letter-spacing: 1px; font-size: 1.1rem; }
        .carousel-item img { width: 100%; height: 250px; object-fit: cover; }

        .category-nav-container { position: sticky; top: 0; z-index: 1000; background: var(--bg-color); backdrop-filter: blur(5px); padding: 10px 0; width: 100%; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .cat-link { flex: 0 0 auto; display: inline-block; padding: 8px 18px; border-radius: 30px; color: var(--text-color); text-decoration: none; font-size: 0.9rem; font-weight: 500; background: var(--card-bg); box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.1); transition: all 0.2s ease; margin-right: 10px; }
        .cat-link.active { background-color: var(--btn-color); color: white; border-color: var(--btn-color); transform: scale(1.05); }
        .category-nav { display: flex; overflow-x: auto; padding: 5px 15px; scrollbar-width: none; scroll-behavior: smooth; }
        .category-nav::-webkit-scrollbar { display: none; }

        .menu-card { border: none; border-radius: 15px; overflow: hidden; background: var(--card-bg); box-shadow: 0 2px 10px rgba(0,0,0,0.05); height: 100%; cursor: pointer; transition: transform 0.2s; }
        .menu-card:active { transform: scale(0.98); }
        .product-img { width: 100%; height: 180px; object-fit: cover; }
        .card-body { padding: 15px; }
        .price-tag { font-size: 1.1rem; font-weight: 700; color: var(--btn-color); }
        .short-desc { font-size: 0.8rem; color: var(--text-color); opacity: 0.8; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 2.4em; margin-bottom: 10px; }
        .modal-price-text { font-size: 1.8rem; font-weight: 700; color: var(--btn-color); }
        
        /* Scroll Spy için önemli ayar */
        .category-section { scroll-margin-top: 80px; color: var(--text-color); border-left-color: var(--btn-color) !important; } 
    </style>
</head>
<body>

    <div class="hero-header">
        <div class="hero-bg"></div>
        <?php if(!empty($store['store_image'])): ?>
            <img src="<?php echo htmlspecialchars($store['store_image']); ?>" class="store-logo" alt="Logo">
        <?php else: ?>
            <div class="store-logo d-flex align-items-center justify-content-center mx-auto" style="font-size: 3rem; color: var(--btn-color);"><i class="bi bi-shop"></i></div>
        <?php endif; ?>

        <div class="store-info">
            <h2 class="store-title"><?php echo htmlspecialchars($store['store_name']); ?></h2>
            
            <?php if($store['show_social'] == 1): ?>
                <div class="d-flex justify-content-center flex-wrap mb-2">
                    <?php if(!empty($store['phone'])): ?> 
                        <a href="tel:<?php echo htmlspecialchars($store['phone']); ?>" class="btn-action"><i class="bi bi-telephone-fill"></i> Ara</a> 
                    <?php endif; ?>
                    <?php if(!empty($store['google_map'])): ?> 
                        <a href="<?php echo htmlspecialchars($store['google_map']); ?>" target="_blank" class="btn-action"><i class="bi bi-geo-alt-fill"></i> Yol Tarifi</a> 
                    <?php endif; ?>
                    <?php if(!empty($store['wifi_name']) || !empty($store['wifi_pass'])): ?>
                        <button class="btn-action" data-bs-toggle="modal" data-bs-target="#wifiModal"><i class="bi bi-wifi"></i> Wi-Fi</button>
                    <?php endif; ?>
                </div>

                <div class="social-links text-center">
                    <?php if(!empty($store['instagram'])): ?> <a href="<?php echo htmlspecialchars($store['instagram']); ?>" target="_blank"><i class="bi bi-instagram"></i></a> <?php endif; ?>
                    <?php if(!empty($store['twitter'])): ?> <a href="<?php echo htmlspecialchars($store['twitter']); ?>" target="_blank"><i class="bi bi-twitter-x"></i></a> <?php endif; ?>
                    <?php if(!empty($store['facebook'])): ?> <a href="<?php echo htmlspecialchars($store['facebook']); ?>" target="_blank"><i class="bi bi-facebook"></i></a> <?php endif; ?>
                    <?php if(!empty($store['youtube'])): ?> <a href="<?php echo htmlspecialchars($store['youtube']); ?>" target="_blank"><i class="bi bi-youtube"></i></a> <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if(($store['show_slider'] ?? 1) == 1 && count($sliders) > 0): ?>
    <div class="slider-section">
        <?php if(!empty($store['slider_title'])): ?><div class="slider-title"><?php echo htmlspecialchars($store['slider_title']); ?></div><?php endif; ?>
        <div id="promoCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php $active = true; foreach($sliders as $slide): ?>
                <div class="carousel-item <?php echo $active ? 'active' : ''; ?>"><img src="<?php echo htmlspecialchars($slide['image_path']); ?>" class="d-block w-100"></div>
                <?php $active = false; endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="category-nav-container">
        <div class="container p-0">
            <div class="category-nav" id="catNav">
                <?php
                $cats_query = $pdo->prepare("SELECT * FROM categories WHERE user_id = ?");
                $cats_query->execute([$rest_id]);
                $categories = $cats_query->fetchAll();
                foreach($categories as $cat) { 
                    echo '<a href="#cat-'.$cat['id'].'" class="cat-link" id="link-cat-'.$cat['id'].'">'.htmlspecialchars($cat['name']).'</a>'; 
                }
                ?>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <?php foreach($categories as $category):
            $products = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND is_active = 1");
            $products->execute([$category['id']]);
            if($products->rowCount() == 0) continue;
        ?>
        <div id="cat-<?php echo $category['id']; ?>" class="category-section h4 mt-4 mb-3 border-start border-4 ps-2"><?php echo htmlspecialchars($category['name']); ?></div>
        <div class="row g-3">
            <?php while($prod = $products->fetch()): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="menu-card" onclick="openProductModal(this)" 
                     data-title="<?php echo htmlspecialchars($prod['name']); ?>"
                     data-desc="<?php echo htmlspecialchars($prod['description']); ?>"
                     data-price="<?php echo number_format($prod['price'], 2); ?>"
                     data-image="<?php echo !empty($prod['image_path']) ? htmlspecialchars($prod['image_path']) : 'https://via.placeholder.com/600x400?text=Lezzet'; ?>">
                    
                    <?php if(!empty($prod['image_path'])): ?><img src="<?php echo htmlspecialchars($prod['image_path']); ?>" class="product-img"><?php else: ?><img src="https://via.placeholder.com/300x200?text=Lezzet" class="product-img"><?php endif; ?>
                    <div class="card-body">
                        <h5 class="h6 fw-bold mb-1" style="color:var(--text-color)"><?php echo htmlspecialchars($prod['name']); ?></h5>
                        <p class="short-desc"><?php echo htmlspecialchars($prod['description']); ?></p>
                        <div class="d-flex justify-content-between align-items-center"><span class="price-tag"><?php echo number_format($prod['price'], 2); ?> ₺</span></div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="text-center text-muted mt-5 mb-3 small"><i class="bi bi-qr-code"></i> Dijital Menü Sistemi</div>

    <div class="modal fade" id="productDetailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content custom-modal-content shadow-lg" style="background-color: var(--card-bg); color: var(--text-color);"><div class="position-relative"><button type="button" class="btn-close position-absolute top-0 end-0 m-3 bg-white p-2 rounded-circle opacity-100 shadow-sm" data-bs-dismiss="modal" style="z-index: 10;"></button><div class="modal-img-container"><img src="" id="modalImg" style="width:100%; height:250px; object-fit:cover;"></div></div><div class="modal-body text-center"><h3 id="modalTitle" class="fw-bold mb-2"></h3><p id="modalDesc" class="mb-3 opacity-75"></p><div id="modalPrice" class="modal-price-text"></div></div><div class="modal-footer justify-content-center border-0 pb-4"><button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Kapat</button></div></div></div></div>

    <div class="modal fade" id="wifiModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background-color: var(--card-bg); color: var(--text-color);">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-wifi"></i> Wi-Fi Bilgileri</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3"><small class="d-block text-muted">Ağ Adı</small><h4 class="fw-bold"><?php echo htmlspecialchars($store['wifi_name']); ?></h4></div>
                    <?php if(!empty($store['wifi_pass'])): ?>
                    <div class="mb-3">
                        <small class="d-block text-muted">Şifre</small>
                        <div class="input-group justify-content-center">
                            <input type="text" class="form-control text-center fw-bold" value="<?php echo htmlspecialchars($store['wifi_pass']); ?>" id="wifiPassInput" readonly style="max-width: 200px;">
                            <button class="btn btn-outline-secondary" onclick="copyWifiPass()"><i class="bi bi-clipboard"></i></button>
                        </div>
                        <small id="copyMsg" class="text-success d-none mt-2">Şifre Kopyalandı! <i class="bi bi-check"></i></small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if($show_announcement): ?>
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-center p-4" style="background: linear-gradient(135deg, var(--btn-color), var(--header-bg)); color: white;">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                <div class="mb-3"><i class="bi bi-megaphone-fill display-1"></i></div>
                <h3 class="fw-bold mb-3"><?php echo htmlspecialchars($store['ann_title']); ?></h3>
                <p class="lead mb-4"><?php echo nl2br(htmlspecialchars($store['ann_message'])); ?></p>
                <button type="button" class="btn btn-light rounded-pill px-5 fw-bold" data-bs-dismiss="modal">Tamam</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const productModal = new bootstrap.Modal(document.getElementById('productDetailModal'));
        
        <?php if($show_announcement): ?>
        window.onload = function() { new bootstrap.Modal(document.getElementById('announcementModal')).show(); };
        <?php endif; ?>

        function openProductModal(card) {
            document.getElementById('modalTitle').innerText = card.getAttribute('data-title');
            document.getElementById('modalDesc').innerText = card.getAttribute('data-desc');
            document.getElementById('modalPrice').innerText = card.getAttribute('data-price') + " ₺";
            document.getElementById('modalImg').src = card.getAttribute('data-image');
            productModal.show();
        }

        function copyWifiPass() {
            var copyText = document.getElementById("wifiPassInput");
            copyText.select(); copyText.setSelectionRange(0, 99999); 
            navigator.clipboard.writeText(copyText.value);
            document.getElementById("copyMsg").classList.remove("d-none");
            setTimeout(() => { document.getElementById("copyMsg").classList.add("d-none"); }, 2000);
        }

        // --- SCROLL SPY (YENİLENEN KISIM) ---
        // Sayfa kaydırıldığında kategoriyi algıla ve menüyü güncelle
        const sections = document.querySelectorAll('.category-section');
        const navLinks = document.querySelectorAll('.cat-link');
        const navContainer = document.getElementById('catNav');

        window.addEventListener('scroll', () => {
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                // 150px yukarıdan pay bırakıyoruz (header için)
                if (scrollY >= (sectionTop - 150)) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href').includes(current)) {
                    link.classList.add('active');
                    
                    // Aktif olan menü öğesini ekranda ortala (Mobilde kaydırma)
                    link.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest',
                        inline: 'center'
                    });
                }
            });
        });
    </script>
</body>
</html>
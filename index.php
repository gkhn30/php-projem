<?php
// index.php - GÜNCEL (Görsel Yok, Register Bağlı)
require_once 'db.php';

// Veritabanından Ayarları Çek
$seo = $pdo->query("SELECT * FROM system_settings WHERE id = 1")->fetch();

// Varsayılanlar
if(!$seo) { 
    $seo = [
        'site_title' => 'QRMasa - Restoran Otomasyonu', 
        'site_desc' => 'Kafe ve restoranlar için yeni nesil QR menü ve sipariş yönetim sistemi.', 
        'site_keywords' => 'qr menü, restoran otomasyon, kafe programı, adisyon', 
        'site_author' => 'QRMasa',
        'demo_url' => '#' 
    ]; 
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo htmlspecialchars($seo['site_title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo['site_desc']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seo['site_keywords']); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($seo['site_author']); ?>">
    <meta name="robots" content="index, follow">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #333; overflow-x: hidden; }
        
        /* Hero Alanı */
        .hero-section {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 140px 0 100px 0;
            position: relative;
            clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);
        }
        .hero-title { font-weight: 800; letter-spacing: -1px; margin-bottom: 20px; line-height: 1.2; }
        .hero-text { font-size: 1.2rem; opacity: 0.9; margin-bottom: 40px; font-weight: 300; color: #cbd5e1; }
        
        /* Butonlar */
        .btn-custom-primary { background-color: #f43f5e; border: none; padding: 15px 40px; font-weight: 700; border-radius: 50px; transition: transform 0.3s; color: white; box-shadow: 0 10px 25px rgba(244, 63, 94, 0.4); text-decoration: none; }
        .btn-custom-primary:hover { background-color: #e11d48; transform: translateY(-3px); color: white; }
        
        .btn-custom-outline { border: 2px solid rgba(255,255,255,0.3); padding: 15px 40px; font-weight: 600; border-radius: 50px; color: white; transition: all 0.3s; text-decoration: none; background: rgba(255,255,255,0.05); }
        .btn-custom-outline:hover { background-color: white; color: #0f172a; border-color: white; }

        /* Özellik Kartları */
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            height: 100%;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
        }
        .feature-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.08); border-color: #f43f5e; }
        
        .icon-box {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 25px;
        }
        
        /* Adım Kartları */
        .step-card {
            text-align: center;
            padding: 30px 20px;
            position: relative;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.03);
            height: 100%;
        }
        .step-number {
            width: 50px;
            height: 50px;
            background: #f43f5e;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            box-shadow: 0 5px 15px rgba(244, 63, 94, 0.3);
        }

        /* Renk Temaları */
        .bg-icon-red { background: #ffe4e6; color: #f43f5e; }
        .bg-icon-blue { background: #dbeafe; color: #3b82f6; }
        .bg-icon-green { background: #dcfce7; color: #22c55e; }
        .bg-icon-purple { background: #f3e8ff; color: #a855f7; }
        .bg-icon-orange { background: #ffedd5; color: #f97316; }
        .bg-icon-cyan { background: #cffafe; color: #06b6d4; }

        /* Footer */
        .footer { background-color: #0f172a; color: white; padding: 60px 0 30px 0; }
        .footer a { color: rgba(255,255,255,0.6); text-decoration: none; transition: 0.3s; }
        .footer a:hover { color: white; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top py-3" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px);">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4" href="#">
                <i class="bi bi-qr-code-scan me-2 text-danger"></i> <?php echo htmlspecialchars($seo['site_author']); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-3">
                    <li class="nav-item"><a class="nav-link" href="#ozellikler">Özellikler</a></li>
                    <li class="nav-item"><a class="nav-link" href="#nasil">Nasıl Çalışır?</a></li>
                    <li class="nav-item">
                        <a href="login.php" class="btn btn-outline-light rounded-pill px-4 btn-sm">Giriş Yap</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero-section d-flex align-items-center">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <span class="badge bg-danger bg-opacity-25 text-danger border border-danger mb-3 px-3 py-2 rounded-pill">🚀 Restoranlar İçin Dijital Çözüm</span>
                    <h1 class="hero-title display-3"><?php echo htmlspecialchars($seo['site_title']); ?></h1>
                    <p class="hero-text">
                        İşletmeniz için modern, hızlı ve temassız sipariş sistemi. 
                        Kasa, Mutfak ve Garson panelleri ile tam kontrol sağlayın.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?php echo htmlspecialchars($seo['demo_url']); ?>" target="_blank" class="btn btn-custom-outline">
                            <i class="bi bi-play-circle-fill me-2"></i> Canlı Demo
                        </a>
                        
                        <a href="register.php" class="btn btn-custom-primary">
                            <i class="bi bi-magic me-2"></i> Menünü Oluştur
                        </a>
                    </div>
                    <div class="mt-4 text-white-50 small">
                        <i class="bi bi-check-circle-fill text-success me-1"></i> Ücretsiz Kurulum 
                        <i class="bi bi-check-circle-fill text-success ms-3 me-1"></i> 7/24 Teknik Destek
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block text-center position-relative">
                    <div style="position: absolute; top: -20px; right: 50px; width: 100px; height: 100px; background: #f43f5e; border-radius: 50%; filter: blur(50px); opacity: 0.5;"></div>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?php echo urlencode($seo['demo_url']); ?>" 
                         class="img-fluid rounded-4 shadow-lg border border-4 border-white position-relative" 
                         style="max-width: 350px; transform: rotate(3deg) translateY(20px);">
                </div>
            </div>
        </div>
    </header>

    <section id="ozellikler" class="py-5">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h6 class="text-danger fw-bold text-uppercase">Neler Sunuyoruz?</h6>
                <h2 class="fw-bold display-6">İşletmenizi Uçuran Özellikler</h2>
                <p class="text-muted w-75 mx-auto">Sadece bir menü değil, restoranınızı yöneten tam kapsamlı bir otomasyon sistemi.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-icon-blue"><i class="bi bi-wallet2"></i></div>
                        <h4>Gelişmiş Kasa Paneli</h4>
                        <p class="text-muted">Tüm siparişleri tek ekrandan yönetin. Masa durumlarını (dolu/boş) anlık görün, ödemeleri kolayca alın.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-icon-red"><i class="bi bi-fire"></i></div>
                        <h4>Anlık Mutfak Ekranı</h4>
                        <p class="text-muted">Kağıt fiş karmaşasına son! Siparişler anında mutfak ekranına düşer, hazırlanan ürünler garsona bildirilir.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-icon-green"><i class="bi bi-person-badge"></i></div>
                        <h4>Garson Paneli</h4>
                        <p class="text-muted">Garsonlarınız cep telefonlarından masaları yönetebilir, sipariş alabilir ve hazır bildirimlerini anında görebilir.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-icon-purple"><i class="bi bi-graph-up-arrow"></i></div>
                        <h4>Detaylı Ciro Raporu</h4>
                        <p class="text-muted">Günlük, haftalık ve aylık kazancınızı grafiklerle takip edin. Hangi ürünün ne kadar sattığını analiz edin.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-icon-orange"><i class="bi bi-images"></i></div>
                        <h4>Görsel Menü & Slider</h4>
                        <p class="text-muted">Kampanyalarınızı slider ile duyurun. Menüdeki ürünleri dilediğiniz zaman güncelleyin, fotoğraf ekleyin.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="icon-box bg-icon-cyan"><i class="bi bi-palette"></i></div>
                        <h4>Tam Özelleştirme</h4>
                        <p class="text-muted">Sistem renklerini, duyuruları ve Wi-Fi bilgilerini admin panelinden işletmenize özel olarak düzenleyin.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="nasil" class="bg-light py-5 border-top">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold display-6">Sistem Nasıl İşliyor?</h2>
                <p class="text-muted">Müşterileriniz için en kolay, sizin için en verimli deneyim.</p>
            </div>

            <div class="row g-4 justify-content-center">
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4 class="fw-bold">QR Kodu Okut</h4>
                        <p class="text-muted">Müşteri masadaki QR kodunu kamerasıyla okutur. Uygulama indirmeye gerek kalmadan menü anında açılır.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4 class="fw-bold">Siparişini Ver</h4>
                        <p class="text-muted">Görsel menüden ürünleri seçer ve siparişi onaylar. Sipariş anında Kasa ve Mutfak ekranına sesli bildirimle düşer.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4 class="fw-bold">Hazırla & Servis Et</h4>
                        <p class="text-muted">Mutfak hazırlar, tek tuşla garsonu çağırır. Garson servis yapar, müşteri memnuniyeti artar.</p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5">
                <a href="register.php" class="btn btn-dark rounded-pill px-5 py-3 fw-bold shadow">
                    <i class="bi bi-person-plus-fill me-2"></i> Hemen Ücretsiz Kayıt Ol
                </a>
            </div>
        </div>
    </section>

    <footer class="footer text-center">
        <div class="container">
            <h3 class="fw-bold mb-3"><?php echo htmlspecialchars($seo['site_author']); ?></h3>
            <p class="mb-4 text-white-50">Restoranlar için en akıllı çözüm ortağı.</p>
            <div class="d-flex justify-content-center gap-3 mb-4">
                <a href="#" class="fs-4 text-white-50 hover-white"><i class="bi bi-instagram"></i></a>
                <a href="#" class="fs-4 text-white-50 hover-white"><i class="bi bi-twitter-x"></i></a>
                <a href="#" class="fs-4 text-white-50 hover-white"><i class="bi bi-facebook"></i></a>
            </div>
            <hr class="border-secondary opacity-25 w-50 mx-auto">
            <p class="mb-0 small opacity-50 mt-3">&copy; <?php echo date("Y"); ?> Tüm hakları saklıdır.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
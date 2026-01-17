<?php
// panel.php - AYARLARDA DOMAIN ADRESİ DÜZELTİLDİ
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
session_start();
include 'db.php';

// Güvenlik
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] == 'admin' && !isset($_GET['target_id'])) { header("Location: admin.php"); exit; }

$aktif_user_id = $_SESSION['user_id'];
$aktif_store_name = isset($_SESSION['store_name']) ? $_SESSION['store_name'] : 'Panel';
$is_admin_mode = false;

// Admin Görüntüleme Modu
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin' && isset($_GET['target_id'])) {
    $aktif_user_id = $_GET['target_id'];
    $is_admin_mode = true;
    $stmt = $pdo->prepare("SELECT store_name FROM users WHERE id = ?");
    $stmt->execute([$aktif_user_id]);
    $t_user = $stmt->fetch();
    if($t_user) { $aktif_store_name = $t_user['store_name']; }
}

$link_suffix = $is_admin_mode ? "&target_id=$aktif_user_id" : "";

// Kullanıcı Bilgileri
$user_info = $pdo->prepare("SELECT * FROM users WHERE id = ?"); 
$user_info->execute([$aktif_user_id]); 
$user_data = $user_info->fetch();

if (empty($user_data['slug'])) {
    $safe_slug = preg_replace('/[^a-z0-9]/', '', strtolower($user_data['username']));
    $pdo->prepare("UPDATE users SET slug = ? WHERE id = ?")->execute([$safe_slug, $aktif_user_id]);
    $user_data['slug'] = $safe_slug;
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
$base_url = str_replace("/panel.php", "", $base_url); 
$menu_link = rtrim($base_url, "/") . "/" . $user_data['slug'];

// --- POP-UP İÇİN SADECE OKUNMAMIŞ DUYURULARI ÇEK ---
$popup_announcements = [];
if (!$is_admin_mode) { 
    $now = date("Y-m-d H:i:s");
    $ann_sql = "SELECT a.* FROM admin_announcements a 
                WHERE a.is_active = 1 
                AND (a.start_date IS NULL OR a.start_date <= ?)
                AND (a.end_date IS NULL OR a.end_date >= ?)
                AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id = ?)
                ORDER BY a.id ASC"; 
    $ann_stmt = $pdo->prepare($ann_sql);
    $ann_stmt->execute([$now, $now, $aktif_user_id]);
    $popup_announcements = $ann_stmt->fetchAll();
}

// SES AYARLARI
$sound_options = [ 'sound1'=>'Ding Dong', 'sound2'=>'Kısa Zil', 'sound3'=>'Ksilofon', 'sound4'=>'Hızlı Uyarı' ];
$my_user_info = $pdo->prepare("SELECT notification_sound FROM users WHERE id = ?");
$my_user_info->execute([$_SESSION['user_id']]);
$my_sound_pref = $my_user_info->fetchColumn();
if(!$my_sound_pref) $my_sound_pref = 'sound1';
$sound_urls = [ 'sound1'=>'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', 'sound2'=>'https://assets.mixkit.co/active_storage/sfx/1003/1003-preview.mp3', 'sound3'=>'https://assets.mixkit.co/active_storage/sfx/236/236-preview.mp3', 'sound4'=>'https://assets.mixkit.co/active_storage/sfx/995/995-preview.mp3' ];
$active_sound_url = $sound_urls[$my_sound_pref];

// --- İŞLEMLER ---
if (isset($_POST['update_settings'])) {
    $new_slug = strtolower($_POST['slug']); $new_slug = preg_replace('/[^a-z0-9\-]/', '', str_replace(['ı','ğ','ü','ş','ö','ç',' '], ['i','g','u','s','o','c','-'], $new_slug)); $check = $pdo->prepare("SELECT id FROM users WHERE slug = ? AND id != ?"); $check->execute([$new_slug, $aktif_user_id]); if ($check->rowCount() > 0) { echo "<script>alert('Bu link dolu!');</script>"; } else { $params = [$_POST['phone'], $_POST['instagram'], $_POST['twitter'], $_POST['facebook'], $_POST['youtube'], $_POST['google_map'], isset($_POST['show_social'])?1:0, $new_slug, $_POST['color_bg'], $_POST['color_header'], $_POST['color_text'], $_POST['color_btn'], $_POST['color_card'], $_POST['wifi_name'], $_POST['wifi_pass'], $_POST['notification_sound'], $_POST['ann_title'], $_POST['ann_message'], !empty($_POST['ann_start'])?$_POST['ann_start']:NULL, !empty($_POST['ann_end'])?$_POST['ann_end']:NULL, isset($_POST['ann_active'])?1:0]; $store_img_sql = ""; if (!empty($_FILES["store_image"]["name"])) { $target_file = "uploads/" . $aktif_user_id . "/logo_" . time() . "_" . basename($_FILES["store_image"]["name"]); if (move_uploaded_file($_FILES["store_image"]["tmp_name"], $target_file)) { $store_img_sql = ", store_image = ?"; $params[] = $target_file; } } $params[] = $aktif_user_id; $pdo->prepare("UPDATE users SET phone=?, instagram=?, twitter=?, facebook=?, youtube=?, google_map=?, show_social=?, slug=?, color_bg=?, color_header=?, color_text=?, color_btn=?, color_card=?, wifi_name=?, wifi_pass=?, notification_sound=?, ann_title=?, ann_message=?, ann_start=?, ann_end=?, ann_active=? $store_img_sql WHERE id=?")->execute($params); header("Location: panel.php?tab=settings".$link_suffix); exit; } 
}
// Diğer standart işlemler...
if (isset($_POST['add_table'])) { $waiters = isset($_POST['waiter_ids']) ? $_POST['waiter_ids'] : []; $waiter_string = (in_array('0', $waiters) || empty($waiters)) ? NULL : implode(',', $waiters); $pdo->prepare("INSERT INTO restaurant_tables (user_id, table_name, assigned_waiter_id) VALUES (?, ?, ?)")->execute([$aktif_user_id, $_POST['table_name'], $waiter_string]); header("Location: panel.php?tab=tables".$link_suffix); exit; }
if (isset($_POST['edit_table'])) { $e_id = $_POST['edit_table_id']; $e_name = $_POST['edit_table_name']; $waiters = isset($_POST['edit_waiter_ids']) ? $_POST['edit_waiter_ids'] : []; $e_waiter_string = (in_array('0', $waiters) || empty($waiters)) ? NULL : implode(',', $waiters); $pdo->prepare("UPDATE restaurant_tables SET table_name = ?, assigned_waiter_id = ? WHERE id = ? AND user_id = ?")->execute([$e_name, $e_waiter_string, $e_id, $aktif_user_id]); header("Location: panel.php?tab=tables" . $link_suffix); exit; }
if (isset($_POST['add_staff'])) { $short_user = preg_replace('/[^a-z0-9]/', '', strtolower($_POST['s_user'])); $final_username = $user_data['slug'] . "_" . $short_user; $check = $pdo->prepare("SELECT id FROM staff WHERE username = ?"); $check->execute([$final_username]); if($check->rowCount() > 0) { echo "<script>alert('Bu kullanıcı adı kullanımda!');</script>"; } else { $pdo->prepare("INSERT INTO staff (user_id, name, username, password, role) VALUES (?, ?, ?, ?, ?)")->execute([$aktif_user_id, $_POST['s_name'], $final_username, $_POST['s_pass'], $_POST['s_role']]); header("Location: panel.php?tab=staff".$link_suffix); exit; } }
if (isset($_GET['del_staff'])) { $pdo->prepare("DELETE FROM staff WHERE id=? AND user_id=?")->execute([$_GET['del_staff'], $aktif_user_id]); header("Location: panel.php?tab=staff".$link_suffix); exit; }
if (isset($_GET['del_order_item'])) { $d_id = $_GET['del_order_item']; $stmt = $pdo->prepare("SELECT order_id, price, quantity FROM order_items WHERE id = ?"); $stmt->execute([$d_id]); $item = $stmt->fetch(); if ($item) { $deduct = $item['price'] * $item['quantity']; $pdo->prepare("UPDATE orders SET total_amount = total_amount - ? WHERE id = ?")->execute([$deduct, $item['order_id']]); $pdo->prepare("DELETE FROM order_items WHERE id = ?")->execute([$d_id]); } header("Location: panel.php?tab=orders" . $link_suffix); exit; }
if (isset($_GET['cancel_order_id'])) { $c_id = $_GET['cancel_order_id']; $tbl_id = $_GET['tbl_id']; $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$c_id]); $pdo->prepare("UPDATE restaurant_tables SET status = 0 WHERE id = ?")->execute([$tbl_id]); header("Location: panel.php?tab=orders" . $link_suffix); exit; }
if (isset($_GET['del_table'])) { $pdo->prepare("DELETE FROM restaurant_tables WHERE id=? AND user_id=?")->execute([$_GET['del_table'], $aktif_user_id]); header("Location: panel.php?tab=tables".$link_suffix); exit; }
if (isset($_POST['add_category'])) { $pdo->prepare("INSERT INTO categories (user_id, name) VALUES (?, ?)")->execute([$aktif_user_id, $_POST['cat_name']]); header("Location: panel.php?tab=categories".$link_suffix); exit; }
if (isset($_GET['sil_kategori'])) { $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?")->execute([$_GET['sil_kategori'], $aktif_user_id]); header("Location: panel.php?tab=categories".$link_suffix); exit; }
if (isset($_POST['add_product'])) { $target_dir = "uploads/" . $aktif_user_id . "/"; if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); } $target_file = $target_dir . time() . "_" . basename($_FILES["p_image"]["name"]); if (move_uploaded_file($_FILES["p_image"]["tmp_name"], $target_file)) { $stmt = $pdo->prepare("INSERT INTO products (user_id, category_id, name, description, price, image_path) VALUES (?, ?, ?, ?, ?, ?)"); $stmt->execute([$aktif_user_id, $_POST['category_id'], $_POST['p_name'], $_POST['p_desc'], $_POST['p_price'], $target_file]); } header("Location: panel.php".$link_suffix); exit; }
if (isset($_GET['sil_urun'])) { $s = $pdo->prepare("SELECT image_path FROM products WHERE id = ? AND user_id = ?"); $s->execute([$_GET['sil_urun'], $aktif_user_id]); $img = $s->fetchColumn(); if($img && file_exists($img)) unlink($img); $pdo->prepare("DELETE FROM products WHERE id = ? AND user_id = ?")->execute([$_GET['sil_urun'], $aktif_user_id]); header("Location: panel.php".$link_suffix); exit; }
if (isset($_GET['durum_id'])) { $yeni = ($_GET['durum'] == 1) ? 0 : 1; $pdo->prepare("UPDATE products SET is_active = ? WHERE id = ? AND user_id = ?")->execute([$yeni, $_GET['durum_id'], $aktif_user_id]); header("Location: panel.php".$link_suffix); exit; }
if (isset($_POST['update_product'])) { $p_id = $_POST['edit_p_id']; $image_sql = ""; $params = [$_POST['edit_category_id'], $_POST['edit_p_name'], $_POST['edit_p_desc'], $_POST['edit_p_price']]; if (!empty($_FILES["edit_p_image"]["name"])) { $s = $pdo->prepare("SELECT image_path FROM products WHERE id = ? AND user_id = ?"); $s->execute([$p_id, $aktif_user_id]); $img = $s->fetchColumn(); if($img && file_exists($img)) unlink($img); $target_file = "uploads/" . $aktif_user_id . "/" . time() . "_upd_" . basename($_FILES["edit_p_image"]["name"]); if (move_uploaded_file($_FILES["edit_p_image"]["tmp_name"], $target_file)) { $image_sql = ", image_path = ?"; $params[] = $target_file; } } $params[] = $p_id; $params[] = $aktif_user_id; $pdo->prepare("UPDATE products SET category_id=?, name=?, description=?, price=? $image_sql WHERE id=? AND user_id=?")->execute($params); header("Location: panel.php".$link_suffix); exit; }
if (isset($_POST['upload_slider'])) { $show = isset($_POST['show_slider']) ? 1 : 0; $pdo->prepare("UPDATE users SET slider_title = ?, show_slider = ? WHERE id = ?")->execute([$_POST['slider_title'], $show, $aktif_user_id]); if (!empty($_FILES["slider_image"]["name"])) { $target_dir = "uploads/" . $aktif_user_id . "/slider/"; if (!file_exists($target_dir)) mkdir($target_dir, 0777, true); $target_file = $target_dir . time() . "_slide_" . basename($_FILES["slider_image"]["name"]); if (move_uploaded_file($_FILES["slider_image"]["tmp_name"], $target_file)) { $pdo->prepare("INSERT INTO slider_images (user_id, image_path) VALUES (?, ?)")->execute([$aktif_user_id, $target_file]); } } header("Location: panel.php?tab=slider".$link_suffix); exit; }
if (isset($_GET['sil_slider'])) { $s = $pdo->prepare("SELECT image_path FROM slider_images WHERE id = ?"); $s->execute([$_GET['sil_slider']]); $img = $s->fetchColumn(); if($img && file_exists($img)) unlink($img); $pdo->prepare("DELETE FROM slider_images WHERE id = ?")->execute([$_GET['sil_slider']]); header("Location: panel.php?tab=slider".$link_suffix); exit; }

$cats = $pdo->prepare("SELECT * FROM categories WHERE user_id = ?"); $cats->execute([$aktif_user_id]); $categories_data = $cats->fetchAll();
$waiters = $pdo->prepare("SELECT * FROM staff WHERE user_id = ? AND role = 'waiter'"); $waiters->execute([$aktif_user_id]); $waiters_list = $waiters->fetchAll();
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'orders';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        .product-thumb{object-fit:cover;border-radius:5px;}
        .store-logo-preview{width:100px;height:100px;object-fit:cover;border-radius:50%;border:2px solid #ddd;}
        .slider-thumb{height:150px;object-fit:cover;}
        .op-card { transition: transform 0.2s; cursor: pointer; border: none; }
        .op-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .color-input-group { display: flex; align-items: center; border: 1px solid #ddd; padding: 5px; border-radius: 5px; background: white; }
        .color-input-group input[type="color"] { border: none; width: 40px; height: 40px; cursor: pointer; background: none; }
        .color-input-group label { flex-grow: 1; margin-left: 10px; font-weight: 500; cursor: pointer; }
        
        .toast-container { z-index: 1060 !important; }
        .custom-toast { border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.3); border: none; overflow: hidden; cursor: pointer; background-color: white; }
        .custom-toast .toast-header { border-bottom: none; color: white; background-color: #dc3545; }
        .custom-toast-body { font-size: 0.95rem; padding: 15px; color: #333; }
        @media print { body * { visibility: hidden; } #printableReport, #printableReport * { visibility: visible; } #printableReport { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; } .no-print { display: none !important; } .card { border: none !important; box-shadow: none !important; } .table { font-size: 12px; } .badge { border: 1px solid #000; color: #000 !important; background: none !important; } }
    </style>
</head>
<body class="bg-light pb-5" onclick="initSound()"> 

    <?php if($is_admin_mode): ?><div class="bg-warning text-dark text-center py-2 fw-bold d-flex justify-content-center align-items-center"><span><i class="bi bi-exclamation-triangle-fill"></i> Admin: <u><?php echo htmlspecialchars($aktif_store_name); ?></u></span><a href="admin.php" class="btn btn-sm btn-dark ms-3">Panele Dön</a></div><?php endif; ?>
    <nav class="navbar navbar-dark bg-dark mb-4 no-print"><div class="container"><span class="navbar-brand">Panel - <?php echo htmlspecialchars($aktif_store_name); ?></span><div class="d-flex align-items-center"><?php if(!$is_admin_mode): ?><a href="support.php" class="btn btn-outline-warning btn-sm me-2 position-relative">Destek <span id="msgBadge" class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none"><span class="visually-hidden">Yeni Mesaj</span></span></a><?php else: ?><a href="admin.php" class="btn btn-danger btn-sm me-2">Admin</a><?php endif; ?><a href="<?php echo $menu_link; ?>" target="_blank" class="btn btn-outline-info btn-sm me-2">Menü</a><a href="logout.php" class="btn btn-outline-secondary btn-sm">Çıkış</a></div></div></nav>
    <div class="container">
        
        <div class="row mb-4 no-print">
            <div class="col-md-4"><a href="waiter.php?v=1<?php echo $link_suffix; ?>" target="_blank" class="card op-card text-decoration-none text-white bg-primary h-100"><div class="card-body text-center d-flex flex-column align-items-center justify-content-center"><i class="bi bi-person-badge-fill display-4 mb-2"></i><h4 class="fw-bold">Garson Paneli</h4><small>Masaları Yönet</small></div></a></div>
            <div class="col-md-4"><a href="kitchen.php?v=1<?php echo $link_suffix; ?>" target="_blank" class="card op-card text-decoration-none text-dark bg-warning h-100"><div class="card-body text-center d-flex flex-column align-items-center justify-content-center"><i class="bi bi-fire display-4 mb-2"></i><h4 class="fw-bold">Mutfak Paneli</h4><small>Siparişleri Hazırla</small></div></a></div>
            <div class="col-md-4"><a href="cashier.php?v=1<?php echo $link_suffix; ?>" target="_blank" class="card op-card text-decoration-none text-white bg-success h-100"><div class="card-body text-center d-flex flex-column align-items-center justify-content-center"><i class="bi bi-wallet2 display-4 mb-2"></i><h4 class="fw-bold">Kasa Paneli</h4><small>Ödemeleri Al</small></div></a></div>
        </div>

        <ul class="nav nav-tabs mb-4 no-print">
            <li class="nav-item"><a class="nav-link <?php echo $active_tab=='orders'?'active text-danger fw-bold':''; ?>" href="?tab=orders<?php echo $link_suffix; ?>"><i class="bi bi-receipt"></i> Sipariş & Rapor</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $active_tab=='menu'?'active':''; ?>" href="?tab=menu<?php echo $link_suffix; ?>">Menü</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $active_tab=='staff'?'active':''; ?>" href="?tab=staff<?php echo $link_suffix; ?>">Personel</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $active_tab=='tables'?'active':''; ?>" href="?tab=tables<?php echo $link_suffix; ?>">Masalar</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $active_tab=='slider'?'active':''; ?>" href="?tab=slider<?php echo $link_suffix; ?>">Slider</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $active_tab=='settings'?'active':''; ?>" href="?tab=settings<?php echo $link_suffix; ?>">Ayarlar</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $active_tab=='admin_announcements'?'active text-warning fw-bold':''; ?>" href="?tab=admin_announcements<?php echo $link_suffix; ?>"><i class="bi bi-megaphone"></i> Admin Duyuruları</a></li>
            
            <li class="nav-item"><a class="nav-link <?php echo $active_tab=='categories'?'active':''; ?>" href="?tab=categories<?php echo $link_suffix; ?>">Kategoriler</a></li>
        </ul>

        <?php if($active_tab == 'orders'): ?>
            <div class="card shadow-sm border-danger mb-5 no-print"><div class="card-header bg-danger text-white d-flex justify-content-between align-items-center"><h5 class="mb-0"><i class="bi bi-activity"></i> Şu An Açık Olan Masalar</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Masa</th><th>Durum</th><th>Toplam</th><th class="text-end">İşlemler</th></tr></thead><tbody><?php $stmt = $pdo->prepare("SELECT o.*, t.table_name FROM orders o LEFT JOIN restaurant_tables t ON o.table_id = t.id WHERE o.user_id = ? AND o.status = 'active' ORDER BY o.id DESC"); $stmt->execute([$aktif_user_id]); while($ord = $stmt->fetch()): ?><tr><td><?php echo htmlspecialchars($ord['table_name'] ?? 'Masa #'.$ord['id']); ?></td><td><span class="badge bg-success">Açık</span></td><td class="fw-bold text-danger"><?php echo number_format($ord['total_amount'], 2); ?> ₺</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#activeOrderModal<?php echo $ord['id']; ?>">Detay / Düzenle</button><div class="modal fade" id="activeOrderModal<?php echo $ord['id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-light"><h5 class="modal-title">Sipariş Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="list-group mb-3"><?php $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?"); $items->execute([$ord['id']]); while($it = $items->fetch()): ?><div class="list-group-item d-flex justify-content-between align-items-center"><div><?php echo $it['quantity']; ?>x <?php echo htmlspecialchars($it['product_name']); ?></div><div><span class="fw-bold me-2"><?php echo number_format($it['price']*$it['quantity'], 2); ?> ₺</span><a href="panel.php?tab=orders&del_order_item=<?php echo $it['id'].$link_suffix; ?>" onclick="return confirm('Silinsin mi?')" class="btn btn-sm btn-danger py-0 px-2"><i class="bi bi-trash"></i></a></div></div><?php endwhile; ?></div></div><div class="modal-footer justify-content-between"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button><a href="panel.php?tab=orders&cancel_order_id=<?php echo $ord['id']; ?>&tbl_id=<?php echo $ord['table_id'].$link_suffix; ?>" onclick="return confirm('İptal edilsin mi?')" class="btn btn-danger">İPTAL ET</a></div></div></div></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
            <?php $start_date=isset($_GET['start_date'])?$_GET['start_date']:date('Y-m-d'); $end_date=isset($_GET['end_date'])?$_GET['end_date']:date('Y-m-d'); $start_full=$start_date." 00:00:00"; $end_full=$end_date." 23:59:59"; $hist_sql="SELECT o.*, t.table_name FROM orders o LEFT JOIN restaurant_tables t ON o.table_id = t.id WHERE o.user_id = ? AND o.status IN ('completed', 'cancelled') AND (o.updated_at BETWEEN ? AND ? OR o.created_at BETWEEN ? AND ?) ORDER BY o.id DESC"; $hist_stmt=$pdo->prepare($hist_sql); $hist_stmt->execute([$aktif_user_id, $start_full, $end_full, $start_full, $end_full]); $history=$hist_stmt->fetchAll(); $total_revenue=0; $total_cash=0; $total_card=0; foreach($history as $h) { if ($h['status'] == 'completed') { $total_revenue += $h['total_amount']; if (isset($h['payment_method']) && $h['payment_method'] == 'kredi_karti') { $total_card += $h['total_amount']; } else { $total_cash += $h['total_amount']; } } } ?>
            <div class="card shadow-sm border-primary mb-3 no-print"><div class="card-body"><form method="get" class="row g-2 align-items-end"><input type="hidden" name="tab" value="orders"><?php if($is_admin_mode): ?><input type="hidden" name="target_id" value="<?php echo $aktif_user_id; ?>"><?php endif; ?><div class="col-md-3"><label class="fw-bold">Başlangıç</label><input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>"></div><div class="col-md-3"><label class="fw-bold">Bitiş</label><input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>"></div><div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filtrele</button></div><div class="col-md-4 text-end"><button type="button" onclick="exportToExcel('histTable', 'Ciro_Raporu_<?php echo $start_date; ?>')" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Excel</button> <button type="button" onclick="exportToPDF()" class="btn btn-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</button> <button type="button" onclick="window.print()" class="btn btn-dark"><i class="bi bi-printer"></i> Yazdır</button></div></form></div></div>
            <div id="printableReport"><div class="text-center mb-3 d-none d-print-block"><h3><?php echo htmlspecialchars($aktif_store_name); ?> - Satış Raporu</h3><p><?php echo date("d.m.Y", strtotime($start_date)); ?> - <?php echo date("d.m.Y", strtotime($end_date)); ?></p></div><div class="row text-center mb-4"><div class="col-md-4"><div class="card bg-success text-white"><div class="card-body"><h3><?php echo number_format($total_revenue, 2); ?> ₺</h3><small>TOPLAM CİRO</small></div></div></div><div class="col-md-4"><div class="card bg-dark text-white"><div class="card-body"><h3><?php echo number_format($total_cash, 2); ?> ₺</h3><small>Nakit Ödemeler</small></div></div></div><div class="col-md-4"><div class="card bg-info text-white"><div class="card-body"><h3><?php echo number_format($total_card, 2); ?> ₺</h3><small>Kredi Kartı</small></div></div></div></div><div class="card shadow-sm border-0"><div class="card-header bg-primary text-white no-print"><h5 class="mb-0"><i class="bi bi-clock-history"></i> Geçmiş Detayları</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped align-middle mb-0" id="histTable"><thead><tr><th>Tarih</th><th>Masa</th><th>Ödeme</th><th>Tutar</th><th>Durum</th><th class="no-print">İşlem</th></tr></thead><tbody><?php if(count($history) == 0): ?><tr><td colspan="6" class="text-center text-muted">Kayıt yok.</td></tr><?php endif; ?><?php foreach($history as $h): ?><tr><td><?php echo date("d.m.Y H:i", strtotime($h['updated_at'] ? $h['updated_at'] : $h['created_at'])); ?></td><td><?php echo htmlspecialchars($h['table_name'] ?? 'Masa #'.$h['id']); ?></td><td><?php echo (isset($h['payment_method']) && $h['payment_method']=='kredi_karti') ? 'K.Kartı' : 'Nakit'; ?></td><td class="fw-bold"><?php echo number_format($h['total_amount'], 2); ?> ₺</td><td><?php echo ($h['status']=='completed') ? 'Tamamlandı' : 'İptal'; ?></td><td class="no-print"><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#histModal<?php echo $h['id']; ?>">İncele</button></td></tr><div class="modal fade no-print" id="histModal<?php echo $h['id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Özet</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><ul class="list-group"><?php $h_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?"); $h_items->execute([$h['id']]); while($hi = $h_items->fetch()): ?><li class="list-group-item d-flex justify-content-between"><span><?php echo $hi['quantity']; ?>x <?php echo htmlspecialchars($hi['product_name']); ?></span><span><?php echo number_format($hi['price']*$hi['quantity'], 2); ?> ₺</span></li><?php endwhile; ?></ul></div></div></div></div><?php endforeach; ?></tbody></table></div></div></div></div> 
        <?php endif; ?>

        <?php if($active_tab == 'admin_announcements'): ?>
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning text-dark fw-bold">
                    <i class="bi bi-megaphone-fill me-2"></i> Yönetimden Duyurular
                </div>
                <div class="list-group list-group-flush">
                    <?php
                    // Aktif ve süresi dolmamış tüm duyuruları çek, okundu bilgisini de al
                    $now = date("Y-m-d H:i:s");
                    $all_ann = $pdo->prepare("SELECT a.*, (SELECT COUNT(*) FROM announcement_reads r WHERE r.announcement_id = a.id AND r.user_id = ?) as is_read 
                                              FROM admin_announcements a 
                                              WHERE a.is_active = 1 
                                              AND (a.start_date IS NULL OR a.start_date <= ?)
                                              AND (a.end_date IS NULL OR a.end_date >= ?)
                                              ORDER BY a.id DESC");
                    $all_ann->execute([$aktif_user_id, $now, $now]);
                    $ann_list = $all_ann->fetchAll();

                    if(count($ann_list) == 0):
                        echo '<div class="p-4 text-center text-muted">Şu an aktif bir duyuru bulunmamaktadır.</div>';
                    else:
                        foreach($ann_list as $ann): 
                            $badge = ($ann['is_read'] > 0) ? '<span class="badge bg-success rounded-pill"><i class="bi bi-check-circle"></i> Okundu</span>' : '<span class="badge bg-danger rounded-pill">Yeni</span>';
                            $bg_class = ($ann['is_read'] > 0) ? '' : 'bg-light';
                    ?>
                    <div class="list-group-item p-3 <?php echo $bg_class; ?>">
                        <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                            <h5 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($ann['title']); ?></h5>
                            <?php echo $badge; ?>
                        </div>
                        <p class="mb-1 text-secondary"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
                        <small class="text-muted"><i class="bi bi-clock"></i> Yayınlanma: <?php echo date("d.m.Y H:i", strtotime($ann['created_at'])); ?></small>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if($active_tab == 'menu'): ?><div class="card p-3 mb-4 text-center shadow-sm border-0 bg-white"><div class="d-flex justify-content-center align-items-center gap-4 flex-wrap"><img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($menu_link); ?>" alt="QR" class="border p-2 rounded"><div class="text-start"><h5 class="mb-1">Menü Linki</h5><a href="<?php echo $menu_link; ?>" target="_blank" class="fw-bold text-decoration-none text-primary fs-5"><?php echo $menu_link; ?></a><br><small class="text-muted">Bu linki müşterilerinizle paylaşın.</small><br><a href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=<?php echo urlencode($menu_link); ?>" download class="btn btn-success btn-sm mt-2">QR İndir</a></div></div></div><div class="row"><div class="col-12 mb-4"><div class="card shadow-sm"><div class="card-header bg-success text-white">Ürün Ekle</div><div class="card-body"><form method="post" enctype="multipart/form-data"><div class="row"><div class="col-md-6"><select name="category_id" class="form-select mb-2" required><option value="">Kategori Seçin</option><?php foreach($categories_data as $c) { echo "<option value='".$c['id']."'>".$c['name']."</option>"; } ?></select><input type="text" name="p_name" class="form-control mb-2" placeholder="Ürün Adı" required><input type="number" step="0.01" name="p_price" class="form-control mb-2" placeholder="Fiyat" required></div><div class="col-md-6"><textarea name="p_desc" class="form-control mb-2" rows="1" placeholder="Açıklama"></textarea><input type="file" name="p_image" class="form-control mb-2" required><button type="submit" name="add_product" class="btn btn-success w-100 mt-1">Kaydet</button></div></div></form></div></div></div><div class="col-12"><div class="card shadow-sm"><div class="card-header bg-secondary text-white">Mevcut Ürünler</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th width="80">Resim</th><th>Ürün</th><th>Fiyat</th><th>Kategori</th><th>Durum</th><th class="text-end">İşlemler</th></tr></thead><tbody><?php $stmt = $pdo->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.user_id = ? ORDER BY p.id DESC"); $stmt->execute([$aktif_user_id]); while($row = $stmt->fetch()): $cat_display = !empty($row['cat_name']) ? htmlspecialchars($row['cat_name']) : '<span class="text-danger small fst-italic">Kategori Silinmiş</span>'; ?><tr><td class="text-center"><img src="<?php echo htmlspecialchars($row['image_path']); ?>" width="60" height="60" class="product-thumb"></td><td><strong><?php echo htmlspecialchars($row['name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($row['description']); ?></small></td><td><?php echo number_format($row['price'], 2); ?> ₺</td><td><span class="badge bg-info text-dark"><?php echo $cat_display; ?></span></td><td><a href="panel.php?durum_id=<?php echo $row['id'].'&durum='.$row['is_active'].$link_suffix; ?>" class="btn btn-sm btn-outline-<?php echo $row['is_active']?'success':'secondary'; ?>"><?php echo $row['is_active']?'Yayında':'Gizli'; ?></a></td><td class="text-end"><button class="btn btn-sm btn-primary edit-product-btn" data-bs-toggle="modal" data-bs-target="#editProductModal" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['name']); ?>" data-price="<?php echo $row['price']; ?>" data-desc="<?php echo htmlspecialchars($row['description']); ?>" data-cat="<?php echo $row['category_id']; ?>"><i class="bi bi-pencil-square"></i></button> <a href="panel.php?sil_urun=<?php echo $row['id'].$link_suffix; ?>" onclick="return confirm('Silinsin mi?')" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></a></td></tr><?php endwhile; ?></tbody></table></div></div></div></div></div><?php endif; ?>
        <?php if($active_tab == 'staff'): ?><div class="row"><div class="col-md-4 mb-3"><div class="card p-3 shadow-sm"><h5>Personel Ekle</h5><form method="post"><input type="text" name="s_name" class="form-control mb-2" placeholder="Ad Soyad" required><div class="input-group mb-2"><span class="input-group-text bg-light small fw-bold text-muted"><?php echo $user_data['slug']; ?>_</span><input type="text" name="s_user" class="form-control" placeholder="kullaniciadi" required></div><small class="text-muted d-block mb-2 fst-italic">Giriş için: <b><?php echo $user_data['slug']; ?>_isim</b> kullanılacak.</small><input type="text" name="s_pass" class="form-control mb-2" placeholder="Şifre" required><select name="s_role" class="form-select mb-2"><option value="waiter">Garson</option><option value="cashier">Kasa</option><option value="kitchen">Mutfak</option></select><button type="submit" name="add_staff" class="btn btn-success w-100">Ekle</button></form></div></div><div class="col-md-8"><div class="card shadow-sm"><div class="card-header bg-primary text-white">Personel Listesi</div><div class="card-body p-0"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Ad</th><th>Kullanıcı Adı</th><th>Rol</th><th class="text-end">İşlem</th></tr></thead><tbody><?php $staffs = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?"); $staffs->execute([$aktif_user_id]); while($s = $staffs->fetch()): ?><tr><td><?php echo htmlspecialchars($s['name']); ?></td><td class="fw-bold text-primary"><?php echo htmlspecialchars($s['username']); ?></td><td><span class="badge bg-info text-dark"><?php echo $s['role']; ?></span></td><td class="text-end"><a href="panel.php?tab=staff&del_staff=<?php echo $s['id'].$link_suffix; ?>" onclick="return confirm('Silinsin mi?')" class="btn btn-sm btn-danger">Sil</a></td></tr><?php endwhile; ?></tbody></table></div></div></div></div><?php endif; ?>
        <?php if($active_tab == 'tables'): ?><div class="row"><div class="col-md-4 mb-3"><div class="card p-3 shadow-sm"><h5>Masa Oluştur</h5><form method="post"><input type="text" name="table_name" class="form-control mb-2" placeholder="Masa Adı" required><label class="form-label small">Sorumlu Garson</label><select name="waiter_ids[]" class="form-select mb-2" multiple style="height:120px;"><option value="0" selected>-- Herkes Görebilir --</option><?php foreach($waiters_list as $w) { echo "<option value='".$w['id']."'>".$w['name']."</option>"; } ?></select><small class="d-block mb-3 text-muted">CTRL ile çoklu seçim yapabilirsiniz.</small><button type="submit" name="add_table" class="btn btn-primary w-100">Oluştur</button></form></div></div><div class="col-md-8"><div class="card shadow-sm"><div class="card-header bg-secondary text-white">Masa Listesi</div><div class="card-body p-0"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Masa</th><th>Sorumlu</th><th>Durum</th><th class="text-end">İşlem</th></tr></thead><tbody><?php $tbls = $pdo->prepare("SELECT t.*, s.name as waiter_name FROM restaurant_tables t LEFT JOIN staff s ON t.assigned_waiter_id = s.id WHERE t.user_id = ?"); $tbls->execute([$aktif_user_id]); while($t = $tbls->fetch()): $waiter_display = 'Herkes'; if (!empty($t['assigned_waiter_id']) && $t['assigned_waiter_id'] != '0') { $w_ids = explode(',', $t['assigned_waiter_id']); $w_ids = array_map('intval', $w_ids); if(!empty($w_ids)){ $in = str_repeat('?,', count($w_ids) - 1) . '?'; $w_sql = "SELECT name FROM staff WHERE id IN ($in)"; $w_stmt = $pdo->prepare($w_sql); $w_stmt->execute($w_ids); $w_names = $w_stmt->fetchAll(PDO::FETCH_COLUMN); if($w_names) $waiter_display = implode(', ', $w_names); } } ?><tr><td><strong><?php echo htmlspecialchars($t['table_name']); ?></strong></td><td><?php echo $waiter_display; ?></td><td><?php echo $t['status']==1?'<span class="badge bg-danger">Dolu</span>':'<span class="badge bg-success">Boş</span>'; ?></td><td class="text-end"><button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#editTableModal" data-id="<?php echo $t['id']; ?>" data-name="<?php echo htmlspecialchars($t['table_name']); ?>" data-waiter="<?php echo $t['assigned_waiter_id']; ?>"><i class="bi bi-pencil-square"></i></button><a href="panel.php?tab=tables&del_table=<?php echo $t['id'].$link_suffix; ?>" onclick="return confirm('Silinsin mi?')" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></a></td></tr><?php endwhile; ?></tbody></table></div></div></div></div><div class="modal fade" id="editTableModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title">Masayı Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="edit_table_id" id="modal_tbl_id"><div class="mb-3"><label>Masa Adı</label><input type="text" name="edit_table_name" id="modal_tbl_name" class="form-control" required></div><div class="mb-3"><label>Sorumlu Garsonlar</label><select name="edit_waiter_ids[]" id="modal_tbl_waiter" class="form-select" multiple style="height: 120px;"><option value="0">-- Herkes Görebilir --</option><?php foreach($waiters_list as $w) { echo "<option value='".$w['id']."'>".$w['name']."</option>"; } ?></select><small class="text-muted">CTRL tuşu ile çoklu seçim yapabilirsiniz.</small></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button><button type="submit" name="edit_table" class="btn btn-primary">Kaydet</button></div></form></div></div><?php endif; ?>
        <?php if($active_tab == 'slider'): ?><div class="card shadow-sm mb-4"><div class="card-header bg-warning text-dark">Slider / Kampanya</div><div class="card-body"><form method="post" enctype="multipart/form-data" class="mb-4"><div class="d-flex justify-content-between align-items-center border rounded p-3 mb-3 bg-white"><label class="form-check-label fw-bold mb-0" for="show_slider">Slider Göster</label><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="show_slider" id="show_slider" style="width: 3em; height: 1.5em;" <?php echo ($user_data['show_slider']??1)==1?'checked':''; ?>></div></div><div class="mb-3"><label class="form-label fw-bold">Slider Başlığı</label><input type="text" name="slider_title" class="form-control" value="<?php echo htmlspecialchars($user_data['slider_title']??'Kampanyalar'); ?>"></div><div class="mb-3"><label class="form-label fw-bold">Yeni Resim</label><input type="file" name="slider_image" class="form-control"></div><button type="submit" name="upload_slider" class="btn btn-primary w-100">Kaydet</button></form><div class="row g-3"><?php $sliders = $pdo->prepare("SELECT * FROM slider_images WHERE user_id = ? ORDER BY id DESC"); $sliders->execute([$aktif_user_id]); while($slide = $sliders->fetch()): ?><div class="col-6 col-md-4"><div class="card h-100"><img src="<?php echo htmlspecialchars($slide['image_path']); ?>" class="slider-thumb"><div class="card-body p-2 text-center"><a href="panel.php?sil_slider=<?php echo $slide['id'].$link_suffix; ?>" class="btn btn-sm btn-danger w-100">Sil</a></div></div></div><?php endwhile; ?></div></div></div><?php endif; ?>
        
        <?php if($active_tab == 'settings'): ?>
            <div class="card shadow-sm"><div class="card-header bg-info text-white">Ayarlar</div><div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-4 bg-light p-3 border rounded">
                        <label class="form-label fw-bold text-primary">Kafe Linki (Slug)</label>
                        <div class="input-group"><span class="input-group-text"><?php echo $_SERVER['HTTP_HOST']; ?>/</span><input type="text" name="slug" class="form-control fw-bold" value="<?php echo htmlspecialchars($user_data['slug']); ?>" required></div>
                        <small class="text-muted">Menü adresiniz: <b><?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $user_data['slug']; ?></b></small>
                    </div>

                    <div class="mb-4 p-3 border rounded">
                        <h6 class="fw-bold text-dark border-bottom pb-2">Site Tasarımı & Renkler</h6>
                        <div class="row g-3">
                            <div class="col-md-2 col-6"><div class="color-input-group"><input type="color" name="color_bg" value="<?php echo $user_data['color_bg'] ?? '#f8f9fa'; ?>"><label>Arka Plan</label></div></div>
                            <div class="col-md-2 col-6"><div class="color-input-group"><input type="color" name="color_header" value="<?php echo $user_data['color_header'] ?? '#ffffff'; ?>"><label>Üst Kısım</label></div></div>
                            <div class="col-md-2 col-6"><div class="color-input-group"><input type="color" name="color_text" value="<?php echo $user_data['color_text'] ?? '#333333'; ?>"><label>Yazı Rengi</label></div></div>
                            <div class="col-md-2 col-6"><div class="color-input-group"><input type="color" name="color_btn" value="<?php echo $user_data['color_btn'] ?? '#ff5e62'; ?>"><label>Buton/Vurgu</label></div></div>
                            <div class="col-md-2 col-6"><div class="color-input-group"><input type="color" name="color_card" value="<?php echo $user_data['color_card'] ?? '#ffffff'; ?>"><label>Kart Rengi</label></div></div>
                        </div>
                    </div>

                    <div class="mb-4 p-3 border rounded bg-light border-warning">
                        <h6 class="fw-bold text-dark border-bottom pb-2"><i class="bi bi-megaphone"></i> Müşteri Duyurusu (Menü İçin)</h6>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="ann_active" id="ann_active" <?php echo ($user_data['ann_active'] ?? 0) == 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="ann_active">Duyuruyu Aktif Et</label>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Başlık</label>
                                <input type="text" name="ann_title" class="form-control" value="<?php echo htmlspecialchars($user_data['ann_title'] ?? ''); ?>" placeholder="Örn: Hafta Sonu İndirimi!">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Mesaj İçeriği</label>
                                <textarea name="ann_message" class="form-control" rows="3" placeholder="Duyuru detaylarını buraya yazın..."><?php echo htmlspecialchars($user_data['ann_message'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Başlangıç Zamanı</label>
                                <input type="datetime-local" name="ann_start" class="form-control" value="<?php echo $user_data['ann_start'] ? date('Y-m-d\TH:i', strtotime($user_data['ann_start'])) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bitiş Zamanı</label>
                                <input type="datetime-local" name="ann_end" class="form-control" value="<?php echo $user_data['ann_end'] ? date('Y-m-d\TH:i', strtotime($user_data['ann_end'])) : ''; ?>">
                                <small class="text-muted">Boş bırakılırsa süresiz olur.</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 p-3 border rounded">
                        <h6 class="fw-bold text-dark border-bottom pb-2"><i class="bi bi-wifi"></i> Wi-Fi Bilgileri</h6>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Wi-Fi Adı</label><input type="text" name="wifi_name" class="form-control" value="<?php echo htmlspecialchars($user_data['wifi_name'] ?? ''); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Wi-Fi Şifresi</label><input type="text" name="wifi_pass" class="form-control" value="<?php echo htmlspecialchars($user_data['wifi_pass'] ?? ''); ?>"></div>
                        </div>
                    </div>

                    <div class="mb-4 p-3 border rounded">
                        <h6 class="fw-bold text-dark border-bottom pb-2"><i class="bi bi-volume-up"></i> Bildirim Sesi</h6>
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <select name="notification_sound" class="form-select" onchange="previewSound(this)">
                                    <?php foreach($sound_options as $key => $name): ?>
                                    <option value="<?php echo $key; ?>" <?php echo (isset($user_data['notification_sound']) && $user_data['notification_sound'] == $key) ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 text-muted small">
                                Ses seçince otomatik çalar.
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4 align-items-center"><div class="col-md-3 text-center"><?php if(!empty($user_data['store_image'])): ?><img src="<?php echo $user_data['store_image']; ?>" class="store-logo-preview mb-2"><?php endif; ?></div><div class="col-md-9"><label class="form-label fw-bold">Logo</label><input type="file" name="store_image" class="form-control"></div></div><hr><div class="mb-3 form-check form-switch"><input class="form-check-input" type="checkbox" name="show_social" id="ss" <?php echo ($user_data['show_social']==1)?'checked':''; ?>><label class="form-check-label" for="ss">Sosyal Medya Göster</label></div><div class="row"><div class="col-md-6 mb-3"><label>Telefon</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user_data['phone']??''); ?>"></div><div class="col-md-6 mb-3"><label>Harita</label><input type="text" name="google_map" class="form-control" value="<?php echo htmlspecialchars($user_data['google_map']??''); ?>"></div><div class="col-md-6 mb-3"><label>Instagram</label><input type="text" name="instagram" class="form-control" value="<?php echo htmlspecialchars($user_data['instagram']??''); ?>"></div><div class="col-md-6 mb-3"><label>X</label><input type="text" name="twitter" class="form-control" value="<?php echo htmlspecialchars($user_data['twitter']??''); ?>"></div><div class="col-md-6 mb-3"><label>Facebook</label><input type="text" name="facebook" class="form-control" value="<?php echo htmlspecialchars($user_data['facebook']??''); ?>"></div><div class="col-md-6 mb-3"><label>Youtube</label><input type="text" name="youtube" class="form-control" value="<?php echo htmlspecialchars($user_data['youtube']??''); ?>"></div></div><button type="submit" name="update_settings" class="btn btn-primary w-100">Kaydet</button></form></div></div>
        <?php endif; ?>
        <?php if($active_tab == 'categories'): ?><div class="row"><div class="col-md-5 mb-3"><div class="card shadow-sm"><div class="card-header bg-primary text-white">Yeni Kategori</div><div class="card-body"><form method="post"><input type="text" name="cat_name" class="form-control mb-3" required><button type="submit" name="add_category" class="btn btn-primary w-100">Ekle</button></form></div></div></div><div class="col-md-7"><div class="card shadow-sm"><div class="card-header bg-secondary text-white">Kategorilerim</div><ul class="list-group list-group-flush"><?php foreach($categories_data as $c): ?><li class="list-group-item d-flex justify-content-between align-items-center"><?php echo htmlspecialchars($c['name']); ?><a href="panel.php?sil_kategori=<?php echo $c['id'].$link_suffix; ?>" onclick="return confirm('Silinsin mi?')" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Sil</a></li><?php endforeach; ?></ul></div></div></div><?php endif; ?>
    </div>

    <div class="modal fade" id="editProductModal" tabindex="-1"><div class="modal-dialog"><form method="post" enctype="multipart/form-data" class="modal-content"><div class="modal-header"><h5 class="modal-title">Ürün Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="edit_p_id" id="modal_p_id"><div class="mb-3"><label>Kategori</label><select name="edit_category_id" id="modal_cat_id" class="form-select"><?php foreach($categories_data as $cat) { echo "<option value='".$cat['id']."'>".$cat['name']."</option>"; } ?></select></div><div class="mb-3"><label>Ürün Adı</label><input type="text" name="edit_p_name" id="modal_p_name" class="form-control" required></div><div class="mb-3"><label>Fiyat</label><input type="number" step="0.01" name="edit_p_price" id="modal_p_price" class="form-control" required></div><div class="mb-3"><label>Açıklama</label><textarea name="edit_p_desc" id="modal_p_desc" class="form-control" rows="2"></textarea></div><div class="mb-3"><label>Yeni Resim</label><input type="file" name="edit_p_image" class="form-control"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button><button type="submit" name="update_product" class="btn btn-primary">Kaydet</button></div></form></div></div>
    
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
        <div id="msgToast" class="toast custom-toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-primary text-white">
                <i class="bi bi-envelope-fill me-2"></i>
                <strong class="me-auto" id="toastTitle">Bildirim</strong>
                <small id="toastTime" class="text-white-50">Şimdi</small>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="custom-toast-body">
                <div class="fw-bold mb-1" id="toastSender">Gönderen</div>
                <div id="toastBody">İçerik...</div>
            </div>
        </div>
    </div>

    <?php if(count($popup_announcements) > 0): $current_ann = $popup_announcements[0]; ?>
    <div class="modal fade" id="adminAnnModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-warning text-dark border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-megaphone-fill me-2"></i> Admin Duyurusu</h5>
                </div>
                <div class="modal-body p-4 text-center">
                    <h3 class="fw-bold mb-3"><?php echo htmlspecialchars($current_ann['title']); ?></h3>
                    <p class="lead mb-4"><?php echo nl2br(htmlspecialchars($current_ann['message'])); ?></p>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-dark btn-lg rounded-pill" onclick="markRead(<?php echo $current_ann['id']; ?>)">OKUDUM, KAPAT</button>
                        
                        <button class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">SONRA OKU</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <audio id="panelSound" src="<?php echo $active_sound_url; ?>"></audio>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // DUYURU MODALI AÇMA (Sadece restoranlar için)
        <?php if(count($popup_announcements) > 0): ?>
        window.addEventListener('load', function() {
            var myModal = new bootstrap.Modal(document.getElementById('adminAnnModal'));
            myModal.show();
        });
        
        function markRead(annId) {
            let formData = new FormData();
            formData.append('ann_id', annId);
            fetch('api_mark_read.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    location.reload(); // Sayfayı yenile (Varsa diğer duyuruyu gösterir)
                });
        }
        <?php endif; ?>

        // SES & BİLDİRİM
        const soundUrls = {
            'sound1': 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3',
            'sound2': 'https://assets.mixkit.co/active_storage/sfx/1003/1003-preview.mp3',
            'sound3': 'https://assets.mixkit.co/active_storage/sfx/236/236-preview.mp3',
            'sound4': 'https://assets.mixkit.co/active_storage/sfx/995/995-preview.mp3'
        };
        function previewSound(select) {
            const url = soundUrls[select.value];
            if(url) { new Audio(url).play(); }
        }

        let audioContextUnlocked = false;
        function initSound() {
            if(!audioContextUnlocked) {
                const audio = document.getElementById('panelSound');
                audio.volume = 0; audio.play().then(()=>{ audio.pause(); audio.volume=1; audioContextUnlocked=true; }).catch(e=>{});
            }
        }

        // BİLDİRİM KONTROL
        const msgToast = new bootstrap.Toast(document.getElementById('msgToast'));
        let lastMsgId = 0;

        document.getElementById('msgToast').addEventListener('click', function() {
            window.location.href = 'support.php';
        });

        function checkNotifications() {
            fetch('api_notifications.php?t='+Date.now())
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'new') {
                        if(data.msg_id > lastMsgId) {
                            lastMsgId = data.msg_id;
                            document.getElementById('toastTitle').innerText = data.title;
                            document.getElementById('toastSender').innerText = data.sender;
                            document.getElementById('toastTime').innerText = data.time;
                            document.getElementById('toastBody').innerHTML = data.text;
                            msgToast.show();
                            document.getElementById('panelSound').play().catch(e=>{});
                            const badge = document.getElementById('msgBadge');
                            if(badge) badge.classList.remove('d-none');
                        }
                    }
                }).catch(e=>{});
        }
        setInterval(checkNotifications, 5000); 
        checkNotifications();

        document.addEventListener('DOMContentLoaded',function(){
            const m=document.getElementById('editProductModal');
            if(m) {
                m.addEventListener('show.bs.modal',e=>{
                    const b=e.relatedTarget;
                    document.getElementById('modal_p_id').value=b.getAttribute('data-id');
                    document.getElementById('modal_p_name').value=b.getAttribute('data-name');
                    document.getElementById('modal_p_price').value=b.getAttribute('data-price');
                    document.getElementById('modal_p_desc').value=b.getAttribute('data-desc');
                    document.getElementById('modal_cat_id').value=b.getAttribute('data-cat');
                });
            }
            const tm = document.getElementById('editTableModal');
            if(tm) {
                tm.addEventListener('show.bs.modal', e => {
                    const btn = e.relatedTarget;
                    document.getElementById('modal_tbl_id').value = btn.getAttribute('data-id');
                    document.getElementById('modal_tbl_name').value = btn.getAttribute('data-name');
                    const waiterIds = btn.getAttribute('data-waiter').split(','); 
                    const selectBox = document.getElementById('modal_tbl_waiter');
                    for (let i = 0; i < selectBox.options.length; i++) selectBox.options[i].selected = false;
                    for (let i = 0; i < selectBox.options.length; i++) {
                        if (waiterIds.includes(selectBox.options[i].value)) selectBox.options[i].selected = true;
                    }
                });
            }
        });
        function exportToExcel(tableID, filename = '') { var elt = document.getElementById(tableID); var wb = XLSX.utils.table_to_book(elt, {sheet: "Rapor"}); filename = filename ? filename + '.xlsx' : 'rapor.xlsx'; XLSX.writeFile(wb, filename); }
        function exportToPDF() { var element = document.getElementById('printableReport'); var opt = { margin: 0.5, filename: 'ciro_raporu.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' } }; html2pdf().set(opt).from(element).save(); }
    </script>
</body>
</html>
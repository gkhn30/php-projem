<?php
// admin.php - ADMİN PROFİL VE ŞİFRE GÜNCELLEME EKLENDİ
session_start();
include 'db.php';

// Güvenlik
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit; }

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
$admin_id = $_SESSION['user_id']; // Giriş yapan adminin ID'si

// --- İŞLEMLER ---

// 1. ADMİN ŞİFRE GÜNCELLEME (YENİ ÖZELLİK)
if (isset($_POST['update_admin_pass'])) {
    $old_pass = $_POST['old_pass'];
    $new_pass = $_POST['new_pass'];
    $renew_pass = $_POST['renew_pass'];

    // Mevcut şifreyi veritabanından çek
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $current_hash = $stmt->fetchColumn();

    if (!password_verify($old_pass, $current_hash)) {
        header("Location: admin.php?tab=profile&err=old_pass"); exit;
    } elseif ($new_pass !== $renew_pass) {
        header("Location: admin.php?tab=profile&err=mismatch"); exit;
    } elseif (strlen($new_pass) < 6) {
        header("Location: admin.php?tab=profile&err=short"); exit;
    } else {
        // Her şey yolunda, şifreyi güncelle
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $admin_id]);
        header("Location: admin.php?tab=profile&msg=pass_ok"); exit;
    }
}

// 2. SEO, DEMO VE GÖRSEL AYARLARINI GÜNCELLE
if (isset($_POST['update_seo'])) {
    $title = $_POST['site_title'];
    $desc = $_POST['site_desc'];
    $keys = $_POST['site_keywords'];
    $auth = $_POST['site_author'];
    $demo = $_POST['demo_url'];
    
    $home_img_sql = "";
    $params = [$title, $desc, $keys, $auth, $demo];
    
    if (!empty($_FILES["home_image"]["name"])) {
        $target_dir = "uploads/admin/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        $target_file = $target_dir . time() . "_home_" . basename($_FILES["home_image"]["name"]);
        if (move_uploaded_file($_FILES["home_image"]["tmp_name"], $target_file)) {
            $home_img_sql = ", home_image = ?";
            $params[] = $target_file;
        }
    }
    
    $params[] = 1; 
    $pdo->prepare("UPDATE system_settings SET site_title=?, site_desc=?, site_keywords=?, site_author=?, demo_url=?, updated_at=NOW() $home_img_sql WHERE id=?")->execute($params);
    header("Location: admin.php?tab=seo&msg=ok"); exit;
}

// 3. KULLANICI İŞLEMLERİ
if (isset($_GET['toggle_user'])) {
    $uid = $_GET['toggle_user'];
    $status = $_GET['status'] == 1 ? 0 : 1;
    $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$status, $uid]);
    header("Location: admin.php?tab=users"); exit;
}
if (isset($_GET['del_user'])) {
    $uid = $_GET['del_user'];
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
    $pdo->prepare("DELETE FROM products WHERE user_id = ?")->execute([$uid]);
    $pdo->prepare("DELETE FROM categories WHERE user_id = ?")->execute([$uid]);
    header("Location: admin.php?tab=users"); exit;
}
if (isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $raw_pass = $_POST['password'];
    $store_name = $_POST['store_name'];
    $email = !empty($_POST['email']) ? $_POST['email'] : NULL;
    $hashed_password = password_hash($raw_pass, PASSWORD_DEFAULT);
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if($check->rowCount() > 0){ echo "<script>alert('Bu kullanıcı adı dolu!');</script>"; } else {
        $slug = preg_replace('/[^a-z0-9]/', '', strtolower($username));
        $pdo->prepare("INSERT INTO users (username, password, role, store_name, slug, email, is_active) VALUES (?, ?, 'restaurant', ?, ?, ?, 1)")
            ->execute([$username, $hashed_password, $store_name, $slug, $email]);
        header("Location: admin.php?tab=users"); exit;
    }
}
if (isset($_POST['edit_user'])) {
    $uid = $_POST['edit_user_id'];
    $u_store = $_POST['edit_store_name'];
    $u_name = $_POST['edit_username'];
    $u_pass = $_POST['edit_password'];
    $u_email = !empty($_POST['edit_email']) ? $_POST['edit_email'] : NULL;
    if (!empty($u_pass)) {
        $hashed_password = password_hash($u_pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET store_name=?, username=?, password=?, email=? WHERE id=?")->execute([$u_store, $u_name, $hashed_password, $u_email, $uid]);
    } else {
        $pdo->prepare("UPDATE users SET store_name=?, username=?, email=? WHERE id=?")->execute([$u_store, $u_name, $u_email, $uid]);
    }
    header("Location: admin.php?tab=users"); exit;
}

// 4. DUYURU İŞLEMLERİ
if (isset($_POST['add_announcement'])) { $start = !empty($_POST['start_date']) ? $_POST['start_date'] : NULL; $end = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL; $pdo->prepare("INSERT INTO admin_announcements (title, message, start_date, end_date, is_active) VALUES (?, ?, ?, ?, 1)")->execute([$_POST['title'], $_POST['message'], $start, $end]); header("Location: admin.php?tab=announcements"); exit; }
if (isset($_GET['del_ann'])) { $pdo->prepare("DELETE FROM admin_announcements WHERE id = ?")->execute([$_GET['del_ann']]); $pdo->prepare("DELETE FROM announcement_reads WHERE announcement_id = ?")->execute([$_GET['del_ann']]); header("Location: admin.php?tab=announcements"); exit; }
if (isset($_GET['toggle_ann'])) { $s = $_GET['status'] == 1 ? 0 : 1; $pdo->prepare("UPDATE admin_announcements SET is_active = ? WHERE id = ?")->execute([$s, $_GET['toggle_ann']]); header("Location: admin.php?tab=announcements"); exit; }
if (isset($_POST['edit_announcement'])) { $aid = $_POST['edit_ann_id']; $start = !empty($_POST['edit_start_date']) ? $_POST['edit_start_date'] : NULL; $end = !empty($_POST['edit_end_date']) ? $_POST['edit_end_date'] : NULL; $pdo->prepare("UPDATE admin_announcements SET title=?, message=?, start_date=?, end_date=? WHERE id=?")->execute([$_POST['edit_title'], $_POST['edit_message'], $start, $end, $aid]); header("Location: admin.php?tab=announcements"); exit; }

// --- VERİLERİ ÇEK ---
$users = $pdo->query("SELECT * FROM users WHERE role != 'admin' ORDER BY id DESC")->fetchAll();
$announcements = $pdo->query("SELECT * FROM admin_announcements ORDER BY id DESC")->fetchAll();
$seo = $pdo->query("SELECT * FROM system_settings WHERE id = 1")->fetch();
if(!$seo) { $seo = ['site_title'=>'QRMasa', 'site_desc'=>'', 'site_keywords'=>'', 'site_author'=>'QRMasa', 'demo_url'=>'#', 'home_image'=>'']; }
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yönetici Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .toast-container { z-index: 9999 !important; }
        .custom-toast { border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.3); border: none; overflow: hidden; cursor: pointer; background-color: white; }
        .custom-toast .toast-header { border-bottom: none; color: white; background-color: #dc3545; }
        .custom-toast-body { font-size: 0.95rem; padding: 15px; color: #333; }
        .badge-pulse { animation: pulse-red 2s infinite; }
        @keyframes pulse-red { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
        .preview-img { max-width: 100px; max-height: 100px; object-fit: cover; border-radius: 10px; border: 2px solid #ddd; }
    </style>
</head>
<body class="bg-light" onclick="initSound()"> 

<nav class="navbar navbar-dark bg-danger mb-4 shadow-sm">
    <div class="container">
        <span class="navbar-brand"><i class="bi bi-shield-lock-fill"></i> Yönetici Paneli</span>
        <div class="d-flex align-items-center">
            <a href="support.php" class="btn btn-light btn-sm me-2 text-danger fw-bold position-relative">
                <i class="bi bi-envelope-fill"></i> Mesajlar
                <span id="msgBadge" class="position-absolute top-0 start-100 translate-middle p-2 bg-warning border border-light rounded-circle d-none badge-pulse"></span>
            </a>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Çıkış</a>
        </div>
    </div>
</nav>

<div class="container">
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded shadow-sm">
        <li class="nav-item"><a class="nav-link <?php echo $active_tab=='users'?'active bg-danger':'text-dark'; ?>" href="?tab=users"><i class="bi bi-people-fill"></i> Kullanıcılar</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $active_tab=='announcements'?'active bg-danger':'text-dark'; ?>" href="?tab=announcements"><i class="bi bi-megaphone-fill"></i> Duyurular</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $active_tab=='seo'?'active bg-danger':'text-dark'; ?>" href="?tab=seo"><i class="bi bi-gear-fill"></i> Ayarlar</a></li>
        <li class="nav-item ms-auto"><a class="nav-link <?php echo $active_tab=='profile'?'active bg-danger':'text-dark'; ?>" href="?tab=profile"><i class="bi bi-person-circle"></i> Profilim</a></li>
    </ul>

    <?php if($active_tab == 'users'): ?>
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">Yeni Restoran Ekle</div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3"><label>Restoran Adı</label><input type="text" name="store_name" class="form-control" required></div>
                        <div class="mb-3"><label>Kullanıcı Adı</label><input type="text" name="username" class="form-control" required></div>
                        <div class="mb-3"><label>E-Posta</label><input type="email" name="email" class="form-control"></div>
                        <div class="mb-3"><label>Şifre</label><input type="text" name="password" class="form-control" required></div>
                        <button type="submit" name="add_user" class="btn btn-success w-100">Kullanıcı Oluştur</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-secondary text-white fw-bold">Kayıtlı Restoranlar (<?php echo count($users); ?>)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>ID</th><th>Restoran</th><th>Durum</th><th class="text-center">Panel</th><th class="text-end">İşlemler</th></tr></thead>
                        <tbody>
                            <?php foreach($users as $u): ?>
                            <tr>
                                <td><?php echo $u['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($u['store_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($u['username']); ?></small></td>
                                <td class="text-center"><?php echo (isset($u['is_active']) && $u['is_active'] == 0) ? '<a href="?tab=users&toggle_user='.$u['id'].'&status=0" class="badge bg-secondary text-decoration-none" onclick="return confirm(\'Aktif et?\')">Pasif</a>' : '<a href="?tab=users&toggle_user='.$u['id'].'&status=1" class="badge bg-success text-decoration-none" onclick="return confirm(\'Pasif yap?\')">Aktif</a>'; ?></td>
                                <td class="text-center"><a href="panel.php?target_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Git</a></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal" data-id="<?php echo $u['id']; ?>" data-store="<?php echo htmlspecialchars($u['store_name']); ?>" data-user="<?php echo htmlspecialchars($u['username']); ?>" data-email="<?php echo htmlspecialchars($u['email'] ?? ''); ?>"><i class="bi bi-pencil-square"></i></button>
                                    <a href="?del_user=<?php echo $u['id']; ?>" onclick="return confirm('Silinsin mi?')" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($active_tab == 'announcements'): ?>
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning text-dark fw-bold">Yeni Duyuru</div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3"><label>Başlık</label><input type="text" name="title" class="form-control" required></div>
                        <div class="mb-3"><label>Mesaj</label><textarea name="message" class="form-control" rows="4" required></textarea></div>
                        <div class="mb-3"><label>Başlangıç</label><input type="datetime-local" name="start_date" class="form-control"></div>
                        <div class="mb-3"><label>Bitiş</label><input type="datetime-local" name="end_date" class="form-control"></div>
                        <button type="submit" name="add_announcement" class="btn btn-dark w-100">Yayınla</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">Geçmiş Duyurular</div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0 align-middle">
                        <thead><tr><th>Başlık</th><th>Tarih</th><th>Durum</th><th class="text-end">İşlemler</th></tr></thead>
                        <tbody>
                            <?php foreach($announcements as $ann): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($ann['title']); ?></strong></td>
                                <td><small><?php echo $ann['start_date'] ? date("d.m H:i", strtotime($ann['start_date'])) : 'Hemen'; ?></small></td>
                                <td><?php echo $ann['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>'; ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editAnnModal" data-id="<?php echo $ann['id']; ?>" data-title="<?php echo htmlspecialchars($ann['title']); ?>" data-message="<?php echo htmlspecialchars($ann['message']); ?>" data-start="<?php echo $ann['start_date']; ?>" data-end="<?php echo $ann['end_date']; ?>"><i class="bi bi-pencil-square"></i></button>
                                    <a href="?tab=announcements&del_ann=<?php echo $ann['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silinsin mi?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($active_tab == 'seo'): ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold"><i class="bi bi-gear-fill"></i> Site Genel Ayarları</div>
                <div class="card-body p-4">
                    <?php if(isset($_GET['msg']) && $_GET['msg']=='ok'): ?>
                        <div class="alert alert-success"><i class="bi bi-check-circle"></i> Ayarlar başarıyla güncellendi!</div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Site Adı (Marka)</label>
                                <input type="text" name="site_author" class="form-control" value="<?php echo htmlspecialchars($seo['site_author']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-danger">Demo Linki</label>
                                <input type="text" name="demo_url" class="form-control" value="<?php echo htmlspecialchars($seo['demo_url']); ?>">
                            </div>
                        </div>
                        <div class="mb-3 border p-3 rounded bg-light">
                            <label class="form-label fw-bold text-success"><i class="bi bi-image"></i> Ana Sayfa Görseli</label>
                            <div class="d-flex align-items-center gap-3">
                                <?php if(!empty($seo['home_image'])): ?><img src="<?php echo htmlspecialchars($seo['home_image']); ?>" class="preview-img"><?php endif; ?>
                                <input type="file" name="home_image" class="form-control">
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3"><label class="form-label fw-bold">Site Başlığı</label><input type="text" name="site_title" class="form-control" value="<?php echo htmlspecialchars($seo['site_title']); ?>"></div>
                        <div class="mb-3"><label class="form-label fw-bold">Açıklama</label><textarea name="site_desc" class="form-control" rows="2"><?php echo htmlspecialchars($seo['site_desc']); ?></textarea></div>
                        <div class="mb-3"><label class="form-label fw-bold">Anahtar Kelimeler</label><input type="text" name="site_keywords" class="form-control" value="<?php echo htmlspecialchars($seo['site_keywords']); ?>"></div>
                        <button type="submit" name="update_seo" class="btn btn-primary w-100 btn-lg"><i class="bi bi-save"></i> Kaydet</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($active_tab == 'profile'): ?>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold"><i class="bi bi-shield-lock"></i> Yönetici Şifre Değiştirme</div>
                <div class="card-body p-4">
                    
                    <?php if(isset($_GET['msg']) && $_GET['msg']=='pass_ok'): ?>
                        <div class="alert alert-success">Şifreniz başarıyla güncellendi.</div>
                    <?php endif; ?>
                    
                    <?php if(isset($_GET['err'])): ?>
                        <div class="alert alert-danger">
                            <?php 
                            if($_GET['err']=='old_pass') echo "Mevcut şifrenizi yanlış girdiniz.";
                            if($_GET['err']=='mismatch') echo "Yeni şifreler uyuşmuyor.";
                            if($_GET['err']=='short') echo "Yeni şifre en az 6 karakter olmalı.";
                            ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mevcut Şifreniz</label>
                            <input type="password" name="old_pass" class="form-control" required>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Yeni Şifre</label>
                            <input type="password" name="new_pass" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Yeni Şifre (Tekrar)</label>
                            <input type="password" name="renew_pass" class="form-control" required>
                        </div>
                        <button type="submit" name="update_admin_pass" class="btn btn-dark w-100">Şifreyi Güncelle</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<div class="modal fade" id="editUserModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title">Kullanıcı Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="edit_user_id" id="modal_uid"><div class="mb-3"><label>Restoran Adı</label><input type="text" name="edit_store_name" id="modal_store" class="form-control" required></div><div class="mb-3"><label>Kullanıcı Adı</label><input type="text" name="edit_username" id="modal_user" class="form-control" required></div><div class="mb-3"><label>E-Posta</label><input type="email" name="edit_email" id="modal_email" class="form-control"></div><div class="mb-3"><label class="fw-bold text-danger">Yeni Şifre</label><input type="text" name="edit_password" id="modal_pass" class="form-control" placeholder="Değişmeyecekse boş bırak"></div></div><div class="modal-footer"><button type="submit" name="edit_user" class="btn btn-primary">Kaydet</button></div></form></div></div>
<div class="modal fade" id="editAnnModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title">Duyuru Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="edit_ann_id" id="modal_ann_id"><div class="mb-3"><label>Başlık</label><input type="text" name="edit_title" id="modal_ann_title" class="form-control" required></div><div class="mb-3"><label>Mesaj</label><textarea name="edit_message" id="modal_ann_msg" class="form-control" rows="4" required></textarea></div><div class="mb-3"><label>Başlangıç</label><input type="datetime-local" name="edit_start_date" id="modal_ann_start" class="form-control"></div><div class="mb-3"><label>Bitiş</label><input type="datetime-local" name="edit_end_date" id="modal_ann_end" class="form-control"></div></div><div class="modal-footer"><button type="submit" name="edit_announcement" class="btn btn-primary">Güncelle</button></div></form></div></div>

<div class="toast-container position-fixed bottom-0 end-0 p-3"><div id="msgToast" class="toast custom-toast" role="alert" aria-live="assertive" aria-atomic="true"><div class="toast-header"><i class="bi bi-envelope-fill me-2"></i><strong class="me-auto" id="toastTitle">Bildirim</strong><small>Şimdi</small><button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button></div><div class="custom-toast-body" id="toastBody"></div></div></div>
<audio id="adminSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3"></audio>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let audioContextUnlocked = false;
    function initSound() { if(!audioContextUnlocked) { document.getElementById('adminSound').play().then(()=>{ document.getElementById('adminSound').pause(); audioContextUnlocked=true; }).catch(()=>{}); } }
    const editModal = document.getElementById('editUserModal');
    if (editModal) { editModal.addEventListener('show.bs.modal', event => { const button = event.relatedTarget; document.getElementById('modal_uid').value = button.getAttribute('data-id'); document.getElementById('modal_store').value = button.getAttribute('data-store'); document.getElementById('modal_user').value = button.getAttribute('data-user'); document.getElementById('modal_email').value = button.getAttribute('data-email'); document.getElementById('modal_pass').value = ""; }); }
    const editAnnModal = document.getElementById('editAnnModal');
    if (editAnnModal) { editAnnModal.addEventListener('show.bs.modal', event => { const button = event.relatedTarget; document.getElementById('modal_ann_id').value = button.getAttribute('data-id'); document.getElementById('modal_ann_title').value = button.getAttribute('data-title'); document.getElementById('modal_ann_msg').value = button.getAttribute('data-message'); document.getElementById('modal_ann_start').value = button.getAttribute('data-start'); document.getElementById('modal_ann_end').value = button.getAttribute('data-end'); }); }
    const msgToast = new bootstrap.Toast(document.getElementById('msgToast'));
    let lastMsgId = 0;
    document.getElementById('msgToast').addEventListener('click', function() { window.location.href = 'support.php'; });
    function checkNotifications() { fetch('api_notifications.php?t='+Date.now()).then(r => r.json()).then(data => { if(data.status === 'new') { document.getElementById('msgBadge').classList.remove('d-none'); if(data.msg_id > lastMsgId) { lastMsgId = data.msg_id; document.getElementById('toastTitle').innerText = data.title; document.getElementById('toastBody').innerText = data.text; msgToast.show(); if(audioContextUnlocked) document.getElementById('adminSound').play().catch(()=>{}); } } }).catch(()=>{}); }
    setInterval(checkNotifications, 5000); checkNotifications();
</script>
</body>
</html>
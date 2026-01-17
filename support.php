<?php
// support.php - PROFESYONEL BİLET SİSTEMİ
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$my_id = $_SESSION['user_id'];
$my_role = $_SESSION['role'];

// --- İŞLEMLER ---

// 1. YENİ BİLET OLUŞTUR (Sadece Restoran)
if (isset($_POST['create_ticket'])) {
    $subject = htmlspecialchars($_POST['subject']);
    $message = htmlspecialchars($_POST['message']);
    
    if ($subject && $message) {
        // Bileti oluştur
        $pdo->prepare("INSERT INTO support_tickets (user_id, subject, status) VALUES (?, ?, 'open')")->execute([$my_id, $subject]);
        $ticket_id = $pdo->lastInsertId();
        // İlk mesajı ekle
        $pdo->prepare("INSERT INTO support_messages (ticket_id, sender_id, message) VALUES (?, ?, ?)")->execute([$ticket_id, $my_id, $message]);
        header("Location: support.php?view_ticket=".$ticket_id); exit;
    }
}

// 2. CEVAP YAZ
if (isset($_POST['reply_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    $message = htmlspecialchars($_POST['message']);
    
    if ($message) {
        $pdo->prepare("INSERT INTO support_messages (ticket_id, sender_id, message) VALUES (?, ?, ?)")->execute([$ticket_id, $my_id, $message]);
        // Bileti güncelle (Son işlem zamanı değişsin)
        $pdo->prepare("UPDATE support_tickets SET updated_at = NOW(), status = 'open' WHERE id = ?")->execute([$ticket_id]);
    }
    header("Location: support.php?view_ticket=".$ticket_id); exit;
}

// 3. BİLETİ KAPAT (Çözüldü İşaretle)
if (isset($_GET['close_ticket'])) {
    $tid = $_GET['close_ticket'];
    // Yetki kontrolü (Bileti sahibi veya admin kapatabilir)
    if ($my_role == 'admin') {
        $pdo->prepare("UPDATE support_tickets SET status = 'closed' WHERE id = ?")->execute([$tid]);
    } else {
        $pdo->prepare("UPDATE support_tickets SET status = 'closed' WHERE id = ? AND user_id = ?")->execute([$tid, $my_id]);
    }
    header("Location: support.php?view_ticket=".$tid); exit;
}

// --- GÖRÜNÜM ---
$view_ticket_id = isset($_GET['view_ticket']) ? $_GET['view_ticket'] : null;
$create_mode = isset($_GET['new']);

// Geri Dönüş Linki
$back_link = ($my_role == 'admin') ? 'admin.php' : 'panel.php';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Destek Biletleri</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .ticket-card { transition: all 0.2s; border-left: 5px solid transparent; cursor: pointer; }
        .ticket-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .ticket-open { border-left-color: #28a745; }
        .ticket-closed { border-left-color: #6c757d; background-color: #f8f9fa; opacity: 0.8; }
        .chat-bubble { padding: 15px; border-radius: 15px; position: relative; display: inline-block; max-width: 80%; }
        .chat-me { background-color: #007bff; color: white; float: right; border-bottom-right-radius: 0; }
        .chat-other { background-color: #ffffff; color: #333; float: left; border-bottom-left-radius: 0; border: 1px solid #ddd; }
        .chat-container { overflow-y: auto; height: 60vh; padding: 20px; display: flex; flex-direction: column; gap: 15px; }
        .ticket-header { background: white; padding: 15px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container">
        <div class="d-flex align-items-center">
            <a href="<?php echo $back_link; ?>" class="btn btn-outline-light btn-sm me-3"><i class="bi bi-arrow-left"></i> Panel</a>
            <span class="navbar-brand mb-0 h1">Destek Merkezi</span>
        </div>
        <?php if($my_role != 'admin' && !$create_mode && !$view_ticket_id): ?>
            <a href="?new=1" class="btn btn-success"><i class="bi bi-plus-lg"></i> Yeni Bilet</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">

    <?php if($create_mode): ?>
    <div class="card shadow-sm mx-auto" style="max-width: 600px;">
        <div class="card-header bg-success text-white fw-bold">Yeni Destek Talebi</div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-bold">Konu Başlığı</label>
                    <input type="text" name="subject" class="form-control" placeholder="Örn: Menü güncelleme sorunu" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Sorununuz / Mesajınız</label>
                    <textarea name="message" class="form-control" rows="5" required></textarea>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="support.php" class="btn btn-secondary">İptal</a>
                    <button type="submit" name="create_ticket" class="btn btn-success px-4">Talebi Gönder</button>
                </div>
            </form>
        </div>
    </div>

    <?php elseif($view_ticket_id): 
        // Bileti Çek
        $t_sql = "SELECT t.*, u.store_name, u.username FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?";
        $t_stmt = $pdo->prepare($t_sql);
        $t_stmt->execute([$view_ticket_id]);
        $ticket = $t_stmt->fetch();

        // Güvenlik: Başkasının biletini görmesin (Admin hariç)
        if (!$ticket || ($my_role != 'admin' && $ticket['user_id'] != $my_id)) {
            echo "<div class='alert alert-danger'>Bu bileti görüntüleme yetkiniz yok.</div>"; exit;
        }

        // Mesajları Çek ve Okundu Yap
        $m_sql = "SELECT * FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC";
        $m_stmt = $pdo->prepare($m_sql);
        $m_stmt->execute([$view_ticket_id]);
        $msgs = $m_stmt->fetchAll();

        // Okundu işaretle (Bana gelenleri)
        $pdo->prepare("UPDATE support_messages SET is_read = 1 WHERE ticket_id = ? AND sender_id != ?")->execute([$view_ticket_id, $my_id]);
    ?>
    <div class="card shadow-sm">
        <div class="ticket-header">
            <div>
                <h5 class="mb-1">
                    <?php if($ticket['status'] == 'closed'): ?><span class="badge bg-secondary me-2">KAPALI</span>
                    <?php else: ?><span class="badge bg-success me-2">AÇIK</span><?php endif; ?>
                    <?php echo htmlspecialchars($ticket['subject']); ?>
                </h5>
                <small class="text-muted">
                    Gönderen: <strong><?php echo htmlspecialchars($ticket['store_name']); ?></strong> | 
                    Tarih: <?php echo date("d.m.Y H:i", strtotime($ticket['created_at'])); ?>
                </small>
            </div>
            <div>
                <?php if($ticket['status'] == 'open'): ?>
                    <a href="?close_ticket=<?php echo $ticket['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Bileti kapatmak istediğinize emin misiniz?')">Bileti Kapat</a>
                <?php else: ?>
                    <button class="btn btn-secondary btn-sm" disabled>Çözüldü</button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card-body bg-light chat-container" id="chatBox">
            <?php foreach($msgs as $msg): 
                $is_me = ($msg['sender_id'] == $my_id);
                $sender_display = $is_me ? 'Ben' : ($my_role == 'admin' ? $ticket['store_name'] : 'Destek Ekibi');
            ?>
            <div class="clearfix">
                <div class="chat-bubble shadow-sm <?php echo $is_me ? 'chat-me' : 'chat-other'; ?>">
                    <div class="fw-bold small mb-1 text-warning"><?php echo $sender_display; ?></div>
                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    <div class="text-end mt-1" style="font-size: 0.7rem; opacity: 0.8;">
                        <?php echo date("H:i", strtotime($msg['created_at'])); ?>
                        <?php if($is_me): ?><i class="bi bi-check2-all"></i><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if($ticket['status'] == 'open'): ?>
        <div class="card-footer bg-white">
            <form method="post" class="d-flex gap-2">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                <textarea name="message" class="form-control" rows="1" placeholder="Cevabınızı yazın..." required></textarea>
                <button type="submit" name="reply_ticket" class="btn btn-primary"><i class="bi bi-send-fill"></i></button>
            </form>
        </div>
        <?php else: ?>
            <div class="card-footer text-center text-muted fst-italic">Bu bilet kapatılmıştır. Yeni bir bilet oluşturabilirsiniz.</div>
        <?php endif; ?>
    </div>

    <script>
        var chatBox = document.getElementById('chatBox');
        chatBox.scrollTop = chatBox.scrollHeight;
    </script>

    <?php else: ?>
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white fw-bold">
            <?php echo ($my_role == 'admin') ? 'Tüm Destek Talepleri' : 'Biletlerim'; ?>
        </div>
        <div class="list-group list-group-flush">
            <?php
            if ($my_role == 'admin') {
                $tickets = $pdo->query("SELECT t.*, u.store_name, (SELECT count(*) FROM support_messages WHERE ticket_id = t.id AND is_read = 0 AND sender_id != $my_id) as unread FROM support_tickets t JOIN users u ON t.user_id = u.id ORDER BY t.updated_at DESC")->fetchAll();
            } else {
                $tickets = $pdo->prepare("SELECT t.*, (SELECT count(*) FROM support_messages WHERE ticket_id = t.id AND is_read = 0 AND sender_id != ?) as unread FROM support_tickets t WHERE t.user_id = ? ORDER BY t.updated_at DESC");
                $tickets->execute([$my_id, $my_id]);
                $tickets = $tickets->fetchAll();
            }

            if (count($tickets) == 0) { echo "<div class='p-4 text-center text-muted'>Henüz destek talebi yok.</div>"; }

            foreach($tickets as $t): 
                $status_class = ($t['status'] == 'open') ? 'ticket-open' : 'ticket-closed';
                $badge = ($t['status'] == 'open') ? '<span class="badge bg-success">Açık</span>' : '<span class="badge bg-secondary">Kapalı</span>';
                $unread_badge = ($t['unread'] > 0) ? '<span class="badge bg-danger rounded-pill ms-2">'.$t['unread'].' Yeni</span>' : '';
            ?>
            <a href="?view_ticket=<?php echo $t['id']; ?>" class="list-group-item list-group-item-action ticket-card <?php echo $status_class; ?> p-3">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1 fw-bold text-dark">
                        <?php if($my_role == 'admin'): ?><span class="text-primary me-2">[<?php echo htmlspecialchars($t['store_name']); ?>]</span><?php endif; ?>
                        <?php echo htmlspecialchars($t['subject']); ?>
                        <?php echo $unread_badge; ?>
                    </h6>
                    <small class="text-muted"><?php echo date("d.m.Y H:i", strtotime($t['updated_at'])); ?></small>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <small class="text-muted">Bilet #<?php echo $t['id']; ?></small>
                    <?php echo $badge; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
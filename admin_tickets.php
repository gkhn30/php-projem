<?php
session_start();
include 'db.php';
// Güvenlik
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit; }

// --- CEVAP VERME ---
if (isset($_POST['reply_ticket'])) {
    $t_id = $_POST['ticket_id'];
    $msg = htmlspecialchars($_POST['message']);
    
    $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_role, message) VALUES (?, 'admin', ?)")->execute([$t_id, $msg]);
    $pdo->prepare("UPDATE tickets SET updated_at = NOW(), status = 'acik' WHERE id = ?")->execute([$t_id]);
    
    header("Location: admin_tickets.php?view=" . $t_id); exit;
}

// --- KAPATMA / AÇMA ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $status = ($_GET['action'] == 'close') ? 'kapali' : 'acik';
    $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?")->execute([$status, $_GET['id']]);
    header("Location: admin_tickets.php?view=" . $_GET['id']); exit;
}

// --- DETAY ---
$view_ticket = null;
if (isset($_GET['view'])) {
    $t_id = $_GET['view'];
    
    // Kullanıcı mesajlarını okundu yap
    $pdo->prepare("UPDATE ticket_messages SET is_read = 1 WHERE ticket_id = ? AND sender_role = 'user'")->execute([$t_id]);
    
    // Bilet bilgisi + Kullanıcı Bilgisi
    $stmt = $pdo->prepare("SELECT t.*, u.store_name, u.username, u.email FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $stmt->execute([$t_id]);
    $view_ticket = $stmt->fetch();
    
    // Mesajlar
    $msgs = $pdo->prepare("SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC");
    $msgs->execute([$t_id]);
    $messages = $msgs->fetchAll();
}

// Tüm Biletler (Okunmamışlar üstte)
$sql = "SELECT t.*, u.store_name, 
        (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id AND m.sender_role = 'user' AND m.is_read = 0) as unread_count
        FROM tickets t JOIN users u ON t.user_id = u.id 
        ORDER BY unread_count DESC, t.updated_at DESC";
$all_tickets = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Destek Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary mb-4">
    <div class="container">
        <span class="navbar-brand">Destek Yönetimi</span>
        <a href="admin.php" class="btn btn-dark btn-sm">Ana Panele Dön</a>
    </div>
</nav>

<div class="container">
    <div class="row">
        <div class="col-md-4" style="height: 80vh; overflow-y: auto;">
            <div class="list-group">
                <?php foreach($all_tickets as $tick): ?>
                    <?php $activeClass = (isset($_GET['view']) && $_GET['view'] == $tick['id']) ? 'active' : ''; ?>
                    <a href="admin_tickets.php?view=<?php echo $tick['id']; ?>" class="list-group-item list-group-item-action <?php echo $activeClass; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($tick['store_name']); ?></h6>
                            <?php if($tick['unread_count'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $tick['unread_count']; ?> Yeni</span>
                            <?php endif; ?>
                        </div>
                        <p class="mb-1 small text-truncate"><?php echo htmlspecialchars($tick['subject']); ?></p>
                        <small class="text-muted"><?php echo ($tick['status']=='acik') ? 'Açık' : 'Kapalı'; ?> - <?php echo date("d.m H:i", strtotime($tick['updated_at'])); ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-md-8">
            <?php if($view_ticket): ?>
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($view_ticket['store_name']); ?></strong> 
                            <small>(<?php echo htmlspecialchars($view_ticket['email']); ?>)</small>
                            <br>Konu: <?php echo htmlspecialchars($view_ticket['subject']); ?>
                        </div>
                        <div>
                            <?php if($view_ticket['status'] == 'acik'): ?>
                                <a href="admin_tickets.php?action=close&id=<?php echo $view_ticket['id']; ?>" class="btn btn-outline-danger btn-sm">Bileti Kapat</a>
                            <?php else: ?>
                                <span class="badge bg-secondary me-2">Kapalı</span>
                                <a href="admin_tickets.php?action=open&id=<?php echo $view_ticket['id']; ?>" class="btn btn-outline-success btn-sm">Tekrar Aç</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body overflow-auto" style="height: 400px; background: #f8f9fa;">
                        <?php foreach($messages as $m): ?>
                            <div class="d-flex mb-3 <?php echo ($m['sender_role']=='admin') ? 'justify-content-end' : 'justify-content-start'; ?>">
                                <div class="p-3 rounded shadow-sm" style="max-width: 75%; background-color: <?php echo ($m['sender_role']=='admin') ? '#dcf8c6' : '#fff'; ?>;">
                                    <small class="d-block text-muted mb-1">
                                        <?php echo ($m['sender_role']=='admin') ? 'Siz' : htmlspecialchars($view_ticket['username']); ?>
                                    </small>
                                    <?php echo nl2br(htmlspecialchars($m['message'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="card-footer bg-white">
                        <form method="post">
                            <input type="hidden" name="ticket_id" value="<?php echo $view_ticket['id']; ?>">
                            <div class="input-group">
                                <textarea name="message" class="form-control" rows="2" placeholder="Cevap yazın..." required></textarea>
                                <button type="submit" name="reply_ticket" class="btn btn-primary">Gönder</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">İşlem yapmak için soldan bir bilet seçin.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
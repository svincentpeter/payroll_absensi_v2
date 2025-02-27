<?php
// File: pesan.php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/koneksi.php';
start_session_safe();

// Pastikan user sudah login
if (!isset($_SESSION['id'])) {
    header("Location: /login.php");
    exit();
}

$userId   = $_SESSION['id'];
$userRole = $_SESSION['role'] ?? '';

// Query untuk mengambil pesan yang dikirim atau diterima oleh user
$sql = "SELECT ls.*, 
               sender.nama AS sender_name, 
               receiver.nama AS receiver_name 
        FROM laporan_surat ls 
        LEFT JOIN anggota_sekolah sender ON ls.id_pengirim = sender.id 
        LEFT JOIN anggota_sekolah receiver ON ls.id_penerima = receiver.id 
        WHERE ls.id_pengirim = ? OR ls.id_penerima = ? 
        ORDER BY ls.tanggal_keluar DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pesan Surat</title>
  <!-- FontAwesome CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-dC3e6uJll3WpvW1cz5qXfOzue+1t8J0d1Y1+e2kF/4t52uY1oD5UVpZ4KbbV84JxK9a6zTni6ZHBW6+0fllpNQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <!-- Bootstrap 5.3.3 CSS CDN -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUa6mYv+0cQKXH5Dk8ROJ0R2fzuy+kFsv+u78S5cRYPFfzqF4A/2P5F06F1p" crossorigin="anonymous">
</head>
<body>
  <?php include 'navbar.php'; ?>
  <div class="container my-4">
    <h1>Pesan Surat</h1>
    <?php if(empty($messages)): ?>
      <div class="alert alert-info">Tidak ada pesan surat.</div>
    <?php else: ?>
      <div class="list-group">
        <?php foreach($messages as $message): ?>
          <a href="<?= BASE_URL ?>/pesan_detail.php?id=<?= $message['id'] ?>" class="list-group-item list-group-item-action">
            <div class="d-flex w-100 justify-content-between">
              <h5 class="mb-1"><?= htmlspecialchars($message['judul']) ?></h5>
              <small><?= date('d M Y H:i', strtotime($message['tanggal_keluar'])) ?></small>
            </div>
            <p class="mb-1"><?= htmlspecialchars(substr($message['isi'], 0, 100)) ?>...</p>
            <small>
              <?php if($message['id_penerima'] == $userId): ?>
                Dari: <?= htmlspecialchars($message['sender_name']) ?>
              <?php else: ?>
                Kepada: <?= htmlspecialchars($message['receiver_name']) ?>
              <?php endif; ?>
            </small>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <!-- Bootstrap 5.3.3 JS Bundle CDN -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoOvZHnNQzYfC0RL5jJ5enq+QcPlj3x1p4cW4Md7o8Lk8UR" crossorigin="anonymous"></script>
</body>
</html>

<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ./php/login.php"); // ถ้ายังไม่ได้ล็อกอิน ให้กลับไปหน้าล็อกอิน
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลักผู้ดูแลระบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>ยินดีต้อนรับสู่หน้าหลักผู้ดูแลระบบ</h1>
        <p>คุณล็อกอินในฐานะ: <strong><?php echo htmlspecialchars($_SESSION['user_id'] ?? 'Guest'); ?></strong></p>
        <p>บทบาท: <strong><?php echo htmlspecialchars($_SESSION['role'] ?? 'N/A'); ?></strong></p>
        <a href="./php/logout.php" class="btn btn-danger">ออกจากระบบ</a>
    </div>
</body>
</html>
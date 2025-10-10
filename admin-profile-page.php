<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login-page.php");
    exit();
}

$user_id = htmlspecialchars($_SESSION['staff_id'] ?? null);
$staff_name = htmlspecialchars($_SESSION['user_display_name'] ?? null);
$staff_sur = htmlspecialchars($_SESSION['user_display_sur'] ?? null);
$user_role = htmlspecialchars($_SESSION['role'] ?? null);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'header.php'?>
</head>
<body>
    <nav class="navbar navbar-dark navigator">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3">
                <a href="admin-data_view-page.php">
                    <img src="./images/logo.png" class="img-fluid logo" alt="Logo">
                </a>
                <div class="d-flex flex-column">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4">ระบบขอใช้อุปกรณ์และสถานที่</span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5">KU FTD</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mx-auto">
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="admin-main-page.php">การจัดการระบบ</a>
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="admin-data_view-page.php">ตรวจสอบอุปกรณ์และสถานที่</a>
            </div>
            <div class="d-flex align-items-center ms-auto gap-2">
                <div class="d-flex flex-column text-end">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4"><?php echo $staff_name . ' ' . $staff_sur; ?></span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5"><?php echo $user_role; ?></span>
                </div>
                <img src="./images/user_button.png" class="img-fluid logo" style="width:40px; height:40px; object-fit:cover;" alt="User Button">
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="profile-card">
                    <h2 class="mb-4">ข้อมูลผู้ใช้</h2>
                    <h4 class="mb-4">KU-LDAP-NONTRI-ACCOUNT</h4>
                    <div class="profile-info">
                        <p><strong>ชื่อผู้ใช้:</strong> <?php echo $user_id; ?></p>
                        <p><strong>ชื่อ (ไทย):</strong> <?php echo $staff_name; ?></p>
                        <p><strong>นามสกุล (ไทย):</strong> <?php echo $staff_sur; ?></p>
                        <p><strong>ประเภทผู้ใช้:</strong> <?php echo $user_role; ?></p>
                    </div>
                    <hr>
                        <a href="./php/logout.php" class="btn btn-danger mt-3">ออกจากระบบ</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
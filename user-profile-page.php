<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login-page.php");
    exit();
}

$nontri_id = htmlspecialchars($_SESSION['nontri_id'] ?? ($_SESSION['staff_id'] ?? 'N/A')); 
$user_name = htmlspecialchars($_SESSION['user_display_name'] ?? 'N/A');
$user_sur = htmlspecialchars($_SESSION['user_display_sur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$fa_de_name = htmlspecialchars($_SESSION['fa_de_name'] ?? 'ไม่ระบุ');

$user_position = htmlspecialchars($_SESSION['position'] ?? ''); 
$user_dept = htmlspecialchars($_SESSION['dept'] ?? ''); 

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
                <a href="user-project-page.php">
                    <img src="./images/logo.png" class="img-fluid logo" alt="Logo">
                </a>
                <div class="d-flex flex-column">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4">ระบบขอใช้อุปกรณ์และสถานที่</span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5">KU FTD</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mx-auto">
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="user-project-page.php">ข้อมูลคำร้อง</a>
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="user-data_view-page.php">ตรวจสอบอุปกรณ์และสถานที่</a>
            </div>
            <div class="d-flex align-items-center ms-auto gap-2">
                <div class="d-flex flex-column text-end">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4"><?php echo $user_name . ' ' . $user_sur; ?></span>
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
                        <p><strong>ชื่อผู้ใช้:</strong> <?php echo $nontri_id; ?></p>
                        <p><strong>ชื่อ (ไทย):</strong> <?php echo $user_name; ?></p>
                        <p><strong>นามสกุล (ไทย):</strong> <?php echo $user_sur; ?></p>
                        <p><strong>ประเภทผู้ใช้:</strong> <?php echo $user_role; ?></p>
                        
                        <!-- *** เพิ่มเงื่อนไขการแสดงผลสำหรับ ตำแหน่ง และ แผนก *** -->
                        <?php if (!empty($user_position)): ?>
                            <p><strong>ตำแหน่ง:</strong> <?php echo $user_position; ?></p>
                        <?php endif; ?>

                        <?php if (!empty($user_dept)): ?>
                            <p><strong>แผนก:</strong> <?php echo $user_dept; ?></p>
                        <?php endif; ?>
                        
                        <p><strong>หน่วยงาน/คณะ:</strong> <?php echo $fa_de_name; ?></p>
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
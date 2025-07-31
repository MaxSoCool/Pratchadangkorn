<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login-page.php");
    exit();
}

$user_id = htmlspecialchars($_SESSION['user_id'] ?? 'N/A');
$staff_THname = htmlspecialchars($_SESSION['staff_THname'] ?? 'N/A');
$staff_THsur = htmlspecialchars($_SESSION['staff_THsur'] ?? 'N/A');
$staff_ENname = htmlspecialchars($_SESSION['staff_ENname'] ?? 'N/A');
$staff_ENsur = htmlspecialchars($_SESSION['staff_ENsur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="style.css" rel="stylesheet">
    <title>ระบบขอใช้อุปกรณ์และสถานที่ KU FTD</title>

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
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="#">การจัดการระบบ</a>
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="admin-data_view-page.php">ตรวจสอบอุปกรณ์และสถานที่</a>
            </div>
            <div class="d-flex align-items-center ms-auto gap-2">
                <div class="d-flex flex-column text-end">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4"><?php echo $staff_THname . ' ' . $staff_THsur; ?></span>
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
                    <h2 class="mb-4">ข้อมูลผู้ใช้ KU-ALL</h2>
                    <div class="profile-info">
                        <p><strong>ชื่อผู้ใช้:</strong> <?php echo $user_id; ?></p>
                        <p><strong>ชื่อ (ไทย):</strong> <?php echo $staff_THname; ?></p>
                        <p><strong>นามสกุล (ไทย):</strong> <?php echo $staff_THsur; ?></p>
                        <p><strong>ชื่อ (อังกฤษ):</strong> <?php echo $staff_ENname; ?></p>
                        <p><strong>นามสกุล (อังกฤษ):</strong> <?php echo $staff_ENsur; ?></p>
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
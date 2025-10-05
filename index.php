<?php
    include 'database/database.php';

    $is_admin = false;
    $is_logged_in = false; 

    $mode = $_GET['mode'] ?? 'buildings';
    $errors = []; 
    $success_message = '';

    include 'php/data_view.php'; 
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'header.php'?>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/th.js'></script>
</head>
<body>
    <nav class="navbar navbar-dark navigator">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3">
                <img src="./images/logo.png" class="img-fluid logo" alt="Logo">
                <div class="d-flex flex-column">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4">ระบบขอใช้อุปกรณ์และสถานที่</span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5">KU FTD</span>
                </div>
            </div>
            <div class="d-flex align-items-center ms-auto">
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="login-page.php">เข้าสู่ระบบ</a>
                <a href="login-page.php">
                    <img src="./images/user_button.png" class="img-fluid logo" style="width:40px; height:40px; object-fit:cover;" alt="User Button">
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-0">
        <?php include 'php/data_view-display.php'; ?>
    </div>

    <?php 
        if (isset($conn)) {
            $conn->close();
        }
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="./js/calendar.js"></script>
</body>
</html>
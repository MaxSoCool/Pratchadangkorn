<?php
    session_start();

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: login-page.php");
        exit();
    }

    include 'database/database.php';

    // --- Flags for Shared Logic ---
    $is_admin = true;
    $is_logged_in = true;

    // --- Page Logic Configuration (common for data view) ---
    $mode = $_GET['mode'] ?? 'buildings';
    $errors = []; // Initialize for shared logic
    $success_message = $_GET['message'] ?? ''; // Admin page still uses this for success/error messages

    include 'php/data_view.php'; // This will handle data fetching and define many variables

    $staff_name = htmlspecialchars($_SESSION['user_display_name'] ?? 'N/A');
    $staff_sur = htmlspecialchars($_SESSION['user_display_sur'] ?? 'N/A');
    $user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'header.php'; ?>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/th.js'></script>
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
                <a href="admin-profile-page.php">
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
    <?php if ($is_admin): ?>
        <script src="./js/admin-modal.js"></script>
    <?php endif; ?>
</body>
</html>
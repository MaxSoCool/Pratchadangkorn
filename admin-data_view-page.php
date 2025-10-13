<?php
    session_start();

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: login-page.php");
        exit();
    }

    include 'database/database.php';

    $is_admin = true;
    $is_logged_in = true;

    $mode = $_GET['mode'] ?? 'buildings';
    $errors = [];
    $success_message = $_GET['message'] ?? ''; 

    include 'php/data_view.php';

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
<div class="modal fade" id="genericModal" tabindex="-1" aria-labelledby="genericModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content details-text">
            <div class="modal-header">
                <h5 class="modal-title" id="genericModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" id="genericModalBody">
            </div>
            <div class="modal-footer d-flex justify-content-center" id="genericModalFooter">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script>
    const phpCurrentMode = "<?php echo $mode; ?>";
    const phpCurrentMainTab = "<?php echo $main_tab; ?>"; // อาจจะไม่มีใน admin-data_view-page.php แต่ไม่เป็นไร
</script>
<script src="./js/admin-modal.js"></script>
<script src="./js/building_dropdown.js"></script>
<!-- ไม่ต้องโหลด user-modal.js ที่นี่ -->
<script src="./js/file_upload.js"></script>
</body>
</html>
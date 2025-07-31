<?php
session_start();

include 'database/database.php';
include 'php/image_inject.php';

$user_id = htmlspecialchars($_SESSION['user_id'] ?? 'N/A');
$staff_THname = htmlspecialchars($_SESSION['staff_THname'] ?? 'N/A');
$staff_THsur = htmlspecialchars($_SESSION['staff_THsur'] ?? 'N/A');
$staff_ENname = htmlspecialchars($_SESSION['staff_ENname'] ?? 'N/A');
$staff_ENsur = htmlspecialchars($_SESSION['staff_ENsur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1; 
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($step == 1) {
        include 'php/build_inject.php';
    } elseif ($step == 2) {
        include 'php/faci_inject.php';
    } elseif ($step == 3) {
        include 'php/eqp_inject.php';
    }
}

$buildings = [];
if ($conn->ping()) {
    $result_buildings = $conn->query("SELECT building_id, building_name FROM buildings ORDER BY building_name");
    if ($result_buildings) {
        while ($row = $result_buildings->fetch_assoc()) {
            $buildings[] = $row;
        }
    } else {
        $errors[] = "ไม่สามารถดึงข้อมูลอาคารได้: " . $conn->error;
    }
}

$conn->close();

$modal_status = $_GET['status'] ?? '';
$modal_message = $_GET['message'] ?? '';

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Step Form - KU FTD Proto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark navigator">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3">
                <a href="user-main-page.php">
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
                <a href="admin-profile-page.php">
                    <img src="./images/user_button.png" class="img-fluid logo" style="width:40px; height:40px; object-fit:cover;" alt="User Button">
                </a>
            </div>
        </div>
    </nav>
    <div class="container mt-5">
    <h1 class="mb-4 text-center">ระบบบันทึกข้อมูล KU FTD Proto</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading">เกิดข้อผิดพลาด!</h4>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($modal_status == 'success'): ?>
        <div class="alert alert-success" role="alert">
            <h4 class="alert-heading">บันทึกสำเร็จ!</h4>
            <p><?php echo htmlspecialchars($modal_message); ?></p>
            <hr>
            <p class="mb-0"><a href="?step=<?php echo $step; ?>" class="btn btn-primary">เพิ่มข้อมูลอีกครั้งในฟอร์มนี้</a></p>
        </div>
    <?php endif; ?>

        <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header <?php echo ($modal_status == 'success') ? 'bg-success' : 'bg-danger'; ?> text-white">
                        <h5 class="modal-title" id="statusModalLabel"><?php echo ($modal_status == 'success') ? 'สำเร็จ!' : 'ข้อผิดพลาด!'; ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php echo htmlspecialchars($modal_message); ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn <?php echo ($modal_status == 'success') ? 'btn-success' : 'btn-danger'; ?>" data-bs-dismiss="modal">ตกลง</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm p-4">
            <?php if ($step == 1): ?>
                <div class="form-section">
                    <h2 class="mb-4 text-center fw-bold fs-5">สร้างอาคารใหม่ (Buildings)</h2>
                    <form action="?step=1" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="building_id" class="form-label fs-6 fw-bold">หมายเลขอาคาร (Building Number):</label>
                            <input type="text" id="building_id" name="building_id"
                                class="form-control fs-6"
                                value="<?php echo htmlspecialchars($_POST['building_id'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="building_name" class="form-label fs-6 fw-bold">ชื่ออาคาร (Building Name):</label>
                            <input type="text" id="building_name" name="building_name"
                                class="form-control fs-6"
                                value="<?php echo htmlspecialchars($_POST['building_name'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="building_pic" class="form-label fs-6 fw-bold">รูปภาพ (Image):</label>
                            <input type="file" id="building_pic" name="building_pic" accept="image/*" class="form-control fs-6">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold flex-fill">บันทึกข้อมูลอาคาร</button>
                            <a href="admin-data_view-page.php" class="btn btn-danger btn-lg fw-bold flex-fill">กลับ</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($step == 2): ?>
                <div class="form-section">
                    <h2 class="mb-4 text-center fw-bold fs-5">สร้างสถานที่ใหม่ภายในอาคาร (Facilities)</h2>
                    <form action="?step=2<?php if (isset($_GET['building_id'])) echo '&building_id=' . htmlspecialchars($_GET['building_id']); ?>" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="facility_name" class="form-label fs-6 fw-bold">ชื่อสถานที่ (Facility Name):</label>
                            <input type="text" id="facility_name" name="facility_name"
                                class="form-control fs-6"
                                value="<?php echo htmlspecialchars($_POST['facility_name'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="facility_des" class="form-label fs-6 fw-bold">รายละเอียดสถานที่ (Description):</label>
                            <textarea id="facility_des" name="facility_des" rows="4" class="form-control fs-6"><?php echo htmlspecialchars($_POST['facility_des'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="facility_pic" class="form-label fs-6 fw-bold">รูปภาพ (Image):</label>
                            <input type="file" id="facility_pic" name="facility_pic" accept="image/*" class="form-control fs-6">
                        </div>
                        <?php

                        $selected_building_name = '';
                        if (isset($_GET['building_id'])) {
                            foreach ($buildings as $building) {
                                if ($building['building_id'] == $_GET['building_id']) {
                                    $selected_building_name = $building['building_name'];
                                    break;
                                }
                            }
                        }
                        ?>
                        <div class="mb-3">
                            <label class="form-label fs-6 fw-bold">อาคาร:</label>
                            <input type="text" class="form-control fs-6" value="<?php echo htmlspecialchars($selected_building_name); ?>" readonly>
                            <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($_GET['building_id'] ?? ''); ?>">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold flex-fill">บันทึกข้อมูลอาคาร</button>
                            <a href="admin-data_view-page.php" class="btn btn-danger btn-lg fw-bold flex-fill">กลับ</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($step == 3): ?>
                <div class="form-section">
                    <h2 class="mb-4 text-center fw-bold fs-5">สร้างอุปกรณ์ใหม่ (Equipments)</h2>
                    <form action="?step=3" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="equip_name" class="form-label fs-6 fw-bold">ชื่ออุปกรณ์ (Equip Name):</label>
                            <input type="text" id="equip_name" name="equip_name"
                                class="form-control fs-6"
                                value="<?php echo htmlspecialchars($_POST['equip_name'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label fs-6 fw-bold">จำนวน (Quantity):</label>
                            <input type="number" id="quantity" name="quantity" min="1"
                                class="form-control fs-6"
                                value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="measure" class="form-label fs-6 fw-bold">หน่วยวัด (Measure):</label>
                            <input type="text" id="measure" name="measure"
                                class="form-control fs-6"
                                value="<?php echo htmlspecialchars($_POST['measure'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="size" class="form-label fs-6 fw-bold">ขนาด (size):</label>
                            <input type="text" id="size" name="size"
                                class="form-control fs-6"
                                value="<?php echo htmlspecialchars($_POST['size'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="equip_pic" class="form-label fs-6 fw-bold">รูปภาพอุปกรณ์ (equip_pic):</label>
                            <input type="file" id="equip_pic" name="equip_pic" accept="image/*" class="form-control fs-6">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold flex-fill">บันทึกข้อมูลอาคาร</button>
                            <a href="admin-data_view-page.php" class="btn btn-danger btn-lg fw-bold flex-fill">กลับ</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var statusModalElement = document.getElementById('statusModal');
            var statusModal = new bootstrap.Modal(statusModalElement);

            // Check for status parameters in URL
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');

            if (status && message) {
                // Set modal content
                statusModalElement.querySelector('.modal-header').className = 'modal-header ' + (status === 'success' ? 'bg-success' : 'bg-danger') + ' text-white';
                statusModalElement.querySelector('.modal-title').innerText = (status === 'success' ? 'สำเร็จ!' : 'ข้อผิดพลาด!');
                statusModalElement.querySelector('.modal-body').innerText = message;
                statusModalElement.querySelector('.modal-footer .btn').className = 'btn ' + (status === 'success' ? 'btn-success' : 'btn-danger');

                statusModal.show();

                // Clear URL parameters after showing modal (optional, but good practice)
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + window.location.search.replace(/(\?|&)(status|message)=[^&]*/g, '').replace(/^&/, '?');
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    </script>
</body>
</html>
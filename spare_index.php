<?php
session_start();

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ku_ftd_proto";

// กำหนดโฟลเดอร์หลักสำหรับรูปภาพ
$images_base_dir = 'images/';

// กำหนดโฟลเดอร์ย่อยสำหรับอัปโหลดรูปภาพแต่ละประเภท
$buildings_upload_dir = $images_base_dir . 'buildings/';
$facilities_upload_dir = $images_base_dir . 'facilities/';
$equipments_upload_dir = $images_base_dir . 'equipments/';

// ตรวจสอบและสร้างโฟลเดอร์ถ้ายังไม่มี
if (!is_dir($buildings_upload_dir)) {
    mkdir($buildings_upload_dir, 0755, true);
}
if (!is_dir($facilities_upload_dir)) {
    mkdir($facilities_upload_dir, 0755, true);
}
if (!is_dir($equipments_upload_dir)) {
    mkdir($equipments_upload_dir, 0755, true);
}

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success_message = '';

// ฟังก์ชันสำหรับอัปโหลดรูปภาพ (รับ $target_dir แทน $upload_dir)
function uploadImage($file_input_name, $target_dir, &$errors) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
        $file_name = basename($_FILES[$file_input_name]['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = "ประเภทไฟล์รูปภาพสำหรับ " . $file_input_name . " ไม่ถูกต้อง อนุญาตเฉพาะ JPG, JPEG, PNG, GIF";
            return false;
        }

        $new_file_name = uniqid() . '.' . $file_ext;
        $upload_path = $target_dir . $new_file_name; // ใช้ $target_dir ที่ส่งเข้ามา

        if (move_uploaded_file($file_tmp_name, $upload_path)) {
            return $upload_path;
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ " . $file_input_name . ": Error Code " . $_FILES[$file_input_name]['error'];
            return false;
        }
    } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_NO_FILE) {
        $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ " . $file_input_name . ": Error Code " . $_FILES[$file_input_name]['error'];
        return false;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($step == 1) {
        $building_name = trim($_POST['building_name'] ?? '');
        // เรียกใช้ uploadImage ด้วยโฟลเดอร์สำหรับ buildings
        $building_pic_path = uploadImage('building_pic', $buildings_upload_dir, $errors);

        if (empty($building_name)) {
            $errors[] = "กรุณากรอกชื่ออาคาร (Building Name).";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO buildings (building_name, building_pic) VALUES (?, ?)");
            if (!$stmt) {
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $stmt->bind_param("ss", $building_name, $building_pic_path);
                if ($stmt->execute()) {
                    header("Location: ?step=1&status=success");
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลอาคาร: " . $stmt->error;
                    if ($building_pic_path && file_exists($building_pic_path)) {
                        unlink($building_pic_path);
                    }
                }
                $stmt->close();
            }
        }
    } elseif ($step == 2) {
        $facility_name = trim($_POST['facility_name'] ?? '');
        $facility_des = trim($_POST['facility_des'] ?? '');
        $building_id = (int)($_POST['building_id'] ?? 0);
        // เรียกใช้ uploadImage ด้วยโฟลเดอร์สำหรับ facilities
        $facility_pic_path = uploadImage('facility_pic', $facilities_upload_dir, $errors);

        if (empty($facility_name)) {
            $errors[] = "กรุณากรอกชื่อสิ่งอำนวยความสะดวก (Facility Name).";
        }
        if ($building_id === 0) {
            $errors[] = "กรุณาเลือกอาคาร (Building ID).";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO facilities (facility_name, facility_des, facility_pic, building_id) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $stmt->bind_param("sssi", $facility_name, $facility_des, $facility_pic_path, $building_id);
                if ($stmt->execute()) {
                    header("Location: ?step=2&status=success");
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลสิ่งอำนวยความสะดวก: " . $stmt->error;
                    if ($facility_pic_path && file_exists($facility_pic_path)) {
                        unlink($facility_pic_path);
                    }
                }
                $stmt->close();
            }
        }
    } elseif ($step == 3) {
        $equip_name = trim($_POST['equip_name'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $measure = trim($_POST['measure'] ?? '');
        $size = trim($_POST['size'] ?? '');
        // เรียกใช้ uploadImage ด้วยโฟลเดอร์สำหรับ equipments
        $equip_pic_path = uploadImage('equip_pic', $equipments_upload_dir, $errors);

        if (empty($equip_name)) {
            $errors[] = "กรุณากรอกชื่ออุปกรณ์ (Equipment Name).";
        }
        if ($quantity <= 0) {
             $errors[] = "จำนวน (Quantity) ต้องเป็นตัวเลขบวก.";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO equipments (equip_name, quantity, measure, size, equip_pic) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $stmt->bind_param("sisss", $equip_name, $quantity, $measure, $size, $equip_pic_path);
                if ($stmt->execute()) {
                    header("Location: ?step=3&status=success");
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลอุปกรณ์: " . $stmt->error;
                    if ($equip_pic_path && file_exists($equip_pic_path)) {
                        unlink($equip_pic_path);
                    }
                }
                $stmt->close();
            }
        }
    }
}

// ดึงข้อมูลอาคารสำหรับ dropdown ในฟอร์ม Facilities
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

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="style.css" rel="stylesheet">
    <title>ระบบบันทึกข้อมูล KU FTD Proto - ฟอร์ม <?php echo $step; ?></title>

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
            <div class="d-flex flex-wrap gap-3 mx-auto">
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="#">ตรวจสอบอุปกรณ์และสถานที่</a>
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="#">ข้อมูลคำร้อง</a>
            </div>
            <div class="d-flex align-items-center ms-auto gap-2">
                <div class="d-flex flex-column text-end">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4">ปรัชชฎางค์กรณ์ แก้วมณีโชติ</span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5">นิสิต</span>
                </div>
                <a href="index.php">
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

        <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert alert-success" role="alert">
                <h4 class="alert-heading">บันทึกสำเร็จ!</h4>
                <p>บันทึกข้อมูลของฟอร์มนี้เรียบร้อยแล้ว!</p>
                <hr>
                <p class="mb-0"><a href="?step=<?php echo $step; ?>" class="btn btn-primary">เพิ่มข้อมูลอีกครั้งในฟอร์มนี้</a></p>
            </div>
        <?php endif; ?>

        <div class="navigation mb-4 text-center">
            <a href="?step=1" class="btn <?php echo ($step == 1) ? 'btn-primary' : 'btn-outline-primary'; ?> me-2">
                ฟอร์ม 1: Buildings
            </a>
            <a href="?step=2" class="btn <?php echo ($step == 2) ? 'btn-primary' : 'btn-outline-primary'; ?> me-2">
                ฟอร์ม 2: Facilities
            </a>
            <a href="?step=3" class="btn <?php echo ($step == 3) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                ฟอร์ม 3: Equipments
            </a>
        </div>

        <?php if ($step == 1): ?>
            <div class="form-section">
                <h2 class="mb-4">1. ข้อมูลอาคาร (Buildings)</h2>
                <form action="?step=1" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="building_name" class="form-label">ชื่ออาคาร (building_name):</label>
                        <input type="text" class="form-control" id="building_name" name="building_name"
                               value="<?php echo htmlspecialchars($_POST['building_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="building_pic" class="form-label">รูปภาพอาคาร (building_pic):</label>
                        <input type="file" class="form-control" id="building_pic" name="building_pic" accept="image/*">
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-success">บันทึกและถัดไป</button>
                    </div>
                </form>
            </div>
        <?php elseif ($step == 2): ?>
            <div class="form-section">
                <h2 class="mb-4">2. ข้อมูลสิ่งอำนวยความสะดวก (Facilities)</h2>
                <form action="?step=2" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="facility_name" class="form-label">ชื่อสิ่งอำนวยความสะดวก (facility_name):</label>
                        <input type="text" class="form-control" id="facility_name" name="facility_name"
                               value="<?php echo htmlspecialchars($_POST['facility_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="facility_des" class="form-label">รายละเอียดสิ่งอำนวยความสะดวก (facility_des):</label>
                        <textarea class="form-control" id="facility_des" name="facility_des" rows="4"><?php echo htmlspecialchars($_POST['facility_des'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="facility_pic" class="form-label">รูปภาพสิ่งอำนวยความสะดวก (facility_pic):</label>
                        <input type="file" class="form-control" id="facility_pic" name="facility_pic" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label for="building_id" class="form-label">อาคาร (building_id):</label>
                        <select class="form-select" id="building_id" name="building_id" required>
                            <option value="">-- เลือกอาคาร --</option>
                            <?php foreach ($buildings as $building): ?>
                                <option value="<?php echo htmlspecialchars($building['building_id']); ?>"
                                    <?php echo (isset($_POST['building_id']) && $_POST['building_id'] == $building['building_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($building['building_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($buildings)): ?>
                            <p class="text-danger mt-2">ยังไม่มีข้อมูลอาคารในระบบ กรุณาบันทึกข้อมูลอาคารก่อน</p>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="?step=1" class="btn btn-secondary">ย้อนกลับ</a>
                        <button type="submit" class="btn btn-success">บันทึกและถัดไป</button>
                    </div>
                </form>
            </div>
        <?php elseif ($step == 3): ?>
            <div class="form-section">
                <h2 class="mb-4">3. ข้อมูลอุปกรณ์ (Equipments)</h2>
                <form action="?step=3" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="equip_name" class="form-label">ชื่ออุปกรณ์ (equip_name):</label>
                        <input type="text" class="form-control" id="equip_name" name="equip_name"
                               value="<?php echo htmlspecialchars($_POST['equip_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="quantity" class="form-label">จำนวน (quantity):</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1"
                               value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="measure" class="form-label">หน่วยวัด (measure):</label>
                        <input type="text" class="form-control" id="measure" name="measure"
                               value="<?php echo htmlspecialchars($_POST['measure'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="size" class="form-label">ขนาด (size):</label>
                        <input type="text" class="form-control" id="size" name="size"
                               value="<?php echo htmlspecialchars($_POST['size'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="equip_pic" class="form-label">รูปภาพอุปกรณ์ (equip_pic):</label>
                        <input type="file" class="form-control" id="equip_pic" name="equip_pic" accept="image/*">
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="?step=2" class="btn btn-secondary">ย้อนกลับ</a>
                        <button type="submit" class="btn btn-success">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</body>
</html>
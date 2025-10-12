<?php
session_start();

$login_status = $_SESSION['login_status'] ?? '';
$login_message = $_SESSION['login_message'] ?? '';

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
                <a href="index.php">
                    <img src="./images/logo.png" class="img-fluid logo" alt="Logo">
                </a>
                <div class="d-flex flex-column">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4">ระบบขอใช้อุปกรณ์และสถานที่</span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5">KU FTD</span>
                </div>
            </div>

        </div>
    </nav>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow login-form-bg">
                    <div class="card-body">
                        <center><img src="./images/ku_logo.png" class="img-fluid logo" alt="KU Logo"></center>
                        <h5 class="card-title login-form-header">KU LDAP-NONTRI-LOGIN</h5>
                        <form action="./php/login.php" method="POST">
                            <div class="mb-3">
                                <input type="text" class="form-control" id="username" name="username" placeholder="ชื่อผู้ใช้งาน เช่น b6xxxxxxxxx">
                            </div>
                            <div class="mb-3">
                                <input type="password" class="form-control" id="password" name="password" placeholder="รหัสผ่าน">
                            </div>
                            <div class="d-flex justify-content-center">
                                <button type="submit" class="btn w-50 login-btn">เข้าสู่ระบบ</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="loginStatus" value="<?php echo htmlspecialchars($login_status); ?>">
    <input type="hidden" id="loginMessage" value="<?php echo htmlspecialchars($login_message); ?>">

    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">เข้าสู่ระบบสำเร็จ!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="successModalBody">
                    <!-- Message will be injected here by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">เข้าสู่ระบบไม่สำเร็จ!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body details-text" id="errorModalBody">
                    <!-- Message will be injected here by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="./js/login_script.js"></script>

    <?php
    unset($_SESSION['login_status']);
    unset($_SESSION['login_message']);
    ?>
</body>
</html>
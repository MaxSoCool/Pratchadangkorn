<?php
session_start();
include '../database/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
        session_write_close(); 
        header("Location: login.php?status=failed&message=" . urlencode('กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน'));
        exit();
    }

    $logged_in = false;
    $user_role = ''; 

    $sql_user = "SELECT user.user_id, user.user_pass, user_THname, user_THsur, user_ENname, user_ENsur, nontri_id, user_type.user_type_name AS role, faculties_department.fa_de_name FROM user
                 JOIN user_type ON user.user_type_id = user_type.user_type_id
                 JOIN faculties_department ON user.fa_de_id = faculties_department.fa_de_id
                 WHERE user.user_id = ? AND user.user_pass = ?";
    $stmt = $conn->prepare($sql_user);

    if ($stmt) {
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            $row['user_pass'] == $password;
            $logged_in = true;
            $user_data = $row;
        } 
        $stmt->close();
    } else {
        error_log("Failed to prepare statement for user table: " . $conn->error);
    }

    if (!$logged_in) {
        $sql_staff = "SELECT staff.user_id, staff.user_pass, staff.staff_THname, staff.staff_THsur, staff.staff_ENname, staff.staff_ENsur, user_type.user_type_name AS role FROM staff
                      JOIN user_type ON staff.user_type_id = user_type.user_type_id
                      WHERE staff.user_id = ? AND staff.user_pass = ?";
        $stmt = $conn->prepare($sql_staff);
        if ($stmt) {
            $stmt->bind_param("ss", $username, $password);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $row['user_pass'] == $password;
                $logged_in = true;
                $user_data = $row;
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for staff table: " . $conn->error);
        }
    }

    $conn->close();


    if ($logged_in) {

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user_data['user_id'];
        $_SESSION['user_THname'] = $user_data['user_THname'];
        $_SESSION['user_THsur'] = $user_data['user_THsur'];
        $_SESSION['user_ENname'] = $user_data['user_ENname'];
        $_SESSION['user_ENsur'] = $user_data['user_ENsur'];
        $_SESSION['staff_THname'] = $user_data['staff_THname'];
        $_SESSION['staff_THsur'] = $user_data['staff_THsur'];
        $_SESSION['staff_ENname'] = $user_data['staff_ENname'];
        $_SESSION['staff_ENsur'] = $user_data['staff_ENsur'];
        $_SESSION['nontri_id'] = $user_data['nontri_id'];
        $_SESSION['role'] = $user_data['role']; 
        $_SESSION['fa_de_name'] = $user_data['fa_de_name']; 

        if (isset($user_data['role']) && $user_data['role'] == 'เจ้าหน้าที่') {
            header("Location: ../admin-profile-page.php");
            exit();
        } else {
            header("Location: ../user-profile-page.php");
            exit();
        }

    } else {
        $_SESSION['login_status'] = 'failed';
        $_SESSION['login_message'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        header("Location: ../login-page.php");
        exit();
    }

} else {
    header("Location: ../login-page.php");
    exit();
}
?>






?>
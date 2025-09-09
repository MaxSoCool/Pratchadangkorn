<?php
session_start();

include '../database/database.php'; 

const API_ENDPOINT = 'https://verify.csc.ku.ac.th/api/cscapi/ldap/';
const KEY_APP = '1db2648bd3d5251c02cd33fd5080f47c24383d0cc5be27159ec8ac01a133e685';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['login_status'] = 'failed';
        $_SESSION['login_message'] = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
        header("Location: ../login-page.php");
        exit();
    }

    $logged_in = false;
    $user_data = []; 

    // --- 1. พยายาม Login ผ่าน KU LDAP API ---
    $ldap_auth_passed_campus_c = false;
    $ldap_uid = null;
    $ldap_connection_error = false;

    $postData = [
        "keyapp" => KEY_APP,
        "dataset" => "ldap",
        "userid" => $username,
        "pwd" => $password
    ];

    $ch = curl_init(API_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("LDAP cURL error: " . $curl_error);
        $ldap_connection_error = true;
    } elseif ($http_code === 200) {
        $ldap_response = json_decode($response, true);
        if ($ldap_response && isset($ldap_response['status_code']) && $ldap_response['status_code'] === '1') {
            if (isset($ldap_response['data']['campus']) && $ldap_response['data']['campus'] === 'C') {
                $ldap_auth_passed_campus_c = true;
                $ldap_uid = $ldap_response['data']['uid'] ?? $username; // ใช้ UID จาก LDAP หรือ username ถ้าไม่มี
            } else {
                $_SESSION['login_status'] = 'failed';
                $_SESSION['login_message'] = 'ขออภัย การเข้าสู่ระบบนี้สำหรับนิสิตและบุคลากรของวิทยาเขตเฉลิมพระเกียรติ จังหวัดสกลนครเท่านั้น!';
                header("Location: ../login-page.php");
                exit();
            }
        }
    }

    if ($ldap_auth_passed_campus_c) {
        $sql_staff = "SELECT staff_id, staff_THname, staff_THsur, staff_ENname, staff_ENsur, ut.user_type_name AS role FROM staff s
                      JOIN user_type ut ON s.user_type_id = ut.user_type_id
                      WHERE s.staff_id = ?";
        $stmt = $pdo->prepare($sql_staff);
        if ($stmt) {
            $stmt->execute([$ldap_uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = null; // ปิด statement
            if ($row) {
                // พบข้อมูลในตาราง staff (บุคลากร Login ผ่าน LDAP)
                $logged_in = true;
                $user_data = $row;
                $user_data['nontri_id'] = null; 
                $user_data['user_THname'] = $row['staff_THname']; 
                $user_data['user_THsur'] = $row['staff_THsur'];
                $user_data['user_ENname'] = $row['staff_ENname'];
                $user_data['user_ENsur'] = $row['staff_ENsur'];
                $user_data['fa_de_name'] = null;
            } else {

                $sql_user = "SELECT nontri_id, user_THname, user_THsur, user_ENname, user_ENsur, ut.user_type_name AS role, fd.fa_de_name FROM user u
                             JOIN user_type ut ON u.user_type_id = ut.user_type_id
                             JOIN faculties_department fd ON u.fa_de_id = fd.fa_de_id
                             WHERE u.nontri_id = ?";
                $stmt = $pdo->prepare($sql_user);
                if ($stmt) {
                    $stmt->execute([$ldap_uid]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stmt = null; 

                    if ($row) {
                        // พบข้อมูลในตาราง user (ผู้ใช้ทั่วไป Login ผ่าน LDAP)
                        $logged_in = true;
                        $user_data = $row;
                        $user_data['staff_id'] = null; 
                        $user_data['staff_THname'] = null; 
                        $user_data['staff_THsur'] = null;
                        $user_data['staff_ENname'] = null;
                        $user_data['staff_ENsur'] = null;
                    } else {
                        // LDAP สำเร็จ Campus 'C' แต่ไม่พบข้อมูลทั้งในตาราง staff และ user
                        $_SESSION['login_status'] = 'failed';
                        $_SESSION['login_message'] = 'LDAP เข้าสู่ระบบสำเร็จสำหรับวิทยาเขตเฉลิมพระเกียรติ แต่ไม่พบข้อมูลของคุณในระบบภายใน';
                        header("Location: ../login-page.php");
                        exit();
                    }
                } else {
                    error_log("Failed to prepare user statement after LDAP: " . $pdo->errorInfo()[2]);
                    $_SESSION['login_status'] = 'failed';
                    $_SESSION['login_message'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้ (หลัง LDAP)';
                    header("Location: ../login-page.php");
                    exit();
                }
            }
        } else {
            error_log("Failed to prepare staff statement after LDAP: " . $pdo->errorInfo()[2]);
            $_SESSION['login_status'] = 'failed';
            $_SESSION['login_message'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลบุคลากร (หลัง LDAP)';
            header("Location: ../login-page.php");
            exit();
        }

    } else {

        
        $sql_staff_internal = "SELECT staff_id, user_pass, staff_THname, staff_THsur, staff_ENname, staff_ENsur, ut.user_type_name AS role FROM staff s
                               JOIN user_type ut ON s.user_type_id = ut.user_type_id
                               WHERE s.staff_id = ? AND s.user_pass = ?";
        $stmt = $pdo->prepare($sql_staff_internal);
        if ($stmt) {
            $stmt->execute([$username, $password]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = null;

            if ($row) {
                $logged_in = true;
                $user_data = $row;
                $user_data['nontri_id'] = null;
                $user_data['user_THname'] = $row['staff_THname']; 
                $user_data['user_THsur'] = $row['staff_THsur'];
                $user_data['fa_de_name'] = null; 
            } else {
                $_SESSION['login_status'] = 'failed';
                $_SESSION['login_message'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                if ($ldap_connection_error) {
                    $_SESSION['login_message'] .= ' (ไม่สามารถเชื่อมต่อ LDAP ได้)';
                }
                header("Location: ../login-page.php");
                exit();
            }
        } else {
            error_log("Failed to prepare staff statement for internal fallback: " . $pdo->errorInfo()[2]);
            $_SESSION['login_status'] = 'failed';
            $_SESSION['login_message'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลบุคลากร (Fallback)';
            header("Location: ../login-page.php");
            exit();
        }
    }

    if ($logged_in) {
        $_SESSION['logged_in'] = true;
        
        $_SESSION['nontri_id'] = $user_data['nontri_id'] ?? null;
        $_SESSION['staff_id'] = $user_data['staff_id'] ?? null;
        $_SESSION['user_THname'] = $user_data['user_THname'] ?? null;
        $_SESSION['user_THsur'] = $user_data['user_THsur'] ?? null;
        $_SESSION['staff_THname'] = $user_data['staff_THname'] ?? null;
        $_SESSION['staff_THsur'] = $user_data['staff_THsur'] ?? null;
        
        $_SESSION['role'] = $user_data['role'] ?? 'ไม่ระบุ';
        $_SESSION['fa_de_name'] = $user_data['fa_de_name'] ?? 'ไม่ระบุ';

        // เช็ค Role ของผู้ใช้
        if (isset($user_data['role']) && $user_data['role'] == 'เจ้าหน้าที่') {
            header("Location: ../admin-main-page.php");
            exit();
        } else {
            header("Location: ../user-project-page.php"); 
            exit();
        }
    } else {
        // แจ้ง failed login
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
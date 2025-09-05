<?php
session_start();

// --- Include ไฟล์เชื่อมต่อฐานข้อมูล ---
// สมมติว่าไฟล์นี้จะสร้าง $pdo object ที่พร้อมใช้งาน
include '../database/database.php'; 

// --- กำหนดค่า API ของ KU LDAP ---
const API_ENDPOINT = 'https://verify.csc.ku.ac.th/api/cscapi/ldap/';
const KEY_APP = '1db2648bd3d5251c02cd33fd5080f47c24383d0cc5be27159ec8ac01a133e685';

// --- ฟังก์ชันสำหรับแปลงชื่อคณะเป็น ID ---
function getFacultyId($facultyName) {
    switch ($facultyName) {
        case 'ทรัพยากรธรรมชาติและอุตสาหกรรมเกษตร':
            return 1;
        case 'วิทยาศาสตร์และวิศวกรรมศาสตร์':
            return 2;
        case 'ศิลปกรรมศาสตร์และวิทยาการจัดการ':
            return 3;
        case 'สาธารณสุขศาสตร์':
            return 4;
        default:
            return null; // หรือ ID สำหรับคณะที่ไม่รู้จัก
    }
}

// --- ฟังก์ชันสำหรับแปลง ID คณะเป็นชื่อคณะ (สำหรับแสดงผล) ---
function getFacultyNameById($facultyId) {
    switch ($facultyId) {
        case 1:
            return 'ทรัพยากรธรรมชาติและอุตสาหกรรมเกษตร';
        case 2:
            return 'วิทยาศาสตร์และวิศวกรรมศาสตร์';
        case 3:
            return 'ศิลปกรรมศาสตร์และวิทยาการจัดการ';
        case 4:
            return 'สาธารณสุขศาสตร์';
        default:
            return 'ไม่ระบุ';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['login_status'] = 'error';
        $_SESSION['login_message'] = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
        header("Location: ../login-page.php");
        exit();
    }

    $ldap_success = false;
    $ldap_response_data = null;

    // --- 1. พยายาม Login ผ่าน KU LDAP API ---
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
    // สำหรับการใช้งานจริง ควรเพิ่ม CURLOPT_SSL_VERIFYPEER และ CURLOPT_SSL_VERIFYHOST เป็น true
    // แต่ถ้ามีปัญหา SSL ให้ลองตั้งเป็น false (เฉพาะการพัฒนา ไม่ควรใช้ใน Production)
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        $_SESSION['login_status'] = 'error';
        $_SESSION['login_message'] = 'ไม่สามารถเชื่อมต่อกับบริการ LDAP ได้: ' . $curl_error;
        header("Location: ../login-page.php");
        exit();
    }

    if ($http_code === 200) {
        $ldap_response = json_decode($response, true);
        if ($ldap_response && isset($ldap_response['status_code']) && $ldap_response['status_code'] === '1') {
            $ldap_success = true;
            $ldap_response_data = $ldap_response['data'];
        }
    }

    if ($ldap_success) {
        // --- การ Login ผ่าน LDAP สำเร็จ ---
        // ตรวจสอบวิทยาเขต
        if (!isset($ldap_response_data['campus']) || $ldap_response_data['campus'] !== 'C') {
            $_SESSION['login_status'] = 'error';
            $_SESSION['login_message'] = 'ผู้ใช้ไม่ได้มาจากวิทยาเขตกำแพงแสน';
            header("Location: ../login-page.php");
            exit();
        }

        // ตรวจสอบว่า $pdo object พร้อมใช้งานหรือไม่ (มาจาก database.php)
        if (!isset($pdo) || !$pdo) {
             $_SESSION['login_status'] = 'error';
             $_SESSION['login_message'] = 'เกิดข้อผิดพลาดภายใน: ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
             header("Location: ../login-page.php");
             exit();
        }

        $uid = $ldap_response_data['uid'] ?? '';
        $thainame_full = $ldap_response_data['thainame'] ?? '';
        $givenname_en = $ldap_response_data['givenname'] ?? '';
        $position_ldap = $ldap_response_data['position'] ?? null;
        $faculty_ldap = $ldap_response_data['faculty'] ?? '';

        // เตรียมข้อมูลผู้ใช้จาก LDAP
        $thainame_parts = explode(' ', $thainame_full);
        $user_THname = count($thainame_parts) > 1 ? array_shift($thainame_parts) : $thainame_full;
        $user_THsur = count($thainame_parts) > 0 ? implode(' ', $thainame_parts) : '';
        
        $user_ENname = $givenname_en; 
        $user_ENsur = ''; // ไม่มีข้อมูล English surname แยกใน LDAP response ที่ให้มา

        $user_type_id_from_ldap = ($position_ldap !== null && $position_ldap !== '') ? 2 : 1; // 2:นักศึกษา, 1:ผู้ใช้งานทั่วไป
        $fa_de_id_from_ldap = getFacultyId($faculty_ldap);

        try { // ใช้ try-catch สำหรับการดำเนินการกับฐานข้อมูล
            // --- ตรวจสอบในตาราง 'user' ---
            $stmt_user = $pdo->prepare("SELECT nontri_id, user_type_id, fa_de_id FROM user WHERE nontri_id = ?");
            $stmt_user->execute([$uid]);
            $user_db_data = $stmt_user->fetch();

            if ($user_db_data) {
                // พบผู้ใช้ในตาราง 'user'
                $_SESSION['logged_in'] = true;
                $_SESSION['nontri_id'] = $uid;
                $_SESSION['user_THname'] = $user_THname; 
                $_SESSION['user_THsur'] = $user_THsur;   
                $_SESSION['user_ENname'] = $user_ENname; 
                $_SESSION['user_ENsur'] = $user_ENsur;   
                $_SESSION['role'] = ($user_db_data['user_type_id'] == 2) ? 'อาจารย์และบุคลากร' : 'นิสิต'; // ใช้ user_type_id จาก DB
                $_SESSION['fa_de_name'] = getFacultyNameById($user_db_data['fa_de_id']); // ใช้ fa_de_id จาก DB

                $_SESSION['login_status'] = 'success';
                $_SESSION['login_message'] = 'เข้าสู่ระบบสำเร็จ';
                header("Location: ../user-profile-page.php");
                exit();

            } else {
                // ไม่พบในตาราง 'user' ตรวจสอบในตาราง 'staff'
                $stmt_staff = $pdo->prepare("SELECT staff_id, staff_THname, staff_THsur, staff_ENname, staff_ENsur FROM staff WHERE staff_id = ?");
                $stmt_staff->execute([$uid]);
                $staff_db_data = $stmt_staff->fetch();

                if ($staff_db_data) {
                    // พบบุคลากรในตาราง 'staff'
                    // จะ Redirect บุคลากรไปที่หน้าหลัก หรือหน้า Dashboard บุคลากร
                    $_SESSION['logged_in'] = true;
                    $_SESSION['nontri_id'] = $uid; // ใช้ UID เป็น nontri_id ใน session เพื่อความเข้ากันได้
                    $_SESSION['user_THname'] = $staff_db_data['staff_THname'];
                    $_SESSION['user_THsur'] = $staff_db_data['staff_THsur'];
                    $_SESSION['user_ENname'] = $staff_db_data['staff_ENname'];
                    $_SESSION['user_ENsur'] = $staff_db_data['staff_ENsur'];
                    $_SESSION['role'] = 'บุคลากร';
                    $_SESSION['fa_de_name'] = getFacultyNameById($fa_de_id_from_ldap); // ใช้คณะจาก LDAP สำหรับบุคลากร

                    $_SESSION['login_status'] = 'success';
                    $_SESSION['login_message'] = 'เข้าสู่ระบบสำเร็จ (บุคลากร)';
                    header("Location: ../index.php"); // Redirect บุคลากรไปหน้าหลัก (หรือหน้าที่เหมาะสม)
                    exit();

                } else {
                    // ไม่พบทั้งใน 'user' และ 'staff' --> สร้างผู้ใช้ใหม่ในตาราง 'user'
                    $stmt_insert_user = $pdo->prepare(
                        "INSERT INTO user (nontri_id, user_THname, user_THsur, user_ENname, user_ENsur, position, user_type_id, fa_de_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt_insert_user->execute([
                        $uid, 
                        $user_THname, 
                        $user_THsur, 
                        $user_ENname, 
                        $user_ENsur, 
                        $position_ldap, 
                        $user_type_id_from_ldap, 
                        $fa_de_id_from_ldap
                    ]);

                    // ตั้งค่า Session สำหรับผู้ใช้ใหม่
                    $_SESSION['logged_in'] = true;
                    $_SESSION['nontri_id'] = $uid;
                    $_SESSION['user_THname'] = $user_THname;
                    $_SESSION['user_THsur'] = $user_THsur;
                    $_SESSION['user_ENname'] = $user_ENname;
                    $_SESSION['user_ENsur'] = $user_ENsur;
                    $_SESSION['role'] = ($user_type_id_from_ldap == 2) ? 'นักศึกษา' : 'ผู้ใช้งานทั่วไป';
                    $_SESSION['fa_de_name'] = getFacultyNameById($fa_de_id_from_ldap);

                    $_SESSION['login_status'] = 'success';
                    $_SESSION['login_message'] = 'เข้าสู่ระบบสำเร็จและสร้างบัญชีใหม่';
                    header("Location: ../user-profile-page.php");
                    exit();
                }
            }
        } catch (PDOException $e) {
            $_SESSION['login_status'] = 'error';
            $_SESSION['login_message'] = 'ข้อผิดพลาดในการดำเนินการฐานข้อมูล: ' . $e->getMessage();
            header("Location: ../login-page.php");
            exit();
        }

    } else {
        // --- การ Login ผ่าน LDAP ล้มเหลว ---
        // จะลอง Login บุคลากรภายในเป็น Fallback
        if (!isset($pdo) || !$pdo) {
             $_SESSION['login_status'] = 'error';
             $_SESSION['login_message'] = 'เกิดข้อผิดพลาดภายใน (Fallback): ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
             header("Location: ../login-page.php");
             exit();
        }
        
        try {
            // IMPORTANT: สำหรับ 'user_pass' ในตาราง staff โปรดตรวจสอบว่าคุณเก็บรหัสผ่านแบบ Hashed หรือ Plain Text
            // โค้ดนี้สมมติว่าเป็น Plain Text สำหรับการสาธิต แต่ควร Hashing ใน Production
            $stmt_staff_internal = $pdo->prepare("SELECT staff_id, staff_THname, staff_THsur, staff_ENname, staff_ENsur FROM staff WHERE staff_id = ? AND user_pass = ?");
            $stmt_staff_internal->execute([$username, $password]); 
            $internal_staff_data = $stmt_staff_internal->fetch();

            if ($internal_staff_data) {
                // Login บุคลากรภายในสำเร็จ
                $_SESSION['logged_in'] = true;
                $_SESSION['nontri_id'] = $internal_staff_data['staff_id'];
                $_SESSION['user_THname'] = $internal_staff_data['staff_THname'];
                $_SESSION['user_THsur'] = $internal_staff_data['staff_THsur'];
                $_SESSION['user_ENname'] = $internal_staff_data['staff_ENname'];
                $_SESSION['user_ENsur'] = $internal_staff_data['staff_ENsur'];
                $_SESSION['role'] = 'บุคลากร (ภายใน)'; // บทบาทที่แตกต่างสำหรับบุคลากรภายใน
                $_SESSION['fa_de_name'] = 'ไม่ระบุ'; // ข้อมูลคณะอาจไม่ชัดเจนสำหรับบุคลากรภายใน

                $_SESSION['login_status'] = 'success';
                $_SESSION['login_message'] = 'เข้าสู่ระบบสำเร็จ (บุคลากรภายใน)';
                header("Location: ../index.php"); // Redirect บุคลากรภายในไปหน้าหลัก
                exit();
            } else {
                // ทั้ง LDAP และ Login บุคลากรภายในล้มเหลว
                $_SESSION['login_status'] = 'error';
                $_SESSION['login_message'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                header("Location: ../login-page.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['login_status'] = 'error';
            $_SESSION['login_message'] = 'ข้อผิดพลาดในการดำเนินการฐานข้อมูล (Fallback): ' . $e->getMessage();
            header("Location: ../login-page.php");
            exit();
        }
    }
} else {
    // หากไม่ใช่ POST request ให้ Redirect กลับไปหน้า Login
    header("Location: ../login-page.php");
    exit();
}
?>
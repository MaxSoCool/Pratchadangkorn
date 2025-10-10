<?php
session_start();

// ควรปิด display_errors ใน Production Environment เพื่อความปลอดภัย
ini_set('display_errors', 1); // เปิดไว้ดู error ตอนเทส
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../database/database.php'; 

// ==================================================================================
// [TEST MODE] COMMENT ส่วน LDAP และ CONSTANT ออกชั่วคราว
/*
const API_ENDPOINT = 'https://inv.csc.ku.ac.th/cscapi/ldap/';
// === สำคัญ: ควรย้าย KEY_APP ไปที่ไฟล์ .env หรือไฟล์นอก webroot เพื่อความปลอดภัย ===
const KEY_APP = '1db2648bd3d5251c02cd33fd5080f47c24383d0cc5be27159ec8ac01a133e685';
*/
// ==================================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // ในโหมดทดสอบ รหัสผ่านจะไม่ถูกนำไปใช้

    // [TEST MODE] ตรวจสอบแค่ username ก็พอ
    if (empty($username)) {
        $_SESSION['login_status'] = 'failed';
        $_SESSION['login_message'] = 'กรุณากรอกชื่อผู้ใช้ (Test Mode: รหัสผ่านใส่อะไรก็ได้)';
        header("Location: ../login-page.php");
        exit();
    }

    $logged_in = false;
    $user_data = []; 

    // ==================================================================================
    // [TEST MODE] เริ่มส่วน LOGIN ชั่วคราว (บายพาสรหัสผ่าน เช็คทั้งตาราง staff และ user)
    // ==================================================================================
    
    // 1. ลองค้นหาในตาราง staff ก่อน (ใช้ staff_id เพียงอย่างเดียว ไม่ตรวจสอบรหัสผ่าน)
    $sql_test_bypass_staff = "SELECT s.staff_id, s.staff_name, s.staff_sur, 
                                     s.position, s.dept,
                                     ut.user_type_name AS role
                              FROM staff s
                              JOIN user_type ut ON s.user_type_id = ut.user_type_id
                              WHERE s.staff_id = ?";
    
    $stmt_staff = $pdo->prepare($sql_test_bypass_staff);
    if ($stmt_staff) {
        $stmt_staff->execute([$username]);
        $row_staff = $stmt_staff->fetch(PDO::FETCH_ASSOC);
        $stmt_staff = null;

        if ($row_staff) {
            // พบผู้ใช้ในตาราง staff
            $logged_in = true;
            $user_data = $row_staff;
            
            // จัดเตรียมข้อมูลให้เหมือนโครงสร้างเดิม เพื่อให้ Session ข้างล่างทำงานได้
            $user_data['nontri_id'] = null; // บุคลากรจะไม่มี nontri_id
            $user_data['user_name'] = $row_staff['staff_name']; // ใช้ staff_name สำหรับ key 'user_name' ใน session
            $user_data['user_sur'] = $row_staff['staff_sur'];   // ใช้ staff_sur สำหรับ key 'user_sur' ใน session
        }
    }

    // 2. ถ้ายังไม่ Login (ไม่พบในตาราง staff) ให้ลองค้นหาในตาราง user (ใช้ nontri_id เพียงอย่างเดียว)
    if (!$logged_in) {
        $sql_test_bypass_user = "SELECT u.nontri_id, u.user_name, u.user_sur,
                                 u.position, u.dept, u.fa_de_id,
                                 ut.user_type_name AS role, fd.fa_de_name 
                            FROM user u
                            JOIN user_type ut ON u.user_type_id = ut.user_type_id
                            JOIN faculties_department fd ON u.fa_de_id = fd.fa_de_id
                            WHERE u.nontri_id = ?";
        
        $stmt_user = $pdo->prepare($sql_test_bypass_user);
        if ($stmt_user) {
            $stmt_user->execute([$username]);
            $row_user = $stmt_user->fetch(PDO::FETCH_ASSOC);
            $stmt_user = null;

            if ($row_user) {
                // พบผู้ใช้ในตาราง user
                $logged_in = true;
                $user_data = $row_user;
                
                // จัดเตรียมข้อมูลให้เหมือนโครงสร้างเดิม
                $user_data['staff_id'] = null; // ผู้ใช้ทั่วไปจะไม่มี staff_id
                // user_name, user_sur, role, fa_de_name มีอยู่ใน $row_user แล้วจากการ query
            }
        }
    }

    // ==================================================================================
    // [TEST MODE] สิ้นสุดส่วน LOGIN ชั่วคราว
    // ==================================================================================


    // ==================================================================================
    // [TEST MODE] COMMENT ปิดระบบ LDAP และ Logic เดิมทั้งหมดด้านล่างนี้
    // ... (ส่วนที่ถูก Comment ปิดยาวไปก่อนหน้านี้) ...
    // ==================================================================================


    // --- ส่วนตั้งค่า SESSION (คงไว้เหมือนเดิมเพื่อให้ระบบทำงานต่อได้) ---
    if ($logged_in) {
        $_SESSION['logged_in'] = true;
        
        $_SESSION['nontri_id'] = $user_data['nontri_id'] ?? null;
        $_SESSION['staff_id'] = $user_data['staff_id'] ?? null;
        // เลือกใช้ชื่อตามประเภทผู้ใช้ (staff_name หรือ user_name)
        $_SESSION['user_display_name'] = $user_data['staff_name'] ?? $user_data['user_name'] ?? null;
        $_SESSION['user_display_sur'] = $user_data['staff_sur'] ?? $user_data['user_sur'] ?? null;
        $_SESSION['position'] = !empty($user_data['position']) ? $user_data['position'] : null;
        $_SESSION['dept'] = !empty($user_data['dept']) ? $user_data['dept'] : null;
        $_SESSION['role'] = $user_data['role'] ?? 'ไม่ระบุ';
        $_SESSION['fa_de_name'] = $user_data['fa_de_name'] ?? 'ไม่ระบุ';

        // เช็ค Role ของผู้ใช้ (คงไว้เหมือนเดิม)
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'เจ้าหน้าที่') {
            header("Location: ../admin-main-page.php");
            exit();
        } else {
            // ผู้ใช้ทั่วไป หรือบุคลากรอื่นๆ ที่ไม่ใช่ 'เจ้าหน้าที่' จะไปที่หน้า user project
            header("Location: ../user-project-page.php"); 
            exit();
        }
    } else {
        // กรณีไม่พบในตาราง staff หรือ user (ในโหมด test)
        $_SESSION['login_status'] = 'failed';
        $_SESSION['login_message'] = 'ไม่พบข้อมูลผู้ใช้ (TEST MODE: ตรวจสอบ nontri_id ในตาราง user หรือ staff_id ในตาราง staff)';
        header("Location: ../login-page.php");
        exit();
    }

} else {
    header("Location: ../login-page.php");
    exit();
}
?>
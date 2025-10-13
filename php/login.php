<?php
session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

include '../database/database.php'; 

// LDAP จาก KUCSC-API
const API_ENDPOINT = 'https://inv.csc.ku.ac.th/cscapi/ldap/';
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
    $ldap_api_error_message = '';
    $ldap_raw_data = [];

    $postData = [
        "keyapp" => KEY_APP,
        "dataset" => "ldap",
        "userid" => $username,
        "pwd" => $password
    ];

    $ch = curl_init(API_ENDPOINT);
    if ($ch === false) {
        error_log("Failed to initialize cURL for LDAP API.");
        $ldap_connection_error = true;
        $ldap_api_error_message = "ไม่สามารถเริ่มต้นการเชื่อมต่อ LDAP API ได้";
    } else {
        $headers = [
            'User-Agent: CSC-API-Client/1.0', 
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($postData, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30, 
            CURLOPT_CONNECTTIMEOUT => 10, 
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            error_log("LDAP cURL error: " . $curl_error . " | HTTP Status: " . $http_code . " | Response: " . $response);
            $ldap_connection_error = true;
            $ldap_api_error_message = "เกิดข้อผิดพลาดในการเชื่อมต่อ LDAP API: " . $curl_error;
        } elseif ($http_code === 200) {
            // ดักวิทยาเขตเฉลิมพระเกียรติเท่านั้น
            $ldap_response = json_decode($response, true);
            if ($ldap_response) {
                if (isset($ldap_response['status_code']) && $ldap_response['status_code'] === '1') {
                    if (isset($ldap_response['data']['campus']) && $ldap_response['data']['campus'] === 'C') {
                        $ldap_auth_passed_campus_c = true;
                        $ldap_uid = $ldap_response['data']['uid'] ?? $username; 
                        $ldap_raw_data = $ldap_response['data'];
                    } else {
                        $_SESSION['login_status'] = 'failed';
                        $_SESSION['login_message'] = 'ขออภัย การเข้าสู่ระบบนี้สำหรับนิสิตและบุคลากรของวิทยาเขตเฉลิมพระเกียรติ จังหวัดสกลนครเท่านั้น!';
                        header("Location: ../login-page.php");
                        exit();
                    }
                } else {
                    $error_data = $ldap_response['data'] ?? 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                    $ldap_api_error_message = "LDAP: " . (is_array($error_data) ? json_encode($error_data) : $error_data);
                    error_log("LDAP API responded with status_code 0 or other. Message: " . (is_array($error_data) ? json_encode($error_data) : $error_data) . " | Full Response: " . $response);
                }
            } else {
                error_log("LDAP API response is not valid JSON. Response: " . $response);
                $ldap_connection_error = true;
                $ldap_api_error_message = "ไม่สามารถประมวลผลการตอบกลับจาก LDAP API ได้";
            }
        } else {
            error_log("LDAP API returned HTTP Status Code: " . $http_code . " | Response: " . $response);
            $ldap_connection_error = true;
            $ldap_api_error_message = "LDAP API ตอบกลับด้วยสถานะผิดปกติ: HTTP " . $http_code;
            if ($http_code === 401) {
                $ldap_api_error_message .= " (ตรวจสอบ Key Application หรือสิทธิ์การเข้าถึง)";
            }
        }
    }

    if ($ldap_auth_passed_campus_c) {
        $sql_staff = "SELECT staff_id, staff_name, staff_sur, position, dept, ut.user_type_name AS role FROM staff s
                      JOIN user_type ut ON s.user_type_id = ut.user_type_id
                      WHERE s.staff_id = ?";
        $stmt = $pdo->prepare($sql_staff);
        if ($stmt) {
            $stmt->execute([$ldap_uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = null; 
            if ($row) {
                // พบข้อมูลในตาราง staff (บุคลากร Login ผ่าน LDAP)
                $logged_in = true;
                $user_data = $row;
                $user_data['nontri_id'] = null; 
                $user_data['user_name'] = $row['staff_name']; 
                $user_data['user_sur'] = $row['staff_sur'];
                $user_data['fa_de_name'] = null;
            } else {

                $sql_user = "SELECT nontri_id, user_name, user_sur, position, dept, ut.user_type_name AS role, fd.fa_de_name FROM user u
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
                        $user_data['staff_name'] = null; 
                        $user_data['staff_sur'] = null;
                    } else {

                        $uid_to_insert = $ldap_raw_data['uid'] ?? $username;
                        $thainame = $ldap_raw_data['thainame'] ?? '';
                        $position = $ldap_raw_data['position'] ?? '';
                        $department = $ldap_raw_data['department'] ?? '';
                        $faculty_name = $ldap_raw_data['faculty'] ?? '';

                        // แยกชื่อ-สกุล
                        $name_parts = explode(' ', $thainame, 2);
                        $first_name = $name_parts[0] ?? '';
                        $last_name = $name_parts[1] ?? '';

                        $fa_de_id = 0;
                        if (!empty($faculty_name)) {
                            switch ($faculty_name) {
                                case 'ทรัพยากรธรรมชาติและอุตสาหกรรมเกษตร': $fa_de_id = 1; break; 
                                case 'คณะทรัพยากรธรรมชาติและอุตสาหกรรมเกษตร': $fa_de_id = 1; break; 
                                case 'วิทยาศาสตร์และวิศวกรรมศาสตร์': $fa_de_id = 2; break;
                                case 'คณะวิทยาศาสตร์และวิศวกรรมศาสตร์': $fa_de_id = 2; break;
                                case 'คณะศิลปศาสตร์และวิทยาการจัดการ': $fa_de_id = 3; break;
                                case 'ศิลปศาสตร์และวิทยาการจัดการ': $fa_de_id = 3; break;
                                case 'สาธารณสุขศาสตร์': $fa_de_id = 4; break;
                                case 'คณะสาธารณสุขศาสตร์': $fa_de_id = 4; break;
                            }

                            switch ($department) {
                                case 'สำนักวิทยาเขต': $fa_de_id = 5; break; 
                                case 'กองบริหารทั่วไป': $fa_de_id = 6; break;
                                case 'กองบริหารวิชาการและนิสิต' : $fa_de_id = 7; break;
                                case 'กองบริหารงานวิจัยและบริการวิชาการ' : $fa_de_id = 8; break;
                                case 'กองบริหารกลาง' : $fa_de_id = 9; break; 
                            }
                        }

                        try {
                            if (empty($department) || empty($position)) {
                                $user_type_id = 1; // นิสิต
                                $sql_insert = "INSERT INTO user (nontri_id, user_type_id, user_name, user_sur, position, dept, fa_de_id) 
                                               VALUES (?, ?, ?, ?, ?, ?, ?)";
                                $stmt_insert = $pdo->prepare($sql_insert);
                                $stmt_insert->execute([$uid_to_insert, $user_type_id, $first_name, $last_name, $position, $department, $fa_de_id]);
                                $stmt_insert = null;

                                $user_data = [
                                    'nontri_id' => $uid_to_insert,
                                    'user_type_id' => $user_type_id,
                                    'user_name' => $first_name,
                                    'user_sur' => $last_name,
                                    'position' => $position, 
                                    'dept' => $department,    
                                    'fa_de_id' => $fa_de_id,
                                    'role' => 'นิสิต', 
                                    'fa_de_name' => null, 
                                ];
                                $logged_in = true;

                            } else {
                                $user_type_id = 2; // อาจารย์และบุคลากร
                                $sql_insert = "INSERT INTO user (nontri_id, user_type_id, user_name, user_sur, position, dept, fa_de_id) 
                                               VALUES (?, ?, ?, ?, ?, ?, ?)";
                                $stmt_insert = $pdo->prepare($sql_insert);
                                $stmt_insert->execute([$uid_to_insert, $user_type_id, $first_name, $last_name, $position, $department, $fa_de_id]);
                                $stmt_insert = null;

                                $user_data = [
                                    'nontri_id' => $uid_to_insert,
                                    'user_type_id' => $user_type_id,
                                    'user_name' => $first_name,
                                    'user_sur' => $last_name,
                                    'position' => $position,
                                    'dept' => $department,
                                    'fa_de_id' => $fa_de_id,
                                    'role' => 'บุคลากร', 
                                    'fa_de_name' => null, 
                                ];
                                $logged_in = true;
                            }

                            if ($logged_in && isset($user_data['fa_de_id']) && $user_data['fa_de_id'] > 0) {
                                $sql_fa_de_name = "SELECT fa_de_name FROM faculties_department WHERE fa_de_id = ?";
                                $stmt_fa_de = $pdo->prepare($sql_fa_de_name);
                                $stmt_fa_de->execute([$user_data['fa_de_id']]);
                                $fa_de_row = $stmt_fa_de->fetch(PDO::FETCH_ASSOC);
                                if ($fa_de_row) {
                                    $user_data['fa_de_name'] = $fa_de_row['fa_de_name'];
                                }
                                $stmt_fa_de = null;
                            }

                        } catch (PDOException $e) {
                            error_log("Error inserting new user/staff from LDAP: " . $e->getMessage());
                            $_SESSION['login_status'] = 'failed';
                            $_SESSION['login_message'] = 'เกิดข้อผิดพลาดในการสร้างข้อมูลผู้ใช้ใหม่: ' . $e->getMessage();
                            header("Location: ../login-page.php");
                            exit();
                        }
                    }
                } else {
                    error_log("Failed to prepare user statement after LDAP: " . print_r($pdo->errorInfo(), true));
                    $_SESSION['login_status'] = 'failed';
                    $_SESSION['login_message'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้ (หลัง LDAP)';
                    header("Location: ../login-page.php");
                    exit();
                }
            }
        } else {
            error_log("Failed to prepare staff statement after LDAP: " . print_r($pdo->errorInfo(), true));
            $_SESSION['login_status'] = 'failed';
            $_SESSION['login_message'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลบุคลากร (หลัง LDAP)';
            header("Location: ../login-page.php");
            exit();
        }

    } else {
        $sql_staff_internal = "SELECT staff_id, user_pass, staff_name, staff_sur, position, dept, ut.user_type_name AS role FROM staff s
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
                $user_data['user_name'] = $row['staff_name']; 
                $user_data['user_sur'] = $row['staff_sur'];
                $user_data['fa_de_name'] = null; 
            } 
            if (!$logged_in) {
                $_SESSION['login_status'] = 'failed';
                $_SESSION['login_message'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                if ($ldap_connection_error && !empty($ldap_api_error_message)) {
                    $_SESSION['login_message'] .= ' (' . $ldap_api_error_message . ')';
                } elseif (!$ldap_connection_error && !empty($ldap_api_error_message)) {
                        $_SESSION['login_message'] .= ' (' . $ldap_api_error_message . ')';
                }
                header("Location: ../login-page.php");
                exit();
            }
        } else {
            error_log("Failed to prepare staff statement for internal fallback: " . print_r($pdo->errorInfo(), true));
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
        $_SESSION['user_display_name'] = $user_data['staff_name'] ?? $user_data['user_name'] ?? null;
        $_SESSION['user_display_sur'] = $user_data['staff_sur'] ?? $user_data['user_sur'] ?? null;
        
        $_SESSION['position'] = $user_data['position'] ?? null;
        $_SESSION['dept'] = $user_data['dept'] ?? null;
        
        $_SESSION['role'] = $user_data['role'] ?? 'ไม่ระบุ';
        $_SESSION['fa_de_name'] = $user_data['fa_de_name'] ?? 'ไม่ระบุ';

        if (isset($_SESSION['role']) && $_SESSION['role'] == 'เจ้าหน้าที่') {
            header("Location: ../admin-main-page.php");
            exit();
        } else {
            header("Location: ../user-project-page.php"); 
            exit();
        }
    } else {
        $_SESSION['login_status'] = 'failed';
        $_SESSION['login_message'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
         if ($ldap_connection_error && !empty($ldap_api_error_message)) {
            $_SESSION['login_message'] .= ' (' . $ldap_api_error_message . ')';
        } elseif (!$ldap_connection_error && !empty($ldap_api_error_message)) {
             $_SESSION['login_message'] .= ' (' . $ldap_api_error_message . ')';
        }
        header("Location: ../login-page.php");
        exit();
    }

} else {
    header("Location: ../login-page.php");
    exit();
}
?>
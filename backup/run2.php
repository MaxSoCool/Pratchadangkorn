<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ======= ค่า config (ควรย้ายไป .env หรือไฟล์นอก webroot ในงานจริง) =======
$apiUrl  = 'https://inv.csc.ku.ac.th/cscapi/ldap/';
$payload = [
    "keyapp"  => "",
    "dataset" => "ldap",  
    "userid"  => "",
    "pwd"     => "",
];

// ถ้า API ต้องการ Token ผ่าน Header ให้ใส่ตรงนี้ (ถ้าไม่ต้องการ ปล่อยว่างได้)
$bearerToken = ''; // เช่น 'eyJhbGciOi...'; ถ้ามีให้ใส่ค่า token ลงไป

// ======= เตรียม cURL =======
$ch = curl_init($apiUrl);
if ($ch === false) {
    die("Cannot init cURL");
}

$headers = [
    'User-Agent: CSC-API-Client/1.0',
    'Content-Type: application/json',
];

if (!empty($bearerToken)) {
    $headers[] = 'Authorization: Bearer ' . $bearerToken;
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,

    // ใช้ POST + JSON
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),

    // ตั้งค่า header
    CURLOPT_HTTPHEADER     => $headers,

    // เปิด verify SSL (ควรเปิดจริงในโปรดักชัน)
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response = curl_exec($ch);
$curlErr  = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ======= แสดงผลลัพธ์ =======
header('Content-Type: text/plain; charset=utf-8');

if ($curlErr) {
    echo "cURL error: {$curlErr}\n";
} else {
    echo "HTTP Status: {$httpCode}\n";
    echo "Body:\n{$response}\n";
}

?>
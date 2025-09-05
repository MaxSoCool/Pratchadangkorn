<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ku_ftd_proto";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("error" . $conn->connect_error);
}

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error connecting to the database: " . $e->getMessage()); 
}
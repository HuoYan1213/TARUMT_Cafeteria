<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$HOST = "tarumt-db.csythvbw89fp.us-east-1.rds.amazonaws.com";
$USER = "admin";
$PASSWORD = "tarumt2023";
$DBNAME = "tarumtDB";

/*
$HOST = "127.0.0.1";
$USER = "root";
$PASSWORD = "TKS12345678";
$DBNAME = "tarumtcafeteria";
*/

$conn = new mysqli($HOST, $USER, $PASSWORD, $DBNAME);

if ($conn->connect_error) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}
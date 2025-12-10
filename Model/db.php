<?php
$HOST = "database-1.csythvbw89fp.us-east-1.rds.amazonaws.com";
$USER = "admin";
$PASSWORD = "tarumt2023";
$DBNAME = "tarumtDB";

$conn = new mysqli($HOST, $USER, $PASSWORD, $DBNAME);

if ($conn->connect_error) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}
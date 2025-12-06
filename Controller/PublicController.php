<?php
require_once __DIR__ . '/../Model/db.php';
require_once __DIR__ . '/../Helper/HashHelper.php';

session_start();

class PublicController {
    private $conn;

    public function __construct($db){
        $this->conn = $db;
    }

    public function requestHandle() {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        if ($action === 'login') {
            $this->login();
        }
        elseif ($action === 'register') {
            $this->register();
        }
    }

    private function register() {
        header('Content-Type: application/json');

        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        if (empty($name) || empty($email) || empty($phone) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Please fill in all fields.']);
            exit;
        }

        $check_sql = 'SELECT User_ID FROM users WHERE Email = ? OR Phone_Number = ?';
        $stmt = $this->conn->prepare($check_sql);
        $stmt->bind_param("ss", $email, $phone);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email or Phone Number already registered.']);
            exit;
        }

        // The User_ID is AUTO_INCREMENT, so we don't generate or insert it.
        $insert_sql = 'INSERT INTO users (User_Name, Email, Phone_Number, Password) VALUES (?, ?, ?, ?)';
        $stmt = $this->conn->prepare($insert_sql);
        
        $hashed_password = HashHelper::hashPassword($password);
        $stmt->bind_param("ssss", $name, $email, $phone, $hashed_password);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Registration successful.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again.']);
        }
        exit;
    }

    private function login() {
        $login_key = isset($_POST['key']) ? trim($_POST['key']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        if (empty($login_key) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Please fill in all fields']);
            return;
        }

        $sql = 'SELECT User_ID, Password, User_Name
                FROM users 
                WHERE Email = ?
                OR Phone_Number = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $login_key, $login_key);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $u = $result->fetch_assoc();

            if (HashHelper::verifyPassword($password, $u['Password'])) {
                $_SESSION['user_id'] = $u['User_ID'];
                $_SESSION['user_name'] = $u['User_Name'];

                $this->createCart($u['User_ID']);

                echo json_encode(['status' => 'success', 'message' => 'Login successful']);
            } 
            else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
            }
        } 
        else {
            echo json_encode(['status' => 'error', 'message' => 'User not found']);
        }
        exit;
    }
            
    private function createCart($id) {
        $check_sql = 'SELECT Cart_ID 
                      FROM carts
                      WHERE User_ID = ?';
        $stmt = $this->conn->prepare($check_sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $_SESSION['cart_id'] = $row['Cart_ID'];
        }
        else {
            $insert_sql = 'INSERT INTO carts (User_ID)
                           Values (?)';
            $insert_stmt = $this->conn->prepare($insert_sql);
            $insert_stmt->bind_param("i", $id);
            
            if ($insert_stmt->execute()) {
                $_SESSION['cart_id'] = $insert_stmt->insert_id;
            }
        }
    }
}

$auth = new PublicController($conn);    
$auth->requestHandle();
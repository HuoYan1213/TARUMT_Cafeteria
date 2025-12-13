<?php
require_once __DIR__ . '/../Model/db.php';
require_once __DIR__ . '/../Helper/HashHelper.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
require_once __DIR__ . '/../Helper/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../Helper/PHPMailer/SMTP.php';
require_once __DIR__ . '/../Helper/PHPMailer/Exception.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

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
        elseif ($action === 'request_otp') {
            $this->requestOtp();
        }
        elseif ($action === 'reset_password') {
            $this->resetPassword();
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

    private function requestOtp() {
        header('Content-Type: application/json');
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email.']);
            exit;
        }

        // Check if email exists
        $sql = 'SELECT User_ID FROM users WHERE Email = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'This email is not registered.']);
            exit;
        }

        // Generate and store OTP in session
        $otp = rand(100000, 999999);
        $_SESSION['reset_otp'] = $otp;
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_otp_expiry'] = time() + 300; // OTP valid for 5 minutes

        // --- Start of Real Email Sending Logic ---
        $mail = new PHPMailer(true);
        try {
            //Server settings - Replace with your SMTP server details
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // ★ 修正：使用真實的 SMTP 伺服器，例如 Gmail
            $mail->SMTPAuth   = true;
            $mail->Username   = 'huoyan0928@gmail.com'; // ★ 填寫您的 Gmail 帳號
            $mail->Password   = 'rsgl lyyh byrk olbv'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            //Recipients
            $mail->setFrom('no-reply@tarumtcafeteria.com', 'TARUMT Cafeteria');
            $mail->addAddress($email); // Add a recipient

            //Content
            $mail->isHTML(true);
            $mail->Subject = 'Your Password Reset OTP';
            $mail->Body    = "Hi there,<br><br>Your One-Time Password (OTP) for password reset is: <b>{$otp}</b><br><br>This OTP is valid for 5 minutes.<br><br>If you did not request this, please ignore this email.<br><br>Thank you,<br>TARUMT Cafeteria Team";
            $mail->AltBody = "Your One-Time Password (OTP) for password reset is: {$otp}. This OTP is valid for 5 minutes.";

            $mail->send();
            echo json_encode(['status' => 'success', 'message' => 'An OTP has been sent to your email.']);

        } catch (Exception $e) { // Catch PHPMailer exceptions
            // Log the actual error for debugging, but show a generic message to the user
            // error_log("Mailer Error: " . $mail->ErrorInfo);
            echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP. Please try again later.']);
        }
        // --- End of Real Email Sending Logic ---
        exit;
    }

    private function resetPassword() {
        header('Content-Type: application/json');
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        // Basic validation
        if (empty($otp) || empty($password) || empty($email)) {
            echo json_encode(['status' => 'error', 'message' => 'Please fill all fields.']);
            exit;
        }

        // OTP validation
        if (!isset($_SESSION['reset_otp']) || $_SESSION['reset_otp'] != $otp || $_SESSION['reset_email'] != $email || time() > $_SESSION['reset_otp_expiry']) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired OTP.']);
            exit;
        }

        // Update password
        $hashed_password = HashHelper::hashPassword($password);
        $sql = "UPDATE users SET Password = ? WHERE Email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $hashed_password, $email);

        if ($stmt->execute()) {
            unset($_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_otp_expiry']); // Clear session data
            echo json_encode(['status' => 'success', 'message' => 'Password reset successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to reset password.']);
        }
        exit;
    }
}

$auth = new PublicController($conn);    
$auth->requestHandle();
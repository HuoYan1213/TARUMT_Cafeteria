<?php
require_once __DIR__ . '/../Model/db.php';
require_once __DIR__ . '/../Helper/IDHelper.php';

session_start();

class AuthController {
    private $conn;
    private $user_id;

    public function __construct($db) {
        $this->conn = $db;
        $this->user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    }

    public function handleRequest() {
        $action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

        if ($action === 'get_products') {
            $this->getProducts();
        }
        elseif ($action === 'add_cart') {
            $this->addCart();
        }
        elseif ($action === 'get_cart_items') {
            $this->getCartItems();
        }
        elseif ($action === 'update_quantity') {
            $this->updateQuantity();
        }
        elseif ($action === 'delete_item') {
            $this->deleteItem();
        }
        elseif ($action === 'get_checkout_details') {
            $this->getCheckoutDetails();
        }
        elseif ($action === 'batch_delete_items') {
            $this->batchDeleteItems();
        }
        elseif ($action === 'get_user_details') {
            $this->getUserDetails();
        }
        elseif ($action === 'place_order') {
            $this->handlePlaceOrder();
        }
        elseif ($action === 'check_payment_status') {
            $this->checkOrderStatus();
        }
        elseif ($action === 'get_confirmation_details') {
            $this->getOrderConfirmationDetails();
        }
        elseif ($action === 'get_order_details') {
            $this->getOrderDetails();
        }
        elseif ($action === 'get_order_history') {
            $this->getOrderHistory();
        }
        elseif ($action === 'get_top_sales') {
            $this->getTopSales();
        }
        elseif ($action === 'check_session') {
            $this->checkSession();
        }
        elseif ($action === 'get_profile_details') {
            $this->getProfileDetails();
        }
        elseif ($action === 'logout') {
            $this->logout();
        }
        elseif ($action === 'update_profile') {
            $this->updateProfile();
        }
        elseif ($action === 'delete_account') {
            $this->deleteAccount();
        }
    }

    private function handlePlaceOrder() {
        header('Content-Type: application/json');
        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : null;
        $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : null;

        // Case 1: E-wallet payment initiation from checkout.html
        if ($payment_method === 'e-wallet-initiate') {
            $this->initiateEWalletOrder();
        } 
        // Case 2: E-wallet payment confirmation from confirmation.html
        elseif ($order_id) {
            $this->confirmEWalletOrder($order_id);
        }
        // Case for FPX
        elseif ($payment_method === 'fpx-initiate') {
            $this->initiateEWalletOrder(); // Can reuse the same logic as e-wallet
        } elseif ($payment_method === 'fpx-confirm' && $order_id) {
            $this->confirmEWalletOrder($order_id); // Can reuse the same logic
        }
        // Case 3: Traditional order placement (e.g., Pay at Counter)
        else {
            $this->createStandardOrder();
        }
    }

    private function initiateEWalletOrder() {
        $provider = isset($_POST['provider']) ? $_POST['provider'] : 'Online Payment';
        $order_type = isset($_POST['order_type']) ? $_POST['order_type'] : 'Dine-In'; // Assume Dine-In if not specified
        $product_ids = isset($_POST['product_ids']) ? $_POST['product_ids'] : [];
        $cart_id = $this->getCart($this->user_id);

        if (empty($product_ids) || !$cart_id) {
            echo json_encode(['status' => 'error', 'message' => 'No products to process.']);
            exit;
        }

        // Securely recalculate total on the backend
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $sql_total = "SELECT SUM(Subtotal) as Total FROM cart_products WHERE Cart_ID = ? AND Product_ID IN ($placeholders)";
        $types = 's' . str_repeat('i', count($product_ids));
        $params = array_merge([$cart_id], $product_ids);
        $stmt_total = $this->conn->prepare($sql_total);
        $stmt_total->bind_param($types, ...$params);
        $stmt_total->execute();
        $total_amount_from_cart = $stmt_total->get_result()->fetch_assoc()['Total'] ?? 0;

        $extra_charge = 0;
        if ($order_type === 'Take-Away') $extra_charge = 1.00;
        elseif ($order_type === 'Delivery') $extra_charge = 2.90;

        $final_total = $total_amount_from_cart + ($total_amount_from_cart * 0.06) + $extra_charge;

        $order_id = IDHelper::generate($this->conn, 'orders', 'Order_ID', 'ORD');
        $current_time = date('Y-m-d H:i:s');
        $status = 'Pending'; // New status for QR flow
        $payment_method = isset($_POST['payment_method']) && str_contains($_POST['payment_method'], 'fpx') ? 'Online Banking' : 'E-Wallet';

        // Insert with all details, including order type
        $sql_order = "INSERT INTO orders (Order_ID, User_ID, Created_At, Total_Amount, Status, Order_Type) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql_order);
        $stmt->bind_param("sisdss", $order_id, $this->user_id, $current_time, $final_total, $status, $order_type);

        if ($stmt->execute()) {
            $this->createPaymentRecord($order_id, $final_total, $payment_method, $provider);
            echo json_encode(['status' => 'success', 'order_id' => $order_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to initiate payment.']);
        }
        exit;
    }

    private function confirmEWalletOrder($order_id) {
        // Find the pending order
        $sql_order = "SELECT * FROM orders WHERE Order_ID = ? AND Status = 'Pending'";
        $stmt_order = $this->conn->prepare($sql_order);
        $stmt_order->bind_param("s", $order_id);
        $stmt_order->execute();
        $order_result = $stmt_order->get_result();

        if ($order_result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or already processed order.']);
            exit;
        }
        $order = $order_result->fetch_assoc();
        $user_id_for_cart = $order['User_ID'];

        // Update order status to 'Completed'
        $sql_update = "UPDATE orders SET Status = 'Completed' WHERE Order_ID = ?";
        $stmt_update = $this->conn->prepare($sql_update);
        $stmt_update->bind_param("s", $order_id);

        if ($stmt_update->execute()) {
            // Move items from cart to order_products and clear cart
            $this->processCartForOrder($order_id, $user_id_for_cart);
            // ★ 關鍵修正：更新現有的支付記錄狀態，而不是創建新的
            $sql_update_payment = "UPDATE payments SET Status = 'COMPLETED' WHERE Order_ID = ?";
            $stmt_update_payment = $this->conn->prepare($sql_update_payment);
            $stmt_update_payment->bind_param("s", $order_id);
            $stmt_update_payment->execute();
            echo json_encode(['status' => 'success', 'message' => 'Order placed successfully', 'order_id' => $order_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to confirm order.']);
        }
        exit;
    }

    private function createStandardOrder() {
        $product_ids = isset($_POST['product_ids']) && is_array($_POST['product_ids']) ? $_POST['product_ids'] : [];
        $payment_method_type = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'counter';
        $provider = isset($_POST['provider']) ? $_POST['provider'] : 'N/A';
        $cart_id = $this->getCart($this->user_id);

        if (empty($product_ids) || !$cart_id) {
            // This can happen if user directly navigates to delivery.html without items
            if (isset($_POST['delivery_address'])) {
                echo json_encode(['status' => 'error', 'message' => 'Your cart is empty or invalid.']);
            }
            echo json_encode(['status' => 'error', 'message' => 'No products selected or cart not found.']);
            exit;
        }

        // Recalculate total from cart for security, based on selected product_ids
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $sql_total = "SELECT SUM(Subtotal) as Total FROM cart_products WHERE Cart_ID = ? AND Product_ID IN ($placeholders)";
        
        $types = 's' . str_repeat('i', count($product_ids));
        $params = array_merge([$cart_id], $product_ids);

        $stmt = $this->conn->prepare($sql_total);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_amount = $row['Total'] ?? 0;

        // Add extra charges for Take-Away or Delivery
        $order_type = isset($_POST['order_type']) ? $_POST['order_type'] : 'Dine-In';
        $delivery_address = isset($_POST['delivery_address']) ? trim($_POST['delivery_address']) : null;
        $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $extra_charge = 0;
        if ($order_type === 'Take-Away') {
            $extra_charge = 1.00;
        } elseif ($order_type === 'Delivery') {
            $extra_charge = 2.90;
        }
        $final_total = $total_amount + ($total_amount * 0.06) + $extra_charge;
        
        if ($final_total <= 0) {
            // This can happen if product_ids are invalid
            echo json_encode(['status' => 'error', 'message' => 'Selected items are invalid or have a total of zero.']);
            exit;
        }

        $order_id = IDHelper::generate($this->conn, 'orders', 'Order_ID', 'ORD');
        $current_time = date('Y-m-d H:i:s');
        $status = 'Completed'; // Assume direct completion for non-pending flows

        $sql_order = "INSERT INTO orders (Order_ID, User_ID, Created_At, Total_Amount, Status, Order_Type, Delivery_Address, Latitude, Longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql_order);
        $stmt->bind_param("sisdsssdd", $order_id, $this->user_id, $current_time, $final_total, $status, $order_type, $delivery_address, $latitude, $longitude);

        if ($stmt->execute()) {
            $this->processCartForOrder($order_id, $this->user_id, $product_ids);
            // For counter payments, explicitly set a clear provider name,
            // otherwise, use the provider sent from the frontend (e.g., 'Visa', 'Mastercard').
            if ($payment_method_type === 'counter' && $order_type === 'Delivery') {
                $provider = 'Cash on Delivery';
            } else if ($payment_method_type === 'counter') {
                $provider = 'Pay At Counter';
            }
            $this->createPaymentRecord($order_id, $final_total, $payment_method_type, $provider);
            echo json_encode(['status' => 'success', 'message' => 'Order placed successfully', 'order_id' => $order_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create order']);
        }
        exit;
    }

    private function createPaymentRecord($order_id, $amount, $method, $provider) {
        $payment_id = IDHelper::generate($this->conn, 'payments', 'Payment_ID', 'PAY');
        // ★ 根據支付流程設定初始狀態
        $status = (str_contains($method, 'initiate')) ? 'PENDING' : 'COMPLETED';
        $current_time = date('Y-m-d H:i:s');

        // Normalize payment method for the database enum
        $db_method = 'Pay At Counter (Cash)'; // Default
        if (str_contains($method, 'e-wallet')) {
            $db_method = 'E-Wallet';
        } elseif (str_contains($method, 'fpx') || $method === 'Online Banking') {
            $db_method = 'Online Banking';
        } elseif ($method === 'card') {
            $db_method = 'Card';
        } elseif ($method === 'counter') {
            $db_method = 'Pay At Counter (Cash)';
        }

        $sql = "INSERT INTO payments (Payment_ID, Order_ID, Total_Amount, Payment_Method, Provider, Status, Created_At)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssdssss", $payment_id, $order_id, $amount, $db_method, $provider, $status, $current_time);
            $stmt->execute(); // We don't need to check for success here, it's a best-effort logging
        }
    }

    private function processCartForOrder($order_id, $user_id, $product_ids = []) {
        $cart_id = $this->getCart($user_id);
        if (!$cart_id) return;

        $where_clause = '';
        $bind_params = [$cart_id];
        $bind_types = 's';

        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $where_clause = " AND Product_ID IN ($placeholders)";
            $bind_params = array_merge($bind_params, $product_ids);
            $bind_types .= str_repeat('i', count($product_ids));
        }
        // Move items from cart to order_products
        $sql_cart_products = "SELECT Product_ID, Quantity, Subtotal FROM cart_products WHERE Cart_ID = ?" . $where_clause;
        $stmt_cart_products = $this->conn->prepare($sql_cart_products);
        $stmt_cart_products->bind_param($bind_types, ...$bind_params);
        $stmt_cart_products->execute();
        $cart_products_result = $stmt_cart_products->get_result();

        $sql_insert_order_product = "INSERT INTO order_products (Order_Product_ID, Order_ID, Product_ID, Quantity, Subtotal) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert_order_product = $this->conn->prepare($sql_insert_order_product);

        $sql_update_bestsale = "UPDATE products SET Best_Sale = Best_Sale + ? WHERE Product_ID = ?";
        $stmt_update_bestsale = $this->conn->prepare($sql_update_bestsale);

        while ($cart_product = $cart_products_result->fetch_assoc()) {
            $order_product_id = IDHelper::generate($this->conn, 'order_products', 'Order_Product_ID', 'OPI');
            $product_id = $cart_product['Product_ID'];
            $quantity = $cart_product['Quantity'];
            $subtotal_item = $cart_product['Subtotal'];

            // Update Best_Sale count for the product
            $stmt_update_bestsale->bind_param("ii", $quantity, $product_id);
            $stmt_update_bestsale->execute();
            $stmt_insert_order_product->bind_param("ssiid", $order_product_id, $order_id, $product_id, $quantity, $subtotal_item);
            $stmt_insert_order_product->execute();
        }

        // Clear only the processed items from the cart
        $sql_clear = "DELETE FROM cart_products WHERE Cart_ID = ?" . $where_clause;
        $stmt_clear = $this->conn->prepare($sql_clear);
        $stmt_clear->bind_param($bind_types, ...$bind_params);
        $stmt_clear->execute();
    }

    private function checkOrderStatus() {
        header('Content-Type: application/json');
        $order_id = isset($_GET['oid']) ? $_GET['oid'] : '';

        if (empty($order_id)) {
            echo json_encode(['status' => 'Error', 'message' => 'Order ID is missing.']);
            exit;
        }

        $sql = "SELECT Status, Order_ID, Total_Amount FROM orders WHERE Order_ID = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'status' => $row['Status'], // e.g., 'Pending Payment', 'Completed'
                'order_id' => $row['Order_ID'],
                'amount' => $row['Total_Amount']
            ]);
        } else {
            echo json_encode(['status' => 'NotFound', 'message' => 'Order not found.']);
        }
        exit;
    }

    private function getOrderConfirmationDetails() {
        header('Content-Type: application/json');
        $order_id = isset($_GET['oid']) ? $_GET['oid'] : '';

        if (empty($order_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Order ID is missing.']);
            exit;
        }

        $sql = "SELECT Total_Amount, Status FROM orders WHERE Order_ID = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($row['Status'] !== 'Pending') {
                echo json_encode(['status' => 'error', 'message' => 'This payment has already been processed.']);
                exit;
            }
            echo json_encode(['status' => 'success', 'amount' => $row['Total_Amount']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Order not found.']);
        }
        exit;
    }

    private function getOrderDetails() {
        header('Content-Type: application/json');
        $order_id = isset($_GET['oid']) ? $_GET['oid'] : '';
    
        if (empty($order_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Order ID is missing.']);
            exit;
        }
    
        // 1. Get main order info
        $sql_order = "SELECT Order_ID, Created_At, Total_Amount, Order_Type FROM orders WHERE Order_ID = ? AND User_ID = ?";
        $stmt_order = $this->conn->prepare($sql_order);
        $stmt_order->bind_param("si", $order_id, $this->user_id);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();
        $order_info = $result_order->fetch_assoc();
    
        if (!$order_info) {
            echo json_encode(['status' => 'error', 'message' => 'Order not found or access denied.']);
            exit;
        }
    
        // 2. Get order items
        $sql_items = "SELECT p.Product_Name, op.Quantity, op.Subtotal
                      FROM order_products op
                      JOIN products p ON op.Product_ID = p.Product_ID
                      WHERE op.Order_ID = ?";
        $stmt_items = $this->conn->prepare($sql_items);
        $stmt_items->bind_param("s", $order_id);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        
        $items = [];
        while ($row = $result_items->fetch_assoc()) {
            $items[] = $row;
        }
    
        $order_info['items'] = $items;
        echo json_encode(['status' => 'success', 'data' => $order_info]);
        exit;
    }

    private function getTopSales() {
        header('Content-Type: application/json');
        
        $sql = "SELECT Product_Name, Price, Image_Path 
                FROM products 
                ORDER BY Best_Sale DESC 
                LIMIT 3";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $top_sales = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $top_sales[] = [
                    'name' => $row['Product_Name'],
                    'price' => $row['Price'],
                    'image' => $row['Image_Path']
                ];
            }
        }
        echo json_encode(['status' => 'success', 'data' => $top_sales]);
    }

    private function checkSession() {
        header('Content-Type: application/json');
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
            echo json_encode(['status' => 'success', 'loggedIn' => true]);
        } else {
            echo json_encode(['status' => 'success', 'loggedIn' => false]);
        }
        exit;
    }

    private function getProfileDetails() {
        header('Content-Type: application/json');
        if ($this->user_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
            exit;
        }
    
        $sql = "SELECT User_Name, Email, Phone_Number, Image_Path, Default_Address FROM users WHERE User_ID = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($user = $result->fetch_assoc()) {
            // Provide a default if Image_Path is empty or null
            $user['Image_Path'] = $user['Image_Path'] ?? 'default_profile.png';
            $user['Default_Address'] = $user['Default_Address'] ?? ''; // Provide empty string if null
            echo json_encode(['status' => 'success', 'data' => $user]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        }
        exit;
    }

    private function logout() {
        session_unset();
        session_destroy();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Logged out successfully.']);
        exit;
    }

    private function updateProfile() {
        header('Content-Type: application/json');
        if ($this->user_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
            exit;
        }
    
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $delivery_address = isset($_POST['delivery_address']) ? trim($_POST['delivery_address']) : null;
    
        if (empty($name) || empty($email) || empty($phone)) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
            exit;
        }
    
        $profile_picture_filename = null;
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_name = $_FILES['profile_picture']['tmp_name'];
            $file_name = $_FILES['profile_picture']['name'];
            $file_size = $_FILES['profile_picture']['size'];
            $file_type = $_FILES['profile_picture']['type'];

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_file_size = 5 * 1024 * 1024; // 5 MB

            if (!in_array($file_type, $allowed_types)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, GIF are allowed.']);
                exit;
            }
            if ($file_size > $max_file_size) {
                echo json_encode(['status' => 'error', 'message' => 'File size exceeds 5MB limit.']);
                exit;
            }

            $upload_dir = __DIR__ . '/../Image/User/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
            }

            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_file_name = 'user_' . $this->user_id . '_' . time() . '.' . $file_ext;
            $destination_path = $upload_dir . $unique_file_name;

            if (move_uploaded_file($file_tmp_name, $destination_path)) {
                $profile_picture_filename = $unique_file_name;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to upload profile picture.']);
                exit;
            }
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
            exit;
        }
    
        $sql_parts = ["User_Name = ?", "Email = ?", "Phone_Number = ?"];
        $params = [$name, $email, $phone];
        $types = "sss";

        if ($profile_picture_filename !== null) {
            $sql_parts[] = "Image_Path = ?";
            $params[] = $profile_picture_filename;
            $types .= "s";
        }
        
        // Add delivery address to update
        if ($delivery_address !== null) { // Allow setting to empty string
            $sql_parts[] = "Default_Address = ?";
            $params[] = $delivery_address;
            $types .= "s";
        }

        $params[] = $this->user_id;
        $types .= "i";

        $sql = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE User_ID = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
    
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update profile. Please try again.']);
        }
        exit;
    }

    private function deleteAccount() {
        header('Content-Type: application/json');
        if ($this->user_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
            exit;
        }
        $sql = "DELETE FROM users WHERE User_ID = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $this->logout(); // Reuse logout to clear session and send success response
    }

    private function getOrderHistory() {
        if ($this->user_id === 0) { // Should be protected by checkSession on the frontend
            echo '<p>Please log in to see your order history.</p>';
            exit;
        }
    
        // Get User Name
        $user_details = $this->getUserDetails();
        $user_name = $user_details ? htmlspecialchars($user_details['User_Name']) : 'Customer';

        // Get Orders
        $sql_orders = "SELECT Order_ID, Created_At, Total_Amount, Status, Order_Type FROM orders WHERE User_ID = ? ORDER BY Created_At DESC";
        $stmt_orders = $this->conn->prepare($sql_orders);
        $stmt_orders->bind_param("i", $this->user_id);
        $stmt_orders->execute();
        $result_orders = $stmt_orders->get_result();
        
        $items_html = '';
        if ($result_orders->num_rows > 0) {
            while ($row = $result_orders->fetch_assoc()) {
                $order_id = htmlspecialchars($row['Order_ID']);
                $created_at = new DateTime($row['Created_At']);
                $date = $created_at->format('M d, Y');
                $total_amount = number_format($row['Total_Amount'], 2);
                $order_type = htmlspecialchars($row['Order_Type']);
                $status = htmlspecialchars($row['Status']);
                $status_class = strtolower(str_replace(' ', '-', $status)); // e.g., 'completed', 'pending'
    
                $items_html .= '
                <div class="history-item" data-order-id="' . $order_id . '">
                    <div class="left-history">
                        <span style="font-size: 12px;">' . $date . ' (' . $order_type . ')</span>
                        <span style="font-size: 18px;font-weight: 600;">' . $order_id . '</span>
                    </div>
                    <div class="right-history">
                        <span style="color: var(--text-price);font-size: 18px;font-weight: 600;">RM ' . $total_amount . '</span>
                        <span class="status ' . $status_class . '">' . $status . '</span>
                    </div>
                </div>';
            }
        } else {
            $items_html = '<p style="text-align:center; color:#888;">You haven\'t placed any orders yet.</p>';
        }

        echo json_encode(['status' => 'success', 'userName' => $user_name, 'itemsHtml' => $items_html]); // This should be JSON
        exit;
    }


    private function getUserDetails() {
        if ($this->user_id === 0) {
            return null;
        }
        $sql = "SELECT User_Name, Email FROM users WHERE User_ID = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }

    private function batchDeleteItems() {
        header('Content-Type: application/json');
        $product_ids = isset($_POST['product_ids']) && is_array($_POST['product_ids']) ? $_POST['product_ids'] : [];
        $cart_id = $this->getCart($this->user_id);

        if (empty($product_ids) || !$cart_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $sql = "DELETE FROM cart_products WHERE Cart_ID = ? AND Product_ID IN ($placeholders)";
        
        $types = 's' . str_repeat('i', count($product_ids));
        $params = array_merge([$cart_id], $product_ids);

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Items removed from cart']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to remove items']);
        }
        exit;
    }

    private function getCheckoutDetails() {
        header('Content-Type: application/json');
        $cart_id = $this->getCart($this->user_id);
        $product_ids = isset($_POST['product_ids']) && is_array($_POST['product_ids']) ? $_POST['product_ids'] : [];

        if (!$cart_id || empty($product_ids)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
            exit;
        }

        // Sanitize all product IDs to be integers
        $sanitized_product_ids = array_map('intval', $product_ids);
        $placeholders = implode(',', array_fill(0, count($sanitized_product_ids), '?'));

        $sql = "SELECT p.Product_ID, p.Product_Name, p.Price, cp.Quantity
                FROM cart_products cp
                JOIN products p ON cp.Product_ID = p.Product_ID
                WHERE cp.Cart_ID = ? AND p.Product_ID IN ($placeholders)";

        $types = 's' . str_repeat('i', count($sanitized_product_ids));
        $params = array_merge([$cart_id], $sanitized_product_ids);

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $items[] = [
                    'id' => $row['Product_ID'],
                    'name' => $row['Product_Name'],
                    'price' => $row['Price'],
                    'quantity' => $row['Quantity']
                ];
            }
        }

        echo json_encode([
            'status' => 'success',
            'items' => $items
        ]);
        exit;
    }

    private function deleteItem() {
        header('Content-Type: application/json');
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $cart_id = $this->getCart($this->user_id);

        if ($product_id <= 0 || !$cart_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
            exit;
        }

        $sql = "DELETE FROM cart_products WHERE Cart_ID = ? AND Product_ID = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $cart_id, $product_id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Item removed from cart']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to remove item']);
        }
        exit;
    }

    private function updateQuantity() {
        header('Content-Type: application/json');
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $cart_id = $this->getCart($this->user_id);
    
        if ($product_id <= 0 || $quantity <= 0 || !$cart_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
            exit;
        }
    
        $price = $this->getPrice($product_id);
        if ($price === false) {
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
            exit;
        }
    
        $subtotal = $price * $quantity;
    
        $sql = "UPDATE cart_products 
                SET Quantity = ?, Subtotal = ? 
                WHERE Cart_ID = ? AND Product_ID = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("idsi", $quantity, $subtotal, $cart_id, $product_id);
    
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Quantity updated']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Update failed']);
        }
        exit;
    }

    private function getCartItems() {
        header('Content-Type: application/json');
        $cart_id = $this->getCart($this->user_id);

        $sql = "SELECT p.Product_ID, p.Product_Name, p.Price, p.Image_Path, cp.Quantity
                FROM cart_products cp
                JOIN products p ON cp.Product_ID = p.Product_ID
                WHERE cp.Cart_ID = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $cart_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $items_html = '';
        $receipt_items_html = '';
        $subtotal = 0;

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $product_id = $row['Product_ID'];
                $name = htmlspecialchars($row['Product_Name']);
                $price = $row['Price'];
                $image = htmlspecialchars($row['Image_Path']);
                $quantity = $row['Quantity'];
                $item_total = $price * $quantity;
                $subtotal += $item_total;

                $items_html .= '
                <div class="cart-items" data-id="'.$product_id.'" data-price="'.$price.'" data-quantity="'.$quantity.'">
                    <label id="cart-checkbox" for="'.$product_id.'">
                        <input type="checkbox" id="'.$product_id.'" class="cart-item-checkbox" value="'.$product_id.'">
                        <img src="../../Image/Product/'.$image.'">
                    </label>
                    <div class="item-details">
                        <span id="item-name">'.$name.'</span>
                        <span id="item-price">RM '.number_format($price, 2).'</span>
                    </div>
                    <div class="item-controller">
                        <button class="delete-button"><i class="fa-solid fa-trash"></i></button>
                        <div class="quantity-button">
                            <button class="minus-button"><i class="fa-solid fa-minus"></i></button>
                            <input type="text" value="'.$quantity.'" readonly>
                            <button class="add-button"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                </div>';

                $receipt_items_html .= '
                <div class="receipt-item">
                    <span>' . $name . '   x' . $quantity . '</span>
                    <span>RM ' . number_format($item_total, 2) . '</span>
                </div>';
            }
            $checkout_button_html = '<button id="checkout-button">Checkout Now</button>';
        } else {
            $items_html = '<div class="no-items-found" style="margin:20px 0px;">Your cart is empty.</div>';
            $receipt_items_html = '<div class="no-items-found">No items</div>';
            $checkout_button_html = '<button id="checkout-button" disabled>Checkout Now</button>';
        }

        $tax = $subtotal * 0.06;
        $total = $subtotal; // Summary on cart page should not include tax yet, it's calculated on checkout

        $summary_html = '
        <h1>RECEIPT</h1>
        <div class="receipt-items-container">' . $receipt_items_html . '</div>
        <div class="dashed-line"></div>
        <div class="cost-subtotal">
            <span>Subtotal</span>
            <span>RM '.number_format($subtotal, 2).'</span>
        </div>
        <div class="cost-tax" style="display:none;">
            <span>Tax (6%)</span>
            <span>RM '.number_format($tax, 2).'</span>
        </div>
        <div class="dashed-line" style="display:none;"></div>
        <div class="total-amount">
            <span>TOTAL</span>
            <span>RM '.number_format($total, 2).'</span>
        </div>
        ' . $checkout_button_html;

        echo json_encode([
            'items_html' => $items_html,
            'summary_html' => $summary_html
        ]);
        exit;
    }

    private function getProducts() {
        $category = isset($_POST['category']) ? $_POST['category'] : '';

        $sql = "SELECT * FROM products WHERE Category = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $id = $row['Product_ID'];
                $name = htmlspecialchars($row['Product_Name']);
                $price = number_format($row['Price'], 2);
                $description = htmlspecialchars($row['Description']);
                $image = htmlspecialchars($row['Image_Path']);

                echo '
                <div class="category-items">
                    <div class="left-items">
                        <img src="../../Image/Product/'.$image.'" alt="'.$name.'">
                        <div class="item-details">
                            <span class="name">'.$name.'</span>
                            <span class="price">RM '.$price.'</span>
                            <span class="description">'.$description.'</span>
                        </div>
                    </div>
                    <div class="right-items">
                        <button class="add-cart-button" data-id="'.$id.'">Add To Cart +</button>
                    </div>
                </div>';
            }
        } 
        else {
            echo '<span class="no-items-found">No items found in this category.</span>';
        }
        exit;
    }

    private function getCart($id) {
        $sql = 'SELECT Cart_ID 
                FROM carts 
                WHERE User_ID = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['Cart_ID'];
        } 
        else {
            $insert_sql = "INSERT INTO carts (User_ID, Total_Amount) 
                          VALUES (?, 0.00)";
            $stmt = $this->conn->prepare($insert_sql);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                return $stmt->insert_id;
            }
            return false;
        }
    }

    private function getPrice($id) {
        $sql = 'SELECT Price 
                FROM products 
                WHERE Product_ID = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['Price'];
        }
        return false;
    }

    private function addCart() {
        header('Content-Type: application/json');

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if ($product_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid Product ID']);
            exit;
        }

        $cart_id = $this->getCart($this->user_id);
        if (!$cart_id) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to get Cart ID']);
            exit;
        }

        $price = $this->getPrice($product_id);
        if ($price === false) {
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
            exit;
        }

        $check_sql = 'SELECT Cart_Product_ID, Quantity 
                      FROM cart_products 
                      WHERE Cart_ID = ? 
                      AND Product_ID = ?';
        $stmt = $this->conn->prepare($check_sql);
        $stmt->bind_param("ii", $cart_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $new_quantity = $row['Quantity'] + 1;
            $new_subtotal = $new_quantity * $price;

            $update_sql = "UPDATE cart_products 
                           SET Quantity = ?, Subtotal = ? 
                           WHERE Cart_Product_ID = ?";
            $update_stmt = $this->conn->prepare($update_sql);
            $update_stmt->bind_param("ids", $new_quantity, $new_subtotal, $row['Cart_Product_ID']);
            
            if ($update_stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Quantity updated']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Update failed']);
            }

        } else {
            $new_id = IDHelper::generate($this->conn, 'cart_products', 'Cart_Product_ID', 'CP');
            $quantity = 1;
            $subtotal = $price * $quantity;

            $insert_sql = "INSERT INTO cart_products (Cart_Product_ID, Cart_ID, Product_ID, Quantity, Subtotal) 
                           VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $this->conn->prepare($insert_sql);
            $insert_stmt->bind_param("siiid", $new_id, $cart_id, $product_id, $quantity, $subtotal);

            if ($insert_stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Item added to cart']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Insert failed']);
            }
        }
        exit;
    }
}

$controller = new AuthController($conn);
$controller->handleRequest();
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
            $this->placeOrder();
        }
    }

    private function placeOrder() {
        header('Content-Type: application/json');

        // 1. 獲取購物車
        $cart_id = $this->getCart($this->user_id);
        if (!$cart_id) {
            echo json_encode(['status' => 'error', 'message' => 'Cart not found']);
            exit;
        }

        // 2. 獲取總金額 (為了安全，後端重算一次)
        $sql_total = "SELECT SUM(Subtotal) as Total FROM cart_products WHERE Cart_ID = ?";
        $stmt = $this->conn->prepare($sql_total);
        $stmt->bind_param("s", $cart_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $subtotal = $row['Total'] ?? 0;
        
        if ($subtotal <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Cart is empty']);
            exit;
        }

        $tax = $subtotal * 0.06;
        $final_total = $subtotal + $tax;

        // 3. 生成訂單 (假設您有 orders 表)
        // 注意：這裡需要根據您的 IDHelper 生成 Order ID
        $order_id = IDHelper::generate($this->conn, 'orders', 'Order_ID', 'ORD');
        $current_time = date('Y-m-d H:i:s');
        $status = 'Pending'; // 或者 'Completed'，看您是否當作貨到付款

        $sql_order = "INSERT INTO orders (Order_ID, User_ID, Created_At, Total_Amount, Status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql_order);
        $stmt->bind_param("sisds", $order_id, $this->user_id, $current_time, $final_total, $status);

        if ($stmt->execute()) {
            // 4. 把購物車商品移到 order_details (假設您有這個表)
            // 這裡做一個簡單的 INSERT SELECT 操作
            $sql_move = "INSERT INTO order_details (Order_ID, Product_ID, Quantity, Subtotal)
                        SELECT ?, Product_ID, Quantity, Subtotal FROM cart_products WHERE Cart_ID = ?";
            $stmt_move = $this->conn->prepare($sql_move);
            $stmt_move->bind_param("ss", $order_id, $cart_id);
            $stmt_move->execute();

            // 5. 清空購物車
            $sql_clear = "DELETE FROM cart_products WHERE Cart_ID = ?";
            $stmt_clear = $this->conn->prepare($sql_clear);
            $stmt_clear->bind_param("s", $cart_id);
            $stmt_clear->execute();

            echo json_encode(['status' => 'success', 'message' => 'Order placed successfully', 'order_id' => $order_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create order']);
        }
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
                    <input type="checkbox" class="cart-item-checkbox" value="'.$product_id.'">
                    <img src="../../Image/Product/'.$image.'">
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
        $total = $subtotal + $tax;

        $summary_html = '
        <h1>RECEIPT</h1>
        <div class="receipt-items-container">' . $receipt_items_html . '</div>
        <div class="dashed-line"></div>
        <div class="cost-subtotal">
            <span>Subtotal</span>
            <span>RM '.number_format($subtotal, 2).'</span>
        </div>
        <div class="cost-tax">
            <span>Tax (6%)</span>
            <span>RM '.number_format($tax, 2).'</span>
        </div>
        <div class="dashed-line"></div>
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
                $stock = htmlspecialchars($row['Stock']);
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
                        <span>Remains: '.$stock.'</span>
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
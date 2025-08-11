<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}

// Xử lý thêm vào giỏ hàng
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_to_cart'])) {
    $menu_item_id = $_POST['menu_item_id'];
    $quantity = $_POST['quantity'];

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if (isset($_SESSION['cart'][$menu_item_id])) {
        $_SESSION['cart'][$menu_item_id] += $quantity;
    } else {
        $_SESSION['cart'][$menu_item_id] = $quantity;
    }
    $_SESSION['message'] = "Đã thêm vào giỏ hàng!";
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . (isset($_GET['category']) ? '?category=' . urlencode($_GET['category']) : '') . (isset($_GET['restaurant_id']) ? (isset($_GET['category']) ? '&' : '?') . 'restaurant_id=' . urlencode($_GET['restaurant_id']) : ''));
    exit();
}

// Xử lý đặt hàng
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    $customer_id = $_SESSION['user_id'];
    
    // Kiểm tra giỏ hàng không trống
    if (empty($_SESSION['cart'])) {
        $_SESSION['error'] = "Giỏ hàng trống!";
        header("Location: cart.php");
        exit();
    }

    // Kiểm tra tất cả sản phẩm cùng 1 nhà hàng
    $restaurant_ids = [];
    foreach (array_keys($_SESSION['cart']) as $menu_item_id) {
        $stmt = $conn->prepare("SELECT restaurant_id FROM menu_items WHERE id = ?");
        $stmt->execute([$menu_item_id]);
        $item = $stmt->fetch();
        $restaurant_ids[$item['restaurant_id']] = true;
    }

    if (count($restaurant_ids) > 1) {
        $_SESSION['error'] = "Không thể đặt món từ nhiều nhà hàng trong cùng 1 đơn hàng!";
        header("Location: cart.php");
        exit();
    }

    $restaurant_id = array_key_first($restaurant_ids);
    
    // Kiểm tra restaurant_id hợp lệ
    $stmt = $conn->prepare("SELECT id, name FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch();

    if (!$restaurant) {
        $_SESSION['error'] = "Nhà hàng không tồn tại!";
        header("Location: cart.php");
        exit();
    }

    $total_price = 0;

    // Tạo đơn hàng
    try {
        $conn->beginTransaction();
        
        // Tạo order
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, restaurant_id, total_price, status) 
                               VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$customer_id, $restaurant_id, 0]);
        $order_id = $conn->lastInsertId();

        // Thêm order items
        foreach ($_SESSION['cart'] as $menu_item_id => $quantity) {
            $stmt = $conn->prepare("SELECT price, name FROM menu_items WHERE id = ?");
            $stmt->execute([$menu_item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                throw new Exception("Món ăn không tồn tại!");
            }
            
            $price = $item['price'] * $quantity;
            $total_price += $price;

            $stmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$order_id, $menu_item_id, $quantity, $price]);
        }

        // Cập nhật tổng tiền
        $stmt = $conn->prepare("UPDATE orders SET total_price = ? WHERE id = ?");
        $stmt->execute([$total_price, $order_id]);
        
        $conn->commit();
        
        unset($_SESSION['cart']);
        $_SESSION['last_order_id'] = $order_id; // Thêm dòng này
        header("Location: order.php?order_id=$order_id"); // Sửa dòng này
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Có lỗi xảy ra khi đặt hàng: " . $e->getMessage();
        header("Location: cart.php");
        exit();
    }
}

// Xử lý tăng/giảm/xóa sản phẩm
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['increase'])) {
        $id = $_POST['menu_item_id'];
        $_SESSION['cart'][$id]++;
    }
    if (isset($_POST['decrease'])) {
        $id = $_POST['menu_item_id'];
        if ($_SESSION['cart'][$id] > 1) {
            $_SESSION['cart'][$id]--;
        } else {
            unset($_SESSION['cart'][$id]);
        }
    }
    if (isset($_POST['remove'])) {
        $id = $_POST['menu_item_id'];
        unset($_SESSION['cart'][$id]);
    }
}

// Lấy thông tin giỏ hàng
$cart_items = [];
$total_price = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    $stmt = $conn->query("SELECT * FROM menu_items WHERE id IN ($ids)");
    while ($item = $stmt->fetch()) {
        $item['quantity'] = $_SESSION['cart'][$item['id']];
        $item['total'] = $item['quantity'] * $item['price'];
        $cart_items[] = $item;
        $total_price += $item['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ hàng | ShopeeFood</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto py-10">
        <h1 class="text-3xl font-bold mb-8 text-orange-600 text-center">Giỏ hàng của bạn</h1>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($cart_items)): ?>
            <div class="bg-white p-8 rounded-lg shadow text-center text-gray-500">
                Giỏ hàng của bạn đang trống.
                <a href="customer.php" class="text-orange-500 hover:underline block mt-4">Tiếp tục mua sắm</a>
            </div>
        <?php else: ?>
            <div class="mb-4 bg-white p-4 rounded-lg shadow">
                <?php 
                $stmt = $conn->prepare("SELECT name FROM restaurants WHERE id = ?");
                $stmt->execute([$cart_items[0]['restaurant_id']]);
                $restaurant = $stmt->fetch();
                ?>
                <p class="font-semibold">Nhà hàng: <?= htmlspecialchars($restaurant['name']) ?></p>
                <p class="text-sm text-gray-500">Tất cả sản phẩm trong giỏ phải từ cùng 1 nhà hàng</p>
            </div>
            
            <form method="post" action="cart.php">
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <table class="w-full text-center">
                        <thead>
                            <tr class="bg-orange-100">
                                <th class="py-2">Ảnh</th>
                                <th>Tên món</th>
                                <th>Giá</th>
                                <th>Số lượng</th>
                                <th>Thành tiền</th>
                                <th>Xóa</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr class="border-b">
                                <td class="py-2">
                                    <img src="<?= htmlspecialchars($item['image_url'] ?? 'https://images.unsplash.com/photo-1504674900247-0877df9cc836') ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>" 
                                         class="w-16 h-16 object-cover rounded">
                                </td>
                                <td class="font-semibold"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="text-orange-600 font-bold"><?= number_format($item['price'], 0) ?>đ</td>
                                <td>
                                    <form method="post" action="cart.php" class="flex items-center justify-center gap-2">
                                        <input type="hidden" name="menu_item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="decrease" 
                                                class="px-2 py-1 bg-gray-200 rounded hover:bg-orange-200">-</button>
                                        <span class="px-2"><?= $item['quantity'] ?></span>
                                        <button type="submit" name="increase" 
                                                class="px-2 py-1 bg-gray-200 rounded hover:bg-orange-200">+</button>
                                    </form>
                                </td>
                                <td class="text-orange-500 font-bold"><?= number_format($item['total'], 0) ?>đ</td>
                                <td>
                                    <form method="post" action="cart.php">
                                        <input type="hidden" name="menu_item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="remove" 
                                                class="text-red-500 hover:underline">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-between items-center bg-white rounded-lg shadow p-6">
                    <div class="text-xl font-bold text-gray-700">
                        Tổng cộng: <span class="text-orange-600"><?= number_format($total_price, 0) ?>đ</span>
                    </div>
                    <button type="submit" name="place_order" 
                            class="bg-orange-500 text-white px-8 py-3 rounded-full font-bold hover:bg-orange-600">
                        Đặt hàng
                    </button>
                </div>
            </form>
        <?php endif; ?>
        
        <div class="mt-8 text-center">
            <a href="customer.php" class="text-orange-500 hover:underline">← Tiếp tục mua sắm</a>
        </div>
    </div>
</body>
</html>
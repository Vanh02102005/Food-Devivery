<?php
session_start();
require 'connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: login.php");
    exit();
}
$delivery_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'delivery'");
$stmt->execute([$delivery_id]);
$valid_shipper = $stmt->fetch();
if (!$valid_shipper) {
    $_SESSION['error'] = "Tài khoản không có quyền giao hàng";
    header("Location: login.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['start_delivery'])) {
        $order_id = $_POST['order_id'];
        // Kiểm tra đơn hàng có thuộc về shipper này không
        $stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND delivery_id = ? AND status = 'confirmed'");
        $stmt->execute([$order_id, $delivery_id]);
        $valid_order = $stmt->fetch();      
        if ($valid_order) {
            $stmt = $conn->prepare("UPDATE orders SET status = 'delivering' WHERE id = ? AND delivery_id = ?");
            $stmt->execute([$order_id, $delivery_id]);
            $_SESSION['message'] = "Đã bắt đầu giao đơn hàng #$order_id";
        } else {
            $_SESSION['error'] = "Đơn hàng không hợp lệ hoặc không thuộc quyền quản lý của bạn";
        }
    } 
    elseif (isset($_POST['complete_delivery'])) {
        $order_id = $_POST['order_id'];     
        $stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND delivery_id = ? AND status = 'delivering'");
        $stmt->execute([$order_id, $delivery_id]);
        $valid_order = $stmt->fetch();
        if ($valid_order) {
            $stmt = $conn->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND delivery_id = ?");
            $stmt->execute([$order_id, $delivery_id]);
            $_SESSION['message'] = "Đã hoàn thành giao đơn hàng #$order_id";
        } else {
            $_SESSION['error'] = "Đơn hàng không hợp lệ hoặc không thuộc quyền quản lý của bạn";
        }
    }
    header("Location: delivery.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giao Hàng - ShopeeFood</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-confirmed { background-color: #dbeafe; color: #1e40af; }
        .status-delivering { background-color: #e0f2fe; color: #0369a1; }
        .status-completed { background-color: #dcfce7; color: #166534; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="bg-orange-600 text-white p-4 sticky top-0 z-10">
        <div class="container mx-auto flex justify-between items-center">
            <div class="text-2xl font-bold">Giao Hàng ShopeeFood</div>
            <div class="flex space-x-4">
                <a href="delivery.php" class="hover:underline">Đơn hàng</a>
                <a href="logout.php" class="hover:underline">Đăng xuất</a>
            </div>
        </div>
    </nav>

    <!-- Delivery Orders -->
    <section class="container mx-auto py-8 px-4">
        <h2 class="text-3xl font-bold text-center mb-6">Đơn hàng được phân công</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <?= $_SESSION['message'] ?>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 gap-6">
            <?php
            $stmt = $conn->prepare("SELECT o.*, u.username, u.phone, u.address AS customer_address, 
                                   r.name AS restaurant_name, r.address AS restaurant_address, r.phone AS restaurant_phone
                                   FROM orders o 
                                   JOIN users u ON o.customer_id = u.id 
                                   JOIN restaurants r ON o.restaurant_id = r.id 
                                   WHERE o.delivery_id = ? AND o.status IN ('confirmed', 'delivering')
                                   ORDER BY FIELD(o.status, 'delivering', 'confirmed'), o.created_at DESC");
            $stmt->execute([$delivery_id]);
            $orders = $stmt->fetchAll();
            
            if (empty($orders)): ?>
                <div class="bg-white p-8 rounded-lg shadow text-center">
                    <i class="fas fa-box-open text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">Hiện không có đơn hàng nào</h3>
                    <p class="text-gray-500">Các đơn hàng mới sẽ xuất hiện ở đây khi được phân công</p>
                </div>
            <?php else: 
                foreach ($orders as $order): ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-xl font-bold">Đơn hàng #<?= $order['id'] ?></h3>
                                <p class="text-gray-500"><?= date('H:i d/m/Y', strtotime($order['created_at'])) ?></p>
                            </div>
                            <span class="status-badge status-<?= $order['status'] ?>">
                                <?= match($order['status']) {
                                    'confirmed' => 'Chờ lấy hàng',
                                    'delivering' => 'Đang giao hàng',
                                    default => $order['status']
                                } ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold mb-2 flex items-center">
                                    <i class="fas fa-store mr-2 text-orange-500"></i> Nhà hàng
                                </h4>
                                <p class="font-medium"><?= htmlspecialchars($order['restaurant_name']) ?></p>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($order['restaurant_address']) ?></p>
                                <p class="text-sm text-gray-600 mt-1">
                                    <i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($order['restaurant_phone']) ?>
                                </p>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold mb-2 flex items-center">
                                    <i class="fas fa-user mr-2 text-orange-500"></i> Khách hàng
                                </h4>
                                <p class="font-medium"><?= htmlspecialchars($order['username']) ?></p>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($order['customer_address']) ?></p>
                                <p class="text-sm text-gray-600 mt-1">
                                    <i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($order['phone']) ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center border-t pt-4">
                            <p class="text-lg font-bold text-orange-600">
                                Tổng tiền: <?= number_format($order['total_price'], 0) ?>đ
                            </p>
                            
                            <form method="POST" class="flex gap-2">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <?php if ($order['status'] == 'confirmed'): ?>
                                    <button type="submit" name="start_delivery" 
                                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 flex items-center">
                                        <i class="fas fa-truck mr-2"></i> Bắt đầu giao
                                    </button>
                                <?php elseif ($order['status'] == 'delivering'): ?>
                                    <button type="submit" name="complete_delivery" 
                                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center">
                                        <i class="fas fa-check mr-2"></i> Hoàn thành
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach;
            endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8">
        <div class="container mx-auto text-center">
            <p>ShopeeFood Delivery &copy; <?= date('Y') ?></p>
        </div>
    </footer>
</body>
</html>
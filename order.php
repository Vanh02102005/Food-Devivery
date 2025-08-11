<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order = null;
$order_items = [];
$error_message = '';

define('DEFAULT_DELIVERY_FEE', 15000);
define('DEFAULT_DISCOUNT', 0);

try {
    if ($order_id > 0) {
        $stmt = $conn->prepare("SELECT o.*, u.username, u.phone, u.address AS customer_address, 
                               r.name AS restaurant_name, r.address AS restaurant_address, r.phone AS restaurant_phone
                               FROM orders o 
                               JOIN users u ON o.customer_id = u.id 
                               JOIN restaurants r ON o.restaurant_id = r.id 
                               WHERE o.id = ? AND o.customer_id = ?");
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        $order = $stmt->fetch();

        if ($order) {
            $stmt = $conn->prepare("SELECT oi.*, mi.name, mi.image_url, mi.price 
                                   FROM order_items oi 
                                   JOIN menu_items mi ON oi.menu_item_id = mi.id 
                                   WHERE oi.order_id = ?");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll();
            
            $subtotal = array_reduce($order_items, function($carry, $item) {
                return $carry + ($item['price'] * $item['quantity']);
            }, 0);
            
            $order['subtotal'] = $subtotal;
            $order['delivery_fee'] = DEFAULT_DELIVERY_FEE;
            $order['discount_amount'] = DEFAULT_DISCOUNT;
            $order['total_amount'] = $subtotal + DEFAULT_DELIVERY_FEE - DEFAULT_DISCOUNT;
        } else {
            $error_message = "Không tìm thấy đơn hàng #$order_id hoặc bạn không có quyền xem đơn hàng này.";
        }
    } else {
        $error_message = "Thiếu thông tin mã đơn hàng.";
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "Đã xảy ra lỗi khi tải thông tin đơn hàng. Vui lòng thử lại sau.";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $order ? "Đơn hàng #$order_id" : "Không tìm thấy đơn hàng" ?> | Food Delivery</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #FF5C00;
            --primary-hover: #E55300;
            --danger: #EF4444;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FAFAFA;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            border: 1px solid #F3F4F6;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .status-pending { background-color: #FFFBEB; color: #D97706; }
        .status-confirmed { background-color: #EFF6FF; color: #1D4ED8; }
        .status-delivering { background-color: #ECFDF5; color: #047857; }
        .status-completed { background-color: #F5F3FF; color: #5B21B6; }
        .status-cancelled { background-color: #FEF2F2; color: #B91C1C; }
        
        .food-img {
            width: 72px;
            height: 72px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .price {
            font-weight: 600;
            color: var(--primary);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <?= $_SESSION['success'] ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Chi tiết đơn hàng</h1>
            <a href="customer.php#orders-section" class="text-orange-500 hover:text-orange-600 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Quay lại
            </a>
        </div>

        <?php if (!empty($error_message) || !$order): ?>
            <div class="card p-8 text-center max-w-md mx-auto">
                <div class="w-20 h-20 bg-orange-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-circle text-3xl text-orange-500"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800 mb-2"><?= empty($error_message) ? 'Không tìm thấy đơn hàng' : 'Có lỗi xảy ra' ?></h2>
                <p class="text-gray-500 mb-4"><?= $error_message ?: "Đơn hàng #$order_id không tồn tại hoặc không thuộc về tài khoản của bạn." ?></p>
                <a href="customer.php#orders-section" class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">
                    <i class="fas fa-list mr-2"></i> Xem đơn hàng của bạn
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Thông tin đơn hàng -->
                <div class="lg:col-span-2">
                    <div class="card p-6">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Đơn hàng #<?= htmlspecialchars($order['id']) ?></h2>
                                <p class="text-gray-500"><?= date('H:i d/m/Y', strtotime($order['created_at'])) ?></p>
                            </div>
                            <span class="status-badge status-<?= $order['status'] ?>">
                                <i class="fas fa-<?= 
                                    $order['status'] === 'pending' ? 'clock' : 
                                    ($order['status'] === 'confirmed' ? 'check-circle' : 
                                    ($order['status'] === 'delivering' ? 'truck' : 
                                    ($order['status'] === 'completed' ? 'check-circle' : 'times-circle')))
                                ?> mr-2"></i>
                                <?= match($order['status']) {
                                    'pending' => 'Chờ xác nhận',
                                    'confirmed' => 'Đã xác nhận',
                                    'delivering' => 'Đang giao hàng',
                                    'completed' => 'Hoàn thành',
                                    'cancelled' => 'Đã hủy',
                                    default => htmlspecialchars($order['status'])
                                } ?>
                            </span>
                        </div>

                        <!-- Chi tiết món hàng -->
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <i class="fas fa-utensils text-orange-500 mr-2"></i>
                            Chi tiết đơn hàng
                        </h3>
                        <div class="divide-y divide-gray-100">
                            <?php foreach ($order_items as $item): ?>
                                <div class="py-4 flex items-center hover:bg-gray-50 px-2 rounded">
                                    <div class="mr-4 flex-shrink-0">
                                        <img src="<?= !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'images/default-food.jpg' ?>" 
                                             alt="<?= htmlspecialchars($item['name']) ?>" 
                                             class="food-img">
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-medium text-gray-900 truncate"><?= htmlspecialchars($item['name']) ?></h4>
                                        <p class="text-sm text-gray-500"><?= number_format($item['price'], 0, ',', '.') ?>đ × <?= $item['quantity'] ?></p>
                                    </div>
                                    <div class="ml-4 font-medium price whitespace-nowrap">
                                        <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Tổng thanh toán -->
                        <div class="border-t border-gray-100 pt-4 mt-6">
                            <div class="flex justify-between py-2">
                                <span class="text-gray-600">Tạm tính:</span>
                                <span class="font-medium"><?= number_format($order['subtotal'], 0, ',', '.') ?>đ</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-gray-600">Phí vận chuyển:</span>
                                <span class="font-medium"><?= number_format($order['delivery_fee'], 0, ',', '.') ?>đ</span>
                            </div>
                            <?php if ($order['discount_amount'] > 0): ?>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">Giảm giá:</span>
                                    <span class="text-green-600 font-medium">-<?= number_format($order['discount_amount'], 0, ',', '.') ?>đ</span>
                                </div>
                            <?php endif; ?>
                            <div class="flex justify-between pt-4 mt-2 border-t border-gray-100">
                                <span class="font-semibold">Tổng cộng:</span>
                                <span class="text-lg font-bold price"><?= number_format($order['total_amount'], 0, ',', '.') ?>đ</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thông tin giao hàng và nhà hàng -->
                <div class="space-y-6">
                    <div class="card p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <i class="fas fa-map-marker-alt text-orange-500 mr-2"></i>
                            Thông tin giao hàng
                        </h3>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-500">Người nhận</p>
                                <p class="font-medium"><?= htmlspecialchars($order['username']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Điện thoại</p>
                                <p class="font-medium"><?= htmlspecialchars($order['phone']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Địa chỉ</p>
                                <p class="font-medium"><?= htmlspecialchars($order['customer_address']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="card p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <i class="fas fa-store text-orange-500 mr-2"></i>
                            Thông tin nhà hàng
                        </h3>
                        <div class="space-y-3 mb-4">
                            <div>
                                <p class="text-sm text-gray-500">Tên nhà hàng</p>
                                <p class="font-medium"><?= htmlspecialchars($order['restaurant_name']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Địa chỉ</p>
                                <p class="font-medium"><?= htmlspecialchars($order['restaurant_address']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Điện thoại</p>
                                <p class="font-medium"><?= htmlspecialchars($order['restaurant_phone']) ?></p>
                            </div>
                        </div>
                        <a href="restaurant.php?id=<?= $order['restaurant_id'] ?>" class="w-full inline-flex items-center justify-center px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">
                            <i class="fas fa-utensils mr-2"></i> Đặt lại từ nhà hàng này
                        </a>
                    </div>

                    <?php if ($order['status'] == 'pending'): ?>
                        <form method="post" action="cancel_order.php" class="card p-6">
                            <h3 class="text-lg font-semibold mb-4 flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                                Hủy đơn hàng
                            </h3>
                            <p class="text-gray-600 mb-4 text-sm">Bạn có thể hủy đơn hàng khi chưa được xác nhận.</p>
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600" 
                                    onclick="return confirm('Bạn có chắc chắn muốn hủy đơn hàng #<?= $order['id'] ?>?')">
                                <i class="fas fa-times-circle mr-2"></i> Hủy đơn hàng
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
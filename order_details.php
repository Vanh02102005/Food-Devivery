<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Hiển thị thông báo nếu có
if (isset($_GET['order_success'])) {
    $success_message = "Đơn hàng của bạn đã được đặt thành công!";
}

// Lấy danh sách đơn hàng
$stmt = $conn->prepare("SELECT o.*, r.name AS restaurant_name 
                       FROM orders o 
                       JOIN restaurants r ON o.restaurant_id = r.id 
                       WHERE o.customer_id = ? 
                       ORDER BY o.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng của tôi | ShopeeFood</title>
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
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Đơn hàng của tôi</h1>
            <a href="customer.php" class="text-orange-500 hover:text-orange-600 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Quay lại
            </a>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <?= $success_message ?>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="bg-white p-8 rounded-lg shadow text-center">
                <i class="fas fa-box-open text-5xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Bạn chưa có đơn hàng nào</h3>
                <p class="text-gray-500 mb-4">Hãy đặt món ngay để trải nghiệm dịch vụ của chúng tôi!</p>
                <a href="customer.php" class="inline-block px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">
                    <i class="fas fa-utensils mr-2"></i> Đặt món ngay
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-orange-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã đơn</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nhà hàng</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày đặt</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tổng tiền</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">#<?= $order['id'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($order['restaurant_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= date('H:i d/m/Y', strtotime($order['created_at'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right"><?= number_format($order['total_price'], 0) ?>đ</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="status-badge status-<?= $order['status'] ?>">
                                        <?= match($order['status']) {
                                            'pending' => 'Chờ xác nhận',
                                            'confirmed' => 'Đã xác nhận',
                                            'delivering' => 'Đang giao hàng',
                                            'completed' => 'Hoàn thành',
                                            'cancelled' => 'Đã hủy',
                                            default => $order['status']
                                        } ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <a href="order.php?order_id=<?= $order['id'] ?>" class="text-orange-500 hover:text-orange-600 hover:underline">
                                        Xem chi tiết
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
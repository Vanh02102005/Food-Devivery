<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'connect.php';
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'restaurant') {
    header("Location: login.php");
    exit();
}
$stmt = $conn->prepare("SELECT id, name FROM restaurants WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$restaurant = $stmt->fetch();

if (!$restaurant) {
    die("Không tìm thấy thông tin nhà hàng");
}
$restaurant_id = $restaurant['id'];
$restaurant_name = $restaurant['name'];
// Xử lý doanh thu
$revenue_by_date = [];
$stmt = $conn->prepare("SELECT DATE(created_at) as order_date, SUM(total_price) as daily_revenue 
                       FROM orders 
                       WHERE restaurant_id = ? AND status = 'completed'
                       GROUP BY DATE(created_at) 
                       ORDER BY order_date DESC");
$stmt->execute([$restaurant_id]);
$revenue_by_date = $stmt->fetchAll();

$total_revenue = 0;
foreach ($revenue_by_date as $revenue) {
    $total_revenue += $revenue['daily_revenue'];
}
// Xử lý form POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['confirm_order'])) {
        $order_id = $_POST['order_id'];
        // Tìm tài xế ít đơn nhất
        $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'delivery' 
                               ORDER BY (SELECT COUNT(*) FROM orders WHERE delivery_id = users.id AND status = 'delivering') ASC 
                               LIMIT 1");
        $stmt->execute();
        $delivery_person = $stmt->fetch();       
        if ($delivery_person) {
            $delivery_id = $delivery_person['id'];
            $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed', delivery_id = ? WHERE id = ? AND restaurant_id = ?");
            if ($stmt->execute([$delivery_id, $order_id, $restaurant_id])) {
                $_SESSION['message'] = "Đã xác nhận đơn hàng #$order_id và phân công người giao";
            } else {
                $_SESSION['error'] = "Lỗi khi cập nhật đơn hàng";
            }
        } else {
            $_SESSION['error'] = "Không có người giao hàng nào khả dụng";
        }
        header("Location: restaurant.php?tab=orders&order_id=$order_id");
        exit();
    } 
    elseif (isset($_POST['cancel_order'])) {
        $order_id = $_POST['order_id'];
        $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$order_id, $restaurant_id]);
        $_SESSION['message'] = "Đã hủy đơn hàng #$order_id";
        header("Location: restaurant.php?tab=orders&order_id=$order_id");
        exit();
    }
    elseif (isset($_POST['save_menu_item'])) {
        $id = isset($_POST['menu_item_id']) ? intval($_POST['menu_item_id']) : 0;
        $name = trim($_POST['name'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $description = trim($_POST['description'] ?? '');  
        $errors = [];
        if (empty($name)) $errors[] = 'Tên món ăn không được trống';
        if (!is_numeric($price) || $price <= 0) $errors[] = 'Giá tiền không hợp lệ';

        $image_url = $_POST['current_image'] ?? 'default-food.jpg';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_ext, $allowed_types)) {
                $file_name = uniqid('food_') . '.' . $file_ext;
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $image_url = $target_path;
                } else {
                    $errors[] = 'Lỗi khi lưu ảnh';
                }
            } else {
                $errors[] = 'Chỉ chấp nhận ảnh JPG, PNG hoặc GIF';
            }
        }

        if (empty($errors)) {
            try {
                if ($id > 0) {
                    $stmt = $conn->prepare("UPDATE menu_items SET name=?, price=?, description=?, image_url=? 
                                          WHERE id=? AND restaurant_id=?");
                    $stmt->execute([$name, $price, $description, $image_url, $id, $restaurant_id]);
                    $_SESSION['message'] = "Cập nhật món ăn thành công!";
                } else {
                    $stmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, name, price, description, image_url) 
                                          VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$restaurant_id, $name, $price, $description, $image_url]);
                    $_SESSION['message'] = "Thêm món ăn thành công!";
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Lỗi database: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
        header("Location: restaurant.php?tab=menu");
        exit();
    }
    elseif (isset($_POST['delete_menu_item'])) {
        $id = intval($_POST['menu_item_id']);
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE id=? AND restaurant_id=?");
        $stmt->execute([$id, $restaurant_id]);
        $_SESSION['message'] = "Đã xóa món ăn!";
        header("Location: restaurant.php?tab=menu");
        exit();
    }
}
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'orders';
// Xử lý tab đơn hàng
if ($current_tab == 'orders') {
    if (isset($_GET['order_id'])) {
        $order_id = intval($_GET['order_id']);
        
        $stmt = $conn->prepare("SELECT o.*, u.username, u.address AS customer_address, u.phone
                               FROM orders o 
                               JOIN users u ON o.customer_id = u.id 
                               WHERE o.id = ? AND o.restaurant_id = ?");
        $stmt->execute([$order_id, $restaurant_id]);
        $order_detail = $stmt->fetch();
        
        if ($order_detail) {
            $stmt = $conn->prepare("SELECT oi.*, mi.name, mi.image_url, mi.price AS unit_price
                                   FROM order_items oi 
                                   JOIN menu_items mi ON oi.menu_item_id = mi.id 
                                   WHERE oi.order_id = ?");
            $stmt->execute([$order_id]);
            $order_detail['items'] = $stmt->fetchAll();
            
            $total = 0;
            foreach ($order_detail['items'] as $item) {
                $total += $item['price'];
            }
            $order_detail['total_price'] = $total;
        }
    } else {
        $stmt = $conn->prepare("SELECT o.*, u.username 
                               FROM orders o 
                               JOIN users u ON o.customer_id = u.id 
                               WHERE o.restaurant_id = ? 
                               ORDER BY o.created_at DESC");
        $stmt->execute([$restaurant_id]);
        $orders = $stmt->fetchAll();
    }
} 
// Xử lý tab menu
elseif ($current_tab == 'menu') {
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE restaurant_id = ? ORDER BY name");
    $stmt->execute([$restaurant_id]);
    $menu_items = $stmt->fetchAll();
    $edit_item = [
        'id' => 0,
        'name' => '',
        'price' => '',
        'description' => '',
        'image_url' => 'default-food.jpg'
    ];
    if (isset($_GET['edit_item'])) {
        $item_id = intval($_GET['edit_item']);
        if ($item_id > 0) {
            $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$item_id, $restaurant_id]);
            $item = $stmt->fetch();
            if ($item) $edit_item = $item;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($restaurant_name); ?> - Quản lý nhà hàng</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .item-img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; }
        .card { transition: all 0.2s ease; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .tab-active { background-color: #fef2f2; color: #dc2626; font-weight: 600; }
        .logout-btn { 
            position: fixed; 
            bottom: 20px; 
            left: 20px; 
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .revenue-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 10px;
        }
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
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow p-4 relative h-screen sticky top-0">
            <h1 class="text-xl font-bold text-red-600 mb-8"><?php echo htmlspecialchars($restaurant_name); ?></h1>
            <nav class="space-y-2">
                <a href="?tab=orders" class="block py-2 px-4 rounded hover:bg-red-50 <?php echo $current_tab=='orders'?'tab-active':'text-gray-700'; ?>">
                    <i class="fas fa-list-alt mr-2"></i> Đơn hàng
                </a>
                <a href="?tab=menu" class="block py-2 px-4 rounded hover:bg-gray-100 <?php echo $current_tab=='menu'?'tab-active':'text-gray-700'; ?>">
                    <i class="fas fa-utensils mr-2"></i> Quản lý menu
                </a>
                <a href="?tab=revenue" class="block py-2 px-4 rounded hover:bg-gray-100 <?php echo $current_tab=='revenue'?'tab-active':'text-gray-700'; ?>">
                    <i class="fas fa-chart-line mr-2"></i> Doanh thu
                </a>
            </nav>

            <a href="?logout=1" class="logout-btn py-2 px-4 rounded text-white bg-red-500 hover:bg-red-600">
                <i class="fas fa-sign-out-alt"></i>
                <span>Đăng xuất</span>
            </a>
        </div>

        <!-- Main content -->
        <div class="flex-1 p-8">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                    <?php echo $_SESSION['message']; ?>
                    <?php unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <?php echo $_SESSION['error']; ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($current_tab == 'orders'): ?>
                <?php if (isset($_GET['order_id']) && isset($order_detail) && $order_detail): ?>
                    <!-- Chi tiết đơn hàng -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <a href="?tab=orders" class="inline-flex items-center text-blue-500 mb-4">
                            <i class="fas fa-arrow-left mr-2"></i> Quay lại danh sách
                        </a>
                        
                        <h2 class="text-2xl font-bold mb-4">Đơn hàng #<?php echo $order_detail['id']; ?></h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-semibold mb-2 flex items-center">
                                    <i class="fas fa-user mr-2"></i> Thông tin khách hàng
                                </h3>
                                <p><span class="font-medium">Tên:</span> <?php echo htmlspecialchars($order_detail['username']); ?></p>
                                <?php if (!empty($order_detail['customer_address'])): ?>
                                <p><span class="font-medium">Địa chỉ:</span> <?php echo htmlspecialchars($order_detail['customer_address']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($order_detail['phone'])): ?>
                                <p><span class="font-medium">Điện thoại:</span> <?php echo htmlspecialchars($order_detail['phone']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-semibold mb-2 flex items-center">
                                    <i class="fas fa-info-circle mr-2"></i> Thông tin đơn hàng
                                </h3>
                                <p><span class="font-medium">Ngày đặt:</span> <?php echo date('d/m/Y H:i', strtotime($order_detail['created_at'])); ?></p>
                                <p><span class="font-medium">Trạng thái:</span> 
                                    <span class="status-badge status-<?= $order_detail['status'] ?>">
                                        <?= match($order_detail['status']) {
                                            'pending' => 'Chờ xác nhận',
                                            'confirmed' => 'Đã xác nhận',
                                            'delivering' => 'Đang giao hàng',
                                            'completed' => 'Hoàn thành',
                                            'cancelled' => 'Đã hủy',
                                            default => $order_detail['status']
                                        } ?>
                                    </span>
                                </p>
                                <p><span class="font-medium">Tổng tiền:</span> 
                                    <span class="text-orange-600 font-bold">
                                        <?php echo number_format($order_detail['total_price'], 0); ?> VND
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <h3 class="font-semibold text-lg mb-3 flex items-center">
                            <i class="fas fa-list-ul mr-2"></i> Danh sách món ăn
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white rounded-lg overflow-hidden">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="py-3 px-4 text-left">Món ăn</th>
                                        <th class="py-3 px-4 text-center">Số lượng</th>
                                        <th class="py-3 px-4 text-right">Đơn giá</th>
                                        <th class="py-3 px-4 text-right">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_detail['items'] as $item): ?>
                                    <tr class="border-b">
                                        <td class="py-3 px-4 flex items-center">
                                            <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'default-food.jpg'); ?>" 
                                                 class="item-img mr-3">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </td>
                                        <td class="py-3 px-4 text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="py-3 px-4 text-right"><?php echo number_format($item['unit_price'], 0); ?> VND</td>
                                        <td class="py-3 px-4 text-right"><?php echo number_format($item['price'], 0); ?> VND</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="bg-gray-50 font-bold">
                                        <td colspan="3" class="py-3 px-4 text-right">Tổng cộng:</td>
                                        <td class="py-3 px-4 text-right"><?php echo number_format($order_detail['total_price'], 0); ?> VND</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($order_detail['status'] == 'pending'): ?>
                        <div class="mt-6 flex justify-end gap-3">
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?php echo $order_detail['id']; ?>">
                                <button type="submit" name="cancel_order" 
                                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 flex items-center">
                                    <i class="fas fa-times mr-2"></i> Hủy đơn hàng
                                </button>
                                <button type="submit" name="confirm_order" 
                                        class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 flex items-center">
                                    <i class="fas fa-check mr-2"></i> Xác nhận đơn hàng
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Danh sách đơn hàng -->
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold flex items-center">
                            <i class="fas fa-list-alt mr-2"></i> Danh sách đơn hàng
                        </h1>
                    </div>
                    
                    <?php if (empty($orders)): ?>
                        <p class="text-center text-gray-500 py-8">
                            <i class="fas fa-inbox text-4xl mb-2 block"></i>
                            Chưa có đơn hàng nào
                        </p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($orders as $order): ?>
                            <a href="?order_id=<?php echo $order['id']; ?>&tab=orders" class="block">
                                <div class="card bg-white p-4 rounded-lg shadow">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-bold">Đơn hàng #<?php echo $order['id']; ?></p>
                                            <p class="text-gray-600 flex items-center">
                                                <i class="fas fa-user mr-1"></i> <?php echo $order['username']; ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="<?php 
                                                echo $order['status']=='pending'?'text-yellow-600':
                                                ($order['status']=='confirmed'?'text-green-600':
                                                ($order['status']=='delivering'?'text-blue-600':
                                                ($order['status']=='completed'?'text-green-600':
                                                ($order['status']=='cancelled'?'text-red-600':'text-gray-600'))));
                                            ?> font-medium">
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
                                            </p>
                                            <p class="text-sm text-gray-500 flex items-center justify-end">
                                                <i class="fas fa-clock mr-1"></i> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-2 pt-2 border-t flex justify-between items-center">
                                        <p class="font-medium"><?php echo number_format($order['total_price'], 0); ?> VND</p>
                                        <span class="text-blue-500 flex items-center">
                                            Xem chi tiết <i class="fas fa-arrow-right ml-1"></i>
                                        </span>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php elseif ($current_tab == 'menu'): ?>
                <!-- Quản lý menu -->
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold flex items-center">
                        <i class="fas fa-utensils mr-2"></i> Quản lý Menu
                    </h1>
                    <a href="?tab=menu&edit_item=0" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 flex items-center">
                        <i class="fas fa-plus mr-2"></i> Thêm món mới
                    </a>
                </div>
                
                <?php if (isset($_GET['edit_item'])): ?>
                    <!-- Form thêm/sửa món ăn -->
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h2 class="text-xl font-bold mb-4 flex items-center">
                            <i class="fas fa-<?php echo $edit_item['id'] ? 'edit' : 'plus'; ?> mr-2"></i> 
                            <?php echo $edit_item['id'] ? 'Sửa' : 'Thêm'; ?> món ăn
                        </h2>
                        
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="menu_item_id" value="<?php echo $edit_item['id']; ?>">
                            <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($edit_item['image_url']); ?>">
                            
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-tag mr-1"></i> Tên món ăn *
                                </label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($edit_item['name']); ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500" required>
                            </div>
                            
                            <div>
                                <label for="price" class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-money-bill-wave mr-1"></i> Giá tiền (VND) *
                                </label>
                                <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($edit_item['price']); ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500" 
                                       required min="1000" step="1000">
                            </div>
                            
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-align-left mr-1"></i> Mô tả
                                </label>
                                <textarea id="description" name="description" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"><?php echo htmlspecialchars($edit_item['description']); ?></textarea>
                            </div>
                            
                            <div>
                                <label for="image" class="block text-sm font-medium text-gray-700">
                                    <i class="fas fa-image mr-1"></i> Ảnh món ăn
                                </label>
                                <?php if (!empty($edit_item['image_url']) && $edit_item['image_url'] != 'default-food.jpg'): ?>
                                    <img src="<?php echo htmlspecialchars($edit_item['image_url']); ?>" class="w-32 h-32 object-cover mb-2 rounded">
                                <?php endif; ?>
                                <input type="file" id="image" name="image" 
                                       class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                                <p class="mt-1 text-sm text-gray-500">Chỉ chấp nhận ảnh JPG, PNG hoặc GIF</p>
                            </div>
                            
                            <div class="flex justify-end space-x-3 pt-4">
                                <a href="?tab=menu" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center">
                                    <i class="fas fa-times mr-2"></i> Hủy bỏ
                                </a>
                                <button type="submit" name="save_menu_item" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 flex items-center">
                                    <i class="fas fa-save mr-2"></i> Lưu món ăn
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Danh sách món ăn -->
                <?php if (empty($menu_items)): ?>
                    <p class="text-center text-gray-500 py-8">
                        <i class="fas fa-utensils text-4xl mb-2 block"></i>
                        Chưa có món nào trong menu
                    </p>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table class="min-w-full">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="py-3 px-4 text-left">Ảnh</th>
                                    <th class="py-3 px-4 text-left">Tên món</th>
                                    <th class="py-3 px-4 text-left">Giá</th>
                                    <th class="py-3 px-4 text-left">Mô tả</th>
                                    <th class="py-3 px-4 text-left">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menu_items as $item): ?>
                                <tr class="border-b">
                                    <td class="py-3 px-4">
                                        <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'default-food.jpg'); ?>" 
                                             class="item-img">
                                    </td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td class="py-3 px-4"><?php echo number_format($item['price'], 0); ?> VND</td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
                                    <td class="py-3 px-4">
                                        <a href="?tab=menu&edit_item=<?php echo $item['id']; ?>" class="text-blue-500 hover:underline mr-2">
                                            <i class="fas fa-edit mr-1"></i> Sửa
                                        </a>
                                        <form method="POST" class="inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa món này?')">
                                            <input type="hidden" name="menu_item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="delete_menu_item" class="text-red-500 hover:underline">
                                                <i class="fas fa-trash-alt mr-1"></i> Xóa
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php elseif ($current_tab == 'revenue'): ?>
                <!-- Phần doanh thu -->
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold flex items-center">
                        <i class="fas fa-chart-line mr-2"></i> Doanh thu theo ngày
                    </h1>
                </div>
                
                <!-- Thống kê tổng quan -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="revenue-card p-6 shadow">
                        <h3 class="text-lg font-medium text-gray-600 mb-2 flex items-center">
                            <i class="fas fa-calendar-day mr-2"></i> Ngày hôm nay
                        </h3>
                        <?php
                        $today = date('Y-m-d');
                        $today_revenue = 0;
                        foreach ($revenue_by_date as $revenue) {
                            if ($revenue['order_date'] == $today) {
                                $today_revenue = $revenue['daily_revenue'];
                                break;
                            }
                        }
                        ?>
                        <p class="text-2xl font-bold"><?php echo number_format($today_revenue, 0); ?> VND</p>
                    </div>
                    
                    <div class="revenue-card p-6 shadow">
                        <h3 class="text-lg font-medium text-gray-600 mb-2 flex items-center">
                            <i class="fas fa-calendar-week mr-2"></i> 7 ngày gần nhất
                        </h3>
                        <?php
                        $last7days_revenue = 0;
                        $last7days = date('Y-m-d', strtotime('-7 days'));
                        foreach ($revenue_by_date as $revenue) {
                            if ($revenue['order_date'] >= $last7days) {
                                $last7days_revenue += $revenue['daily_revenue'];
                            }
                        }
                        ?>
                        <p class="text-2xl font-bold"><?php echo number_format($last7days_revenue, 0); ?> VND</p>
                    </div>
                    
                    <div class="revenue-card p-6 shadow">
                        <h3 class="text-lg font-medium text-gray-600 mb-2 flex items-center">
                            <i class="fas fa-coins mr-2"></i> Tổng doanh thu
                        </h3>
                        <p class="text-2xl font-bold"><?php echo number_format($total_revenue, 0); ?> VND</p>
                    </div>
                </div>
                
                <?php if (empty($revenue_by_date)): ?>
                    <p class="text-center text-gray-500 py-8">
                        <i class="fas fa-chart-pie text-4xl mb-2 block"></i>
                        Chưa có dữ liệu doanh thu
                    </p>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table class="min-w-full">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="py-3 px-4 text-left">Ngày</th>
                                    <th class="py-3 px-4 text-right">Doanh thu</th>
                                    <th class="py-3 px-4 text-right">% Tổng doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($revenue_by_date as $revenue): ?>
                                <tr class="border-b">
                                    <td class="py-3 px-4">
                                        <i class="fas fa-calendar-day mr-2"></i> <?php echo date('d/m/Y', strtotime($revenue['order_date'])); ?>
                                    </td>
                                    <td class="py-3 px-4 text-right"><?php echo number_format($revenue['daily_revenue'], 0); ?> VND</td>
                                    <td class="py-3 px-4 text-right">
                                        <?php echo $total_revenue > 0 ? number_format(($revenue['daily_revenue'] / $total_revenue * 100), 1) : 0; ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="bg-gray-50 font-bold">
                                    <td class="py-3 px-4">
                                        <i class="fas fa-calculator mr-2"></i> Tổng cộng
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <?php echo number_format($total_revenue, 0); ?> VND
                                    </td>
                                    <td class="py-3 px-4 text-right">100%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
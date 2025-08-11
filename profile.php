<?php
session_start();
require 'connect.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Lấy thông tin người dùng
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Kiểm tra chế độ chỉnh sửa
$edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';

// Xử lý cập nhật thông tin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $username = $_POST['name'];
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    $update_stmt = $conn->prepare("UPDATE users SET username = ?, phone = ?, address = ? WHERE id = ?");
    if ($update_stmt->execute([$username, $phone, $address, $user_id])) {
        $_SESSION['message'] = "Cập nhật thông tin thành công!";
        header("Location: profile.php");
        exit();
    } else {
        $error = "Có lỗi xảy ra khi cập nhật thông tin";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_mode ? 'Chỉnh sửa' : 'Thông tin' ?> tài khoản - ShopeeFood</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #FF5C00;
            --primary-hover: #E55300;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FAFAFA;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="bg-gradient-to-r from-orange-500 to-orange-600 text-white p-4 sticky top-0 z-20 shadow-lg">
        <div class="container mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-8">
                <a href="customer.php" class="text-2xl font-bold tracking-tight">ShopeeFood</a>
                <div class="hidden md:flex items-center space-x-6">
                    <a href="customer.php" class="hover:text-orange-200 transition-colors">Trang chủ</a>
                    <a href="order.php" class="hover:text-orange-200 transition-colors">Đơn hàng</a>
                    <a href="#contact" class="hover:text-orange-200 transition-colors">Liên hệ</a>
                </div>
            </div>
            
            <div class="flex items-center space-x-4 md:space-x-6">
                <a href="profile.php" class="hover:text-orange-200 hidden sm:block transition-colors">
                    <i class="fa fa-user"></i> <?= htmlspecialchars($user['name'] ?? 'Tài khoản') ?>
                </a>
                <a href="logout.php" class="hover:text-orange-200 transition-colors">Đăng xuất</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">
                    <?= $edit_mode ? 'Chỉnh sửa thông tin' : 'Thông tin tài khoản' ?>
                </h1>
                
                <?php if (!$edit_mode): ?>
                    <a href="profile.php?edit=true" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-full transition-colors">
                        <i class="fas fa-edit mr-2"></i> Chỉnh sửa
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Hiển thị thông báo -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($_SESSION['message']) ?>
                    <?php unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <!-- Phần thông tin cá nhân -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <?php if ($edit_mode): ?>
                    <!-- Form chỉnh sửa -->
                    <form method="post" action="profile.php">
                        <div class="space-y-4">
                            <div>
                                <label for="name" class="block text-gray-600 text-sm mb-1">Họ tên</label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['username']) ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-600 text-sm mb-1">Email</label>
                                <p class="text-gray-800"><?= htmlspecialchars($user['email']) ?></p>
                                <p class="text-xs text-gray-500 mt-1">Liên hệ hỗ trợ để thay đổi email</p>
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-gray-600 text-sm mb-1">Số điện thoại</label>
                                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            </div>
                            
                            <div>
                                <label for="address" class="block text-gray-600 text-sm mb-1">Địa chỉ</label>
                                <textarea id="address" name="address" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="flex justify-end space-x-3 pt-4">
                                <a href="profile.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md transition-colors">
                                    Hủy bỏ
                                </a>
                                <button type="submit" name="update_profile" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-md transition-colors">
                                    Lưu thay đổi
                                </button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Hiển thị thông tin -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-600 text-sm mb-1">Họ tên</label>
                            <p class="text-gray-800 font-medium"><?= htmlspecialchars($user['username']) ?></p>
                        </div>
                        
                        <div>
                            <label class="block text-gray-600 text-sm mb-1">Email</label>
                            <p class="text-gray-800 font-medium"><?= htmlspecialchars($user['email']) ?></p>
                        </div>
                        
                        <div>
                            <label class="block text-gray-600 text-sm mb-1">Số điện thoại</label>
                            <p class="text-gray-800 font-medium"><?= htmlspecialchars($user['phone'] ?? 'Chưa cập nhật') ?></p>
                        </div>
                        
                        <div>
                            <label class="block text-gray-600 text-sm mb-1">Địa chỉ</label>
                            <p class="text-gray-800 font-medium"><?= htmlspecialchars($user['address'] ?? 'Chưa cập nhật') ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer id="contact" class="bg-orange-600 text-white py-12">
        <div class="container mx-auto px-4 grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
            <div>
                <h3 class="text-lg font-bold mb-4">ShopeeFood</h3>
                <p class="text-sm">Giao đồ ăn nhanh chóng, tiện lợi, mọi lúc mọi nơi.</p>
            </div>
            <div>
                <h3 class="text-lg font-bold mb-4">Liên hệ</h3>
                <p class="text-sm">Email: support@shopeefood.com</p>
                <p class="text-sm">Hotline: +84 123 456 789</p>
            </div>
            <div>
                <h3 class="text-lg font-bold mb-4">Theo dõi chúng tôi</h3>
                <div class="flex justify-center gap-4">
                    <a href="#" class="text-white hover:text-orange-200 transition-colors"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-white hover:text-orange-200 transition-colors"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-white hover:text-orange-200 transition-colors"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        <p class="text-center text-sm mt-8">© <?= date('Y') ?> ShopeeFood. Tất cả quyền được bảo lưu.</p>
    </footer>
</body>
</html>
<?php
session_start();
require 'connect.php';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_to_cart'])) {
    $menu_item_id = $_POST['menu_item_id'];
    $quantity = (int)$_POST['quantity'];
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if (isset($_GET['order_success'])) {
        $_SESSION['message'] = "Đơn hàng của bạn đã được đặt thành công!";
        header("Location: order.php?order_id=" . ($_SESSION['last_order_id'] ?? ''));
        exit();
    }
    if (isset($_SESSION['cart'][$menu_item_id])) {
        $_SESSION['cart'][$menu_item_id] += $quantity;
    } else {
        $_SESSION['cart'][$menu_item_id] = $quantity;
    }
    $_SESSION['message'] = "Đã thêm vào giỏ hàng!";
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . 
          (isset($_GET['category']) ? '?category=' . urlencode($_GET['category']) : '') . 
          (isset($_GET['restaurant_id']) ? (isset($_GET['category']) ? '&' : '?') . 'restaurant_id=' . urlencode($_GET['restaurant_id']) : ''));
    exit();
}
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchResults = [];
$showSearchResults = false;
if (!empty($searchTerm)) {
    $searchParam = "%" . $searchTerm . "%";
    $stmt = $conn->prepare("
        SELECT mi.*, r.name AS restaurant_name 
        FROM menu_items mi 
        JOIN restaurants r ON mi.restaurant_id = r.id 
        WHERE mi.name LIKE ? OR mi.description LIKE ? OR r.name LIKE ?
    ");
    $stmt->execute([$searchParam, $searchParam, $searchParam]);
    $searchResults = $stmt->fetchAll();
    $showSearchResults = true;
}
$user_id = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShopeeFood - Giao Đồ Ăn</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            transition: opacity 0.3s ease;
        }
        
        .banner-container {
            position: relative;
            height: 400px;
            overflow: hidden;
            background-color: var(--primary);
        }
        
        .banner-img {
            position: absolute;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: opacity 1s ease-in-out;
        }
        
        .banner-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.3) 100%);
            z-index: 1;
        }
        
        .banner-content {
            position: relative;
            z-index: 2;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .cart-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }
        
        .cart-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }
        
        .cart-btn .fa-shopping-cart {
            font-size: 1.5rem;
        }
        
        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .order-status {
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
        
        .food-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .food-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .popup-message {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            animation: slideIn 0.3s ease-out, fadeOut 0.5s ease-in 2.5s forwards;
        }
        
        @keyframes slideIn {
            from { bottom: -50px; opacity: 0; }
            to { bottom: 20px; opacity: 1; }
        }
        
        @keyframes fadeOut {
            to { opacity: 0; visibility: hidden; }
        }
        
        .category-card {
            transition: all 0.2s ease;
        }
        
        .category-card:hover {
            transform: scale(1.05);
        }
        
        .filter-btn {
            transition: all 0.2s ease;
        }
        
        .filter-btn:hover {
            background-color: var(--primary) !important;
            color: white !important;
        }
        .search-highlight {
            background-color: #FFEDD5;
            padding: 0.1em 0.2em;
            border-radius: 0.2em;
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
                <a href="order_details.php" class="hover:text-orange-200 transition-colors">Đơn hàng</a>
                <a href="#contact" class="hover:text-orange-200 transition-colors">Liên hệ</a>
            </div>
        </div>
            
            <div class="flex-1 flex justify-center mx-4">
                <form method="get" action="customer.php" class="relative w-full max-w-md">
                    <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" 
                           placeholder="Tìm món ăn, nhà hàng..." 
                           class="w-full p-3 pl-12 pr-4 rounded-full border border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white text-gray-800 placeholder-gray-400" />
                    <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-orange-500">
                        <i class="fa fa-search"></i>
                    </span>
                </form>
            </div>
            
            <div class="flex items-center space-x-4 md:space-x-6">
                <?php if ($user_id): ?>
                    <a href="profile.php" class="hover:text-orange-200 hidden sm:block transition-colors"><i class="fa fa-user"></i> Tài khoản</a>
                    <a href="logout.php" class="hover:text-orange-200 transition-colors">Đăng xuất</a>
                <?php else: ?>
                    <a href="login.php" class="hover:text-orange-200 transition-colors">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <section id="home" class="banner-container flex items-center justify-center">
        <div id="banner-carousel" class="absolute inset-0 w-full h-full">
            <img src="images/banner1.jpg" class="banner-img opacity-100" style="z-index:1;" 
                 onerror="this.onerror=null;this.src='/Test/SDLC Picture/Caesar Salad.jpg'">
            <img src="images/banner2.jpg" class="banner-img opacity-0" style="z-index:1;" 
                 onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1504674900247-0877df9cc836'">
            <div class="banner-overlay"></div>
        </div>
        <div class="banner-content text-center p-8 rounded-lg">
            <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight mb-4">Thưởng thức món ngon, giao hàng cực nhanh!</h1>
            <p class="text-lg md:text-xl mb-6">Hàng ngàn món ăn từ các nhà hàng yêu thích đang chờ bạn.</p>
            <!-- Sửa nút Đặt ngay để về trang chủ -->
            <a href="customer.php" class="inline-block bg-white text-orange-500 py-3 px-8 rounded-full hover:bg-gray-100 font-semibold shadow-lg transition-colors">
                Đặt ngay
            </a>
        </div>
        <button id="prevBanner" class="absolute left-4 top-1/2 -translate-y-1/2 bg-white bg-opacity-40 hover:bg-opacity-70 text-white rounded-full p-3 z-20 transition-all">
            <i class="fa fa-chevron-left"></i>
        </button>
        <button id="nextBanner" class="absolute right-4 top-1/2 -translate-y-1/2 bg-white bg-opacity-40 hover:bg-opacity-70 text-white rounded-full p-3 z-20 transition-all">
            <i class="fa fa-chevron-right"></i>
        </button>
    </section>

    <!-- Promotion Banner -->
    <section class="bg-orange-100 border-l-4 border-orange-500 text-orange-800 p-4 my-6 mx-4 rounded">
        <div class="container mx-auto flex items-center justify-center">
            <i class="fas fa-tag text-orange-500 text-xl mr-3"></i>
            <p class="text-base md:text-lg font-semibold">Giảm 50% cho đơn hàng đầu tiên! Nhập mã <span class="font-bold bg-orange-500 text-white px-2 py-1 rounded">NEW50</span></p>
        </div>
    </section>

    <!-- Kết quả tìm kiếm -->
    <?php if ($showSearchResults): ?>
        <section id="search-results" class="container mx-auto px-4 py-12">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl md:text-3xl font-extrabold text-gray-800">
                    Kết quả tìm kiếm cho "<?= htmlspecialchars($searchTerm) ?>"
                </h2>
                <span class="text-gray-600"><?= count($searchResults) ?> kết quả</span>
            </div>
            
            <?php if (!empty($searchResults)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($searchResults as $item): ?>
                        <?php
                        $img = !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'https://images.unsplash.com/photo-1504674900247-0877df9cc836';
                        
                        // Hàm highlight từ khóa tìm kiếm
                        function highlightSearchTerm($text, $term) {
                            if (empty($term)) return htmlspecialchars($text);
                            return preg_replace(
                                '/(' . preg_quote($term, '/') . ')/i', 
                                '<span class="search-highlight">$1</span>', 
                                htmlspecialchars($text)
                            );
                        }
                        ?>
                        <div class="food-card bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all">
                            <img src="<?= $img ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-48 object-cover rounded-lg mb-4">
                            <h3 class="text-lg font-bold text-gray-800 mb-2">
                                <?= highlightSearchTerm($item['name'], $searchTerm) ?>
                            </h3>
                            <p class="text-gray-600 text-sm mb-2">
                                <?= highlightSearchTerm($item['restaurant_name'], $searchTerm) ?>
                            </p>
                            <?php if (!empty($item['description'])): ?>
                                <p class="text-gray-600 text-sm mb-2">
                                    <?= highlightSearchTerm($item['description'], $searchTerm) ?>
                                </p>
                            <?php endif; ?>
                            <p class="text-orange-500 font-bold mb-3"><?= number_format($item['price'], 0) ?>đ</p>
                            <form method="post" action="customer.php">
                                <input type="hidden" name="menu_item_id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="restaurant_id" value="<?= $item['restaurant_id'] ?>">
                                <div class="flex items-center gap-2">
                                    <input type="number" name="quantity" value="1" min="1" class="w-16 p-2 border rounded text-gray-800">
                                    <button type="submit" name="add_to_cart" class="bg-orange-500 text-white py-2 px-4 rounded-full hover:bg-orange-600 transition-colors">
                                        Thêm vào giỏ
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12 bg-white rounded-lg shadow">
                    <i class="fas fa-search text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">Không tìm thấy kết quả</h3>
                    <p class="text-gray-500">Không có món ăn hoặc nhà hàng nào phù hợp với "<?= htmlspecialchars($searchTerm) ?>"</p>
                    <a href="customer.php" class="inline-block mt-4 text-orange-500 hover:text-orange-600 font-medium">
                        <i class="fas fa-arrow-left mr-2"></i> Quay lại trang chủ
                    </a>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <!-- Featured Dishes (chỉ hiển thị khi không có tìm kiếm) -->
        <section class="container mx-auto px-4 py-12">
            <h2 class="text-2xl md:text-3xl font-extrabold text-center mb-8 text-gray-800">Món ăn nổi bật</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php
                $stmt = $conn->query("SELECT mi.*, r.name AS restaurant_name FROM menu_items mi JOIN restaurants r ON mi.restaurant_id = r.id ORDER BY RAND() LIMIT 3");
                while ($row = $stmt->fetch()):
                    $img = !empty($row['image_url']) ? htmlspecialchars($row['image_url']) : 'https://images.unsplash.com/photo-1504674900247-0877df9cc836';
                ?>
                    <div class="food-card bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all">
                        <img src="<?= $img ?>" alt="<?= htmlspecialchars($row['name']) ?>" class="w-full h-48 object-cover rounded-lg mb-4">
                        <h3 class="text-lg font-bold text-gray-800 mb-2"><?= htmlspecialchars($row['name']) ?></h3>
                        <p class="text-gray-600 text-sm mb-2"><?= htmlspecialchars($row['restaurant_name']) ?></h3>
                        <p class="text-orange-500 font-bold mb-3"><?= number_format($row['price'], 0) ?>đ</p>
                        <form method="post" action="customer.php">
                            <input type="hidden" name="menu_item_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="restaurant_id" value="<?= $row['restaurant_id'] ?>">
                            <div class="flex items-center gap-2">
                                <input type="number" name="quantity" value="1" min="1" class="w-16 p-2 border rounded text-gray-800">
                                <button type="submit" name="add_to_cart" class="bg-orange-500 text-white py-2 px-4 rounded-full hover:bg-orange-600 transition-colors">
                                    Thêm vào giỏ
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>

        <!-- Food Categories -->
        <section id="menu-categories" class="container mx-auto px-4 py-12">
            <h2 class="text-2xl md:text-3xl font-extrabold text-center mb-8 text-gray-800">Khám phá món ăn</h2>
            <div class="flex flex-wrap justify-center gap-4 md:gap-6">
                <?php
                $categories = ['Cơm', 'Bún', 'Phở', 'Pizza', 'Đồ uống', 'Salad', 'Khác'];
                $images = [
                    'Cơm' => '/Test/SDLC Picture/full.jpg',
                    'Bún' => '/Test/SDLC Picture/Bún Bò Giò Nạc.jpg',
                    'Phở' => '/Test/SDLC Picture/phodb.jpg',
                    'Pizza' => '/Test/SDLC Picture/PIZZAganam.png',
                    'Đồ uống' => '/Test/SDLC Picture/Trà Alisan Kem Sữa.jpg',
                    'Salad' => '/Test/SDLC Picture/Tuna San Salad.jpg',
                    'Khác' => '/Test/SDLC Picture/Tiramisu.jpg'
                ];
                foreach ($categories as $category):
                ?>
                    <a href="customer.php?category=<?= urlencode($category) ?>#restaurants-by-category" 
                       class="category-card flex flex-col items-center p-4 rounded-lg bg-white shadow-md hover:shadow-lg">
                        <div class="w-20 h-20 md:w-28 md:h-28 rounded-full overflow-hidden border-2 border-orange-200">
                            <img src="<?= $images[$category] ?? '/Test/SDLC Picture/full.jpg' ?>" 
                                 alt="<?= htmlspecialchars($category) ?>" class="w-full h-full object-cover">
                        </div>
                        <span class="mt-2 text-base md:text-lg font-semibold text-gray-800"><?= htmlspecialchars($category) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Restaurants by Category -->
    <?php if (isset($_GET['category']) && !$showSearchResults): ?>
        <?php
        $category = htmlspecialchars($_GET['category']);
        $stmt = $conn->prepare("SELECT id, name, address FROM restaurants WHERE category = ?");
        $stmt->execute([$category]);
        ?>
        <section id="restaurants-by-category" class="container mx-auto px-4 py-12">
            <h2 class="text-2xl md:text-3xl font-extrabold text-center mb-8 text-gray-800">
                Nhà hàng bán <span class="text-orange-500"><?= $category ?></span>
            </h2>
            
            <div class="flex flex-wrap justify-center gap-2 mb-6">
                <button class="filter-btn px-3 py-1 md:px-4 md:py-2 bg-white border border-orange-300 rounded-full text-gray-800">
                    Gần tôi
                </button>
                <button class="filter-btn px-3 py-1 md:px-4 md:py-2 bg-white border border-orange-300 rounded-full text-gray-800">
                    Đánh giá cao
                </button>
                <button class="filter-btn px-3 py-1 md:px-4 md:py-2 bg-white border border-orange-300 rounded-full text-gray-800">
                    Khuyến mãi
                </button>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($stmt->rowCount() > 0): ?>
                    <?php while ($row = $stmt->fetch()): ?>
                        <?php
                        $stmtImg = $conn->prepare("SELECT image_url FROM menu_items WHERE restaurant_id = ? AND image_url != '' LIMIT 1");
                        $stmtImg->execute([$row['id']]);
                        $imgRow = $stmtImg->fetch();
                        $img = $imgRow && !empty($imgRow['image_url']) ? htmlspecialchars($imgRow['image_url']) : 'https://images.unsplash.com/photo-1504674900247-0877df9cc836';
                        ?>
                        <div class="food-card bg-white rounded-xl shadow-lg hover:shadow-orange-200 transition-all p-6">
                            <img src="<?= $img ?>" alt="Nhà hàng" class="w-full h-48 object-cover rounded-lg mb-4">
                            <h3 class="text-xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($row['name']) ?></h3>
                            <?php if (!empty($row['address'])): ?>
                                <p class="text-gray-600 text-sm mb-2">
                                    <i class="fa fa-map-marker-alt text-orange-500"></i> <?= htmlspecialchars($row['address']) ?>
                                </p>
                            <?php endif; ?>
                            <p class="text-gray-600 text-sm mb-3">4.5 ⭐ (200+ đánh giá)</p>
                            <a href="customer.php?restaurant_id=<?= $row['id'] ?>#restaurant-menu" 
                               class="inline-block bg-orange-500 text-white px-6 py-2 rounded-full font-semibold hover:bg-orange-600 transition-colors">
                                Xem menu
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-3 text-center py-8">
                        <i class="fas fa-utensils text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 text-lg">Chưa có nhà hàng nào bán loại này.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Restaurant Menu -->
    <?php if (isset($_GET['restaurant_id']) && !$showSearchResults): ?>
        <?php
        $restaurant_id = (int)$_GET['restaurant_id'];
        $stmt = $conn->prepare("SELECT name FROM restaurants WHERE id = ?");
        $stmt->execute([$restaurant_id]);
        $restaurant = $stmt->fetch();
        ?>
        
        <?php if ($restaurant): ?>
            <section id="restaurant-menu" class="container mx-auto px-4 py-12">
                <h2 class="text-2xl md:text-3xl font-extrabold text-center mb-8 text-gray-800">
                    Menu của <span class="text-orange-500"><?= htmlspecialchars($restaurant['name']) ?></span>
                </h2>
                
                <?php
                $stmtMenu = $conn->prepare("SELECT * FROM menu_items WHERE restaurant_id = ?");
                $stmtMenu->execute([$restaurant_id]);
                ?>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if ($stmtMenu->rowCount() > 0): ?>
                        <?php while ($item = $stmtMenu->fetch()): ?>
                            <?php
                            $img = !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'https://images.unsplash.com/photo-1504674900247-0877df9cc836';
                            ?>
                            <div class="food-card bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all">
                                <img src="<?= $img ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-48 object-cover rounded-lg mb-4">
                                <h4 class="text-lg font-bold text-gray-800 mb-2"><?= htmlspecialchars($item['name']) ?></h4>
                                <?php if (!empty($item['description'])): ?>
                                    <p class="text-gray-600 text-sm mb-2"><?= htmlspecialchars($item['description']) ?></p>
                                <?php endif; ?>
                                <p class="text-orange-500 font-bold mb-3"><?= number_format($item['price'], 0) ?>đ</p>
                                <form method="post" action="customer.php">
                                    <input type="hidden" name="menu_item_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="restaurant_id" value="<?= $item['restaurant_id'] ?>">
                                    <div class="flex items-center gap-2">
                                        <input type="number" name="quantity" value="1" min="1" class="w-16 p-2 border rounded text-gray-800">
                                        <button type="submit" name="add_to_cart" class="bg-orange-500 text-white py-2 px-4 rounded-full hover:bg-orange-600 transition-colors">
                                            Thêm vào giỏ
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-3 text-center py-8">
                            <i class="fas fa-utensils text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-600 text-lg">Nhà hàng chưa có món nào.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Cart Button -->
    <a href="cart.php" class="cart-btn">
        <i class="fa fa-shopping-cart"></i>
        <?php if (!empty($_SESSION['cart'])): ?>
            <span class="cart-count">
                <?= array_sum($_SESSION['cart']) ?>
            </span>
        <?php endif; ?>
    </a>

    <!-- Footer -->
    <footer id="contact" class="bg-orange-600 text-white py-12">
        <div class="container mx-auto px-4 grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
            <div>
                <h3 class="text-lg font-bold mb-4">ShopeeFood</h3>
                <p class="text-sm">Giao đồ ăn nhanh chóng, tiện lợi, mọi lúc mọi nơi.</p>
            </div>
            <div>
                <h3 class="text-lg font-bold mb-4">Liên hệ</h3>
                <p class="text-sm">Email: nguyenvuvietanh02102005@gmail.com</p>
                <p class="text-sm">Hotline: +0976150732</p>
            </div>
            <div>
                <h3 class="text-lg font-bold mb-4">Theo dõi chúng tôi</h3>
                <div class="flex justify-center gap-4">
                    <a href="https://www.facebook.com/nguyen.v.anh.345985/" target="_blank" class="text-white hover:text-orange-200 transition-colors">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                </div>
            </div>
        </div>
        <p class="text-center text-sm mt-8">© <?= date('Y') ?> ShopeeFood. Tất cả quyền được bảo lưu.</p>
    </footer>

    <!-- Popup Message -->
    <?php if (isset($_SESSION['message'])): ?>
        <div id="popup-message" class="popup-message">
            <?= htmlspecialchars($_SESSION['message']) ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <script>
        // Banner Carousel
        const images = document.querySelectorAll('#banner-carousel .banner-img');
        let current = 0;
        
        function showBanner(idx) {
            images.forEach((img, i) => {
                img.style.opacity = i === idx ? '1' : '0';
            });
        }
        
        function nextBanner() {
            current = (current + 1) % images.length;
            showBanner(current);
        }
        
        function prevBanner() {
            current = (current - 1 + images.length) % images.length;
            showBanner(current);
        }
        
        document.getElementById('nextBanner').onclick = nextBanner;
        document.getElementById('prevBanner').onclick = prevBanner;
        setInterval(nextBanner, 5000);
        
        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
        
        // Page transition effect
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '1';
            
            // Xử lý khi click vào các liên kết
            document.querySelectorAll('a').forEach(link => {
                if (link.href && !link.href.startsWith('javascript:') && 
                    !link.href.startsWith('mailto:') && 
                    !link.href.startsWith('tel:') &&
                    !link.href.startsWith('#')) {
                    link.addEventListener('click', function(e) {
                        // Nếu là liên kết về trang chủ và đang ở trang chủ thì không làm gì
                        if (link.getAttribute('href') === 'customer.php' && 
                            window.location.pathname.endsWith('customer.php') && 
                            !window.location.search && 
                            !window.location.hash) {
                            e.preventDefault();
                            return;
                        }
                        
                        // Skip nếu mở tab mới hoặc download
                        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
                        if (link.hasAttribute('download')) return;
                        
                        e.preventDefault();
                        document.body.style.opacity = '0.5';
                        
                        setTimeout(() => {
                            window.location.href = link.href;
                        }, 300);
                    });
                }
            });

            // Tự động focus vào ô tìm kiếm nếu có từ khóa
            <?php if (!empty($searchTerm)): ?>
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.setSelectionRange(<?= mb_strlen($searchTerm) ?>, <?= mb_strlen($searchTerm) ?>);
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>
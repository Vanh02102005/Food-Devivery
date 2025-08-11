<?php
require 'connect.php';

try {
    // Start transaction
    $conn->beginTransaction();

    echo "Truncating existing data...\n";
    // Disable foreign key checks to avoid errors during truncation
    $conn->exec('SET foreign_key_checks = 0');

    // Truncate tables in reverse order of dependency
    $conn->exec('DELETE FROM menu_items');
    $conn->exec('ALTER TABLE menu_items AUTO_INCREMENT = 1');
    $conn->exec('DELETE FROM orders');
    $conn->exec('ALTER TABLE orders AUTO_INCREMENT = 1');
    $conn->exec('DELETE FROM restaurants');
    $conn->exec('ALTER TABLE restaurants AUTO_INCREMENT = 1');
    
    // Only delete users that are restaurants to keep customer accounts
    $conn->exec("DELETE FROM users WHERE role = 'restaurant'");
    $conn->exec('ALTER TABLE users AUTO_INCREMENT = 1');

    // Re-enable foreign key checks
    $conn->exec('SET foreign_key_checks = 1');
    echo "Data truncated.\n";


    echo "Starting to seed database...\n";

    // Hash a common password for all restaurant users
    $password = 'password123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // --- Restaurant 1: Cơm Tấm Cali ---
    // Create user for the restaurant
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'restaurant', ?)");
    $stmt->execute(['cali_restaurant', $hashed_password, 'cali@example.com']);
    $userId1 = $conn->lastInsertId();
    echo "Created user 'cali_restaurant' (password: password123)\n";

    // Create the restaurant
    $stmt = $conn->prepare("INSERT INTO restaurants (user_id, name, address, category) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId1, 'Cơm Tấm Cali', '123 Bùi Viện, Hoàn Kiếm, Hà Nội', 'Cơm']);
    $restaurantId1 = $conn->lastInsertId();
    echo "Created restaurant 'Cơm Tấm Cali'\n";

    // Add menu items for Restaurant 1
    $menu1 = [
        ['Cơm Tấm Sườn Bì Chả', 'Sườn cốt lết, bì heo, chả trứng hấp, trứng ốp la.', 55000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Cơm'],
        ['Cơm Tấm Gà Quay', 'Đùi gà góc tư quay giòn da, ăn kèm với đồ chua.', 50000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Cơm'],
        ['Cơm Tấm Sườn Trứng', 'Sườn nướng và trứng ốp la.', 45000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Cơm'],
        ['Cơm Tấm Bì Chả', 'Bì heo trộn thính và chả trứng hấp.', 40000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Cơm'],
        ['Cơm Tấm Đặc Biệt (Full)', 'Sườn, bì, chả, trứng, lạp xưởng.', 65000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Cơm'],
        ['Canh Khổ Qua Dồn Thịt', 'Món canh thanh mát, giải nhiệt.', 15000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Canh Rong Biển', 'Canh rong biển nấu với tôm hoặc thịt băm.', 15000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Trà Đá', 'Trà đá miễn phí, giải khát.', 2000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Nước Sâm', 'Nước sâm bí đao, la hán quả.', 10000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Sữa Đậu Nành', 'Sữa đậu nành nhà làm.', 10000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
    ];
    $stmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, name, description, price, image_url, category) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($menu1 as $item) {
        $stmt->execute([$restaurantId1, $item[0], $item[1], $item[2], $item[3], $item[4]]);
    }
    echo "Added " . count($menu1) . " menu items for Cơm Tấm Cali.\n";


    // --- Restaurant 2: Phở Thìn Hà Nội ---
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'restaurant', ?)");
    $stmt->execute(['phothin_hanoi', $hashed_password, 'phothin@example.com']);
    $userId2 = $conn->lastInsertId();
    echo "Created user 'phothin_hanoi' (password: password123)\n";

    $stmt = $conn->prepare("INSERT INTO restaurants (user_id, name, address, category) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId2, 'Phở Thìn Hà Nội', '13 Lò Đúc, Hai Bà Trưng, Hà Nội', 'Phở']);
    $restaurantId2 = $conn->lastInsertId();
    echo "Created restaurant 'Phở Thìn Hà Nội'\n";

    $menu2 = [
        ['Phở Tái Lăn', 'Thịt bò thái mỏng, xào tái trên lửa lớn với gừng, tỏi.', 60000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Phở'],
        ['Phở Chín', 'Thịt bò được luộc chín tới, thái mỏng.', 50000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Phở'],
        ['Phở Tái', 'Thịt bò tươi thái mỏng, trụng với nước dùng nóng.', 55000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Phở'],
        ['Phở Nạm Gầu', 'Thịt nạm và gầu bò béo ngậy.', 65000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Phở'],
        ['Phở Đặc Biệt', 'Đầy đủ các loại thịt: tái, chín, nạm, gầu, gân.', 75000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Phở'],
        ['Trứng Trần', 'Trứng gà chần trong nước dùng.', 5000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Quẩy (đĩa)', 'Ăn kèm phở, giòn rụm.', 5000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Trà Chanh', 'Trà chanh pha tay.', 15000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Nước Sấu', 'Nước sấu ngâm đường.', 15000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Bia Hà Nội', 'Bia chai.', 20000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
    ];
    $stmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, name, description, price, image_url, category) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($menu2 as $item) {
        $stmt->execute([$restaurantId2, $item[0], $item[1], $item[2], $item[3], $item[4]]);
    }
    echo "Added " . count($menu2) . " menu items for Phở Thìn Hà Nội.\n";

    
    // --- Restaurant 3: Pizza Express ---
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'restaurant', ?)");
    $stmt->execute(['pizza_express', $hashed_password, 'pizzaexp@example.com']);
    $userId3 = $conn->lastInsertId();
    echo "Created user 'pizza_express' (password: password123)\n";

    $stmt = $conn->prepare("INSERT INTO restaurants (user_id, name, address, category) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId3, 'Pizza Express', '258 Bà Triệu, Hai Bà Trưng, Hà Nội', 'Pizza']);
    $restaurantId3 = $conn->lastInsertId();
    echo "Created restaurant 'Pizza Express'\n";
    
    $menu3 = [
        ['Pizza Hải Sản', 'Pizza với tôm, mực, thanh cua và sốt cà chua.', 150000, 'https://images.unsplash.com/photo-1513104890138-7c749659a680', 'Pizza'],
        ['Pizza Bò BBQ', 'Pizza với thịt bò, sốt BBQ, hành tây và ớt chuông.', 140000, 'https://images.unsplash.com/photo-1513104890138-7c749659a680', 'Pizza'],
        ['Pizza Gà Nấm', 'Pizza gà, nấm, sốt kem.', 130000, 'https://images.unsplash.com/photo-1513104890138-7c749659a680', 'Pizza'],
        ['Pizza Margherita', 'Pizza truyền thống với cà chua, phô mai Mozzarella.', 120000, 'https://images.unsplash.com/photo-1513104890138-7c749659a680', 'Pizza'],
        ['Mỳ Ý Bò Bằm', 'Spaghetti với sốt bò bằm cà chua.', 90000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Mỳ Ý Hải Sản', 'Spaghetti với tôm, mực, sốt kem.', 110000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Salad Cá Ngừ', 'Salad trộn với cá ngừ, rau củ tươi và sốt dầu giấm.', 70000, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe', 'Salad'],
        ['Súp Kem Nấm', 'Súp kem béo ngậy với nấm.', 45000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Coca-Cola', 'Nước ngọt giải khát.', 15000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Nước Suối', 'Nước khoáng tinh khiết.', 10000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
    ];
    $stmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, name, description, price, image_url, category) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($menu3 as $item) {
        $stmt->execute([$restaurantId3, $item[0], $item[1], $item[2], $item[3], $item[4]]);
    }
    echo "Added " . count($menu3) . " menu items for Pizza Express.\n";

    // --- Restaurant 4: Bún Chả Hương Liên ---
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'restaurant', ?)");
    $stmt->execute(['huonglien_buncha', $hashed_password, 'huonglien@example.com']);
    $userId4 = $conn->lastInsertId();
    echo "Created user 'huonglien_buncha' (password: password123)\n";

    $stmt = $conn->prepare("INSERT INTO restaurants (user_id, name, address, category) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId4, 'Bún Chả Hương Liên', '24 Lê Văn Hưu, Hai Bà Trưng, Hà Nội', 'Bún']);
    $restaurantId4 = $conn->lastInsertId();
    echo "Created restaurant 'Bún Chả Hương Liên'\n";
    
    $menu4 = [
        ['Bún Chả Đặc Biệt', 'Suất đầy đủ với chả nướng, nem, bún và rau sống.', 60000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Bún'],
        ['Bún Chả (suất thường)', 'Chả nướng, bún và rau sống.', 45000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Bún'],
        ['Nem Cua Bể (cái)', 'Nem rán giòn rụm với nhân thịt cua bể.', 7000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Nem Vuông (cái)', 'Nem rán hình vuông, nhân thịt.', 8000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Thêm Chả (đĩa)', 'Đĩa chả nướng thêm.', 30000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Thêm Bún (đĩa)', 'Đĩa bún rối thêm.', 10000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Bún'],
        ['Trà Chanh', 'Trà chanh tươi mát giải nhiệt.', 15000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Nước Sấu', 'Nước sấu đá.', 15000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Bia Hà Nội', 'Bia chai.', 20000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Trà Đá', 'Trà đá.', 5000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
    ];
    $stmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, name, description, price, image_url, category) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($menu4 as $item) {
        $stmt->execute([$restaurantId4, $item[0], $item[1], $item[2], $item[3], $item[4]]);
    }
    echo "Added " . count($menu4) . " menu items for Bún Chả Hương Liên.\n";

    // --- Restaurant 5: Bánh Mì Phượng ---
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'restaurant', ?)");
    $stmt->execute(['banhmiphuong_hoian', $hashed_password, 'banhmiphuong@example.com']);
    $userId5 = $conn->lastInsertId();
    echo "Created user 'banhmiphuong_hoian' (password: password123)\n";

    $stmt = $conn->prepare("INSERT INTO restaurants (user_id, name, address, category) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId5, 'Bánh Mì Phượng', '2B Phan Chu Trinh, Hoàn Kiếm, Hà Nội', 'Khác']);
    $restaurantId5 = $conn->lastInsertId();
    echo "Created restaurant 'Bánh Mì Phượng'\n";
    
    $menu5 = [
        ['Bánh Mì Thập Cẩm', 'Bánh mì đặc biệt với đầy đủ các loại nhân.', 30000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Bánh Mì Gà Nướng', 'Bánh mì với thịt gà nướng thơm lừng.', 25000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Bánh Mì Pate', 'Bánh mì với pate gan.', 20000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Bánh Mì Xíu Mại', 'Bánh mì với xíu mại sốt cà chua.', 25000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Bánh Mì Chả', 'Bánh mì với chả lụa.', 20000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Bánh Mì Ốp La', 'Bánh mì với trứng ốp la.', 20000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Nước Sả Chanh', 'Nước uống giải khát từ sả và chanh.', 15000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Sữa Bắp', 'Sữa ngô ngọt.', 15000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Cà Phê Sữa', 'Cà phê sữa đá.', 18000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Nước Chanh', 'Nước chanh tươi.', 12000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
    ];
    $stmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, name, description, price, image_url, category) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($menu5 as $item) {
        $stmt->execute([$restaurantId5, $item[0], $item[1], $item[2], $item[3], $item[4]]);
    }
    echo "Added " . count($menu5) . " menu items for Bánh Mì Phượng.\n";

    // --- Restaurant 6: Gà Rán Popeyes ---
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'restaurant', ?)");
    $stmt->execute(['popeyes_garang', $hashed_password, 'popeyes@example.com']);
    $userId6 = $conn->lastInsertId();
    echo "Created user 'popeyes_garang' (password: password123)\n";

    $stmt = $conn->prepare("INSERT INTO restaurants (user_id, name, address, category) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId6, 'Gà Rán Popeyes', '789 Nguyễn Văn Linh, Long Biên, Hà Nội', 'Khác']);
    $restaurantId6 = $conn->lastInsertId();
    echo "Created restaurant 'Gà Rán Popeyes'\n";
    
    $menu6 = [
        ['Combo Gà Rán (2 miếng)', '2 miếng gà giòn cay, 1 khoai tây chiên, 1 nước ngọt.', 89000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Combo Gà Rán (3 miếng)', '3 miếng gà giòn cay, 1 khoai tây chiên, 1 nước ngọt.', 119000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Gà Rán Giòn Cay (1 miếng)', '1 miếng gà rán giòn cay.', 35000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Burger Tôm', 'Burger với nhân tôm chiên giòn.', 55000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Cơm Gà', 'Cơm trắng ăn kèm 1 miếng gà rán.', 60000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Mỳ Ý Gà', 'Mỳ Ý sốt kem với thịt gà.', 65000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Salad Bắp Cải', 'Salad bắp cải trộn.', 25000, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe', 'Salad'],
        ['Khoai Tây Chiên Cỡ Lớn', 'Khoai tây chiên giòn, nóng hổi.', 30000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Kem Tươi', 'Kem vani.', 10000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
        ['Nước ngọt (chai)', 'Coca/Pepsi/7Up.', 20000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
    ];
    $stmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, name, description, price, image_url, category) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($menu6 as $item) {
        $stmt->execute([$restaurantId6, $item[0], $item[1], $item[2], $item[3], $item[4]]);
    }
    echo "Added " . count($menu6) . " menu items for Gà Rán Popeyes.\n";

    // --- Restaurant 7: Trà Sữa Gong Cha ---
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'restaurant', ?)");
    $stmt->execute(['gongcha_trasua', $hashed_password, 'gongcha@example.com']);
    $userId7 = $conn->lastInsertId();
    echo "Created user 'gongcha_trasua' (password: password123)\n";

    $stmt = $conn->prepare("INSERT INTO restaurants (user_id, name, address, category) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId7, 'Trà Sữa Gong Cha', '101 Tôn Dật Tiên, Tây Hồ, Hà Nội', 'Đồ uống']);
    $restaurantId7 = $conn->lastInsertId();
    echo "Created restaurant 'Trà Sữa Gong Cha'\n";
    
    $menu7 = [
        ['Trà Sữa Trân Châu Đen', 'Hương vị trà sữa cổ điển với trân châu đen dai ngon.', 52000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Trà Alisan Kem Sữa', 'Trà Alisan thanh mát kết hợp với lớp kem sữa béo ngậy.', 58000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Trà Xanh Chanh Dây', 'Trà xanh chua ngọt vị chanh dây.', 48000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Trà Sữa Oolong 3J', 'Trà sữa Oolong với 3 loại topping: trân châu, pudding, thạch.', 65000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Trà Đen Kem Sữa', 'Trà đen nguyên chất với lớp kem sữa mặn.', 55000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Trà Sữa Khoai Môn', 'Trà sữa vị khoai môn ngọt bùi.', 54000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Trà Bưởi Mật Ong', 'Trà xanh kết hợp với tép bưởi và mật ong.', 50000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Thêm Trân Châu Trắng', 'Topping trân châu trắng giòn.', 10000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Thêm Pudding Trứng', 'Topping pudding trứng béo ngậy.', 10000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
        ['Thêm Thạch Ai-yu', 'Topping thạch Ai-yu trong veo.', 10000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
    ];
    $stmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, name, description, price, image_url, category) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($menu7 as $item) {
        $stmt->execute([$restaurantId7, $item[0], $item[1], $item[2], $item[3], $item[4]]);
    }
    echo "Added " . count($menu7) . " menu items for Trà Sữa Gong Cha.\n";

    // --- More Restaurants to fill categories ---

    // Cơm Category
    $restaurants_com = [
        ['com_ga_ba_buoi', 'Cơm Gà Bà Buội', '18 Đào Duy Từ, Hoàn Kiếm, Hà Nội', 'Cơm', [
            ['Cơm Gà Xé', 'Gà luộc xé nhỏ trộn với gia vị, ăn cùng cơm và rau răm.', 40000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Cơm'],
            ['Cơm Gà Đùi', 'Đùi gà luộc ăn với cơm nghệ.', 45000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Cơm'],
            ['Lòng Gà Trộn', 'Lòng gà xào nghệ.', 30000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
            ['Canh Lá Dang', 'Canh gà lá dang chua nhẹ.', 20000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
            ['Gỏi Gà', 'Gỏi gà trộn hành tây, rau răm.', 50000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác']
        ]],
        ['com_tam_ba_ghien', 'Cơm Tấm Ba Ghiền', '84 Đặng Văn Ngữ, Đống Đa, Hà Nội', 'Cơm', [
            ['Cơm Sườn To Khổng Lồ', 'Miếng sườn cốt lết siêu to, nướng đậm đà.', 65000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Cơm'],
            ['Cơm Sườn Bì', 'Sườn nướng và bì heo.', 55000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Cơm'],
            ['Cơm Sườn Chả', 'Sườn nướng và chả trứng.', 55000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Cơm'],
            ['Chả Trứng Hấp', 'Chả trứng hấp mềm, béo ngậy.', 20000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
            ['Trứng Ốp La', 'Trứng gà ốp la.', 10000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác']
        ]]
    ];

    // Bún Category
    $restaurants_bun = [
        ['bun_bo_an_cuu', 'Bún Bò Huế An Cựu', '108 Nguyễn Công Trứ, Hai Bà Trưng, Hà Nội', 'Bún', [
            ['Bún Bò Giò Nạc', 'Bún bò truyền thống với giò heo và nạc bò.', 45000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Bún'],
            ['Bún Bò Chả Cua', 'Bún bò có thêm chả cua đậm vị.', 50000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Bún'],
            ['Bún Bò Đặc Biệt', 'Đầy đủ giò, nạc, chả, tiết.', 55000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Bún'],
            ['Chả Cua (chén)', 'Chén chả cua thêm.', 15000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Khác'],
            ['Tiết Heo (chén)', 'Chén tiết heo luộc.', 10000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Khác']
        ]],
        ['bun_dau_trang_ti', 'Bún Đậu Mắm Tôm Tràng Thi', '25 Tràng Thi, Hà Nội', 'Bún', [
            ['Mẹt Bún Đậu Đầy Đủ', 'Bún, đậu rán, chả cốm, nem chua, dồi, thịt luộc.', 80000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Bún'],
            ['Mẹt Bún Đậu Cơ Bản', 'Bún, đậu rán, chả cốm.', 45000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Bún'],
            ['Đậu Hũ Rán', 'Đậu hũ non rán giòn.', 20000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Khác'],
            ['Chả Cốm', 'Chả cốm làng Vòng.', 25000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Khác'],
            ['Thịt Chân Giò Luộc', 'Thịt chân giò luộc thái mỏng.', 30000, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc', 'Khác']
        ]]
    ];

    // Phở Category
    $restaurants_pho = [
        ['pho_ly_quoc_su', 'Phở 10 Lý Quốc Sư', '10 Lý Quốc Sư, Hà Nội', 'Phở', [
            ['Phở Tái Nạm Gầu', 'Tô phở đầy đủ với tái, nạm, gầu.', 70000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Phở'],
            ['Phở Bò Sốt Vang', 'Bò sốt vang ăn cùng bánh phở.', 65000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Phở'],
            ['Phở Gà', 'Phở gà ta, thịt dai ngọt.', 55000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Phở'],
            ['Lẩu Bò', 'Nồi lẩu bò cho 2-3 người ăn.', 350000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Khác'],
            ['Quẩy Giòn', 'Quẩy ăn kèm phở.', 10000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác']
        ]],
        ['pho_ga_nguyet', 'Phở Gà Nguyệt', '5b Phủ Doãn, Hà Nội', 'Phở', [
            ['Phở Gà Đùi', 'Phở với thịt đùi gà ta.', 50000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Phở'],
            ['Phở Gà Trộn', 'Phở gà trộn chua ngọt.', 55000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Phở'],
            ['Nộm Gà Xé Phay', 'Nộm gà xé chua ngọt.', 60000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Khác'],
            ['Xôi Gà', 'Xôi trắng ăn cùng gà luộc xé.', 40000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Khác'],
            ['Tràng Trứng Cháy Tỏi', 'Tràng trứng gà non cháy tỏi.', 80000, 'https://images.unsplash.com/photo-1464306076886-debca5e8a6b0', 'Khác']
        ]]
    ];

    // Pizza Category
    $restaurants_pizza = [
        ['pizza_4ps', 'Pizza 4P\'s', '151B Hai Bà Trưng, Hoàn Kiếm, Hà Nội', 'Pizza', [
            ['Pizza Burrata', 'Pizza với phô mai Burrata tươi béo ngậy.', 250000, 'https://images.unsplash.com/photo-1513104890138-7c749659a680', 'Pizza'],
            ['Pizza Teriyaki Gà', 'Pizza gà sốt Teriyaki kiểu Nhật.', 190000, 'https://images.unsplash.com/photo-1513104890138-7c749659a680', 'Pizza'],
            ['Pizza 4 Loại Phô Mai', 'Pizza với 4 loại phô mai hảo hạng.', 220000, 'https://images.unsplash.com/photo-1513104890138-7c749659a680', 'Pizza'],
            ['Mì Ý Cua', 'Mì Ý sốt kem với thịt cua tươi.', 180000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
            ['Salad Rau Xanh', 'Salad rau xanh với sốt tự chọn.', 90000, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe', 'Salad']
        ]],
        ['the_pizza_company', 'The Pizza Company', '333 Cầu Giấy, Hà Nội', 'Pizza', [
            ['Pizza Hải Sản Pesto', 'Pizza hải sản với sốt Pesto xanh.', 210000, 'https://images.unsplash.com/photo-1513104890138-7c749659a680', 'Pizza'],
            ['Pizza Gà Rán', 'Pizza độc đáo với topping gà rán.', 195000, 'https://images.unsplash.com/photo-1513104890138-7c749659a680', 'Pizza'],
            ['Sườn Nướng BBQ', 'Sườn heo nướng sốt BBQ.', 150000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
            ['Bánh Mì Bơ Tỏi', 'Bánh mì nướng bơ tỏi.', 45000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
            ['Nước Ngọt Lớn', 'Chai nước ngọt 1.5L.', 35000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống']
        ]]
    ];
    
    // Đồ uống Category
    $restaurants_douong = [
        ['highlands_coffee', 'Highlands Coffee', '200 Nguyễn Thị Minh Khai, Hai Bà Trưng, Hà Nội', 'Đồ uống', [
            ['Phin Sữa Đá', 'Cà phê phin sữa đá đậm đà.', 29000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
            ['Phin Đen Đá', 'Cà phê phin đen.', 29000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
            ['Trà Sen Vàng', 'Trà sen thanh mát, củ năng giòn.', 39000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
            ['Trà Thạch Vải', 'Trà đen với thạch và quả vải.', 45000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
            ['Bánh Mì Que', 'Bánh mì que pate.', 19000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác']
        ]],
        ['the_coffee_house', 'The Coffee House', '86 Cao Thắng, Đống Đa, Hà Nội', 'Đồ uống', [
            ['Cà Phê Sữa Đá', 'Cà phê sữa đá pha máy.', 45000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
            ['Trà Đào Cam Sả', 'Trà đào, cam, sả thanh nhiệt.', 50000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
            ['Macchiato Trà Đen', 'Trà đen với lớp kem mặn Macchiato.', 55000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
            ['Croissant', 'Bánh sừng bò.', 30000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
            ['Tiramisu', 'Bánh Tiramisu.', 35000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác']
        ]]
    ];

    // Salad Category
    $restaurants_salad = [
        ['saladstop', 'SaladStop!', '123 Lê Lợi, Hoàn Kiếm, Hà Nội', 'Salad', [
            ['Caesar Salad', 'Salad Caesar truyền thống với gà nướng.', 120000, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe', 'Salad'],
            ['Greek Salad', 'Salad Hy Lạp với phô mai Feta.', 110000, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe', 'Salad'],
            ['Tuna San Salad', 'Salad cá ngừ kiểu Nhật.', 130000, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe', 'Salad'],
            ['Create Your Own Salad', 'Tự chọn nguyên liệu cho món salad của bạn.', 150000, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe', 'Salad'],
            ['Nước Ép Táo', 'Nước ép táo nguyên chất.', 50000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống']
        ]],
        ['au_parc', 'Au Parc Saigon', '23 Hàn Thuyên, Hai Bà Trưng, Hà Nội', 'Salad', [
            ['Mediterranean Platter', 'Đĩa đồ ăn Địa Trung Hải với hummus, falafel.', 180000, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe', 'Salad'],
            ['Nicoise Salad', 'Salad Nicoise với cá ngừ, trứng, khoai tây.', 160000, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe', 'Salad'],
            ['Bánh Mì Kẹp Gà', 'Bánh mì sandwich gà nướng.', 140000, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836', 'Khác'],
            ['Sinh Tố Bơ', 'Sinh tố bơ.', 70000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống'],
            ['Cà Phê Ý', 'Espresso, Cappuccino.', 60000, 'https://images.unsplash.com/photo-1604382351388-8ab6eb7512d9', 'Đồ uống']
        ]]
    ];

    $all_new_restaurants = array_merge($restaurants_com, $restaurants_bun, $restaurants_pho, $restaurants_pizza, $restaurants_douong, $restaurants_salad);

    foreach ($all_new_restaurants as $res) {
        // Create user
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'restaurant', ?)");
        $stmt->execute([$res[0], $hashed_password, $res[0] . '@example.com']);
        $userId = $conn->lastInsertId();
        echo "Created user '{$res[0]}'\n";

        // Create restaurant
        $stmt = $conn->prepare("INSERT INTO restaurants (user_id, name, address, category) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $res[1], $res[2], $res[3]]);
        $restaurantId = $conn->lastInsertId();
        echo "Created restaurant '{$res[1]}'\n";

        // Add menu items
        $stmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, name, description, price, image_url, category) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($res[4] as $item) {
            $stmt->execute([$restaurantId, $item[0], $item[1], $item[2], $item[3], $item[4]]);
        }
        echo "Added " . count($res[4]) . " menu items for {$res[1]}.\n";
    }

    // Commit transaction
    $conn->commit();
    echo "\nDatabase seeding completed successfully!\n";
    echo "You can now login with the restaurant accounts. E.g., username: cali_restaurant, password: password123\n";

} catch (Exception $e) {
    // Rollback transaction if something failed
    $conn->rollBack();
    echo "Failed to seed database: " . $e->getMessage();
} 
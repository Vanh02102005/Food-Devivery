<?php
session_start();
require 'connect.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
}
if (isset($_POST['edit_user_submit'])) {
    $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=? WHERE id=?");
    $stmt->execute([
        $_POST['edit_username'],
        $_POST['edit_email'],
        $_POST['edit_role'],
        $_POST['edit_user_id']
    ]);
    header("Location: admin.php#users");
    exit();
}
// Handle add delivery user
if (isset($_POST['add_delivery_user'])) {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'delivery')");
    $stmt->execute([
        $_POST['delivery_username'],
        $_POST['delivery_email'],
        password_hash($_POST['delivery_password'], PASSWORD_DEFAULT)
    ]);
    header("Location: admin.php#delivery");
    exit();
}
// Handle edit delivery user
if (isset($_POST['edit_delivery_user_submit'])) {
    $stmt = $conn->prepare("UPDATE users SET username=?, email=? WHERE id=?");
    $stmt->execute([
        $_POST['edit_delivery_username'],
        $_POST['edit_delivery_email'],
        $_POST['edit_delivery_user_id']
    ]);
    header("Location: admin.php#delivery");
    exit();
}
// Handle delete delivery user
if (isset($_POST['delete_delivery_user'])) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$_POST['delivery_user_id']]);
    header("Location: admin.php#delivery");
    exit();
}
// Handle edit restaurant
if (isset($_POST['edit_restaurant_submit'])) {
    $stmt = $conn->prepare("UPDATE restaurants SET name=?, address=?, category=? WHERE id=?");
    $stmt->execute([
        $_POST['edit_restaurant_name'],
        $_POST['edit_restaurant_address'],
        $_POST['edit_restaurant_category'],
        $_POST['edit_restaurant_id']
    ]);
    header("Location: admin.php#restaurants");
    exit();
}
if (isset($_POST['approve_restaurant_user'])) {
    // Cập nhật status nhà hàng của user này thành 'approved'
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("UPDATE restaurants SET status = 'approved' WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: admin.php#users");
    exit();
}
// Handle delete user (for admin, customer, restaurant)
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    // Nếu là restaurant, xóa luôn bản ghi trong bảng restaurants (nếu có)
    $stmt = $conn->prepare("DELETE FROM restaurants WHERE user_id = ?");
    $stmt->execute([$user_id]);
    // Xóa user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    header("Location: admin.php#users");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Delivery - Admin Interface</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .sidebar-tab-active {
            background: #dc2626;
            color: #fff;
        }
        .sidebar-tab-inactive {
            background: #fff;
            color: #dc2626;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-56 bg-white shadow flex flex-col py-8 px-2 sticky top-0 h-screen">
            <div class="text-2xl font-bold text-center mb-8 text-red-600">Admin Dashboard</div>
            <button class="sidebar-btn sidebar-tab-active w-full text-left px-4 py-3 font-bold rounded mb-2 border-l-4 border-red-600" onclick="showTab('users')" id="tab-users">Users</button>
            <button class="sidebar-btn sidebar-tab-inactive w-full text-left px-4 py-3 font-bold rounded mb-2 border-l-4 border-transparent" onclick="showTab('restaurants')" id="tab-restaurants">Restaurants</button>
            <button class="sidebar-btn sidebar-tab-inactive w-full text-left px-4 py-3 font-bold rounded mb-2 border-l-4 border-transparent" onclick="showTab('delivery')" id="tab-delivery">Delivery</button>
            <button class="sidebar-btn sidebar-tab-inactive w-full text-left px-4 py-3 font-bold rounded mb-2 border-l-4 border-transparent" onclick="showTab('orders')" id="tab-orders">Orders</button>
            <a href="logout.php" class="mt-auto px-4 py-3 font-bold text-red-600 hover:underline">Logout</a>
        </div>
        <!-- Main Content -->
        <div class="flex-1 px-4 py-8">
            <!-- Manage Users -->
            <section id="users" class="tab-section">
                <h2 class="text-3xl font-bold text-center mb-6">Manage Users</h2>
                <div class="grid grid-cols-1 gap-4">
                    <?php
                    $edit_user_id = isset($_POST['edit_user']) ? $_POST['user_id'] : null;
                    $stmt = $conn->query("
                        SELECT * FROM users 
                        WHERE role IN ('admin', 'customer')
                        OR (role = 'restaurant' AND id NOT IN (SELECT user_id FROM restaurants WHERE status = 'approved'))
                    ");
                    while ($row = $stmt->fetch()) {
                        echo "<div class='bg-white p-4 rounded-lg shadow'>";
                        if ($edit_user_id == $row['id']) {
                            // Edit form
                            echo "<form method='post' action='admin.php#users'>";
                            echo "<input type='hidden' name='edit_user_id' value='{$row['id']}'>";
                            echo "<input type='text' name='edit_username' value='{$row['username']}' class='p-2 border rounded mb-2' required> ";
                            echo "<input type='email' name='edit_email' value='{$row['email']}' class='p-2 border rounded mb-2' required> ";
                            echo "<select name='edit_role' class='p-2 border rounded mb-2'>";
                            foreach (["admin","restaurant","customer","delivery"] as $role) {
                                $selected = $row['role']==$role ? "selected" : "";
                                echo "<option value='$role' $selected>$role</option>";
                            }
                            echo "</select> ";
                            echo "<button type='submit' name='edit_user_submit' class='bg-green-600 text-white py-1 px-2 rounded hover:bg-green-700'>Save</button>";
                            echo "</form>";
                        } else {
                            echo "<p>Username: {$row['username']}</p>";
                            echo "<p>Email: {$row['email']}</p>";
                            echo "<p>Role: {$row['role']}</p>";
                            echo "<form method='post' action='admin.php#users' style='display:inline'>";
                            echo "<input type='hidden' name='user_id' value='{$row['id']}'>";
                            echo "<button type='submit' name='edit_user' class='bg-blue-600 text-white py-1 px-2 rounded hover:bg-blue-700'>Edit</button> ";
                            echo "<button type='submit' name='delete_user' class='bg-red-600 text-white py-1 px-2 rounded hover:bg-red-700'>Delete</button>";
                            if ($row['role'] == 'restaurant') {
                                // Hiển thị nút Approve
                                echo "<form method='post' action='admin.php#users' style='display:inline'>";
                                echo "<input type='hidden' name='user_id' value='{$row['id']}'>";
                                echo "<button type='submit' name='approve_restaurant_user' class='bg-green-600 text-white py-1 px-2 rounded hover:bg-green-700'>Approve</button>";
                                echo "</form>";
                            }
                            echo "</form>";
                        }
                        echo "</div>";
                    }
                    ?>
                </div>
            </section>

            <!-- Manage Restaurants -->
            <section id="restaurants" class="tab-section" style="display:none">
                <h2 class="text-3xl font-bold text-center mb-6">Manage Restaurants</h2>
                <div class="grid grid-cols-1 gap-4">
                    <?php
                    $edit_restaurant_id = isset($_POST['edit_restaurant']) ? $_POST['restaurant_id'] : null;
                    $stmt = $conn->prepare("SELECT r.*, u.username FROM restaurants r JOIN users u ON r.user_id = u.id WHERE r.status = 'approved'");
                    $stmt->execute();
                    while ($row = $stmt->fetch()) {
                        echo "<div class='bg-white p-4 rounded-lg shadow'>";
                        if ($edit_restaurant_id == $row['id']) {
                            // Edit form
                            echo "<form method='post' action='admin.php#restaurants'>";
                            echo "<input type='hidden' name='edit_restaurant_id' value='{$row['id']}'>";
                            echo "<input type='text' name='edit_restaurant_name' value='{$row['name']}' class='p-2 border rounded mb-2' required> ";
                            echo "<input type='text' name='edit_restaurant_address' value='{$row['address']}' class='p-2 border rounded mb-2' required> ";
                            echo "<input type='text' name='edit_restaurant_category' value='{$row['category']}' class='p-2 border rounded mb-2' required> ";
                            echo "<button type='submit' name='edit_restaurant_submit' class='bg-green-600 text-white py-1 px-2 rounded hover:bg-green-700'>Save</button>";
                            echo "</form>";
                        } else {
                            echo "<p>Name: {$row['name']}</p>";
                            echo "<p>Owner: {$row['username']}</p>";
                            echo "<p>Category: {$row['category']}</p>";
                            echo "<p>Status: {$row['status']}</p>";
                            echo "<form method='post' action='admin.php#restaurants' style='display:inline'>";
                            echo "<input type='hidden' name='restaurant_id' value='{$row['id']}'>";
                            echo "<button type='submit' name='edit_restaurant' class='bg-blue-600 text-white py-1 px-2 rounded hover:bg-blue-700'>Edit</button> ";
                            if ($row['status'] == 'pending') {
                                echo "<button type='submit' name='approve_restaurant' class='bg-green-600 text-white py-1 px-2 rounded hover:bg-green-700'>Approve</button> ";
                                echo "<button type='submit' name='reject_restaurant' class='bg-red-600 text-white py-1 px-2 rounded hover:bg-red-700'>Reject</button> ";
                            }
                            echo "</form>";
                        }
                        echo "</div>";
                    }
                    ?>
                </div>
            </section>

            <!-- Manage Delivery (users with role=delivery) -->
            <section id="delivery" class="tab-section" style="display:none">
                <h2 class="text-3xl font-bold text-center mb-6">Manage Delivery</h2>
                <div class="grid grid-cols-1 gap-4">
                    <?php
                    $edit_delivery_user_id = isset($_POST['edit_delivery_user']) ? $_POST['delivery_user_id'] : null;
                    $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'delivery'");
                    $stmt->execute();
                    while ($row = $stmt->fetch()) {
                        echo "<div class='bg-white p-4 rounded-lg shadow'>";
                        if ($edit_delivery_user_id == $row['id']) {
                            // Edit form
                            echo "<form method='post' action='admin.php#delivery'>";
                            echo "<input type='hidden' name='edit_delivery_user_id' value='{$row['id']}'>";
                            echo "<input type='text' name='edit_delivery_username' value='{$row['username']}' class='p-2 border rounded mb-2' required> ";
                            echo "<input type='email' name='edit_delivery_email' value='{$row['email']}' class='p-2 border rounded mb-2' required> ";
                            echo "<button type='submit' name='edit_delivery_user_submit' class='bg-green-600 text-white py-1 px-2 rounded hover:bg-green-700'>Save</button>";
                            echo "</form>";
                        } else {
                            echo "<p>Username: {$row['username']}</p>";
                            echo "<p>Email: {$row['email']}</p>";
                            echo "<form method='post' action='admin.php#delivery' style='display:inline'>";
                            echo "<input type='hidden' name='delivery_user_id' value='{$row['id']}'>";
                            echo "<button type='submit' name='edit_delivery_user' class='bg-blue-600 text-white py-1 px-2 rounded hover:bg-blue-700'>Edit</button> ";
                            echo "<button type='submit' name='delete_delivery_user' class='bg-red-600 text-white py-1 px-2 rounded hover:bg-red-700'>Delete</button>";
                            echo "</form>";
                        }
                        echo "</div>";
                    }
                    ?>
                </div>
            </section>

            <!-- Order Statistics -->
            <section id="orders" class="tab-section" style="display:none">
                <h2 class="text-3xl font-bold text-center mb-6">Order Statistics</h2>
                <div class="bg-white p-4 rounded-lg shadow">
                    <?php
                    $stmt = $conn->query("SELECT COUNT(*) as total_orders FROM orders");
                    $total_orders = $stmt->fetch()['total_orders'];
                    $stmt = $conn->query("SELECT COUNT(*) as completed_orders FROM orders WHERE status = 'completed'");
                    $completed_orders = $stmt->fetch()['completed_orders'];
                    echo "<p>Total Orders: $total_orders</p>";
                    echo "<p>Completed Orders: $completed_orders</p>";

                    // Hiển thị tổng tiền từng đơn hàng
                    echo "<h3 class='text-xl font-bold mt-4 mb-2'>Chi tiết từng đơn hàng:</h3>";
                    $stmt = $conn->query("SELECT id, total_price FROM orders");
                    echo "<ul class='mb-4'>";
                    while ($row = $stmt->fetch()) {
                        echo "<li>Đơn hàng #{$row['id']}: " . number_format($row['total_price'], 0, ',', '.') . " VND</li>";
                    }
                    echo "</ul>";

                    // Tổng doanh thu
                    $stmt = $conn->query("SELECT SUM(total_price) as total_revenue FROM orders");
                    $total_revenue = $stmt->fetch()['total_revenue'] ?? 0;
                    echo "<p>Tổng doanh thu: " . number_format($total_revenue, 0, ',', '.') . " VND</p>";
                    ?>
                </div>
            </section>
        </div>
    </div>
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-8">
        <div class="container mx-auto text-center">
            <p>Contact us: support@fooddelivery.com | +84 123 456 789</p>
        </div>
    </footer>

    <script>
    function showTab(tab) {
        // Hide all
        document.querySelectorAll('.tab-section').forEach(s => s.style.display = 'none');
        document.getElementById(tab).style.display = '';
        // Update sidebar
        document.querySelectorAll('.sidebar-btn').forEach(btn => btn.classList.remove('sidebar-tab-active'));
        document.querySelectorAll('.sidebar-btn').forEach(btn => btn.classList.add('sidebar-tab-inactive'));
        document.getElementById('tab-' + tab).classList.add('sidebar-tab-active');
        document.getElementById('tab-' + tab).classList.remove('sidebar-tab-inactive');
    }
    // Default tab
    showTab('users');
    </script>
</body>
</html>
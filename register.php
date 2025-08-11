<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'connect.php';
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $repassword = $_POST['repassword'];
    $role = $_POST['role'];
    // Lấy địa chỉ theo role
    $address = '';
    if ($role == 'restaurant') {
        $address = isset($_POST['restaurant_address']) ? trim($_POST['restaurant_address']) : '';
    } elseif ($role == 'delivery') {
        $address = isset($_POST['delivery_address']) ? trim($_POST['delivery_address']) : '';
    }
    // Validate input
    if (empty($username) || empty($email) || empty($phone) || empty($password)) {
        $error = "Vui lòng điền đầy đủ thông tin.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ.";
    } elseif ($password !== $repassword) {
        $error = "Mật khẩu nhập lại không khớp.";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } else {
        // Check for existing user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR phone = ?");
        $stmt->execute([$username, $email, $phone]);
        if ($stmt->fetch()) {
            $error = "Tài khoản này đã tồn tại.";
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Start transaction
            $conn->beginTransaction();       
            try {
                // Insert into users table
                $stmt = $conn->prepare("INSERT INTO users (username, password, role, email, phone, address) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $password_hash, $role, $email, $phone, $address]);
                $user_id = $conn->lastInsertId();
                
                // Handle role-specific data
                if ($role == 'restaurant') {
                    $restaurant_name = trim($_POST['restaurant_name']);
                    if (empty($restaurant_name)) {
                        throw new Exception("Vui lòng nhập tên nhà hàng.");
                    }
                    $stmt = $conn->prepare("INSERT INTO restaurants (user_id, name, address, phone) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $restaurant_name, $address, $phone]);
                } elseif ($role == 'delivery') {
                    $stmt = $conn->prepare("INSERT INTO delivery (user_id, name, phone, status) VALUES (?, ?, ?, 'available')");
                    $stmt->execute([$user_id, $username, $phone]);
                }
                
                // Commit transaction
                $conn->commit();
                
                header("Location: login.php");
                exit();
            } catch (Exception $e) {
                // Rollback transaction if error occurs
                $conn->rollBack();
                $error = "Đăng ký thất bại: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Roboto', sans-serif;
        }
        .register-container {
            width: 340px;
            margin: 80px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 28px 24px 20px 24px;
        }
        .register-title {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #ee4d2d;
            margin-bottom: 18px;
        }
        .register-form label {
            display: block;
            font-weight: 500;
            color: #333;
            margin-top: 10px;
        }
        .register-form input[type="text"],
        .register-form input[type="password"],
        .register-form input[type="email"],
        .register-form select {
            width: 100%;
            padding: 9px 10px;
            margin: 7px 0 14px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            box-sizing: border-box;
        }
        .register-form input[type="submit"] {
            width: 100%;
            background: #ee4d2d;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
        }
        .register-form input[type="submit"]:hover {
            background: #d7381a;
        }
        .error-message {
            color: #ee4d2d;
            text-align: center;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .register-footer {
            text-align: center;
            margin-top: 14px;
            color: #888;
            font-size: 13px;
        }
        .register-footer a {
            color: #ee4d2d;
            text-decoration: none;
            font-weight: 500;
        }
        .popup-error {
            position: fixed;
            top: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #fff0f0;
            color: #ee4d2d;
            border: 1.5px solid #ee4d2d;
            border-radius: 8px;
            padding: 14px 32px 14px 18px;
            font-size: 16px;
            font-weight: 500;
            box-shadow: 0 2px 12px rgba(238,77,45,0.12);
            z-index: 9999;
            min-width: 220px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .close-btn {
            margin-left: 12px;
            font-size: 20px;
            font-weight: bold;
            color: #ee4d2d;
            cursor: pointer;
            line-height: 1;
        }
        .role-fields {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 6px;
        }
    </style>
</head>
<body>
<div class="register-container">
    <div class="register-title">Đăng ký</div>
    <?php if ($error): ?>
        <div id="popup-error" class="popup-error">
            <?php echo htmlspecialchars($error); ?>
            <span class="close-btn" onclick="document.getElementById('popup-error').style.display='none'">&times;</span>
        </div>
    <?php endif; ?>
    <form class="register-form" method="post" action="">
        <label for="username">Tên đăng nhập</label>
        <input type="text" id="username" name="username" required maxlength="50">
        
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required maxlength="100">
        
        <label for="phone">Số điện thoại</label>
        <input type="text" id="phone" name="phone" required maxlength="20">
        
        <label for="password">Mật khẩu (ít nhất 6 ký tự)</label>
        <input type="password" id="password" name="password" required minlength="6" maxlength="255">
        
        <label for="repassword">Nhập lại mật khẩu</label>
        <input type="password" id="repassword" name="repassword" required minlength="6" maxlength="255">
        
        <label for="role">Vai trò</label>
        <select id="role" name="role" onchange="toggleRoleFields()" required>
            <option value="customer">Khách hàng</option>
            <option value="restaurant">Nhà hàng</option>
            <option value="delivery">Giao hàng</option>
        </select>
        
        <div id="restaurant-fields" class="role-fields">
            <label for="restaurant_name">Tên nhà hàng</label>
            <input type="text" id="restaurant_name" name="restaurant_name">
            <label for="restaurant_address">Địa chỉ</label>
            <input type="text" id="restaurant_address" name="restaurant_address" maxlength="255">
        </div>
        
        <div id="delivery-fields" class="role-fields">
            <label for="delivery_address">Địa chỉ</label>
            <input type="text" id="delivery_address" name="delivery_address" maxlength="255">
        </div>
        
        <input type="submit" name="submit" value="Đăng ký">
    </form>
    <div class="register-footer">
        Đã có tài khoản? <a href="login.php">Đăng nhập</a>
    </div>
</div>

<script>
function toggleRoleFields() {
    const role = document.getElementById('role').value;
    document.getElementById('restaurant-fields').style.display = role === 'restaurant' ? 'block' : 'none';
    document.getElementById('delivery-fields').style.display = role === 'delivery' ? 'block' : 'none';

    // Đồng bộ address field khi chuyển đổi role
    if (role === 'restaurant') {
        document.getElementById('delivery_address').value = '';
    } else if (role === 'delivery') {
        document.getElementById('restaurant_address').value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleRoleFields(); // Initialize fields visibility
    
    const popup = document.getElementById('popup-error');
    if (popup) {
        setTimeout(function() {
            popup.style.display = 'none';
        }, 5000);
    }
    
    // Prevent form submission if there are errors
    document.querySelector('.register-form').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const repassword = document.getElementById('repassword').value;
        
        if (password !== repassword) {
            e.preventDefault();
            alert('Mật khẩu nhập lại không khớp!');
            return false;
        }
        
        return true;
    });
});
</script>
</body>
</html>
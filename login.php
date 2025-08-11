<?php
session_start();
require 'connect.php';

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        if ($user['role'] == 'customer') {
            header("Location: customer.php");
            exit();
        } elseif ($user['role'] == 'restaurant') {
            header("Location: restaurant.php");
            exit();
        } elseif ($user['role'] == 'delivery') {
            header("Location: delivery.php");
            exit();
        } elseif ($user['role'] == 'admin') {
            header("Location: admin.php");
            exit();
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Roboto', sans-serif;
        }
        .login-container {
            width: 340px;
            margin: 80px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 28px 24px 20px 24px;
        }
        .login-title {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #ee4d2d;
            margin-bottom: 18px;
        }
        .login-form label {
            font-weight: 500;
            color: #333;
        }
        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 9px 10px;
            margin: 7px 0 14px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
        }
        .login-form input[type="submit"] {
            width: 100%;
            background: #ee4d2d;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }
        .login-form input[type="submit"]:hover {
            background: #d7381a;
        }
        .error-message {
            color: #ee4d2d;
            text-align: center;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .login-footer {
            text-align: center;
            margin-top: 14px;
            color: #888;
            font-size: 13px;
        }
        .login-footer a {
            color: #ee4d2d;
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-title">Đăng nhập</div>
    <?php if ($error) echo "<div class='error-message'>$error</div>"; ?>
    <form class="login-form" method="post">
        <label for="username">Tên đăng nhập</label>
        <input type="text" id="username" name="username" required>
        <label for="password">Mật khẩu</label>
        <input type="password" id="password" name="password" required>
        <input type="submit" value="Đăng nhập">
    </form>
    <div class="login-footer">
        Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
    </div>
</div>
</body>
</html>
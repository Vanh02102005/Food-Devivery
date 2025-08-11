<?php
session_start();
require 'connect.php';
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vui lòng đăng nhập để thực hiện thao tác";
    header("Location: login.php");
    exit();
}
if (!isset($_POST['order_id']) || !is_numeric($_POST['order_id'])) {
    $_SESSION['error'] = "Thiếu thông tin đơn hàng";
    header("Location: customer.php");
    exit();
}
$order_id = (int)$_POST['order_id'];

try {
    $stmt = $conn->prepare("
        SELECT id, status 
        FROM orders 
        WHERE id = ? AND customer_id = ? AND status = 'pending'
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        $_SESSION['error'] = "Không thể hủy đơn hàng #$order_id. Đơn hàng đã được xác nhận hoặc không tồn tại.";
        header("Location: order.php?order_id=$order_id");
        exit();
    }
    $update = $conn->prepare("
        UPDATE orders 
        SET status = 'cancelled', 
            cancelled_at = NOW()
        WHERE id = ?
    ");
    if ($update->execute([$order_id])) {
        $_SESSION['success'] = "Đã hủy thành công đơn hàng #$order_id";
    } else {
        $_SESSION['error'] = "Không thể cập nhật trạng thái đơn hàng";
    }
} catch (PDOException $e) {
    error_log("Lỗi hủy đơn hàng: " . $e->getMessage());
    $_SESSION['error'] = "Có lỗi xảy ra khi hủy đơn hàng. Vui lòng liên hệ hỗ trợ.";
}
header("Location: order.php?order_id=$order_id");
exit();
?>
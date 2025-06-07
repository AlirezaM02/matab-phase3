<?php
session_start();
require 'db_connect.php';

// بررسی اینکه آیا پزشک وارد شده است
if (!isset($_SESSION['doctor_id'])) {
    header("Location: doctor_login.php");
    exit();
}

$doctor_credential_id = (int)$_SESSION['doctor_id'];
$status_message = '';

// دریافت اطلاعات پزشک
$sql = "SELECT id, name FROM doctors WHERE credential_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_credential_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    // اگر در جدول doctors رکوردی نباشد، به صفحه لاگین با خطا برمی‌گردیم
    header("Location: doctor_login.php?error=حساب پزشک یافت نشد.");
    exit();
}

$doctor_id = $doctor['id'];
$doctor_name = $doctor['name'];

// پردازش تغییر وضعیت نوبت
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['booking_id']) && isset($_POST['action'])) {
    $booking_id = (int)$_POST['booking_id'];
    $action = $_POST['action'];

    $sql = "SELECT id FROM bookings WHERE id = ? AND doctor_id = ?";
    $stmt_check = $conn->prepare($sql);
    $stmt_check->bind_param("ii", $booking_id, $doctor_id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        if ($action === 'confirm') {
            $sql_update = "UPDATE bookings SET status = 'confirmed' WHERE id = ?";
        } elseif ($action === 'cancel') {
            $sql_update = "DELETE FROM bookings WHERE id = ?";
        }
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("i", $booking_id);
        if ($stmt_update->execute()) {
            $status_message = $action === 'confirm' ? "نوبت با موفقیت تأیید شد!" : "نوبت با موفقیت حذف شد!";
        } else {
            $status_message = "خطایی در پردازش درخواست رخ داد.";
        }
        $stmt_update->close();
    } else {
        $status_message = "این نوبت متعلق به شما نیست.";
    }
    $stmt_check->close();
}

// دریافت نوبت‌های رزروشده
$sql_bookings = "SELECT b.id, b.booking_date, b.status, u.full_name, u.phone 
                 FROM bookings b 
                 JOIN users u ON b.user_id = u.id 
                 WHERE b.doctor_id = ? 
                 ORDER BY b.booking_date DESC";
$stmt_bookings = $conn->prepare($sql_bookings);
$stmt_bookings->bind_param("i", $doctor_id);
$stmt_bookings->execute();
$bookings = $stmt_bookings->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_bookings->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <link rel="icon" type="image/x-icon" href="https://i.postimg.cc/J0dCfhLH/1111.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل پزشک - مطب</title>
    <style>
        body { font-family: 'Almarai', 'Segoe UI', Tahoma, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
        .details-container { max-width: 1000px; margin: 0 auto; background-color: white; padding: 40px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header-container { text-align: center; margin-bottom: 30px; }
        .logo { width: 100px; height: 100px; margin-bottom: 15px; }
        .site-name { color: black; margin: 10px 0; }
        h2 { color: #0d47a1; margin-bottom: 20px; text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: right; }
        th { background-color: #0d47a1; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #f1f1f1; }
        .action-btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin: 0 5px; }
        .confirm-btn { background-color: #28a745; color: white; }
        .confirm-btn:hover { background-color: #218838; }
        .cancel-btn { background-color: #dc3545; color: white; }
        .cancel-btn:hover { background-color: #c82333; }
        .action-btn-group { display: flex; justify-content: flex-start; gap: 5px; }
        .btn-general { display: block; width: 100%; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 10px; text-align: center; text-decoration: none; box-sizing: border-box; }
        .logout-btn { background-color: #dc3545; color: white; }
        .logout-btn:hover { background-color: #c82333; }
        .support-btn { background-color: #007bff; color: white; }
        .support-btn:hover { background-color: #0056b3; }
        .no-bookings { color: #666; text-align: center; margin-top: 20px; padding: 20px; background-color: #fafafa; border-radius: 5px; }
        .message { margin: 15px 0; padding: 10px; border-radius: 5px; text-align: center; }
        .success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <div class="details-container">
        <div class="header-container">
            <a href="index.php"><img src="https://i.postimg.cc/J0dCfhLH/1111.jpg" alt="لوگو" class="logo"></a>
            <h3 class="site-name">مطب - با ما، درمان نزدیک‌تر از همیشه</h3>
        </div>
        <h2>پنل دکتر <?php echo htmlspecialchars($doctor_name); ?> - مدیریت نوبت‌ها</h2>
        <?php if ($status_message): ?>
            <p class="message <?php echo strpos($status_message, 'موفقیت') !== false ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($status_message); ?></p>
        <?php endif; ?>
        <?php if (empty($bookings)): ?>
            <p class="no-bookings">هیچ نوبت رزروشده‌ای برای شما وجود ندارد.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>نام بیمار</th>
                        <th>شماره تماس</th>
                        <th>تاریخ و زمان نوبت</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['phone']); ?></td>
                            <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                            <td>
                                <?php
                                $status_text = 'نامشخص';
                                if ($booking['status'] == 'pending') $status_text = 'در انتظار تأیید';
                                if ($booking['status'] == 'confirmed') $status_text = 'تأییدشده';
                                echo $status_text;
                                ?>
                            </td>
                            <td>
                                <?php if ($booking['status'] == 'pending'): ?>
                                    <div class="action-btn-group">
                                        <form method="POST" style="display: inline;"><input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>"><input type="hidden" name="action" value="confirm"><button type="submit" class="action-btn confirm-btn">تأیید</button></form>
                                        <form method="POST" style="display: inline;"><input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>"><input type="hidden" name="action" value="cancel"><button type="submit" class="action-btn cancel-btn">حذف</button></form>
                                    </div>
                                <?php else: ?>
                                    <span>-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- EDIT: Added Support button and separated from Logout -->
        <a href="support.php" class="btn-general support-btn">پشتیبانی فنی</a>
        <a href="logout.php" class="btn-general logout-btn">خروج از حساب کاربری</a>
    </div>
</body>
</html>

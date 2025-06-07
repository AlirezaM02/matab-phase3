<?php
// support_api.php
header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';

$response = ['success' => false, 'message' => 'درخواست نامعتبر.'];
$action = $_REQUEST['action'] ?? null;

// All actions require a logged-in user
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'لطفا ابتدا وارد شوید.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if user is support staff
function is_support_staff($conn, $user_id) {
    $stmt = $conn->prepare("SELECT staff_id FROM support_staff WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->num_rows > 0;
}
$is_staff = is_support_staff($conn, $user_id);

switch ($action) {
    //== User Actions ==//
    case 'check_staff_online':
        $stmt = $conn->prepare("SELECT COUNT(*) as online_count FROM support_staff WHERE is_online = 1 AND last_active > (NOW() - INTERVAL 5 MINUTE)");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $response['success'] = true;
        $response['online'] = $result['online_count'] > 0;
        break;

    case 'create_ticket_offline':
        $title = $_POST['title'] ?? '';
        $message = $_POST['message'] ?? '';
        
        if (empty($title) || empty($message)) {
            $response['message'] = 'عنوان و پیام نمی‌توانند خالی باشند.';
            break;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, title, status) VALUES (?, ?, 'باز')");
            $stmt->bind_param("is", $user_id, $title);
            $stmt->execute();
            $ticket_id = $stmt->insert_id;
            $stmt->close();

            $attachment_path = handle_attachment_upload($ticket_id);

            $stmt_msg = $conn->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_type, message, attachment_path) VALUES (?, ?, 'user', ?, ?)");
            $stmt_msg->bind_param("iiss", $ticket_id, $user_id, $message, $attachment_path);
            $stmt_msg->execute();
            $stmt_msg->close();

            $conn->commit();
            $response['success'] = true;
            $response['message'] = 'تیکت با موفقیت ایجاد شد.';
        } catch (Exception $e) {
            $conn->rollback();
            $response['message'] = 'خطای پایگاه داده: ' . $e->getMessage();
        }
        break;
    
    case 'create_ticket_live':
        $title = $_POST['title'] ?? 'چت زنده';
        $message = $_POST['message'] ?? '';
        if (empty($message)) {
            $response['message'] = 'پیام نمی‌تواند خالی باشد.';
            break;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, title, status) VALUES (?, ?, 'باز')");
            $stmt->bind_param("is", $user_id, $title);
            $stmt->execute();
            $ticket_id = $stmt->insert_id;
            $stmt->close();

            $stmt_msg = $conn->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_type, message) VALUES (?, ?, 'user', ?)");
            $stmt_msg->bind_param("iis", $ticket_id, $user_id, $message);
            $stmt_msg->execute();
            $stmt_msg->close();
            
            // Mark the ticket as 'in progress' since it's a live chat
            $update_stmt = $conn->prepare("UPDATE support_tickets SET status = 'در حال بررسی' WHERE ticket_id = ?");
            $update_stmt->bind_param("i", $ticket_id);
            $update_stmt->execute();
            $update_stmt->close();

            $conn->commit();
            $response['success'] = true;
            $response['ticket_id'] = $ticket_id;
        } catch (Exception $e) {
            $conn->rollback();
            $response['message'] = 'خطای پایگاه داده: ' . $e->getMessage();
        }
        break;

    case 'send_message':
        $ticket_id = $_POST['ticket_id'] ?? 0;
        $message = $_POST['message'] ?? '';
        if (empty($message) || empty($ticket_id)) {
            $response['message'] = 'شناسه تیکت و پیام الزامی است.';
            break;
        }

        // Verify user has access to this ticket
        $stmt_verify = $conn->prepare("SELECT user_id FROM support_tickets WHERE ticket_id = ?");
        $stmt_verify->bind_param("i", $ticket_id);
        $stmt_verify->execute();
        $ticket_owner = $stmt_verify->get_result()->fetch_assoc();
        $stmt_verify->close();

        if (!$is_staff && (!$ticket_owner || $ticket_owner['user_id'] != $user_id)) {
            $response['message'] = 'شما دسترسی به این تیکت را ندارید.';
            break;
        }
        
        $sender_type = $is_staff ? 'support' : 'user';
        $stmt = $conn->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_type, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $ticket_id, $user_id, $sender_type, $message);
        
        if ($stmt->execute()) {
            // Update ticket status if support replies
            if ($is_staff) {
                $update_stmt = $conn->prepare("UPDATE support_tickets SET status = 'پاسخ داده شد', updated_at = NOW() WHERE ticket_id = ?");
                $update_stmt->bind_param("i", $ticket_id);
                $update_stmt->execute();
                $update_stmt->close();
            } else {
                 $update_stmt = $conn->prepare("UPDATE support_tickets SET status = 'باز', updated_at = NOW() WHERE ticket_id = ? AND status != 'بسته شده'");
                 $update_stmt->bind_param("i", $ticket_id);
                 $update_stmt->execute();
                 $update_stmt->close();
            }
            $response['success'] = true;
        } else {
            $response['message'] = 'خطا در ارسال پیام.';
        }
        $stmt->close();
        break;

    case 'get_messages':
        $ticket_id = $_GET['ticket_id'] ?? 0;
        if (empty($ticket_id)) break;

        // Verify access again
        $stmt_verify = $conn->prepare("SELECT user_id FROM support_tickets WHERE ticket_id = ?");
        $stmt_verify->bind_param("i", $ticket_id);
        $stmt_verify->execute();
        $ticket_owner = $stmt_verify->get_result()->fetch_assoc();
        $stmt_verify->close();

        if (!$is_staff && (!$ticket_owner || $ticket_owner['user_id'] != $user_id)) {
            $response['message'] = 'دسترسی غیر مجاز.';
            break;
        }

        $stmt = $conn->prepare("SELECT sender_id, sender_type, message, attachment_path, created_at FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $response['success'] = true;
        $response['messages'] = $messages;
        break;

    //== Staff Actions ==//
    case 'update_staff_status':
        if (!$is_staff) {
            $response['message'] = 'فقط کارمندان پشتیبانی می‌توانند وضعیت را به‌روز کنند.';
            break;
        }
        $stmt = $conn->prepare("UPDATE support_staff SET is_online = 1, last_active = NOW() WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        $response['success'] = true;
        break;

    case 'get_tickets':
        if (!$is_staff) break;
        
        $status_filter = $_GET['status'] ?? 'all';
        $sql = "SELECT t.ticket_id, t.title, t.status, t.created_at, u.name as user_name FROM support_tickets t JOIN users u ON t.user_id = u.id";
        
        if($status_filter != 'all') {
            $sql .= " WHERE t.status = ?";
        }
        $sql .= " ORDER BY t.updated_at DESC";

        $stmt = $conn->prepare($sql);
        if($status_filter != 'all') {
            $stmt->bind_param("s", $status_filter);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $tickets = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $response['success'] = true;
        $response['tickets'] = $tickets;
        break;

    case 'update_ticket_status':
        if (!$is_staff) break;

        $ticket_id = $_POST['ticket_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        $allowed_statuses = ['باز', 'در حال بررسی', 'پاسخ داده شد', 'بسته شده'];

        if (empty($ticket_id) || !in_array($status, $allowed_statuses)) {
             $response['message'] = 'شناسه تیکت یا وضعیت نامعتبر است.';
             break;
        }
        
        $stmt = $conn->prepare("UPDATE support_tickets SET status = ? WHERE ticket_id = ?");
        $stmt->bind_param("si", $status, $ticket_id);
        if ($stmt->execute()) {
            $response['success'] = true;
        } else {
            $response['message'] = 'خطا در بروزرسانی وضعیت.';
        }
        $stmt->close();
        break;
}

// Function to handle file uploads
function handle_attachment_upload($ticket_id) {
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $target_dir = "uploads/support_attachments/";
        // Create a unique filename
        $file_extension = pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION);
        $safe_filename = "ticket_" . $ticket_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $safe_filename;

        // Basic validation
        $allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'zip'];
        if (!in_array(strtolower($file_extension), $allowed_types)) {
            return null; // or set an error message
        }
        if ($_FILES["attachment"]["size"] > 5000000) { // 5MB limit
            return null; // or set an error message
        }

        if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
            return $target_file;
        }
    }
    return null;
}

echo json_encode($response);
$conn->close();
?>

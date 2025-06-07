<?php
// support.php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's existing tickets
$tickets_stmt = $conn->prepare("SELECT ticket_id, title, status, created_at FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$tickets_stmt->bind_param("i", $user_id);
$tickets_stmt->execute();
$tickets_result = $tickets_stmt->get_result();
$tickets = $tickets_result->fetch_all(MYSQLI_ASSOC);
$tickets_stmt->close();

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پشتیبانی</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
        .chat-bubble-user { background-color: #DCF8C6; }
        .chat-bubble-support { background-color: #FFFFFF; }
        .loader {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100">

    <div class="container mx-auto p-4 md:p-8">
        <div class="flex justify-between items-center mb-6">
             <h1 class="text-3xl font-bold text-gray-800">مرکز پشتیبانی</h1>
             <a href="index.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300">بازگشت به صفحه اصلی</a>
        </div>


        <!-- Main Content Area -->
        <div id="main-content" class="bg-white p-6 rounded-lg shadow-lg">
            <div id="loading-area" class="text-center py-8">
                <div class="loader mx-auto"></div>
                <p class="mt-4 text-gray-600">در حال بررسی وضعیت تیم پشتیبانی...</p>
            </div>

            <!-- Offline Form Area -->
            <div id="offline-form-area" class="hidden">
                 <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-md" role="alert">
                    <p class="font-bold">پشتیبانی آفلاین است</p>
                    <p>در حال حاضر هیچ یک از کارشناسان ما آنلاین نیستند. لطفاً یک تیکت ثبت کنید تا در اسرع وقت به آن رسیدگی کنیم.</p>
                </div>
                <h2 class="text-2xl font-bold mb-4">ایجاد تیکت جدید</h2>
                <form id="ticket-form" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="title" class="block text-gray-700 font-bold mb-2">عنوان</label>
                        <input type="text" id="title" name="title" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div class="mb-4">
                        <label for="message" class="block text-gray-700 font-bold mb-2">شرح مشکل</label>
                        <textarea id="message" name="message" rows="5" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
                    </div>
                    <div class="mb-4">
                        <label for="attachment" class="block text-gray-700 font-bold mb-2">افزودن فایل (اختیاری)</label>
                        <input type="file" id="attachment" name="attachment" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300">ارسال تیکت</button>
                </form>
            </div>
            
            <!-- Live Chat Area -->
            <div id="live-chat-area" class="hidden">
                 <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
                    <p class="font-bold">پشتیبانی آنلاین است!</p>
                    <p>یکی از کارشناسان ما آماده پاسخگویی به شماست. لطفاً پیام خود را در کادر زیر بنویسید.</p>
                </div>
                 <div id="chat-box" class="h-96 border rounded-lg p-4 mb-4 overflow-y-auto bg-gray-50 flex flex-col space-y-4">
                    <!-- Messages will be loaded here -->
                </div>
                <form id="chat-form">
                    <div class="flex">
                        <input type="text" id="chat-message-input" placeholder="پیام خود را تایپ کنید..." class="flex-grow px-3 py-2 border rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-r-lg">ارسال</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- User's Ticket History -->
        <div class="mt-10 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-bold mb-4">تاریخچه تیکت‌های شما</h2>
            <div id="ticket-list" class="space-y-4">
                <?php if (empty($tickets)): ?>
                    <p class="text-gray-500">شما هنوز هیچ تیکتی ثبت نکرده‌اید.</p>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <div class="border p-4 rounded-lg hover:bg-gray-50 transition cursor-pointer" onclick="openTicketChat(<?php echo $ticket['ticket_id']; ?>)">
                            <div class="flex justify-between items-center">
                                <p class="font-bold text-lg text-blue-600"><?php echo htmlspecialchars($ticket['title']); ?></p>
                                <span class="px-3 py-1 text-sm font-semibold rounded-full 
                                    <?php 
                                        switch($ticket['status']) {
                                            case 'باز': echo 'bg-blue-200 text-blue-800'; break;
                                            case 'در حال بررسی': echo 'bg-yellow-200 text-yellow-800'; break;
                                            case 'پاسخ داده شد': echo 'bg-green-200 text-green-800'; break;
                                            case 'بسته شده': echo 'bg-gray-200 text-gray-800'; break;
                                        }
                                    ?>">
                                    <?php echo htmlspecialchars($ticket['status']); ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">ایجاد شده در: <?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal for displaying chat history of a specific ticket -->
    <div id="ticket-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-h-[90vh] flex flex-col">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 id="modal-title" class="text-xl font-bold">گفتگو</h3>
                <button onclick="closeTicketModal()" class="text-gray-500 hover:text-gray-800">&times;</button>
            </div>
            <div id="modal-chat-box" class="p-4 overflow-y-auto flex-grow bg-gray-50">
                <!-- Chat messages for the selected ticket will be loaded here -->
            </div>
            <div class="p-4 border-t">
                 <form id="modal-chat-form">
                    <input type="hidden" id="modal-ticket-id">
                    <div class="flex">
                        <input type="text" id="modal-chat-message-input" placeholder="پاسخ خود را بنویسید..." class="flex-grow px-3 py-2 border rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-r-lg">ارسال</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


<script>
let liveChatTicketId = null;
let activeTicketId = null;
let messagePollingInterval = null;

// Check staff status on page load
document.addEventListener('DOMContentLoaded', () => {
    fetch('support_api.php?action=check_staff_online')
        .then(response => response.json())
        .then(data => {
            document.getElementById('loading-area').classList.add('hidden');
            if (data.online) {
                document.getElementById('live-chat-area').classList.remove('hidden');
            } else {
                document.getElementById('offline-form-area').classList.remove('hidden');
            }
        });
});

// Handle offline ticket form submission
document.getElementById('ticket-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'create_ticket_offline');

    fetch('support_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('تیکت شما با موفقیت ثبت شد!');
            window.location.reload();
        } else {
            alert('خطا در ثبت تیکت: ' + data.message);
        }
    });
});


// Handle live chat form submission
document.getElementById('chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const messageInput = document.getElementById('chat-message-input');
    const message = messageInput.value.trim();
    if (!message) return;

    const action = liveChatTicketId ? 'send_message' : 'create_ticket_live';
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('message', message);
    if(liveChatTicketId) {
        formData.append('ticket_id', liveChatTicketId);
    } else {
        formData.append('title', 'چت زنده');
    }

    fetch('support_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            if (data.ticket_id && !liveChatTicketId) {
                liveChatTicketId = data.ticket_id;
                // Start polling for new messages
                if(messagePollingInterval) clearInterval(messagePollingInterval);
                messagePollingInterval = setInterval(() => fetchMessages(liveChatTicketId, 'chat-box'), 3000);
            }
            fetchMessages(liveChatTicketId, 'chat-box'); // Fetch immediately after sending
        } else {
            alert('خطا در ارسال پیام: ' + data.message);
        }
    });
});

function fetchMessages(ticketId, chatBoxId) {
    if (!ticketId) return;
    fetch(`support_api.php?action=get_messages&ticket_id=${ticketId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const chatBox = document.getElementById(chatBoxId);
                const shouldScroll = chatBox.scrollTop + chatBox.clientHeight === chatBox.scrollHeight;
                
                chatBox.innerHTML = ''; // Clear previous messages
                data.messages.forEach(msg => {
                    const messageDiv = document.createElement('div');
                    messageDiv.classList.add('p-3', 'rounded-lg', 'max-w-xs', 'md:max-w-md');
                    
                    if(msg.sender_type === 'user') {
                        messageDiv.classList.add('chat-bubble-user', 'self-end', 'mr-auto');
                    } else {
                        messageDiv.classList.add('chat-bubble-support', 'self-start', 'ml-auto', 'border', 'border-gray-200');
                    }
                    
                    messageDiv.innerHTML = `
                        <p class="text-sm">${msg.message}</p>
                        ${msg.attachment_path ? `<a href="${msg.attachment_path}" target="_blank" class="text-xs text-blue-600 block mt-1">فایل ضمیمه</a>` : ''}
                        <p class="text-xs text-gray-500 mt-2 text-left">${new Date(msg.created_at).toLocaleTimeString('fa-IR')}</p>
                    `;
                    chatBox.appendChild(messageDiv);
                });

                if (shouldScroll) {
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
            }
        });
}


// Ticket History Modal Logic
function openTicketChat(ticketId) {
    activeTicketId = ticketId;
    const modal = document.getElementById('ticket-modal');
    document.getElementById('modal-ticket-id').value = ticketId;
    document.getElementById('modal-title').innerText = `گفتگو - تیکت #${ticketId}`;
    
    fetchMessages(ticketId, 'modal-chat-box');
    modal.classList.remove('hidden');

    if (messagePollingInterval) clearInterval(messagePollingInterval);
    messagePollingInterval = setInterval(() => fetchMessages(ticketId, 'modal-chat-box'), 3000);
}

function closeTicketModal() {
    document.getElementById('ticket-modal').classList.add('hidden');
    activeTicketId = null;
    if (messagePollingInterval) {
        clearInterval(messagePollingInterval);
    }
}

document.getElementById('modal-chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const messageInput = document.getElementById('modal-chat-message-input');
    const message = messageInput.value.trim();
    const ticketId = document.getElementById('modal-ticket-id').value;

    if (!message || !ticketId) return;

    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('message', message);
    formData.append('ticket_id', ticketId);

    fetch('support_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            fetchMessages(ticketId, 'modal-chat-box');
        } else {
            alert('خطا در ارسال پیام: ' + data.message);
        }
    });
});
</script>

</body>
</html>

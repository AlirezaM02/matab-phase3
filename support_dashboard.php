<?php
// support_dashboard.php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if the logged-in user is a support staff member
$stmt = $conn->prepare("SELECT staff_id FROM support_staff WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    // If not a staff, redirect to home or show an error
    echo "شما دسترسی به این صفحه را ندارید.";
    exit();
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد پشتیبانی</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
        .chat-bubble-user { background-color: #E1F5FE; }
        .chat-bubble-support { background-color: #DCF8C6; }
        #ticket-list .active {
            background-color: #e0f2fe;
            border-right-width: 4px;
            border-right-color: #0284c7;
        }
    </style>
</head>
<body class="bg-gray-100">

<div class="flex h-screen">
    <!-- Sidebar with Ticket List -->
    <div class="w-1/3 bg-white border-l h-full flex flex-col">
        <div class="p-4 border-b">
            <h2 class="text-xl font-bold">تیکت‌های پشتیبانی</h2>
        </div>
        <div class="p-2">
            <select id="status-filter" class="w-full p-2 border rounded-md">
                <option value="all">همه تیکت‌ها</option>
                <option value="باز">باز</option>
                <option value="در حال بررسی">در حال بررسی</option>
                <option value="پاسخ داده شد">پاسخ داده شد</option>
                <option value="بسته شده">بسته شده</option>
            </select>
        </div>
        <div id="ticket-list" class="overflow-y-auto flex-grow">
            <!-- Ticket list will be loaded here -->
        </div>
    </div>

    <!-- Main Chat Area -->
    <div class="w-2/3 flex flex-col h-screen">
        <div id="chat-area" class="flex-grow flex flex-col bg-gray-50 hidden">
            <!-- Chat Header -->
            <div class="p-4 bg-white border-b flex justify-between items-center">
                <div>
                    <h3 id="chat-header-title" class="font-bold"></h3>
                    <p id="chat-header-user" class="text-sm text-gray-600"></p>
                </div>
                <div>
                     <select id="ticket-status-changer" class="p-2 border rounded-md">
                        <option value="باز">باز</option>
                        <option value="در حال بررسی">در حال بررسی</option>
                        <option value="پاسخ داده شد">پاسخ داده شد</option>
                        <option value="بسته شده">بسته شده</option>
                    </select>
                </div>
            </div>
            <!-- Chat Box -->
            <div id="chat-box" class="flex-grow p-4 overflow-y-auto flex flex-col space-y-4">
                <!-- Messages will be loaded here -->
            </div>
            <!-- Chat Input -->
            <div class="p-4 bg-white border-t">
                <form id="chat-form">
                    <div class="flex">
                        <input type="text" id="chat-message-input" placeholder="پاسخ خود را بنویسید..." class="flex-grow px-3 py-2 border rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-r-lg">ارسال پاسخ</button>
                    </div>
                </form>
            </div>
        </div>
        <div id="welcome-area" class="flex-grow flex items-center justify-center bg-gray-50">
            <div class="text-center">
                <h2 class="text-2xl text-gray-500">لطفا یک تیکت را برای مشاهده انتخاب کنید.</h2>
            </div>
        </div>
    </div>
</div>

<script>
let activeTicketId = null;
let ticketListInterval = null;
let messagePollingInterval = null;

// Heartbeat to keep staff online status active
function sendHeartbeat() {
    fetch('support_api.php?action=update_staff_status', { method: 'POST' });
}
sendHeartbeat(); // Initial heartbeat
setInterval(sendHeartbeat, 60 * 1000); // Send heartbeat every 1 minute

// Load ticket list
function loadTicketList() {
    const filter = document.getElementById('status-filter').value;
    fetch(`support_api.php?action=get_tickets&status=${filter}`)
        .then(response => response.json())
        .then(data => {
            const ticketList = document.getElementById('ticket-list');
            ticketList.innerHTML = '';
            if (data.success && data.tickets.length > 0) {
                data.tickets.forEach(ticket => {
                    const div = document.createElement('div');
                    div.className = `p-4 border-b cursor-pointer hover:bg-gray-100 ${ticket.ticket_id == activeTicketId ? 'active' : ''}`;
                    div.onclick = () => selectTicket(ticket.ticket_id, ticket.title, ticket.user_name, ticket.status);
                    
                    let statusClass = '';
                    switch(ticket.status) {
                        case 'باز': statusClass = 'bg-blue-200 text-blue-800'; break;
                        case 'در حال بررسی': statusClass = 'bg-yellow-200 text-yellow-800'; break;
                        case 'پاسخ داده شد': statusClass = 'bg-green-200 text-green-800'; break;
                        case 'بسته شده': statusClass = 'bg-gray-200 text-gray-800'; break;
                    }

                    div.innerHTML = `
                        <div class="flex justify-between items-start">
                            <p class="font-bold">${ticket.title}</p>
                            <span class="text-xs font-semibold px-2 py-1 rounded-full ${statusClass}">${ticket.status}</span>
                        </div>
                        <p class="text-sm text-gray-600">کاربر: ${ticket.user_name}</p>
                        <p class="text-xs text-gray-500 mt-1">${new Date(ticket.created_at).toLocaleString('fa-IR')}</p>
                    `;
                    ticketList.appendChild(div);
                });
            } else {
                ticketList.innerHTML = '<p class="p-4 text-center text-gray-500">هیچ تیکتی یافت نشد.</p>';
            }
        });
}

// Select a ticket to view conversation
function selectTicket(ticketId, title, userName, status) {
    activeTicketId = ticketId;
    
    document.getElementById('welcome-area').classList.add('hidden');
    document.getElementById('chat-area').classList.remove('hidden');

    document.getElementById('chat-header-title').innerText = `تیکت #${ticketId}: ${title}`;
    document.getElementById('chat-header-user').innerText = `گفتگو با ${userName}`;
    
    const statusChanger = document.getElementById('ticket-status-changer');
    statusChanger.value = status;

    loadTicketList(); // To highlight the active ticket
    
    fetchMessages(ticketId);
    if(messagePollingInterval) clearInterval(messagePollingInterval);
    messagePollingInterval = setInterval(() => fetchMessages(ticketId), 3000); // Poll for new messages every 3 seconds
}


function fetchMessages(ticketId) {
    if (!ticketId) return;
    fetch(`support_api.php?action=get_messages&ticket_id=${ticketId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const chatBox = document.getElementById('chat-box');
                const shouldScroll = chatBox.scrollTop + chatBox.clientHeight >= chatBox.scrollHeight - 30;

                chatBox.innerHTML = ''; // Clear previous messages
                data.messages.forEach(msg => {
                    const messageDiv = document.createElement('div');
                    messageDiv.classList.add('p-3', 'rounded-lg', 'max-w-xs', 'md:max-w-md');
                    
                    if(msg.sender_type === 'support') {
                        messageDiv.classList.add('chat-bubble-support', 'self-end', 'mr-auto');
                    } else {
                        messageDiv.classList.add('chat-bubble-user', 'self-start', 'ml-auto', 'border');
                    }
                    
                    messageDiv.innerHTML = `
                        <p class="text-sm">${msg.message}</p>
                        ${msg.attachment_path ? `<a href="${msg.attachment_path}" target="_blank" class="text-xs text-blue-600 block mt-1">فایل ضمیمه</a>` : ''}
                        <p class="text-xs text-gray-500 mt-2 text-left">${new Date(msg.created_at).toLocaleTimeString('fa-IR')}</p>
                    `;
                    chatBox.appendChild(messageDiv);
                });
                
                if(shouldScroll){
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
            }
        });
}

// Handle reply form submission
document.getElementById('chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const messageInput = document.getElementById('chat-message-input');
    const message = messageInput.value.trim();

    if (!message || !activeTicketId) return;

    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('message', message);
    formData.append('ticket_id', activeTicketId);

    fetch('support_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            fetchMessages(activeTicketId); // Fetch immediately after sending
        } else {
            alert('خطا در ارسال پیام: ' + data.message);
        }
    });
});

// Handle status change
document.getElementById('ticket-status-changer').addEventListener('change', function(e){
    if(!activeTicketId) return;
    const newStatus = e.target.value;

    const formData = new FormData();
    formData.append('action', 'update_ticket_status');
    formData.append('ticket_id', activeTicketId);
    formData.append('status', newStatus);

    fetch('support_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success){
            // Optionally show a success message
            loadTicketList(); // Refresh list to show new status
        } else {
            alert('خطا در بروزرسانی وضعیت: ' + data.message);
        }
    });
});

// Handle filter change
document.getElementById('status-filter').addEventListener('change', loadTicketList);

// Initial Load
document.addEventListener('DOMContentLoaded', () => {
    loadTicketList();
    ticketListInterval = setInterval(loadTicketList, 10000); // Refresh ticket list every 10 seconds
});

</script>
</body>
</html>

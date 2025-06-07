<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// تمرير user_id إلى JavaScript
$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <link rel="icon" type="image/x-icon" href="https://i.postimg.cc/J0dCfhLH/1111.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Almarai&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پروفایل - مطب</title>
    <style>
        body {
            font-family: 'Almarai', sans-serif;
            margin: 0;
            background-color: #f4f4f4;
        }
        .header {
            background: linear-gradient(90deg, #007bff, #0d47a1);
            color: white;
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            width: 100%;
            justify-content: space-between;
        }
        .logo {
            width: 60px;
            border-radius: 30px;
        }
        .title {
            color: white;
            flex-grow: 1;
            text-align: center;
            margin: 0;
        }
        .header-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .profile-btn {
            padding: 10px 20px;
            background: #1a237e;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            text-decoration: none;
        }
        .profile-btn:hover {
            background: #0d47a1;
        }
        .profile-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .profile-section {
            margin-bottom: 30px; /* Increased margin */
        }
        .profile-section h2 {
            color: #0d47a1;
            margin-bottom: 15px; /* Increased margin */
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .profile-section input {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px 0; /* Added bottom margin */
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .profile-section button, .support-btn-link {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
        }
        .profile-section button:hover, .support-btn-link:hover {
            background: #0d47a1;
        }
        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .bookings-table th, .bookings-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        .bookings-table th {
            background: #e9ecef;
            color: #333;
        }
        .back-btn {
            display: block;
            width: fit-content;
            margin: 20px auto;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            font-size: 1rem;
            text-decoration: none;
        }
        .back-btn:hover {
            background: #5a6268;
        }
        .error { color: red; text-align: center; margin: 10px 0; }
        .success { color: green; text-align: center; margin: 10px 0; }
    </style>
    <script>
        const userId = <?php echo json_encode($user_id); ?>;

        function updateHeaderButtons() {
            const authButton = document.getElementById('authButton');
            authButton.textContent = 'خروج';
            authButton.onclick = () => window.location.href = 'logout.php';

            const supportButton = document.getElementById('supportButton');
            supportButton.style.display = 'inline-block';
        }

        async function loadProfile() {
            if (!userId) {
                window.location.href = 'login.php';
                return;
            }
            try {
                const response = await fetch(`get_profile.php?user_id=${encodeURIComponent(userId)}`);
                const user = await response.json();
                
                if (user.error) {
                    document.getElementById('profileInfo').innerHTML = `<p class="error">${user.error}</p>`;
                    return;
                }
                document.getElementById('name').value = user.name || '';
                document.getElementById('national_id').value = user.national_id || '';
                document.getElementById('insurance_number').value = user.insurance_number || '';
                document.getElementById('email').value = user.email || '';
            } catch (error) {
                document.getElementById('profileInfo').innerHTML = `<p class="error">خطا در بارگذاری اطلاعات: ${error.message}</p>`;
            }
        }

        async function updateProfile() {
            const name = document.getElementById('name').value.trim();
            const national_id = document.getElementById('national_id').value.trim();
            const insurance_number = document.getElementById('insurance_number').value.trim();
            const email = document.getElementById('email').value.trim();

            if (!name || !national_id || !email) {
                document.getElementById('updateMessage').innerHTML = `<p class="error">لطفاً همه فیلدهای ستاره‌دار را پر کنید.</p>`;
                return;
            }

            try {
                const response = await fetch('update_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, name, national_id, insurance_number, email })
                });
                const result = await response.json();
                const messageDiv = document.getElementById('updateMessage');
                if (result.success) {
                    messageDiv.innerHTML = '<p class="success">اطلاعات با موفقیت به‌روزرسانی شد.</p>';
                } else {
                    messageDiv.innerHTML = `<p class="error">خطا: ${result.error}</p>`;
                }
            } catch (error) {
                document.getElementById('updateMessage').innerHTML = `<p class="error">خطا در به‌روزرسانی: ${error.message}</p>`;
            }
        }

        async function loadBookings() {
            try {
                const response = await fetch(`get_bookings.php?user_id=${encodeURIComponent(userId)}`);
                const bookings = await response.json();
                const tableBody = document.getElementById('bookingsTableBody');
                tableBody.innerHTML = '';
                if (Array.isArray(bookings) && bookings.length > 0) {
                    bookings.forEach(booking => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${booking.booking_date || 'N/A'}</td>
                            <td>${booking.booking_type === 'doctor' ? 'پزشک' : booking.booking_type === 'lab' ? 'آزمایشگاه' : booking.booking_type === 'clinic' ? 'کلینیک' : 'تصویربرداری'}</td>
                            <td>${booking.target_name || 'N/A'}</td>
                            <td>${booking.status === 'pending' ? 'در انتظار تأیید' : booking.status === 'confirmed' ? 'تأیید شده' : 'لغو شده'}</td>
                        `;
                        tableBody.appendChild(row);
                    });
                } else {
                    tableBody.innerHTML = '<tr><td colspan="4">هیچ رزروی یافت نشد.</td></tr>';
                }
            } catch (error) {
                document.getElementById('bookingsTableBody').innerHTML = `<tr><td colspan="4" class="error">خطا در بارگذاری رزروها</td></tr>`;
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            updateHeaderButtons();
            loadProfile();
            loadBookings();
        });
    </script>
</head>
<body>
    <header class="header">
        <a href="index.php">
            <img src="https://i.postimg.cc/J0dCfhLH/1111.jpg" alt="لوگو" class="logo">
        </a>
        <h4 class="title">مطب - با ما، درمان نزدیک‌تر از همیشه</h4>
        <div class="header-buttons">
            <a href="support.php" id="supportButton" class="profile-btn" style="display: none;">پشتیبانی</a>
            <button id="authButton" class="profile-btn"></button>
        </div>
    </header>
    <div class="profile-container">
        <div class="profile-section" id="profileInfo">
            <h2>اطلاعات شخصی</h2>
            <input type="text" id="name" placeholder="نام و نام خانوادگی" required>
            <input type="text" id="national_id" placeholder="شماره ملی" required>
            <input type="text" id="insurance_number" placeholder="شماره بیمه">
            <input type="email" id="email" placeholder="ایمیل" required>
            <button onclick="updateProfile()">ذخیره تغییرات</button>
            <div id="updateMessage"></div>
        </div>
        
        <div class="profile-section">
            <h2>سوابق رزرو</h2>
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>تاریخ رزرو</th>
                        <th>نوع رزرو</th>
                        <th>نام پزشک/مرکز</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody id="bookingsTableBody"></tbody>
            </table>
        </div>
        
        <!-- EDIT: Added Support Section -->
        <div class="profile-section">
            <h2>پشتیبانی</h2>
            <p>برای ارتباط با تیم پشتیبانی یا پیگیری تیکت‌های خود از دکمه زیر استفاده کنید.</p>
            <a href="support.php" class="support-btn-link">ورود به مرکز پشتیبانی</a>
        </div>

    </div>
    <a href="index.php" class="back-btn">بازگشت به صفحه اصلی</a>
</body>
</html>

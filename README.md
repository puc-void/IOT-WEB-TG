# 🚀 ESP32 RFID Attendance Management System (PHP + MySQL + Tailwind CSS)

একটি সম্পূর্ণ আধুনিক, রেসপন্সিভ এবং রিয়েল-টাইম **ESP32 RFID Attendance Management System**। এই সিস্টেমে ESP32 মাইক্রোকন্ট্রোলার এবং RC522 RFID Reader এর মাধ্যমে কার্ড স্ক্যান করে সার্ভারে হাজিরা (In-Time, Out-Time) রেকর্ড করা হয়।

---

## ✨ Features (বৈশিষ্ট্যসমূহ)

1. **🔒 Admin Authentication Portal:**
   - সিকিউর অ্যাডমিন লগইন ও সেশন ম্যানেজমেন্ট।
   - ডিফল্ট লগইন ইউজার: `admin` / পাসওয়ার্ড: `admin123`

2. **🏫 Department Management (ডিপার্টমেন্ট বিভাগ):**
   - ডিপার্টমেন্ট যোগ, এডিট, ডিলেট করার সুবিধা (CRUD)।
   - ডিপার্টমেন্ট অনুসারে মোট স্টুডেন্ট কাউন্ট করার রিয়েল-টাইম কাউন্টার।

3. **👨‍🎓 Student Registration (শিক্ষার্থী নিবন্ধন ও কার্ড লিঙ্ক):**
   - স্টুডেন্ট নাম, আইডি, ডিপার্টমেন্ট, ইমেইল, ফোন ও RFID Card UID রেজিস্টার করার অপশন।
   - ফিল্টার ও সার্চ সিস্টেম।

4. **⏱️ Smart In-Time, Out-Time & Day Limit Validation:**
   - **১ম স্ক্যান:** ইন-টাইম (In-Time) হিসেবে কাউন্ট হবে।
   - **২য় স্ক্যান:** আউট-টাইম (Out-Time) হিসেবে কাউন্ট হবে।
   - **৩য়+ স্ক্যান (একই দিনে):** ১ দিনের লিমিট ওভার (Day Limit Exceeded) ওয়ার্নিং নোটিফিকেশন দেবে।
   - **Late Time Rule:** নির্ধারিত লেট টাইমের (যেমন: 09:30 AM) পর স্ক্যান করলে সয়ংক্রিয়ভাবে "Late" ব্যাজ দেখাবে।

5. **⚡ Virtual Web Scan Terminal (ওয়েব সিমুলেটর):**
   - হার্ডওয়্যার সংযোগ ছাড়াই ব্রাউজার থেকে সরাসরি RFID কার্ড স্ক্যান টেস্ট করার সুবিশাল সিমুলেটর।

6. **📊 Filter, Search & Export Options:**
   - তারিখ (Date), ডিপার্টমেন্ট এবং স্ট্যাটাস দিয়ে ফিল্টার করা।
   - **CSV Export:** এক ক্লিকে এক্সেল সমর্থিত CSV ডাউনলোড।
   - **Print / PDF:** ক্লিন প্রিন্ট ফ্রেন্ডলি রিপোর্ট ফরম্যাট।

---

## 🛠️ Requirements & Setup in XAMPP

### ১. ডেটাবেস সেটআপ (Database Import):
1. **XAMPP Control Panel** ওপেন করুন এবং **Apache** ও **MySQL** Start করুন।
2. ব্রাউজারে যান: `http://localhost/phpmyadmin`
3. একটি নতুন ডেটাবেস তৈরি করুন: `attendance_db` (অথবা তৈরি না করলেও হবে, স্ক্রিপ্টে অটো ক্রিয়েট কোড আছে)।
4. **Import** ট্যাবে গিয়ে `database/schema.sql` ফাইলটি সিলেক্ট করে **Go** এ ক্লিক করুন।

### ২. প্রজেক্ট ফাইল ফোল্ডার:
* প্রজেক্ট ফোল্ডারটি XAMPP এর `htdocs` ফোল্ডারে রাখুন:
  `C:/xampp/htdocs/Attendance Management`

### ৩. ব্রাউজারে রান করুন:
* অ্যাডমিন প্যানেল: `http://localhost/Attendance%20Management/auth/login.php`
* ইউজারনেম: `admin`
* পাসওয়ার্ড: `admin123`

---

## 🔌 ESP32 & RFID RC522 Hardware Connections

### Pin Mapping (ESP32 <-> MFRC522):
| MFRC522 Pin | ESP32 GPIO Pin | Description |
| :--- | :--- | :--- |
| **VCC** | **3.3V** | (3.3V পাওয়ার সোর্স) |
| **RST** | **GPIO 22** | Reset Pin |
| **GND** | **GND** | Ground |
| **MISO** | **GPIO 19** | SPI MISO |
| **MOSI** | **GPIO 23** | SPI MOSI |
| **SCK** | **GPIO 18** | SPI Clock |
| **SDA (SS)** | **GPIO 5** | SPI Slave Select |
| **Green LED** | **GPIO 2** | Success Scan |
| **Red LED** | **GPIO 15** | Invalid / Limit Card |
| **Buzzer** | **GPIO 4** | Beep Sound |

### Arduino IDE Libraries Needed:
1. `MFRC522` by githubmfrc522
2. `ArduinoJson` (v6 or v7)

### ESP32 Code Path:
Arduino কোডটি পাবেন: `esp32/esp32_rfid_attendance.ino`
* কোডের ভেতরে আপনার Wi-Fi নাম (`ssid`), পাসওয়ার্ড (`password`), এবং আপনার পিসির IP ঠিক করে আপলোড দিন।

---

## 🌐 API Endpoint for ESP32

```http
POST /Attendance%20Management/api/rfid_scan.php
Content-Type: application/x-www-form-urlencoded

card_uid=A3B4C5D6
```

### JSON Success Response Example:
```json
{
  "status": "success",
  "type": "IN",
  "student_name": "Tanvir Ahmed",
  "student_code": "CSE-2024-001",
  "dept_code": "CSE",
  "time": "08:45:10 AM",
  "attendance_status": "On Time",
  "message": "Welcome Tanvir Ahmed! In-Time recorded (On Time)"
}
```

---

## 📁 Project File Structure

```
Attendance Management/
├── api/
│   ├── get_live_logs.php      # Real-time AJAX logs & statistics API
│   └── rfid_scan.php          # ESP32 RFID processing API endpoint
├── assets/                    # Static assets
├── auth/
│   ├── login.php              # Admin login page
│   └── logout.php             # Session logout
├── config/
│   └── db.php                 # PDO Database connection & helper functions
├── database/
│   └── schema.sql             # SQL Schema & initial test data
├── esp32/
│   └── esp32_rfid_attendance.ino # Complete ESP32 Arduino C++ code
├── includes/
│   ├── footer.php             # Common footer & scripts
│   ├── header.php             # Navbar, clock & Tailwind CDN
│   └── sidebar.php            # Navigation sidebar
├── attendance.php             # Attendance logs table, filter, CSV export
├── departments.php            # Department CRUD management
├── index.php                  # Dashboard overview with live stream
├── rfid_terminal.php          # Virtual Web Scan Scanner Terminal
├── students.php               # Student registration & card binding
└── README.md                  # Project documentation
```

---
Developed for **ESP32 RFID Attendance Management**.
# IOT-WEB-TG

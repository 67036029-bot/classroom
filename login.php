<?php
session_start();
include 'db.php';

// เคลียร์ Error เก่า
$error_msg = "";
if (isset($_SESSION['login_error'])) {
    $error_msg = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// ถ้าล็อกอินค้างไว้แล้ว ให้เด้งไปหน้า Dashboard ตามสิทธิ์
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'teacher') header("Location: index.php"); // 🟢 แก้เป็น index.php
    else header("Location: student_dashboard.php");
    exit();
}

if (isset($_POST['login_btn'])) {
    $role = $_POST['role']; // รับค่าจากปุ่มสลับ (student / teacher)
    $password = trim($_POST['password']);

    if ($role == 'teacher') {
        // --- 1. ส่วนของคุณครู (ใช้รหัสผ่านอย่างเดียว) ---
        // เช็คจากฐานข้อมูล tb_course_info (ตามโค้ดเดิมของคุณ)
        $stmt = $conn->prepare("SELECT teacher_name, teacher_password FROM tb_course_info LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row_teacher = $result->fetch_assoc();
            // ตรวจสอบรหัสผ่าน (รองรับทั้ง Hash และ Plain Text เพื่อความยืดหยุ่น)
            if (password_verify($password, $row_teacher['teacher_password']) || $password == $row_teacher['teacher_password']) {
                session_regenerate_id(true);
                $_SESSION['role'] = 'teacher';
                $_SESSION['user_name'] = $row_teacher['teacher_name'];
                
                // 🟢 ส่งครูไปหน้า Dashboard (index.php)
                header("Location: index.php"); 
                exit();
            } else {
                $error_msg = "รหัสผ่านครูไม่ถูกต้อง";
            }
        } else {
            // Fallback: กรณีฐานข้อมูลยังไม่มีข้อมูลครู ให้ใช้รหัสสำรอง '1234'
            if($password == '1234') {
                $_SESSION['role'] = 'teacher';
                // 🟢 ส่งครูไปหน้า Dashboard (index.php)
                header("Location: index.php");
                exit();
            }
            $error_msg = "ไม่พบข้อมูลผู้สอนในระบบ";
        }

    } elseif ($role == 'student') {
        // --- 2. ส่วนของนักเรียน (รหัสนักเรียน + รหัสผ่าน) ---
        $username = trim($_POST['username']); // รับรหัสนักเรียน

        $stmt = $conn->prepare("SELECT * FROM tb_students WHERE std_code = ? AND password = ?");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            session_regenerate_id(true);
            
            $_SESSION['std_id'] = $row['id'];
            $_SESSION['role'] = 'student';
            $_SESSION['std_name'] = $row['firstname'] . ' ' . $row['lastname'];
            
            // 🛑 เช็คว่าเป็นรหัสเริ่มต้น "12345" หรือไม่?
            if ($password == '12345') {
                $_SESSION['force_change_pwd'] = true; // ติดธงว่าต้องเปลี่ยนรหัส
                header("Location: force_change_pwd.php"); // ส่งไปหน้าบังคับเปลี่ยน
            } else {
                header("Location: student_dashboard.php");
            }
            exit();
        } else {
            $error_msg = "รหัสนักเรียน หรือ รหัสผ่าน ไม่ถูกต้อง";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Login - Classroom Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
    :root {
        --primary-color: #d63384; /* สีชมพูหลัก */
        --glass-bg: rgba(255, 255, 255, 0.1);
        --glass-border: rgba(255, 255, 255, 0.2);
    }

    body {
        font-family: 'Sarabun', sans-serif;
        background: radial-gradient(circle at top left, #1e1e2f, #0f0f1a);
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }

    /* --- Animated Background (Orbs) --- */
    .bg-orb {
        position: absolute; border-radius: 50%; filter: blur(60px); z-index: 0; opacity: 0.5;
        animation: float 10s infinite alternate;
    }
    .orb-1 { width: 400px; height: 400px; background: #4f46e5; top: -100px; left: -100px; animation-delay: 0s; }
    .orb-2 { width: 300px; height: 300px; background: var(--primary-color); bottom: -50px; right: -50px; animation-delay: 2s; }
    .orb-3 { width: 200px; height: 200px; background: #00d4ff; top: 30%; left: 60%; opacity: 0.2; animation-delay: 4s; }

    @keyframes float {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(20px, 40px) scale(1.05); }
    }

    /* --- Glass Card --- */
    .login-card {
        background: var(--glass-bg);
        backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px);
        width: 100%; max-width: 420px;
        border-radius: 24px; border: 1px solid var(--glass-border);
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        padding: 40px 35px; position: relative; z-index: 10; text-align: center;
    }

    /* --- Role Switcher (New Style) --- */
    .role-switch {
        display: flex; background: rgba(0,0,0,0.2); border-radius: 50px;
        padding: 5px; margin-bottom: 25px; border: 1px solid var(--glass-border);
    }
    .role-btn {
        flex: 1; text-align: center; padding: 10px; border-radius: 50px;
        cursor: pointer; color: rgba(255,255,255,0.6); font-weight: 600; transition: 0.3s;
        font-family: 'Poppins', sans-serif; font-size: 0.9rem;
    }
    .role-btn:hover { color: white; }
    .role-btn.active {
        background: var(--primary-color); color: white;
        box-shadow: 0 4px 15px rgba(214, 51, 132, 0.4);
    }

    /* --- Logo & Text --- */
    .logo-container {
        width: 90px; height: 90px;
        background: rgba(255,255,255,0.9); border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 15px; border: 4px solid rgba(255,255,255,0.1);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 10px;
    }
    .logo-container img { width: 100%; height: 100%; object-fit: contain; }

    .app-name { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.3rem; color: white; margin-bottom: 5px; }
    .school-name { color: rgba(255,255,255,0.7); font-size: 0.85rem; margin-bottom: 25px; }

    /* --- Form Elements --- */
    .form-control-neon {
        background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
        border-radius: 50px; color: white; padding: 12px 20px;
        text-align: center; font-size: 1rem; transition: 0.3s;
    }
    .form-control-neon:focus {
        background: rgba(255,255,255,0.2); border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(214, 51, 132, 0.2); color: white; outline: none;
    }
    .form-control-neon::placeholder { color: rgba(255,255,255,0.5); }

    .btn-login-neon {
        background: linear-gradient(90deg, #d63384, #be185d); border: none;
        width: 100%; padding: 12px; border-radius: 50px; color: white;
        font-weight: 700; font-size: 1rem; margin-top: 10px;
        box-shadow: 0 4px 15px rgba(214, 51, 132, 0.4); transition: 0.3s;
    }
    .btn-login-neon:hover {
        transform: translateY(-2px); box-shadow: 0 8px 25px rgba(214, 51, 132, 0.5);
    }

    .alert-error {
        background: rgba(220, 53, 69, 0.2); border: 1px solid rgba(220, 53, 69, 0.3);
        color: #ff8686; border-radius: 15px; font-size: 0.9rem; margin-bottom: 20px;
    }
    
    .footer-credit { position: absolute; bottom: 15px; width: 100%; text-align: center; color: rgba(255,255,255,0.2); font-size: 0.75rem; }
    </style>
</head>
<body>

    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>
    <div class="bg-orb orb-3"></div>

    <div class="login-card">
        <div class="logo-container">
            <img src="images/logo.png" alt="Logo" onerror="this.src='https://cdn-icons-png.flaticon.com/512/2995/2995620.png'">
        </div>

        <div class="app-name">Classroom Management</div>
        <div class="school-name">Bansuanjananusorn School</div>

        <?php if($error_msg != ""): ?>
            <div class="alert alert-error text-center py-2">
                <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <input type="hidden" name="role" id="roleInput" value="student">

            <div class="role-switch">
                <div class="role-btn active" id="tabStudent" onclick="switchRole('student')">
                    <i class="fa-solid fa-user-graduate me-1"></i> นักเรียน
                </div>
                <div class="role-btn" id="tabTeacher" onclick="switchRole('teacher')">
                    <i class="fa-solid fa-chalkboard-user me-1"></i> คุณครู
                </div>
            </div>

            <div id="studentFields">
                <div class="mb-3 position-relative">
                    <i class="fa-solid fa-id-card position-absolute text-white-50" style="top: 15px; left: 20px;"></i>
                    <input type="text" name="username" class="form-control form-control-neon ps-5" 
                           placeholder="รหัสนักเรียน (5 หลัก)" autocomplete="off">
                </div>
            </div>

            <div class="mb-4 position-relative">
                <i class="fa-solid fa-lock position-absolute text-white-50" style="top: 15px; left: 20px;"></i>
                <input type="password" name="password" class="form-control form-control-neon ps-5" 
                       placeholder="รหัสผ่าน" required autocomplete="off">
            </div>

            <button type="submit" name="login_btn" class="btn-login-neon">
                เข้าสู่ระบบ
            </button>
        </form>
    </div>

    <div class="footer-credit">&copy; <?php echo date("Y"); ?> Digital Classroom System</div>

    <script>
        function switchRole(role) {
            // อัปเดตค่า role ใน input hidden
            document.getElementById('roleInput').value = role;

            // สลับ Class Active ของปุ่ม
            if (role === 'student') {
                document.getElementById('tabStudent').classList.add('active');
                document.getElementById('tabTeacher').classList.remove('active');
                
                // แสดงช่องรหัสนักเรียน
                document.getElementById('studentFields').style.display = 'block';
                document.querySelector('input[name="password"]').placeholder = "รหัสผ่าน";
                document.querySelector('input[name="username"]').required = true;
            } else {
                document.getElementById('tabTeacher').classList.add('active');
                document.getElementById('tabStudent').classList.remove('active');
                
                // ซ่อนช่องรหัสนักเรียน
                document.getElementById('studentFields').style.display = 'none';
                document.querySelector('input[name="password"]').placeholder = "รหัสผ่านสำหรับครู";
                document.querySelector('input[name="username"]').required = false;
            }
        }
    </script>

</body>
</html>
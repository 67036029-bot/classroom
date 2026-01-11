<?php
session_start();
if (!isset($_SESSION['std_id'])) { header("Location: login.php"); exit(); }
include 'db.php';

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// 1. ดึงข้อมูล PIN และชื่อชุดข้อสอบ
// ใช้ JOIN เพื่อดึง work_name ด้วย
$sql_exam = "SELECT tbs.access_pin, tbs.exam_id, tbw.work_name 
             FROM tb_exam_sets tbs 
             JOIN tb_work tbw ON tbs.work_id = tbw.work_id 
             WHERE tbs.exam_id = '$exam_id' AND tbs.is_active = 1";
             
$res_exam = $conn->query($sql_exam);

if ($res_exam->num_rows == 0) { exit("<center><h1>ไม่พบข้อสอบ หรือการสอบถูกปิดแล้ว</h1><a href='student_dashboard.php'>กลับหน้าหลัก</a></center>"); }
$exam_data = $res_exam->fetch_assoc();
$required_pin = $exam_data['access_pin'];
$exam_name = $exam_data['work_name'];

$pin_error = "";

// 2. ตรวจสอบการส่ง PIN
if (isset($_POST['submit_pin'])) {
    $submitted_pin = $_POST['access_pin'];
    
    if ($submitted_pin === $required_pin) {
        // PIN ถูกต้อง!
        // ส่งไปหน้าทำข้อสอบ (โดยส่งรหัสผ่านไปทาง Session หรือปล่อยผ่านได้เลยถ้าหน้านั้นไม่เช็ค PIN ซ้ำ)
        // เพื่อความปลอดภัย เราควร Set session flag ว่าผ่านด่านแล้ว แต่เบื้องต้น Redirect ไปก่อน
        $_SESSION['exam_unlocked_' . $exam_id] = true;
        header("Location: exam_take.php?exam_id=$exam_id");
        exit();
    } else {
        $pin_error = "รหัสผ่านเข้าสอบไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Enter Exam PIN</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { max-width: 450px; border-radius: 15px; }
        .pin-input { font-size: 2.5rem; height: 80px; text-align: center; letter-spacing: 0.5rem; font-weight: 800; border: 2px solid #ccc; }
        .pin-input:focus { border-color: #d63384; box-shadow: 0 0 0 0.25rem rgba(214, 51, 132, 0.25); }
        .btn-primary { background-color: #d63384; border-color: #d63384; }
        .btn-primary:hover { background-color: #a61e61; border-color: #a61e61; }
    </style>
</head>
<body>
    <div class="card shadow-lg p-4">
        <div class="card-body text-center">
            <i class="fa-solid fa-lock-open fs-1 text-muted mb-3"></i>
            <h4 class="card-title fw-bold mb-4">🔐 รหัสผ่านเข้าห้องสอบ</h4>
            <p class="text-muted">โปรดกรอกรหัส PIN 4 หลัก ที่ครูกำหนดเพื่อเริ่มทำข้อสอบ</p>
            <h5 class="mb-3 fw-bold text-dark">วิชา/ชิ้นงาน: **<?php echo htmlspecialchars($exam_name); ?>**</h5>
            
            <form method="POST">
                <?php if ($pin_error): ?>
                    <div class="alert alert-danger mb-3 py-2" role="alert">
                        <?php echo $pin_error; ?>
                    </div>
                <?php endif; ?>
                <div class="mb-4">
                    <input type="text" name="access_pin" class="form-control pin-input fw-bold" maxlength="4" required autofocus placeholder="••••">
                </div>
                <button type="submit" name="submit_pin" class="btn btn-primary w-100 fw-bold py-3">เริ่มทำข้อสอบ</button>
            </form>
            
            <a href="student_dashboard.php" class="small text-muted mt-3 d-block">ยกเลิกและกลับหน้าหลัก</a>
        </div>
    </div>
</body>
</html>
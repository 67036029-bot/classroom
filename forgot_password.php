<?php
session_start();
include 'db.php';

$msg = "";

if (isset($_POST['reset_btn'])) {
    $email = trim($_POST['email']);
    
    // 1. ตรวจสอบว่ามีอีเมลนี้ในตารางครูหรือไม่
    // หมายเหตุ: ต้องมั่นใจว่าในหน้า Settings คุณได้กรอกอีเมลบันทึกไว้แล้ว
    $stmt = $conn->prepare("SELECT id FROM tb_course_info WHERE teacher_email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // 2. พบอีเมล! ทำการรีเซ็ตรหัสผ่านเป็น "1234"
        // ต้องทำการ Hash รหัส 1234 ก่อนบันทึก เพื่อให้ Login ได้
        $new_pass_plain = "1234";
        $new_hash = password_hash($new_pass_plain, PASSWORD_DEFAULT);
        
        // อัปเดตรหัสผ่านใหม่ลงฐานข้อมูล (อัปเดตทุก Row ที่เป็นครูคนนี้)
        $update = $conn->prepare("UPDATE tb_course_info SET teacher_password = ? WHERE teacher_email = ?");
        $update->bind_param("ss", $new_hash, $email);
        
        if ($update->execute()) {
            $msg = "
            <div class='alert alert-success text-center border-0 shadow-sm rounded-4'>
                <i class='fa-solid fa-circle-check fa-3x mb-3 text-success'></i><br>
                <h4 class='fw-bold'>ยืนยันตัวตนสำเร็จ!</h4>
                <p>รหัสผ่านของคุณถูกรีเซ็ตเป็นค่าเริ่มต้นเรียบร้อยแล้ว</p>
                <div class='bg-white border rounded p-2 mb-3 d-inline-block fw-bold fs-4 text-primary px-4'>
                    1234
                </div>
                <p class='small text-muted'>กรุณานำรหัสนี้ไปเข้าสู่ระบบและเปลี่ยนรหัสผ่านใหม่ทันที</p>
                <a href='login.php' class='btn btn-primary w-100 rounded-pill fw-bold'>กลับไปเข้าสู่ระบบ</a>
            </div>";
        } else {
            $msg = "<div class='alert alert-danger rounded-pill text-center'>เกิดข้อผิดพลาดในการบันทึกข้อมูล</div>";
        }
    } else {
        // 3. ไม่พบอีเมล
        $msg = "<div class='alert alert-danger rounded-4 text-center py-3'>
                    <i class='fa-solid fa-circle-xmark fa-2x mb-2'></i><br>
                    <strong>ไม่พบอีเมลนี้ในระบบ</strong><br>
                    <span class='small'>กรุณาตรวจสอบความถูกต้อง หรือติดต่อผู้ดูแลระบบ</span>
                </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>กู้คืนรหัสผ่าน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f2f3f5;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-forgot {
            width: 100%;
            max-width: 400px;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            background: white;
        }
        .header-bg {
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            padding: 30px 20px;
            text-align: center;
            color: white;
        }
    </style>
</head>
<body>

    <div class="card-forgot">
        <?php if(empty($msg) || strpos($msg, 'alert-danger') !== false): ?>
            <div class="header-bg">
                <i class="fa-solid fa-key fa-3x mb-2 opacity-75"></i>
                <h4 class="fw-bold mb-0">ลืมรหัสผ่าน?</h4>
                <p class="small text-white-50 mb-0">ระบบกู้คืนบัญชีสำหรับครูผู้สอน</p>
            </div>
            
            <div class="p-4">
                <?php echo $msg; ?>
                
                <p class="text-muted small text-center mb-4">
                    กรุณากรอก <b>อีเมล (Email)</b> ที่ท่านได้บันทึกไว้ในเมนูตั้งค่า <br>
                    ระบบจะทำการรีเซ็ตรหัสผ่านเป็น <b>1234</b>
                </p>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary ms-2">อีเมลยืนยันตัวตน</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 rounded-start-pill ps-3">
                                <i class="fa-solid fa-envelope text-muted"></i>
                            </span>
                            <input type="email" name="email" class="form-control border-start-0 rounded-end-pill py-2 bg-light" placeholder="name@example.com" required>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" name="reset_btn" class="btn btn-primary rounded-pill py-2 fw-bold shadow-sm">
                            ยืนยันและรีเซ็ตรหัสผ่าน
                        </button>
                        <a href="login.php" class="btn btn-light rounded-pill py-2 text-muted small">
                            ยกเลิก / กลับหน้าเข้าสู่ระบบ
                        </a>
                    </div>
                </form>
            </div>
        
        <?php else: ?>
            <div class="p-4">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
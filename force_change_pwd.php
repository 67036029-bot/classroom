<?php
session_start();
include 'db.php';

// ถ้าไม่ได้ล็อกอิน หรือไม่ได้โดนบังคับเปลี่ยนรหัส ให้ดีดออก
if (!isset($_SESSION['std_id']) || !isset($_SESSION['force_change_pwd'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['save_new_pass'])) {
    $p1 = $_POST['pass1'];
    $p2 = $_POST['pass2'];
    $std_id = $_SESSION['std_id'];

    if ($p1 !== $p2) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } elseif (strlen($p1) < 4) {
        $error = "รหัสผ่านต้องมีอย่างน้อย 4 ตัว";
    } elseif ($p1 == '12345') {
        $error = "ห้ามใช้รหัส 12345 ซ้ำ! กรุณาตั้งใหม่";
    } else {
        // บันทึกรหัสใหม่
        $stmt = $conn->prepare("UPDATE tb_students SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $p1, $std_id);
        if ($stmt->execute()) {
            // ปลดล็อก
            unset($_SESSION['force_change_pwd']);
            echo "<script>alert('✅ เปลี่ยนรหัสสำเร็จ! เข้าใช้งานได้เลย'); window.location='student_dashboard.php';</script>";
            exit();
        } else {
            $error = "เกิดข้อผิดพลาดทางระบบ";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตั้งรหัสผ่านใหม่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-force { max-width: 400px; border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .header-force { background: #ffc107; padding: 30px; text-align: center; }
    </style>
</head>
<body>
    <div class="card card-force">
        <div class="header-force">
            <h1 style="font-size: 3rem;">🔒</h1>
            <h4 class="fw-bold text-dark">ตั้งรหัสผ่านใหม่</h4>
            <p class="mb-0 text-dark opacity-75 small">เพื่อความปลอดภัย กรุณาเปลี่ยนรหัสผ่าน<br>จากค่าเริ่มต้น '12345' เป็นรหัสส่วนตัวของคุณ</p>
        </div>
        <div class="card-body p-4">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger text-center small py-2"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">รหัสผ่านใหม่ (ตัวเลข 4 หลักขึ้นไป)</label>
                    <input type="number" name="pass1" class="form-control rounded-pill text-center fw-bold" placeholder="xxxx" required>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">ยืนยันรหัสผ่านอีกครั้ง</label>
                    <input type="number" name="pass2" class="form-control rounded-pill text-center fw-bold" placeholder="xxxx" required>
                </div>
                <button type="submit" name="save_new_pass" class="btn btn-dark w-100 rounded-pill fw-bold py-2">บันทึกและเข้าใช้งาน</button>
            </form>
        </div>
    </div>
</body>
</html>
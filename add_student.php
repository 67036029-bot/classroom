
<?php

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}
include 'db.php';

if (isset($_POST['save_btn'])) {
    // รับค่าจากฟอร์ม
    $std_no = $_POST['std_no'];
    $std_code = $_POST['std_code'];
    $title = $_POST['title'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $room = $_POST['room'];

    // --- ส่วนที่แก้ไขใหม่ (Prevent SQL Injection) ---
    // ใช้ ? แทนตัวแปรโดยตรง
    $sql = "INSERT INTO tb_students (std_no, std_code, title, firstname, lastname, room) 
            VALUES (?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // ผูกตัวแปรเข้ากับ ? (s = string, i = integer)
        // std_no เป็น int หรือ string ก็ได้ แต่ในที่นี้ใช้ s ให้ครอบคลุม
        $stmt->bind_param("ssssss", $std_no, $std_code, $title, $firstname, $lastname, $room);
        
        if ($stmt->execute()) {
            echo "<script>alert('บันทึกข้อมูลสำเร็จ!'); window.location='list_students.php';</script>";
        } else {
            echo "Error executing: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error preparing: " . $conn->error;
    }
}
?>



<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มนักเรียนใหม่</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6f9; }
        .card-header-dark { background-color: #212529; color: #ff99cc; border-bottom: 3px solid #d63384; }
        .btn-pink { background-color: #d63384; color: white; border: none; }
        .btn-pink:hover { background-color: #a61e61; color: white; }
    </style>
</head>
<body class="p-4">
    <div class="container" style="max-width: 700px;">
        <div class="card shadow border-0">
            <div class="card-header card-header-dark p-3">
                <h4 class="mb-0"><i class="fa-solid fa-user-plus"></i> เพิ่มนักเรียนใหม่</h4>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="fw-bold text-muted small">เลขที่</label>
                            <input type="number" name="std_no" class="form-control" required>
                        </div>
                        <div class="col-md-8">
                            <label class="fw-bold text-muted small">รหัสนักเรียน</label>
                            <input type="text" name="std_code" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="fw-bold text-muted small">คำนำหน้า</label>
                            <select name="title" class="form-select" required>
                                <option value="ด.ช.">ด.ช.</option>
                                <option value="ด.ญ.">ด.ญ.</option>
                                <option value="นาย">นาย</option>
                                <option value="นางสาว">นางสาว</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="fw-bold text-muted small">ชื่อ</label>
                            <input type="text" name="firstname" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold text-muted small">นามสกุล</label>
                            <input type="text" name="lastname" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="fw-bold text-muted small">ห้องเรียน</label>
                        <input type="text" name="room" class="form-control" placeholder="เช่น ม.2/13" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="save_btn" class="btn btn-pink btn-lg shadow">บันทึกข้อมูล</button>
                        <a href="list_students.php" class="btn btn-light text-muted">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
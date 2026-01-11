<?php
session_start(); // เริ่มทำงาน Session
// ถ้าไม่มีสถานะ หรือ สถานะไม่ใช่ครู -> ดีดออกไปหน้า Login เดี๋ยวนี้!
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}
include 'db.php';
// ... โค้ดเดิมต่อจากนี้ ...


include 'db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM tb_students WHERE id = $id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
}

if (isset($_POST['update_btn'])) {
    $id = $_POST['id'];
    $std_no = $_POST['std_no'];
    $std_code = $_POST['std_code'];
    $title = $_POST['title'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $room = $_POST['room'];

    $sql = "UPDATE tb_students SET 
            std_no='$std_no', std_code='$std_code', title='$title',
            firstname='$firstname', lastname='$lastname', room='$room' 
            WHERE id=$id";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('แก้ไขสำเร็จ!'); window.location='list_students.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขข้อมูลนักเรียน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6f9; }
        .card-header-warning { background-color: #ffc107; color: #000; border-bottom: 3px solid #d39e00; }
        .btn-dark-pink { background-color: #212529; color: #ff99cc; border: none; }
        .btn-dark-pink:hover { background-color: #000; color: #fff; }
    </style>
</head>
<body class="p-4">
    <div class="container" style="max-width: 700px;">
        <div class="card shadow border-0">
            <div class="card-header card-header-warning p-3">
                <h4 class="mb-0"><i class="fa-solid fa-user-pen"></i> แก้ไขข้อมูลนักเรียน</h4>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="fw-bold text-muted small">เลขที่</label>
                            <input type="number" name="std_no" class="form-control" value="<?php echo $row['std_no']; ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="fw-bold text-muted small">รหัสนักเรียน</label>
                            <input type="text" name="std_code" class="form-control" value="<?php echo $row['std_code']; ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="fw-bold text-muted small">คำนำหน้า</label>
                             <input type="text" name="title" class="form-control" value="<?php echo $row['title']; ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="fw-bold text-muted small">ชื่อ</label>
                            <input type="text" name="firstname" class="form-control" value="<?php echo $row['firstname']; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold text-muted small">นามสกุล</label>
                            <input type="text" name="lastname" class="form-control" value="<?php echo $row['lastname']; ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="fw-bold text-muted small">ห้องเรียน</label>
                        <input type="text" name="room" class="form-control" value="<?php echo $row['room']; ?>" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="update_btn" class="btn btn-dark-pink btn-lg shadow">บันทึกการแก้ไข</button>
                        <a href="list_students.php" class="btn btn-light text-muted">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

$msg = ""; $status = "";

if (isset($_POST['import_btn'])) {
    if ($_FILES['file']['name']) {
        $filename = explode(".", $_FILES['file']['name']);
        if (end($filename) == "csv") {
            $handle = fopen($_FILES['file']['tmp_name'], "r");
            $count = 0;
            $row_num = 0; // ตัวนับแถวเพื่อข้ามหัวตาราง

            while ($data = fgetcsv($handle, 1000, ",")) {
                $row_num++;
                if ($row_num == 1) continue; // ข้ามบรรทัดหัวตาราง
                if (count($data) < 6) continue; // ข้ามถ้าคอลัมน์ไม่ครบ 6

                // --- 1. รับและ Sanitize ข้อมูล ---
                $std_no = $conn->real_escape_string($data[0]);    
                $std_code = $conn->real_escape_string($data[1]);  
                $title = $conn->real_escape_string($data[2]);     
                $firstname = $conn->real_escape_string($data[3]); 
                $lastname = $conn->real_escape_string($data[4]);  
                $room = $conn->real_escape_string($data[5]);      

                // ข้ามถ้าเลขที่หรือรหัสว่าง
                if (empty($std_no) || empty($std_code)) continue;

                // --- 2. Insert ข้อมูล ---
                $sql = "INSERT INTO tb_students (std_no, std_code, title, firstname, lastname, room) 
                        VALUES ('$std_no', '$std_code', '$title', '$firstname', '$lastname', '$room')";
                
                if($conn->query($sql)) {
                    $count++;
                }
            }
            
            fclose($handle);
            $msg = "นำเข้าข้อมูลสำเร็จจำนวน $count คน!";
            $status = "success";
            
        } else { $msg = "กรุณาเลือกไฟล์นามสกุล .CSV เท่านั้น"; $status = "danger"; }
    } else { $msg = "กรุณาเลือกไฟล์ก่อนกดปุ่ม"; $status = "warning"; }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>นำเข้าข้อมูลนักเรียน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6f9; }
        .sidebar-container { display: flex; flex-wrap: nowrap; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 20px; overflow-y: auto; height: 100vh; }
        .text-pink { color: #d63384; } .btn-pink { background-color: #d63384; color: white; border: none; }
        .card-header-dark { background-color: #212529; color: white; border-bottom: 3px solid #d63384; }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area d-flex flex-column justify-content-center align-items-center">
            
            <div class="card shadow border-0" style="max-width: 650px;">
                <div class="card-header card-header-dark p-3">
                    <h4 class="mb-0"><i class="fa-solid fa-cloud-arrow-up text-pink"></i> นำเข้าข้อมูลนักเรียนจาก Excel (CSV)</h4>
                </div>
                <div class="card-body p-4">
                    
                    <?php if ($msg != ""): ?>
                        <div class="alert alert-<?php echo $status; ?> text-center shadow-sm mb-4">
                            <?php echo $msg; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info small border-info border-opacity-50 mb-4">
                        <h6 class="fw-bold text-dark"><i class="fa-solid fa-list-check me-2"></i> รูปแบบไฟล์ที่ถูกต้อง (6 คอลัมน์)</h6>
                        <table class="table table-sm table-bordered mt-2 small bg-white">
                            <thead><tr><th>#</th><th>คอลัมน์</th><th>ตัวอย่าง</th></tr></thead>
                            <tbody>
                                <tr><td>1</td><td>เลขที่</td><td>1, 2, 3</td></tr>
                                <tr><td>2</td><td>รหัสนักเรียน</td><td>40501</td></tr>
                                <tr><td>3</td><td>คำนำหน้า</td><td>นาย, ด.ช.</td></tr>
                                <tr><td>4</td><td>ชื่อ</td><td>สมชาย</td></tr>
                                <tr><td>5</td><td>นามสกุล</td><td>ใจดี</td></tr>
                                <tr><td>6</td><td>ห้องเรียน</td><td>ม.6/1</td></tr>
                            </tbody>
                        </table>
                        <p class="mt-2 text-danger small">⚠️ ต้องบันทึกไฟล์เป็น **CSV UTF-8** เท่านั้น</p>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="mt-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">เลือกไฟล์ CSV:</label>
                            <input type="file" name="file" class="form-control form-control-lg" required accept=".csv">
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="import_btn" class="btn btn-pink btn-lg shadow fw-bold">
                                <i class="fa-solid fa-cloud-arrow-up"></i> เริ่มนำเข้าข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
    </div>
</body>
</html>
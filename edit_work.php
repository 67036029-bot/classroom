<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

// 1. Get Data
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM tb_work WHERE work_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// 2. Prepare Room Options
$sql_rooms = "SELECT DISTINCT room FROM tb_students ORDER BY room ASC";
$result_rooms = $conn->query($sql_rooms);
$grades = []; $rooms_all = [];
while($r = $result_rooms->fetch_assoc()) {
    $full_room = $r['room']; $rooms_all[] = $full_room;
    $parts = explode('/', $full_room); $g = $parts[0]; 
    if (!in_array($g, $grades)) $grades[] = $g;
}

// 3. Update Logic
if (isset($_POST['update_work'])) {
    $id = $_POST['id'];
    $work_name = $_POST['work_name'];
    $full_score = $_POST['full_score'];
    $work_type = $_POST['work_type'];
    $target_room = $_POST['target_room'];

    $stmt = $conn->prepare("UPDATE tb_work SET work_name=?, full_score=?, work_type=?, target_room=? WHERE work_id=?");
    $stmt->bind_param("sisii", $work_name, $full_score, $work_type, $target_room, $id);
    
    if ($stmt->execute()) {
        echo "<script>alert('แก้ไขงานสำเร็จ!'); window.location='manage_work.php';</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขงาน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 25px; }
        
        .card-edit { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .card-header-edit { background: #ffc107; color: #000; padding: 15px 20px; border-bottom: 3px solid #e0a800; border-radius: 15px 15px 0 0; }
        .btn-save { background: #212529; color: #ffc107; border: none; font-weight: bold; }
        .btn-save:hover { background: #000; color: white; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area d-flex justify-content-center align-items-center">
            <div class="card card-edit w-100" style="max-width: 600px;">
                <div class="card-header-edit">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> แก้ไขรายละเอียดงาน</h5>
                </div>
                <div class="card-body p-4 bg-white">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?php echo $row['work_id']; ?>">

                        <div class="mb-3">
                            <label class="fw-bold small text-muted">ชื่องาน / การสอบ</label>
                            <input type="text" name="work_name" class="form-control fw-bold" value="<?php echo $row['work_name']; ?>" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="fw-bold small text-muted">คะแนนเต็ม</label>
                                <input type="number" name="full_score" class="form-control text-center fw-bold text-danger" value="<?php echo $row['full_score']; ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="fw-bold small text-muted">ประเภทงาน</label>
                                <select name="work_type" class="form-select">
                                    <option value="1" <?php if($row['work_type']==1) echo 'selected'; ?>>1. คะแนนเก็บ 1</option>
                                    <option value="2" <?php if($row['work_type']==2) echo 'selected'; ?>>2. คะแนนเก็บ 2</option>
                                    <option value="3" <?php if($row['work_type']==3) echo 'selected'; ?>>3. สอบกลางภาค</option>
                                    <option value="4" <?php if($row['work_type']==4) echo 'selected'; ?>>4. สอบปลายภาค</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="fw-bold small text-muted">เป้าหมาย (ห้องเรียน)</label>
                            <select name="target_room" class="form-select border-warning fw-bold text-dark">
                                <option value="all" <?php if($row['target_room']=='all') echo 'selected'; ?>>🌐 ทุกห้องเรียน</option>
                                <optgroup label="--- ตามระดับชั้น ---">
                                    <?php foreach($grades as $g): $val = "grade:".$g; ?>
                                        <option value="<?php echo $val; ?>" <?php if($row['target_room']==$val) echo 'selected'; ?>>📚 ระดับชั้น <?php echo $g; ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="--- เลือกรายห้อง ---">
                                    <?php foreach($rooms_all as $rm): ?>
                                        <option value="<?php echo $rm; ?>" <?php if($row['target_room']==$rm) echo 'selected'; ?>>📍 ห้อง <?php echo $rm; ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="update_work" class="btn btn-save py-2 shadow-sm">บันทึกการแก้ไข</button>
                            <a href="manage_work.php" class="btn btn-light text-muted small">ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
// ตรวจสอบสิทธิ์
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

// เตรียมตัวแปร SweetAlert
$sweet_alert = "";

// --- 0. Logic: Toggle Active Status (เปิด/ปิดสอบ) ---
if (isset($_GET['toggle_exam']) && isset($_GET['status'])) {
    $exam_id = intval($_GET['toggle_exam']);
    $new_status = intval($_GET['status']);
    $conn->query("UPDATE tb_exam_sets SET is_active = $new_status WHERE exam_id = $exam_id");
    header("Location: manage_work.php");
    exit();
}

// --- 1. Logic: เพิ่มงาน (Add) ---
if (isset($_POST['save_work'])) {
    // แก้ไข bind_param: s=string, d=double(ทศนิยม), i=integer
    // เรียง: name(s), full_score(d), work_type(i), target_room(s)
    $stmt = $conn->prepare("INSERT INTO tb_work (course_id, work_name, full_score, work_type, target_room) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isdis", $_POST['course_id'], $_POST['work_name'], $_POST['full_score'], $_POST['work_type'], $_POST['target_room']);
    
    if ($stmt->execute()) {
        $sweet_alert = "Swal.fire({
            icon: 'success',
            title: 'เพิ่มงานสำเร็จ!',
            text: 'บันทึกข้อมูลเรียบร้อยแล้ว',
            confirmButtonColor: '#ff007f',
            timer: 2000
        }).then(() => { window.location = 'manage_work.php'; });";
    }
    $stmt->close();
}

// --- 2. Logic: แก้ไขงาน (Edit) [จุดที่เคยมีปัญหา] ---
if (isset($_POST['update_work'])) {
    // 🔴 แก้ไขจุดบั๊ก: เปลี่ยน 'sisii' เป็น 'sdisi'
    // s = work_name (ชื่อ)
    // d = full_score (คะแนน - เป็นทศนิยมได้)
    // i = work_type (ประเภท - เป็นเลข)
    // s = target_room (ห้อง - เป็นตัวหนังสือ) **สำคัญ**
    // i = work_id (ไอดี - เป็นเลข)
    
    $stmt = $conn->prepare("UPDATE tb_work SET work_name=?, full_score=?, work_type=?, target_room=? WHERE work_id=?");
    $stmt->bind_param("sdisi", $_POST['work_name'], $_POST['full_score'], $_POST['work_type'], $_POST['target_room'], $_POST['id']);
    
    if ($stmt->execute()) {
        $sweet_alert = "Swal.fire({
            icon: 'success',
            title: 'แก้ไขสำเร็จ!',
            text: 'ข้อมูลได้รับการอัปเดตแล้ว',
            confirmButtonColor: '#ff007f',
            timer: 2000
        }).then(() => { window.location = 'manage_work.php'; });";
    }
    $stmt->close();
}

// --- 3. เตรียมข้อมูล ---
$sql_courses = "SELECT * FROM tb_course_info ORDER BY id ASC";
$res_courses = $conn->query($sql_courses);
$all_courses = [];
while($c = $res_courses->fetch_assoc()) { $all_courses[] = $c; }

$sql_rooms = "SELECT DISTINCT room FROM tb_students ORDER BY room ASC";
$result_rooms = $conn->query($sql_rooms);
$grades = []; $rooms_all = []; 
while($r = $result_rooms->fetch_assoc()) {
    $full_room = $r['room']; $rooms_all[] = $full_room;
    $parts = explode('/', $full_room); $g = $parts[0]; 
    if (!in_array($g, $grades)) $grades[] = $g;
}

$works_by_course = [];
$sql_works = "SELECT w.*, e.exam_id, e.is_active, 
              (SELECT COUNT(*) FROM tb_score s WHERE s.work_id = w.work_id) as submitted_count
              FROM tb_work w 
              LEFT JOIN tb_exam_sets e ON w.work_id = e.work_id 
              ORDER BY w.work_type ASC, w.work_id ASC";
$res_works = $conn->query($sql_works);
while($w = $res_works->fetch_assoc()) {
    $works_by_course[$w['course_id']][] = $w;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการงานและข้อสอบ</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --pink-neon: #ff007f; --dark-text: #2c3e50; }
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 30px; }
        
        /* Modal Style */
        .modal-content { border-radius: 24px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden; }
        .modal-header { background: linear-gradient(135deg, #212529 0%, #343a40 100%); color: white; border-bottom: none; padding: 25px 30px; position: relative; }
        .modal-header::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 4px; background: var(--pink-neon); }
        .modal-title { font-weight: 800; letter-spacing: 0.5px; }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); opacity: 0.8; }
        .modal-body { padding: 30px; background-color: #fff; }
        .form-control, .form-select { border-radius: 12px; border: 1px solid #e0e0e0; padding: 12px 15px; font-weight: 600; transition: all 0.3s; }
        .form-control:focus, .form-select:focus { border-color: var(--pink-neon); box-shadow: 0 0 0 4px rgba(255, 0, 127, 0.1); }
        .btn-neon { background: var(--pink-neon); color: white; border: none; font-weight: bold; box-shadow: 0 4px 15px rgba(255, 0, 127, 0.3); transition: 0.3s; }
        .btn-neon:hover { background: #d6006b; transform: translateY(-2px); }
        
        /* List Style */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 20px 30px; border-radius: 16px; box-shadow: 0 2px 15px rgba(0,0,0,0.03); border-left: 6px solid var(--pink-neon); }
        .course-wrapper { margin-bottom: 40px; }
        .course-header { font-weight: 800; font-size: 1.1rem; color: var(--dark-text); margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .badge-course { background: #212529; color: white; padding: 5px 10px; border-radius: 6px; font-size: 0.85rem; }
        .list-container { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); overflow: hidden; border: 1px solid #f0f0f0; }
        .work-item { padding: 20px 25px; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .work-item:last-child { border-bottom: none; }
        .work-item:hover { background-color: #fafafa; }
        .item-left { display: flex; align-items: center; gap: 20px; flex-grow: 1; }
        .icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: white; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .icon-exam { background: linear-gradient(135deg, #ff007f, #d6006b); }
        .icon-work { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .info-box h5 { margin: 0; font-weight: 700; color: var(--dark-text); font-size: 1rem; }
        .meta-tags { display: flex; gap: 10px; margin-top: 5px; align-items: center; }
        .badge-type { font-size: 0.75rem; padding: 4px 8px; border-radius: 4px; background: #e9ecef; color: #495057; font-weight: 600; }
        .text-score { font-size: 0.8rem; color: #6c757d; font-weight: 600; }
        .item-right { display: flex; align-items: center; gap: 30px; }
        .stat-group { text-align: center; min-width: 80px; }
        .stat-val { font-weight: 800; font-size: 1.1rem; color: var(--dark-text); display: block; line-height: 1; }
        .stat-label { font-size: 0.7rem; color: #adb5bd; font-weight: 600; text-transform: uppercase; }
        .target-badge { background: #fff3cd; color: #856404; padding: 5px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: bold; border: 1px solid #ffeeba; }
        .form-check-input { cursor: pointer; width: 3em; height: 1.5em; }
        .form-check-input:checked { background-color: #198754; border-color: #198754; }
        .action-group { display: flex; gap: 8px; }
        .btn-icon { width: 36px; height: 36px; border-radius: 8px; border: 1px solid #eee; background: white; color: #6c757d; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .btn-icon:hover { background: #f8f9fa; color: var(--dark-text); border-color: #ccc; }
        .btn-icon.delete:hover { background: #fee2e2; color: #dc3545; border-color: #fecaca; }
        .btn-exam { background: var(--pink-neon); color: white; border: none; padding: 6px 15px; border-radius: 50px; font-size: 0.85rem; font-weight: bold; box-shadow: 0 4px 10px rgba(255, 0, 127, 0.3); display: flex; align-items: center; gap: 5px; text-decoration: none; transition: 0.2s; }
        .btn-exam:hover { background: #d6006b; color: white; transform: translateY(-2px); }
        .btn-create-exam { background: white; color: var(--pink-neon); border: 1px dashed var(--pink-neon); padding: 6px 15px; border-radius: 50px; font-size: 0.85rem; font-weight: bold; text-decoration: none; }
        .btn-create-exam:hover { background: #fff0f6; }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            <div class="page-header">
                <div>
                    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-layer-group text-danger me-2"></i> จัดการงาน & ข้อสอบ</h3>
                    <small class="text-muted">ระบบจัดการแบบ Smart List</small>
                </div>
                <button class="btn btn-neon rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#addWorkModal">
                    <i class="fa-solid fa-plus me-1"></i> เพิ่มงานใหม่
                </button>
            </div>

            <?php foreach($all_courses as $course): 
                $cid = $course['id'];
                $works = isset($works_by_course[$cid]) ? $works_by_course[$cid] : [];
            ?>
            <div class="course-wrapper">
                <div class="course-header">
                    <span class="badge-course"><?php echo $course['subject_code']; ?></span>
                    <?php echo $course['subject_name']; ?>
                    <small class="text-muted fw-normal ms-2">(<?php echo $course['level_class']; ?>)</small>
                </div>

                <div class="list-container">
                    <?php if(empty($works)): ?>
                        <div class="text-center py-5 text-muted opacity-50"><i class="fa-regular fa-folder-open fs-2 mb-2"></i><br>ยังไม่มีงานในรายวิชานี้</div>
                    <?php else: ?>
                        <?php foreach($works as $row): 
                            $work_type_int = (int)$row['work_type'];
                            $is_exam_type = ($work_type_int >= 3);
                            $icon_class = $is_exam_type ? 'icon-exam' : 'icon-work';
                            $icon_fa = $is_exam_type ? 'fa-file-signature' : 'fa-book-open';
                            $type_text = match($work_type_int) { 1 => 'คะแนนเก็บ 1', 2 => 'คะแนนเก็บ 2', 3 => 'สอบกลางภาค', 4 => 'สอบปลายภาค', default => 'ทั่วไป' };
                            $has_exam_set = !empty($row['exam_id']);
                            $is_active = ($has_exam_set && $row['is_active'] == 1);
                            $target_display = ($row['target_room'] == 'all') ? 'ทุกห้องเรียน' : str_replace('grade:', 'ระดับ ', $row['target_room']);
                            $json_work = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="work-item">
                            <div class="item-left">
                                <div class="icon-box <?php echo $icon_class; ?>"><i class="fa-solid <?php echo $icon_fa; ?>"></i></div>
                                <div class="info-box">
                                    <h5><?php echo $row['work_name']; ?></h5>
                                    <div class="meta-tags">
                                        <span class="badge-type"><?php echo $type_text; ?></span>
                                        <span class="text-score"><i class="fa-solid fa-star text-warning"></i> <?php echo $row['full_score']; ?> คะแนน</span>
                                    </div>
                                </div>
                            </div>
                            <div class="item-right">
                                <span class="target-badge"><i class="fa-solid fa-users-viewfinder"></i> <?php echo $target_display; ?></span>
                                <div class="stat-group"><span class="stat-val"><?php echo $row['submitted_count']; ?></span><span class="stat-label">ส่งแล้ว</span></div>
                                <div class="stat-group" style="min-width: 60px;">
                                    <?php if($has_exam_set): ?>
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox" <?php echo $is_active ? 'checked' : ''; ?> onchange="window.location.href='manage_work.php?toggle_exam=<?php echo $row['exam_id']; ?>&status=' + (this.checked ? 1 : 0)">
                                        </div>
                                        <span class="stat-label"><?php echo $is_active ? 'เปิดสอบ' : 'ปิดสอบ'; ?></span>
                                    <?php else: ?><span class="text-muted small">-</span><?php endif; ?>
                                </div>
                                <div class="action-group">
                                    <?php if($has_exam_set): ?>
                                        <a href="exam_create.php?work_id=<?php echo $row['work_id']; ?>" class="btn-exam"><i class="fa-solid fa-gear"></i> จัดการข้อสอบ</a>
                                    <?php else: ?>
                                        <a href="exam_create.php?work_id=<?php echo $row['work_id']; ?>" class="btn-create-exam"><i class="fa-solid fa-plus"></i> สร้างข้อสอบ</a>
                                    <?php endif; ?>
                                    <button class="btn-icon" onclick="openEditWork(<?php echo $json_work; ?>)" title="แก้ไข"><i class="fa-solid fa-pen"></i></button>
                                    <button class="btn-icon delete" onclick="confirmDelete('delete_work.php?id=<?php echo $row['work_id']; ?>')" title="ลบ"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal fade" id="addWorkModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-plus-circle me-2"></i> เพิ่มงานใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="fw-bold small text-muted ms-1">เลือกรายวิชา</label>
                            <select name="course_id" class="form-select">
                                <?php foreach($all_courses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo $c['subject_code']." - ".$c['subject_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3"><label class="fw-bold small text-muted ms-1">ชื่องาน / การสอบ</label><input type="text" name="work_name" class="form-control" required></div>
                        <div class="row mb-3">
                            <div class="col-6"><label class="fw-bold small text-muted ms-1">คะแนนเต็ม</label><input type="number" step="0.01" name="full_score" class="form-control text-center" required></div>
                            <div class="col-6"><label class="fw-bold small text-muted ms-1">ประเภท</label><select name="work_type" class="form-select text-center"><option value="1">เก็บ 1</option><option value="2">เก็บ 2</option><option value="3">กลางภาค</option><option value="4">ปลายภาค</option></select></div>
                        </div>
                        <div class="mb-4">
                            <label class="fw-bold small text-muted ms-1">มอบหมายให้</label>
                            <select name="target_room" class="form-select">
                                <option value="all">🌐 ทุกห้อง</option>
                                <optgroup label="--- ตามระดับ ---"><?php foreach($grades as $g): ?><option value="grade:<?php echo $g; ?>">📚 ระดับ <?php echo $g; ?></option><?php endforeach; ?></optgroup>
                                <optgroup label="--- รายห้อง ---"><?php foreach($rooms_all as $rm): ?><option value="<?php echo $rm; ?>">📍 ห้อง <?php echo $rm; ?></option><?php endforeach; ?></optgroup>
                            </select>
                        </div>
                        <div class="d-grid"><button type="submit" name="save_work" class="btn btn-neon py-3 rounded-pill">บันทึกข้อมูล</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editWorkModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title text-dark"><i class="fa-solid fa-pen-to-square me-2"></i> แก้ไขงาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3"><label class="fw-bold small text-muted ms-1">ชื่องาน</label><input type="text" name="work_name" id="edit_name" class="form-control" required></div>
                        <div class="row mb-3">
                            <div class="col-6"><label class="fw-bold small text-muted ms-1">คะแนนเต็ม</label><input type="number" step="0.01" name="full_score" id="edit_score" class="form-control text-center" required></div>
                            <div class="col-6"><label class="fw-bold small text-muted ms-1">ประเภท</label><select name="work_type" id="edit_type" class="form-select text-center"><option value="1">เก็บ 1</option><option value="2">เก็บ 2</option><option value="3">กลางภาค</option><option value="4">ปลายภาค</option></select></div>
                        </div>
                        <div class="mb-4"><label class="fw-bold small text-muted ms-1">เป้าหมาย</label><select name="target_room" id="edit_room" class="form-select"><option value="all">🌐 ทุกห้อง</option><optgroup label="--- ตามระดับ ---"><?php foreach($grades as $g): ?><option value="grade:<?php echo $g; ?>">📚 ระดับ <?php echo $g; ?></option><?php endforeach; ?></optgroup><optgroup label="--- รายห้อง ---"><?php foreach($rooms_all as $rm): ?><option value="<?php echo $rm; ?>">📍 ห้อง <?php echo $rm; ?></option><?php endforeach; ?></optgroup></select></div>
                        <div class="d-grid"><button type="submit" name="update_work" class="btn btn-dark py-3 rounded-pill">บันทึกการแก้ไข</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function openEditWork(data) {
            document.getElementById('edit_id').value = data.work_id;
            document.getElementById('edit_name').value = data.work_name;
            document.getElementById('edit_score').value = data.full_score;
            document.getElementById('edit_type').value = data.work_type;
            document.getElementById('edit_room').value = data.target_room;
            var myModal = new bootstrap.Modal(document.getElementById('editWorkModal'));
            myModal.show();
        }

        function confirmDelete(url) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "ข้อมูลคะแนนทั้งหมดในงานนี้จะหายไปและกู้คืนไม่ได้!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ลบข้อมูล',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) { window.location.href = url; }
            })
        }

        <?php if($sweet_alert != "") { echo $sweet_alert; } ?>
    </script>
</body>
</html>
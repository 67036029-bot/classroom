<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

// ==========================================
// 1. ส่วนจัดการ Logic (PHP)
// ==========================================

// --- Logic: รีเซตค่าพื้นฐานรายวิชา ---
if (isset($_GET['reset_course_id']) && isset($_GET['level'])) {
    $course_id = $_GET['reset_course_id'];
    $level = $_GET['level'];
    
    // (Logic เดิมของคุณ คงไว้)
    $sql_template = "SELECT * FROM tb_grade_config WHERE grade_level = '$level'";
    $res_template = $conn->query($sql_template);
    if ($res_template->num_rows == 0) {
        $level_num = str_replace(['ม.', '.'], '', $level);
        $sql_template = "SELECT * FROM tb_grade_config WHERE grade_level = '$level_num'";
        $res_template = $conn->query($sql_template);
    }

    if ($res_template->num_rows > 0) {
        $tpl = $res_template->fetch_assoc();
        $def_name = $tpl['default_subject_name'];
        $def_code = $tpl['default_subject_code'];
        $stmt = $conn->prepare("UPDATE tb_course_info SET subject_name = ?, subject_code = ? WHERE id = ?");
        $stmt->bind_param("ssi", $def_name, $def_code, $course_id);
        if ($stmt->execute()) {
            header("Location: settings.php?tab=course&msg=reset_success_alert");
            exit();
        }
        $stmt->close();
    } else {
        echo "<script>alert('ไม่พบ Template'); window.location='settings.php?tab=course';</script>";
        exit();
    }
}

// --- AUTO-UPDATE DB ---
$check_col = $conn->query("SHOW COLUMNS FROM tb_course_info LIKE 'teacher_email'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE tb_course_info ADD COLUMN teacher_email VARCHAR(100) DEFAULT '' AFTER teacher_name");
}

// Helper: SweetAlert
function sweetAlert($icon, $title, $text, $redirect_tab = '') {
    $url = 'settings.php' . ($redirect_tab ? "?tab=$redirect_tab" : "");
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '$icon',
                title: '$title',
                text: '$text',
                confirmButtonColor: '#4f46e5'
            }).then(() => { window.location = '$url'; });
        });
    </script>";
}

// ดึงข้อมูล "ล่าสุด"
$info_query = $conn->query("SELECT * FROM tb_course_info ORDER BY id DESC LIMIT 1");
$teacher_info = $info_query->fetch_assoc();
if (!$teacher_info) {
    $teacher_info = ['school_name' => '', 'teacher_name' => '', 'teacher_email' => '', 'teacher_password' => '$2y$10$...'];
}
$current_pass_hash = $teacher_info['teacher_password'];

// --- Actions Handler (POST) ---

// 1. บันทึกโปรไฟล์ (คงเดิม)
if (isset($_POST['save_profile'])) {
    $school = $_POST['teacher_school'];
    $name = $_POST['teacher_name'];
    $email = $_POST['teacher_email'];
    $update_all = isset($_POST['update_all_courses']) ? true : false;

    if ($update_all) {
        $stmt = $conn->prepare("UPDATE tb_course_info SET school_name=?, teacher_name=?, teacher_email=?");
        $stmt->bind_param("sss", $school, $name, $email);
        $stmt->execute();
        header("Location: settings.php?msg=profile_all_updated");
    } else {
        $stmt = $conn->prepare("UPDATE tb_course_info SET school_name=?, teacher_name=?, teacher_email=? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("sss", $school, $name, $email);
        $stmt->execute();
        header("Location: settings.php?msg=profile_saved");
    }
    exit();
}

// 2. เพิ่มวิชาใหม่ (คงเดิม)
if (isset($_POST['add_course'])) {
    $stmt = $conn->prepare("INSERT INTO tb_course_info (school_name, teacher_name, teacher_email, subject_name, subject_code, semester, year, level_class, teacher_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssss", $_POST['school_name'], $_POST['teacher_name'], $_POST['teacher_email'], $_POST['subject_name'], $_POST['subject_code'], $_POST['semester'], $_POST['year'], $_POST['level_class'], $current_pass_hash);
    if($stmt->execute()) header("Location: settings.php?tab=course");
    exit();
}

// 3. อัปเดตรายวิชา (คงเดิม)
if (isset($_POST['update_course'])) {
    $id = $_POST['course_id'];
    $stmt = $conn->prepare("UPDATE tb_course_info SET school_name=?, teacher_name=?, subject_name=?, subject_code=?, semester=?, year=?, level_class=? WHERE id=?");
    $stmt->bind_param("sssssssi", $_POST['school_name'], $_POST['teacher_name'], $_POST['subject_name'], $_POST['subject_code'], $_POST['semester'], $_POST['year'], $_POST['level_class'], $id);
    if($stmt->execute()) header("Location: settings.php?tab=course");
    exit();
}

// 4. ลบวิชา (คงเดิม)
if (isset($_GET['del_id'])) {
    $id = $_GET['del_id'];
    $cnt = $conn->query("SELECT count(*) as c FROM tb_course_info")->fetch_assoc()['c'];
    if($cnt > 1) {
        $conn->query("DELETE FROM tb_course_info WHERE id=$id");
    }
    header("Location: settings.php?tab=course");
    exit();
}

// 5. เปลี่ยนรหัสผ่าน (อัปเกรดความปลอดภัย!)
if (isset($_POST['save_password'])) {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // 5.1 ตรวจสอบรหัสเดิม
    if (password_verify($old_pass, $current_pass_hash)) {
        // 5.2 ตรวจสอบรหัสใหม่ว่าตรงกันไหม
        if ($new_pass === $confirm_pass) {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE tb_course_info SET teacher_password=?");
            $stmt->bind_param("s", $new_hash);
            $stmt->execute();
            header("Location: settings.php?msg=pass_changed");
        } else {
            header("Location: settings.php?msg=pass_mismatch");
        }
    } else {
        header("Location: settings.php?msg=old_pass_wrong");
    }
    exit();
}

// 6. ล้างระบบ (Reset Factory) - คงระบบยืนยันตัวตนไว้
if (isset($_POST['reset_system_btn'])) {
    $input_pass = $_POST['confirm_password_reset'];
    // ตรวจสอบรหัสผ่านก่อนลบ (สำคัญ!)
    if (password_verify($input_pass, $current_pass_hash)) {
        $tables = ['tb_students', 'tb_score', 'tb_work', 'tb_exam_sets', 'tb_exam_questions', 'tb_exam_results', 'tb_exam_answer_log', 'tb_cheat_events', 'tb_eval_results'];
        foreach ($tables as $tb) $conn->query("TRUNCATE TABLE $tb");
        header("Location: settings.php?msg=reset_success");
    } else {
        header("Location: settings.php?msg=wrong_pass_reset");
    }
    exit();
}

// ดึงรายวิชาทั้งหมด
$courses = $conn->query("SELECT * FROM tb_course_info");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตั้งค่าระบบ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 30px; }
        .card-custom { background: white; border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; }
        
        /* Menu Tabs */
        .nav-pills .nav-link { color: #64748b; font-weight: 500; padding: 14px 20px; border-radius: 12px; margin-bottom: 6px; text-align: left; }
        .nav-pills .nav-link:hover { background-color: #f1f5f9; color: #4f46e5; }
        .nav-pills .nav-link.active { background-color: #4f46e5; color: white; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        .nav-pills .nav-link i { width: 25px; text-align: center; margin-right: 10px; }
        
        /* Table Actions */
        .btn-action { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; border: none; transition: 0.2s; }
        .bg-edit { background-color: #eef2ff; color: #4f46e5; }
        .bg-del { background-color: #fef2f2; color: #ef4444; }
        .bg-reset { background-color: #fff3cd; color: #856404; }

        /* 🔥 DANGER ZONE EFFECT 🔥 */
        .danger-zone {
            background-color: #1a0505; /* พื้นหลังมืดอมแดง */
            border: 2px solid #ef4444;
            color: #ffcccc;
            position: relative;
            overflow: hidden;
            animation: pulse-border 2s infinite; /* เอฟเฟกต์กรอบกระพริบ */
        }
        
        .danger-zone h3 {
            color: #ff4d4d;
            text-shadow: 0 0 10px rgba(255, 77, 77, 0.7);
        }

        .danger-icon {
            animation: shake 0.5s infinite alternate; /* ไอคอนสั่น */
            color: #ff0000;
            text-shadow: 0 0 20px #ff0000;
        }

        @keyframes pulse-border {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); border-color: #ef4444; }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); border-color: #b91c1c; }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); border-color: #ef4444; }
        }

        @keyframes shake {
            from { transform: rotate(-5deg); }
            to { transform: rotate(5deg); }
        }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            <?php 
            // Alerts Management
            if(isset($_GET['msg'])) {
                if($_GET['msg']=='profile_saved') sweetAlert('success', 'บันทึก Default', 'ข้อมูลตั้งต้นถูกบันทึกแล้ว');
                if($_GET['msg']=='profile_all_updated') sweetAlert('success', 'อัปเดตทั้งหมด', 'ข้อมูลครูในทุกรายวิชาถูกแก้ไขเรียบร้อย');
                
                // Password Alerts
                if($_GET['msg']=='pass_changed') sweetAlert('success', 'สำเร็จ', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
                if($_GET['msg']=='old_pass_wrong') sweetAlert('error', 'ผิดพลาด', 'รหัสผ่านเดิมไม่ถูกต้อง');
                if($_GET['msg']=='pass_mismatch') sweetAlert('warning', 'ไม่ตรงกัน', 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน');
                
                // Reset Alerts
                if($_GET['msg']=='reset_success') sweetAlert('success', 'ล้างระบบสำเร็จ', 'ข้อมูลทั้งหมดถูกลบเรียบร้อยแล้ว');
                if($_GET['msg']=='wrong_pass_reset') sweetAlert('error', 'ปฏิเสธ', 'รหัสผ่านยืนยันไม่ถูกต้อง ไม่มีการลบข้อมูล');
                if($_GET['msg']=='reset_success_alert') sweetAlert('success', 'รีเซตวิชาสำเร็จ', 'คืนค่าชื่อวิชาและรหัสวิชาเป็นค่าเริ่มต้นแล้ว', 'course');
            }
            ?>

            <h3 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-sliders text-primary me-2"></i>ตั้งค่าระบบ</h3>

            <div class="row g-4">
                <div class="col-lg-3">
                    <div class="card-custom p-3">
                        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                            <button class="nav-link active" id="menu1-tab" data-bs-toggle="pill" data-bs-target="#menu1"><i class="fa-solid fa-id-card"></i> ข้อมูลครู</button>
                            <button class="nav-link" id="menu2-tab" data-bs-toggle="pill" data-bs-target="#menu2"><i class="fa-solid fa-layer-group"></i> จัดการรายวิชา</button>
                            <button class="nav-link" id="menu3-tab" data-bs-toggle="pill" data-bs-target="#menu3"><i class="fa-solid fa-key"></i> เปลี่ยนรหัสผ่าน</button>
                            <div class="border-top my-2"></div>
                            <button class="nav-link text-danger fw-bold" id="menu4-tab" data-bs-toggle="pill" data-bs-target="#menu4"><i class="fa-solid fa-triangle-exclamation"></i> ล้างระบบ (Danger)</button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-9">
                    <div class="tab-content" id="v-pills-tabContent">
                        
                        <div class="tab-pane fade show active" id="menu1">
                            <div class="card-custom p-4">
                                <h5 class="fw-bold mb-3">ข้อมูลตั้งต้นของครู</h5>
                                <form method="POST">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">ชื่อ-นามสกุล</label>
                                            <input type="text" name="teacher_name" class="form-control" value="<?php echo htmlspecialchars($teacher_info['teacher_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">โรงเรียน</label>
                                            <input type="text" name="teacher_school" class="form-control" value="<?php echo htmlspecialchars($teacher_info['school_name']); ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">อีเมลติดต่อ</label>
                                            <input type="email" name="teacher_email" class="form-control" value="<?php echo htmlspecialchars($teacher_info['teacher_email']); ?>">
                                        </div>
                                        <div class="col-12 mt-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="update_all_courses" id="flexSwitchCheckDefault">
                                                <label class="form-check-label small fw-bold text-primary" for="flexSwitchCheckDefault">อัปเดตข้อมูลนี้ไปยังรายวิชาที่มีอยู่ทั้งหมดด้วย</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-4 text-end">
                                        <button type="submit" name="save_profile" class="btn btn-primary px-4 fw-bold shadow-sm">บันทึกข้อมูล</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="menu2">
                            <div class="card-custom p-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="fw-bold m-0">รายวิชาที่สอน</h5>
                                    <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="openCourseModal()"><i class="fa-solid fa-plus me-1"></i> เพิ่มวิชา</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="p-3 border-0 rounded-start">รหัสวิชา</th>
                                                <th class="border-0">ชื่อวิชา / ครูผู้สอน</th>
                                                <th class="border-0">ระดับชั้น</th>
                                                <th class="p-3 border-0 rounded-end text-center">จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($courses as $c): ?>
                                            <tr>
                                                <td class="p-3"><span class="badge bg-primary bg-opacity-10 text-primary"><?php echo $c['subject_code']; ?></span></td>
                                                <td><div class="fw-bold"><?php echo $c['subject_name']; ?></div><div class="small text-muted"><?php echo $c['teacher_name']; ?></div></td>
                                                <td><?php echo $c['level_class']; ?></td>
                                                <td class="text-center">
                                                    <button class="btn-action bg-edit me-1" onclick='openCourseModal(<?php echo json_encode($c); ?>)'><i class="fa-solid fa-pen"></i></button>
                                                    <a href="settings.php?reset_course_id=<?php echo $c['id']; ?>&level=<?php echo $c['level_class']; ?>" class="btn-action bg-reset me-1" onclick="return confirm('คืนค่าชื่อวิชาเริ่มต้น?');"><i class="fa-solid fa-rotate-left"></i></a>
                                                    <a href="settings.php?del_id=<?php echo $c['id']; ?>" class="btn-action bg-del" onclick="return confirm('ยืนยันลบ?');"><i class="fa-solid fa-trash"></i></a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="menu3">
                            <div class="card-custom p-5 text-center">
                                <div class="mb-4">
                                    <span class="bg-dark text-white rounded-circle d-inline-flex align-items-center justify-content-center shadow" style="width: 70px; height: 70px;">
                                        <i class="fa-solid fa-shield-halved fs-2"></i>
                                    </span>
                                </div>
                                <h5 class="fw-bold">เปลี่ยนรหัสผ่าน Admin</h5>
                                <p class="text-muted small mb-4 mx-auto" style="max-width: 400px;">
                                    เพื่อความปลอดภัย กรุณายืนยันรหัสผ่านเดิมก่อนทำการเปลี่ยนแปลง
                                </p>
                                
                                <div class="row justify-content-center">
                                    <div class="col-md-6 text-start">
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold">รหัสผ่านปัจจุบัน</label>
                                                <input type="password" name="old_password" class="form-control" required placeholder="Old Password">
                                            </div>
                                            <hr>
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold text-primary">รหัสผ่านใหม่</label>
                                                <input type="password" name="new_password" class="form-control border-primary" required placeholder="New Password">
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label small fw-bold text-primary">ยืนยันรหัสผ่านใหม่</label>
                                                <input type="password" name="confirm_password" class="form-control border-primary" required placeholder="Confirm New Password">
                                            </div>
                                            <div class="d-grid">
                                                <button type="submit" name="save_password" class="btn btn-dark fw-bold">บันทึกรหัสผ่านใหม่</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="menu4">
                            <div class="card-custom p-5 text-center danger-zone">
                                <div class="text-danger mb-3">
                                    <i class="fa-solid fa-biohazard fa-4x danger-icon"></i>
                                </div>
                                <h3 class="fw-bold">DANGER ZONE: ล้างระบบ</h3>
                                <p class="text-white-50 mx-auto mb-4" style="max-width: 500px;">
                                    คำเตือน: การกระทำนี้จะ <strong class="text-danger bg-white px-1">ลบข้อมูลทั้งหมด</strong> 
                                    (นักเรียน, คะแนน, การบ้าน, ข้อสอบ) อย่างถาวรและกู้คืนไม่ได้
                                </p>
                                <button class="btn btn-danger btn-lg rounded-pill px-5 shadow-lg fw-bold border-2 border-white" data-bs-toggle="modal" data-bs-target="#resetModal">
                                    <i class="fa-solid fa-triangle-exclamation me-2"></i> เริ่มการล้างระบบ
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="courseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="courseModalTitle">เพิ่มวิชาใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="course_id" id="inp_id">
                    <input type="hidden" name="teacher_email" id="inp_email_hidden" value="<?php echo $teacher_info['teacher_email']; ?>">
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6"><label class="form-label">โรงเรียน</label><input type="text" name="school_name" id="inp_school" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label">ครูผู้สอน</label><input type="text" name="teacher_name" id="inp_teacher" class="form-control" required></div>
                        </div>
                        <hr>
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label">ชื่อวิชา</label><input type="text" name="subject_name" id="inp_subj" class="form-control" required></div>
                            <div class="col-md-4"><label class="form-label">รหัสวิชา</label><input type="text" name="subject_code" id="inp_code" class="form-control" required></div>
                            <div class="col-md-4"><label class="form-label">ระดับชั้น</label><input type="text" name="level_class" id="inp_level" class="form-control" placeholder="เช่น ม.5" required></div>
                            <div class="col-md-4"><label class="form-label">ภาคเรียน</label><input type="text" name="semester" id="inp_sem" class="form-control" required></div>
                            <div class="col-md-4"><label class="form-label">ปีการศึกษา</label><input type="text" name="year" id="inp_year" class="form-control" required></div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="submit" name="add_course" id="btn_add" class="btn btn-primary fw-bold">บันทึก</button>
                        <button type="submit" name="update_course" id="btn_update" class="btn btn-warning fw-bold text-dark" style="display:none;">แก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4" style="border: 2px solid red;">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-skull-crossbones me-2"></i> ยืนยันความปลอดภัยสูงสุด</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center bg-dark text-white">
                    <p class="mb-3 text-danger fw-bold fs-5">คุณแน่ใจหรือไม่ที่จะลบข้อมูลทั้งหมด?</p>
                    <p class="small text-muted mb-4">กรุณากรอกรหัสผ่านครูเพื่อยืนยันการทำรายการ</p>
                    <form method="POST">
                        <input type="password" name="confirm_password_reset" class="form-control text-center fw-bold mb-3 border-danger bg-secondary text-white" placeholder="รหัสผ่านครู" required>
                        <button type="submit" name="reset_system_btn" class="btn btn-danger w-100 fw-bold py-2 shadow">
                            <i class="fa-solid fa-bomb me-2"></i> ยืนยันลบข้อมูลเดี๋ยวนี้
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('tab') === 'course'){ new bootstrap.Tab(document.querySelector('#menu2-tab')).show(); }

        function openCourseModal(data = null) {
            const modal = new bootstrap.Modal(document.getElementById('courseModal'));
            if (data) {
                document.getElementById('courseModalTitle').innerText = "แก้ไขวิชา";
                document.getElementById('inp_id').value = data.id;
                document.getElementById('inp_school').value = data.school_name;
                document.getElementById('inp_teacher').value = data.teacher_name;
                document.getElementById('inp_code').value = data.subject_code;
                document.getElementById('inp_subj').value = data.subject_name;
                document.getElementById('inp_level').value = data.level_class;
                document.getElementById('inp_sem').value = data.semester;
                document.getElementById('inp_year').value = data.year;
                document.getElementById('btn_add').style.display = 'none';
                document.getElementById('btn_update').style.display = 'block';
            } else {
                document.getElementById('courseModalTitle').innerText = "เพิ่มวิชาใหม่";
                document.getElementById('inp_id').value = "";
                document.getElementById('inp_school').value = "<?php echo $teacher_info['school_name']; ?>";
                document.getElementById('inp_teacher').value = "<?php echo $teacher_info['teacher_name']; ?>";
                document.getElementById('inp_code').value = "";
                document.getElementById('inp_subj').value = "";
                document.getElementById('inp_level').value = "";
                document.getElementById('inp_sem').value = "";
                document.getElementById('inp_year').value = "";
                document.getElementById('btn_add').style.display = 'block';
                document.getElementById('btn_update').style.display = 'none';
            }
            modal.show();
        }
    </script>
</body>
</html>
<?php
session_start();
// ตรวจสอบสิทธิ์
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

// ตัวแปรสำหรับ SweetAlert และ Preview
$sweet_alert = "";
$show_preview_modal = false;

// --- Logic 1: เพิ่มนักเรียนรายคน ---
if (isset($_POST['save_btn'])) {
    $stmt = $conn->prepare("INSERT INTO tb_students (std_no, std_code, title, firstname, lastname, room, password) VALUES (?, ?, ?, ?, ?, ?, '12345')");
    $stmt->bind_param("isssss", $_POST['std_no'], $_POST['std_code'], $_POST['title'], $_POST['firstname'], $_POST['lastname'], $_POST['room']);
    if ($stmt->execute()) {
        $sweet_alert = "Swal.fire({icon: 'success', title: 'เพิ่มสำเร็จ', text: 'เพิ่มนักเรียนใหม่เรียบร้อย (รหัสผ่าน: 12345)', confirmButtonColor: '#d63384'});";
    }
    $stmt->close();
}

// --- Logic 2: แก้ไขข้อมูล ---
if (isset($_POST['update_btn'])) {
    $stmt = $conn->prepare("UPDATE tb_students SET std_no=?, std_code=?, title=?, firstname=?, lastname=?, room=? WHERE id=?");
    $stmt->bind_param("isssssi", $_POST['std_no'], $_POST['std_code'], $_POST['title'], $_POST['firstname'], $_POST['lastname'], $_POST['room'], $_POST['id']);
    if ($stmt->execute()) {
        $sweet_alert = "Swal.fire({icon: 'success', title: 'แก้ไขเรียบร้อย', showConfirmButton: false, timer: 1500});";
    }
    $stmt->close();
}

// --- Logic 3.1: อัปโหลดเพื่อ Preview (ยังไม่บันทึก) ---
if (isset($_POST['upload_preview_btn'])) {
    if ($_FILES['file']['name']) {
        $filename = explode(".", $_FILES['file']['name']);
        if (end($filename) == 'csv') {
            $handle = fopen($_FILES['file']['tmp_name'], "r");
            $preview_data = [];
            
            // อ่านไฟล์ CSV
            while ($data = fgetcsv($handle, 1000, ",")) {
                // รูปแบบไฟล์: เลขที่, รหัส, คำนำหน้า, ชื่อจริง, นามสกุล, ห้องเรียน
                // (ถ้ามี Header ให้ข้ามบรรทัดแรก โดยเช็คว่า $data[0] เป็นตัวเลขไหม หรือใช้ตัวนับ)
                if(!is_numeric($data[0])) continue; // ข้ามบรรทัดหัวตาราง (ถ้าช่องเลขที่ไม่ใช่ตัวเลข)

                $preview_data[] = [
                    'std_no'    => intval($data[0]),
                    'std_code'  => trim($data[1]),
                    'title'     => trim($data[2]),
                    'firstname' => trim($data[3]),
                    'lastname'  => trim($data[4]),
                    'room'      => trim($data[5])
                ];
            }
            fclose($handle);
            
            // เก็บใส่ Session ไว้รอการยืนยัน
            $_SESSION['csv_import_cache'] = $preview_data;
            $show_preview_modal = true; // สั่งให้ Modal เด้งขึ้นมา
        } else {
            $sweet_alert = "Swal.fire({icon: 'error', title: 'ไฟล์ไม่ถูกต้อง', text: 'กรุณาอัปโหลดไฟล์ .csv เท่านั้น'});";
        }
    }
}

// --- Logic 3.2: ยืนยันการบันทึก (Confirm Import) ---
if (isset($_POST['confirm_save_import']) && isset($_SESSION['csv_import_cache'])) {
    $count = 0;
    $default_pass = '12345';
    
    $stmt = $conn->prepare("INSERT INTO tb_students (std_no, std_code, title, firstname, lastname, room, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($_SESSION['csv_import_cache'] as $row) {
        // ป้องกันข้อมูลซ้ำ อาจจะเช็คก่อน Insert แต่ในที่นี้เน้นความเร็ว Insert เลย
        $stmt->bind_param("issssss", $row['std_no'], $row['std_code'], $row['title'], $row['firstname'], $row['lastname'], $row['room'], $default_pass);
        if($stmt->execute()) {
            $count++;
        }
    }
    $stmt->close();
    
    // เคลียร์ Session
    unset($_SESSION['csv_import_cache']);
    
    // แจ้งเตือนความสำเร็จ
    $sweet_alert = "Swal.fire({
        icon: 'success',
        title: 'นำเข้าสำเร็จ!',
        html: 'บันทึกข้อมูลนักเรียนจำนวน <b>$count</b> คนเรียบร้อยแล้ว<br>รหัสผ่านเริ่มต้นคือ 12345',
        confirmButtonColor: '#198754'
    }).then(() => { window.location = 'list_students.php'; });";
}

// --- Logic 4: ลบข้อมูล ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM tb_students WHERE id = $del_id");
    echo "<script>window.location='list_students.php';</script>";
}

// --- Filter Room ---
$sql_rooms = "SELECT DISTINCT room FROM tb_students ORDER BY room ASC";
$result_rooms = $conn->query($sql_rooms);
$selected_room = isset($_GET['room']) ? $_GET['room'] : "";
$search_query = isset($_GET['search']) ? $_GET['search'] : "";

// Query ข้อมูล
$where_sql = "1=1";
if ($selected_room != "") $where_sql .= " AND room = '$selected_room'";
if ($search_query != "") $where_sql .= " AND (firstname LIKE '%$search_query%' OR lastname LIKE '%$search_query%' OR std_code LIKE '%$search_query%')";

$sql = "SELECT * FROM tb_students WHERE $where_sql ORDER BY room ASC, std_no ASC";
$result = $conn->query($sql);
$total_std = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายชื่อนักเรียน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --pink-brand: #d63384; --dark-head: #212529; --hover-bg: #fff0f6; }
        body { font-family: 'Sarabun', sans-serif; background-color: #f2f4f6; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 20px; height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        
        /* Header Toolbar */
        .page-header {
            background: white; border-radius: 12px; padding: 12px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 15px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .header-title { font-size: 1.1rem; font-weight: 700; color: #333; display: flex; align-items: center; gap: 10px; }
        .header-stats { font-size: 0.85rem; color: #6c757d; font-weight: 500; background: #f8f9fa; padding: 5px 12px; border-radius: 20px; }
        
        /* Table Styles */
        .card-table { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: none; overflow: hidden; display: flex; flex-direction: column; flex-grow: 1; }
        .table-scroll { flex-grow: 1; overflow-y: auto; position: relative; }
        .table-modern { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-modern thead th { position: sticky; top: 0; z-index: 10; background-color: var(--dark-head); color: white; font-weight: 500; font-size: 0.9rem; padding: 12px 10px; text-align: center; border-bottom: 3px solid var(--pink-brand); vertical-align: middle; }
        .table-modern tbody td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; font-size: 0.95rem; color: #495057; }
        .table-modern tbody tr:hover { background-color: var(--hover-bg); }

        /* Buttons */
        .btn-icon { width: 32px; height: 32px; border-radius: 8px; border: none; display: inline-flex; align-items: center; justify-content: center; transition: 0.2s; background: transparent; color: #adb5bd; }
        .btn-icon:hover { background: #e9ecef; color: #495057; }
        .btn-icon.text-danger:hover { background: #ffebeb; color: #dc3545; }
        .btn-icon.reset { color: #ffc107; } .btn-icon.reset:hover { background: #fff3cd; color: #856404; }
        .btn-add { background: var(--pink-brand); color: white; border-radius: 50px; padding: 5px 20px; font-weight: bold; border:none; }
        .btn-import { background: #198754; color: white; border-radius: 50px; padding: 5px 20px; font-weight: bold; border:none; }
        .form-select-room { border-radius: 50px; background-color: #f8f9fa; border: 1px solid #dee2e6; width: 150px;}
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            <div class="page-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="header-title"><i class="fa-solid fa-user-group text-secondary"></i> รายชื่อนักเรียน</div>
                    <div class="header-stats"><i class="fa-solid fa-users"></i> <?php echo number_format($total_std); ?> คน</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <form method="GET" class="d-flex align-items-center gap-2 m-0">
                        <select name="room" class="form-select form-select-room" onchange="this.form.submit()">
                            <option value="">-- ทุกห้อง --</option>
                            <?php 
                            $result_rooms->data_seek(0);
                            while($r = $result_rooms->fetch_assoc()) {
                                $sel = ($selected_room == $r['room']) ? "selected" : "";
                                echo "<option value='{$r['room']}' $sel>{$r['room']}</option>";
                            }
                            ?>
                        </select>
                        <input type="text" name="search" class="form-control rounded-pill" placeholder="ค้นหา..." value="<?php echo htmlspecialchars($search_query); ?>" style="width: 150px;">
                    </form>
                    <button class="btn btn-import shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fa-solid fa-file-csv me-1"></i> นำเข้า</button>
                    <button class="btn btn-add shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fa-solid fa-plus"></i> เพิ่ม</button>
                </div>
            </div>

            <div class="card-table">
                <div class="table-scroll">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th width="60" class="text-center"></th>
                                <th width="60" class="text-center">เลขที่</th>
                                <th width="100" class="text-center">รหัส</th>
                                <th class="ps-3">ชื่อ - นามสกุล</th>
                                <th width="100" class="text-center">ห้องเรียน</th>
                                <th width="140" class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): 
                                    $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                    $title_chk = trim($row['title']);
                                    $icon_cls = ($title_chk == 'นาย' || $title_chk == 'ด.ช.') ? "fa-user-tie" : "fa-user";
                                ?>
                                <tr>
                                    <td class="text-center"><div style="width:36px;height:36px;border-radius:50%;background:#f1f3f5;color:#adb5bd;display:flex;align-items:center;justify-content:center;margin:auto;"><i class="fa-solid <?php echo $icon_cls; ?>"></i></div></td>
                                    <td class="text-center fw-bold"><?php echo $row['std_no']; ?></td>
                                    <td class="text-center text-muted" style="font-family:monospace;"><?php echo $row['std_code']; ?></td>
                                    <td class="ps-3 text-truncate" style="max-width: 250px;">
                                        <span class="text-secondary small me-1"><?php echo $row['title']; ?></span>
                                        <span class="fw-bold text-dark"><?php echo $row['firstname'] . ' ' . $row['lastname']; ?></span>
                                    </td>
                                    <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $row['room']; ?></span></td>
                                    <td class="text-center">
                                        <button class="btn-icon reset" onclick="resetPassword(<?php echo $row['id']; ?>, '<?php echo $row['firstname']; ?>')" title="รีเซ็ตรหัส"><i class="fa-solid fa-key"></i></button>
                                        <button class="btn-icon text-primary" onclick="openEditModal(<?php echo $json_data; ?>)" title="แก้ไข"><i class="fa-solid fa-pen"></i></button>
                                        <a href="list_students.php?delete_id=<?php echo $row['id']; ?>" class="btn-icon text-danger" onclick="return confirm('ยืนยันลบข้อมูล?');" title="ลบ"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted opacity-50"><i class="fa-solid fa-user-slash display-4 mb-3"></i><br>ไม่พบข้อมูลนักเรียน</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fa-solid fa-file-csv me-2"></i> นำเข้าไฟล์ CSV</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="form-label text-muted">เลือกไฟล์ CSV (UTF-8)</label>
                            <input type="file" name="file" class="form-control" accept=".csv" required>
                        </div>
                        <div class="alert alert-light border small text-start">
                            <strong>เรียงลำดับคอลัมน์ในไฟล์:</strong><br>
                            เลขที่, รหัส, คำนำหน้า, ชื่อจริง, นามสกุล, ห้องเรียน<br>
                            <span class="text-danger">* ระบบจะตั้งรหัสผ่าน "12345" ให้อัตโนมัติ</span>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="upload_preview_btn" class="btn btn-success rounded-pill fw-bold">
                                <i class="fa-solid fa-eye me-2"></i> ตรวจสอบข้อมูล (Preview)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="previewModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-list-check me-2"></i> ตรวจสอบข้อมูลก่อนบันทึก</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-bold">จำนวนรายการ: <?php echo isset($_SESSION['csv_import_cache']) ? count($_SESSION['csv_import_cache']) : 0; ?> คน</span>
                        <small class="text-muted">กรุณาตรวจสอบความถูกต้อง</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0" style="font-size:0.9rem;">
                            <thead class="table-dark">
                                <tr>
                                    <th>ห้อง</th>
                                    <th>เลขที่</th>
                                    <th>รหัส</th>
                                    <th>ชื่อ - นามสกุล</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(isset($_SESSION['csv_import_cache'])): foreach($_SESSION['csv_import_cache'] as $p): ?>
                                <tr>
                                    <td><?php echo $p['room']; ?></td>
                                    <td><?php echo $p['std_no']; ?></td>
                                    <td><?php echo $p['std_code']; ?></td>
                                    <td><?php echo $p['title'].$p['firstname']." ".$p['lastname']; ?></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="list_students.php" class="btn btn-secondary rounded-pill px-4">ยกเลิก</a>
                    <form method="POST">
                        <button type="submit" name="confirm_save_import" class="btn btn-success rounded-pill px-4 fw-bold shadow">
                            <i class="fa-solid fa-save me-1"></i> ยืนยันและบันทึก
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i> เพิ่มนักเรียน</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><select name="title" class="form-select"><option value="นาย">นาย</option><option value="นางสาว">น.ส.</option><option value="ด.ช.">ด.ช.</option><option value="ด.ญ.">ด.ญ.</option></select></div>
                            <div class="col-md-5"><input type="text" name="firstname" class="form-control" placeholder="ชื่อ" required></div>
                            <div class="col-md-4"><input type="text" name="lastname" class="form-control" placeholder="นามสกุล" required></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4"><input type="text" name="room" class="form-control" placeholder="ห้อง (เช่น ม.1/1)" required></div>
                            <div class="col-md-4"><input type="number" name="std_no" class="form-control" placeholder="เลขที่" required></div>
                            <div class="col-md-4"><input type="text" name="std_code" class="form-control" placeholder="รหัสนักเรียน" required></div>
                        </div>
                        <div class="mt-3 text-muted small"><i class="fa-solid fa-info-circle"></i> รหัสผ่านเริ่มต้นจะเป็น <b>12345</b></div>
                        <div class="d-grid mt-4"><button type="submit" name="save_btn" class="btn btn-dark rounded-pill">บันทึก</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen me-2"></i> แก้ไขข้อมูล</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><input type="text" name="title" id="edit_title" class="form-control" required></div>
                            <div class="col-md-5"><input type="text" name="firstname" id="edit_fname" class="form-control" required></div>
                            <div class="col-md-4"><input type="text" name="lastname" id="edit_lname" class="form-control" required></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4"><input type="text" name="room" id="edit_room" class="form-control" required></div>
                            <div class="col-md-4"><input type="number" name="std_no" id="edit_no" class="form-control" required></div>
                            <div class="col-md-4"><input type="text" name="std_code" id="edit_code" class="form-control" required></div>
                        </div>
                        <div class="d-grid mt-4"><button type="submit" name="update_btn" class="btn btn-warning rounded-pill fw-bold">บันทึกแก้ไข</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_title').value = data.title;
            document.getElementById('edit_fname').value = data.firstname;
            document.getElementById('edit_lname').value = data.lastname;
            document.getElementById('edit_room').value = data.room;
            document.getElementById('edit_no').value = data.std_no;
            document.getElementById('edit_code').value = data.std_code;
            var myModal = new bootstrap.Modal(document.getElementById('editModal'));
            myModal.show();
        }

        // Script รีเซ็ตรหัสผ่าน (ยิงไปไฟล์ reset_student_pwd.php)
        function resetPassword(id, name) {
            Swal.fire({
                title: 'รีเซ็ตรหัสผ่าน?',
                text: "ยืนยันที่จะรีเซ็ตรหัสผ่านของ " + name + " กลับเป็น '12345' หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, รีเซ็ตเลย',
                cancelButtonText: 'ยกเลิก',
                customClass: { confirmButton: 'text-dark fw-bold' }
            }).then((result) => {
                if (result.isConfirmed) {
                    let formData = new FormData();
                    formData.append('std_id', id);

                    fetch('reset_student_pwd.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        if(data.trim() === 'success') {
                            Swal.fire('สำเร็จ!', 'รหัสผ่านถูกรีเซ็ตเป็น 12345 แล้ว', 'success');
                        } else {
                            Swal.fire('ผิดพลาด', 'ไม่สามารถรีเซ็ตได้', 'error');
                        }
                    });
                }
            });
        }

        // Logic เปิด Preview Modal อัตโนมัติถ้ามีข้อมูล
        <?php if($show_preview_modal): ?>
            var previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
            previewModal.show();
        <?php endif; ?>

        // แสดง SweetAlert
        <?php if($sweet_alert != "") { echo $sweet_alert; } ?>
    </script>
</body>
</html>
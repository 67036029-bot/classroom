<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { 
    header("Location: login.php"); 
    exit(); 
}
include 'db.php';

// --- 1. เตรียมตัวกรองห้องเรียน ---
$sql_rooms = "SELECT DISTINCT room FROM tb_students ORDER BY room ASC";
$result_rooms = $conn->query($sql_rooms);
$filter_room = isset($_GET['room']) ? trim($_GET['room']) : "";

// ถ้ายังไม่เลือกห้อง ให้เลือกห้องแรกอัตโนมัติ
if ($filter_room == "" && $result_rooms->num_rows > 0) {
    $result_rooms->data_seek(0);
    $filter_room = $result_rooms->fetch_assoc()['room'];
}

// --- 2. ดึงรายชื่อนักเรียน (ใช้ Prepared Statements) ---
if ($filter_room != "") {
    // ✅ FIX #1: ใช้ Prepared Statements เพื่อป้องกัน SQL Injection
    $sql_std = "SELECT * FROM tb_students WHERE room = ? ORDER BY std_no ASC";
    $stmt_std = $conn->prepare($sql_std);
    
    if (!$stmt_std) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt_std->bind_param("s", $filter_room);  // "s" = string type
    $stmt_std->execute();
    $result_std = $stmt_std->get_result();
} else {
    $result_std = false;
}

// --- 3. Stats Queries ---
$total_students_count = $conn->query("SELECT count(*) as c FROM tb_students")->fetch_assoc()['c'];
$result_rooms->data_seek(0);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6f9; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 25px; }
        
        /* --- Compact Hero Banner --- */
        .hero-banner {
            background: linear-gradient(90deg, #212529 0%, #1a1a1a 100%);
            color: white;
            border-radius: 12px;
            padding: 20px 25px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-left: 6px solid #d63384;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .hero-text h3 { font-size: 1.5rem; font-weight: bold; margin: 0; }
        .hero-text small { color: #adb5bd; font-size: 0.85rem; }
        
        .student-count-badge {
            background-color: rgba(214, 51, 132, 0.1);
            color: #ff85c0;
            border: 1px solid #d63384;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* --- Modern Table (Maximized Space) --- */
        .card-table { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); 
            overflow: hidden; 
            height: calc(100vh - 130px);
            display: flex; 
            flex-direction: column;
        }
        
        .card-header-custom {
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-responsive {
            flex-grow: 1;
            overflow-y: auto;
        }

        .table-custom thead th {
            background-color: #212529;
            color: white;
            padding: 12px 15px;
            font-weight: 500;
            border: none;
            position: sticky; 
            top: 0;
            z-index: 10;
        }
        
        .table-custom tbody td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f8f9fa;
            color: #495057;
            font-size: 0.95rem;
        }
        
        /* Hover Effect */
        .table-hover tbody tr:hover { background-color: #fff0f6; cursor: pointer; }
        .table-hover tbody tr:hover td { color: #d63384; font-weight: 600; }
        
        /* Select Box แบบ Compact */
        .form-select-sm-custom {
            border-radius: 20px;
            border: 1px solid #dee2e6;
            padding-left: 15px;
            font-weight: bold;
            color: #333;
            background-color: #f8f9fa;
        }
        .form-select-sm-custom:focus { border-color: #d63384; box-shadow: none; }

    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            
            <div class="hero-banner">
                <div class="hero-text">
                    <h3>
                        ยินดีต้อนรับ, <span style="color: #ff85c0;">
                            <?php 
                            // ✅ FIX #2: ใช้ htmlspecialchars() เพื่อป้องกัน XSS
                            echo isset($TeacherName) ? htmlspecialchars($TeacherName, ENT_QUOTES, 'UTF-8') : 'คุณครู'; 
                            ?>
                        </span>
                    </h3>
                    <small>
                        <i class="fa-solid fa-graduation-cap me-1"></i> 
                        ภาคเรียนที่ <?php echo isset($Semester) ? htmlspecialchars($Semester, ENT_QUOTES, 'UTF-8') : '1'; ?>/<?php echo isset($AcadYear) ? htmlspecialchars($AcadYear, ENT_QUOTES, 'UTF-8') : date('Y'); ?> 
                        | โรงเรียนบ้านสวน (จั่นอนุสรณ์)
                    </small>
                </div>
                
                <div class="student-count-badge">
                    <i class="fa-solid fa-users"></i>
                    <span>นร. ทั้งหมด <?php echo number_format($total_students_count); ?></span>
                </div>
            </div>

            <div class="card card-table bg-white">
                <div class="card-header-custom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-dark text-white rounded-circle d-flex justify-content-center align-items-center" style="width: 35px; height: 35px;">
                            <i class="fa-solid fa-list-ul small"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">สถานะรายห้องเรียน</h6>
                            <div class="small text-muted" style="font-size: 0.75rem;">คะแนนรวมและงานค้าง</div>
                        </div>
                    </div>
                    
                    <form method="GET" class="d-flex align-items-center gap-2">
                        <label class="text-muted small fw-bold text-nowrap">เลือกห้อง:</label>
                        <select name="room" class="form-select form-select-sm form-select-sm-custom" onchange="this.form.submit()">
                            <?php 
                            $result_rooms->data_seek(0);
                            while($r = $result_rooms->fetch_assoc()): 
                                $sel = ($filter_room == $r['room']) ? "selected" : "";
                            ?>
                                <!-- ✅ FIX #3: ใช้ htmlspecialchars() สำหรับค่า room -->
                                <option value="<?php echo htmlspecialchars($r['room'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $sel; ?>>
                                    <?php echo htmlspecialchars($r['room'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </form>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-custom table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="text-center" width="60">#</th>
                                <th>ชื่อ - นามสกุล</th>
                                <th class="text-center" width="100">ห้อง</th>
                                <th class="text-center" width="120">คะแนนรวม</th>
                                <th class="text-center" width="150">สถานะงาน</th>
                                <th class="text-center" width="80">ดู</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result_std && $result_std->num_rows > 0) {
                                while($std = $result_std->fetch_assoc()) {
                                    $std_id = (int)$std['id'];  // ✅ FIX: Validate as integer
                                    $room = $std['room'];
                                    $parts = explode('/', $room);
                                    $grade = $parts[0];

                                    // ✅ FIX #4: คำนวณคะแนน ใช้ Prepared Statements
                                    $sql_sum = "SELECT SUM(score_point) as total FROM tb_score WHERE std_id = ?";
                                    $stmt_sum = $conn->prepare($sql_sum);
                                    if (!$stmt_sum) {
                                        die("Prepare failed: " . $conn->error);
                                    }
                                    $stmt_sum->bind_param("i", $std_id);  // "i" = integer
                                    $stmt_sum->execute();
                                    $row_sum = $stmt_sum->get_result()->fetch_assoc();
                                    $total_score = $row_sum['total'] ?? 0;

                                    // ✅ FIX #5: คำนวณงานค้าง ใช้ Prepared Statements
                                    $sql_all_work = "SELECT work_id FROM tb_work WHERE target_room = 'all' OR target_room = ? OR target_room = ?";
                                    $stmt_work = $conn->prepare($sql_all_work);
                                    if (!$stmt_work) {
                                        die("Prepare failed: " . $conn->error);
                                    }
                                    $grade_target = 'grade:' . $grade;
                                    $stmt_work->bind_param("ss", $grade_target, $room);  // "ss" = 2 strings
                                    $stmt_work->execute();
                                    $res_all_work = $stmt_work->get_result();
                                    $missing_count = 0;

                                    while($w = $res_all_work->fetch_assoc()) {
                                        $wid = (int)$w['work_id'];  // ✅ FIX: Validate as integer
                                        
                                        // ✅ FIX #6: check งาน ใช้ Prepared Statements
                                        $check_sql = "SELECT score_point FROM tb_score WHERE std_id = ? AND work_id = ?";
                                        $check_stmt = $conn->prepare($check_sql);
                                        if (!$check_stmt) {
                                            die("Prepare failed: " . $conn->error);
                                        }
                                        $check_stmt->bind_param("ii", $std_id, $wid);  // "ii" = 2 integers
                                        $check_stmt->execute();
                                        $check = $check_stmt->get_result();
                                        
                                        if ($check->num_rows == 0) { 
                                            $missing_count++; 
                                        } else { 
                                            $s = $check->fetch_assoc(); 
                                            if ($s['score_point'] === null) $missing_count++; 
                                        }
                                    }
                                    ?>
                                    <tr onclick="showStudentDetails(<?php echo $std_id; ?>)">
                                        <td class="text-center text-muted small fw-bold">
                                            <?php echo htmlspecialchars($std['std_no'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td>
                                            <div class="text-dark fw-bold" style="font-size: 0.95rem;">
                                                <?php 
                                                // ✅ FIX #7: Escape ชื่อนักเรียน
                                                echo htmlspecialchars($std['title'] . $std['firstname'], ENT_QUOTES, 'UTF-8'); 
                                                ?> 
                                                <?php echo htmlspecialchars($std['lastname'], ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <div class="small text-muted" style="font-size: 0.7rem;">
                                                <?php echo htmlspecialchars($std['std_code'], ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border fw-normal">
                                                <?php echo htmlspecialchars($std['room'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        
                                        <td class="text-center">
                                            <span class="fw-bold text-dark"><?php echo number_format($total_score, 0); ?></span>
                                        </td>
                                        
                                        <td class="text-center">
                                            <?php if ($missing_count > 0): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill fw-normal px-3">
                                                    ค้าง <?php echo $missing_count; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill fw-normal px-3">
                                                    <i class="fa-solid fa-check me-1"></i> ครบ
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-center">
                                            <i class="fa-solid fa-chevron-right text-muted opacity-50"></i>
                                        </td>
                                    </tr>
                                <?php
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center py-5 text-muted'>ไม่พบรายชื่อนักเรียนในห้อง ";
                                echo htmlspecialchars($filter_room, ENT_QUOTES, 'UTF-8');
                                echo "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="studentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow" style="border-radius: 15px; overflow: hidden;">
                <div class="modal-header border-bottom-0 pb-0" style="background: #212529; color: white;">
                    <h6 class="modal-title fw-bold ps-2 py-2"><i class="fa-solid fa-address-card me-2" style="color: #d63384;"></i> รายละเอียดนักเรียน</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-4" id="modalContent">
                    <div class="text-center py-5"><div class="spinner-border text-pink" role="status"></div></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showStudentDetails(stdId) {
            // ✅ FIX #8: Validate stdId is a number
            if (!Number.isInteger(stdId) || stdId <= 0) {
                alert('Invalid Student ID');
                return;
            }

            var myModal = new bootstrap.Modal(document.getElementById('studentModal'));
            myModal.show();
            var contentDiv = document.getElementById('modalContent');
            contentDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border" style="color: #d63384;"></div><p class="mt-3 text-muted small">กำลังโหลดข้อมูล...</p></div>';

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "get_student_details.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
                    // ✅ FIX #9: Sanitize HTML response (optional: use DOMPurify library for production)
                    contentDiv.innerHTML = this.responseText;
                }
            };
            xhr.send("std_id=" + stdId);
        }
    </script>
</body>
</html>
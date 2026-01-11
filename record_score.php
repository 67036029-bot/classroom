<?php
// record_score.php - Paste from Excel + Smart Compare Import
ini_set('memory_limit', '512M');
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

// =========================================================
// PART 1: API HANDLER (Save Batch)
// =========================================================
if (isset($_GET['action']) && $_GET['action'] == 'save_batch') {
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !is_array($data)) { echo json_encode(['status' => 'error', 'msg' => 'No Data']); exit; }

    $conn->begin_transaction();
    $count = 0;
    try {
        foreach ($data as $item) {
            $std_id = intval($item['std_id']);
            $work_id = intval($item['work_id']);
            $score = trim($item['score']);

            // Validate Score
            $q_max = $conn->query("SELECT full_score FROM tb_work WHERE work_id = $work_id");
            if ($q_max->num_rows == 0) continue;
            $max = floatval($q_max->fetch_assoc()['full_score']);

            if ($score === "") {
                $conn->query("DELETE FROM tb_score WHERE std_id = $std_id AND work_id = $work_id");
                $count++;
            } else {
                $score_val = floatval($score);
                if ($score_val >= 0 && $score_val <= $max) {
                    // Upsert (Insert or Update)
                    $chk = $conn->query("SELECT score_id FROM tb_score WHERE std_id = $std_id AND work_id = $work_id");
                    if ($chk->num_rows > 0) {
                        $stmt = $conn->prepare("UPDATE tb_score SET score_point = ? WHERE std_id = ? AND work_id = ?");
                        $stmt->bind_param("dii", $score_val, $std_id, $work_id);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO tb_score (std_id, work_id, score_point) VALUES (?, ?, ?)");
                        $stmt->bind_param("iid", $std_id, $work_id, $score_val);
                    }
                    $stmt->execute();
                    $count++;
                }
            }
        }
        $conn->commit();
        echo json_encode(['status' => 'success', 'count' => $count]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// =========================================================
// PART 2: IMPORT LOGIC (STEP 1: PREVIEW & COMPARE)
// =========================================================
$show_preview = false;
if (isset($_POST['upload_preview_btn']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $room_filter = $_POST['current_room_hidden']; // รับค่าห้องปัจจุบันเพื่อดึงข้อมูลเดิมมาเทียบ

    if (is_uploaded_file($file)) {
        if (($handle = fopen($file, "r")) !== FALSE) {
            
            // 1. Prepare Data for Comparison (ดึงคะแนนเดิมใน DB มาเก็บไว้ก่อน)
            $db_scores = []; // [std_code][work_id] = current_score
            $sql_old = "SELECT s.std_code, sc.work_id, sc.score_point 
                        FROM tb_students s 
                        LEFT JOIN tb_score sc ON s.id = sc.std_id 
                        WHERE s.room = '$room_filter'";
            $res_old = $conn->query($sql_old);
            while($row = $res_old->fetch_assoc()) {
                if($row['work_id']) $db_scores[$row['std_code']][$row['work_id']] = $row['score_point'];
            }

            // 2. Scan Header
            $work_map = []; // [IndexCSV => WorkID]
            $header_names = [];
            $header_found = false;
            $std_code_idx = -1;

            while (($row = fgetcsv($handle)) !== FALSE) {
                // Remove BOM
                if (isset($row[0]) && substr($row[0], 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) { $row[0] = substr($row[0], 3); }

                // Check for [ID:xxx]
                $has_id_tag = false;
                foreach ($row as $col) { if (strpos($col, '[ID:') !== false) { $has_id_tag = true; break; } }

                if ($has_id_tag) {
                    foreach ($row as $index => $col_name) {
                        // Map Work ID
                        if (preg_match('/\[ID:(\d+)\]/', $col_name, $matches)) { 
                            $work_map[$index] = $matches[1]; 
                            $header_names[$index] = $col_name;
                        }
                        // Map Student Code Column (ค้นหาคอลัมน์รหัส)
                        if (strpos($col_name, 'รหัสนักเรียน') !== false || strpos(strtolower($col_name), 'code') !== false) {
                            $std_code_idx = $index;
                        }
                    }
                    // ถ้าหาคอลัมน์รหัสไม่เจอ ให้เดาว่าเป็น Index 2 (Room, No, Code, Name)
                    if ($std_code_idx == -1) $std_code_idx = 2; 
                    
                    $header_found = true;
                    break; 
                }
            }

            if ($header_found && !empty($work_map)) {
                $preview_data = [];
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $std_code = isset($data[$std_code_idx]) ? trim($data[$std_code_idx]) : "";
                    if ($std_code == "") continue;

                    // Lookup Student Info
                    $q_std = $conn->query("SELECT id, firstname, lastname, room FROM tb_students WHERE std_code = '$std_code'");
                    if ($q_std->num_rows > 0) {
                        $std_row = $q_std->fetch_assoc();
                        $row_changes = [];
                        $has_change = false;

                        foreach ($work_map as $col_idx => $w_id) {
                            $new_val = isset($data[$col_idx]) ? trim($data[$col_idx]) : "";
                            $old_val = isset($db_scores[$std_code][$w_id]) ? $db_scores[$std_code][$w_id] : ""; // Default "" if no score

                            // Logic Comparison (เทียบค่า)
                            // ถ้าใน Excel ว่าง ("") แปลว่าไม่แก้อะไร (Ignore) หรือจะให้ลบ? -> โดยทั่วไป Excel ว่างคือไม่ยุ่ง
                            // แต่ถ้าอยากให้ลบ ต้องกำหนดสัญลักษณ์พิเศษ
                            // ในที่นี้สมมติ: Excel ว่าง = ไม่ทำอะไร
                            
                            if ($new_val !== "") {
                                // แปลงเป็น float เพื่อเทียบค่าตัวเลข (เช่น 10.00 กับ 10)
                                $diff = false;
                                if ($old_val === "") { $diff = true; } // เดิมไม่มี ใหม่มี
                                else { if (floatval($new_val) != floatval($old_val)) $diff = true; } // ค่าไม่เท่ากัน

                                if ($diff) {
                                    $row_changes[] = [
                                        'work_id' => $w_id,
                                        'work_name' => $header_names[$col_idx],
                                        'old' => $old_val,
                                        'new' => $new_val
                                    ];
                                    $has_change = true;
                                }
                            }
                        }
                        
                        if ($has_change) {
                            $preview_data[] = [
                                'std_id' => $std_row['id'],
                                'std_code' => $std_code,
                                'name' => $std_row['firstname'] . " " . $std_row['lastname'],
                                'changes' => $row_changes
                            ];
                        }
                    }
                }
                $_SESSION['score_import_preview'] = $preview_data;
                $show_preview = true;
            } 
            fclose($handle);
        }
    }
}

// =========================================================
// PART 3: CONFIRM IMPORT (Save to DB)
// =========================================================
if (isset($_POST['confirm_import_btn']) && isset($_SESSION['score_import_preview'])) {
    $preview_data = $_SESSION['score_import_preview'];
    $conn->begin_transaction();
    $saved_count = 0;
    try {
        foreach ($preview_data as $p) {
            $std_id = $p['std_id'];
            foreach ($p['changes'] as $chg) {
                $work_id = $chg['work_id'];
                $score = floatval($chg['new']);
                
                // Final Max Check
                $q_max = $conn->query("SELECT full_score FROM tb_work WHERE work_id = $work_id");
                if ($q_max->num_rows > 0) {
                    $max = floatval($q_max->fetch_assoc()['full_score']);
                    if ($score <= $max && $score >= 0) {
                        // Upsert Logic
                        $chk = $conn->query("SELECT score_id FROM tb_score WHERE std_id = $std_id AND work_id = $work_id");
                        if ($chk->num_rows > 0) {
                            $conn->query("UPDATE tb_score SET score_point = '$score' WHERE std_id = $std_id AND work_id = $work_id");
                        } else {
                            $conn->query("INSERT INTO tb_score (std_id, work_id, score_point) VALUES ('$std_id', '$work_id', '$score')");
                        }
                        $saved_count++;
                    }
                }
            }
        }
        $conn->commit();
        unset($_SESSION['score_import_preview']);
        echo "<script>
            setTimeout(function() {
                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกเรียบร้อย!',
                    text: 'อัปเดตข้อมูลจำนวน $saved_count รายการ',
                    confirmButtonColor: '#198754'
                }).then(() => { window.location = 'record_score.php?room={$_GET['room']}'; });
            }, 500);
        </script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}

// =========================================================
// PART 4: DATA FETCHING (ปกติ)
// =========================================================
$sel_room = isset($_GET['room']) ? $_GET['room'] : "";
$sql_rooms = "SELECT DISTINCT room FROM tb_students ORDER BY room ASC";
$res_rooms = $conn->query($sql_rooms);
$works_list = []; $students_list = []; $scores_map = [];

// ดึงระดับชั้นเพื่อใช้ Export
$grades_available = [];
$res_rooms->data_seek(0);
while($r = $res_rooms->fetch_assoc()){
    $g = explode('/', $r['room'])[0]; 
    if(!in_array($g, $grades_available)) $grades_available[] = $g;
}
$res_rooms->data_seek(0); 

if ($sel_room != "") {
    $parts = explode('/', $sel_room);
    $grade_part = isset($parts[0]) ? $parts[0] : ""; 
    $sql_works = "SELECT * FROM tb_work WHERE target_room = 'all' OR target_room = 'grade:$grade_part' OR target_room = '$sel_room' ORDER BY work_type ASC, work_id ASC";
    $res_works = $conn->query($sql_works);
    while($w = $res_works->fetch_assoc()) { $works_list[] = $w; }

    $sql_stds = "SELECT * FROM tb_students WHERE room = '$sel_room' ORDER BY std_no ASC";
    $res_stds = $conn->query($sql_stds);
    while($s = $res_stds->fetch_assoc()) { $students_list[] = $s; }

    if (count($students_list) > 0 && count($works_list) > 0) {
        $std_ids = array_column($students_list, 'id');
        $std_ids_str = implode(',', $std_ids);
        $sql_scores = "SELECT std_id, work_id, score_point FROM tb_score WHERE std_id IN ($std_ids_str)";
        $res_scores = $conn->query($sql_scores);
        while($sc = $res_scores->fetch_assoc()) { $scores_map[$sc['std_id']][$sc['work_id']] = $sc['score_point']; }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกคะแนน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6f9; overflow-x: hidden; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 15px; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        .save-bar { background: white; padding: 10px 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-left: 5px solid #6c757d; transition: all 0.3s; }
        .save-bar.unsaved { border-left-color: #ffc107; background: #fffcf5; }
        
        .table-container { flex-grow: 1; overflow: auto; background: white; border-radius: 8px; border: 1px solid #dee2e6; position: relative; }
        .gradebook-table { border-collapse: separate; border-spacing: 0; min-width: 100%; font-size: 0.85rem; }
        .gradebook-table th { position: sticky; top: 0; z-index: 100; background-color: #212529; color: white; padding: 8px 4px; border-right: 1px solid #444; border-bottom: 3px solid #ff007f; text-align: center; vertical-align: middle; height: 50px; }
        .sticky-col { position: sticky; z-index: 50; background-color: #ffffff; border-right: 1px solid #dee2e6; transition: background 0.2s; }
        .col-no { width: 45px; left: 0; }
        .col-name { width: 150px; left: 45px; box-shadow: 2px 0 5px -2px rgba(0,0,0,0.1); }
        .gradebook-table thead th.sticky-col { z-index: 200; background-color: #212529; color: white; border-right: 1px solid #444; }
        
        .gradebook-table tbody tr:hover td { background-color: #e3f2fd !important; color: #000; }
        .gradebook-table tbody tr:hover .sticky-col { background-color: #e3f2fd !important; border-right: 1px solid #90caf9 !important; }
        .gradebook-table tbody tr:hover .score-input { color: #0d47a1; }

        .score-input { width: 100%; height: 32px; text-align: center; border: 1px solid transparent; background: transparent; font-weight: 600; color: #333; font-size: 0.9rem; cursor: pointer; }
        .score-input:focus { background: #fff; outline: 2px solid #0d6efd; z-index: 10; cursor: text; }
        .score-input.changed { background-color: #d1e7dd !important; color: #0f5132; font-weight: bold; border: 1px solid #198754; } /* ไฮไลท์เขียว */
        
        .col-total { background-color: #f8f9fa; font-weight: 800; color: #333; text-align: center; min-width: 70px; border-left: 2px solid #dee2e6; }
        .form-select-custom { border-radius: 50px; font-weight: bold; height: 38px; }
        .pulse { animation: pulse-animation 1.5s infinite; }
        @keyframes pulse-animation { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }

        /* Compare Modal Style */
        .compare-row { border-bottom: 1px solid #eee; }
        .val-old { text-decoration: line-through; color: #adb5bd; font-size: 0.85rem; margin-right: 5px; }
        .val-new { color: #198754; font-weight: bold; font-size: 1rem; }
        .val-arrow { color: #6c757d; font-size: 0.8rem; margin: 0 5px; }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>
        <div class="content-area">
            
            <div class="save-bar" id="saveBar">
                <div class="d-flex align-items-center gap-3">
                    <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-table-cells text-danger me-2"></i> บันทึกคะแนน</h4>
                    <span id="unsaved-badge" class="badge bg-warning text-dark rounded-pill d-none"><i class="fa-solid fa-pen-nib"></i> ยังไม่บันทึก</span>
                </div>
                <div class="d-flex gap-2">
                    <form method="GET" class="m-0" id="roomForm">
                        <select name="room" class="form-select form-select-custom shadow-sm" onchange="checkUnsavedAndSubmit(this)" style="width: 150px;">
                            <option value="">-- เลือกห้อง --</option>
                            <?php if ($res_rooms) { $res_rooms->data_seek(0); while($r = $res_rooms->fetch_assoc()) { $sel = ($sel_room == $r['room']) ? "selected" : ""; echo "<option value='{$r['room']}' $sel>{$r['room']}</option>"; } } ?>
                        </select>
                    </form>
                    <button id="btnSave" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" onclick="saveAll()" disabled><i class="fa-solid fa-save me-1"></i> บันทึก</button>
                </div>
            </div>

            <?php if ($sel_room != "" && count($students_list) > 0): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex gap-2">
                        <div class="dropdown">
                            <button class="btn btn-outline-success btn-sm rounded-pill px-3 py-1 dropdown-toggle fw-bold" type="button" data-bs-toggle="dropdown"><i class="fa-solid fa-download"></i> ดาวน์โหลดฟอร์ม</button>
                            <ul class="dropdown-menu shadow border-0">
                                <?php foreach($grades_available as $g): ?><li><a class="dropdown-item small" href="export_score.php?grade=<?php echo $g; ?>">ระดับชั้น <?php echo $g; ?></a></li><?php endforeach; ?>
                            </ul>
                        </div>
                        <button class="btn btn-outline-dark btn-sm rounded-pill px-3 py-1 fw-bold" type="button" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fa-solid fa-upload"></i> อัปโหลดคะแนน</button>
                    </div>
                    <div class="small text-muted fw-bold"><i class="fa-regular fa-paste me-1"></i>วาง (Paste) คะแนนจาก Excel ได้เลย</div>
                </div>

                <div class="table-container">
                    <table class="gradebook-table" id="scoreTable">
                        <thead>
                            <tr>
                                <th class="sticky-col col-no">#</th>
                                <th class="sticky-col col-name">ชื่อ</th>
                                <?php $total_full = 0; foreach ($works_list as $w): $total_full += floatval($w['full_score']); ?>
                                    <th class="col-work" title="<?php echo $w['work_name']; ?>">
                                        <div style="font-size:0.8rem; line-height:1.2; height:2.4em; overflow:hidden;"><?php echo $w['work_name']; ?></div>
                                        <span class="badge bg-secondary bg-opacity-25 text-white" style="font-size:0.65rem;"><?php echo floatval($w['full_score']); ?></span>
                                    </th>
                                <?php endforeach; ?>
                                <th class="col-total">รวม<br><small>(<?php echo $total_full; ?>)</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_list as $std): $std_total = 0; ?>
                            <tr>
                                <td class="sticky-col col-no text-muted fw-bold text-center"><?php echo $std['std_no']; ?></td>
                                <td class="sticky-col col-name text-dark fw-bold text-truncate" style="padding-left:10px !important; text-align:left;"><?php echo $std['firstname']; ?></td>
                                <?php foreach ($works_list as $w): $val = $scores_map[$std['id']][$w['work_id']] ?? ""; if($val !== "") $std_total += floatval($val); ?>
                                    <td>
                                        <input type="number" step="0.01" class="score-input"
                                               data-std="<?php echo $std['id']; ?>"
                                               data-work="<?php echo $w['work_id']; ?>"
                                               data-max="<?php echo $w['full_score']; ?>"
                                               data-original="<?php echo $val; ?>"
                                               value="<?php echo $val; ?>" 
                                               placeholder="-" autocomplete="off">
                                    </td>
                                <?php endforeach; ?>
                                <td class="col-total text-pink total-cell"><?php echo $std_total; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center mt-5 text-muted opacity-50"><i class="fa-solid fa-arrow-up-right-dots display-1 mb-3"></i><h4>กรุณาเลือกห้องเรียน</h4></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-csv me-2"></i> อัปโหลดคะแนน (CSV)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="current_room_hidden" value="<?php echo $sel_room; ?>">
                        <div class="mb-3">
                            <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                        </div>
                        <div class="alert alert-light border small text-start">
                            <i class="fa-solid fa-circle-info text-info"></i> ระบบจะค้นหาคอลัมน์ <strong>[ID:xxx]</strong> และเปรียบเทียบคะแนนเดิมให้อัตโนมัติ
                        </div>
                        <button type="submit" name="upload_preview_btn" class="btn btn-primary rounded-pill w-100 fw-bold">ตรวจสอบและเปรียบเทียบ</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if($show_preview && isset($_SESSION['score_import_preview'])): ?>
    <div class="modal fade show" id="previewModal" tabindex="-1" style="display:block; background:rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-code-compare me-2"></i> สรุปรายการเปลี่ยนแปลง</h5>
                    <button type="button" class="btn-close" onclick="closePreview()"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 bg-light d-flex justify-content-between align-items-center">
                        <div>
                            <strong>พบการเปลี่ยนแปลง: <?php echo count($_SESSION['score_import_preview']); ?> คน</strong>
                            <div class="small text-muted">แสดงเฉพาะคะแนนที่มีการอัปเดตใหม่เท่านั้น</div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="font-size:0.9rem;">
                            <thead class="table-dark"><tr><th>รหัส</th><th>ชื่อ</th><th>รายการอัปเดต (เดิม <i class="fa-solid fa-arrow-right small"></i> ใหม่)</th></tr></thead>
                            <tbody>
                                <?php foreach($_SESSION['score_import_preview'] as $p): ?>
                                <tr>
                                    <td><?php echo $p['std_code']; ?></td>
                                    <td class="fw-bold"><?php echo $p['name']; ?></td>
                                    <td>
                                        <?php foreach($p['changes'] as $c): ?>
                                            <div class="compare-row py-1">
                                                <span class="badge bg-secondary bg-opacity-10 text-dark border me-2"><?php echo $c['work_name']; ?></span>
                                                <span class="val-old"><?php echo ($c['old'] === "") ? "-" : $c['old']; ?></span>
                                                <i class="fa-solid fa-arrow-right val-arrow"></i>
                                                <span class="val-new"><?php echo $c['new']; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" onclick="closePreview()">ยกเลิก</button>
                    <form method="POST">
                        <button type="submit" name="confirm_import_btn" class="btn btn-success rounded-pill fw-bold px-4">ยืนยันการบันทึก</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>function closePreview() { document.getElementById('previewModal').style.display='none'; }</script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let isDirty = false;

    // --- 1. Paste from Excel Feature ---
    document.addEventListener('paste', function(e) {
        // เช็คว่ากำลังโฟกัสที่ช่องกรอกคะแนนหรือไม่
        if (!e.target.classList.contains('score-input')) return;
        
        e.preventDefault();
        // เอาข้อมูลจาก Clipboard (รองรับ Excel/Sheets ที่คั่นด้วย Tab)
        const pasteData = (e.clipboardData || window.clipboardData).getData('text');
        const rows = pasteData.trim().split('\n'); // แถว
        
        const startInput = e.target;
        const startTr = startInput.closest('tr');
        const startTd = startInput.closest('td');
        
        // หาตำแหน่งเริ่มต้นในตาราง (Row Index, Cell Index)
        const allRows = Array.from(document.querySelectorAll('#scoreTable tbody tr'));
        const startRowIndex = allRows.indexOf(startTr);
        
        // หาตำแหน่งคอลัมน์ (นับเฉพาะ td ที่มี input)
        const allInputsInRow = Array.from(startTr.querySelectorAll('.score-input'));
        const startColIndex = allInputsInRow.indexOf(startInput);

        rows.forEach((rowStr, rIdx) => {
            const currentRow = allRows[startRowIndex + rIdx];
            if (!currentRow) return; // หมดแถวแล้ว

            const cols = rowStr.split('\t'); // คอลัมน์
            const inputsInCurrentRow = Array.from(currentRow.querySelectorAll('.score-input'));

            cols.forEach((val, cIdx) => {
                const targetInput = inputsInCurrentRow[startColIndex + cIdx];
                if (targetInput) {
                    const cleanVal = val.trim();
                    // อัปเดตค่า (ถ้าไม่ว่าง)
                    if(cleanVal !== "") {
                        targetInput.value = cleanVal;
                        // Trigger Event เพื่อเช็คสีเขียวและบันทึก
                        targetInput.dispatchEvent(new Event('input'));
                    }
                }
            });
        });
    });

    // --- 2. Input Logic (Manual Save & Validation) ---
    document.querySelectorAll('.score-input').forEach(input => {
        input.addEventListener('input', function() {
            const currentVal = this.value.trim();
            const originalVal = this.dataset.original;
            const maxVal = parseFloat(this.dataset.max);

            if (currentVal !== "" && parseFloat(currentVal) > maxVal) {
                Swal.fire({ icon: 'error', title: 'คะแนนเกิน!', text: `เต็ม ${maxVal}`, timer: 1000, showConfirmButton: false });
                this.value = ""; return;
            }

            if (currentVal !== originalVal) { this.classList.add('changed'); setDirty(true); } 
            else { this.classList.remove('changed'); checkIfAnyDirty(); }
            updateRowTotal(this.closest('tr'));
        });

        // Keyboard Navigation
        input.addEventListener('keydown', function(e) {
            const inputs = Array.from(document.querySelectorAll('.score-input'));
            const index = inputs.indexOf(this);
            const colCount = <?php echo count($works_list); ?>; 
            if (e.key === 'Enter') { e.preventDefault(); if (index + colCount < inputs.length) inputs[index + colCount].focus(); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); if (index + colCount < inputs.length) inputs[index + colCount].focus(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); if (index - colCount >= 0) inputs[index - colCount].focus(); }
            else if (e.key === 'ArrowRight') { if (index + 1 < inputs.length) inputs[index + 1].focus(); }
            else if (e.key === 'ArrowLeft') { if (index - 1 >= 0) inputs[index - 1].focus(); }
        });
    });

    function setDirty(status) {
        isDirty = status;
        const btn = document.getElementById('btnSave');
        const badge = document.getElementById('unsaved-badge');
        const bar = document.getElementById('saveBar');
        if (status) { btn.disabled = false; btn.classList.add('pulse'); badge.classList.remove('d-none'); bar.classList.add('unsaved'); } 
        else { btn.disabled = true; btn.classList.remove('pulse'); badge.classList.add('d-none'); bar.classList.remove('unsaved'); }
    }

    function checkIfAnyDirty() { const changed = document.querySelectorAll('.score-input.changed'); setDirty(changed.length > 0); }
    function updateRowTotal(tr) {
        let total = 0; tr.querySelectorAll('.score-input').forEach(inp => { let v = parseFloat(inp.value); if (!isNaN(v)) total += v; });
        tr.querySelector('.total-cell').innerText = parseFloat(total.toFixed(2));
    }

    function saveAll() {
        const changedInputs = document.querySelectorAll('.score-input.changed');
        if (changedInputs.length === 0) return;
        let payload = [];
        changedInputs.forEach(inp => { payload.push({ std_id: inp.dataset.std, work_id: inp.dataset.work, score: inp.value }); });
        const btn = document.getElementById('btnSave');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ...'; btn.disabled = true;
        fetch('record_score.php?action=save_batch', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(res => res.json()).then(data => {
            if (data.status === 'success') {
                changedInputs.forEach(inp => { inp.dataset.original = inp.value; inp.classList.remove('changed'); });
                setDirty(false);
                Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', text: `บันทึก ${data.count} รายการ`, timer: 1500, showConfirmButton: false });
            } else { throw new Error(data.msg); }
        }).catch(err => { Swal.fire({ icon: 'error', title: 'Error', text: err.message }); }).finally(() => { btn.innerHTML = originalText; if (isDirty) btn.disabled = false; });
    }

    window.checkUnsavedAndSubmit = function(select) {
        if (isDirty) {
            Swal.fire({ title: 'ยังไม่บันทึก!', text: "บันทึกก่อนเปลี่ยนห้องไหม?", icon: 'warning', showDenyButton: true, showCancelButton: true, confirmButtonText: 'บันทึก', denyButtonText: 'ไม่' })
            .then((result) => { if (result.isConfirmed) { saveAll(); setTimeout(() => select.form.submit(), 1500); } else if (result.isDenied) { isDirty = false; select.form.submit(); } });
        } else { select.form.submit(); }
    };
    window.addEventListener('beforeunload', function (e) { if (isDirty) { e.preventDefault(); e.returnValue = ''; } });
    </script>
</body>
</html>
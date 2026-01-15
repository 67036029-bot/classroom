<?php
// file: index.php
include 'header.php'; // เรียกใช้ Header
// --- ดึงข้อมูลครูและปีการศึกษาจากฐานข้อมูล ---
// ดึงข้อมูลแถวแรกจากตาราง tb_course_info
$sql_info = "SELECT teacher_name, semester, year FROM tb_course_info LIMIT 1";
$result_info = $conn->query($sql_info);

// ตรวจสอบว่ามีข้อมูลหรือไม่ ถ้ามีให้เก็บลงตัวแปร
if ($result_info && $result_info->num_rows > 0) {
    $info_data = $result_info->fetch_assoc();
    $db_teacher_name = $info_data['teacher_name'];
    $db_semester = $info_data['semester'];
    $db_year = $info_data['year'];
} else {
    // ค่า Default กรณีไม่พบข้อมูลในฐานข้อมูล
    $db_teacher_name = 'คุณครู'; 
    $db_semester = '1';
    $db_year = date('Y');
}


// --- 1. เตรียมตัวกรองห้องเรียน ---
$sql_rooms = "SELECT DISTINCT room FROM tb_students ORDER BY room ASC";
$result_rooms = $conn->query($sql_rooms);
$filter_room = isset($_GET['room']) ? $_GET['room'] : "";

if ($filter_room == "" && $result_rooms->num_rows > 0) {
    $result_rooms->data_seek(0);
    $filter_room = $result_rooms->fetch_assoc()['room'];
}

// --- 2. Stats Queries (จำนวนนักเรียนทั้งหมด) ---
$total_students_count = $conn->query("SELECT count(*) as c FROM tb_students")->fetch_assoc()['c'];
$result_rooms->data_seek(0);

// ==========================================
// 🚀 OPTIMIZED QUERY SECTION (ส่วนที่ปรับปรุงใหม่)
// ==========================================
$students_data = []; // เก็บข้อมูลนักเรียน
if ($filter_room != "") {
    $parts = explode('/', $filter_room);
    $grade_level = $parts[0]; // เช่น ม.6

    // 2.1 ดึง Work ID ทั้งหมดที่ห้องนี้ต้องทำ (Query ครั้งเดียว)
    $relevant_work_ids = [];
    $sql_works = "SELECT work_id FROM tb_work 
                  WHERE target_room = 'all' 
                  OR target_room = 'grade:$grade_level' 
                  OR target_room = '$filter_room'";
    $res_works = $conn->query($sql_works);
    while($w = $res_works->fetch_assoc()) { 
        $relevant_work_ids[] = $w['work_id']; 
    }
    $total_works_count = count($relevant_work_ids);

    // 2.2 ดึงข้อมูลนักเรียน + คะแนนรวม (ใช้ JOIN เพื่อลด Query)
    $sql_std = "SELECT s.*, SUM(sc.score_point) as total_score 
                FROM tb_students s
                LEFT JOIN tb_score sc ON s.id = sc.std_id 
                WHERE s.room = '$filter_room'
                GROUP BY s.id
                ORDER BY s.std_no ASC";
    $result_std = $conn->query($sql_std);

    // 2.3 ดึงข้อมูลการส่งงานของนักเรียนในห้องนี้ (เฉพาะงานที่เกี่ยวข้อง)
    // เพื่อนำมานับว่าใครส่งกี่งานแล้ว (โดยไม่ต้อง Query ในลูป)
    $submitted_counts = [];
    if (!empty($relevant_work_ids)) {
        $work_ids_str = implode(',', $relevant_work_ids);
        // นับเฉพาะงานที่มีคะแนน (ไม่เป็น NULL) และอยู่ในรายชื่องานที่ต้องทำ
        $sql_sub = "SELECT std_id, COUNT(DISTINCT work_id) as cnt 
                    FROM tb_score 
                    WHERE work_id IN ($work_ids_str) 
                    AND score_point IS NOT NULL 
                    AND std_id IN (SELECT id FROM tb_students WHERE room = '$filter_room')
                    GROUP BY std_id";
        $res_sub = $conn->query($sql_sub);
        while($row = $res_sub->fetch_assoc()) {
            $submitted_counts[$row['std_id']] = $row['cnt'];
        }
    }

    // รวมข้อมูล
    while($row = $result_std->fetch_assoc()) {
        $std_id = $row['id'];
        $row['total_score'] = $row['total_score'] ?? 0;
        
        // คำนวณงานค้าง: งานทั้งหมด - งานที่ส่งแล้ว
        $submitted = isset($submitted_counts[$std_id]) ? $submitted_counts[$std_id] : 0;
        $row['missing_count'] = $total_works_count - $submitted;
        // ป้องกันค่าติดลบ (เผื่อมีขยะใน DB)
        if ($row['missing_count'] < 0) $row['missing_count'] = 0;
        
        $students_data[] = $row;
    }
}
// ==========================================
?>

<div class="hero-banner">
    <div class="hero-text">
        <h3>
            ยินดีต้อนรับ, <span style="color: #ff85c0;">
                <?php echo htmlspecialchars($db_teacher_name); ?>
            </span>
        </h3>
        <small>
            <i class="fa-solid fa-graduation-cap me-1"></i> 
            ภาคเรียนที่ <?php echo htmlspecialchars($db_semester); ?>/<?php echo htmlspecialchars($db_year); ?> 
            | โรงเรียนบ้านสวน (จั่นอนุสรณ์)
        </small>
    </div>
    
    <div class="student-count-badge">
        <i class="fa-solid fa-users"></i>
        <span>นร. ทั้งหมด <?php echo isset($total_students_count) ? number_format($total_students_count) : '0'; ?></span>
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
            <button type="button" class="btn btn-danger btn-sm shadow-sm fw-bold rounded-pill px-3 text-nowrap" onclick="openFailingReport()">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> รายงานกลุ่มเสี่ยง
            </button>

            <label class="text-muted small fw-bold text-nowrap">เลือกห้อง:</label>
            <select name="room" class="form-select form-select-sm form-select-sm-custom" onchange="this.form.submit()">
                <?php 
                $result_rooms->data_seek(0);
                while($r = $result_rooms->fetch_assoc()): 
                    $sel = ($filter_room == $r['room']) ? "selected" : "";
                ?>
                    <option value="<?php echo $r['room']; ?>" <?php echo $sel; ?>><?php echo $r['room']; ?></option>
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
                if (!empty($students_data)) {
                    foreach($students_data as $std) {
                        ?>
                        <tr onclick="showStudentDetails(<?php echo $std['id']; ?>)">
                            <td class="text-center text-muted small fw-bold"><?php echo $std['std_no']; ?></td>
                            <td>
                                <div class="text-dark fw-bold" style="font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($std['title'].$std['firstname']." ".$std['lastname']); ?>
                                </div>
                                <div class="small text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($std['std_code']); ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border fw-normal"><?php echo $std['room']; ?></span>
                            </td>
                            
                            <td class="text-center">
                                <span class="fw-bold text-dark"><?php echo number_format($std['total_score'], 0); ?></span>
                            </td>
                            
                            <td class="text-center">
                                <?php if ($std['missing_count'] > 0): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill fw-normal px-3">
                                        ค้าง <?php echo $std['missing_count']; ?>
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
                    echo "<tr><td colspan='6' class='text-center py-5 text-muted'>ไม่พบรายชื่อนักเรียนในห้อง ".htmlspecialchars($filter_room)."</td></tr>";
                }
                ?>
            </tbody>
        </table>
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

<div class="modal fade" id="failingReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> รายงานนักเรียนกลุ่มเสี่ยง (คะแนน < 50)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="d-flex justify-content-between align-items-center mb-3 bg-white p-3 rounded shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <label class="fw-bold text-muted text-nowrap">เลือกห้องเรียน:</label>
                        <select id="failing_room_filter" class="form-select form-select-sm border-danger text-danger fw-bold" style="width: 150px;" onchange="loadFailingStudents()">
                            <option value="all">⚡ ทุกห้อง</option>
                            <?php 
                            $result_rooms->data_seek(0);
                            while($r = $result_rooms->fetch_assoc()): 
                            ?>
                                <option value="<?php echo htmlspecialchars($r['room']); ?>"><?php echo htmlspecialchars($r['room']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <button onclick="printFailingReport()" class="btn btn-dark btn-sm rounded-pill px-3"><i class="fa-solid fa-print me-1"></i> พิมพ์ / บันทึก PDF</button>
                    </div>
                </div>
                <div id="failing_content" class="bg-white rounded p-3 shadow-sm" style="min-height: 200px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
    function showStudentDetails(stdId) {
        var myModal = new bootstrap.Modal(document.getElementById('studentModal'));
        myModal.show();
        var contentDiv = document.getElementById('modalContent');
        contentDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border" style="color: #d63384;"></div><p class="mt-3 text-muted small">กำลังโหลดข้อมูล...</p></div>';

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "get_student_details.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
                contentDiv.innerHTML = this.responseText;
            }
        };
        xhr.send("std_id=" + stdId);
    }

    // Script สำหรับรายงานกลุ่มเสี่ยง
    function openFailingReport() {
        var myModal = new bootstrap.Modal(document.getElementById('failingReportModal'));
        myModal.show();
        const urlParams = new URLSearchParams(window.location.search);
        const currentRoom = urlParams.get('room');
        if(currentRoom) document.getElementById('failing_room_filter').value = currentRoom;
        loadFailingStudents();
    }

    function loadFailingStudents() {
        var room = document.getElementById('failing_room_filter').value;
        var contentDiv = document.getElementById('failing_content');
        contentDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-danger" role="status"></div><p class="mt-2 text-muted">กำลังประมวลผล...</p></div>';
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "get_failing_students.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (this.readyState === XMLHttpRequest.DONE && this.status === 200) contentDiv.innerHTML = this.responseText;
        };
        xhr.send("room=" + encodeURIComponent(room));
    }

    function printFailingReport() {
        var room = document.getElementById('failing_room_filter').value;
        window.open('print_failing_report.php?room=' + encodeURIComponent(room), '_blank');
    }
</script>

<?php include 'footer.php'; // เรียกใช้ Footer ?>
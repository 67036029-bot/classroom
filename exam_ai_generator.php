<?php
// file: exam_ai_generator.php
session_start();
// ตรวจสอบสิทธิ์ครู
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

// --- ส่วนที่ 1: บันทึกข้อสอบลงฐานข้อมูล (Backend) ---
if (isset($_POST['action']) && $_POST['action'] == 'save_exam') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) { echo json_encode(['status'=>'error', 'msg'=>'ไม่มีข้อมูลส่งมา']); exit; }

    $work_id = intval($input['work_id']);
    $questions = $input['questions'];
    
    $conn->begin_transaction();
    try {
        // 1. สร้าง/ดึง Exam Set
        $exam_id = 0;
        $chk = $conn->query("SELECT exam_id FROM tb_exam_sets WHERE work_id = $work_id");
        if ($chk->num_rows > 0) {
            $exam_id = $chk->fetch_assoc()['exam_id'];
        } else {
            // สร้างชุดข้อสอบใหม่ (PIN สุ่ม 4 หลัก)
            $pin = rand(1000, 9999);
            $conn->query("INSERT INTO tb_exam_sets (work_id, access_pin, is_active) VALUES ($work_id, '$pin', 0)");
            $exam_id = $conn->insert_id;
        }

        // 2. บันทึกคำถาม (tb_exam_questions)
        // สมมติโครงสร้างตาราง: id, exam_id, question_text, option_1, option_2, option_3, option_4, correct_option
        $stmt = $conn->prepare("INSERT INTO tb_exam_questions (exam_id, question_text, option_1, option_2, option_3, option_4, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($questions as $q) {
            // แปลง index 0-3 เป็น 1-4 (ถ้า DB เก็บแบบ 1,2,3,4) หรือเก็บ 1-4 ตามความนิยม
            $correct = $q['answer_index'] + 1; 
            $stmt->bind_param("isssssi", $exam_id, $q['question'], $q['options'][0], $q['options'][1], $q['options'][2], $q['options'][3], $correct);
            $stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(['status'=>'success', 'count'=>count($questions)]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]);
    }
    exit;
}

// --- ส่วนที่ 2: ดึงรายชื่อวิชา (Frontend Setup) ---
$courses = [];
$sql = "SELECT w.work_id, w.work_name, c.subject_name 
        FROM tb_work w 
        JOIN tb_course_info c ON w.course_id = c.id 
        ORDER BY w.work_id DESC";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()) { $courses[] = $row; }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>AI Exam Generator</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; }
        .ai-panel { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .ai-header { background: linear-gradient(135deg, #0d6efd 0%, #0043a8 100%); color: white; padding: 25px; }
        .magic-btn { background: linear-gradient(90deg, #FF0080 0%, #7928CA 100%); color: white; border: none; font-weight: bold; transition: 0.3s; }
        .magic-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(121, 40, 202, 0.4); color: white; }
        .q-card { border-left: 4px solid #0d6efd; background: #fff; margin-bottom: 15px; transition: 0.2s; }
        .q-card:hover { border-left-color: #FF0080; transform: translateX(5px); }
        .loading-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(255,255,255,0.9); z-index: 9999; text-align: center; padding-top: 20%; }
        .bounce-icon { animation: bounce 2s infinite; font-size: 3rem; color: #7928CA; }
        @keyframes bounce { 0%, 20%, 50%, 80%, 100% {transform: translateY(0);} 40% {transform: translateY(-20px);} 60% {transform: translateY(-10px);} }
    </style>
</head>
<body>

<div id="loading" class="loading-overlay">
    <div class="bounce-icon"><i class="fa-solid fa-robot"></i></div>
    <h3 class="mt-3 fw-bold text-dark">AI กำลังออกข้อสอบให้คุณ...</h3>
    <p class="text-muted">ใช้สมองกลรุ่น Gemini 2.5 Flash ⚡</p>
</div>

<div class="container py-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0"><i class="fa-solid fa-wand-magic-sparkles text-primary"></i> สร้างข้อสอบด้วย AI</h2>
            <p class="text-muted mb-0">ระบบอัจฉริยะช่วยครูออกข้อสอบใน 1 คลิก</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">กลับหน้าหลัก</a>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="ai-panel p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-sliders"></i> ตั้งค่าโจทย์</h5>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">API Key (จาก AI Studio)</label>
                    <input type="password" id="apiKey" class="form-control form-control-sm" placeholder="วาง Key ที่นี่">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">เลือกงาน/การสอบที่จะบันทึก</label>
                    <select id="workId" class="form-select">
                        <option value="">-- เลือกงาน --</option>
                        <?php foreach($courses as $c): ?>
                            <option value="<?php echo $c['work_id']; ?>"><?php echo $c['subject_name']." : ".$c['work_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr>

                <div class="mb-3">
                    <label class="form-label fw-bold">หัวข้อ / เนื้อหา</label>
                    <textarea id="topic" class="form-control" rows="3" placeholder="เช่น สงครามโลกครั้งที่ 2, การสังเคราะห์แสง, Past Simple Tense"></textarea>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">ระดับชั้น</label>
                        <select id="grade" class="form-select form-select-sm">
                            <option>ประถมปลาย</option>
                            <option selected>มัธยมต้น</option>
                            <option>มัธยมปลาย</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">ความยาก</label>
                        <select id="difficulty" class="form-select form-select-sm">
                            <option>ง่าย</option>
                            <option selected>ปานกลาง</option>
                            <option>ยาก</option>
                            <option>วิเคราะห์</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">จำนวนข้อ</label>
                    <input type="range" id="qtyRange" class="form-range" min="1" max="20" value="5" oninput="document.getElementById('qtyDisp').innerText = this.value">
                    <div class="text-end small fw-bold text-primary"><span id="qtyDisp">5</span> ข้อ</div>
                </div>

                <button onclick="generateExam()" class="btn magic-btn w-100 py-2 rounded-pill shadow">
                    <i class="fa-solid fa-bolt me-2"></i> สร้างข้อสอบทันที
                </button>
            </div>
        </div>

        <div class="col-lg-8">
            <div id="welcomeState" class="text-center py-5 text-muted bg-white rounded-4 shadow-sm">
                <i class="fa-solid fa-file-circle-question display-1 mb-3 opacity-25"></i>
                <h4>ยังไม่มีข้อสอบ</h4>
                <p>กรอกข้อมูลทางซ้ายแล้วกดปุ่มเพื่อเริ่มสร้าง</p>
            </div>

            <div id="resultState" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold m-0 text-dark">✨ ผลลัพธ์จาก AI</h5>
                    <button onclick="saveToDB()" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-save me-2"></i> บันทึกลงระบบ
                    </button>
                </div>
                
                <div id="examList">
                    </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let currentQuestions = []; // เก็บข้อมูลข้อสอบไว้ส่งเข้า DB

    async function generateExam() {
        // 1. รับค่า
        const apiKey = document.getElementById('apiKey').value.trim();
        const topic = document.getElementById('topic').value.trim();
        const grade = document.getElementById('grade').value;
        const diff = document.getElementById('difficulty').value;
        const qty = document.getElementById('qtyRange').value;

        if(!apiKey) { Swal.fire('เตือน', 'กรุณาใส่ API Key ก่อนครับ', 'warning'); return; }
        if(!topic) { Swal.fire('เตือน', 'กรุณาใส่หัวข้อที่จะออกสอบ', 'warning'); return; }

        // 2. UI Loading
        document.getElementById('loading').style.display = 'block';

        // 3. Prompt (ใช้เทคนิคบังคับ JSON)
        const prompt = `
            คุณเป็นครูมืออาชีพ ช่วยออกข้อสอบปรนัย 4 ตัวเลือก (${qty} ข้อ)
            หัวข้อ: "${topic}" สำหรับชั้น: ${grade} ความยาก: ${diff}
            
            **สำคัญมาก**: ตอบกลับเป็น JSON Array เท่านั้น (ห้ามมี Markdown \`\`\`json)
            โครงสร้าง: [{"question":"...","options":["ก...","ข...","ค...","ง..."],"answer_index":0}]
            (answer_index: 0=ข้อแรก, 1=ข้อสอง...)
        `;

        // 4. ยิงไปที่ Gemini 2.5 Flash (Model ที่คุณยืนยันว่าเวิร์ค)
        const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=${apiKey}`;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }] })
            });

            if(!response.ok) throw new Error("API Error: " + response.status);

            const data = await response.json();
            
            // 5. แกะข้อมูล
            if(data.candidates && data.candidates[0].content) {
                let raw = data.candidates[0].content.parts[0].text;
                // Clean Markdown
                raw = raw.replace(/```json/g, '').replace(/```/g, '').trim();
                
                try {
                    currentQuestions = JSON.parse(raw);
                    renderExams(currentQuestions);
                } catch(e) {
                    console.log("Raw Text:", raw);
                    throw new Error("AI ตอบกลับมาแต่รูปแบบข้อมูลไม่ถูกต้อง");
                }
            } else {
                throw new Error("AI ไม่ตอบกลับ (อาจติด Safety Filter)");
            }

        } catch(err) {
            console.error(err);
            Swal.fire('เกิดข้อผิดพลาด', err.message, 'error');
        } finally {
            document.getElementById('loading').style.display = 'none';
        }
    }

    function renderExams(questions) {
        const container = document.getElementById('examList');
        container.innerHTML = '';
        
        questions.forEach((q, idx) => {
            let optsHtml = '';
            q.options.forEach((opt, i) => {
                const isCorrect = (i === q.answer_index);
                const cls = isCorrect ? 'text-success fw-bold bg-success bg-opacity-10 rounded px-2' : 'text-muted ms-2';
                const mark = isCorrect ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-regular fa-circle"></i>';
                optsHtml += `<div class="mb-1 ${cls}">${mark} ${opt}</div>`;
            });

            const card = `
                <div class="card q-card shadow-sm p-3">
                    <h6 class="fw-bold mb-2"><span class="badge bg-primary me-2">ข้อ ${idx+1}</span> ${q.question}</h6>
                    <div class="small">${optsHtml}</div>
                </div>
            `;
            container.innerHTML += card;
        });

        document.getElementById('welcomeState').style.display = 'none';
        document.getElementById('resultState').style.display = 'block';
    }

    async function saveToDB() {
        const workId = document.getElementById('workId').value;
        if(!workId) { Swal.fire('เลือกงาน', 'กรุณาเลือกงาน/การสอบที่จะบันทึกข้อสอบชุดนี้ลงไป', 'warning'); return; }
        if(currentQuestions.length === 0) return;

        Swal.fire({
            title: 'ยืนยันการบันทึก',
            text: `ต้องการบันทึกข้อสอบ ${currentQuestions.length} ข้อ ลงในงานที่เลือกใช่ไหม?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ใช่, บันทึกเลย',
            confirmButtonColor: '#198754'
        }).then(async (res) => {
            if(res.isConfirmed) {
                // ส่งข้อมูลไป PHP
                try {
                    const resp = await fetch('exam_ai_generator.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            action: 'save_exam',
                            work_id: workId,
                            questions: currentQuestions
                        })
                    });
                    const result = await resp.json();
                    
                    if(result.status === 'success') {
                        Swal.fire('สำเร็จ!', `บันทึกข้อสอบเรียบร้อยแล้ว`, 'success').then(() => {
                            window.location = 'manage_work.php'; // กลับไปหน้าจัดการงาน
                        });
                    } else {
                        throw new Error(result.msg);
                    }
                } catch(err) {
                    Swal.fire('ผิดพลาด', 'บันทึกไม่สำเร็จ: ' + err.message, 'error');
                }
            }
        });
    }
</script>

</body>
</html>
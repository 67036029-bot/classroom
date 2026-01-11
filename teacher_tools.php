<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

// --- Pre-load Student Data for JS ---
// ดึงข้อมูลนักเรียนทั้งหมดมาเก็บไว้ในตัวแปร JS เพื่อให้การสุ่มทำงานได้ทันทีไม่ต้องรอโหลด
$students_by_room = [];
$sql = "SELECT id, std_code, title, firstname, lastname, room FROM tb_students ORDER BY std_no ASC";
$result = $conn->query($sql);
while($row = $result->fetch_assoc()) {
    $students_by_room[$row['room']][] = $row;
}
$json_students = json_encode($students_by_room);

// ดึงรายชื่อห้อง
$rooms = array_keys($students_by_room);
sort($rooms);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เครื่องมือครู (Teacher Tools)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    
    <style>
        :root {
            --pink-neon: #ff007f;
            --dark-bg: #1a1a1a;
            --card-bg: #ffffff;
        }
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6f9; overflow-x: hidden; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 25px; transition: 0.3s; }
        
        /* Custom Tabs */
        .nav-pills-custom .nav-link {
            color: #6c757d; font-weight: bold; background: white;
            border-radius: 50px; padding: 10px 25px; margin-right: 10px;
            border: 1px solid #eee; transition: 0.3s;
        }
        .nav-pills-custom .nav-link.active {
            background: var(--pink-neon); color: white;
            box-shadow: 0 4px 15px rgba(255, 0, 127, 0.4); border-color: var(--pink-neon);
        }
        .nav-pills-custom .nav-link i { margin-right: 8px; }

        /* --- Tool 1: Group Generator --- */
        .group-card {
            background: white; border-radius: 15px; overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.2s;
            border-top: 5px solid; height: 100%;
        }
        .group-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .group-header { padding: 10px 15px; font-weight: 800; font-size: 1.1rem; }
        .group-list { list-style: none; padding: 15px; margin: 0; font-size: 0.95rem; }
        .group-list li { margin-bottom: 5px; border-bottom: 1px dashed #eee; padding-bottom: 2px; }

        /* --- Tool 2: Lucky Picker --- */
        .lucky-stage {
            background: radial-gradient(circle at center, #2b2b2b, #000000);
            border-radius: 20px; padding: 40px; text-align: center;
            color: white; position: relative; overflow: hidden;
            min-height: 400px; display: flex; flex-direction: column; justify-content: center; align-items: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .lucky-name {
            font-size: 4rem; font-weight: 800;
            background: linear-gradient(to right, #fff, #aaa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            transition: 0.1s;
        }
        .lucky-name.winner {
            font-size: 5rem;
            background: linear-gradient(to right, #ff007f, #ffeb3b);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(255, 0, 127, 0.8);
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn { 0% { transform: scale(0.5); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        /* --- Tool 3: Timer --- */
        .timer-container {
            position: relative; width: 300px; height: 300px; margin: 0 auto;
        }
        .timer-display {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            font-size: 4rem; font-weight: 800; color: #333;
        }
        .circle-bg { fill: none; stroke: #eee; stroke-width: 15; }
        .circle-progress { 
            fill: none; stroke: var(--pink-neon); stroke-width: 15; stroke-linecap: round;
            transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 1s linear;
        }
        .timer-btn-group button { border-radius: 50px; font-weight: bold; width: 60px; height: 60px; }
        
        .floating-action {
            position: absolute; top: 20px; right: 20px; z-index: 10;
        }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-toolbox text-warning me-2"></i> เครื่องมือครู (Teacher Tools)</h3>
                    <small class="text-muted">รวมเครื่องมือช่วยสอน จัดกิจกรรม และสุ่มนักเรียน</small>
                </div>
            </div>

            <ul class="nav nav-pills nav-pills-custom mb-4" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="tab-group" data-bs-toggle="pill" data-bs-target="#content-group">
                        <i class="fa-solid fa-users-viewfinder"></i> จัดกลุ่มสุ่ม
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-lucky" data-bs-toggle="pill" data-bs-target="#content-lucky">
                        <i class="fa-solid fa-dice"></i> สุ่มเลขที่/ชื่อ
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-timer" data-bs-toggle="pill" data-bs-target="#content-timer">
                        <i class="fa-solid fa-stopwatch"></i> จับเวลา
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="pills-tabContent">
                
                <div class="tab-pane fade show active" id="content-group">
                    <div class="row g-4">
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm p-3 h-100">
                                <h5 class="fw-bold mb-3"><i class="fa-solid fa-sliders"></i> ตั้งค่าการจัดกลุ่ม</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted">เลือกห้องเรียน</label>
                                    <select id="grp_room" class="form-select rounded-pill fw-bold border-2">
                                        <option value="">-- เลือกห้อง --</option>
                                        <?php foreach($rooms as $r): echo "<option value='$r'>$r</option>"; endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted">เงื่อนไขการแบ่ง</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="grp_mode" id="mode_groups" value="groups" checked>
                                        <label class="btn btn-outline-dark rounded-start-pill" for="mode_groups">จำนวนกลุ่ม</label>
                                        
                                        <input type="radio" class="btn-check" name="grp_mode" id="mode_people" value="people">
                                        <label class="btn btn-outline-dark rounded-end-pill" for="mode_people">คนต่อกลุ่ม</label>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-muted">จำนวน</label>
                                    <input type="number" id="grp_num" class="form-control form-control-lg text-center fw-bold" value="5" min="1">
                                </div>

                                <button onclick="generateGroups()" class="btn btn-dark w-100 rounded-pill py-3 fw-bold shadow">
                                    <i class="fa-solid fa-shuffle me-2"></i> สุ่มจัดกลุ่ม
                                </button>
                            </div>
                        </div>

                        <div class="col-md-9">
                            <div class="position-relative">
                                <div id="group_results" class="row g-3">
                                    <div class="text-center py-5 text-muted opacity-50">
                                        <i class="fa-solid fa-layer-group display-1 mb-3"></i>
                                        <h4>เลือกห้องและกดสุ่มเพื่อเริ่มใช้งาน</h4>
                                    </div>
                                </div>
                                <button id="btn_copy_group" class="btn btn-success position-absolute top-0 end-0 m-2 shadow-sm rounded-pill px-3 d-none" onclick="copyGroups()">
                                    <i class="fa-regular fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="content-lucky">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-center mb-4 gap-3 align-items-center">
                                <select id="lucky_room" class="form-select rounded-pill fw-bold border-2" style="width: 200px;">
                                    <option value="">-- เลือกห้อง --</option>
                                    <?php foreach($rooms as $r): echo "<option value='$r'>$r</option>"; endforeach; ?>
                                </select>
                                <button onclick="startLuckyDraw()" id="btn_lucky_start" class="btn btn-lg btn-danger rounded-pill px-5 fw-bold shadow" disabled>
                                    <i class="fa-solid fa-play me-2"></i> สุ่มผู้โชคดี!
                                </button>
                            </div>

                            <div class="lucky-stage">
                                <div class="floating-action">
                                    <button class="btn btn-outline-light rounded-circle" onclick="resetLucky()" title="Reset"><i class="fa-solid fa-rotate-right"></i></button>
                                </div>
                                
                                <div id="lucky_display" class="lucky-name">
                                    <i class="fa-solid fa-question opacity-25"></i>
                                </div>
                                <div id="lucky_detail" class="mt-3 text-white-50" style="min-height: 20px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="content-timer">
                    <div class="row justify-content-center">
                        <div class="col-md-6 text-center">
                            <div class="card border-0 shadow-sm p-5 rounded-4">
                                <h4 class="fw-bold mb-4 text-muted">จับเวลาถอยหลัง</h4>
                                
                                <div class="timer-container mb-4">
                                    <svg width="300" height="300">
                                        <circle class="circle-bg" cx="150" cy="150" r="130"></circle>
                                        <circle id="timer_ring" class="circle-progress" cx="150" cy="150" r="130" stroke-dasharray="817" stroke-dashoffset="0"></circle>
                                    </svg>
                                    <div id="timer_text" class="timer-display">00:00</div>
                                </div>

                                <div class="mb-4">
                                    <button onclick="addTime(1)" class="btn btn-outline-secondary rounded-pill me-1">+1m</button>
                                    <button onclick="addTime(5)" class="btn btn-outline-secondary rounded-pill me-1">+5m</button>
                                    <button onclick="addTime(10)" class="btn btn-outline-secondary rounded-pill me-1">+10m</button>
                                    <button onclick="setTimeCustom()" class="btn btn-outline-secondary rounded-pill px-3">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                </div>

                                <div class="d-flex justify-content-center gap-3">
                                    <button onclick="toggleTimer()" id="btn_timer_toggle" class="btn btn-success btn-lg rounded-pill px-5 fw-bold shadow">
                                        <i class="fa-solid fa-play"></i> เริ่ม
                                    </button>
                                    <button onclick="resetTimer()" class="btn btn-danger btn-lg rounded-pill px-4 fw-bold shadow">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // 1. DATA PREPARATION
        const allStudents = <?php echo $json_students; ?>;
        
        // --- GROUP GENERATOR LOGIC ---
        function generateGroups() {
            const room = document.getElementById('grp_room').value;
            const mode = document.querySelector('input[name="grp_mode"]:checked').value;
            let num = parseInt(document.getElementById('grp_num').value);

            if(!room) { Swal.fire('แจ้งเตือน', 'กรุณาเลือกห้องเรียนก่อนครับ', 'warning'); return; }
            if(!allStudents[room]) { Swal.fire('ขออภัย', 'ไม่พบรายชื่อนักเรียนในห้องนี้', 'error'); return; }

            // 1. Clone & Shuffle
            let list = [...allStudents[room]];
            list.sort(() => Math.random() - 0.5);

            // 2. Calculate Chunks
            let chunks = [];
            const total = list.length;
            
            if (mode === 'groups') {
                // แบ่งตามจำนวนกลุ่ม (เช่น 5 กลุ่ม)
                if(num > total) num = total;
                for (let i = 0; i < num; i++) chunks.push([]);
                list.forEach((std, i) => {
                    chunks[i % num].push(std);
                });
            } else {
                // แบ่งตามจำนวนคน (เช่น กลุ่มละ 4 คน)
                for (let i = 0; i < list.length; i += num) {
                    chunks.push(list.slice(i, i + num));
                }
            }

            // 3. Render HTML
            const container = document.getElementById('group_results');
            container.innerHTML = '';
            const colors = ['#0d6efd', '#6610f2', '#d63384', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'];

            chunks.forEach((group, idx) => {
                let color = colors[idx % colors.length];
                let html = `
                <div class="col-md-4 col-xl-3 animate-up">
                    <div class="group-card" style="border-top-color: ${color};">
                        <div class="group-header" style="color: ${color}; bg-light">
                            <i class="fa-solid fa-users"></i> กลุ่มที่ ${idx + 1}
                            <span class="float-end badge bg-light text-dark border">${group.length} คน</span>
                        </div>
                        <ul class="group-list">
                            ${group.map(s => `<li>${s.title}${s.firstname}</li>`).join('')}
                        </ul>
                    </div>
                </div>`;
                container.insertAdjacentHTML('beforeend', html);
            });

            document.getElementById('btn_copy_group').classList.remove('d-none');
        }

        function copyGroups() {
            const room = document.getElementById('grp_room').value;
            const container = document.getElementById('group_results');
            if(!container.innerText) return;

            let text = `📌 ผลการจัดกลุ่ม ห้อง ${room}\n\n`;
            const cards = container.querySelectorAll('.group-card');
            
            cards.forEach((card) => {
                let title = card.querySelector('.group-header').innerText.split('\n')[0]; // เอาแค่ชื่อกลุ่ม
                let names = Array.from(card.querySelectorAll('li')).map(li => li.innerText).join(', ');
                text += `✅ ${title}: ${names}\n`;
            });

            navigator.clipboard.writeText(text).then(() => {
                const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                Toast.fire({ icon: 'success', title: 'คัดลอกเรียบร้อย!' });
            });
        }

        // --- LUCKY PICKER LOGIC ---
        let luckyInterval;
        let isSpinning = false;

        document.getElementById('lucky_room').addEventListener('change', function() {
            const btn = document.getElementById('btn_lucky_start');
            btn.disabled = !this.value;
            resetLucky();
        });

        function startLuckyDraw() {
            const room = document.getElementById('lucky_room').value;
            if(!room) return;
            const list = allStudents[room];
            const display = document.getElementById('lucky_display');
            const detail = document.getElementById('lucky_detail');
            const btn = document.getElementById('btn_lucky_start');

            if(isSpinning) return; // Prevent double click
            isSpinning = true;
            btn.disabled = true;
            display.classList.remove('winner');
            detail.innerText = "";

            let counter = 0;
            let speed = 50; // เริ่มต้นเร็ว
            
            // Loop สุ่มชื่อ
            const run = () => {
                const randIndex = Math.floor(Math.random() * list.length);
                const student = list[randIndex];
                display.innerText = student.firstname;
                
                counter++;
                
                if (counter < 20) {
                    luckyInterval = setTimeout(run, speed);
                } else if (counter < 30) {
                    speed += 20; // ช้าลง
                    luckyInterval = setTimeout(run, speed);
                } else if (counter < 35) {
                    speed += 60; // ช้าลงอีก
                    luckyInterval = setTimeout(run, speed);
                } else {
                    // STOP & WINNER
                    finishLucky(student);
                }
            };
            run();
        }

        function finishLucky(student) {
            const display = document.getElementById('lucky_display');
            const detail = document.getElementById('lucky_detail');
            const btn = document.getElementById('btn_lucky_start');

            display.innerText = student.firstname + " " + student.lastname;
            display.classList.add('winner');
            detail.innerHTML = `<span class="badge bg-light text-dark fs-6 mt-2">เลขที่ ${student.std_no} | รหัส ${student.std_code}</span>`;
            
            // Confetti Effect
            confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
            
            isSpinning = false;
            btn.disabled = false;
        }

        function resetLucky() {
            clearTimeout(luckyInterval);
            isSpinning = false;
            document.getElementById('lucky_display').innerHTML = '<i class="fa-solid fa-question opacity-25"></i>';
            document.getElementById('lucky_display').classList.remove('winner');
            document.getElementById('lucky_detail').innerText = '';
            if(document.getElementById('lucky_room').value) document.getElementById('btn_lucky_start').disabled = false;
        }

        // --- TIMER LOGIC ---
        let timerIntervalId;
        let totalTime = 0;
        let timeLeft = 0;
        let isTimerRunning = false;
        const ring = document.getElementById('timer_ring');
        const circumference = 2 * Math.PI * 130; // r=130

        function updateTimerDisplay() {
            let m = Math.floor(timeLeft / 60);
            let s = timeLeft % 60;
            document.getElementById('timer_text').innerText = 
                `${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
            
            // Update Ring
            let offset = 0;
            if(totalTime > 0) {
                offset = circumference - (timeLeft / totalTime) * circumference;
            }
            ring.style.strokeDashoffset = offset;

            // Color Change
            if(timeLeft <= 10 && timeLeft > 0) {
                ring.style.stroke = '#dc3545'; // Red
                document.getElementById('timer_text').style.color = '#dc3545';
            } else if (timeLeft == 0) {
                document.getElementById('timer_text').classList.add('blink');
            } else {
                ring.style.stroke = '#ff007f'; // Pink
                document.getElementById('timer_text').style.color = '#333';
            }
        }

        function addTime(min) {
            if(isTimerRunning) return;
            timeLeft += min * 60;
            totalTime = timeLeft; // Reset base
            updateTimerDisplay();
        }

        function setTimeCustom() {
            if(isTimerRunning) return;
            Swal.fire({
                title: 'ระบุเวลา (นาที)',
                input: 'number',
                inputAttributes: { min: 1, step: 1 },
                showCancelButton: true,
                confirmButtonColor: '#212529'
            }).then((res) => {
                if(res.isConfirmed && res.value) {
                    timeLeft = parseInt(res.value) * 60;
                    totalTime = timeLeft;
                    updateTimerDisplay();
                }
            });
        }

        function toggleTimer() {
            if(isTimerRunning) {
                // Pause
                clearInterval(timerIntervalId);
                isTimerRunning = false;
                document.getElementById('btn_timer_toggle').innerHTML = '<i class="fa-solid fa-play"></i> ต่อ';
                document.getElementById('btn_timer_toggle').classList.replace('btn-warning', 'btn-success');
            } else {
                // Start
                if(timeLeft <= 0) return;
                isTimerRunning = true;
                document.getElementById('btn_timer_toggle').innerHTML = '<i class="fa-solid fa-pause"></i> หยุด';
                document.getElementById('btn_timer_toggle').classList.replace('btn-success', 'btn-warning');
                
                timerIntervalId = setInterval(() => {
                    if(timeLeft > 0) {
                        timeLeft--;
                        updateTimerDisplay();
                    } else {
                        // Time's up
                        clearInterval(timerIntervalId);
                        isTimerRunning = false;
                        playSound();
                        Swal.fire({ title: 'หมดเวลา!', icon: 'warning', confirmButtonText: 'ตกลง' });
                        document.getElementById('btn_timer_toggle').innerHTML = '<i class="fa-solid fa-play"></i> เริ่ม';
                        document.getElementById('btn_timer_toggle').classList.replace('btn-warning', 'btn-success');
                    }
                }, 1000);
            }
        }

        function resetTimer() {
            clearInterval(timerIntervalId);
            isTimerRunning = false;
            timeLeft = 0;
            totalTime = 0;
            updateTimerDisplay();
            document.getElementById('btn_timer_toggle').innerHTML = '<i class="fa-solid fa-play"></i> เริ่ม';
            document.getElementById('btn_timer_toggle').classList.replace('btn-warning', 'btn-success');
        }

        function playSound() {
            // Beep Sound (Base64 for no dependency)
            const audio = new Audio("data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU..."); 
            // หมายเหตุ: เพื่อไม่ให้โค้ดยาวเกินไป ผมละ Base64 ไว้ แต่แนะนำให้หาไฟล์ mp3 สั้นๆ มาใส่
            // หรือใช้ beep ของ Browser
            const context = new (window.AudioContext || window.webkitAudioContext)();
            const osc = context.createOscillator();
            osc.type = 'square';
            osc.frequency.value = 800;
            osc.connect(context.destination);
            osc.start();
            setTimeout(() => osc.stop(), 500); // Beep 0.5s
        }

        // Init Ring
        ring.style.strokeDasharray = `${circumference} ${circumference}`;
        ring.style.strokeDashoffset = 0; // เต็มวงเริ่ม
    </script>
</body>
</html>
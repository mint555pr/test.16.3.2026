<?php 
require 'db.php'; 
if (!isset($_SESSION['user_name'])) { header("Location: login.php"); exit(); }
$currentUserCompany = $_SESSION['company'] ?? '-'; 
$myName = $_SESSION['user_name'];
$myRole = $_SESSION['role'];
$myCompany = $_SESSION['company'];

// แปลง Role เป็นภาษาไทยสำหรับแสดงผลในเมนู
$roleThai = match($myRole) {
    'Admin' => 'แอดมิน',
    'Head' => 'หัวหน้าบริษัท',
    'Head IT' => 'หัวหน้า IT',
    'Manager' => 'ผู้จัดการ',
    'Employee' => 'พนักงาน',
    default => $myRole
};

// เช็คหน้าปัจจุบันเพื่อทำ Active Menu
$currentPage = basename($_SERVER['PHP_SELF']);
$statPending = 0; // กำหนดค่าไว้เผื่อกรณี Head/Manager เข้ามาหน้านี้

// (Logic ดึง Head เหมือนเดิม...)
$heads = [];
try {
    $response = $service->spreadsheets_values->get($spreadsheetId, 'Users!A:F');
    $users = $response->getValues();
    if (!empty($users)) {
        foreach ($users as $index => $row) {
            if ($index == 0) continue;
            $role = $row[3] ?? ''; 
            $comp = $row[4] ?? '';
            //if ($role == 'Head' && $comp == $currentUserCompany) $heads[] = $row[0];
            if ($role == 'Head' && strpos($comp, $currentUserCompany) !== false) {
                $heads[] = $row[0];}
        }
    }
} catch (Exception $e) { }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แจ้งเปลี่ยนเบอร์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    <style> 
        body { font-family: 'Sarabun', sans-serif; } 
        
        /* สไตล์สำหรับเมนูที่นุ่มนวลขึ้น */
        .menu-item {
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .menu-item::before {
            content: '';
            position: absolute;
            left: -100%;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, rgba(239,246,255,0) 0%, rgba(219,234,254,0.5) 50%, rgba(239,246,255,0) 100%);
            transition: left 0.5s ease;
            z-index: 0;
        }
        
        .menu-item:hover::before {
            left: 100%;
        }
        
        .menu-item > * {
            position: relative;
            z-index: 1;
        }

        .menu-active {
            background-color: #eff6ff;
            color: #1d4ed8;
            font-weight: 700;
            border-left: 4px solid #2563eb;
            padding-left: 0.75rem !important;
        }
        
        .menu-inactive {
            color: #4b5563;
            font-weight: 500;
            border-left: 4px solid transparent;
        }
        
        .menu-inactive:hover {
            background-color: #f9fafb;
            color: #111827;
            transform: translateX(4px);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">

    <div class="flex min-h-screen">
        
        <aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col fixed h-full z-10 shadow-sm transition-all duration-300">
            <div class="h-16 flex items-center px-6 border-b border-gray-100 group">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white mr-3 shadow-blue-200 shadow-lg transition-transform duration-300 group-hover:scale-110">
                    <i class="fa-solid fa-mobile-screen"></i>
                </div>
                <span class="text-xl font-bold text-gray-800 tracking-tight">NumberSystem</span>
            
            </div>

            <div class="p-4 flex-1 overflow-y-auto">
                <p class="px-4 text-xs font-bold text-gray-400 uppercase mb-3 tracking-wider">เมนูหลัก</p>
                <nav class="space-y-1.5">
                    <a href="dashboard.php" class="menu-item flex items-center px-4 py-3 rounded-lg <?php echo ($currentPage == 'dashboard.php') ? 'menu-active' : 'menu-inactive'; ?>">
                        <i class="fa-solid fa-chart-pie w-6 text-center <?php echo ($currentPage == 'dashboard.php') ? 'text-blue-600' : 'text-gray-400'; ?> transition-colors duration-300"></i>
                        <span>ภาพรวม</span>
                    </a>

                    <?php if(!in_array($myRole, ['Head', 'Head IT', 'Manager'])): ?>
                    <a href="form_change.php" class="menu-item flex items-center px-4 py-3 rounded-lg <?php echo ($currentPage == 'form_change.php') ? 'menu-active' : 'menu-inactive'; ?>">
                        <i class="fa-solid fa-file-pen w-6 text-center <?php echo ($currentPage == 'form_change.php') ? 'text-blue-600' : 'text-gray-400'; ?> transition-colors duration-300"></i> 
                        <span>แจ้งเปลี่ยนเบอร์</span>
                    </a>
                    
                    <a href="history.php" class="menu-item flex items-center px-4 py-3 rounded-lg <?php echo ($currentPage == 'history.php') ? 'menu-active' : 'menu-inactive'; ?>">
                        <i class="fa-solid fa-clock-rotate-left w-6 text-center <?php echo ($currentPage == 'history.php') ? 'text-blue-600' : 'text-gray-400'; ?> transition-colors duration-300"></i> 
                        <span>ประวัติของฉัน</span>
                    </a>
                    <?php endif; ?>

                    <?php if(in_array($myRole, ['Head', 'Head IT', 'Manager'])): ?>
                    <a href="approve_dashboard.php" class="menu-item flex items-center px-4 py-3 rounded-lg <?php echo ($currentPage == 'approve_dashboard.php') ? 'menu-active' : 'menu-inactive'; ?>">
                        <i class="fa-solid fa-check-to-slot w-6 text-center <?php echo ($currentPage == 'approve_dashboard.php') ? 'text-blue-600' : 'text-gray-400'; ?> transition-colors duration-300"></i> 
                        <span>รออนุมัติ</span>
                        <?php if($statPending > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm animate-pulse border border-red-600">
                            <?php echo $statPending; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>

            <div class="p-4 border-t border-gray-100 hover:bg-gray-50 transition-colors duration-300 cursor-pointer group">
                <div class="flex items-center gap-3 px-2 py-2">
                    <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold border border-gray-200 group-hover:bg-blue-100 group-hover:text-blue-600 group-hover:border-blue-200 transition-colors duration-300">
                        <?php echo strtoupper(substr($myName, 0, 1)); ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-800 truncate"><?php echo $myName; ?></p>
                        <p class="text-[11px] text-gray-500 truncate"><?php echo $roleThai; ?></p>
                    </div>
                    <a href="#" onclick="confirmLogout(event)" class="text-gray-400 hover:text-red-500 transition-colors duration-200 p-2 rounded-lg hover:bg-red-50">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        </aside>

        <main class="flex-1 md:ml-64 p-4 md:p-8 bg-slate-50 min-h-screen">
            <div class="md:hidden flex justify-between items-center mb-6 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                <span class="font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-mobile-screen text-blue-600"></i> NumberSystem</span>
                <a href="#" onclick="confirmLogout(event)" class="text-gray-500 hover:text-red-500 transition-colors"><i class="fa-solid fa-power-off"></i></a>
            </div>
            
            <div class="max-w-3xl mx-auto">
                <div class="mb-6">
                    <a href="dashboard.php" class="text-sm text-gray-500 hover:text-blue-600 mb-2 inline-block"><i class="fa-solid fa-arrow-left mr-1"></i> กลับหน้าหลัก</a>
                    <h1 class="text-2xl font-bold text-gray-800">📝 แจ้งขอเปลี่ยนเบอร์โทรศัพท์</h1>
                    <p class="text-gray-500 text-sm">กรอกข้อมูลให้ครบถ้วนเพื่อส่งอนุมัติ</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-8">
                        <form method="post" class="space-y-6">
                            <div class="bg-blue-50 p-4 rounded-xl border border-blue-100 grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-blue-400 uppercase">วันที่แจ้ง</label>
                                    <p class="text-blue-900 font-bold"><?php echo date("d/m/Y H:i"); ?></p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-blue-400 uppercase">ผู้ทำรายการ</label>
                                    <p class="text-blue-900 font-bold"><?php echo $_SESSION['user_name']; ?></p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">รหัสสมาชิก <span class="text-red-500">*</span></label>
                                <input type="text" name="member_no" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="ระบุรหัสสมาชิก">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">ชื่อ-นามสกุล (เจ้าของเบอร์) <span class="text-red-500">*</span></label>
                                <input type="text" name="owner_name" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="ระบุชื่อลูกค้า...">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">เบอร์เดิม <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-red-400"><i class="fa-solid fa-phone-slash"></i></div>
                                        <input type="text" name="old_num" required class="w-full pl-10 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-200 outline-none bg-red-50/30 transition" placeholder="08x-xxx-xxxx">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">เบอร์ใหม่ <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-green-500"><i class="fa-solid fa-phone"></i></div>
                                        <input type="text" name="new_num" required class="w-full pl-10 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-200 outline-none bg-green-50/30 transition" placeholder="08x-xxx-xxxx">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">ส่งให้หัวหน้าอนุมัติ <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <select name="selected_head_name" required class="w-full pl-10 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white appearance-none cursor-pointer hover:border-blue-400 transition">
                                        <option value="">-- เลือกหัวหน้า --</option>
                                        <?php foreach($heads as $h): ?>
                                            <option value="<?php echo $h; ?>">คุณ <?php echo $h; ?></option>
                                        <?php endforeach; ?>
                                      
                                    </select>
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><i class="fa-solid fa-user-tie"></i></div>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400"><i class="fa-solid fa-chevron-down text-xs"></i></div>
                                </div>
                            </div>

                            <button type="submit" name="submit_request" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-200 transition transform hover:-translate-y-0.5 active:translate-y-0">
                                <i class="fa-solid fa-paper-plane mr-2"></i> ยืนยันการส่งคำขอ
                            </button>
                        </form>

                        <?php
                        if (isset($_POST['submit_request'])) {
                             $timestamp = date("Y-m-d H:i:s");
                             $requester = $_SESSION['user_name'];
                             $ownerName = $_POST['owner_name'];
                             $oldNum = $_POST['old_num'];
                             $newNum = $_POST['new_num'];
                             $approverName = $_POST['selected_head_name'];
                             $statusHead = 'Pending'; $statusManager = 'Pending'; 
                             $newRow = [ [$timestamp, $requester, $ownerName, $oldNum, $newNum, $approverName, $statusHead, $statusManager] ];
                             $body = new \Google\Service\Sheets\ValueRange(['values' => $newRow]);
                             $params = ['valueInputOption' => 'RAW'];
                             $member_no = $_POST['member_no'] ?? '';

                             $statusHead = 'Pending';
                             $statusManager = 'Pending';

                            $newRow = [[
                            $timestamp,
                            $requester,
                            $ownerName,
                            $oldNum,
                            $newNum,
                            $approverName,
                            $statusHead,
                            $statusManager,
                            "",
                            "",
                            $member_no
                                        ]];

                            $body = new \Google\Service\Sheets\ValueRange(['values' => $newRow]);
                            $params = ['valueInputOption' => 'RAW'];

                             try {
                                 $service->spreadsheets_values->append($spreadsheetId, 'Requests!A:K', $body, $params);
                                 echo "<script>alert('บันทึกสำเร็จ!'); window.location='history.php';</script>";
                             } catch(Exception $e) { echo "Error"; }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // ฟังก์ชันตกแต่ง Logout
        function confirmLogout(event) {
            event.preventDefault(); // หยุดการทำงานของปุ่มไว้ก่อน
            Swal.fire({
                title: 'ออกจากระบบ?',
                text: "คุณต้องการออกจากระบบใช่หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444', // สีแดง
                cancelButtonColor: '#9ca3af',  // สีเทา
                confirmButtonText: '<i class="fa-solid fa-right-from-bracket mr-1"></i> ยืนยัน',
                cancelButtonText: 'ยกเลิก',
                background: '#ffffff',
                borderRadius: '1rem', // ขอบโค้งให้เข้ากับเว็บ
                customClass: {
                    title: 'font-bold text-gray-800 font-sans',
                    popup: 'rounded-2xl shadow-xl border border-gray-100',
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php'; // เด้งไปหน้า logout เมื่อกดยืนยัน
                }
            });
        }

        // ฟังก์ชันดึงเบอร์เก่าอัตโนมัติ (คงไว้เหมือนเดิม)
        document.addEventListener('DOMContentLoaded', function() {
            const ownerNameInput = document.querySelector('input[name="owner_name"]');
            const oldNumInput = document.querySelector('input[name="old_num"]');
            let timeoutId;

            if (ownerNameInput && oldNumInput) {
                ownerNameInput.addEventListener('input', function() {
                    clearTimeout(timeoutId);
                    const nameToSearch = this.value.trim();

                    if (nameToSearch.length > 2) { 
                        timeoutId = setTimeout(() => {
                            fetch(`get_old_number.php?name=${encodeURIComponent(nameToSearch)}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.old_number) {
                                        oldNumInput.value = data.old_number;
                                        const originalClasses = oldNumInput.className;
                                        oldNumInput.classList.add('bg-green-100', 'border-green-400');
                                        setTimeout(() => {
                                            oldNumInput.className = originalClasses;
                                        }, 1500);
                                    }
                                })
                                .catch(error => console.error('Error fetching data:', error));
                        }, 500);
                    }
                });
            }
        });
    </script>
</body>
</html>
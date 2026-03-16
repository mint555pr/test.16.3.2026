<?php 
require 'db.php'; 


// ตรวจสอบล็อกอิน
if (!isset($_SESSION['user_name'])) { header("Location: login.php"); exit(); }
if ($_SESSION['role'] == 'Admin') { header("Location: admin/admin_dashboard.php"); exit(); }

// ✅ ประกาศตัวแปรให้ชัดเจน
$myRole = $_SESSION['role'];
$myName = $_SESSION['user_name'];
$myCompany = $_SESSION['company'];


// ✅ 1. ดึงข้อมูล Users เพื่อจับคู่ ชื่อ -> บริษัท (จำเป็นต้องรู้ว่าคนขออยู่บริษัทไหน)
$userCompanyMap = [];
$myCompany = '';
try {
    // ดึงข้อมูล Users!A:E (Name, User, Pass, Role, Company)
    $uResponse = $service->spreadsheets_values->get($spreadsheetId, 'Users!A:E');
    $uRows = $uResponse->getValues();
    foreach ($uRows as $u) {
        $uName = $u[0] ?? '';
        $uComp = $u[4] ?? '-'; // Column E คือ Company
        if ($uName) {
            $userCompanyMap[$uName] = $uComp;
        }
    }
    // หาบริษัทของฉัน
    $myCompany = $userCompanyMap[$myName] ?? '-';
} catch (Exception $e) { }

// ✅ แปลง Role เป็นภาษาไทย (เพิ่ม Head IT)
$roleThai = match($myRole) {
    'Admin' => 'แอดมิน',
    'Head' => 'หัวหน้าบริษัท',
    'Head IT' => 'หัวหน้า IT',
    'Manager' => 'ผู้จัดการ',
    'Employee' => 'พนักงาน',
    default => $myRole
};

// --- คำนวณสถิติ ---
$statTotal = 0; $statPending = 0; $statSuccess = 0; $statRejected = 0;
$recentActivities = []; 

try {
    $response = $service->spreadsheets_values->get($spreadsheetId, 'Requests!A:H');
    $rows = $response->getValues();
    
    if (!empty($rows)) {
        $reversedRows = array_reverse($rows); 
        if (count($reversedRows) > 0 && $reversedRows[count($reversedRows)-1][0] == 'Timestamp') array_pop($reversedRows);

        foreach ($reversedRows as $row) {
            $timestamp = $row[0] ?? '-';
            $requester = $row[1] ?? '-';
            $owner = $row[2] ?? '-';
            $statusHead = $row[6] ?? 'Pending';
            $statusManager = $row[7] ?? 'Pending';
            
            // หาบริษัทของคนขอ
            $requesterCompany = $userCompanyMap[$requester] ?? 'Unknown';

            $isRelated = false;

            // --- เงื่อนไขการมองเห็น (Logic ใหม่) ---
            if ($myRole == 'Employee' && $requester == $myName) {
                // พนักงานเห็นของตัวเอง
                $isRelated = true;
            }
            elseif ( ($myRole == 'Head' || $myRole == 'Head IT') && $requesterCompany == $myCompany ) {
                // หัวหน้า (หรือ Head IT) เห็นของพนักงานในบริษัทตัวเอง
                $isRelated = true;
            }
            elseif ($myRole == 'Manager' && $statusHead == 'Approved') {
                // ผู้จัดการเห็นรายการที่หัวหน้าอนุมัติแล้ว
                $isRelated = true;
            }

            if ($isRelated) {
                $statTotal++;
                
                // นับสถานะ
                if ($statusHead == 'Rejected' || $statusManager == 'Rejected') {
                    $statRejected++;
                }
                elseif ($statusManager == 'Approved') {
                    $statSuccess++;
                }
                else {
                    // เช็คว่างานค้างที่ใคร
                    if ( ($myRole == 'Head' || $myRole == 'Head IT') && $statusHead != 'Pending') {}
                    elseif ($myRole == 'Manager' && $statusManager != 'Pending') {}
                    else {
                        $statPending++; // งานค้างที่ต้องทำ
                    }
                }

                // รายการล่าสุด
                if (count($recentActivities) < 5) {
                    $displayStatus = 'รอตรวจสอบ'; $statusColor = 'text-yellow-600 bg-yellow-100';
                    
                    if ($statusManager == 'Approved') { 
                        $displayStatus = 'อนุมัติแล้ว'; $statusColor = 'text-green-600 bg-green-100'; 
                    }
                    elseif ($statusHead == 'Rejected' || $statusManager == 'Rejected') { 
                        $displayStatus = 'ไม่อนุมัติ'; $statusColor = 'text-red-600 bg-red-100'; 
                    }
                    elseif ($myRole == 'Manager' && $statusHead == 'Approved') { 
                        $displayStatus = 'รอผู้จัดการ'; 
                    }
                    elseif ( ($myRole == 'Head' || $myRole == 'Head IT') && $statusHead == 'Approved') {
                        $displayStatus = 'รอผู้จัดการ';
                    }
                    
                    $recentActivities[] = ['time' => $timestamp, 'title' => "เปลี่ยนเบอร์ : คุณ $owner", 'status' => $displayStatus, 'color' => $statusColor];
                }
            }
        }
    }
} catch (Exception $e) { }

// เช็คว่าตอนนี้อยู่หน้าไหนเพื่อทำ Active Menu
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    <style> 
        body { font-family: 'Sarabun', sans-serif; } 
        
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
                <div>
                    <span class="text-xl font-bold text-gray-800 tracking-tight">
                        NumberSystem
                    </span>

                    <p class="text-xs text-gray-500 mt-1">
                    <span class="font-semibold">สิทธิบริษัท :</span>
                    <?= $myCompany ?>
                    </p>
                </div>
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

            <header class="mb-8">
                <h1 class="text-2xl font-bold text-gray-800">ระบบแจ้งเปลี่ยนเบอร์โทรศัพท์</h1>
                <?php if(in_array($myRole, ['Head', 'Head IT'])): ?>
                <p class="text-sm text-gray-500 mt-1">ส่วนจัดการสำหรับหัวหน้าสังกัด: <span class="font-bold text-blue-600"><?php echo $myCompany; ?></span></p>
                <?php endif; ?>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-folder-open"></i></div>
                        <span class="text-xs font-bold text-gray-400 bg-gray-50 px-2 py-1 rounded">ทั้งหมด</span>
                    </div>
                    <div class="text-gray-500 text-sm font-medium">รายการทั้งหมด</div>
                    <div class="text-3xl font-bold text-gray-800 mt-1"><?php echo $statTotal; ?></div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-yellow-50 text-yellow-600 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-clock"></i></div>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-2 py-1 rounded">รอผลอนุมัติ</span>
                    </div>
                    <div class="text-gray-500 text-sm font-medium"><?php echo ($myRole == 'Employee') ? 'รอผลอนุมัติ' : 'งานรอคุณตรวจ'; ?></div>
                    <div class="text-3xl font-bold text-yellow-600 mt-1"><?php echo $statPending; ?></div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-circle-check"></i></div>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">อนุมัติสำเร็จ</span>
                    </div>
                    <div class="text-gray-500 text-sm font-medium">อนุมัติสำเร็จ</div>
                    <div class="text-3xl font-bold text-emerald-600 mt-1"><?php echo $statSuccess; ?></div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-red-50 text-red-500 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-circle-xmark"></i></div>
                        <span class="text-xs font-bold text-red-500 bg-red-50 px-2 py-1 rounded">ไม่อนุมัติ</span>
                    </div>
                    <div class="text-gray-500 text-sm font-medium">ไม่อนุมัติ</div>
                    <div class="text-3xl font-bold text-red-500 mt-1"><?php echo $statRejected; ?></div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-white">
                    <h3 class="font-bold text-gray-800 text-sm flex items-center"><i class="fa-solid fa-bolt text-yellow-500 mr-2"></i> ความเคลื่อนไหวล่าสุด</h3>
                </div>
                <div class="p-2">
                    <?php if(empty($recentActivities)): ?>
                        <div class="text-center py-10 text-gray-400 text-sm flex flex-col items-center"><i class="fa-regular fa-folder-open text-3xl mb-2 opacity-30"></i>ไม่มีรายการล่าสุด</div>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-50">
                            <?php foreach($recentActivities as $act): ?>
                            <li class="p-4 hover:bg-slate-50 rounded-xl transition duration-200 cursor-default">
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center gap-4">
                                        <div class="w-2 h-2 rounded-full <?php echo str_replace('text-', 'bg-', explode(' ', $act['color'])[0]); ?>"></div>
                                        <div>
                                            <p class="text-sm font-bold text-gray-700"><?php echo $act['title']; ?></p>
                                            <p class="text-xs text-gray-400 flex items-center mt-0.5"><i class="fa-regular fa-clock mr-1"></i> <?php echo date('d/m/Y H:i', strtotime($act['time'])); ?></p>
                                        </div>
                                    </div>
                                    <span class="<?php echo $act['color']; ?> px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide border border-current opacity-80"><?php echo $act['status']; ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function confirmLogout(event) {
        event.preventDefault();
        Swal.fire({
            title: 'ออกจากระบบ?',
            text: "คุณต้องการออกจากระบบใช่หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#9ca3af',
            confirmButtonText: '<i class="fa-solid fa-right-from-bracket mr-1"></i> ยืนยัน',
            cancelButtonText: 'ยกเลิก',
            background: '#ffffff',
            borderRadius: '1rem',
            customClass: {
                title: 'font-bold text-gray-800 font-sans',
                popup: 'rounded-2xl shadow-xl border border-gray-100',
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    }
    </script>
</body>
</html>
<?php 
require 'db.php'; 
if (!isset($_SESSION['user_name'])) { header("Location: login.php"); exit(); }
$myRole = $_SESSION['role'];
$myName = $_SESSION['user_name'];

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

// ดึงข้อมูล (ขยายเป็น A:J เพื่อเอา IT_Status)
$myRequests = [];
try {
    $response = $service->spreadsheets_values->get($spreadsheetId, 'Requests!A:J');
    $rows = $response->getValues();
    if (!empty($rows)) {
        foreach ($rows as $index => $row) {
            if ($index == 0) continue;
            if (isset($row[1]) && $row[1] == $_SESSION['user_name']) {
                $myRequests[] = $row;
            }
        }
        // เรียงใหม่สุดขึ้นก่อน
        usort($myRequests, function($a, $b) { return strtotime($b[0]) - strtotime($a[0]); });
    }
} catch (Exception $e) { }

function getStatusBadge($status) {
    return match($status) {
        'Approved' => '<span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-bold border border-green-200"><i class="fa-solid fa-check mr-1"></i>อนุมัติ</span>',
        'Rejected' => '<span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs font-bold border border-red-200"><i class="fa-solid fa-times mr-1"></i>ไม่อนุมัติ</span>',
        default => '<span class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded text-xs font-bold border border-yellow-200"><i class="fa-regular fa-clock mr-1"></i>รออนุมัติ</span>'
    };
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติรายการ</title>
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
            background-color: #eff6ff; /* bg-blue-50 */
            color: #1d4ed8; /* text-blue-700 */
            font-weight: 700;
            border-left: 4px solid #2563eb; /* ขีดสีน้ำเงินด้านซ้าย */
            padding-left: 0.75rem !important; /* ปรับ padding ให้สมดุลกับ border */
        }
        
        .menu-inactive {
            color: #4b5563; /* text-gray-600 */
            font-weight: 500;
            border-left: 4px solid transparent;
        }
        
        .menu-inactive:hover {
            background-color: #f9fafb; /* hover:bg-gray-50 */
            color: #111827; /* hover:text-gray-900 */
            transform: translateX(4px); /* เลื่อนขวานิดๆ ตอนโฮเวอร์ */
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

            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">🕒 ประวัติรายการแจ้ง</h1>
                    <p class="text-gray-500 text-sm">รายการที่คุณเคยทำเรื่องขออนุมัติไว้</p>
                </div>
                        <!--รวมจำนวนแถว-->
                <span class="bg-white border px-3 py-1 rounded-full text-xs font-bold text-gray-600 shadow-sm">Total: <?php echo count($myRequests); ?></span>
               
            </div>
            

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase font-semibold border-b border-gray-200 text-xs">
                            <tr>
                                <th class="px-6 py-4">วันที่แจ้ง</th>
                                <th class="px-6 py-4">ลูกค้า</th>
                                <th class="px-6 py-4">รายละเอียด</th>
                                <th class="px-6 py-4 text-center">หัวหน้า</th>
                                <th class="px-6 py-4 text-center">ผู้จัดการ</th>
                                <th class="px-6 py-4 text-center">เปลี่ยนเบอร์</th> <th class="px-6 py-4 text-center">ตรวจสอบ IT</th> </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php if (empty($myRequests)): ?>
                                <tr><td colspan="7" class="px-6 py-16 text-center text-gray-400 italic">ไม่พบประวัติการรายการ</td></tr>
                            <?php else: ?>
                                <?php foreach ($myRequests as $req): 
                                    $sHead = $req[6] ?? 'Pending';
                                    $sManager = $req[7] ?? 'Pending';
                                    $empItStatus = $req[8] ?? ''; // Column I: Employee_it_Status
                                    $itStatus = $req[9] ?? '';    // Column J: IT_Status
                                    
                                    if ($sHead == 'Rejected') $sManager = '-';
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500"><?php echo date('d/m/y H:i', strtotime($req[0])); ?></td>
                                    <td class="px-6 py-4 font-bold"><?php echo $req[2]; ?></td>
                                    <td class="px-6 py-4 text-xs">
                                        <div class="flex items-center">
                                            <span class="text-red-400 line-through mr-1"><?php echo $req[3]; ?></span>
                                            <i class="fa-solid fa-arrow-right text-gray-300 text-[10px]"></i>
                                            <span class="text-green-600 font-bold ml-1"><?php echo $req[4]; ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center"><?php echo getStatusBadge($sHead); ?></td>
                                    <td class="px-6 py-4 text-center"><?php echo ($sManager == '-') ? '-' : getStatusBadge($sManager); ?></td>
                                    
                                    <td class="px-6 py-4 text-center">
                                        <?php 
                                            if ($empItStatus == 'Approved') echo '<span class="text-green-600 font-bold text-xs border border-green-200 bg-green-50 px-2 py-0.5 rounded"><i class="fa-solid fa-circle-check mr-1"></i>เรียบร้อย</span>';
                                            elseif ($sHead == 'Approved' && $sManager == 'Approved') echo '<span class="text-blue-500 font-bold text-xs animate-pulse">กำลังดำเนินงาน</span>';
                                            elseif ($sHead == 'Rejected' || $sManager == 'Rejected') echo '<span class="text-gray-400 text-xs">-</span>';
                                            else echo '<span class="text-gray-300 text-xs">-</span>';
                                        ?>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <?php 
                                            if ($itStatus == 'Approved') echo '<span class="text-purple-600 font-bold text-xs border border-purple-200 bg-purple-50 px-2 py-0.5 rounded"><i class="fa-solid fa-shield-check mr-1"></i>ตรวจแล้ว</span>';
                                            elseif ($empItStatus == 'Approved') echo '<span class="text-orange-400 text-xs italic">รอ IT ตรวจ</span>';
                                            else echo '<span class="text-gray-300 text-xs">-</span>';
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
function confirmLogout(event) {
    event.preventDefault(); // หยุดการทำงานของปุ่มไว้ก่อน
    
    Swal.fire({
        title: 'ออกจากระบบ?',
        text: "คุณต้องการออกจากระบบใช่หรือไม่?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444', // สีแดง (Tailwind red-500)
        cancelButtonColor: '#9ca3af',  // สีเทา (Tailwind gray-400)
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
            // ถ้ากดยืนยัน ค่อยเด้งไปหน้า logout.php
            window.location.href = 'logout.php';
        }
    });
}
</script>
 
</body>
</html>
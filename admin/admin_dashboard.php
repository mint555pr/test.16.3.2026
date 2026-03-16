<?php 
require '../db.php';

// 🔒 เช็คความปลอดภัย
if (!isset($_SESSION['user_name']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$myRole = $_SESSION['role'];

// ฟังก์ชันแปลงภาษาตำแหน่ง
function getRoleThai($r) {
    return match($r) {
        'Admin' => 'แอดมิน',
        'Head' => 'หัวหน้าบริษัท',
        'Head IT' => 'หัวหน้า IT',
        'Manager' => 'ผู้จัดการ',
        'Employee' => 'พนักงาน',
        default => $r
    };
}
$myRoleThai = getRoleThai($myRole);

// --- รับค่า Filter จาก URL (GET) ---
$searchQuery = $_GET['q'] ?? '';
$filterComp = $_GET['company'] ?? 'all';
$filterRole = $_GET['role'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; 

// 📄 1. ดึงข้อมูล User ทั้งหมด
$users = [];
$stats = ['WCC'=>0, 'WCR'=>0, '4P'=>0, 'WT'=>0, 'Total'=>0]; 

try {
    $response = $service->spreadsheets_values->get($spreadsheetId, 'Users!A:G');
    $rows = $response->getValues();
    
    if (!empty($rows)) {
        foreach ($rows as $index => $row) {
            if ($index == 0) continue; 
            
            $name = $row[0] ?? '';
            $user = $row[1] ?? '';
            $role = $row[3] ?? '';
            $comp = isset($row[4]) ? strtoupper(trim($row[4])) : '-';
            $status = $row[6] ?? '';
            
            $stats['Total']++;
            if ($comp == 'WCC') $stats['WCC']++;
            elseif ($comp == 'WCR') $stats['WCR']++;
            elseif ($comp == '4P') $stats['4P']++;
            elseif ($comp == 'WT') $stats['WT']++;

            $isMatch = true;
            if ($filterComp !== 'all' && $comp !== $filterComp) $isMatch = false;
            if ($filterRole !== 'all') {
                $thaiRole = getRoleThai($role);
                if ($role !== $filterRole && $thaiRole !== $filterRole) $isMatch = false;
            }
            if ($searchQuery !== '') {
                $term = strtolower($searchQuery);
                if (!str_contains(strtolower($name), $term) && !str_contains(strtolower($user), $term)) $isMatch = false;
            }

            if ($isMatch) {
                // ✅ แก้ไขตรงนี้: เอา +1 ออก เพื่อไม่ให้ Index มันเบิ้ลกับในหน้า edit_user.php
                $row['original_index'] = $index; 
                $users[] = $row;
            }
        }
    }
} catch (Exception $e) { $users = []; }

// 🔔 2. หา Link แจ้งเตือน (เฉพาะงานที่ Manager อนุมัติ แต่ IT ยังไม่กด)
$notifyLink = 'requester_list.php'; // ค่าเริ่มต้น
try {
    $reqResponse = $service->spreadsheets_values->get($spreadsheetId, 'Requests!A:I'); // ดึงถึง Col I
    $reqRows = $reqResponse->getValues();
    if (!empty($reqRows)) {
        $reversedReqs = array_reverse($reqRows); 
        foreach($reversedReqs as $r) {
            $mgrStatus = $r[7] ?? ''; // Col H
            $empItStatus = $r[8] ?? ''; // Col I (Employee_it_Status)
            
            // ✅ เงื่อนไข: Manager ผ่านแล้ว และ IT ยังไม่ Approved
            if ($mgrStatus == 'Approved' && $empItStatus != 'Approved') {
                $targetUser = $r[1] ?? ''; 
                if ($targetUser) {
                    $notifyLink = "requester_detail.php?user=" . urlencode($targetUser);
                    break; 
                }
            }
        }
    }
} catch (Exception $e) {}

// --- Pagination Logic ---
$totalItems = count($users);
$totalPages = ceil($totalItems / $limit);
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
$offset = ($page - 1) * $limit;
$displayUsers = array_slice($users, $offset, $limit); 

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    <style> 
        body { font-family: 'Sarabun', sans-serif; } 
        .glass-effect { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .bell-ringing { animation: bell-ring 1s ease-in-out infinite; }
        @keyframes bell-ring { 0% { transform: rotate(0); } 10% { transform: rotate(30deg); } 30% { transform: rotate(-28deg); } 50% { transform: rotate(34deg); } 70% { transform: rotate(-32deg); } 90% { transform: rotate(30deg); } 100% { transform: rotate(0); } }
    </style>
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-900">

   <nav class="glass-effect shadow-sm border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-slate-800 rounded-xl flex items-center justify-center text-white shadow-lg shadow-slate-200">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <span class="text-lg font-bold text-slate-800 block leading-none">Admin</span>
                        
                    </div>
                </div>
                <div class="flex items-center space-x-6">
                    
                    <div class="relative cursor-pointer group mr-2" onclick="window.location.href='<?php echo $notifyLink; ?>'">
                        <i id="bell-icon" class="fa-solid fa-bell text-slate-400 text-xl group-hover:text-slate-600 transition"></i>
                        <span id="notify-badge" class="hidden absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full border-2 border-white shadow-sm animate-bounce">0</span>
                    </div>
                    
                    <div class="hidden md:flex flex-col text-right border-l border-slate-200 pl-6">
                        <span class="text-sm font-bold text-slate-700"><?php echo $_SESSION['user_name']; ?></span>
                        <span class="text-xs text-slate-400"><?php echo $myRoleThai; ?></span>
                    </div>
                     <a href="#" onclick="confirmLogout(event)" class="text-gray-400 hover:text-red-500 transition-colors duration-200 p-2 rounded-lg hover:bg-red-50">
                        <i class="fa-solid fa-power-off"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-800">ภาพรวมระบบ (Overview)</h1>
            <p class="text-slate-500 mt-1">สรุปข้อมูลพนักงานและสถานะบัญชีผู้ใช้งานล่าสุด</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <?php 
                function getLink($key, $val, $exclude = []) {
                    $params = $_GET;
                    $params[$key] = $val;
                    if($key != 'page') $params['page'] = 1;
                    return '?' . http_build_query($params);
                }
            ?>
            
            <a href="<?php echo getLink('company', 'all'); ?>" class="block bg-white p-5 rounded-2xl shadow-sm border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition duration-300 relative overflow-hidden group">
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">ทั้งหมด</p>
                        <h3 class="text-2xl font-bold text-slate-800 group-hover:text-slate-900"><?php echo $stats['Total']; ?></h3>
                    </div>
                    <div class="w-10 h-10 bg-slate-100 text-slate-600 rounded-lg flex items-center justify-center text-lg">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </a>

            <a href="<?php echo getLink('company', 'WCC'); ?>" class="block bg-white p-5 rounded-2xl shadow-sm border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition duration-300 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-20 h-20 bg-blue-50 rounded-full group-hover:bg-blue-100 transition"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">WCC</p>
                        <h3 class="text-2xl font-bold text-blue-600"><?php echo $stats['WCC']; ?></h3>
                    </div>
                    <div class="w-10 h-10 bg-blue-50 text-blue-500 rounded-lg flex items-center justify-center text-lg">
                        <i class="fa-solid fa-building"></i>
                    </div>
                </div>
            </a>

            <a href="<?php echo getLink('company', 'WCR'); ?>" class="block bg-white p-5 rounded-2xl shadow-sm border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition duration-300 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-20 h-20 bg-orange-50 rounded-full group-hover:bg-orange-100 transition"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">WCR</p>
                        <h3 class="text-2xl font-bold text-orange-600"><?php echo $stats['WCR']; ?></h3>
                    </div>
                    <div class="w-10 h-10 bg-orange-50 text-orange-500 rounded-lg flex items-center justify-center text-lg">
                        <i class="fa-solid fa-industry"></i>
                    </div>
                </div>
            </a>

            <a href="<?php echo getLink('company', '4P'); ?>" class="block bg-white p-5 rounded-2xl shadow-sm border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition duration-300 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-20 h-20 bg-purple-50 rounded-full group-hover:bg-purple-100 transition"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">4P</p>
                        <h3 class="text-2xl font-bold text-purple-600"><?php echo $stats['4P']; ?></h3>
                    </div>
                    <div class="w-10 h-10 bg-purple-50 text-purple-500 rounded-lg flex items-center justify-center text-lg">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                </div>
            </a>

            <a href="<?php echo getLink('company', 'WT'); ?>" class="block bg-white p-5 rounded-2xl shadow-sm border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition duration-300 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-20 h-20 bg-cyan-50 rounded-full group-hover:bg-cyan-100 transition"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">WT</p>
                        <h3 class="text-2xl font-bold text-cyan-600"><?php echo $stats['WT']; ?></h3>
                    </div>
                    <div class="w-10 h-10 bg-cyan-50 text-cyan-500 rounded-lg flex items-center justify-center text-lg">
                        <i class="fa-solid fa-network-wired"></i>
                    </div>
                </div>
            </a>
        </div>

        <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center">
            <i class="fa-solid fa-sliders text-slate-400 mr-2"></i> เมนูจัดการ (Management)
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
            <a href="requester_list.php" class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 hover:border-blue-300 hover:shadow-md transition group flex items-start gap-4">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center text-2xl group-hover:scale-110 transition">
                    <i class="fa-solid fa-address-book"></i>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-slate-800 group-hover:text-blue-600 transition">ดูรายการผู้แจ้ง</h3>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-300 group-hover:text-blue-400"></i>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Requester List & History</p>
                    <p class="text-xs text-slate-400 mt-1">ดูประวัติการแจ้งของพนักงานรายบุคคล</p>
                </div>
            </a>

            <a href="..\register.php" class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 hover:border-emerald-300 hover:shadow-md transition group flex items-start gap-4">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center text-2xl group-hover:scale-110 transition">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-slate-800 group-hover:text-emerald-600 transition">เพิ่มพนักงานใหม่</h3>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-300 group-hover:text-emerald-400"></i>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Register New Account</p>
                    <p class="text-xs text-slate-400 mt-1">สร้างบัญชีผู้ใช้งานใหม่เข้าระบบ</p>
                </div>
            </a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4 bg-white">
                <h3 class="font-bold text-slate-800 flex items-center">
                    <i class="fa-solid fa-list text-slate-400 mr-2"></i> รายชื่อผู้ใช้งาน
                </h3>

                <form id="filterForm" method="GET" class="flex flex-col md:flex-row gap-3 w-full md:w-auto items-center">
                    
                    <div class="flex bg-slate-100 p-1 rounded-lg overflow-x-auto">
                        <input type="hidden" name="company" id="companyInput" value="<?php echo htmlspecialchars($filterComp); ?>">
                        
                        <?php 
                        $comps = ['all'=>'ทั้งหมด', 'WCC'=>'WCC', 'WCR'=>'WCR', '4P'=>'4P', 'WT'=>'WT'];
                        foreach($comps as $val => $label): 
                            $active = ($filterComp == $val);
                            $cls = $active 
                                ? "bg-white text-slate-800 shadow-sm font-bold border border-slate-200" 
                                : "text-slate-500 hover:text-slate-700 font-medium";
                            if($active && $val!='all') {
                                if($val=='WCC') $cls = "bg-blue-600 text-white shadow-md font-bold";
                                if($val=='WCR') $cls = "bg-orange-600 text-white shadow-md font-bold";
                                if($val=='4P')  $cls = "bg-purple-600 text-white shadow-md font-bold";
                                if($val=='WT')  $cls = "bg-cyan-600 text-white shadow-md font-bold";
                            }
                        ?>
                        <button type="button" onclick="setCompany('<?php echo $val; ?>')" class="px-3 py-1.5 text-xs rounded-md transition-all whitespace-nowrap <?php echo $cls; ?>">
                            <?php echo $label; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <select name="role" onchange="document.getElementById('filterForm').submit()" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-2 focus:ring-blue-500 block p-2 transition outline-none cursor-pointer hover:bg-slate-100">
                        <option value="all" <?php echo $filterRole=='all'?'selected':''; ?>>ตำแหน่ง (ทั้งหมด)</option>
                        <option value="แอดมิน" <?php echo $filterRole=='แอดมิน'?'selected':''; ?>>แอดมิน</option>
                        <option value="พนักงาน" <?php echo $filterRole=='พนักงาน'?'selected':''; ?>>พนักงาน</option>
                        <option value="หัวหน้าบริษัท" <?php echo $filterRole=='หัวหน้าบริษัท'?'selected':''; ?>>หัวหน้าบริษัท</option>
                        <option value="หัวหน้า IT" <?php echo $filterRole=='หัวหน้า IT'?'selected':''; ?>>หัวหน้า IT</option>
                        <option value="ผู้จัดการ" <?php echo $filterRole=='ผู้จัดการ'?'selected':''; ?>>ผู้จัดการ</option>
                    </select>

                    <div class="relative w-full md:w-56">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </div>
                        <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                               class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-2 focus:ring-blue-500 focus:bg-white focus:border-blue-500 block pl-9 p-2 transition placeholder-slate-400" 
                               placeholder="ค้นหาชื่อ, Username..." onchange="document.getElementById('filterForm').submit()">
                    </div>
                </form>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 text-slate-500 text-xs font-semibold uppercase tracking-wider border-b border-slate-200">
                            <th class="py-4 px-6 pl-8">ชื่อ-นามสกุล</th>
                            <th class="py-4 px-6">Username</th>
                            <th class="py-4 px-6">ตำแหน่ง</th>
                            <th class="py-4 px-6">สังกัด</th>
                            <th class="py-4 px-6">สถานะการทำงาน</th>
                            <th class="py-4 px-6 text-right pr-8">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-600 text-sm divide-y divide-slate-100">
                        <?php if (empty($displayUsers)): ?>
                            <tr>
                                <td colspan="5" class="py-10 text-center text-slate-400">
                                    <i class="fa-regular fa-folder-open text-4xl mb-2 opacity-30"></i><br>
                                    ไม่พบข้อมูลผู้ใช้งาน
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($displayUsers as $row): 
                                    $name = htmlspecialchars($row[0] ?? '-');
                                    $userRole = $row[3] ?? '-';
                                    $userRoleThai = getRoleThai($userRole);
                                    $rowIndex = $row['original_index']; 
                                    
                                    $roleBadge = match($userRole) {
                                        'Admin' => 'bg-slate-800 text-white',
                                        'Manager' => 'bg-orange-100 text-orange-700 border border-orange-200',
                                        'Head' => 'bg-blue-100 text-blue-700 border border-blue-200',
                                        'Head IT' => 'bg-purple-100 text-purple-700 border border-purple-200',
                                        default => 'bg-green-100 text-green-700 border border-green-200'
                                    };
                                    $comp = $row[4] ?? '-';
                                    $compClass = match($comp) {
                                         'WCC' => 'text-blue-600 font-bold',
                                         'WCR' => 'text-orange-600 font-bold',
                                         '4P' => 'text-purple-600 font-bold',
                                         'WT' => 'text-cyan-600 font-bold',
                                         default => 'text-gray-500'
                                    };
                            ?>
                            <tr class="hover:bg-slate-50 transition duration-150 group">
                                <td class="py-4 px-6 pl-8">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center mr-3 text-slate-500 font-bold text-sm border border-slate-200 group-hover:bg-white group-hover:border-blue-200 group-hover:text-blue-600 transition">
                                            <?php echo strtoupper(mb_substr($name, 0, 1)); ?>
                                        </div>
                                        <span class="font-bold text-slate-700"><?php echo $name; ?></span>
                                    </div>
                                </td>
                                <td class="py-4 px-6 font-mono text-xs text-slate-500"><?php echo htmlspecialchars($row[1] ?? '-'); ?></td>
                                <td class="py-4 px-6"><span class="<?php echo $roleBadge; ?> py-1 px-2.5 rounded-md text-[11px] font-bold uppercase tracking-wide"><?php echo $userRoleThai; ?></span></td>
                                <td class="py-4 px-6 text-xs  <?php echo $compClass; ?>"><?php echo htmlspecialchars($comp); ?></td>
                                <td class="py-4 px-6 text-xs" ><?php $status = $row[6] ?? '';
                                        if($status === 'Active'){
                                            echo '<span class="px-2 py-1 bg-green-100 text-green-700 rounded">ทำงาน</span>';
                                        }else{
                                            echo '<span class="px-2 py-1 bg-red-100 text-red-700 rounded">ยกเลิกใช้งาน</span>';
                                        }
                                        ?>
                                </td>
                                <td class="py-4 px-6 pr-8 text-right relative">
                                    <button onclick="toggleMenu('menu-<?php echo $rowIndex; ?>')" class="w-8 h-8 rounded-full hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition flex items-center justify-center ml-auto">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <div id="menu-<?php echo $rowIndex; ?>" class="hidden absolute right-10 top-8 w-44 bg-white rounded-lg shadow-xl border border-slate-100 z-50 overflow-hidden text-left">
                                        <a href="edit_user.php?row=<?php echo $rowIndex; ?>" class="block px-4 py-2.5 text-sm hover:bg-blue-50 text-slate-600">แก้ไขข้อมูล</a>
                                        <!--<a href="delete_user.php?row=<?php echo $rowIndex; ?>" onclick="return confirm('ลบผู้ใช้นี้?');" class="block px-4 py-2.5 text-sm text-red-500 hover:bg-red-50">ลบผู้ใช้</a>
                                        -->
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($totalPages > 1): ?>
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
                <div class="text-xs text-slate-500">
                    หน้า <span class="font-bold text-slate-800"><?php echo $page; ?></span> จาก <?php echo $totalPages; ?> 
                    (พบ <?php echo $totalItems; ?> รายการ)
                </div>
                <div class="flex gap-1">
                    <?php 
                        $q = http_build_query(array_merge($_GET, ['page' => $page-1]));
                        if($page > 1): 
                    ?>
                    <a href="?<?php echo $q; ?>" class="px-3 py-1 bg-white border border-slate-300 rounded hover:bg-slate-100 text-xs text-slate-600 transition">
                        <i class="fa-solid fa-chevron-left mr-1"></i> ก่อนหน้า
                    </a>
                    <?php endif; ?>
                    
                    <?php 
                        $q = http_build_query(array_merge($_GET, ['page' => $page+1]));
                        if($page < $totalPages): 
                    ?>
                    <a href="?<?php echo $q; ?>" class="px-3 py-1 bg-white border border-slate-300 rounded hover:bg-slate-100 text-xs text-slate-600 transition">
                        ถัดไป <i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        function setCompany(comp) {
            document.getElementById('companyInput').value = comp;
            document.getElementById('filterForm').submit();
        }

        function checkNotifications() {
            fetch('../api_check_notify.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('notify-badge');
                    const bell = document.getElementById('bell-icon');
                    const currentCount = parseInt(data.count);
                    const lastCount = parseInt(localStorage.getItem('lastNotifyCount') || 0);

                    if (currentCount > 0) {
                        badge.innerText = currentCount;
                        badge.classList.remove('hidden');
                        if (currentCount > lastCount) {
                            bell.classList.add('bell-ringing');
                            // ✅ ลบคำสั่งเล่นเสียงออกแล้ว
                            setTimeout(() => bell.classList.remove('bell-ringing'), 3000);
                        }
                    } else {
                        badge.classList.add('hidden');
                    }
                    localStorage.setItem('lastNotifyCount', currentCount);
                })
                .catch(err => console.error('Notify Error:', err));
        }

        setInterval(checkNotifications, 10000);
        checkNotifications();

        function toggleMenu(menuId) {
            document.querySelectorAll('[id^="menu-"]').forEach(menu => { if(menu.id !== menuId) menu.classList.add('hidden'); });
            document.getElementById(menuId).classList.toggle('hidden');
        }
        window.onclick = function(event) {
            if (!event.target.closest('td.relative')) {
                document.querySelectorAll('[id^="menu-"]').forEach(menu => menu.classList.add('hidden'));
            }
        }
    </script>
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
                window.location.href = '../logout.php';
            }
        });
    }
    </script>
</body>
</html>
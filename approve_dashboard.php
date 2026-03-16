<?php 
require 'db.php'; 

if (!isset($_SESSION['user_name'])) { header("Location: login.php"); exit(); }

$myRole = $_SESSION['role'];
$myName = $_SESSION['user_name'];

// --- การตั้งค่า Pagination และ Filter ---
$currentTab = $_GET['tab'] ?? 'pending'; // pending, approved, rejected
$currentCompany = $_GET['company'] ?? 'all'; // all, WCC, WCR, 4P, WT
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$searchQuery = $_GET['q'] ?? '';
$limit = 25;

// --- 1. เตรียมข้อมูลบริษัท (Mapping User -> Company) ---
$userCompanyMap = [];
try {
    $uResponse = $service->spreadsheets_values->get($spreadsheetId, 'Users!A:E');
    $uRows = $uResponse->getValues();
    foreach ($uRows as $u) {
        $uName = $u[0] ?? '';
        $uComp = $u[4] ?? '-'; 
        if ($uName) $userCompanyMap[$uName] = $uComp;
    }
} catch (Exception $e) { }

// --- 2. ดึงข้อมูล Requests และแยกหมวดหมู่ ---
$lists = [
    'pending' => [],
    'approved' => [],
    'rejected' => []
];

try {
    $response = $service->spreadsheets_values->get($spreadsheetId, 'Requests!A:H');
    $rows = $response->getValues();
    
    if (!empty($rows)) {
        foreach ($rows as $index => $row) {
            if ($index == 0) continue; 

            $requester = $row[1] ?? '';
            $owner = $row[2] ?? '';
            $statusHead = $row[6] ?? 'Pending';
            $statusManager = $row[7] ?? 'Pending';
            $reqCompany = $userCompanyMap[$requester] ?? 'Unknown';

            if ($searchQuery !== '') {
                $searchText = strtolower($requester . $owner . $reqCompany);
                if (!str_contains($searchText, strtolower($searchQuery))) {
                    continue;
                }
            }

            $item = $row;
            $item['row_id'] = $index + 1; 
            $item['req_company'] = $reqCompany;
            $item['can_approve'] = true; 

            $bucket = '';
            
            if ($myRole == 'Head' || $myRole == 'Head IT') {
                if ($statusHead == 'Pending') $bucket = 'pending';
                elseif ($statusHead == 'Approved') $bucket = 'approved';
                elseif ($statusHead == 'Rejected') $bucket = 'rejected';
            } 
            elseif ($myRole == 'Manager') {
                if ($statusHead == 'Approved' && $statusManager == 'Pending') $bucket = 'pending';
                elseif ($statusManager == 'Approved') $bucket = 'approved';
                elseif ($statusManager == 'Rejected') $bucket = 'rejected';
                elseif ($statusHead == 'Rejected') $bucket = 'rejected'; 
            }

            if ($bucket) {
                array_unshift($lists[$bucket], $item);
            }
        }
    }
} catch(Exception $e) {}

// --- 3. กรองตามบริษัท ---
$filteredItems = [];
if ($currentCompany === 'all') {
    $filteredItems = $lists[$currentTab];
} else {
    foreach ($lists[$currentTab] as $item) {
        if ($item['req_company'] === $currentCompany) {
            $filteredItems[] = $item;
        }
    }
}

// --- 4. ตัดแบ่งหน้า ---
$totalItems = count($filteredItems);
$totalPages = ceil($totalItems / $limit);

if ($currentPage < 1) $currentPage = 1;
if ($currentPage > $totalPages && $totalPages > 0) $currentPage = $totalPages;

$offset = ($currentPage - 1) * $limit;
$displayItems = array_slice($filteredItems, $offset, $limit);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการรออนุมัติ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style> body { font-family: 'Sarabun', sans-serif; } </style>
</head>
<body class="bg-slate-50 min-h-screen py-8 px-4 font-sans">

    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-clipboard-check text-blue-600"></i> จัดการคำขออนุมัติ
                </h1>
                <p class="text-sm text-slate-500 mt-1">
                    สิทธิ์การใช้งาน: <span class="font-bold text-slate-700"><?php echo $myRole; ?></span>
                </p>
            </div>
            
            <div class="flex gap-2 w-full md:w-auto">
                <form method="GET" class="relative w-full md:w-72">
                    <input type="hidden" name="tab" value="<?php echo $currentTab; ?>">
                    <input type="hidden" name="company" value="<?php echo $currentCompany; ?>">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fa-solid fa-search"></i>
                    </span>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                        class="w-full pl-10 pr-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm shadow-sm transition" 
                        placeholder="ค้นหาชื่อ, เบอร์..." onchange="this.form.submit()">
                </form>
                <a href="dashboard.php" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm transition flex items-center shadow-sm whitespace-nowrap">
                    <i class="fa-solid fa-arrow-left mr-2"></i> กลับ Dashboard
                </a>
            </div>
        </div>

        <div class="bg-white rounded-t-xl border-b border-gray-200 px-4 pt-4 flex space-x-4 overflow-x-auto shadow-sm">
            <?php 
                $tabsDef = [
                    'pending' => ['label' => 'รออนุมัติ', 'icon' => 'fa-clock', 'color' => 'text-yellow-600', 'bg' => 'bg-yellow-100'],
                    'approved' => ['label' => 'อนุมัติแล้ว', 'icon' => 'fa-circle-check', 'color' => 'text-green-600', 'bg' => 'bg-green-100'],
                    'rejected' => ['label' => 'ไม่อนุมัติ', 'icon' => 'fa-circle-xmark', 'color' => 'text-red-600', 'bg' => 'bg-red-100'],
                ];
                
                foreach($tabsDef as $key => $def):
                    $isActive = ($currentTab == $key);
                    $baseClass = "pb-3 px-4 text-sm font-bold flex items-center border-b-2 transition whitespace-nowrap";
                    $activeClass = $isActive ? "border-blue-600 text-blue-600" : "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300";
                    $count = count($lists[$key]);
            ?>
            <a href="?tab=<?php echo $key; ?>&company=<?php echo $currentCompany; ?>&q=<?php echo urlencode($searchQuery); ?>" class="<?php echo $baseClass . ' ' . $activeClass; ?>">
                <i class="fa-solid <?php echo $def['icon']; ?> mr-2 <?php echo $isActive ? '' : $def['color']; ?>"></i>
                <?php echo $def['label']; ?>
                <span class="ml-2 px-2 py-0.5 rounded-full text-xs <?php echo $def['bg'] . ' ' . $def['color']; ?>">
                    <?php echo $count; ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="bg-gray-50 border-b border-gray-200 px-6 py-3 flex items-center gap-2 overflow-x-auto">
            <span class="text-xs font-bold text-gray-500 mr-2 uppercase tracking-wide">กรองสังกัด:</span>
            <?php
                $companies = ['all' => 'ทั้งหมด', 'WCC' => 'WCC', 'WCR' => 'WCR', '4P' => '4P', 'WT' => 'WT'];
                foreach ($companies as $code => $label):
                    $isCompActive = ($currentCompany == $code);
                    $compClass = $isCompActive 
                        ? 'bg-slate-700 text-white shadow-md' 
                        : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-100';
            ?>
            <a href="?tab=<?php echo $currentTab; ?>&company=<?php echo $code; ?>&q=<?php echo urlencode($searchQuery); ?>" 
                class="px-3 py-1.5 rounded-md text-xs font-bold transition whitespace-nowrap <?php echo $compClass; ?>">
                <?php echo $label; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="bg-white rounded-b-xl shadow-sm border border-gray-200 border-t-0 overflow-hidden min-h-[400px]">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 w-24">วันที่</th>
                            <th class="px-6 py-4">ผู้ขอ (Requester)</th>
                            <th class="px-6 py-4">สังกัด</th>
                            <th class="px-6 py-4">ลูกค้า (Owner)</th>
                            <th class="px-6 py-4">เบอร์เดิม <i class="fa-solid fa-arrow-right mx-1 text-gray-300"></i> ใหม่</th>
                            <?php if($currentTab == 'pending'): ?>
                            <th class="px-6 py-4 text-center w-32">จัดการ</th>
                            <?php else: ?>
                            <th class="px-6 py-4 text-center w-32">สถานะ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($displayItems)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-300">
                                        <i class="fa-regular fa-folder-open text-5xl mb-4"></i>
                                        <span class="text-sm font-medium text-gray-400 mt-2">ไม่พบรายการ</span>
                                        <span class="text-xs text-gray-300">ลองเปลี่ยนตัวกรองบริษัทหรือค้นหาใหม่</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($displayItems as $req): 
                                $reqComp = $req['req_company'];
                                $compClass = match($reqComp) {
                                    'WCC' => 'text-blue-700 bg-blue-50 border-blue-100',
                                    'WCR' => 'text-orange-700 bg-orange-50 border-orange-100',
                                    '4P' => 'text-purple-700 bg-purple-50 border-purple-100',
                                    'WT' => 'text-cyan-700 bg-cyan-50 border-cyan-100',
                                    default => 'text-gray-600 bg-gray-50 border-gray-100'
                                };
                            ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                <td class="px-6 py-4 text-gray-500 whitespace-nowrap text-xs">
                                    <?php echo date('d/m/y', strtotime($req[0])); ?>
                                    <div class="text-[10px] opacity-70"><?php echo date('H:i', strtotime($req[0])); ?></div>
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-800">
                                    <?php echo htmlspecialchars($req[1]); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded border text-[10px] font-bold <?php echo $compClass; ?>">
                                        <?php echo htmlspecialchars($reqComp); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600 font-bold">
                                    <?php echo htmlspecialchars($req[2]); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-red-400 line-through text-xs"><?php echo htmlspecialchars($req[3]); ?></span>
                                        <span class="text-green-600 font-bold"><?php echo htmlspecialchars($req[4]); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($currentTab == 'pending'): ?>
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button" onclick="confirmAction('approve', <?php echo $req['row_id']; ?>, '<?php echo addslashes($req[2]); ?>')" 
                                                class="w-8 h-8 rounded-full bg-green-100 hover:bg-green-600 text-green-600 hover:text-white flex items-center justify-center transition shadow-sm" title="อนุมัติ">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            
                                            <button type="button" onclick="confirmAction('reject', <?php echo $req['row_id']; ?>, '<?php echo addslashes($req[2]); ?>')" 
                                                class="w-8 h-8 rounded-full bg-red-100 hover:bg-red-500 text-red-500 hover:text-white flex items-center justify-center transition shadow-sm" title="ปฏิเสธ">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <?php if($currentTab == 'approved'): ?>
                                            <span class="text-green-600 text-xs font-bold border border-green-200 bg-green-50 px-2 py-1 rounded"><i class="fa-solid fa-check-circle mr-1"></i>อนุมัติ</span>
                                        <?php else: ?>
                                            <span class="text-red-500 text-xs font-bold border border-red-200 bg-red-50 px-2 py-1 rounded"><i class="fa-solid fa-times-circle mr-1"></i>ปฏิเสธ</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($totalPages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    หน้า <span class="font-bold text-gray-700"><?php echo $currentPage; ?></span> จาก <?php echo $totalPages; ?> 
                    (ทั้งหมด <?php echo $totalItems; ?> รายการ)
                </div>
                <div class="flex items-center space-x-1">
                    <?php $linkParams = "&tab=$currentTab&company=$currentCompany&q=".urlencode($searchQuery); ?>
                    
                    <?php if($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage-1; ?><?php echo $linkParams; ?>" class="px-3 py-1 bg-white border border-gray-300 rounded hover:bg-gray-100 text-xs text-gray-600 transition">
                        <i class="fa-solid fa-chevron-left mr-1"></i> ก่อนหน้า
                    </a>
                    <?php endif; ?>
                    
                    <?php if($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage+1; ?><?php echo $linkParams; ?>" class="px-3 py-1 bg-white border border-gray-300 rounded hover:bg-gray-100 text-xs text-gray-600 transition">
                        ถัดไป <i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function confirmAction(actionType, rowId, ownerName) {
        // เตรียมข้อมูล ป๊อปอัป ตาม Action (Approve หรือ Reject)
        const isApprove = actionType === 'approve';
        const title = isApprove ? 'ยืนยันการอนุมัติ?' : 'ยืนยันการปฏิเสธ?';
        const text = isApprove 
            ? `คุณต้องการอนุมัติการเปลี่ยนเบอร์ของ <b>${ownerName}</b> ใช่หรือไม่?` 
            : `คุณต้องการปฏิเสธการเปลี่ยนเบอร์ของ <b>${ownerName}</b> ใช่หรือไม่?`;
        const icon = isApprove ? 'question' : 'warning';
        const btnColor = isApprove ? '#16a34a' : '#ef4444'; // เขียว หรือ แดง
        const btnText = isApprove ? '<i class="fa-solid fa-check mr-1"></i> อนุมัติ' : '<i class="fa-solid fa-xmark mr-1"></i> ปฏิเสธ';

        Swal.fire({
            title: title,
            html: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: btnColor,
            cancelButtonColor: '#9ca3af',
            confirmButtonText: btnText,
            cancelButtonText: 'ยกเลิก',
            customClass: {
                title: 'font-bold text-gray-800 font-sans',
                popup: 'rounded-2xl shadow-xl border border-gray-100'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // ถ้ากดยืนยัน ให้เด้งไปหน้า approve_action.php พร้อมส่งค่า (เหมือนกับการกด Link แบบเดิมเป๊ะ)
                window.location.href = `approve_action.php?action=${actionType}&row=${rowId}&role=<?php echo urlencode($myRole); ?>`;
            }
        });
    }
    </script>
</body>
</html>
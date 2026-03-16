<?php 
require 'db.php';

// ตรวจสอบสิทธิ์ Head IT
if (!isset($_SESSION['user_name']) || $_SESSION['role'] !== 'Head IT') {
    header("Location: login.php");
    exit();
}

$myName = $_SESSION['user_name'];

// --- Handle Form Submit (กดปุ่มยืนยัน IT) ---
if (isset($_POST['mark_it_done'])) {
    $rowId = $_POST['row_id'];
    try {
        $range = "Requests!J{$rowId}";
        $body = new \Google\Service\Sheets\ValueRange(['values' => [['Approved']]]);
        $params = ['valueInputOption' => 'RAW'];
        $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
        
        $qs = $_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : '';
        
        // แจ้งเตือนแบบ SweetAlert ภายใน PHP
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: 'บันทึกการตรวจสอบเรียบร้อยแล้ว',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500,
                    customClass: {
                        title: 'font-bold text-slate-800 font-sans',
                        popup: 'rounded-2xl shadow-xl border border-slate-100'
                    }
                }).then(() => {
                    window.location.href = 'head_it_dashboard.php{$qs}';
                });
            });
        </script>";
        exit();
    } catch (Exception $e) { echo "<script>alert('Error: ".$e->getMessage()."');</script>"; }
}

// --- การตั้งค่า Filter & Pagination ---
$currentTab = $_GET['tab'] ?? 'pending'; 
$currentCompany = $_GET['company'] ?? 'all'; 
$searchQuery = $_GET['q'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 25;

// 1. เตรียมข้อมูลบริษัท (Mapping User -> Company)
$userCompanyMap = [];
try {
    $uRes = $service->spreadsheets_values->get($spreadsheetId, 'Users!A:E');
    $uRows = $uRes->getValues();
    foreach ($uRows as $k => $r) {
        if ($k == 0) continue;
        if (isset($r[0])) $userCompanyMap[$r[0]] = $r[4] ?? '-';
    }
} catch (Exception $e) {}

// 2. ดึงข้อมูลและกรอง
$allItems = [];
try {
    $response = $service->spreadsheets_values->get($spreadsheetId, 'Requests!A:J');
    $rows = $response->getValues();
    
    if (!empty($rows)) {
        $reversedRows = array_reverse($rows); 
        $tempItems = [];
        foreach ($rows as $index => $row) {
            if ($index == 0) continue;
            
            $requester = $row[1] ?? '';
            $owner = $row[2] ?? '';
            $finalStatus = $row[8] ?? ''; // Col I: Employee_it_Status
            $itStatus = $row[9] ?? '';    // Col J: IT_Status
            $comp = $userCompanyMap[$requester] ?? 'Unknown';

            if ($finalStatus == 'Approved') {
                $item = $row;
                $item['row_index'] = $index + 1;
                $item['company'] = $comp;
                $item['it_status'] = $itStatus;

                if ($currentTab == 'pending' && $itStatus == 'Approved') continue;
                if ($currentTab == 'history' && $itStatus != 'Approved') continue;

                if ($currentCompany != 'all' && $comp != $currentCompany) continue;

                if ($searchQuery != '') {
                    $searchStr = strtolower($requester . $owner . ($row[4]??''));
                    if (!str_contains($searchStr, strtolower($searchQuery))) continue;
                }

                $tempItems[] = $item;
            }
        }
        
        usort($tempItems, function($a, $b) use ($currentTab) {
            $tA = strtotime($a[0]); $tB = strtotime($b[0]);
            return ($currentTab == 'pending') ? $tA - $tB : $tB - $tA;
        });
        
        $allItems = $tempItems;
    }
} catch (Exception $e) { }

// 3. Pagination Logic
$totalItems = count($allItems);
$totalPages = ceil($totalItems / $limit);
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
$offset = ($page - 1) * $limit;
$displayItems = array_slice($allItems, $offset, $limit);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Head IT Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style> 
        body { font-family: 'Sarabun', sans-serif; } 
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .btn-smooth { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-smooth:active { transform: scale(0.95); }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900 font-sans">

    <nav class="bg-white shadow-sm border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center text-white shadow-lg shadow-purple-200">
                    <i class="fa-solid fa-server"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-slate-800 leading-none">Head IT</h1>
                    <span class="text-[10px] text-slate-500 font-medium">Management Console</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="hidden md:flex flex-col text-right">
                    <span class="text-xs font-bold text-slate-700"><?php echo $myName; ?></span>
                    <span class="text-[10px] text-slate-400">Head IT</span>
                </div>
                <a href="#" onclick="confirmLogout(event)" class="btn-smooth w-8 h-8 rounded-full bg-slate-100 hover:bg-red-50 text-slate-400 hover:text-red-500 flex items-center justify-center transition">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-8 px-4">
        
        <div class="flex flex-col md:flex-row justify-between items-end gap-4 mb-6">
            
            <div class="flex bg-white p-1 rounded-xl shadow-sm border border-slate-200">
                <a href="?tab=pending&company=<?php echo $currentCompany; ?>&q=<?php echo urlencode($searchQuery); ?>" 
                   class="btn-smooth px-5 py-2 rounded-lg text-sm font-bold transition flex items-center gap-2 <?php echo $currentTab=='pending' ? 'bg-purple-600 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <i class="fa-solid fa-clock"></i> รอตรวจสอบ
                    <?php if($currentTab=='pending'): ?><span class="bg-white/20 px-1.5 rounded text-xs"><?php echo $totalItems; ?></span><?php endif; ?>
                </a>
                <a href="?tab=history&company=<?php echo $currentCompany; ?>&q=<?php echo urlencode($searchQuery); ?>" 
                   class="btn-smooth px-5 py-2 rounded-lg text-sm font-bold transition flex items-center gap-2 <?php echo $currentTab=='history' ? 'bg-slate-700 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <i class="fa-solid fa-history"></i> ประวัติ
                </a>
            </div>

            <div class="flex flex-col md:flex-row gap-2 w-full md:w-auto">
                <div class="flex bg-slate-100 p-1 rounded-lg overflow-x-auto scrollbar-hide">
                    <?php 
                    $comps = ['all'=>'ทั้งหมด', 'WCC'=>'WCC', 'WCR'=>'WCR', '4P'=>'4P', 'WT'=>'WT'];
                    foreach($comps as $k=>$v): 
                        $isActive = ($currentCompany == $k);
                        $cls = $isActive ? 'bg-white text-slate-800 shadow-sm font-bold' : 'text-slate-500 hover:text-slate-700 font-medium';
                    ?>
                    <a href="?tab=<?php echo $currentTab; ?>&company=<?php echo $k; ?>&q=<?php echo urlencode($searchQuery); ?>" 
                        class="btn-smooth px-3 py-1.5 text-xs rounded-md transition whitespace-nowrap <?php echo $cls; ?>">
                        <?php echo $v; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <form class="relative w-full md:w-64">
                    <input type="hidden" name="tab" value="<?php echo $currentTab; ?>">
                    <input type="hidden" name="company" value="<?php echo $currentCompany; ?>">
                    <i class="fa-solid fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                           class="w-full pl-8 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 outline-none transition-shadow" 
                           placeholder="ค้นหาลูกค้า, ผู้แจ้ง...">
                </form>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden min-h-[400px]">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 uppercase font-semibold text-xs border-b">
                        <tr>
                            <th class="px-6 py-4">วันที่</th>
                            <th class="px-6 py-4">ผู้แจ้ง</th>
                            <th class="px-6 py-4">สังกัด</th>
                            <th class="px-6 py-4">ลูกค้า (Owner)</th>
                            <th class="px-6 py-4">เบอร์ใหม่</th>
                            <th class="px-6 py-4 text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($displayItems)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center text-slate-300">
                                        <i class="fa-regular fa-folder-open text-5xl mb-4 opacity-50"></i>
                                        <span class="text-sm">ไม่พบข้อมูลในหมวดนี้</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($displayItems as $row): 
                                $compClass = match($row['company']) {
                                    'WCC' => 'text-blue-700 bg-blue-50 border-blue-100',
                                    'WCR' => 'text-orange-700 bg-orange-50 border-orange-100',
                                    '4P' => 'text-purple-700 bg-purple-50 border-purple-100',
                                    'WT' => 'text-cyan-700 bg-cyan-50 border-cyan-100',
                                    default => 'text-slate-600 bg-slate-50 border-slate-100'
                                };
                            ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-slate-500 text-xs">
                                    <?php echo date('d/m/y', strtotime($row[0])); ?>
                                    <div class="opacity-50"><?php echo date('H:i', strtotime($row[0])); ?></div>
                                </td>
                                <td class="px-6 py-4 font-bold text-slate-800">
                                    <?php echo $row[1]; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 rounded border text-[10px] font-bold <?php echo $compClass; ?>">
                                        <?php echo $row['company']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-600 font-medium">
                                    <?php echo $row[2]; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-green-600 font-bold font-mono"><?php echo $row[4]; ?></span>
                                        <span class="text-[10px] text-slate-400">จาก: <?php echo $row[3]; ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($currentTab == 'pending'): ?>
                                        <form method="POST" onsubmit="confirmSubmit(event, 'ยืนยันการตรวจสอบ?', 'คุณยืนยันว่าตรวจสอบเรียบร้อยแล้วใช่หรือไม่? (พนักงานจะได้เบอร์นี้)', '#9333ea')">
                                            <input type="hidden" name="row_id" value="<?php echo $row['row_index']; ?>">
                                            <button type="submit" name="mark_it_done" class="btn-smooth bg-purple-600 hover:bg-purple-700 text-white text-xs px-4 py-1.5 rounded-lg shadow-sm transition flex items-center justify-center mx-auto hover:shadow-purple-200">
                                                <i class="fa-solid fa-check-double mr-1.5"></i> ตรวจสอบ
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">
                                            <i class="fa-solid fa-check mr-1"></i> Checked
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($totalPages > 1): ?>
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
                <span class="text-xs text-slate-500">
                    หน้า <strong><?php echo $page; ?></strong> / <?php echo $totalPages; ?>
                </span>
                <div class="flex gap-1">
                    <?php 
                    $qs = "&tab=$currentTab&company=$currentCompany&q=".urlencode($searchQuery);
                    if($page > 1): ?>
                    <a href="?page=<?php echo $page-1 . $qs; ?>" class="btn-smooth w-8 h-8 flex items-center justify-center rounded bg-white border border-slate-300 text-slate-600 hover:bg-slate-100 transition"><i class="fa-solid fa-chevron-left text-xs"></i></a>
                    <?php endif; ?>
                    
                    <?php if($page < $totalPages): ?>
                    <a href="?page=<?php echo $page+1 . $qs; ?>" class="btn-smooth w-8 h-8 flex items-center justify-center rounded bg-white border border-slate-300 text-slate-600 hover:bg-slate-100 transition"><i class="fa-solid fa-chevron-right text-xs"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ฟังก์ชัน Logout
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
                customClass: { title: 'font-bold text-slate-800 font-sans', popup: 'rounded-2xl shadow-xl border border-slate-100' }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        // ฟังก์ชันยืนยันการตรวจสอบ (ปุ่มสีม่วง)
        function confirmSubmit(event, title, text, confirmColor) {
            event.preventDefault(); // หยุดการส่งฟอร์มไว้ก่อน
            const form = event.target;
            
            Swal.fire({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: confirmColor,
                cancelButtonColor: '#9ca3af',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก',
                customClass: {
                    title: 'font-bold text-slate-800 font-sans',
                    popup: 'rounded-2xl shadow-xl border border-slate-100',
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // สร้าง input ซ่อนไว้ให้รู้ว่ากดปุ่มไหน
                    const btn = form.querySelector('button[type="submit"]');
                    if (btn && btn.name) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = btn.name;
                        hiddenInput.value = '1';
                        form.appendChild(hiddenInput);
                    }
                    form.submit(); // ส่งฟอร์มเพื่อรัน PHP
                }
            });
        }
    </script>
</body>
</html>
<?php 
require '../db.php';

if (!isset($_SESSION['user_name'])) { header("Location: login.php"); exit(); }

// 1. ดึงข้อมูล Users เพื่อจับคู่ "ชื่อ -> บริษัท"
$userCompanyMap = [];
try {
    $uRes = $service->spreadsheets_values->get($spreadsheetId, 'Users!A:E');
    $uRows = $uRes->getValues();
    foreach ($uRows as $k => $r) {
        if ($k == 0) continue;
        if (isset($r[0])) $userCompanyMap[$r[0]] = $r[4] ?? '-';
    }
} catch (Exception $e) {}

$requesters = [];

// 2. ดึงข้อมูล Requests มาคำนวณ
try {
    $response = $service->spreadsheets_values->get($spreadsheetId, 'Requests!A:H');
    $rows = $response->getValues();
    
    if (!empty($rows)) {
        foreach ($rows as $index => $row) {
            if ($index == 0) continue; 
            
            $username = $row[1] ?? 'Unknown'; 
            $sHead = $row[6] ?? 'Pending';
            $sManager = $row[7] ?? 'Pending';
            
            $comp = $userCompanyMap[$username] ?? 'Unknown';

            if (!isset($requesters[$username])) {
                $requesters[$username] = [
                    'name' => $username,
                    'company' => $comp, 
                    'total' => 0,
                    'wait_head' => 0,
                    'wait_mgr' => 0,
                    'success' => 0,
                    'rejected' => 0,
                    'last_date' => $row[0]
                ];
            }
            
            $requesters[$username]['total']++;
            
            if ($sHead == 'Rejected' || $sManager == 'Rejected') {
                $requesters[$username]['rejected']++;
            } elseif ($sHead == 'Pending') {
                $requesters[$username]['wait_head']++;
            } elseif ($sHead == 'Approved' && $sManager == 'Pending') {
                $requesters[$username]['wait_mgr']++;
            } elseif ($sManager == 'Approved') {
                $requesters[$username]['success']++;
            }

            if ($row[0] > $requesters[$username]['last_date']) {
                $requesters[$username]['last_date'] = $row[0];
            }
        }
        
        uasort($requesters, function($a, $b) {
            return strtotime($b['last_date']) - strtotime($a['last_date']);
        });
    }
} catch (Exception $e) { }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทำเนียบผู้แจ้ง | Admin Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    <style> 
        body { font-family: 'Sarabun', sans-serif; } 
        .glass-effect { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        @keyframes bell-ring { 0% { transform: rotate(0); } 10% { transform: rotate(30deg); } 30% { transform: rotate(-28deg); } 50% { transform: rotate(34deg); } 70% { transform: rotate(-32deg); } 90% { transform: rotate(30deg); } 100% { transform: rotate(0); } }
        .bell-ringing { animation: bell-ring 1s ease-in-out infinite; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">

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
                    <div class="relative cursor-pointer group">
                        <i id="bell-icon" class="fa-solid fa-bell text-slate-400 text-xl group-hover:text-slate-600 transition"></i>
                        <span id="notify-badge" class="hidden absolute -top-2 -right-2 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full border-2 border-white shadow-sm">0</span>
                    </div>
                    <div class="hidden md:flex flex-col text-right">
                        <span class="text-sm font-bold text-slate-700"><?php echo $_SESSION['user_name']; ?></span>
                        <span class="text-xs text-slate-400"><?php echo $_SESSION['role']; ?></span>
                    </div>
                    <a href="#" onclick="confirmLogout(event)" class="text-gray-400 hover:text-red-500 transition-colors duration-200 p-2 rounded-lg hover:bg-red-50">
                        
                        <i class="fa-solid fa-power-off"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row justify-between items-end mb-8 gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-address-book text-blue-600"></i> ดูรายการผู้แจ้ง
                </h1>
                <p class="text-slate-500 mt-1 text-sm">ตรวจสอบสถานะการแจ้งงานของพนักงานแต่ละคน</p>
            </div>
            
            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto items-center">
                
                <div class="flex bg-slate-100 p-1 rounded-lg overflow-x-auto scrollbar-hide max-w-full">
                    <button onclick="filterItems('all')" id="btn-all" class="px-3 py-1.5 text-xs font-bold rounded-md bg-white text-slate-800 shadow-sm transition whitespace-nowrap">ทั้งหมด</button>
                    <button onclick="filterItems('WCC')" id="btn-WCC" class="px-3 py-1.5 text-xs font-medium rounded-md text-slate-500 hover:text-slate-700 transition whitespace-nowrap">WCC</button>
                    <button onclick="filterItems('WCR')" id="btn-WCR" class="px-3 py-1.5 text-xs font-medium rounded-md text-slate-500 hover:text-slate-700 transition whitespace-nowrap">WCR</button>
                    <button onclick="filterItems('4P')" id="btn-4P" class="px-3 py-1.5 text-xs font-medium rounded-md text-slate-500 hover:text-slate-700 transition whitespace-nowrap">4P</button>
                    <button onclick="filterItems('WT')" id="btn-WT" class="px-3 py-1.5 text-xs font-medium rounded-md text-slate-500 hover:text-slate-700 transition whitespace-nowrap">WT</button>
                </div>

                <div class="relative w-full md:w-auto">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                    <input type="text" id="searchInput" onkeyup="searchItems()" placeholder="ค้นหาชื่อ..." 
                           class="pl-8 pr-4 py-1.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none w-full md:w-48">
                </div>
                <a href="admin_dashboard.php" class="bg-slate-800 text-white px-4 py-1.5 rounded-lg text-sm font-medium hover:bg-slate-900 transition flex items-center justify-center whitespace-nowrap">
                    กลับหน้าหลัก
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="cardGrid">
            <?php if (empty($requesters)): ?>
                <div class="col-span-full text-center py-20 text-slate-400">
                    <i class="fa-regular fa-folder-open text-4xl mb-3 opacity-50"></i>
                    <p>ยังไม่มีข้อมูลการแจ้งในระบบ</p>
                </div>
            <?php else: ?>
                <?php foreach ($requesters as $user): 
                    $compClass = match($user['company']) {
                        'WCC' => 'bg-blue-100 text-blue-700',
                        'WCR' => 'bg-orange-100 text-orange-700',
                        '4P' => 'bg-purple-100 text-purple-700',
                        'WT' => 'bg-cyan-100 text-cyan-700',
                        default => 'bg-slate-100 text-slate-500'
                    };
                ?>
                <a href="requester_detail.php?user=<?php echo urlencode($user['name']); ?>" 
                   class="requester-card block group" 
                   data-company="<?php echo $user['company']; ?>" 
                   data-name="<?php echo strtolower($user['name']); ?>">
                    
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 hover:border-blue-400 hover:shadow-md transition duration-200 h-full flex flex-col justify-between">
                        
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h3 class="font-bold text-lg text-slate-800 group-hover:text-blue-600 transition truncate">
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </h3>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded mt-1 inline-block <?php echo $compClass; ?>">
                                    <?php echo $user['company']; ?>
                                </span>
                            </div>
                            <div class="text-xs text-slate-400 text-right">
                                <p>ล่าสุด</p>
                                <p class="font-mono"><?php echo date('d/m/y', strtotime($user['last_date'])); ?></p>
                            </div>
                        </div>

                        <div class="space-y-2 mt-2">
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-slate-500"><i class="fa-solid fa-user-tie mr-1.5 w-4 text-center"></i>รอ Head</span>
                                <span class="font-bold <?php echo $user['wait_head'] > 0 ? 'text-orange-500' : 'text-slate-300'; ?>">
                                    <?php echo $user['wait_head']; ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-slate-500"><i class="fa-solid fa-briefcase mr-1.5 w-4 text-center"></i>รอ Manager</span>
                                <span class="font-bold <?php echo $user['wait_mgr'] > 0 ? 'text-blue-500' : 'text-slate-300'; ?>">
                                    <?php echo $user['wait_mgr']; ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-slate-500"><i class="fa-solid fa-check-circle mr-1.5 w-4 text-center"></i>สำเร็จ</span>
                                <span class="font-bold <?php echo $user['success'] > 0 ? 'text-green-600' : 'text-slate-300'; ?>">
                                    <?php echo $user['success']; ?>
                                </span>
                            </div>
                             <div class="flex justify-between items-center text-xs">
                                <span class="text-slate-500"><i class="fa-solid fa-times-circle mr-1.5 w-4 text-center"></i>ไม่ผ่าน</span>
                                <span class="font-bold <?php echo $user['rejected'] > 0 ? 'text-red-500' : 'text-slate-300'; ?>">
                                    <?php echo $user['rejected']; ?>
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-100 flex justify-between items-center">
                            <span class="text-xs text-slate-400">รวมทั้งหมด</span>
                            <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-xs font-bold group-hover:bg-blue-100 group-hover:text-blue-700 transition">
                                <?php echo $user['total']; ?> รายการ
                            </span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <audio id="notifySound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>
    <script>
        let currentFilter = 'all';
        function filterItems(comp) {
            currentFilter = comp;
            const buttons = ['all', 'WCC', 'WCR', '4P', 'WT'];
            buttons.forEach(id => {
                const btn = document.getElementById('btn-' + id);
                if (btn) btn.className = "px-3 py-1.5 text-xs font-medium rounded-md text-slate-500 hover:text-slate-700 transition whitespace-nowrap";
            });
            const activeBtn = document.getElementById('btn-' + comp);
            if (activeBtn) {
                let activeClass = "px-3 py-1.5 text-xs font-bold rounded-md shadow-sm transition whitespace-nowrap ";
                if(comp === 'all') activeClass += "bg-white text-slate-800 ring-1 ring-slate-200";
                else if(comp === 'WCC') activeClass += "bg-blue-600 text-white";
                else if(comp === 'WCR') activeClass += "bg-orange-600 text-white";
                else if(comp === '4P') activeClass += "bg-purple-600 text-white";
                else if(comp === 'WT') activeClass += "bg-cyan-600 text-white";
                activeBtn.className = activeClass;
            }
            applyFilter();
        }
        function searchItems() { applyFilter(); }
        function applyFilter() {
            const searchVal = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.requester-card');
            cards.forEach(card => {
                const comp = card.getAttribute('data-company');
                const name = card.getAttribute('data-name');
                const matchComp = (currentFilter === 'all') || (comp === currentFilter);
                const matchName = name.includes(searchVal);
                if (matchComp && matchName) {
                    card.style.display = 'block';
                    card.classList.add('animate-fade-in'); 
                } else {
                    card.style.display = 'none';
                }
            });
        }
        function checkNotifications() {
            fetch('api_check_notify.php').then(r=>r.json()).then(d=>{
                const b=document.getElementById('notify-badge'), i=document.getElementById('bell-icon');
                const c=parseInt(d.count), l=parseInt(localStorage.getItem('lnc')||0);
                if(c>0){
                    b.innerText=c; b.classList.remove('hidden');
                    if(c>l){ i.classList.add('bell-ringing'); document.getElementById('notifySound').play().catch(()=>{}); setTimeout(()=>i.classList.remove('bell-ringing'),3000); }
                }else{ b.classList.add('hidden'); }
                localStorage.setItem('lnc',c);
            }).catch(()=>{});
        }
        setInterval(checkNotifications,10000); checkNotifications();
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
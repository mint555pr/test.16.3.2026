<?php 
require '../db.php';

if (!isset($_SESSION['user_name'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['user'])) { header("Location: requester_list.php"); exit(); }

$targetUser = $_GET['user'];
$myRole = $_SESSION['role']; 


// --- Handle Form Submit (กดปุ่มจบงาน - เปลี่ยนสถานะเป็น Approved) ---
if (isset($_POST['mark_done'])) {
    $rowId = $_POST['row_id'];
    try {
        $range = "Requests!I{$rowId}"; // Column I: Employee_it_Status
        $body = new \Google\Service\Sheets\ValueRange(['values' => [['Approved']]]);
        $params = ['valueInputOption' => 'RAW'];
        $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
        header("Location: requester_detail.php?user=" . urlencode($targetUser));
        exit();
    } catch (Exception $e) { echo "<script>alert('Error: ".$e->getMessage()."');</script>"; }
}

// --- Handle Form Submit (กดปุ่ม Head IT - เปลี่ยนสถานะเป็น Approved) ---
if (isset($_POST['mark_it_done'])) {
    if ($myRole !== 'Head IT') {
        echo "<script>alert('คุณไม่มีสิทธิ์ดำเนินการนี้');</script>";
    } else {
        $rowId = $_POST['row_id'];
        try {
            $range = "Requests!J{$rowId}"; // Column J: IT_Status
            $body = new \Google\Service\Sheets\ValueRange(['values' => [['Approved']]]);
            $params = ['valueInputOption' => 'RAW'];
            $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
            header("Location: requester_detail.php?user=" . urlencode($targetUser));
            exit();
        } catch (Exception $e) { echo "<script>alert('Error: ".$e->getMessage()."');</script>"; }
    }
}

$userHistory = [];
try {
    $response = $service->spreadsheets_values->get($spreadsheetId, 'Requests!A:J');
    $rows = $response->getValues();
    
    if (!empty($rows)) {
        foreach ($rows as $index => $row) {
            if ($index == 0) continue;
            if (isset($row[1]) && $row[1] == $targetUser) {
                $row['row_index'] = $index + 1; 
                $userHistory[] = $row;
            }
        }
        usort($userHistory, function($a, $b) {
            return strtotime($b[0]) - strtotime($a[0]);
        });
    }
} catch (Exception $e) { }

function getBadge($status) {
    return match($status) {
        'Approved' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-200"><i class="fa-solid fa-check mr-1"></i>อนุมัติ</span>',
        'Rejected' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-700 border border-red-200"><i class="fa-solid fa-times mr-1"></i>ไม่อนุมัติ</span>',
        default => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-yellow-50 text-yellow-700 border border-yellow-200"><i class="fa-regular fa-clock mr-1"></i>รอดำเนินการ</span>'
    };
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติ: <?php echo htmlspecialchars($targetUser); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style> body { font-family: 'Sarabun', sans-serif; } .glass-effect { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); } </style>
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
                        <i class="fa-solid fa-bell text-slate-400 text-xl group-hover:text-slate-600 transition"></i>
                    </div>
                    <div class="hidden md:flex flex-col text-right">
                        <span class="text-sm font-bold text-slate-700"><?php echo $_SESSION['user_name']; ?></span>
                        <span class="text-xs text-slate-400"><?php echo $_SESSION['role']; ?></span>
                    </div>
                    <a href="../logout.php" onclick="confirmLogout(event)" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-red-50 text-slate-400 hover:text-red-500 flex items-center justify-center transition duration-200">
                        <i class="fa-solid fa-power-off"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">DETAIL VIEW</p>
                <h1 class="text-2xl font-bold text-slate-800">
                    ประวัติของ: <span class="text-blue-600" id="userNameForExport"><?php echo htmlspecialchars($targetUser); ?></span>
                </h1>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                    </div>
                    
                    <input type="text" id="searchInput" placeholder="ค้นหาชื่อลูกค้า..." class="w-full sm:w-64 pl-10 pr-4 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition shadow-sm">
                
                </div>

<div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
<select onchange="filterByRange(this.value)"
class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center justify-center whitespace-nowrap">

<option value="">กรองตามช่วงเวลา</option>
<option value="today">วันนี้</option>
<option value="7days">7 วันล่าสุด</option>
<option value="30days">30 วันล่าสุด</option>
<option value="month">เดือนนี้</option>
<option value="year">ปีนี้</option>
<option value="reset">แสดงทั้งหมด</option>

</select>

</div>
                <div class="relative inline-block text-left" id="exportDropdownContainer">
                    <button type="button" onclick="toggleExportMenu()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center justify-center whitespace-nowrap focus:outline-none">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> Export <i class="fa-solid fa-chevron-down ml-2 text-[10px]"></i>
                    </button>
                    
                    <div id="exportMenu" class="hidden origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 z-50 transition-all">
                        <div class="py-1">
                            <a href="#" onclick="exportToExcel(event)" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700">
                                <i class="fa-solid fa-file-excel mr-3 text-emerald-500 group-hover:scale-110 transition-transform"></i> Excel (.xlsx)
                            </a>
                            <a href="#" onclick="exportToPDF(event)" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700">
                                <i class="fa-solid fa-file-pdf mr-3 text-red-500 group-hover:scale-110 transition-transform"></i> PDF (.pdf)
                            </a>
                        </div>
                    </div>
                </div>
                
                <a href="requester_list.php" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center justify-center whitespace-nowrap">
                    <i class="fa-solid fa-arrow-left mr-2"></i>ย้อนกลับ
                </a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200">
            <div class="overflow-x-auto" id="data-to-export">
                <div id="pdf-header" class="hidden p-6 pb-2 text-center">
                    <h2 class="text-xl font-bold text-gray-800">รายงานประวัติการแจ้งเปลี่ยนเบอร์โทรศัพท์</h2>
                    <p class="text-gray-600 text-sm mt-1">ผู้แจ้ง: <?php echo htmlspecialchars($targetUser); ?></p>
                    <p class="text-gray-500 text-xs mt-1">พิมพ์เมื่อ: <span id="printDate"></span></p>
                </div>

                <table class="w-full text-left text-sm border-collapse" id="historyTable">
                    <thead class="bg-slate-50 text-slate-500 uppercase font-semibold border-b border-slate-200 text-xs">
                        <tr>
                            <th class="px-6 py-4">วันที่แจ้ง</th>
                            <th class="px-6 py-4">เจ้าของเบอร์</th>
                            <th class="px-6 py-4">รายละเอียด</th>
                            <th class="px-6 py-4 text-center">หัวหน้าบริษัท</th>
                            <th class="px-6 py-4 text-center">ผู้จัดการ</th>
                            <th class="px-6 py-4 text-center pdf-exclude-col">พนักงานไอที</th>
                            <th class="px-6 py-4 text-center pdf-exclude-col">หัวหน้าไอที</th> 
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-600">
                        <?php if (empty($userHistory)): ?>
                            <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400">ไม่พบข้อมูล</td></tr>
                        <?php else: ?>
                            <?php foreach ($userHistory as $req): 
                                $sHead = $req[6] ?? 'Pending';
                                $sManager = $req[7] ?? 'Pending';
                                $finalStatus = $req[8] ?? ''; // Employee_it_Status
                                $itStatus = $req[9] ?? ''; // IT_Status
                                
                                if ($sHead == 'Rejected') $sManager = '-';
                            ?>
                            <tr class="hover:bg-slate-50 transition data-row">
                                <td class="px-6 py-4 whitespace-nowrap text-slate-500"><?php echo $req[0]; ?></td>
                                <td class="px-6 py-4 font-bold text-slate-800 customer-name"><?php echo htmlspecialchars($req[2]); ?></td>
                                <td class="px-6 py-4" data-export-text="<?php echo htmlspecialchars($req[3]); ?> -> <?php echo htmlspecialchars($req[4]); ?>">
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="text-red-400 line-through"><?php echo htmlspecialchars($req[3]); ?></span>
                                        <i class="fa-solid fa-arrow-right text-slate-300"></i>
                                        <span class="text-green-600 font-bold"><?php echo htmlspecialchars($req[4]); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center" data-export-text="<?php echo $sHead; ?>"><?php echo getBadge($sHead); ?></td>
                                <td class="px-6 py-4 text-center" data-export-text="<?php echo $sManager; ?>">
                                    <?php echo ($sManager == '-') ? '<span class="text-slate-300">-</span>' : getBadge($sManager); ?>
                                </td>
                                
                                <td class="px-6 py-4 text-center pdf-exclude-col">
                                    <?php if ($finalStatus == 'Approved'): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-200">
                                            <i class="fa-solid fa-circle-check mr-1.5"></i>ดำเนินการแล้ว
                                        </span>
                                    <?php elseif ($sHead == 'Approved' && $sManager == 'Approved'): ?>
                                        <form method="POST" onsubmit="confirmSubmit(event, 'ยืนยันการเปลี่ยนเบอร์?', 'คุณยืนยันว่าได้เปลี่ยนเบอร์ให้ลูกค้าเรียบร้อยแล้วใช่หรือไม่?', '#2563eb')">
                                            <input type="hidden" name="row_id" value="<?php echo $req['row_index']; ?>">
                                            <button type="submit" name="mark_done" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1.5 rounded shadow-sm transition flex items-center mx-auto animate-pulse">
                                                <i class="fa-regular fa-square-check mr-1.5"></i>ยืนยันเปลี่ยนเบอร์
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-slate-300 text-xs italic">รออนุมัติครบ</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-4 text-center pdf-exclude-col">
                                    <?php if ($itStatus == 'Approved'): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-700 border border-purple-200">
                                            <i class="fa-solid fa-shield-check mr-1.5"></i>ตรวจสอบแล้ว
                                        </span>
                                    <?php elseif ($finalStatus == 'Approved'): ?>
                                        <?php if ($myRole == 'Head IT'): ?>
                                            <form method="POST" onsubmit="confirmSubmit(event, 'ตรวจสอบเรียบร้อย?', 'ยืนยันการตรวจสอบการเปลี่ยนเบอร์โดย หัวหน้า IT ใช่หรือไม่?', '#9333ea')">
                                                <input type="hidden" name="row_id" value="<?php echo $req['row_index']; ?>">
                                                <button type="submit" name="mark_it_done" class="bg-purple-600 hover:bg-purple-700 text-white text-xs px-3 py-1.5 rounded shadow-sm transition flex items-center mx-auto">
                                                    <i class="fa-solid fa-check-double mr-1.5"></i>ยืนยัน (IT)
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-xs italic">รอ IT ตรวจสอบ</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-200 text-xs">-</span>
                                    <?php endif; ?>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <tr id="noResultsRow" class="hidden">
                            <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                <i class="fa-solid fa-magnifying-glass text-slate-300 text-2xl mb-2 block"></i>
                                ไม่พบข้อมูลลูกค้าที่ค้นหา
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // ✅ ฟังก์ชันป๊อปอัปแจ้งเตือนสมูทๆ สำหรับปุ่มยืนยัน
        function confirmSubmit(event, title, text, confirmColor) {
            event.preventDefault(); // เบรกฟอร์มไว้ก่อน
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
                    title: 'font-bold text-gray-800 font-sans',
                    popup: 'rounded-2xl shadow-xl border border-gray-100',
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // แอบสร้าง input ซ่อนไว้ส่งค่าตามชื่อปุ่ม (เพื่อให้ PHP ทำงานต่อได้)
                    const btn = form.querySelector('button[type="submit"]');
                    if (btn && btn.name) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = btn.name;
                        hiddenInput.value = '1';
                        form.appendChild(hiddenInput);
                    }
                    form.submit(); // สั่งส่งฟอร์มจริงๆ
                }
            });
        }

        // ✅ ฟังก์ชัน Logout ด้วย SweetAlert2
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
                customClass: { title: 'font-bold text-gray-800 font-sans', popup: 'rounded-2xl shadow-xl border border-gray-100' }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../logout.php';
                }
            });
        }

        // --- สคริปต์ค้นหาลูกค้า ---
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const tableRows = document.querySelectorAll('#historyTable tbody tr.data-row');
            const noResultsRow = document.getElementById('noResultsRow');

            if (searchInput && tableRows.length > 0) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.trim().toLowerCase();
                    let hasVisibleRow = false;

                    tableRows.forEach(row => {
                        const customerName = row.querySelector('.customer-name').textContent.toLowerCase();
                        if (customerName.includes(searchTerm)) {
                            row.style.display = '';
                            hasVisibleRow = true;
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    if (hasVisibleRow || searchTerm === '') {
                        noResultsRow.classList.add('hidden');
                    } else {
                        noResultsRow.classList.remove('hidden');
                    }
                });
            }
        });

        // --- สคริปต์จัดการ Dropdown Export ---
        function toggleExportMenu() {
            const menu = document.getElementById('exportMenu');
            menu.classList.toggle('hidden');
        }

        window.addEventListener('click', function(e) {
            if (!document.getElementById('exportDropdownContainer').contains(e.target)) {
                document.getElementById('exportMenu').classList.add('hidden');
            }
        });

        // --- ฟังก์ชัน Export Excel ---
        function exportToExcel(e) {
            e.preventDefault();
            const userName = document.getElementById('userNameForExport').innerText;
            
            let excelData = [
                ["วันที่แจ้ง", "เจ้าของเบอร์", "รายละเอียดการเปลี่ยน", "สถานะ: หัวหน้าบริษัท", "สถานะ: ผู้จัดการ"]
            ];

            const tableRows = document.querySelectorAll('#historyTable tbody tr.data-row');
            
            tableRows.forEach(row => {
                if (row.style.display === 'none') return;

                const cells = row.querySelectorAll('td');
                if(cells.length >= 5) {
                    let rowData = [
                        cells[0].innerText.trim(), 
                        cells[1].innerText.trim(), 
                        cells[2].getAttribute('data-export-text') || cells[2].innerText.trim(), 
                        cells[3].getAttribute('data-export-text') || cells[3].innerText.trim(), 
                        cells[4].getAttribute('data-export-text') || cells[4].innerText.trim()  
                    ];
                    excelData.push(rowData);
                }
            });

            const ws = XLSX.utils.aoa_to_sheet(excelData);
            ws['!cols'] = [ {wch: 20}, {wch: 25}, {wch: 35}, {wch: 20}, {wch: 20} ];

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "ประวัติการแจ้ง");
            XLSX.writeFile(wb, `Report_${userName.replace(/\s+/g, '_')}.xlsx`);
            toggleExportMenu();
        }

        // --- ฟังก์ชัน Export PDF ---
        function exportToPDF(e) {
            e.preventDefault();
            const userName = document.getElementById('userNameForExport').innerText;
            const element = document.getElementById('data-to-export');
            
            const now = new Date();
            document.getElementById('printDate').innerText = now.toLocaleString('th-TH');
            
            document.getElementById('pdf-header').classList.remove('hidden');
            document.getElementById('pdf-header').classList.add('block');
            const excludeCols = document.querySelectorAll('.pdf-exclude-col');
            excludeCols.forEach(col => col.style.display = 'none');

            const opt = {
                margin:       10,
                filename:     `Report_${userName.replace(/\s+/g, '_')}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                document.getElementById('pdf-header').classList.add('hidden');
                document.getElementById('pdf-header').classList.remove('block');
                excludeCols.forEach(col => col.style.display = '');
            });
            
            toggleExportMenu();
        }
    </script>
    <script>
function filterByRange(type){

let now = new Date();
let startDate, endDate;

if(type === "today"){

startDate = new Date();
startDate.setHours(0,0,0,0);

endDate = new Date();
endDate.setHours(23,59,59,999);

}

else if(type === "7days"){

endDate = new Date();
startDate = new Date();
startDate.setDate(endDate.getDate()-7);

}

else if(type === "30days"){

endDate = new Date();
startDate = new Date();
startDate.setDate(endDate.getDate()-30);

}

else if(type === "month"){

startDate = new Date(now.getFullYear(), now.getMonth(), 1);
endDate = new Date(now.getFullYear(), now.getMonth()+1, 0);

}

else if(type === "year"){

startDate = new Date(now.getFullYear(),0,1);
endDate = new Date(now.getFullYear(),11,31);

}

else if(type === "reset"){

let rows = document.querySelectorAll("#historyTable tbody tr.data-row");
rows.forEach(row => row.style.display="");
return;

}

let rows = document.querySelectorAll("#historyTable tbody tr.data-row");

rows.forEach(row => {

let dateText = row.cells[0].innerText.trim();
let rowDate = new Date(dateText);

if(rowDate >= startDate && rowDate <= endDate){
row.style.display="";
}else{
row.style.display="none";
}

});

}
    </script>
</body>
</html>


<?php
require 'db.php';

// รับค่า
$action = $_GET['action'] ?? '';
$rowId = $_GET['row'] ?? '';
$role = $_GET['role'] ?? ''; // Head, Head IT, Manager

if (!$action || !$rowId || !$role) die("ข้อมูลไม่ครบถ้วน");

$status = ($action == 'approve') ? 'Approved' : 'Rejected';

// แปลง Role ให้ตรงกับ Column (Head IT ใช้สิทธิ์เดียวกับ Head)
$targetRole = ($role == 'Manager') ? 'Manager' : 'Head';

try {
    // 1. อัปเดตสถานะลง Google Sheets
    // Head/Head IT แก้ Col G (index 6), Manager แก้ Col H (index 7)
    $col = ($targetRole == 'Head') ? 'G' : 'H';
    $range = "Requests!{$col}{$rowId}";
    
    $body = new \Google\Service\Sheets\ValueRange(['values' => [[$status]]]);
    $params = ['valueInputOption' => 'RAW'];
    $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);

    // 2. ข้อความแจ้งผล
    $nextStepHtml = "";

    if ($action == 'approve' && $targetRole == 'Head') {
        $nextStepHtml = "
        <div class='mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg text-left max-w-lg mx-auto'>
            <h3 class='font-bold text-blue-800 mb-2'>
                <i class='fa-solid fa-share-from-square'></i> ส่งต่ออัตโนมัติ
            </h3>
            <p class='text-sm text-gray-700'>
                ระบบได้ส่งรายการนี้ไปที่ <strong>Dashboard ของผู้จัดการ (Manager)</strong> เรียบร้อยแล้ว
            </p>
        </div>";
    } 
    else if ($action == 'approve' && $targetRole == 'Manager') {
        $nextStepHtml = "
        <div class='mt-6 p-4 bg-green-50 border border-green-200 rounded-lg text-left max-w-lg mx-auto'>
            <h3 class='font-bold text-green-800 mb-2'>
                <i class='fa-solid fa-flag-checkered'></i> เสร็จสิ้นกระบวนการ
            </h3>
            <p class='text-sm text-gray-700'>
                รายการนี้ได้รับการอนุมัติครบถ้วนสมบูรณ์แล้ว
            </p>
        </div>";
    }

    // Redirect กลับหน้า Dashboard อัตโนมัติหลัง 3 วินาที (Optional)
    // หรือกดปุ่มกลับเอง
    echo "
    <!DOCTYPE html>
    <html lang='th'>
    <head>
        <meta charset='UTF-8'>
        <title>บันทึกผลสำเร็จ</title>
        <script src='https://cdn.tailwindcss.com'></script>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'/>
        <link href='https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap' rel='stylesheet'>
        <style>body{font-family:'Sarabun',sans-serif;}</style>
    </head>
    <body class='bg-gray-100 h-screen flex flex-col items-center justify-center p-4 text-center'>
        <div class='bg-white p-8 rounded-xl shadow-lg w-full max-w-xl'>
            
            <div class='inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-6 text-green-500'>
                <i class='fa-solid fa-circle-check text-5xl'></i>
            </div>
            
            <h1 class='text-2xl font-bold text-gray-800'>บันทึกผลเรียบร้อย!</h1>
            <div class='mt-2 text-gray-600'>
                <span class='bg-gray-100 text-gray-600 px-2 py-1 rounded text-sm mr-2'>$role</span>
                <i class='fa-solid fa-arrow-right text-gray-400 text-xs'></i>
                <span class='font-bold " . ($status=='Approved' ? 'text-green-600' : 'text-red-600') . " ml-2'>$status</span>
            </div>
            
            $nextStepHtml
            
            <div class='mt-8'>
                <a href='approve_dashboard.php' class='inline-block bg-slate-800 hover:bg-slate-900 text-white px-6 py-2.5 rounded-lg font-medium transition shadow-lg shadow-slate-300/50'>
                    <i class='fa-solid fa-list-check mr-2'></i> กลับไปหน้ารายการ
                </a>
            </div>
        </div>
    </body>
    </html>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
<?php
require '../db.php';

// 🔒 ตรวจสอบสิทธิ์ (ต้องเป็น Admin เท่านั้นถึงลบได้)
if (!isset($_SESSION['user_name']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['row'])) {
    $rowId = (int)$_GET['row'];
    
    // ⚠️ สำคัญ: การลบแถวใน Google Sheets ต้องใช้ "Sheet ID" (ตัวเลข)
    // โดยปกติ Sheet แรกสุดจะมี ID = 0
    // แต่ถ้าคุณเคยลบ Sheet1 ทิ้งแล้วสร้างใหม่ ID อาจไม่ใช่ 0
    // (ถ้าลบแล้ว Error ให้ลองเปลี่ยนเลข 0 เป็นเลข gid ที่เห็นใน URL ของ Google Sheets)
    $sheetId = 0; 

    $batchUpdateRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
        'requests' => [
            'deleteDimension' => [
                'range' => [
                    'sheetId' => $sheetId, 
                    'dimension' => 'ROWS',
                    'startIndex' => $rowId,     // เริ่มลบที่แถวนี้
                    'endIndex' => $rowId + 1    // ลบไป 1 แถว (Start + 1)
                ]
            ]
        ]
    ]);

    try {
        $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
        echo "<script>
            alert('✅ ลบข้อมูลผู้ใช้งานเรียบร้อยแล้ว');
            window.location.href='admin_dashboard.php';
        </script>";
    } catch (Exception $e) {
        echo "<div style='color:red; text-align:center; margin-top:50px;'>
                <h3>❌ เกิดข้อผิดพลาดในการลบ</h3>
                <p>Error: " . $e->getMessage() . "</p>
                <br>
                <a href='admin_dashboard.php'>กลับหน้าหลัก</a>
              </div>";
    }
} else {
    // ถ้าไม่มีการส่งค่า row มา ให้กลับไปหน้า Dashboard เฉยๆ
    header("Location: admin_dashboard.php");
    exit();
}
?>
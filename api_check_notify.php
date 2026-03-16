<?php
require 'db.php';

header('Content-Type: application/json');

$count = 0;

try {
    // ดึงข้อมูลถึง Column I (Employee_it_Status)
    $response = $service->spreadsheets_values->get($spreadsheetId, 'Requests!A:I');
    $rows = $response->getValues();
    
    if (!empty($rows)) {
        foreach ($rows as $index => $row) {
            if ($index == 0) continue; // ข้าม Header
            
            $statusManager = $row[7] ?? ''; // Column H
            $statusEmpIT = $row[8] ?? '';   // Column I (Employee_it_Status)
            
            // เงื่อนไข: ผู้จัดการอนุมัติแล้ว AND พนักงาน IT ยังไม่กด Approved
            if ($statusManager == 'Approved' && $statusEmpIT != 'Approved') {
                $count++;
            }
        }
    }
} catch (Exception $e) {
    $count = 0;
}

echo json_encode(['count' => $count]);
?>
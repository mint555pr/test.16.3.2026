<?php
require 'db.php'; // ตรวจสอบว่า path ของ db.php ถูกต้อง

header('Content-Type: application/json');

if (isset($_GET['name'])) {
    $searchName = trim($_GET['name']);
    $lastNumber = '';

    try {
        // ดึงข้อมูลชีต Requests
        $response = $service->spreadsheets_values->get($spreadsheetId, 'Requests!A:H');
        $requests = $response->getValues();

        if (!empty($requests)) {
            // วนลูปจากล่างขึ้นบน เพื่อหาข้อมูลล่าสุด
            for ($i = count($requests) - 1; $i > 0; $i--) {
                $row = $requests[$i];
                
                // อิงตาม Index: C(2)=OwnerName, E(4)=NewNum (ที่จะเป็นเบอร์เก่า)
                $ownerName = isset($row[2]) ? trim($row[2]) : ''; 
                $newNumber = isset($row[4]) ? trim($row[4]) : ''; 

                if (strcasecmp($ownerName, $searchName) == 0) {
                    $lastNumber = $newNumber;
                    break;
                }
            }
        }
        
        echo json_encode(['success' => true, 'old_number' => $lastNumber]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'No name provided']);
?>
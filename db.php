<?php

date_default_timezone_set('Asia/Bangkok');

// 1. เรียกใช้ Library ของ Google (ที่ติดตั้งผ่าน Composer)
require 'vendor/autoload.php'; 

// 2. ตั้งค่าการเชื่อมต่อ
$client = new \Google\Client();
$client->setApplicationName('number system');
$client->setScopes([\Google\Service\Sheets::SPREADSHEETS]);
$client->setAccessType('offline');

// ⚠️ สำคัญ: ไฟล์กุญแจต้องชื่อ credentials.json และวางอยู่ข้างๆ ไฟล์นี้
$client->setAuthConfig(__DIR__ . '/credentials.json');

// 3. สร้างตัวจัดการ Google Sheets
$service = new \Google\Service\Sheets($client);

// 4. รหัสของไฟล์ Google Sheet (ผมใส่จากรูปของคุณให้แล้ว)
// ถ้าสร้างไฟล์ใหม่ ให้แก้รหัสตรงนี้ครับ
$spreadsheetId = '1XaeuW2c8VfqKvTnN6s2kTquCdYKvm6dxqinnXK4Arls';

// 5. เริ่มต้น Session (เพื่อให้จำการล็อกอินข้ามหน้าได้)
session_start();
?>
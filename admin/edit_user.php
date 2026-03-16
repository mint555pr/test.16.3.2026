<?php 
require '../db.php';

// ตรวจสอบว่ามีเลขแถวส่งมาไหม
if (!isset($_GET['row'])) {
    header("Location: admin_dashboard.php"); // แก้ลิงก์ให้ถูก (อยู่ห้องเดียวกัน)
    exit();
}

$rowNum = (int)$_GET['row'] + 1; 
$range = "Users!A{$rowNum}:G{$rowNum}";


// ดึงข้อมูลเก่ามาโชว์
try {
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();
    
    if (empty($values)) {
        die("ไม่พบข้อมูลผู้ใช้");
    }

    $userData = $values[0]; 
    // [0]=Name, [1]=User, [2]=Pass, [3]=Role, [4]=Comp

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลพนักงาน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> body { font-family: 'Sarabun', sans-serif; } </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

    <?php include 'navbar_admin.php'; ?>

    <div class="flex-grow flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl overflow-hidden border border-gray-200">
            
            <div class="bg-slate-800 px-8 py-6 flex justify-between items-center text-white">
                <div>
                    <h2 class="text-xl font-bold">✏️ แก้ไขข้อมูลผู้ใช้งาน</h2>
                    <p class="text-slate-300 text-sm mt-1">กำลังแก้ไขข้อมูล: <?php echo htmlspecialchars($userData[0]); ?></p>
                </div>
            </div>

            <div class="p-8">
                <form method="post" class="space-y-6">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" required value="<?php echo htmlspecialchars($userData[0] ?? ''); ?>"
                            class="w-full px-4 py-2 rounded-md border border-gray-300 focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" name="user" required value="<?php echo htmlspecialchars($userData[1] ?? ''); ?>"
                            class="w-full px-4 py-2 rounded-md border border-gray-300 focus:ring-2 focus:ring-slate-500 outline-none transition bg-white text-gray-900">
                        <p class="text-xs text-blue-500 mt-1"><i class="fa-solid fa-circle-info mr-1"></i>สามารถแก้ไข Username ได้แล้ว</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password ใหม่ <span class="text-xs text-gray-400 font-normal">(เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)</span></label>
                        <input type="password" name="pass" placeholder="กรอกเฉพาะถ้าต้องการเปลี่ยนรหัสผ่าน"
                            class="w-full px-4 py-2 rounded-md border border-gray-300 focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ตำแหน่ง (Role)</label>
                            <select name="role" class="w-full px-4 py-2 rounded-md border border-gray-300 outline-none bg-white">
                                <?php 
                                    $roles = ['Employee', 'Head', 'Manager', 'Admin'];
                                    $currentRole = $userData[3] ?? '';
                                    foreach($roles as $r) {
                                        $selected = ($r == $currentRole) ? 'selected' : '';
                                        echo "<option value='$r' $selected>$r</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท (Company)</label>
                            <select name="company" class="w-full px-4 py-2 rounded-md border border-gray-300 outline-none bg-white">
                                 <?php 
                                    $comps = ['WCC', 'WCR', 'WCC&WCR', '4P', 'WT' , '4P&WT']; 
                                    $currentComp = $userData[4] ?? '';
                                    foreach($comps as $c) {
                                        $selected = ($c == $currentComp) ? 'selected' : '';
                                        echo "<option value='$c' $selected>$c</option>";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">สถานะการทำงาน</label>
                        <select name="status" class="w-full px-4 py-2 rounded-md border border-gray-300 outline-none bg-white">
                                <?php $statuses = ['Active', 'Inactive']; $currentStatus = $userData[6] ?? ''; foreach($statuses as $s){
                                    $selected = ($s == $currentStatus) ? 'selected' : '';
                                            echo "<option value='$s' $selected>$s</option>";
                                    }
                                ?>
                        </select>
                        </div>
                    </div>
                    <div class="pt-4 flex items-center justify-end space-x-3 border-t mt-6">
                        <a href="admin_dashboard.php" class="px-5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition">ยกเลิก</a>
                        <button type="submit" name="update" class="px-6 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium shadow-sm transition">
                            <i class="fa-solid fa-save mr-2"></i> บันทึกการแก้ไข
                        </button>
                    </div>

                </form>

                <?php
                if (isset($_POST['update'])) {
                    $newName = $_POST['name'];
                    $newUser = $_POST['user']; 
                    $newRole = $_POST['role'];
                    $newComp = $_POST['company'];
                    $status = $_POST['status'];
                    
                    // เช็คว่ามีการแก้รหัสผ่านไหม
                    if (!empty($_POST['pass'])) {
                        $finalPass = password_hash($_POST['pass'], PASSWORD_DEFAULT);
                    } else {
                        $finalPass = $userData[2]; // ใช้รหัสเดิม
                    }

                    $updateRow = [ [$newName, $newUser, $finalPass, $newRole, $newComp,'', $status] ];
                    $body = new \Google\Service\Sheets\ValueRange(['values' => $updateRow]);
                    $params = ['valueInputOption' => 'RAW'];

                    try {
                        $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
                        
                        // ✅ เปลี่ยนมาใช้ SweetAlert2 โชว์ความสำเร็จและตั้งเวลาดีเลย์ 1 วิ
                        echo "<script>
                            Swal.fire({
                                title: 'สำเร็จ!',
                                text: 'บันทึกข้อมูลเรียบร้อยแล้ว',
                                icon: 'success',
                                showConfirmButton: false,
                                timer: 1000,
                                timerProgressBar: true,
                                customClass: {
                                    title: 'font-bold text-gray-800 font-sans',
                                    popup: 'rounded-2xl shadow-xl border border-gray-100'
                                }
                            }).then(() => {
                                window.location.href = 'admin_dashboard.php';
                            });
                        </script>";
                    } catch(Exception $e) {
                        echo "<script>
                            Swal.fire({
                                title: 'เกิดข้อผิดพลาด!',
                                text: 'ไม่สามารถบันทึกข้อมูลได้: " . addslashes($e->getMessage()) . "',
                                icon: 'error',
                                confirmButtonColor: '#ef4444',
                                confirmButtonText: 'ตกลง',
                                customClass: {
                                    title: 'font-bold text-gray-800 font-sans',
                                    popup: 'rounded-2xl shadow-xl border border-gray-100'
                                }
                            });
                        </script>";
                    }
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>
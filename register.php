<?php require 'db.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มพนักงานใหม่</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    <style> body { font-family: 'Sarabun', sans-serif; } </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center py-10 px-4">

    <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl overflow-hidden border border-gray-200">
        
        <div class="bg-white border-b border-gray-200 px-8 py-6 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-bold text-slate-800">เพิ่มบัญชีผู้ใช้งานใหม่</h2>
                <p class="text-gray-500 text-sm mt-1">กรอกข้อมูลพนักงานเพื่อสร้างสิทธิ์การเข้าใช้งาน</p>
            </div>
            <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-600">
                <i class="fa-solid fa-user-plus"></i>
            </div>
        </div>

        <div class="p-8">

            <form method="post" class="space-y-6">
                  
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4 border-b pb-2">ข้อมูลเบื้องต้น</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ-นามสกุล <span class="text-red-500">*</span></label>
                            <input type="text" name="name" required placeholder=""
                                class="w-full px-4 py-2 rounded-md border border-gray-300 focus:ring-2 focus:ring-slate-500 outline-none transition bg-gray-50 focus:bg-white">
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล (Email) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fa-solid fa-envelope text-xs"></i></span>
                                <input type="email" name="email" required placeholder=""
                                    class="w-full pl-8 px-4 py-2 rounded-md border border-gray-300 focus:ring-2 focus:ring-slate-500 outline-none transition bg-gray-50 focus:bg-white">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4 border-b pb-2">ข้อมูลเข้าสู่ระบบ</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fa-solid fa-at text-xs"></i></span>
                                <input type="text" name="user" required placeholder="ภาษาอังกฤษเท่านั้น"
                                    class="w-full pl-8 px-4 py-2 rounded-md border border-gray-300 focus:ring-2 focus:ring-slate-500 outline-none transition bg-gray-50 focus:bg-white">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fa-solid fa-key text-xs"></i></span>
                                <input type="password" name="pass" required placeholder="กำหนดรหัสผ่าน"
                                    class="w-full pl-8 px-4 py-2 rounded-md border border-gray-300 focus:ring-2 focus:ring-slate-500 outline-none transition bg-gray-50 focus:bg-white">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4 border-b pb-2">กำหนดสิทธิ์และสังกัด</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ตำแหน่ง (Role)</label>
                            <select name="role" class="w-full px-4 py-2 rounded-md border border-gray-300 focus:ring-2 focus:ring-slate-500 outline-none bg-white">
                                <option value="Employee">พนักงาน</option>
                                <option value="Head">หัวหน้าบริษัท</option>
                                <option value="Head IT">หัวหน้า IT</option> 
                                <option value="Manager">ผู้จัดการ</option>
                                <option value="Admin">ผู้ดูแลระบบ</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">บริษัท</label>
                            <select name="company" class="w-full px-4 py-2 rounded-md border border-gray-300 focus:ring-2 focus:ring-slate-500 outline-none bg-white">
                                <option value="WCC">WCC</option>
                                <option value="WCR">WCR</option>
                                <option value="WCC&WCR">WCC&WCR</option>
                                <option value="4P">4P</option> 
                                <option value="WT">WT</option> 
                                <option value="4P&WT">4P&WT</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-end space-x-3 border-t mt-6">
                    <a href="admin/admin_dashboard.php" class="px-5 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition">
                        กลับหน้าหลัก
                    </a>
                    <button type="submit" name="register" 
                        class="px-6 py-2.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-medium shadow-sm transition flex items-center">
                        <i class="fa-solid fa-save mr-2"></i> บันทึกข้อมูล
                    </button>
                 
                </div>

            </form>

            <?php
            if (isset($_POST['register'])) {
                $name = $_POST['name'];
                $user = $_POST['user'];
                $pass = password_hash($_POST['pass'], PASSWORD_DEFAULT);
                $role = $_POST['role'];
                $comp = $_POST['company'];
                $email = $_POST['email']; 
                $staus = 'Active';   // กำหนดค่าเริ่มต้น

                $newRow = [ [$name, $user, $pass, $role, $comp, $email, $staus] ];
                
                $body = new \Google\Service\Sheets\ValueRange(['values' => $newRow]);
                $params = ['valueInputOption' => 'RAW'];

                try {
                    $result = $service->spreadsheets_values->append($spreadsheetId, 'Users!A:G', $body, $params);
                    echo "<div class='mt-6 p-4 bg-green-50 text-green-700 rounded-lg flex items-center border border-green-200'>
                            <i class='fa-solid fa-check-circle text-xl mr-3'></i>
                            <div>
                                <span class='font-bold block'>บันทึกสำเร็จ!</span>
                                <span class='text-sm'>เพิ่มผู้ใช้งานและอีเมลเรียบร้อยแล้ว</span>
                            </div>
                          </div>";
                } catch(Exception $e) {
                    echo "<div class='mt-6 p-4 bg-red-50 text-red-700 rounded-lg flex items-center border border-red-200'>
                            <i class='fa-solid fa-times-circle text-xl mr-3'></i>
                            <div>
                                <span class='font-bold block'>เกิดข้อผิดพลาด</span>
                                <span class='text-sm'>".$e->getMessage()."</span>
                            </div>
                          </div>";
                }
            }
            ?>
        </div>
    </div>
</body>
</html>
<?php require 'db.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | HR System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    <style> body { font-family: 'Sarabun', sans-serif; } </style>
</head>
<body class="bg-gray-50">

    <div class="min-h-screen flex">
        
        <div class="hidden lg:flex w-1/2 bg-slate-900 items-center justify-center relative overflow-hidden">
            <div class="absolute inset-0 bg-blue-900 opacity-20"></div>
            <div class="z-10 text-center px-10">
             
                <h2 class="text-4xl font-bold text-white mb-4">ระบบแจ้งเปลี่ยนเบอร์โทรศัพท์</h2>
                <p class="text-blue-200 text-lg">สำหรับภายในองค์กร</p>
            </div>
            <div class="absolute -bottom-32 -left-40 w-80 h-80 border-4 border-white/10 rounded-full"></div>
            <div class="absolute -bottom-40 -left-20 w-80 h-80 border-4 border-white/10 rounded-full"></div>
        </div>

        <div class="flex w-full lg:w-1/2 justify-center items-center bg-white p-8">
            <div class="w-full max-w-md">
                
                <div class="text-center mb-10">
                    <h1 class="text-3xl font-bold text-slate-800 mb-2">ลงชื่อเข้าใช้งาน</h1>
                    <p class="text-gray-500">กรุณากรอกข้อมูลเพื่อเข้าสู่ระบบ</p>
                </div>

                <form method="post" class="space-y-6">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-regular fa-user text-gray-400"></i>
                            </div>
                            <input type="text" name="user" required 
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-slate-800 focus:ring-1 focus:ring-slate-800 outline-none transition text-gray-700 bg-gray-50 focus:bg-white"
                                placeholder="">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" name="pass" required 
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-slate-800 focus:ring-1 focus:ring-slate-800 outline-none transition text-gray-700 bg-gray-50 focus:bg-white"
                                placeholder="">
                        </div>
                    </div>

                    <button type="submit" name="login" 
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 rounded-lg shadow-md transition duration-300 transform active:scale-95 flex justify-center items-center gap-2">
                        เข้าสู่ระบบ <i class="fa-solid fa-arrow-right"></i>
                    </button>

                </form>

                <div class="mt-8 text-center">
                     <p class="text-xs text-gray-400">© 2026.</p>
                </div>

                <?php
                if (isset($_POST['login'])) {
                    $user_input = $_POST['user'];
                    $pass_input = $_POST['pass'];

                    try {
                        $response = $service->spreadsheets_values->get($spreadsheetId, 'Users!A:G');
                        $rows = $response->getValues();
                        $login_success = false;

                        if (!empty($rows)) {
                            foreach ($rows as $index => $row) {
                                if ($index == 0) continue; 
                                if (isset($row[1]) && $row[1] == $user_input) {
                                    if (password_verify($pass_input, $row[2])) {
                                        $status = $row[6] ?? '';

                                        if ($status !== 'Active') {
                                        echo "<script>alert('บัญชีถูกยกเลิกการใช้งาน');window.location='login.php';</script>";
                                        exit;
                                        }

                                        $_SESSION['user_name'] = $row[0];
                                        $_SESSION['role'] = $row[3];
                                        $_SESSION['company'] = $row[4];
                                        $login_success = true;
                                        break;
                                    }
                                }
                            }
                        }

                        if ($login_success) {
                            // ✅ เพิ่มเงื่อนไข Redirect สำหรับ Head IT
                            if ($_SESSION['role'] == 'Admin') {
                                echo "<script>window.location.href='admin/admin_dashboard.php';</script>";
                            } elseif ($_SESSION['role'] == 'Head IT') {
                                echo "<script>window.location.href='head_it_dashboard.php';</script>";
                            } else {
                                echo "<script>window.location.href='dashboard.php';</script>";
                            }
                            exit();
                        } else {
                            echo "<div class='mt-4 p-3 bg-red-50 text-red-600 rounded-lg text-sm text-center border border-red-200'><i class='fa-solid fa-circle-exclamation mr-2'></i>ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง</div>";
                        }
                    } catch (Exception $e) {
                        echo "<div class='mt-4 text-center text-red-500 text-sm'>System Error</div>";
                    }
                }
                   
                ?>
            </div>
        </div>
    </div>
</body>
</html>
<nav class="bg-slate-900 text-white shadow-lg sticky top-0 z-50 border-b border-slate-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center space-x-4">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center shadow-lg shadow-blue-900/50">
                    <i class="fa-solid fa-shield-halved text-xl"></i>
                </div>
                <div>
                    <span class="block text-lg font-bold tracking-wide leading-tight">Admin</span>
                </div>
            </div>

            <div class="flex items-center space-x-6">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-semibold text-slate-200"><?php echo $_SESSION['user_name']; ?></p>
                    <div class="flex items-center justify-end space-x-1">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        <span class="text-xs text-slate-400">Online</span>
                    </div>
                </div>
                <div class="h-8 w-px bg-slate-700 mx-2"></div>
                
                <a href="../logout.php" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?');" 
                   class="group flex items-center justify-center w-10 h-10 rounded-full hover:bg-slate-800 transition duration-200" title="ออกจากระบบ">
                    <i class="fa-solid fa-power-off text-slate-400 group-hover:text-red-400 transition"></i>
                </a>
            </div>
        </div>
    </div>
</nav>
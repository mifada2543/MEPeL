<?php
session_name('mepel');
session_start();
require_once '../core/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - MEPeL</title>
    <link rel="icon" href="../assets/MEeL.png" type="image/png">
    <script src="../assets/js/tailwind.js"></script>
    <script src="../assets/js/htmx.js"></script>
    <script src="../assets/js/lucide.js"></script>
</head>
<body class="bg-[#121212] text-gray-100 font-mono">
    <div class="flex h-screen">
        <div class="w-64 bg-gray-900 border-r border-white/10 flex flex-col">
            <div class="p-6 border-b border-white/10">
                <h1 class="text-xl font-black text-yellow-400" onclick="window.location.href='../index.php'" title="kembali">MEPeL ADMIN</h1>
            </div>
            <nav class="flex-grow p-4 space-y-2">
                <button hx-get="admin_content.php?page=dashboard" hx-target="#main-content" 
                    class="w-full flex items-center gap-3 p-3 rounded hover:bg-white/5 transition">
                    <i data-lucide="layout-dashboard"></i> Dashboard
                </button>
                <button hx-get="admin_content.php?page=students" hx-target="#main-content"
                    class="w-full flex items-center gap-3 p-3 rounded hover:bg-white/5 transition">
                    <i data-lucide="users"></i> Data Murid
                </button>
                <button hx-get="admin_content.php?page=questions" hx-target="#main-content"
                    class="w-full flex items-center gap-3 p-3 rounded hover:bg-white/5 transition">
                    <i data-lucide="book-open"></i> Kelola Soal
                </button>
                <button hx-get="admin_content.php?page=materi" hx-target="#main-content"
                    class="w-full flex items-center gap-3 p-3 rounded hover:bg-white/5 transition">
                    <i data-lucide="file-text"></i> Kelola Materi
                </button>
            </nav>
            <div class="p-4 border-t border-white/10">
                <a href="../logout.php" class="flex items-center gap-3 p-3 text-red-400 hover:bg-red-500/10 rounded">
                    <i data-lucide="log-out"></i> Keluar
                </a>
            </div>
        </div>

        <main id="main-content" hx-get="admin_content.php?page=dashboard" hx-trigger="load" class="flex-grow overflow-y-auto p-8">
            </main>
    </div>
    <script>document.body.addEventListener('htmx:afterSwap', () => lucide.createIcons());</script>
</body>
</html>

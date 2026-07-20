<?php
session_name('mepel');
session_start();
require_once '../core/db.php';

$message = '';
$is_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Cek apakah username sudah ada
    $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmtCek->execute([$username]);
    if ($stmtCek->fetchColumn() > 0) {
        $message = "Username sudah digunakan!";
    } else {
        // Enkripsi password & Masukkan dengan role user, is_active 0
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'user', 0)");
        
        if ($stmt->execute([$username, $hash])) {
            $is_success = true;
            $message = "Pendaftaran berhasil! Tunggu verifikasi admin.";
        } else {
            $message = "Terjadi kesalahan pada sistem.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - MEPeL</title>
    <link rel="icon" href="../assets/MEeL.png" type="image/png">
    <script src="../assets/js/tailwind.js"></script>
    <script src="../assets/js/lucide.js"></script>
    <style>
        .paper-bg {
            background-color: #fdfbf7;
            background-image: linear-gradient(90deg, transparent 79px, #abced4 79px, #abced4 81px, transparent 81px),
                              linear-gradient(#eee .1em, transparent .1em);
            background-size: 100% 1.2em;
        }
    </style>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center font-mono relative overflow-hidden">
    <div class="w-full max-w-sm paper-bg p-8 rounded shadow-2xl rotate-1 border border-gray-300 relative">
        <div class="absolute top-2 left-2 w-4 h-4 rounded-full bg-red-400/50 shadow-inner"></div>
        
        <div class="mb-8 pt-4 border-b-2 border-dashed border-gray-400 pb-2">
            <h2 class="text-2xl font-black text-gray-800 uppercase tracking-tighter">Formulir Siswa</h2>
            <p class="text-xs text-gray-500 font-bold">MEPeL Pendaftaran Baru</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 text-xs font-bold p-2 <?= $is_success ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800' ?> border-l-4 <?= $is_success ? 'border-green-600' : 'border-red-600' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if (!$is_success): ?>
        <form method="POST" class="space-y-6">
            <div class="flex flex-col">
                <label class="text-[10px] font-bold text-gray-500 uppercase">Nama Pengguna (Username)</label>
                <input type="text" name="username" required autocomplete="off"
                    class="bg-transparent border-b-2 border-gray-400 focus:border-blue-600 outline-none py-1 text-sm font-bold text-gray-800">
            </div>
            <div class="flex flex-col">
                <label class="text-[10px] font-bold text-gray-500 uppercase">Kata Sandi</label>
                <input type="password" name="password" required 
                    class="bg-transparent border-b-2 border-gray-400 focus:border-blue-600 outline-none py-1 text-sm font-bold text-gray-800 tracking-widest">
            </div>
            <button type="submit" class="w-full bg-blue-800 text-white font-bold py-3 mt-4 hover:bg-blue-900 transition-colors uppercase text-sm">
                Ajukan Pendaftaran
            </button>
        </form>
        <?php else: ?>
            <a href="login.php" class="block text-center w-full bg-green-800 text-white font-bold py-3 mt-4 hover:bg-green-900 transition-colors uppercase text-sm">
                Kembali ke Login
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_name('mepel');
session_start();
require_once '../core/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Pastikan variabel diambil dari $_POST sebelum digunakan
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? ''; // Ini agar tidak Undefined Variable

    if (!empty($username) && !empty($password)) {
        // 2. Ambil data user dari database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC); // Memastikan $user adalah Array

        // 3. Pengecekan: Apakah user ditemukan DAN passwordnya cocok?
        if ($user && password_verify($password, $user['password'])) {
            
            // 4. Cek Status Aktivasi
            if (isset($user['is_active']) && $user['is_active'] == 0) {
                $error = "Akun belum diverifikasi! Hubungi Pengelolah.";
            } else {
                // Jika aktif, jalankan session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                header("Location: ../index.php");
                exit;
            }
        } else {
            $error = "Identitas tidak dikenali, Username atau Password salah.";
        }
    } else {
        $error = "Jangan biarkan lembar jawaban kosong!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MEPeL - Login</title>
    <link rel="icon" href="../assets/MEeL.png" type="image/png">
    <script src="../assets/js/tailwind.js"></script>
    <style>
        .paper-texture {
            background-color: #fff9e6;
            background-image: radial-gradient(#d1d1d1 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .red-line {
            border-left: 2px solid #ff7272;
            height: 100%;
            position: absolute;
            left: 40px;
            top: 0;
        }
    </style>
</head>
<body class="bg-[#3e2723] h-screen flex items-center justify-center font-mono p-4">

    <div class="w-full max-w-md bg-[#fff9e6] p-10 shadow-2xl rounded-sm relative overflow-hidden paper-texture border-l-8 border-yellow-200">
        <div class="red-line"></div>
        
        <div class="relative z-10 pl-10">
            <h2 class="text-3xl font-bold text-gray-800 mb-2 border-b-2 border-gray-400">LOGIN</h2>
            <p class="text-xs text-gray-500 mb-8 italic">Silahkan isi identitas Anda.</p>

            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-2 mb-6 text-sm">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 uppercase tracking-widest">Username</label>
                    <input type="text" name="username" required
                        class="w-full bg-transparent border-b-2 border-gray-400 focus:border-blue-500 outline-none py-2 text-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 uppercase tracking-widest">Password</label>
                    <input type="password" name="password" required
                        class="w-full bg-transparent border-b-2 border-gray-400 focus:border-blue-500 outline-none py-2 text-lg">
                </div>

                <div class="pt-4 flex items-center justify-between">
                    <a href="../index.php" class="text-sm text-gray-500 hover:text-gray-800 underline">Kembali ke Kelas</a>
                    <button type="submit" 
                        class="bg-gray-800 text-white px-6 py-2 rounded shadow-lg hover:bg-black transition-colors">
                        MASUK
                    </button>
                </div>
            </form>
        </div>

        <div class="absolute top-4 right-4 w-10 h-2 bg-gray-400/30 rounded-full rotate-45"></div>
    </div>

</body>
</html>
<?php
// partials/navbar.php
if (!isset($is_logged_in)) {
    $is_logged_in = isset($_SESSION['user_id']);
}
?>
<nav class="absolute top-0 right-0 w-auto px-6 py-5 flex justify-end items-center gap-3 z-50">
    <?php if ($is_logged_in): ?>
        <div class="flex items-center gap-2 bg-white/[.04] px-4 py-2 rounded-xl border border-white/[.06] backdrop-blur-sm">
            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
            <a href="profile.php" class="text-[11px] font-bold text-gray-400 hover:text-white transition-colors">
                <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
            </a>
            <span class="w-px h-3 bg-white/10 mx-1"></span>
            <a href="auth/logout.php" class="text-[10px] font-bold text-gray-600 hover:text-red-400 uppercase tracking-widest transition-colors">
                Out
            </a>
        </div>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="auth/admin.php" class="text-[10px] bg-yellow-600/20 text-yellow-500 border border-yellow-600/50 hover:bg-yellow-600 hover:text-white transition-all px-3 py-2 rounded-lg font-bold uppercase tracking-widest">
                <i data-lucide="shield-check" class="w-3 h-3 inline"></i> Admin Panel
            </a>
        <?php endif; ?>

    <?php else: ?>
        <a href="auth/login.php" class="text-[11px] font-bold text-gray-600 hover:text-white transition-colors px-3 py-2">
            Login
        </a>
        <a href="auth/register.php" class="bg-blue-600 hover:bg-blue-500 text-white text-[11px] font-bold px-4 py-2 rounded-lg transition shadow-[0_0_15px_rgba(37,99,235,0.5)] uppercase tracking-wider">
            Daftar
        </a>
    <?php endif; ?>
</nav>
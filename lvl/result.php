<?php
if (!defined('SOAL_PER_LEVEL')) define('SOAL_PER_LEVEL', 10);
// dipanggil via include dari logic.php
// Variabel tersedia: $score, $percentage, $passed, $posted_cat, $posted_lvl_for_result, $total, $pdo

$category   = $posted_cat                ?? 'dasar_php';
$lvl        = (int)($posted_lvl_for_result ?? 1);
$username   = htmlspecialchars($_SESSION['username'] ?? 'User');
$score      = (int)($_SESSION['game_score'] ?? 0);
$total      = (int)($_SESSION['game_total'] ?? SOAL_PER_LEVEL);
$percentage = $total > 0 ? round(($score / $total) * 100) : 0;

// Grade & pesan
if ($percentage === 100) {
    $grade = 'S'; $color = 'text-yellow-400'; $border = 'border-yellow-400';
    $msg   = "Sempurna, {$username}! Semua {$total} soal benar. Level selanjutnya terbuka!";
    $bg    = 'bg-yellow-400/10';
} elseif ($percentage >= 80) {
    $grade = 'A'; $color = 'text-green-400'; $border = 'border-green-400';
    $msg   = "Luar biasa, {$username}! Kamu sangat memahami materi ini, dan level berikutnya sudah terbuka.";
    $bg    = 'bg-green-400/10';
} elseif ($percentage >= 50) {
    $grade = 'B'; $color = 'text-blue-400'; $border = 'border-blue-400';
    $msg   = "Bagus! Terus berlatih untuk mencapai hasil lebih baik. Kamu tetap bisa lanjut ke level berikutnya.";
    $bg    = 'bg-blue-400/10';
} else {
    $grade = 'C'; $color = 'text-red-400'; $border = 'border-red-400';
    $msg   = "Jangan menyerah! Coba pelajari ulang materinya. Level berikutnya tetap terbuka supaya kamu bisa terus lanjut.";
    $bg    = 'bg-red-400/10';
}

// Cek apakah ada level berikutnya
$stmtNext = $pdo->prepare(
    "SELECT COUNT(*) FROM questions WHERE category = ? AND level = ?"
);
$stmtNext->execute([$category, $lvl + 1]);
$has_next_level = (int)$stmtNext->fetchColumn() > 0;

// Menyelesaikan level membuka akses ke level berikutnya jika tersedia.
$next_unlocked = $has_next_level;
?>

<div class="flex flex-col items-center justify-center w-full h-full text-white font-mono p-6
            animate-in fade-in zoom-in duration-500">

    <!-- Header -->
    <div class="mb-6 text-center">
        <p class="text-[10px] text-gray-500 uppercase tracking-[0.4em] mb-1">
            <?= htmlspecialchars(str_replace('_', ' ', $category)) ?> · Level <?= $lvl ?>
        </p>
        <h2 class="text-3xl font-black tracking-tighter uppercase border-b-4 border-white inline-block px-4">
            REPORT CARD
        </h2>
    </div>

    <!-- Skor utama -->
    <div class="flex items-center gap-10 mb-6">
        <!-- Grade badge -->
        <div class="w-28 h-28 rounded-full flex items-center justify-center
                    border-4 border-dashed <?= $border ?> rotate-[-8deg]">
            <span class="text-5xl font-black <?= $color ?>">
                <?= $grade ?>
            </span>
        </div>

        <!-- Angka skor -->
        <div>
            <div class="text-[72px] font-black leading-none <?= $color ?> flex items-baseline gap-2">
                <?= $score ?>
                <span class="text-xl text-white/30 italic">/ <?= $total ?></span>
            </div>
            <!-- Progress bar -->
            <div class="w-56 bg-white/10 h-2.5 rounded-full mt-2 overflow-hidden border border-white/20">
                <div class="h-full rounded-full transition-all duration-700 <?= $color ?> bg-current"
                     style="width:<?= $percentage ?>%"></div>
            </div>
            <p class="text-[10px] uppercase tracking-widest mt-1.5 text-gray-400">
                Akurasi: <?= $percentage ?>%
            </p>
        </div>
    </div>

    <!-- Soal tracker: tampilkan tiap nomor soal -->
    <div class="flex gap-2 mb-6 flex-wrap justify-center max-w-sm">
        <?php
        $ids_answered = $_SESSION['game_ids_history'] ?? [];
        for ($i = 1; $i <= $total; $i++):
        ?>
            <div class="w-7 h-7 rounded-full text-xs flex items-center justify-center font-bold
                <?= $i <= $score ? 'bg-green-500 text-white' : 'bg-red-500/50 text-red-200' ?>">
                <?= $i ?>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Pesan motivasi -->
    <div class="max-w-md text-center <?= $bg ?> p-5 rounded-2xl border border-white/10 mb-8 relative">
        <i data-lucide="quote" class="absolute -top-3 -left-3 w-7 h-7 text-white/20"></i>
        <p class="text-base italic leading-relaxed text-gray-100">"<?= $msg ?>"</p>
    </div>

    <!-- Tombol aksi -->
    <div class="flex flex-wrap justify-center gap-3">
        <!-- Coba lagi level ini -->
        <button hx-get="core/logic.php?lvl=<?= $lvl ?>&cat=<?= htmlspecialchars($category, ENT_QUOTES) ?>"
                hx-target="#content"
                class="flex items-center gap-2 bg-white/10 border-2 border-white/40 px-6 py-3
                       rounded-xl font-black hover:bg-white/20 transition-all text-sm">
            <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Coba Lagi
        </button>

        <!-- Lanjut ke level berikutnya — muncul jika level berikutnya tersedia -->
        <?php if ($next_unlocked): ?>
            <button hx-get="core/logic.php?lvl=<?= $lvl + 1 ?>&cat=<?= htmlspecialchars($category, ENT_QUOTES) ?>"
                    hx-target="#content"
                    class="flex items-center gap-2 bg-yellow-400 text-black px-6 py-3 rounded-xl
                           font-black hover:bg-yellow-300 transition-all text-sm
                           shadow-[0_0_20px_rgba(250,204,21,0.4)]">
                Level <?= $lvl + 1 ?> →
            </button>
        <?php elseif (!$has_next_level): ?>
            <div class="flex items-center gap-2 bg-purple-500/20 border-2 border-purple-400/50 px-6 py-3
                        rounded-xl text-purple-300 font-black text-sm">
                <i data-lucide="trophy" class="w-4 h-4"></i> Kategori Selesai!
            </div>
        <?php endif; ?>

        <!-- Kembali ke peta level -->
        <button hx-get="lvl/menu.php?cat=<?= htmlspecialchars($category, ENT_QUOTES) ?>"
                hx-target="#content"
                class="flex items-center gap-2 bg-transparent border-2 border-white/30 px-6 py-3
                       rounded-xl font-black hover:bg-white/10 transition-all text-sm">
            <i data-lucide="map" class="w-4 h-4"></i> Peta Level
        </button>
    </div>
</div>

<script>lucide.createIcons();</script>

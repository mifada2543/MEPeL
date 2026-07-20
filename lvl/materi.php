<?php
session_name('mepel');
session_start();
require_once '../core/db.php';
require_once '../core/materials.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='text-center p-8'>
            <p class='mb-4 text-gray-300'>Silahkan login terlebih dahulu.</p>
            <a href='auth/login.php' class='bg-yellow-500 text-black px-6 py-2 rounded font-bold'>LOGIN</a>
          </div>";
    exit;
}

$category = $_GET['cat'] ?? 'dasar_php';
$lvl      = max(1, (int)($_GET['lvl'] ?? 1));

$valid_categories = ['dasar_php', 'menengah_php', 'project_php'];
if (!in_array($category, $valid_categories, true)) {
    $category = 'dasar_php';
}

ensure_materials_table($pdo);

$stmt = $pdo->prepare(
    "SELECT id, pdf_filename, updated_at
     FROM level_materials
     WHERE category = ? AND level = ?
     LIMIT 1"
);
$stmt->execute([$category, $lvl]);
$material = $stmt->fetch();

$streamUrl = 'lvl/material_pdf.php?cat=' . urlencode($category) . '&lvl=' . urlencode((string)$lvl);
?>

<div class="w-full h-full blackboard-bg rounded-lg relative overflow-hidden border-4 border-brown-700 shadow-inner flex flex-col">
    <div class="flex items-center justify-between px-5 pt-4 pb-2 border-b border-white/10 z-20">
        <div>
            <h3 class="text-xl font-black text-yellow-400 uppercase tracking-tighter drop-shadow-lg">
                Materi Level <?= $lvl ?>
            </h3>
            <p class="text-[11px] text-white/60 mt-0.5">
                <?= htmlspecialchars(str_replace('_', ' ', $category)) ?>
            </p>
        </div>
        <button
            hx-get="lvl/menu.php?cat=<?= urlencode($category) ?>"
            hx-target="#content"
            class="px-3 py-1.5 text-[11px] font-bold rounded-full bg-white/10 text-white border border-white/20 hover:bg-white/20 transition-colors">
            Kembali ke Peta
        </button>
    </div>

    <div class="flex-1 min-h-0 p-4 md:p-5">
        <?php if ($material): ?>
            <div class="h-full bg-black/30 border border-white/15 rounded-xl overflow-hidden flex flex-col">
                <div class="px-4 py-2 border-b border-white/10 flex items-center justify-between">
                    <p class="text-xs text-gray-300 truncate">
                        <?= htmlspecialchars($material['pdf_filename']) ?>
                    </p>
                    <a href="<?= htmlspecialchars($streamUrl) ?>"
                       target="_blank"
                       rel="noopener"
                       class="text-[10px] text-yellow-300 hover:text-yellow-200 font-bold uppercase tracking-wide">
                        Buka Tab Baru
                    </a>
                </div>
                <iframe
                    src="<?= htmlspecialchars($streamUrl) ?>"
                    title="Preview Materi"
                    class="w-full flex-1 bg-white"
                    loading="lazy">
                </iframe>
            </div>
        <?php else: ?>
            <div class="h-full bg-black/30 border border-dashed border-white/20 rounded-xl flex items-center justify-center p-6 text-center">
                <div>
                    <i data-lucide="file-warning" class="w-9 h-9 mx-auto text-gray-400 mb-3"></i>
                    <p class="text-gray-200 font-bold">Materi untuk level ini belum tersedia.</p>
                    <p class="text-xs text-gray-400 mt-1">Admin belum mengunggah PDF untuk <?= htmlspecialchars(str_replace('_', ' ', $category)) ?> level <?= $lvl ?>.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>lucide.createIcons();</script>

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

$user_id  = $_SESSION['user_id'];
$category = $_GET['cat'] ?? 'dasar_php';
$valid_categories = ['dasar_php', 'menengah_php', 'project_php'];
if (!in_array($category, $valid_categories, true)) $category = 'dasar_php';
ensure_materials_table($pdo);

// ─── Koordinat level node (mendukung sampai 10 level) ─────────────────────
$path_coordinates = [
    ['x' => 80, 'y' => 82],
    ['x' => 55, 'y' => 70],
    ['x' => 30, 'y' => 58],
    ['x' => 20, 'y' => 44],
    ['x' => 35, 'y' => 30],
    ['x' => 60, 'y' => 20],
    ['x' => 78, 'y' => 32],
    ['x' => 70, 'y' => 46],
    ['x' => 50, 'y' => 50],
    ['x' => 50, 'y' => 12],
];

// ─── Ambil semua level dengan jumlah soalnya ──────────────────────────────
$stmtLevels = $pdo->prepare(
    "SELECT level, COUNT(*) as soal_count
     FROM questions WHERE category = ?
     GROUP BY level ORDER BY level ASC"
);
$stmtLevels->execute([$category]);
$levels = $stmtLevels->fetchAll();

$stmtMat = $pdo->prepare(
    "SELECT level FROM level_materials WHERE category = ?"
);
$stmtMat->execute([$category]);
$materials_by_level = array_map('intval', array_column($stmtMat->fetchAll(), 'level'));

// ─── Progress user: level mana saja yang sudah completed ─────────────────
$stmtDone = $pdo->prepare(
    "SELECT level_reached FROM user_progress
     WHERE user_id = ? AND category_slug = ? AND is_completed = 1"
);
$stmtDone->execute([$user_id, $category]);
$completed_levels = array_column($stmtDone->fetchAll(), 'level_reached');

// Level tertinggi yang bisa diakses = completed + 1
$max_completed    = !empty($completed_levels) ? max($completed_levels) : 0;
$accessible_until = $max_completed + 1;

// ─── Cek lock kategori ─────────────────────────────────────────────────────
function is_category_locked(string $cat, int $user_id, PDO $pdo): bool {
    if ($cat === 'dasar_php') return false;
    $prerequisite = match($cat) {
        'menengah_php' => 'dasar_php',
        'project_php'  => 'menengah_php',
        default        => null,
    };
    if (!$prerequisite) return true;

    $stmtTotal = $pdo->prepare("SELECT COUNT(DISTINCT level) FROM questions WHERE category = ?");
    $stmtTotal->execute([$prerequisite]);
    $totalLevels = (int)$stmtTotal->fetchColumn();
    if ($totalLevels === 0) return true;

    $stmtDone = $pdo->prepare(
        "SELECT COUNT(DISTINCT level_reached) FROM user_progress
         WHERE user_id = ? AND category_slug = ? AND is_completed = 1"
    );
    $stmtDone->execute([$user_id, $prerequisite]);
    return (int)$stmtDone->fetchColumn() < $totalLevels;
}

$category_locked  = is_category_locked($category, $user_id, $pdo);
$menengah_locked  = is_category_locked('menengah_php', $user_id, $pdo);
$project_locked   = is_category_locked('project_php',  $user_id, $pdo);
?>

<div class="w-full h-full blackboard-bg rounded-lg relative overflow-hidden border-4 border-brown-700 shadow-inner flex flex-col">

    <!-- Header kategori -->
    <div class="flex items-center justify-between px-5 pt-4 pb-2 border-b border-white/10 z-20">
        <h3 class="text-xl font-black text-yellow-400 uppercase tracking-tighter drop-shadow-lg">
            World: <?= htmlspecialchars(str_replace('_', ' ', $category)) ?>
        </h3>
        <span class="text-xs text-white/40">
            <?= count($completed_levels) ?> / <?= count($levels) ?> level selesai
        </span>
    </div>

    <!-- Peta level + konten -->
    <div class="flex flex-1 min-h-0">

        <!-- Peta kiri -->
        <div class="relative flex-1">
            <?php if ($category_locked): ?>
                <div class="absolute inset-0 flex items-center justify-center z-20 bg-black/70 rounded-bl-lg">
                    <div class="text-center">
                        <i data-lucide="lock" class="w-10 h-10 mx-auto text-gray-400 mb-2"></i>
                        <p class="text-gray-300 font-bold text-sm">Selesaikan kategori sebelumnya dulu.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Path SVG -->
            <svg class="absolute inset-0 w-full h-full z-0 pointer-events-none"
                 viewBox="0 0 100 100" preserveAspectRatio="none">
                <defs>
                    <marker id="arr" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
                        <polygon points="0 0,8 3,0 6" fill="#555" />
                    </marker>
                </defs>
                <?php for ($i = 0; $i < count($levels) - 1; $i++):
                    if (!isset($path_coordinates[$i], $path_coordinates[$i + 1])) break;
                    $s = $path_coordinates[$i]; $e = $path_coordinates[$i + 1];
                    $lvl_num = (int)$levels[$i]['level'];
                    $stroke  = in_array($lvl_num, $completed_levels) ? '#4ade80' : '#444';
                ?>
                    <line x1="<?= $s['x'] ?>" y1="<?= $s['y'] ?>"
                          x2="<?= $e['x'] ?>" y2="<?= $e['y'] ?>"
                          stroke="<?= $stroke ?>" stroke-width="0.6"
                          marker-end="url(#arr)" />
                <?php endfor; ?>
            </svg>

            <!-- Level nodes -->
            <?php foreach ($levels as $idx => $lvl):
                if (!isset($path_coordinates[$idx])) break;
                $c       = $path_coordinates[$idx];
                $lvl_num = (int)$lvl['level'];
                $soal_n  = min((int)$lvl['soal_count'], 10);
                $is_done = in_array($lvl_num, $completed_levels);
                $is_lock = $category_locked || ($lvl_num > $accessible_until);
                $is_curr = !$category_locked && ($lvl_num === $accessible_until);

                if ($is_lock) {
                    $cls  = "bg-gray-700 text-gray-500 border-gray-800 cursor-not-allowed";
                    $hx   = "disabled";
                } elseif ($is_done) {
                    $cls  = "bg-green-600 text-white border-green-900 hover:bg-green-500";
                    $hx   = "hx-get='core/logic.php?cat={$category}&lvl={$lvl_num}' hx-target='#content'";
                } else {
                    $cls  = "bg-yellow-500 text-black border-white hover:bg-yellow-400" . ($is_curr ? " animate-pulse" : "");
                    $hx   = "hx-get='core/logic.php?cat={$category}&lvl={$lvl_num}' hx-target='#content'";
                }
            ?>
                <div class="absolute z-10 transform -translate-x-1/2 -translate-y-1/2 flex flex-col items-center gap-0.5"
                     style="left:<?= $c['x'] ?>%;top:<?= $c['y'] ?>%">
                    <button <?= $hx ?>
                            class="w-14 h-14 rounded-full flex items-center justify-center font-black text-xl
                                   shadow-lg transition-all duration-200 border-4 active:scale-95 <?= $cls ?>"
                            <?= $is_lock ? 'disabled' : '' ?>>
                        <?php if ($is_lock): ?>
                            <i data-lucide="lock" class="w-5 h-5"></i>
                        <?php elseif ($is_done): ?>
                            <i data-lucide="check" class="w-5 h-5"></i>
                        <?php else: ?>
                            <?= $lvl_num ?>
                        <?php endif; ?>
                    </button>
                    <!-- Mini badge: jumlah soal -->
                    <span class="text-[9px] <?= $is_done ? 'text-green-400' : ($is_lock ? 'text-gray-600' : 'text-yellow-300') ?> font-bold whitespace-nowrap">
                        <?= $soal_n ?>/10 soal
                    </span>
                </div>
            <?php endforeach; ?>

            <!-- Navigasi bawah -->
            <div class="absolute bottom-3 left-3 flex gap-2 z-30">
                <button hx-get="lvl/menu.php?cat=dasar_php" hx-target="#content"
                    class="px-3 py-1.5 text-[11px] font-bold rounded-full transition-colors
                           <?= $category === 'dasar_php' ? 'bg-white text-green-900' : 'bg-green-900/70 text-white border border-white/30 hover:bg-green-800' ?>">
                    Dasar PHP
                </button>
                <button hx-get="lvl/menu.php?cat=menengah_php" hx-target="#content"
                        <?= $menengah_locked ? 'disabled' : '' ?>
                    class="px-3 py-1.5 text-[11px] font-bold rounded-full transition-colors
                           <?= $category === 'menengah_php' ? 'bg-yellow-400 text-black' : ($menengah_locked ? 'bg-gray-800 text-gray-600 border border-gray-700 cursor-not-allowed' : 'bg-gray-700/70 text-gray-300 border border-gray-600 hover:bg-gray-600') ?>">
                    <?= $menengah_locked ? '🔒 ' : '' ?>Menengah
                </button>
                <button hx-get="lvl/menu.php?cat=project_php" hx-target="#content"
                        <?= $project_locked ? 'disabled' : '' ?>
                    class="px-3 py-1.5 text-[11px] font-bold rounded-full transition-colors
                           <?= $category === 'project_php' ? 'bg-purple-400 text-white' : ($project_locked ? 'bg-gray-800 text-gray-600 border border-gray-700 cursor-not-allowed' : 'bg-gray-700/70 text-gray-300 border border-gray-600 hover:bg-gray-600') ?>">
                    <?= $project_locked ? '🔒 ' : '' ?>Project
                </button>
            </div>
        </div>

        <!-- Panel kanan: detail level yang di-hover/dipilih (hanya jika ada level) -->
        <?php if (!empty($levels)): ?>
        <div class="w-44 border-l border-white/10 p-3 flex flex-col gap-2 overflow-y-auto">
            <p class="text-[10px] uppercase text-white/40 font-bold tracking-wider mb-1">Semua Level</p>
            <?php foreach ($levels as $lvl):
                $ln    = (int)$lvl['level'];
                $sn    = min((int)$lvl['soal_count'], 10);
                $done  = in_array($ln, $completed_levels);
                $lock  = $category_locked || $ln > $accessible_until;
                $curr  = !$lock && ($ln === $accessible_until);
            ?>
                <div class="flex items-center gap-2 p-2 rounded-lg text-xs
                    <?= $done ? 'bg-green-900/30 border border-green-700/40' : ($lock ? 'opacity-40' : ($curr ? 'bg-yellow-400/10 border border-yellow-500/40' : 'bg-white/5')) ?>">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-[10px] flex-shrink-0
                        <?= $done ? 'bg-green-500 text-white' : ($curr ? 'bg-yellow-400 text-black' : 'bg-white/10 text-white/50') ?>">
                        <?= $ln ?>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="font-bold <?= $done ? 'text-green-300' : ($curr ? 'text-yellow-300' : 'text-white/60') ?>">
                            Level <?= $ln ?>
                        </div>
                        <!-- Mini soal bar -->
                        <div class="flex gap-0.5 mt-0.5">
                            <?php for ($s = 1; $s <= 10; $s++): ?>
                                <div class="h-1 flex-1 rounded-sm
                                    <?= $s <= $sn
                                        ? ($done ? 'bg-green-500' : ($curr ? 'bg-yellow-400' : 'bg-white/30'))
                                        : 'bg-white/10' ?>">
                                </div>
                            <?php endfor; ?>
                        </div>
                        <p class="text-[9px] mt-0.5 <?= $done ? 'text-green-400' : 'text-white/30' ?>">
                            <?= $sn ?>/10 soal<?= $done ? ' ✓' : '' ?>
                        </p>
                        <button
                            hx-get="lvl/materi.php?cat=<?= urlencode($category) ?>&lvl=<?= $ln ?>"
                            hx-target="#content"
                            class="mt-1 px-2 py-0.5 rounded text-[9px] font-bold border transition-colors
                                   <?= in_array($ln, $materials_by_level, true)
                                        ? 'border-cyan-400/50 text-cyan-300 hover:bg-cyan-500/10'
                                        : 'border-white/20 text-white/50 hover:bg-white/5' ?>">
                            Materi PDF
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>lucide.createIcons();</script>

<?php
session_name('mepel');
session_start();
require_once '../core/db.php';
require_once '../core/materials.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_validate(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403); die('Request tidak valid.');
    }
}

$csrf   = csrf_token();
$page   = $_GET['page']   ?? 'dashboard';
$id     = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$action = $_GET['action'] ?? null;
$admin_name = htmlspecialchars($_SESSION['username'] ?? 'Admin');

$valid_categories = ['dasar_php', 'menengah_php', 'project_php'];
$valid_types      = ['pilihan_ganda', 'debugging', 'analisis', 'logika'];

$type_colors = [
    'pilihan_ganda' => 'bg-blue-500/20 text-blue-300 border-blue-500/30',
    'debugging'     => 'bg-red-500/20 text-red-300 border-red-500/30',
    'analisis'      => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
    'logika'        => 'bg-purple-500/20 text-purple-300 border-purple-500/30',
];

ensure_materials_table($pdo);
$material_notice = null;

// ─── Aksi POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    csrf_validate();

    if ($action === 'delete_user' && $id) {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$id]);

    } elseif ($action === 'approve_user' && $id) {
        $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$id]);

    } elseif ($action === 'delete_question' && $id) {
        $row = $pdo->prepare("SELECT category, level, question_order FROM questions WHERE id = ?");
        $row->execute([$id]);
        $q = $row->fetch();
        $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$id]);
        if ($q) {
            $pdo->prepare(
                "UPDATE questions SET question_order = question_order - 1
                 WHERE category = ? AND level = ? AND question_order > ?"
            )->execute([$q['category'], $q['level'], $q['question_order']]);
        }
        exit;

    } elseif ($action === 'update_question' && $id) {
        $correct = strtoupper(trim($_POST['correct'] ?? ''));
        $qtype   = $_POST['question_type'] ?? 'pilihan_ganda';
        if (!in_array($correct, ['A','B','C','D'], true)) {
            http_response_code(400); die('Kunci jawaban tidak valid.');
        }
        if (!in_array($qtype, $valid_types, true)) $qtype = 'pilihan_ganda';

        // code_snippet: kosongkan jika tipe tidak butuh kode
        $needs_code  = in_array($qtype, ['debugging', 'analisis', 'logika'], true);
        $code_snippet = $needs_code ? (trim($_POST['code_snippet'] ?? '') ?: null) : null;

        $pdo->prepare(
            "UPDATE questions
             SET instruction=?, question_text=?, code_snippet=?,
                 option_a=?, option_b=?, option_c=?, option_d=?,
                 correct_answer=?, question_type=?
             WHERE id=?"
        )->execute([
            $_POST['instruction'] ?? '',
            $_POST['question']    ?? '',
            $code_snippet,
            $_POST['opt_a'] ?? '', $_POST['opt_b'] ?? '',
            $_POST['opt_c'] ?? '', $_POST['opt_d'] ?? '',
            $correct, $qtype, $id,
        ]);
        echo "<div class='text-green-400 text-xs p-2 bg-green-900/20 rounded border border-green-800'>
                ✓ Soal berhasil diperbarui.
              </div>";
        exit;
    } elseif ($action === 'upload_materi') {
        $category = $_POST['category'] ?? '';
        $level    = filter_var($_POST['level'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $file     = $_FILES['pdf_file'] ?? null;
        $page     = 'materi';

        if (!in_array($category, $valid_categories, true)) {
            $material_notice = ['type' => 'error', 'text' => 'Kategori materi tidak valid.'];
        } elseif ($level === false) {
            $material_notice = ['type' => 'error', 'text' => 'Level materi harus angka positif.'];
        } elseif (!$file) {
            $material_notice = ['type' => 'error', 'text' => 'File PDF wajib dipilih.'];
        } else {
            $uploadErr = null;
            if (!materials_is_valid_pdf_upload($file, $uploadErr)) {
                $material_notice = ['type' => 'error', 'text' => $uploadErr ?? 'File PDF tidak valid.'];
            } else {
                $storageDir  = ensure_materials_storage_dir();
                $storedName  = generate_stored_pdf_name($category, (int)$level);
                $targetFull  = $storageDir . DIRECTORY_SEPARATOR . $storedName;
                $targetRel   = materials_relative_path($storedName);
                $original    = sanitize_uploaded_pdf_name((string)$file['name']);

                if (!move_uploaded_file((string)$file['tmp_name'], $targetFull)) {
                    $material_notice = ['type' => 'error', 'text' => 'Gagal memindahkan file PDF ke server.'];
                } else {
                    try {
                        $existingStmt = $pdo->prepare(
                            "SELECT id, pdf_path FROM level_materials WHERE category = ? AND level = ? LIMIT 1"
                        );
                        $existingStmt->execute([$category, (int)$level]);
                        $existing = $existingStmt->fetch();

                        if ($existing) {
                            $pdo->prepare(
                                "UPDATE level_materials
                                 SET pdf_filename = ?, pdf_path = ?, uploaded_by = ?, updated_at = CURRENT_TIMESTAMP
                                 WHERE id = ?"
                            )->execute([$original, $targetRel, (int)$_SESSION['user_id'], (int)$existing['id']]);
                            safe_unlink_material_file((string)$existing['pdf_path']);
                            $material_notice = ['type' => 'success', 'text' => "Materi level {$level} berhasil diganti."];
                        } else {
                            $pdo->prepare(
                                "INSERT INTO level_materials (category, level, pdf_filename, pdf_path, uploaded_by)
                                 VALUES (?, ?, ?, ?, ?)"
                            )->execute([$category, (int)$level, $original, $targetRel, (int)$_SESSION['user_id']]);
                            $material_notice = ['type' => 'success', 'text' => "Materi level {$level} berhasil diunggah."];
                        }
                    } catch (Throwable $e) {
                        @unlink($targetFull);
                        error_log('[MEPeL Materi Upload] ' . $e->getMessage());
                        $material_notice = ['type' => 'error', 'text' => 'Gagal menyimpan data materi ke database.'];
                    }
                }
            }
        }
    } elseif ($action === 'delete_materi' && $id) {
        $page = 'materi';
        $stmt = $pdo->prepare("SELECT pdf_path FROM level_materials WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $materi = $stmt->fetch();

        if (!$materi) {
            $material_notice = ['type' => 'error', 'text' => 'Data materi tidak ditemukan.'];
        } else {
            $pdo->prepare("DELETE FROM level_materials WHERE id = ?")->execute([$id]);
            safe_unlink_material_file((string)$materi['pdf_path']);
            $material_notice = ['type' => 'success', 'text' => 'Materi berhasil dihapus.'];
        }
    }

    if (!in_array($action, ['upload_materi', 'delete_materi'], true)) {
        header("HX-Refresh: true");
        exit;
    }
}

// ─── RENDER ────────────────────────────────────────────────────────────────
if ($page === 'dashboard'):
    $totalUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
    $pendingUsers   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user' AND is_active=0")->fetchColumn();
    $totalQuestions = (int)$pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    $totalLevels    = (int)$pdo->query("SELECT COUNT(DISTINCT CONCAT(category,'-',level)) FROM questions")->fetchColumn();
?>
    <h2 class="text-2xl font-bold mb-6">Dashboard</h2>
    <p class="text-gray-400 mb-6">Selamat datang, <?= $admin_name ?>.</p>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <?php foreach ([
            ['Total Murid',   $totalUsers,     'text-yellow-400'],
            ['Pending',       $pendingUsers,   'text-red-400'],
            ['Total Soal',    $totalQuestions, 'text-green-400'],
            ['Total Level',   $totalLevels,    'text-blue-400'],
        ] as [$lbl,$val,$col]): ?>
        <div class="bg-gray-800 rounded-xl p-5 border border-white/10">
            <p class="text-3xl font-black <?= $col ?>"><?= $val ?></p>
            <p class="text-sm text-gray-400 mt-1"><?= $lbl ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Breakdown tipe soal -->
    <h3 class="text-base font-bold text-gray-300 mb-3">Distribusi Tipe Soal</h3>
    <?php
    $type_dist = $pdo->query(
        "SELECT question_type, COUNT(*) as cnt FROM questions GROUP BY question_type ORDER BY cnt DESC"
    )->fetchAll();
    $total_q = max($totalQuestions, 1);
    ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
        <?php foreach ($type_dist as $t):
            $tcls = $type_colors[$t['question_type']] ?? 'bg-gray-500/20 text-gray-300 border-gray-500/30';
            $pct  = round($t['cnt'] / $total_q * 100);
        ?>
        <div class="bg-gray-800 rounded-xl p-4 border <?= $tcls ?>">
            <p class="text-2xl font-black"><?= $t['cnt'] ?></p>
            <p class="text-sm font-bold mt-0.5"><?= htmlspecialchars($t['question_type']) ?></p>
            <div class="w-full bg-white/10 h-1.5 rounded-full mt-2">
                <div class="h-full bg-current rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Status slot per kategori -->
    <h3 class="text-base font-bold text-gray-300 mb-3">Status Slot Soal</h3>
    <?php foreach ($valid_categories as $cat):
        $rows = $pdo->prepare(
            "SELECT level, COUNT(*) as cnt FROM questions WHERE category=? GROUP BY level ORDER BY level"
        );
        $rows->execute([$cat]);
        $lvls = $rows->fetchAll();
    ?>
    <div class="bg-gray-800/50 border border-white/10 rounded-xl p-4 mb-3">
        <p class="text-sm font-bold text-yellow-400 mb-3">
            <?= htmlspecialchars(str_replace('_',' ', $cat)) ?>
        </p>
        <?php if (empty($lvls)): ?>
            <p class="text-gray-500 text-xs">Belum ada soal.</p>
        <?php else: ?>
        <div class="flex flex-wrap gap-4">
            <?php foreach ($lvls as $r):
                $f = min((int)$r['cnt'], 10);
            ?>
            <div class="flex flex-col items-center gap-1">
                <span class="text-[10px] text-gray-400">Lvl <?= $r['level'] ?></span>
                <div class="flex gap-0.5">
                    <?php for ($s = 1; $s <= 10; $s++): ?>
                    <div class="w-3 h-4 rounded-sm <?= $s <= $f ? 'bg-green-500' : 'bg-gray-600' ?>"></div>
                    <?php endfor; ?>
                </div>
                <span class="text-[9px] <?= $f >= 10 ? 'text-green-400' : 'text-gray-500' ?>">
                    <?= $f ?>/10
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

<?php elseif ($page === 'students'):
    $users = $pdo->prepare(
        "SELECT u.*,
                (SELECT COUNT(DISTINCT level_reached) FROM user_progress up
                 WHERE up.user_id = u.id AND up.is_completed = 1) as total_lulus
         FROM users u WHERE role = 'user' ORDER BY created_at DESC"
    );
    $users->execute();
    $users = $users->fetchAll();
?>
    <h2 class="text-2xl font-bold mb-6">Manajemen Murid</h2>
    <div class="overflow-hidden rounded-xl border border-white/10">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-gray-400 uppercase text-[10px]">
                <tr>
                    <th class="p-4">Nama</th><th class="p-4">Bergabung</th>
                    <th class="p-4">Level Lulus</th><th class="p-4 text-center">Status</th>
                    <th class="p-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php foreach ($users as $u): ?>
                <tr class="hover:bg-white/5 transition">
                    <td class="p-4 font-bold text-yellow-400"><?= htmlspecialchars($u['username']) ?></td>
                    <td class="p-4 text-gray-400"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td class="p-4 text-green-400 font-bold"><?= (int)$u['total_lulus'] ?> Level</td>
                    <td class="p-4 text-center">
                        <span class="px-2 py-1 rounded-full text-[10px] <?= $u['is_active'] ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400' ?>">
                            <?= $u['is_active'] ? 'AKTIF' : 'PENDING' ?>
                        </span>
                    </td>
                    <td class="p-4 text-right flex justify-end gap-2">
                        <?php if (!$u['is_active']): ?>
                        <form method="POST"
                              hx-post="admin_content.php?page=students&action=approve_user&id=<?= $u['id'] ?>"
                              hx-target="#main-content" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="bg-green-600 p-2 rounded hover:bg-green-500">
                                <i data-lucide="check" class="w-4 h-4"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST"
                              hx-post="admin_content.php?page=students&action=delete_user&id=<?= $u['id'] ?>"
                              hx-target="#main-content"
                              hx-confirm="Hapus murid '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>'?"
                              style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="bg-red-600 p-2 rounded hover:bg-red-500">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="5" class="p-6 text-center text-gray-500">Belum ada murid.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($page === 'materi'):
    $materials = $pdo->query(
        "SELECT lm.*, u.username AS uploader_name
         FROM level_materials lm
         LEFT JOIN users u ON u.id = lm.uploaded_by
         ORDER BY lm.category ASC, lm.level ASC"
    )->fetchAll();
?>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold">Kelola Materi</h2>
            <p class="text-gray-400 text-xs mt-1">Upload PDF materi per kategori + level.</p>
        </div>
    </div>

    <?php if ($material_notice): ?>
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm
            <?= $material_notice['type'] === 'success'
                ? 'bg-green-900/30 border-green-700 text-green-300'
                : 'bg-red-900/30 border-red-700 text-red-300' ?>">
            <?= htmlspecialchars($material_notice['text']) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-[360px_1fr] gap-5">
        <div class="bg-gray-800/50 border border-white/10 rounded-xl p-4">
            <h3 class="text-sm font-black text-yellow-400 uppercase mb-3">Upload / Ganti Materi PDF</h3>
            <form method="POST"
                  enctype="multipart/form-data"
                  hx-post="admin_content.php?page=materi&action=upload_materi"
                  hx-target="#main-content"
                  hx-encoding="multipart/form-data"
                  class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div>
                    <label class="text-[10px] uppercase text-gray-400 font-bold">Kategori</label>
                    <select name="category"
                            required
                            class="mt-1 w-full bg-gray-900 border border-white/20 p-2 rounded text-sm outline-none focus:border-yellow-500">
                        <?php foreach ($valid_categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= htmlspecialchars(str_replace('_', ' ', $cat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] uppercase text-gray-400 font-bold">Level</label>
                    <input type="number"
                           name="level"
                           required
                           min="1"
                           placeholder="Contoh: 1"
                           class="mt-1 w-full bg-gray-900 border border-white/20 p-2 rounded text-sm outline-none focus:border-yellow-500">
                </div>

                <div>
                    <label class="text-[10px] uppercase text-gray-400 font-bold">File PDF</label>
                    <input type="file"
                           name="pdf_file"
                           accept="application/pdf,.pdf"
                           required
                           class="mt-1 block w-full text-xs text-gray-300
                                  file:mr-3 file:px-3 file:py-2 file:rounded file:border-0
                                  file:bg-yellow-500 file:text-black file:font-bold file:text-xs
                                  hover:file:bg-yellow-400">
                    <p class="text-[10px] text-gray-500 mt-1">Maksimum ukuran file: 10 MB.</p>
                </div>

                <button type="submit"
                        class="w-full bg-yellow-500 hover:bg-yellow-400 text-black text-xs font-black py-2 rounded transition-transform active:scale-95">
                    Simpan Materi
                </button>
            </form>
        </div>

        <div class="bg-gray-800/40 border border-white/10 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-white/10">
                <h3 class="text-sm font-bold text-gray-200">Daftar Materi per Level</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-white/5 text-gray-400 uppercase text-[10px]">
                        <tr>
                            <th class="p-3 text-left">Kategori</th>
                            <th class="p-3 text-left">Level</th>
                            <th class="p-3 text-left">File</th>
                            <th class="p-3 text-left">Uploader</th>
                            <th class="p-3 text-left">Update</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php foreach ($materials as $m): ?>
                        <tr class="hover:bg-white/5">
                            <td class="p-3 text-yellow-400 font-bold"><?= htmlspecialchars(str_replace('_', ' ', $m['category'])) ?></td>
                            <td class="p-3 text-gray-200">Level <?= (int)$m['level'] ?></td>
                            <td class="p-3 text-gray-300 max-w-[240px] truncate" title="<?= htmlspecialchars($m['pdf_filename']) ?>">
                                <?= htmlspecialchars($m['pdf_filename']) ?>
                            </td>
                            <td class="p-3 text-gray-400"><?= htmlspecialchars($m['uploader_name'] ?? '-') ?></td>
                            <td class="p-3 text-gray-400 text-xs"><?= date('d M Y H:i', strtotime($m['updated_at'])) ?></td>
                            <td class="p-3 text-right">
                                <form method="POST"
                                      hx-post="admin_content.php?page=materi&action=delete_materi&id=<?= (int)$m['id'] ?>"
                                      hx-target="#main-content"
                                      hx-confirm="Hapus materi level ini?"
                                      style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit"
                                            class="bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded text-[10px] font-bold">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($materials)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500">Belum ada materi PDF.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($page === 'questions'):
    $questions = $pdo->query(
        "SELECT * FROM questions ORDER BY category, level, question_order ASC"
    )->fetchAll();
    $grouped = [];
    foreach ($questions as $q) {
        $grouped[$q['category']][$q['level']][] = $q;
    }
?>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Kelola Soal</h2>
        <a href="upload.php"
           class="bg-yellow-500 text-black px-4 py-2 rounded font-bold text-xs hover:bg-yellow-400 transition-transform active:scale-95">
            + Tambah Soal
        </a>
    </div>

    <div id="status-msg" class="mb-3"></div>

    <?php foreach ($grouped as $cat => $levels_data): ?>
    <div class="mb-8">
        <h3 class="text-base font-black text-yellow-400 uppercase mb-3">
            <?= htmlspecialchars(str_replace('_',' ', $cat)) ?>
        </h3>
        <?php foreach ($levels_data as $lvl_num => $soal_list): ?>
        <div class="mb-4 bg-gray-800/40 rounded-xl border border-white/10 overflow-hidden">

            <!-- Level header + slot bar -->
            <div class="flex items-center justify-between px-5 py-3 bg-white/5 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <span class="font-black text-white">Level <?= $lvl_num ?></span>
                    <div class="flex gap-0.5">
                        <?php for ($s = 1; $s <= 10; $s++):
                            $q_at_s = null;
                            foreach ($soal_list as $q) {
                                if ((int)$q['question_order'] === $s) { $q_at_s = $q; break; }
                            }
                            $bg = $q_at_s ? match($q_at_s['question_type'] ?? '') {
                                'pilihan_ganda' => 'background:#3b82f6',
                                'debugging'     => 'background:#ef4444',
                                'analisis'      => 'background:#f59e0b',
                                'logika'        => 'background:#a855f7',
                                default         => 'background:#6b7280',
                            } : 'background:#374151';
                        ?>
                        <div class="w-3 h-5 rounded-sm" style="<?= $bg ?>"
                             title="<?= $q_at_s ? 'Soal '.$s.': '.$q_at_s['question_type'] : 'Slot '.$s.' kosong' ?>">
                        </div>
                        <?php endfor; ?>
                    </div>
                    <span class="text-xs <?= count($soal_list) >= 10 ? 'text-green-400' : 'text-gray-400' ?>">
                        <?= count($soal_list) ?>/10 soal
                    </span>
                </div>
            </div>

            <!-- Soal list -->
            <div class="divide-y divide-white/5">
                <?php foreach ($soal_list as $q):
                    $qtype = $q['question_type'] ?? 'pilihan_ganda';
                    $tcls  = $type_colors[$qtype] ?? 'bg-gray-500/20 text-gray-300 border-gray-500/30';
                    $has_code = !empty(trim($q['code_snippet'] ?? ''));
                    $needs_code = in_array($qtype, ['debugging','analisis','logika'], true);
                ?>
                <div id="q-card-<?= $q['id'] ?>" class="p-5">
                    <div class="flex items-start gap-3">
                        <!-- Nomor + tipe badge -->
                        <div class="flex flex-col items-center gap-1 flex-shrink-0 pt-1">
                            <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center
                                        text-sm font-black text-white">
                                <?= (int)$q['question_order'] ?>
                            </div>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold border <?= $tcls ?> whitespace-nowrap">
                                <?= htmlspecialchars($qtype) ?>
                            </span>
                        </div>

                        <!-- Form edit -->
                        <div class="flex-1">
                            <form hx-post="admin_content.php?action=update_question&id=<?= $q['id'] ?>"
                                  hx-target="#status-msg" hx-swap="innerHTML" class="space-y-3">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                                <!-- Tipe soal -->
                                <div class="flex items-center gap-3 flex-wrap">
                                    <label class="text-[10px] text-gray-500 uppercase">Tipe:</label>
                                    <?php foreach ($valid_types as $vt):
                                        $vtcls = $type_colors[$vt] ?? '';
                                    ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="question_type" value="<?= $vt ?>"
                                               <?= $qtype === $vt ? 'checked' : '' ?>
                                               class="peer sr-only"
                                               onchange="toggleCodeField(<?= $q['id'] ?>, this.value)">
                                        <span class="text-[10px] px-2 py-1 rounded border font-bold cursor-pointer
                                                     transition-all opacity-50 peer-checked:opacity-100
                                                     <?= $vtcls ?>">
                                            <?= htmlspecialchars($vt) ?>
                                        </span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Instruksi -->
                                <div>
                                    <label class="text-[10px] text-gray-500 uppercase">Instruksi</label>
                                    <input type="text" name="instruction"
                                           value="<?= htmlspecialchars($q['instruction']) ?>"
                                           class="w-full bg-gray-900 border border-white/20 p-1.5 rounded text-xs
                                                  outline-none focus:border-yellow-500 mt-1">
                                </div>

                                <!-- Pertanyaan -->
                                <div>
                                    <label class="text-[10px] text-gray-500 uppercase">Pertanyaan</label>
                                    <textarea name="question" rows="2"
                                              class="w-full bg-gray-900 border border-white/20 p-1.5 rounded text-sm
                                                     outline-none focus:border-yellow-500 mt-1"><?= htmlspecialchars($q['question_text']) ?></textarea>
                                </div>

                                <!-- Code snippet (tampil/sembuny sesuai tipe) -->
                                <div id="code-field-<?= $q['id'] ?>"
                                     class="<?= $needs_code ? '' : 'hidden' ?>">
                                    <label class="text-[10px] text-gray-500 uppercase">
                                        Kode / Tabel
                                    </label>
                                    <textarea name="code_snippet" rows="5"
                                              style="font-family:monospace;font-size:11px;background:#1e1e2e;color:#cdd6f4;border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:8px;resize:vertical;width:100%;outline:none;margin-top:4px;"
                                              ><?= htmlspecialchars($q['code_snippet'] ?? '') ?></textarea>
                                </div>

                                <!-- Opsi -->
                                <div class="grid grid-cols-2 gap-2">
                                    <?php foreach (['a','b','c','d'] as $opt): ?>
                                    <div class="flex items-center gap-2">
                                        <span class="text-yellow-500 font-bold text-xs"><?= strtoupper($opt) ?>:</span>
                                        <input type="text" name="opt_<?= $opt ?>"
                                               value="<?= htmlspecialchars($q['option_' . $opt]) ?>"
                                               class="flex-1 bg-gray-900 border border-white/20 p-1 px-2 rounded
                                                      text-xs outline-none focus:border-blue-500">
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Footer -->
                                <div class="flex items-center justify-between pt-2 border-t border-white/5">
                                    <div class="flex items-center gap-2">
                                        <label class="text-[10px] text-gray-400">Kunci:</label>
                                        <select name="correct"
                                                class="bg-gray-900 border border-white/20 text-xs p-1 rounded
                                                       text-yellow-400 font-bold">
                                            <?php foreach (['A','B','C','D'] as $ans): ?>
                                            <option value="<?= $ans ?>" <?= $q['correct_answer'] === $ans ? 'selected':'' ?>>
                                                <?= $ans ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="submit"
                                                class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5
                                                       rounded text-[10px] font-black transition-transform active:scale-95">
                                            Simpan
                                        </button>
                                        <form method="POST"
                                              hx-post="admin_content.php?action=delete_question&id=<?= $q['id'] ?>"
                                              hx-target="#q-card-<?= $q['id'] ?>"
                                              hx-swap="outerHTML"
                                              hx-confirm="Hapus soal #<?= (int)$q['question_order'] ?>?"
                                              style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <button type="submit"
                                                    class="text-gray-500 hover:text-red-400 p-1.5 rounded
                                                           hover:bg-red-500/10 transition">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php if (empty($grouped)): ?>
    <div class="text-center text-gray-500 py-16">
        Belum ada soal.
        <a href="upload.php" class="text-yellow-400 hover:underline ml-1">Tambah sekarang →</a>
    </div>
    <?php endif; ?>

<script>
function toggleCodeField(qid, type) {
    const needsCode = ['debugging','analisis','logika'].includes(type);
    const field = document.getElementById('code-field-' + qid);
    if (field) field.classList.toggle('hidden', !needsCode);
}
</script>
<?php endif; ?>

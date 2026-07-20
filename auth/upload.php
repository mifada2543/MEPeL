<?php
session_name('mepel');
session_start();
require_once '../core/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); die("Akses ditolak.");
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$valid_categories = ['dasar_php', 'menengah_php', 'project_php'];
$valid_types      = ['pilihan_ganda', 'debugging', 'analisis', 'logika'];
$valid_modes      = ['abcd', 'iya_tidak'];
$max_soal         = 10;

$type_meta = [
    'pilihan_ganda' => ['label'=>'Pilihan Ganda','icon'=>'list',       'color'=>'blue',  'hint'=>'Soal teks biasa.','code_label'=>null,'code_ph'=>''],
    'debugging'     => ['label'=>'Debugging',    'icon'=>'bug',        'color'=>'red',   'hint'=>'Siswa melihat kode PHP dan mencari bug-nya.','code_label'=>'Kode PHP (yang mengandung bug)','code_ph'=>"<?php\n\$x = 10\necho \$x; // bug: missing semicolon"],
    'analisis'      => ['label'=>'Analisis Kode','icon'=>'search-code','color'=>'amber', 'hint'=>'Kode di kiri, pertanyaan di kanan.','code_label'=>'Kode PHP (untuk dianalisis)','code_ph'=>"<?php\n\$a = 10;\n\$b = 3;\necho \$a % \$b;"],
    'logika'        => ['label'=>'Logika',       'icon'=>'table-2',    'color'=>'purple','hint'=>'Tabel kebenaran. Format: Kolom1|Kolom2, satu baris per row.','code_label'=>'Tabel Logika (Kolom1|Kolom2)','code_ph'=>"Ekspresi|Hasil\n\$a && \$b|true\n!\$a|false"],
];

$success   = false;
$error_msg = '';
$saved_to  = ['cat'=>'','lvl'=>0,'order'=>0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        http_response_code(403); die('Request tidak valid.');
    }

    do {
        $category      = $_POST['category']      ?? '';
        $question_type = $_POST['question_type'] ?? 'pilihan_ganda';
        $answer_mode   = $_POST['answer_mode']   ?? 'abcd';

        if (!in_array($category,      $valid_categories, true)) { $error_msg = 'Kategori tidak valid.'; break; }
        if (!in_array($question_type, $valid_types,      true)) { $error_msg = 'Tipe tidak valid.';    break; }
        if (!in_array($answer_mode,   $valid_modes,      true)) { $answer_mode = 'abcd'; }

        $level = filter_var($_POST['level'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($level === false) { $error_msg = 'Level harus angka positif.'; break; }

        // Auto question_order
        $stmtOrd = $pdo->prepare("SELECT COALESCE(MAX(question_order),0)+1 FROM questions WHERE category=? AND level=?");
        $stmtOrd->execute([$category, $level]);
        $question_order = (int)$stmtOrd->fetchColumn();
        if ($question_order > $max_soal) { $error_msg = "Level $level sudah penuh ($max_soal soal)."; break; }

        $instruction = trim($_POST['instruction'] ?? '');
        $question    = trim($_POST['question']    ?? '');

        // Kode/tabel hanya untuk tipe yang membutuhkan
        $needs_code   = in_array($question_type, ['debugging','analisis','logika'], true);
        $code_snippet = $needs_code ? (trim($_POST['code_snippet'] ?? '') ?: null) : null;
        if ($needs_code && $code_snippet === null) {
            $error_msg = 'Tipe '.$question_type.' memerlukan konten kode/tabel.'; break;
        }

        // Validasi opsi & kunci jawaban per mode
        if ($answer_mode === 'iya_tidak') {
            // Mode IYA/TIDAK: opsi A-D tidak dipakai, kunci harus Y atau T
            $correct = strtoupper(trim($_POST['correct_yt'] ?? ''));
            if (!in_array($correct, ['Y','T'], true)) { $error_msg = 'Kunci jawaban harus IYA atau TIDAK.'; break; }
            // Simpan sebagai NULL di opsi — tidak ditampilkan di game
            $a = $b = $c = $d = null;
        } else {
            // Mode A-B-C-D biasa
            $correct = strtoupper(trim($_POST['correct'] ?? ''));
            $a = trim($_POST['opt_a'] ?? '');
            $b = trim($_POST['opt_b'] ?? '');
            $c = trim($_POST['opt_c'] ?? '');
            $d = trim($_POST['opt_d'] ?? '');
            if (!in_array($correct, ['A','B','C','D'], true)) { $error_msg = 'Kunci jawaban harus A/B/C/D.'; break; }
            if ($a===''||$b===''||$c===''||$d==='') { $error_msg = 'Semua opsi jawaban wajib diisi.'; break; }
        }

        if ($question==='') { $error_msg = 'Pertanyaan wajib diisi.'; break; }

        try {
            $pdo->prepare(
                "INSERT INTO questions
                     (category, level, question_order, question_type, answer_mode, instruction,
                      question_text, code_snippet, option_a, option_b, option_c, option_d, correct_answer)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $category, $level, $question_order, $question_type, $answer_mode,
                $instruction ?: '', $question, $code_snippet,
                $a, $b, $c, $d, $correct,
            ]);
            $success = true;
            $saved_to = ['cat'=>$category,'lvl'=>$level,'order'=>$question_order];
        } catch (PDOException $e) {
            error_log('[MEPeL Upload] '.$e->getMessage());
            $error_msg = 'Gagal menyimpan: ' . $e->getMessage();
        }
    } while (false);
}

// Slot data
$slot_data = [];
foreach ($valid_categories as $cat) {
    $slot_data[$cat] = [];
    $rows = $pdo->prepare("SELECT level, question_order, question_type, answer_mode FROM questions WHERE category=? ORDER BY level, question_order");
    $rows->execute([$cat]);
    foreach ($rows->fetchAll() as $r) {
        $l = (int)$r['level'];
        if (!isset($slot_data[$cat][$l])) {
            $slot_data[$cat][$l] = ['filled'=>0,'slots'=>array_fill(1,10,null)];
        }
        $o = (int)$r['question_order'];
        if ($o>=1&&$o<=10) {
            $slot_data[$cat][$l]['slots'][$o] = ['type'=>$r['question_type'],'mode'=>$r['answer_mode']];
            $slot_data[$cat][$l]['filled']++;
        }
    }
}

function next_slot(array $sd, string $cat): array {
    foreach ($sd[$cat] as $lvl => $info) {
        if ($info['filled'] < 10) return ['lvl'=>$lvl,'order'=>$info['filled']+1];
    }
    $max = !empty($sd[$cat]) ? max(array_keys($sd[$cat])) : 0;
    return ['lvl'=>$max+1,'order'=>1];
}

$slot_type_colors = ['pilihan_ganda'=>'#3b82f6','debugging'=>'#ef4444','analisis'=>'#f59e0b','logika'=>'#a855f7'];
$admin_name = htmlspecialchars($_SESSION['username'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEPeL — Tambah Soal</title>
    <script src="../assets/js/tailwind.js"></script>
    <script src="../assets/js/lucide.js"></script>
    <style>
        .journal-bg { background-color:#f4f1ea; background-image:linear-gradient(#e1d9c1 1px,transparent 1px); background-size:100% 2.5rem; }
        .slot-box   { width:24px;height:24px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;transition:all .15s; }
        .slot-empty { background:#374151;color:#6b7280; }
        .form-section { display:none; }
        .form-section.active { display:block;animation:fadeSlide .2s ease; }
        @keyframes fadeSlide { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
        .code-textarea { font-family:'Courier New',monospace;font-size:12px;line-height:1.6;background:#1e1e2e;color:#cdd6f4;border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:12px;resize:vertical;width:100%;outline:none; }
        .code-textarea:focus { border-color:rgba(255,255,255,.3); }
        .code-textarea::placeholder { color:#585b70; }
        .type-card { cursor:pointer;border:2px solid transparent;transition:all .15s;border-radius:10px;padding:10px 12px; }
        .type-card:hover { border-color:rgba(255,255,255,.2);background:rgba(255,255,255,.05); }
        .type-card.selected-blue   { border-color:#3b82f6;background:rgba(59,130,246,.1); }
        .type-card.selected-red    { border-color:#ef4444;background:rgba(239,68,68,.1); }
        .type-card.selected-amber  { border-color:#f59e0b;background:rgba(245,158,11,.1); }
        .type-card.selected-purple { border-color:#a855f7;background:rgba(168,85,247,.1); }

        /* Toggle switch */
        .toggle-switch { position:relative;width:44px;height:24px; }
        .toggle-switch input { opacity:0;width:0;height:0;position:absolute; }
        .toggle-track { position:absolute;inset:0;background:#374151;border-radius:12px;cursor:pointer;transition:.2s; }
        .toggle-track::after { content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;background:#fff;border-radius:50%;transition:.2s; }
        input:checked + .toggle-track { background:#14b8a6; }
        input:checked + .toggle-track::after { transform:translateX(20px); }

        /* IYA/TIDAK preview cards */
        .yt-preview { display:flex;gap:12px;justify-content:center; }
        .yt-card { width:100px;height:68px;border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;border:2px solid;font-weight:900;font-size:14px;letter-spacing:.05em; }
        .yt-iya  { border-color:#22c55e;color:#22c55e;background:rgba(34,197,94,.1); }
        .yt-tdk  { border-color:#ef4444;color:#ef4444;background:rgba(239,68,68,.1); }
    </style>
</head>
<body class="bg-gray-900 min-h-screen font-mono p-4">
<div class="max-w-6xl mx-auto flex flex-col gap-4">

    <!-- Topbar -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-white font-black text-xl">MEPeL — Tambah Soal</h1>
            <p class="text-gray-400 text-xs mt-0.5">Halo, <?= $admin_name ?>.</p>
        </div>
        <a href="admin.php" class="flex items-center gap-2 text-sm text-gray-300 hover:text-yellow-400">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Panel Admin
        </a>
    </div>

    <?php if ($success): ?>
    <div class="bg-green-900/40 border border-green-600 text-green-300 rounded-lg p-4 flex items-center gap-3">
        <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i>
        <div>
            <p class="font-bold">Soal berhasil disimpan!</p>
            <p class="text-xs mt-0.5 text-green-400">
                Kategori: <b><?= htmlspecialchars($saved_to['cat']) ?></b>
                · Level <b><?= $saved_to['lvl'] ?></b>
                · Soal ke-<b><?= $saved_to['order'] ?></b>
            </p>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="bg-red-900/40 border border-red-600 text-red-300 rounded-lg p-4 flex items-center gap-3">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
        <p><?= htmlspecialchars($error_msg) ?></p>
    </div>
    <?php endif; ?>

    <div class="flex gap-4 items-start">

        <!-- Panel kiri: slot overview -->
        <div class="w-72 flex-shrink-0 bg-gray-800 rounded-xl border border-white/10 overflow-hidden sticky top-4">
            <div class="px-4 py-3 border-b border-white/10">
                <p class="text-white font-bold text-sm">Status Slot</p>
                <p class="text-gray-400 text-xs mt-0.5">
                    Kotak berwarna = terisi. Kotak dengan lingkaran = mode IYA/TIDAK.
                </p>
            </div>
            <!-- Legend -->
            <div class="px-4 py-2 border-b border-white/5 flex flex-wrap gap-2">
                <?php foreach ($type_meta as $tk=>$tm): ?>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 rounded-sm" style="background:<?= $slot_type_colors[$tk]??'#888' ?>"></div>
                    <span class="text-[10px] text-gray-400"><?= $tm['label'] ?></span>
                </div>
                <?php endforeach; ?>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 rounded-full bg-teal-400"></div>
                    <span class="text-[10px] text-teal-400">IYA/TIDAK</span>
                </div>
            </div>
            <!-- Tab kategori -->
            <div class="flex border-b border-white/10">
                <?php foreach ($valid_categories as $i=>$cat): ?>
                <button onclick="switchCat('<?= $cat ?>')" id="tab-<?= $cat ?>"
                    class="flex-1 py-2 text-[10px] font-bold uppercase transition-colors
                           <?= $i===0?'bg-white/10 text-yellow-400':'text-gray-400 hover:text-white' ?>">
                    <?= str_replace('_php','',$cat) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <!-- Slot grids -->
            <?php foreach ($valid_categories as $cat): ?>
            <div id="slots-<?= $cat ?>" class="p-3 <?= $cat!=='dasar_php'?'hidden':'' ?>">
                <?php if (empty($slot_data[$cat])): ?>
                    <p class="text-gray-500 text-xs text-center py-4">Belum ada soal.</p>
                <?php else: ?>
                    <?php foreach ($slot_data[$cat] as $lvl=>$info): ?>
                    <div class="mb-3">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold text-white">Level <?= $lvl ?></span>
                            <span class="text-[10px] <?= $info['filled']>=10?'text-green-400':'text-gray-400' ?>">
                                <?= $info['filled'] ?>/10<?= $info['filled']>=10?' ✓':'' ?>
                            </span>
                        </div>
                        <div class="flex gap-0.5">
                            <?php for ($s=1;$s<=10;$s++):
                                $slot = $info['slots'][$s] ?? null;
                                $bg   = $slot ? ($slot_type_colors[$slot['type']]??'#888') : '';
                                $style= $slot ? "background:{$bg};" : '';
                                $is_yt= $slot && $slot['mode']==='iya_tidak';
                                $title= $slot ? "Soal $s: {$slot['type']}" . ($is_yt?' (IYA/TIDAK)':'') : "Soal $s: kosong";
                            ?>
                            <div class="slot-box <?= !$slot?'slot-empty':'' ?>"
                                 style="<?= $style ?>" title="<?= $title ?>">
                                <?php if ($is_yt): ?>
                                    <div class="w-2 h-2 rounded-full bg-teal-300"></div>
                                <?php else: ?>
                                    <?= $s ?>
                                <?php endif; ?>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php $ns = next_slot($slot_data, $cat); ?>
                <div class="mt-2 p-2.5 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
                    <p class="text-[10px] text-yellow-400 font-bold mb-0.5">Slot berikutnya:</p>
                    <p class="text-yellow-300 font-black text-sm">Level <?= $ns['lvl'] ?> · Soal #<?= $ns['order'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Form utama -->
        <div class="flex-1 bg-gray-800 rounded-xl border border-white/10 overflow-hidden">
            <div class="bg-gray-900/60 px-6 py-4 border-b border-white/10">
                <h2 class="text-white font-black text-base flex items-center gap-2">
                    <i data-lucide="plus-circle" class="w-5 h-5 text-yellow-400"></i>
                    Tambah Soal Baru
                </h2>
                <p class="text-gray-400 text-xs mt-0.5">
                    Soal ke-<span id="order-preview" class="text-yellow-400 font-bold">?</span>
                    dari 10 · Level <span id="lvl-preview" class="text-yellow-400 font-bold">?</span>
                </p>
            </div>

            <div class="p-6">
                <form method="POST" id="soal-form" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <!-- Row 1: Kategori + Level -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Kategori</label>
                            <select name="category" id="catSelect" required
                                class="w-full bg-gray-900 border border-white/20 rounded-lg px-3 py-2 text-sm font-bold text-white outline-none focus:border-yellow-500">
                                <?php foreach ($valid_categories as $cat): ?>
                                <option value="<?= $cat ?>"><?= htmlspecialchars(str_replace('_',' ', ucwords($cat,'_'))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Level</label>
                            <input type="number" name="level" id="lvlInput" required min="1"
                                class="w-full bg-gray-900 border border-white/20 rounded-lg px-3 py-2 text-xl font-black text-white outline-none focus:border-yellow-500">
                        </div>
                    </div>

                    <!-- Row 2: Tipe soal -->
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-2">Tipe Soal</label>
                        <div class="grid grid-cols-4 gap-2" id="type-cards">
                            <?php foreach ($type_meta as $tk=>$tm):
                                $icon_color=['pilihan_ganda'=>'text-blue-400','debugging'=>'text-red-400','analisis'=>'text-amber-400','logika'=>'text-purple-400'][$tk]??'text-gray-400';
                            ?>
                            <label class="type-card <?= $tk==='pilihan_ganda'?'selected-blue':'' ?>"
                                   id="card-<?= $tk ?>" data-type="<?= $tk ?>" data-color="<?= explode('-',$icon_color)[1] ?>">
                                <input type="radio" name="question_type" value="<?= $tk ?>"
                                       <?= $tk==='pilihan_ganda'?'checked':'' ?> class="sr-only">
                                <div class="flex flex-col items-center gap-1.5 text-center">
                                    <i data-lucide="<?= $tm['icon'] ?>" class="w-5 h-5 <?= $icon_color ?>"></i>
                                    <span class="text-xs font-bold text-white leading-tight"><?= $tm['label'] ?></span>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p id="type-hint" class="text-xs text-gray-400 mt-2 italic">
                            <?= $type_meta['pilihan_ganda']['hint'] ?>
                        </p>
                    </div>

                    <!-- Row 3: Toggle mode IYA/TIDAK -->
                    <div class="flex items-center justify-between bg-gray-900/50 border border-white/10
                                rounded-xl px-5 py-4">
                        <div>
                            <p class="text-sm font-bold text-white flex items-center gap-2">
                                Mode Jawaban IYA / TIDAK
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5" id="mode-hint">
                                Nonaktif — siswa menjawab dengan memilih opsi A/B/C/D.
                            </p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="modeToggle" onchange="onModeChange()">
                            <span class="toggle-track"></span>
                        </label>
                        <!-- Hidden input yang dikirim ke server -->
                        <input type="hidden" name="answer_mode" id="answerModeInput" value="abcd">
                    </div>

                    <!-- Section: field kode/tabel -->
                    <div id="section-code" class="form-section">
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1" id="code-label">Kode</label>
                        <textarea name="code_snippet" id="codeInput" rows="8" class="code-textarea"
                                  placeholder="Tulis kode di sini..."></textarea>
                    </div>

                    <!-- Instruksi + Pertanyaan -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">
                                Instruksi / Panduan
                                <span class="normal-case text-gray-600 font-normal ml-1">(opsional)</span>
                            </label>
                            <textarea name="instruction" rows="3" placeholder="Petunjuk tambahan untuk siswa — boleh dikosongkan..."
                                class="w-full bg-gray-900 border border-white/20 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-yellow-500 resize-none leading-relaxed"></textarea>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Pertanyaan</label>
                            <textarea name="question" rows="3" required placeholder="Tuliskan soalnya..."
                                class="w-full bg-gray-900 border border-white/20 rounded-lg px-3 py-2 text-sm font-bold text-white outline-none focus:border-yellow-500 resize-none leading-relaxed"></textarea>
                        </div>
                    </div>

                    <!-- ── Section A-B-C-D (default) ── -->
                    <div id="section-abcd">
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-2">Pilihan Jawaban</label>
                        <div class="grid grid-cols-2 gap-3">
                            <?php foreach (['a','b','c','d'] as $opt): ?>
                            <div class="flex items-center gap-2 bg-gray-900/50 border border-white/10 rounded-lg
                                        px-3 py-2 focus-within:border-white/30 transition-colors">
                                <span class="text-yellow-500 font-black text-sm w-4"><?= strtoupper($opt) ?></span>
                                <input type="text" name="opt_<?= $opt ?>"
                                       placeholder="Pilihan <?= strtoupper($opt) ?>..."
                                       class="flex-1 bg-transparent text-sm text-white outline-none placeholder-gray-600">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ── Section IYA/TIDAK (muncul saat toggle aktif) ── -->
                    <div id="section-yt" class="hidden">
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-3">Preview Tampilan</label>
                        <div class="yt-preview mb-4">
                            <div class="yt-card yt-iya">
                                <i data-lucide="check-circle" style="width:22px;height:22px"></i>
                                IYA
                            </div>
                            <div class="yt-card yt-tdk">
                                <i data-lucide="x-circle" style="width:22px;height:22px"></i>
                                TIDAK
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 text-center">
                            Opsi A/B/C/D tidak ditampilkan. Siswa hanya klik satu dari dua tombol ini.
                        </p>
                    </div>

                    <!-- ── Submit: kunci jawaban berubah per mode ── -->
                    <div class="flex items-center justify-between pt-3 border-t border-white/10">

                        <!-- Kunci A-D (mode abcd) -->
                        <div id="correct-abcd" class="flex items-center gap-3">
                            <label class="text-[10px] font-bold text-gray-400 uppercase">Kunci Jawaban:</label>
                            <div class="flex gap-1">
                                <?php foreach (['A','B','C','D'] as $ans): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="correct" value="<?= $ans ?>"
                                           <?= $ans==='A'?'checked':'' ?> class="peer sr-only">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center font-black text-sm
                                                border-2 border-white/20 text-gray-400
                                                peer-checked:border-yellow-400 peer-checked:text-yellow-400
                                                peer-checked:bg-yellow-400/10 transition-all cursor-pointer">
                                        <?= $ans ?>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Kunci IYA/TIDAK (mode iya_tidak) -->
                        <div id="correct-yt" class="hidden flex items-center gap-3">
                            <label class="text-[10px] font-bold text-gray-400 uppercase">Jawaban Benar:</label>
                            <div class="flex gap-2">
                                <label class="cursor-pointer">
                                    <input type="radio" name="correct_yt" value="Y" checked class="peer sr-only">
                                    <div class="px-5 py-2 rounded-lg flex items-center gap-2 font-black text-sm
                                                border-2 border-green-500/40 text-green-500/60
                                                peer-checked:border-green-400 peer-checked:text-green-400
                                                peer-checked:bg-green-400/10 transition-all cursor-pointer">
                                        <i data-lucide="check-circle" class="w-4 h-4"></i> IYA
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="correct_yt" value="T" class="peer sr-only">
                                    <div class="px-5 py-2 rounded-lg flex items-center gap-2 font-black text-sm
                                                border-2 border-red-500/40 text-red-500/60
                                                peer-checked:border-red-400 peer-checked:text-red-400
                                                peer-checked:bg-red-400/10 transition-all cursor-pointer">
                                        <i data-lucide="x-circle" class="w-4 h-4"></i> TIDAK
                                    </div>
                                </label>
                            </div>
                        </div>

                        <button type="submit"
                            class="bg-yellow-500 hover:bg-yellow-400 text-black px-8 py-2.5 rounded-xl
                                   font-black text-sm transition-all hover:-translate-y-0.5 shadow
                                   flex items-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Soal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
lucide.createIcons();

const slotData  = <?= json_encode($slot_data, JSON_HEX_TAG) ?>;
const typeMeta  = <?= json_encode(array_map(fn($t)=>['hint'=>$t['hint'],'code_label'=>$t['code_label'],'code_ph'=>$t['code_ph']], $type_meta), JSON_HEX_TAG) ?>;

const catSelect    = document.getElementById('catSelect');
const lvlInput     = document.getElementById('lvlInput');
const orderPreview = document.getElementById('order-preview');
const lvlPreview   = document.getElementById('lvl-preview');
const typeHint     = document.getElementById('type-hint');
const sectionCode  = document.getElementById('section-code');
const codeLabel    = document.getElementById('code-label');
const codeInput    = document.getElementById('codeInput');
const modeToggle   = document.getElementById('modeToggle');
const modeInput    = document.getElementById('answerModeInput');
const modeHint     = document.getElementById('mode-hint');

// ── Toggle IYA/TIDAK ───────────────────────────────────────────────────
function onModeChange() {
    const isYT = modeToggle.checked;
    modeInput.value = isYT ? 'iya_tidak' : 'abcd';

    document.getElementById('section-abcd').classList.toggle('hidden', isYT);
    document.getElementById('section-yt').classList.toggle('hidden', !isYT);
    document.getElementById('correct-abcd').classList.toggle('hidden', isYT);
    document.getElementById('correct-yt').classList.toggle('hidden', !isYT);

    modeHint.textContent = isYT
        ? 'Aktif — siswa menjawab dengan menekan tombol IYA atau TIDAK.'
        : 'Nonaktif — siswa menjawab dengan memilih opsi A/B/C/D.';

    lucide.createIcons();
}

// ── Pilih tipe soal ────────────────────────────────────────────────────
let activeType = 'pilihan_ganda';
document.querySelectorAll('.type-card').forEach(card => {
    card.addEventListener('click', () => {
        const type  = card.dataset.type;
        const color = card.dataset.color;
        activeType  = type;

        document.querySelectorAll('.type-card').forEach(c => {
            c.className = c.className.replace(/selected-\w+/g,'').trim();
        });
        card.querySelector('input[type=radio]').checked = true;
        card.classList.add('selected-'+color);

        const meta = typeMeta[type];
        typeHint.textContent = meta.hint;

        if (meta.code_label) {
            sectionCode.classList.add('active');
            codeLabel.textContent = meta.code_label;
            codeInput.placeholder = meta.code_ph;
            codeInput.value       = '';
        } else {
            sectionCode.classList.remove('active');
            codeInput.value = '';
        }
    });
});

// ── Tab kategori ───────────────────────────────────────────────────────
function switchCat(cat) {
    document.querySelectorAll('[id^="tab-"]').forEach(t=>{
        t.classList.remove('bg-white/10','text-yellow-400');
        t.classList.add('text-gray-400');
    });
    const tab = document.getElementById('tab-'+cat);
    if (tab) { tab.classList.remove('text-gray-400'); tab.classList.add('bg-white/10','text-yellow-400'); }
    document.querySelectorAll('[id^="slots-"]').forEach(p=>p.classList.add('hidden'));
    const panel = document.getElementById('slots-'+cat);
    if (panel) panel.classList.remove('hidden');
    if (catSelect.value !== cat) catSelect.value = cat;
    updateSlotPreview();
}

catSelect.addEventListener('change', () => { switchCat(catSelect.value); autoSuggestLevel(); });
lvlInput.addEventListener('input', updateSlotPreview);

function updateSlotPreview() {
    const cat = catSelect.value;
    const lvl = parseInt(lvlInput.value) || 0;
    lvlPreview.textContent = lvl || '?';
    if (!lvl || !slotData[cat]?.[lvl]) { orderPreview.textContent = '1'; return; }
    const filled = slotData[cat][lvl].filled;
    orderPreview.textContent = filled >= 10 ? '— PENUH' : filled + 1;
}

function autoSuggestLevel() {
    const cat = catSelect.value;
    let suggest = 1;
    if (slotData[cat]) {
        const levels = Object.keys(slotData[cat]).map(Number).sort((a,b)=>a-b);
        for (const l of levels) {
            if (slotData[cat][l].filled < 10) { suggest = l; break; }
            suggest = l + 1;
        }
    }
    lvlInput.value = suggest;
    updateSlotPreview();
}

autoSuggestLevel();
updateSlotPreview();
</script>
</body>
</html>
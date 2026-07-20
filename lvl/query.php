<?php
if (!defined('SOAL_PER_LEVEL')) define('SOAL_PER_LEVEL', 10);

$csrf          = csrf_token();
$current_order = (int)($_SESSION['game_order'] ?? 1);
$total_soal    = (int)($_SESSION['game_total'] ?? SOAL_PER_LEVEL);
$score_now     = (int)($_SESSION['game_score'] ?? 0);
$lvl_num       = (int)$data['level'];
$qid           = (int)$data['id'];
$qtype         = $data['question_type'] ?? 'pilihan_ganda';
$code          = $data['code_snippet']  ?? '';
$answer_mode   = $data['answer_mode']   ?? 'abcd';   // 'abcd' | 'iya_tidak'

$type_meta = [
    'pilihan_ganda' => ['label' => 'Pilihan Ganda',  'color' => 'bg-blue-500/20 text-blue-300',   'border' => 'border-blue-500/30'],
    'debugging'     => ['label' => 'Debugging',      'color' => 'bg-red-500/20 text-red-300',     'border' => 'border-red-500/30'],
    'analisis'      => ['label' => 'Analisis Kode',  'color' => 'bg-amber-500/20 text-amber-300', 'border' => 'border-amber-500/30'],
    'logika'        => ['label' => 'Logika',         'color' => 'bg-purple-500/20 text-purple-300','border' => 'border-purple-500/30'],
];
$meta = $type_meta[$qtype] ?? $type_meta['pilihan_ganda'];

// Payload HTMX dasar — __ANS__ diganti saat render
$hx_base = [
    'ans'        => '__ANS__',
    'lvl'        => (string)$lvl_num,
    'cat'        => $data['category'],
    'qid'        => (string)$qid,
    'csrf_token' => $csrf,
];

// ── Helper: render tombol A-D (mode abcd) ──────────────────────────────────
function render_abcd(array $data, array $hx_base, string $extra = ''): void {
    $opts = ['A' => $data['option_a'], 'B' => $data['option_b'],
             'C' => $data['option_c'], 'D' => $data['option_d']];
    foreach ($opts as $k => $v) {
        if (trim((string)$v) === '') continue;
        $payload = json_encode(array_merge($hx_base, ['ans' => $k]));
        echo '<button
            hx-post="core/logic.php"
            hx-vals=\''.htmlspecialchars($payload, ENT_QUOTES).'\'
            hx-target="#content" hx-swap="innerHTML transition:true"
            class="group relative bg-white/5 border-2 border-white/40 p-3 rounded-lg
                   hover:bg-white hover:text-green-900 hover:border-white
                   transition-all duration-200 text-left overflow-hidden '.$extra.'">
            <span class="absolute right-2 top-1.5 text-xs font-black opacity-20
                         group-hover:opacity-100 pointer-events-none">'.$k.'</span>
            <p class="font-bold text-sm pointer-events-none pr-5">'.htmlspecialchars((string)$v).'</p>
        </button>';
    }
}

// ── Helper: render 2 tombol besar IYA / TIDAK ──────────────────────────────
function render_iya_tidak(array $hx_base): void {
    $btns = [
        'Y' => [
            'label' => 'IYA',
            'icon'  => 'check-circle',
            'cls'   => 'border-green-500/60 hover:bg-green-500 hover:border-green-400 text-green-400 hover:text-white',
            'bg'    => 'bg-green-500/10',
        ],
        'T' => [
            'label' => 'TIDAK',
            'icon'  => 'x-circle',
            'cls'   => 'border-red-500/60 hover:bg-red-500 hover:border-red-400 text-red-400 hover:text-white',
            'bg'    => 'bg-red-500/10',
        ],
    ];

    echo '<div class="flex gap-6 justify-center">';
    foreach ($btns as $k => $b) {
        $payload = json_encode(array_merge($hx_base, ['ans' => $k]));
        echo '<button
            hx-post="core/logic.php"
            hx-vals=\''.htmlspecialchars($payload, ENT_QUOTES).'\'
            hx-target="#content" hx-swap="innerHTML transition:true"
            class="flex flex-col items-center justify-center gap-3
                   w-40 h-32 rounded-2xl border-2 font-black text-2xl
                   transition-all duration-200 active:scale-95
                   '.$b['bg'].' '.$b['cls'].'">
            <i data-lucide="'.$b['icon'].'" class="w-10 h-10 pointer-events-none"></i>
            <span class="pointer-events-none tracking-widest">'.$b['label'].'</span>
        </button>';
    }
    echo '</div>';
}
?>

<div class="flex flex-col w-full h-full font-mono text-white select-none">

    <!-- ── Header: progress dots + badge tipe + mode badge ── -->
    <div class="flex items-center gap-2 px-5 pt-3 pb-2 border-b border-white/10 flex-shrink-0">
        <div class="flex gap-1 flex-wrap">
            <?php for ($i = 1; $i <= $total_soal; $i++): ?>
                <div class="w-5 h-5 rounded-full text-[9px] flex items-center justify-center font-bold border
                    <?= $i < $current_order
                        ? 'bg-green-500 border-green-400 text-white'
                        : ($i === $current_order
                            ? 'bg-yellow-400 border-yellow-300 text-black animate-pulse'
                            : 'bg-white/10 border-white/20 text-white/30') ?>">
                    <?= $i ?>
                </div>
            <?php endfor; ?>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <button
                hx-get="lvl/menu.php?cat=<?= urlencode((string)$data['category']) ?>"
                hx-target="#content"
                class="px-2.5 py-1 rounded-full text-[10px] font-bold
                       bg-white/5 text-gray-300 border border-white/20
                       hover:bg-white/10 hover:text-white transition-colors">
                Kembali ke Peta
            </button>
            <?php if ($answer_mode === 'iya_tidak'): ?>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold
                             bg-teal-500/20 text-teal-300 border border-teal-500/30">
                    IYA / TIDAK
                </span>
            <?php endif; ?>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold
                         <?= $meta['color'] ?> border <?= $meta['border'] ?>">
                <?= $meta['label'] ?>
            </span>
            <span class="text-xs text-gray-400">
                ✓ <span class="text-green-400 font-bold"><?= $score_now ?></span>
                <span class="text-white/30">/ <?= $current_order - 1 ?></span>
            </span>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TIPE 1: PILIHAN GANDA
    ══════════════════════════════════════════════════════ -->
    <?php if ($qtype === 'pilihan_ganda'): ?>
    <div class="flex flex-1 min-h-0 p-5 gap-5">
        <div class="w-1/3 flex flex-col border-r border-white/10 pr-5">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">
                Lvl <?= $lvl_num ?> · Soal <?= $current_order ?>/<?= $total_soal ?>
            </p>
            <p class="text-xs font-bold text-white mb-2">Panduan:</p>
            <p class="text-sm text-gray-300 italic leading-relaxed flex-1 overflow-y-auto">
                <?= nl2br(htmlspecialchars($data['instruction'])) ?>
            </p>
        </div>
        <div class="flex-1 flex flex-col justify-center">
            <h4 class="text-xl font-bold text-center mb-7 leading-snug px-4">
                <?= htmlspecialchars($data['question_text']) ?>
            </h4>
            <?php if ($answer_mode === 'iya_tidak'): ?>
                <?php render_iya_tidak($hx_base); ?>
            <?php else: ?>
                <div class="grid grid-cols-2 gap-3">
                    <?php render_abcd($data, $hx_base); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TIPE 2: DEBUGGING
    ══════════════════════════════════════════════════════ -->
    <?php elseif ($qtype === 'debugging'): ?>
    <div class="flex flex-col flex-1 min-h-0 p-4 gap-3">
        <p class="text-sm font-bold text-red-300 flex items-center gap-2 flex-shrink-0">
            <i data-lucide="bug" class="w-4 h-4"></i>
            <?= htmlspecialchars($data['question_text']) ?>
        </p>
        <div class="flex-1 min-h-0 rounded-lg overflow-hidden border border-red-500/30 bg-[#1e1e2e] flex flex-col">
            <div class="flex items-center gap-2 px-4 py-2 bg-[#181825] border-b border-white/5 flex-shrink-0">
                <span class="w-3 h-3 rounded-full bg-red-500/70"></span>
                <span class="w-3 h-3 rounded-full bg-yellow-500/70"></span>
                <span class="w-3 h-3 rounded-full bg-green-500/70"></span>
                <span class="text-[10px] text-gray-500 ml-2 font-mono">script.php</span>
            </div>
            <div class="overflow-auto flex-1 p-4">
                <table class="text-sm font-mono w-full border-collapse">
                    <?php foreach (explode("\n", $code) as $i => $line):
                        $is_bug = preg_match('/\/\/\s*(bug|error|salah|fix|!)/i', $line) || str_contains($line, '// ?');
                    ?>
                    <tr class="<?= $is_bug ? 'bg-red-500/10' : '' ?>">
                        <td class="text-gray-600 pr-4 text-right select-none w-8 leading-6"><?= $i+1 ?></td>
                        <td class="text-gray-100 leading-6 whitespace-pre"><?= htmlspecialchars($line) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <p class="text-[10px] text-gray-500 italic flex-shrink-0">
            💡 <?= htmlspecialchars($data['instruction']) ?>
        </p>
        <?php if ($answer_mode === 'iya_tidak'): ?>
            <?php render_iya_tidak($hx_base); ?>
        <?php else: ?>
            <div class="grid grid-cols-4 gap-2 flex-shrink-0">
                <?php render_abcd($data, $hx_base, 'text-center !text-xs'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TIPE 3: ANALISIS KODE
    ══════════════════════════════════════════════════════ -->
    <?php elseif ($qtype === 'analisis'): ?>
    <div class="flex flex-1 min-h-0 p-4 gap-4">
        <div class="w-1/2 flex flex-col rounded-lg overflow-hidden border border-amber-500/30 bg-[#1e1e2e]">
            <div class="flex items-center gap-2 px-3 py-1.5 bg-[#181825] border-b border-white/5 flex-shrink-0">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500/70"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-yellow-500/70"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-green-500/70"></span>
                <span class="text-[10px] text-gray-500 ml-2">analisis.php</span>
            </div>
            <div class="overflow-auto flex-1 p-3">
                <table class="text-xs font-mono w-full border-collapse">
                    <?php foreach (explode("\n", $code) as $i => $line): ?>
                    <tr>
                        <td class="text-gray-600 pr-3 text-right select-none w-6 leading-5"><?= $i+1 ?></td>
                        <td class="text-gray-100 leading-5 whitespace-pre"><?= htmlspecialchars($line) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <div class="w-1/2 flex flex-col justify-between">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-amber-500/70 font-bold mb-2">
                    Analisis Kode · Soal <?= $current_order ?>/<?= $total_soal ?>
                </p>
                <h4 class="text-base font-bold leading-snug mb-3">
                    <?= htmlspecialchars($data['question_text']) ?>
                </h4>
                <p class="text-xs text-gray-400 italic mb-4">
                    <?= htmlspecialchars($data['instruction']) ?>
                </p>
            </div>
            <?php if ($answer_mode === 'iya_tidak'): ?>
                <?php render_iya_tidak($hx_base); ?>
            <?php else: ?>
                <div class="grid grid-cols-2 gap-2">
                    <?php render_abcd($data, $hx_base, '!text-sm'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TIPE 4: LOGIKA
    ══════════════════════════════════════════════════════ -->
    <?php elseif ($qtype === 'logika'): ?>
    <div class="flex flex-col flex-1 min-h-0 p-5 gap-4">
        <div class="text-center flex-shrink-0">
            <p class="text-[10px] uppercase tracking-widest text-purple-400/70 font-bold mb-1">
                Logika · Soal <?= $current_order ?>/<?= $total_soal ?>
            </p>
            <h4 class="text-lg font-bold leading-snug">
                <?= htmlspecialchars($data['question_text']) ?>
            </h4>
        </div>

        <?php if (!empty(trim($code))):
            $rows_raw    = array_filter(array_map('trim', explode("\n", trim($code))));
            $rows_parsed = array_map(fn($r) => array_map('trim', explode('|', $r)), $rows_raw);
            $header      = array_shift($rows_parsed);
        ?>
        <div class="flex-1 flex items-center justify-center min-h-0">
            <div class="rounded-xl overflow-hidden border border-purple-500/30 w-full max-w-lg">
                <table class="w-full text-sm text-center">
                    <thead>
                        <tr class="bg-purple-900/40">
                            <?php foreach ($header as $h): ?>
                                <th class="py-2 px-4 font-bold text-purple-300 border-b border-purple-500/20">
                                    <?= htmlspecialchars($h) ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows_parsed as $ri => $row): ?>
                        <tr class="<?= $ri%2===0 ? 'bg-white/5' : 'bg-white/[0.02]' ?> border-b border-white/5 last:border-0">
                            <?php foreach ($row as $ci => $cell):
                                $cl = strtolower(trim($cell));
                                $cc = match(true) {
                                    in_array($cl, ['true','1','ya','yes','benar'])    => 'text-green-400 font-bold',
                                    in_array($cl, ['false','0','tidak','no','salah']) => 'text-red-400 font-bold',
                                    $ci === 0 => 'text-yellow-300 font-mono font-bold',
                                    default   => 'text-gray-200',
                                };
                            ?>
                            <td class="py-2 px-4 <?= $cc ?>"><?= htmlspecialchars($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-[10px] text-gray-500 italic text-center flex-shrink-0">
            💡 <?= htmlspecialchars($data['instruction']) ?>
        </p>
        <?php else: ?>
        <div class="flex-1 flex items-center justify-center">
            <p class="text-gray-400 italic text-sm text-center max-w-sm">
                <?= nl2br(htmlspecialchars($data['instruction'])) ?>
            </p>
        </div>
        <?php endif; ?>

        <?php if ($answer_mode === 'iya_tidak'): ?>
            <?php render_iya_tidak($hx_base); ?>
        <?php else: ?>
            <div class="grid grid-cols-4 gap-2 flex-shrink-0">
                <?php render_abcd($data, $hx_base, 'text-center justify-center'); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script>lucide.createIcons();</script>

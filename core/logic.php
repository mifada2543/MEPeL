<?php
session_name('mepel');
session_start();
require_once 'db.php';

// ─── Konstanta ────────────────────────────────────────────────────────────
const SOAL_PER_LEVEL   = 10;
const VALID_CATEGORIES = ['dasar_php', 'menengah_php', 'project_php'];
// A-D untuk mode abcd, Y/T untuk mode iya_tidak
const VALID_ANSWERS    = ['A', 'B', 'C', 'D', 'Y', 'T'];

// ─── CSRF ─────────────────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_validate(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Request tidak valid.');
    }
}

// ─── Auth guard ────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "<div class='text-center p-8'>
            <p class='text-red-300 mb-4'>Sesi habis.</p>
            <a href='auth/login.php' class='bg-yellow-500 text-black px-6 py-2 rounded font-bold'>Login Ulang</a>
          </div>";
    exit;
}

// ─── Sanitasi input ────────────────────────────────────────────────────────
$category = $_REQUEST['cat'] ?? 'dasar_php';
if (!in_array($category, VALID_CATEGORIES, true)) $category = 'dasar_php';

// ─── Session init & reset jika ganti kategori ─────────────────────────────
if (($_SESSION['game_cat'] ?? '') !== $category) {
    unset($_SESSION['game_score'], $_SESSION['game_order'], $_SESSION['game_total'],
          $_SESSION['game_lvl'],   $_SESSION['game_ids']);
    $_SESSION['game_cat'] = $category;
}

// ─── Helper: ambil soal ke-N ───────────────────────────────────────────────
function get_soal(PDO $pdo, string $cat, int $lvl, int $order): array|false {
    $stmt = $pdo->prepare(
        "SELECT * FROM questions
         WHERE category = ? AND level = ? AND question_order = ?
         LIMIT 1"
    );
    $stmt->execute([$cat, $lvl, $order]);
    return $stmt->fetch();
}

// ─── Helper: hitung total soal ─────────────────────────────────────────────
function count_soal(PDO $pdo, string $cat, int $lvl): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE category = ? AND level = ?");
    $stmt->execute([$cat, $lvl]);
    return min((int)$stmt->fetchColumn(), SOAL_PER_LEVEL);
}

// ─── Helper: simpan progress ───────────────────────────────────────────────
function save_progress(PDO $pdo, int $user_id, string $cat, int $lvl, bool $passed): void {
    $check = $pdo->prepare(
        "SELECT id, is_completed FROM user_progress
         WHERE user_id = ? AND category_slug = ? AND level_reached = ?"
    );
    $check->execute([$user_id, $cat, $lvl]);
    $existing = $check->fetch();

    if (!$existing) {
        $pdo->prepare(
            "INSERT INTO user_progress (user_id, category_slug, is_completed, level_reached)
             VALUES (?, ?, ?, ?)"
        )->execute([$user_id, $cat, $passed ? 1 : 0, $lvl]);
    } elseif ($passed && !$existing['is_completed']) {
        $pdo->prepare(
            "UPDATE user_progress SET is_completed = 1
             WHERE user_id = ? AND category_slug = ? AND level_reached = ?"
        )->execute([$user_id, $cat, $lvl]);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// GET — Mulai / Reset level
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $lvl = max(1, (int)($_GET['lvl'] ?? 1));

    $_SESSION['game_score'] = 0;
    $_SESSION['game_order'] = 1;
    $_SESSION['game_lvl']   = $lvl;
    $_SESSION['game_total'] = count_soal($pdo, $category, $lvl);
    $_SESSION['game_ids']   = [];

    if ($_SESSION['game_total'] === 0) {
        echo "<div class='text-center p-8 text-gray-400'>
                <p class='text-2xl mb-2'>⚠️</p>
                <p>Belum ada soal untuk level ini.</p>
              </div>";
        exit;
    }

    $data = get_soal($pdo, $category, $lvl, 1);
    if ($data) {
        $_SESSION['game_ids'][] = (int)$data['id'];
        include '../lvl/query.php';
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// POST — Proses jawaban
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $user_ans   = strtoupper(trim($_POST['ans'] ?? ''));
    $posted_lvl = max(1, (int)($_POST['lvl']    ?? 1));
    $posted_cat = $_POST['cat'] ?? 'dasar_php';
    if (!in_array($posted_cat, VALID_CATEGORIES, true)) $posted_cat = 'dasar_php';

    // Validasi jawaban — terima A-D (abcd) ATAU Y/T (iya_tidak)
    if (!in_array($user_ans, VALID_ANSWERS, true)) {
        http_response_code(400);
        die('Jawaban tidak valid.');
    }

    $answered_id = (int)($_POST['qid'] ?? 0);

    // Ambil correct_answer + answer_mode dari DB untuk cross-check konsistensi
    $stmt = $pdo->prepare(
        "SELECT correct_answer, answer_mode FROM questions
         WHERE id = ? AND category = ? AND level = ?"
    );
    $stmt->execute([$answered_id, $posted_cat, $posted_lvl]);
    $row     = $stmt->fetch();
    $correct = strtoupper(trim($row['correct_answer'] ?? ''));
    $mode    = $row['answer_mode'] ?? 'abcd';

    // Validasi silang: jawaban harus sesuai mode soal
    $valid_for_mode = $mode === 'iya_tidak'
        ? in_array($user_ans, ['Y', 'T'], true)
        : in_array($user_ans, ['A','B','C','D'], true);

    if ($correct !== '' && $valid_for_mode && $user_ans === $correct) {
        $_SESSION['game_score']++;
    }

    // Naikkan urutan soal
    $next_order = ($_SESSION['game_order'] ?? 1) + 1;
    $_SESSION['game_order'] = $next_order;
    $total = $_SESSION['game_total'] ?? SOAL_PER_LEVEL;

    if ($next_order <= $total) {
        $data = get_soal($pdo, $posted_cat, $posted_lvl, $next_order);
        if ($data) {
            $_SESSION['game_ids'][] = (int)$data['id'];
            include '../lvl/query.php';
            exit;
        }
    }

    // Semua soal selesai
    $score      = (int)($_SESSION['game_score'] ?? 0);
    $percentage = $total > 0 ? round(($score / $total) * 100) : 0;
    // Menyelesaikan semua soal sudah cukup untuk membuka level berikutnya.
    $passed     = true;

    save_progress($pdo, (int)$_SESSION['user_id'], $posted_cat, $posted_lvl, $passed);
    unset($_SESSION['game_order'], $_SESSION['game_ids']);

    $posted_lvl_for_result = $posted_lvl;
    include '../lvl/result.php';
    exit;
}

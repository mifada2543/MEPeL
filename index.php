<?php
session_name('mepel');
session_start();
$song_dir  = "assets/song/";
$song_list = [];
$allowed   = ['mp3', 'flac', 'wav', 'ogg'];

if (is_dir($song_dir)) {
    $files = array_diff(scandir($song_dir), ['.', '..']);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) {
            $song_list[] = $file;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEPeL - Media Enterprise Pelajaran e-Library</title>
    <link rel="icon" href="assets/MEeL.png" type="image/png">
    <script src="assets/js/htmx.js"></script>
    <script src="assets/js/tailwind.js"></script>
    <script src="assets/js/plyr.js"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plyr.css">
    <style>
        .blackboard-border {
            border: 15px solid #5d4037;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .5), inset 0 0 10px #000;
        }

        .blackboard-bg {
            background-color: #2c5d3f;
        }

        .plyr--audio .plyr__controls {
            background: transparent !important;
            padding: 0 !important;
            height: 32px !important;
        }

        .plyr__progress__container {
            padding: 0 20px !important;
        }

        .plyr__time {
            font-size: 10px !important;
            color: #9ca3af;
        }

        #music-dropdown div::-webkit-scrollbar {
            width: 5px;
        }

        #music-dropdown div::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 10px;
        }

        .htmx-added {
            opacity: 0;
        }

        .htmx-settling {
            opacity: 1;
            transition: opacity .5s ease-in-out;
        }
    </style>
</head>

<body class="bg-gray-900 text-white overflow-hidden font-mono">

    <header class="p-4 flex justify-between items-center bg-gray-800 shadow-md border-b-4 border-brown-700">
        <div class="flex items-center gap-6">

            <!-- Tombol playlist -->
            <div class="relative inline-block text-left">
                <button onclick="toggleDropdown(event)" class="hover:text-yellow-400 transition-colors mt-1">
                    <i data-lucide="music-2"></i>
                </button>
                <div id="music-dropdown"
                    class="hidden absolute left-0 mt-2 w-64 rounded-md shadow-lg bg-gray-800 ring-1 ring-black ring-opacity-5 border border-gray-600 z-[100]">
                    <div class="p-2 text-xs uppercase text-gray-400 border-b border-gray-600 font-bold">Daftar Lagu</div>
                    <div class="max-h-60 overflow-y-auto">
                        <?php if (empty($song_list)): ?>
                            <p class="text-xs text-gray-500 p-3">Tidak ada lagu ditemukan.</p>
                        <?php else: ?>
                            <?php foreach ($song_list as $index => $song): ?>
                                <button onclick="playSong('<?= htmlspecialchars($song, ENT_QUOTES) ?>', <?= $index ?>)"
                                    class="block w-full text-left px-4 py-2 text-sm hover:bg-green-800 transition-colors truncate">
                                    🎵 <?= htmlspecialchars(pathinfo($song, PATHINFO_FILENAME)) ?>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Player mini -->
            <div class="flex flex-col gap-1 bg-gray-900/80 p-2 px-4 rounded-xl border border-gray-700 min-w-[320px]">
                <div class="flex items-center justify-between border-b border-gray-700/50 pb-1 overflow-hidden">
                    <span class="text-[9px] uppercase text-gray-500 font-bold tracking-tighter mr-2">Playing:</span>
                    <div class="flex-grow overflow-hidden relative h-4">
                        <div id="song-title" class="absolute whitespace-nowrap text-[11px] text-green-400 font-bold animate-marquee">
                            Silahkan pilih lagu...
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1">
                        <button onclick="prevTrack()" class="hover:text-green-400 p-1">
                            <i data-lucide="skip-back" class="w-4 h-4"></i>
                        </button>
                        <button onclick="nextTrack()" class="hover:text-green-400 p-1">
                            <i data-lucide="skip-forward" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="flex-grow scale-90 origin-left">
                        <audio id="player" playsinline controls>
                            <source src="" type="audio/mp3" />
                        </audio>
                    </div>
                </div>
            </div>

            <?php include 'partials/navbar.php'; ?>
        </div>
    </header>

    <main class="h-screen flex items-center justify-center p-10 relative">
        <div id="content"
            class="w-full max-w-5xl h-3/4 blackboard-border rounded-lg overflow-hidden flex flex-col items-center justify-center blackboard-bg relative z-10">
            <div class="text-center p-8">
                <h2 class="text-5xl font-bold mb-6 text-white drop-shadow-lg uppercase tracking-tighter">
                    Mari Belajar Bahasa Pemrograman
                </h2>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <button
                        hx-get="lvl/menu.php?cat=dasar_php"
                        hx-target="#content"
                        hx-swap="innerHTML"
                        class="bg-yellow-500 hover:bg-yellow-400 text-black font-black py-4 px-10 rounded-full
                           shadow-[0_5px_0_0_#ca8a04] active:shadow-none active:translate-y-1 transition-all">
                        MULAI PETUALANGAN
                    </button>
                <?php else: ?>
                    <div class="bg-red-900/40 border-2 border-dashed border-red-500 p-6 rounded-xl backdrop-blur-md">
                        <p class="text-red-200 font-bold mb-4 uppercase tracking-widest text-sm">
                            <i data-lucide="lock" class="inline w-4 h-4 mb-1"></i> Akses Terbatas
                        </p>
                        <p class="text-white text-xs mb-6 opacity-80">
                            Kamu harus masuk ke akun siswa untuk mengakses materi MEPeL.
                        </p>
                        <a href="auth/login.php"
                            class="bg-white text-black px-8 py-3 rounded font-black hover:bg-gray-200 transition-colors uppercase text-[10px]">
                            Login Sekarang
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="assets/js/lucide.js"></script>
    <script>
        lucide.createIcons();

        const player = new Plyr('#player', {
            controls: ['play', 'progress', 'current-time', 'mute', 'volume', 'loop'],
            displayDuration: true,
        });

        // FIX: Mapping MIME type yang benar per ekstensi
        const mimeMap = {
            mp3: 'audio/mpeg',
            flac: 'audio/flac',
            wav: 'audio/wav',
            ogg: 'audio/ogg',
        };

        const playlist = <?= json_encode($song_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let currentTrackIndex = 0;

        function playSong(fileName, index) {
            currentTrackIndex = index;
            const ext = fileName.split('.').pop().toLowerCase();
            const mime = mimeMap[ext] || 'audio/mpeg';
            const displayName = fileName.replace(/\.[^/.]+$/, '');

            document.getElementById('song-title').innerText = displayName;

            player.source = {
                type: 'audio',
                sources: [{
                    src: 'assets/song/' + encodeURIComponent(fileName),
                    type: mime
                }],
            };
            player.play();
            document.getElementById('music-dropdown').classList.add('hidden');
        }

        function toggleDropdown(event) {
            event.stopPropagation();
            document.getElementById('music-dropdown').classList.toggle('hidden');
        }

        function nextTrack() {
            if (!playlist.length) return;
            currentTrackIndex = (currentTrackIndex + 1) % playlist.length;
            playSong(playlist[currentTrackIndex], currentTrackIndex);
        }

        function prevTrack() {
            if (!playlist.length) return;
            currentTrackIndex = (currentTrackIndex - 1 + playlist.length) % playlist.length;
            playSong(playlist[currentTrackIndex], currentTrackIndex);
        }

        player.on('ended', () => {
            if (!player.loop) nextTrack();
        });

        window.onclick = function(event) {
            if (!event.target.closest('#music-dropdown') && !event.target.closest('button[onclick*="toggleDropdown"]')) {
                document.getElementById('music-dropdown').classList.add('hidden');
            }
        };
    </script>
</body>

</html>
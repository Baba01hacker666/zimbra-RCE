<?php
declare(strict_types=1);
session_start();

// While learning, uncomment these two lines to see PHP errors.
// error_reporting(E_ALL);
// ini_set('display_errors', '1');

/*
    Mini single-file PHP file manager for learning.
    Use it on localhost first.
*/

// 1. Settings
$baseDir = __DIR__ . '/files';
$maxUploadBytes = 10 * 1024 * 1024; // 10 MB

// Only these extensions can be uploaded.
// You can add/remove extensions as you learn.
$allowedExtensions = [
    'txt',
    'md',
    'csv',
    'json',
    'log',
    'html',
    'css',
    'js',
    'png',
    'jpg',
    'jpeg',
    'gif',
    'webp',
    'pdf',
    'zip',
];

// Create the managed folder if it does not exist.
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0775, true);
}

$realBase = realpath($baseDir);

if ($realBase === false) {
    exit('Base directory could not be found.');
}

// 2. Helper functions

/**
 * Escape output for HTML.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Safely resolve an existing path inside the base directory.
 */
function safePath(string $base, string $relative): ?string
{
    $relative = trim($relative);

    if ($relative === '') {
        return $base;
    }

    $relative = str_replace('\\', '/', $relative);
    $relative = str_replace("\0", '', $relative);

    $full = realpath($base . '/' . $relative);

    if ($full === false) {
        return null;
    }

    if ($full === $base) {
        return $full;
    }

    // Make sure the resolved path is still inside the base directory.
    if (strncmp($full, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) {
        return null;
    }

    return $full;
}

/**
 * Safely build a target path that may not exist yet.
 * Used for creating folders and uploaded files.
 */
function safeTargetPath(string $base, string $relative): ?string
{
    $relative = trim($relative);

    if ($relative === '') {
        return null;
    }

    $relative = str_replace('\\', '/', $relative);
    $relative = str_replace("\0", '', $relative);

    $parts = explode('/', $relative);
    $cleanParts = [];

    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        // Block directory traversal.
        if ($part === '..') {
            return null;
        }

        $cleanParts[] = $part;
    }

    if ($cleanParts === []) {
        return null;
    }

    $candidate = $base;

    foreach ($cleanParts as $part) {
        $candidate .= DIRECTORY_SEPARATOR . $part;
    }

    $parent = dirname($candidate);
    $realParent = realpath($parent);

    if ($realParent === false) {
        return null;
    }

    // Make sure parent is inside the base directory.
    if ($realParent !== $base && strncmp($realParent, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) {
        return null;
    }

    $name = basename($candidate);

    if ($name === '' || $name === '.' || $name === '..') {
        return null;
    }

    return $realParent . DIRECTORY_SEPARATOR . $name;
}

/**
 * Get relative path from base.
 */
function relPath(string $base, string $full): string
{
    if ($full === $base) {
        return '';
    }

    $path = substr($full, strlen($base));
    $path = str_replace('\\', '/', $path);

    return ltrim($path, '/');
}

/**
 * Make a file/folder name safer.
 */
function safeName(string $name): string
{
    $name = trim($name);

    // Remove characters that are risky or annoying in file names.
    $name = str_replace(
        ["\0", '\\', '/', ':', '*', '?', '"', '<', '>', '|'],
        '_',
        $name
    );

    // Collapse multiple spaces.
    $name = preg_replace('/\s+/', ' ', $name) ?? '';

    // Prevent hidden files and traversal-like names.
    $name = trim($name, '. ');

    if ($name === '' || $name === '.' || $name === '..') {
        return '';
    }

    return $name;
}

/**
 * Human-readable file size.
 */
function formatBytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];

    $factor = (int) floor(log($bytes, 1024));
    $factor = min($factor, count($units) - 1);

    return sprintf('%.1f %s', $bytes / pow(1024, $factor), $units[$factor]);
}

/**
 * Simple CSRF token for POST forms.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf'];
}

/**
 * Redirect after POST actions.
 */
function redirect(string $path, string $message = '')
{
    $url = '?path=' . urlencode($path);

    if ($message !== '') {
        $url .= '&msg=' . urlencode($message);
    }

    header('Location: ' . $url);
    exit;
}

$token = csrfToken();

// 3. Read request values
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$path = $_POST['path'] ?? $_GET['path'] ?? '';

if (!is_string($action)) {
    $action = 'list';
}

if (!is_string($path)) {
    $path = '';
}

$currentDir = safePath($realBase, $path);

if ($currentDir === null || !is_dir($currentDir)) {
    http_response_code(400);
    exit('Invalid path.');
}

$relDir = relPath($realBase, $currentDir);

// 4. Handle POST actions before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['token']) || !is_string($_POST['token']) || !hash_equals($token, $_POST['token'])) {
        http_response_code(400);
        exit('Invalid security token.');
    }

    // Create folder
    if ($action === 'create_folder') {
        $folderInput = $_POST['folder_name'] ?? '';

        if (!is_string($folderInput)) {
            $folderInput = '';
        }

        $folderName = safeName($folderInput);

        if ($folderName === '') {
            redirect($relDir, 'invalid_name');
        }

        $targetRelative = ($relDir === '' ? '' : $relDir . '/') . $folderName;
        $target = safeTargetPath($realBase, $targetRelative);

        if ($target === null || file_exists($target)) {
            redirect($relDir, 'folder_exists');
        }

        if (mkdir($target, 0775)) {
            redirect($relDir, 'folder_created');
        }

        redirect($relDir, 'error');
    }

    // Upload file
    if ($action === 'upload') {
        if (!isset($_FILES['upload']) || !is_array($_FILES['upload'])) {
            redirect($relDir, 'upload_failed');
        }

        $file = $_FILES['upload'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            redirect($relDir, 'upload_failed');
        }

        if (($file['size'] ?? 0) > $maxUploadBytes) {
            redirect($relDir, 'file_too_large');
        }

        $originalName = basename((string)($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $fileNameOnly = pathinfo($originalName, PATHINFO_FILENAME);

        // If there is an extension, it must be allowed.
        if ($extension !== '' && !in_array($extension, $allowedExtensions, true)) {
            redirect($relDir, 'type_not_allowed');
        }

        $safeBase = safeName($fileNameOnly);

        if ($safeBase === '') {
            $safeBase = 'file';
        }

        $finalName = $extension === '' ? $safeBase : $safeBase . '.' . $extension;
        $targetRelative = ($relDir === '' ? '' : $relDir . '/') . $finalName;
        $target = safeTargetPath($realBase, $targetRelative);

        // If file exists, create name like file-1.txt, file-2.txt
        $copyNumber = 1;

        while ($target !== null && file_exists($target)) {
            $finalName = $extension === ''
                ? $safeBase . '-' . $copyNumber
                : $safeBase . '-' . $copyNumber . '.' . $extension;

            $targetRelative = ($relDir === '' ? '' : $relDir . '/') . $finalName;
            $target = safeTargetPath($realBase, $targetRelative);
            $copyNumber++;
        }

        if ($target === null) {
            redirect($relDir, 'error');
        }

        if (move_uploaded_file((string)($file['tmp_name'] ?? ''), $target)) {
            redirect($relDir, 'uploaded');
        }

        redirect($relDir, 'upload_failed');
    }

    // Delete file or empty folder
    if ($action === 'delete') {
        $type = $_POST['type'] ?? '';
        $item = $_POST['item'] ?? '';

        if (!is_string($type)) {
            $type = '';
        }

        if (!is_string($item)) {
            $item = '';
        }

        // Prevent deleting the root managed folder itself.
        if ($item === '') {
            redirect($relDir, 'error');
        }

        $itemPath = safePath($realBase, $item);

        if ($itemPath === null || $itemPath === $realBase) {
            redirect($relDir, 'error');
        }

        if ($type === 'file' && is_file($itemPath)) {
            if (unlink($itemPath)) {
                redirect($relDir, 'deleted');
            }

            redirect($relDir, 'delete_failed');
        }

        if ($type === 'dir' && is_dir($itemPath)) {
            // Only delete empty folders. Safer for beginners.
            if (rmdir($itemPath)) {
                redirect($relDir, 'deleted');
            }

            redirect($relDir, 'delete_failed_folder_not_empty');
        }

        redirect($relDir, 'error');
    }
}

// 5. Handle GET file actions: view/download
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($action === 'view' || $action === 'download')) {
    $file = $_GET['file'] ?? '';

    if (!is_string($file)) {
        $file = '';
    }

    $fullFile = safePath($realBase, $file);

    if ($fullFile === null || !is_file($fullFile)) {
        http_response_code(404);
        exit('File not found.');
    }

    $fileName = str_replace('"', '', basename($fullFile));

    if ($action === 'download') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . (string) filesize($fullFile));
        readfile($fullFile);
        exit;
    }

    // View as plain text. Safer for learning.
    // Later you can improve this for images/PDF.
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    readfile($fullFile);
    exit;
}

// 6. Read current directory listing
$entries = scandir($currentDir) ?: [];

$folders = [];
$files = [];

foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }

    $full = $currentDir . DIRECTORY_SEPARATOR . $entry;

    if (is_dir($full)) {
        $folders[] = $entry;
    } else {
        $files[] = $entry;
    }
}

sort($folders);
sort($files);

$message = $_GET['msg'] ?? '';

if (!is_string($message)) {
    $message = '';
}

$messages = [
    'folder_created' => 'Folder created.',
    'uploaded' => 'File uploaded.',
    'deleted' => 'Deleted.',
    'invalid_name' => 'Invalid name.',
    'folder_exists' => 'Folder already exists or invalid target.',
    'upload_failed' => 'Upload failed.',
    'file_too_large' => 'File is too large.',
    'type_not_allowed' => 'File type not allowed.',
    'delete_failed' => 'Delete failed.',
    'delete_failed_folder_not_empty' => 'Folder delete failed. Folder must be empty.',
    'error' => 'Something went wrong.',
];

$messageText = $messages[$message] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mini PHP File Manager</title>
    <style>
        :root {
            color-scheme: light dark;
        }

        body {
            font-family: Arial, system-ui, sans-serif;
            margin: 24px;
            line-height: 1.4;
        }

        h1 {
            margin-bottom: 4px;
        }

        .sub,
        .small {
            opacity: .75;
            font-size: 14px;
        }

        .card {
            border: 1px solid rgba(128, 128, 128, .35);
            border-radius: 10px;
            padding: 14px;
            margin: 14px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px 8px;
            border-bottom: 1px solid rgba(128, 128, 128, .25);
            text-align: left;
            vertical-align: middle;
        }

        th {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .04em;
            opacity: .8;
        }

        a {
            color: #2563eb;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .actions a,
        .actions button {
            margin-right: 8px;
        }

        button,
        input[type="text"],
        input[type="file"] {
            padding: 8px 10px;
            border: 1px solid rgba(128, 128, 128, .4);
            border-radius: 8px;
            background: transparent;
        }

        button {
            cursor: pointer;
        }

        .inline-form {
            display: inline;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .notice {
            padding: 10px 12px;
            border: 1px solid rgba(37, 99, 235, .35);
            background: rgba(37, 99, 235, .08);
            border-radius: 8px;
        }

        .danger {
            color: #b91c1c;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            font: inherit;
        }

        .crumbs {
            margin: 8px 0;
        }

        @media (max-width: 700px) {
            .hide-mobile {
                display: none;
            }
        }
    </style>
</head>
<body>

<h1>Mini PHP File Manager</h1>
<div class="sub">Learning project. Keep it local or add authentication before exposing it.</div>

<?php if ($messageText !== ''): ?>
    <p class="notice"><?= e($messageText) ?></p>
<?php endif; ?>

<div class="card">
    <div class="toolbar">
        <a href="?">Home</a>

        <?php if ($relDir !== ''): ?>
            <?php $parent = dirname($relDir); ?>
            <a href="?path=<?= e(urlencode($parent === '.' ? '' : $parent)) ?>">Up one folder</a>
        <?php endif; ?>
    </div>

    <div class="crumbs">
        <strong>Path:</strong>
        <a href="?">files</a><?php
        $parts = $relDir === '' ? [] : explode('/', $relDir);
        $accum = '';

        foreach ($parts as $part) {
            $accum = $accum === '' ? $part : $accum . '/' . $part;
            echo ' / <a href="?path=' . e(urlencode($accum)) . '">' . e($part) . '</a>';
        }
        ?>
    </div>
</div>

<div class="card">
    <div class="toolbar">
        <form method="post" class="toolbar">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="create_folder">
            <input type="hidden" name="path" value="<?= e($relDir) ?>">
            <input type="text" name="folder_name" placeholder="New folder name" required>
            <button type="submit">Create folder</button>
        </form>

        <form method="post" enctype="multipart/form-data" class="toolbar">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="path" value="<?= e($relDir) ?>">
            <input type="file" name="upload" required>
            <button type="submit">Upload</button>
        </form>
    </div>

    <div class="small">
        Allowed extensions: <?= e(implode(', ', $allowedExtensions)) ?>
        (files without extension are allowed).
        Max size: <?= e(formatBytes($maxUploadBytes)) ?>
    </div>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th class="hide-mobile">Size</th>
                <th class="hide-mobile">Modified</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($folders as $folder): ?>
                <?php
                $itemRel = ($relDir === '' ? '' : $relDir . '/') . $folder;
                $full = $currentDir . DIRECTORY_SEPARATOR . $folder;
                ?>
                <tr>
                    <td>
                        📁 <a href="?path=<?= e(urlencode($itemRel)) ?>"><?= e($folder) ?>/</a>
                    </td>
                    <td class="hide-mobile">-</td>
                    <td class="hide-mobile"><?= e(date('Y-m-d H:i', (int) filemtime($full))) ?></td>
                    <td class="actions">
                        <form
                            method="post"
                            class="inline-form"
                            onsubmit="return confirm('Delete this folder if it is empty?');"
                        >
                            <input type="hidden" name="token" value="<?= e($token) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="path" value="<?= e($relDir) ?>">
                            <input type="hidden" name="type" value="dir">
                            <input type="hidden" name="item" value="<?= e($itemRel) ?>">
                            <button type="submit" class="danger">delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php foreach ($files as $file): ?>
                <?php
                $itemRel = ($relDir === '' ? '' : $relDir . '/') . $file;
                $full = $currentDir . DIRECTORY_SEPARATOR . $file;
                ?>
                <tr>
                    <td>📄 <?= e($file) ?></td>
                    <td class="hide-mobile"><?= e(formatBytes((int) filesize($full))) ?></td>
                    <td class="hide-mobile"><?= e(date('Y-m-d H:i', (int) filemtime($full))) ?></td>
                    <td class="actions">
                        <a
                            href="?action=view&file=<?= e(urlencode($itemRel)) ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            view
                        </a>

                        <a href="?action=download&file=<?= e(urlencode($itemRel)) ?>">download</a>

                        <form
                            method="post"
                            class="inline-form"
                            onsubmit="return confirm('Delete this file?');"
                        >
                            <input type="hidden" name="token" value="<?= e($token) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="path" value="<?= e($relDir) ?>">
                            <input type="hidden" name="type" value="file">
                            <input type="hidden" name="item" value="<?= e($itemRel) ?>">
                            <button type="submit" class="danger">delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$folders && !$files): ?>
                <tr>
                    <td colspan="4">This folder is empty.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>

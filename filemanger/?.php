<?php
session_start();

// Show errors while learning locally.
error_reporting(E_ALL);
ini_set('display_errors', '1');

/*
    Local PHP File Manager Playground
    ---------------------------------
    Single-file project.

    Features:
    - browse folders
    - create folder
    - create file
    - upload file
    - edit/save text files
    - download files
    - preview images
    - copy / cut / paste
    - delete files/folders
    - change permissions with chmod

    This is for local learning/testing.
*/


// ---------------------------------------------------------------------
// Basic setup
// ---------------------------------------------------------------------

// This is the folder the file manager will manage.
$baseFolderPath = __DIR__ . '/files';

// Create the managed folder if it does not exist yet.
if (!is_dir($baseFolderPath)) {
    mkdir($baseFolderPath, 0775, true);
}

// Real absolute path of the base folder.
$realBaseFolderPath = realpath($baseFolderPath);

if ($realBaseFolderPath === false) {
    exit('Base folder could not be found.');
}


// ---------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------

/**
 * Escape text before printing it into HTML.
 */
function escapeHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Turn a relative path like "images/pic.png" into a full real path.
 * Returns null if the path does not exist or looks invalid.
 */
function makeFullPath(string $baseFolderPath, string $relativePath): ?string
{
    $relativePath = trim($relativePath);

    if ($relativePath === '') {
        return $baseFolderPath;
    }

    // Normalize slashes.
    $relativePath = str_replace('\\', '/', $relativePath);

    // Remove null bytes.
    $relativePath = str_replace("\0", '', $relativePath);

    $fullPath = realpath($baseFolderPath . '/' . $relativePath);

    if ($fullPath === false) {
        return null;
    }

    // If it is the base folder itself, allow it.
    if ($fullPath === $baseFolderPath) {
        return $fullPath;
    }

    // Make sure the final path is inside the base folder.
    if (strpos($fullPath, $baseFolderPath . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }

    return $fullPath;
}

/**
 * Convert a full path back into a relative path inside the base folder.
 */
function makeRelativePath(string $baseFolderPath, string $fullPath): string
{
    if ($fullPath === $baseFolderPath) {
        return '';
    }

    $relativePath = substr($fullPath, strlen($baseFolderPath));
    $relativePath = str_replace('\\', '/', $relativePath);

    return ltrim($relativePath, '/');
}

/**
 * Build a path for a new file/folder being created in the current folder.
 */
function makeNewItemPath(
    string $baseFolderPath,
    string $currentRelativePath,
    string $newItemName
): ?string {
    $newItemName = trim($newItemName);

    // Remove characters that are annoying/risky in file names.
    $newItemName = str_replace(
        ["\0", '\\', '/', ':', '*', '?', '"', '<', '>', '|'],
        '_',
        $newItemName
    );

    // Collapse multiple spaces into one.
    $newItemName = preg_replace('/\s+/', ' ', $newItemName) ?? '';

    // Prevent names like ".", "..", hidden files, etc.
    $newItemName = trim($newItemName, '. ');

    if ($newItemName === '' || $newItemName === '.' || $newItemName === '..') {
        return null;
    }

    $currentFullPath = makeFullPath($baseFolderPath, $currentRelativePath);

    if ($currentFullPath === null || !is_dir($currentFullPath)) {
        return null;
    }

    return $currentFullPath . DIRECTORY_SEPARATOR . $newItemName;
}

/**
 * If a destination already exists, make a new name like:
 * file.txt -> file - copy 1.txt
 */
function makeUniqueDestinationPath(string $targetPath): string
{
    if (!file_exists($targetPath)) {
        return $targetPath;
    }

    $targetFolder = dirname($targetPath);
    $originalName = basename($targetPath);

    $pathInformation = pathinfo($originalName);

    $nameWithoutExtension = $pathInformation['filename'] ?? $originalName;
    $extensionPart = empty($pathInformation['extension'])
        ? ''
        : '.' . $pathInformation['extension'];

    $copyNumber = 1;

    do {
        $newName = $nameWithoutExtension . ' - copy ' . $copyNumber . $extensionPart;
        $newTargetPath = $targetFolder . DIRECTORY_SEPARATOR . $newName;
        $copyNumber++;
    } while (file_exists($newTargetPath));

    return $newTargetPath;
}

/**
 * Copy a folder and everything inside it.
 */
function copyFolderRecursively(string $sourceFolderPath, string $destinationFolderPath): bool
{
    if (!is_dir($sourceFolderPath)) {
        return false;
    }

    if (!is_dir($destinationFolderPath)) {
        if (!mkdir($destinationFolderPath, 0775, true)) {
            return false;
        }
    }

    $itemNames = scandir($sourceFolderPath);

    if ($itemNames === false) {
        return false;
    }

    foreach ($itemNames as $itemName) {
        if ($itemName === '.' || $itemName === '..') {
            continue;
        }

        $sourceItemPath = $sourceFolderPath . DIRECTORY_SEPARATOR . $itemName;
        $destinationItemPath = $destinationFolderPath . DIRECTORY_SEPARATOR . $itemName;

        if (is_dir($sourceItemPath)) {
            if (!copyFolderRecursively($sourceItemPath, $destinationItemPath)) {
                return false;
            }
        } else {
            if (!copy($sourceItemPath, $destinationItemPath)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Delete a folder and everything inside it.
 */
function deleteFolderRecursively(string $folderPath): bool
{
    if (!is_dir($folderPath)) {
        return false;
    }

    $itemNames = scandir($folderPath);

    if ($itemNames === false) {
        return false;
    }

    foreach ($itemNames as $itemName) {
        if ($itemName === '.' || $itemName === '..') {
            continue;
        }

        $itemPath = $folderPath . DIRECTORY_SEPARATOR . $itemName;

        if (is_dir($itemPath)) {
            if (!deleteFolderRecursively($itemPath)) {
                return false;
            }
        } else {
            if (!unlink($itemPath)) {
                return false;
            }
        }
    }

    return rmdir($folderPath);
}

/**
 * Delete either a file or a folder.
 */
function deleteFileOrFolder(string $path): bool
{
    if (is_file($path) || is_link($path)) {
        return unlink($path);
    }

    if (is_dir($path)) {
        return deleteFolderRecursively($path);
    }

    return false;
}

/**
 * Move a file or folder.
 */
function moveFileOrFolder(string $sourcePath, string $destinationPath): bool
{
    // Try normal rename first.
    if (@rename($sourcePath, $destinationPath)) {
        return true;
    }

    // Fallback for weird filesystem cases: copy then delete.
    if (is_file($sourcePath)) {
        if (!copy($sourcePath, $destinationPath)) {
            return false;
        }

        return unlink($sourcePath);
    }

    if (is_dir($sourcePath)) {
        if (!copyFolderRecursively($sourcePath, $destinationPath)) {
            return false;
        }

        return deleteFolderRecursively($sourcePath);
    }

    return false;
}

/**
 * Get a normal 4-digit permission string like 0644 or 0755.
 */
function getPermissionString(string $path): string
{
    $permissions = @fileperms($path);

    if ($permissions === false) {
        return '????';
    }

    return substr(sprintf('%o', $permissions), -4);
}

/**
 * Format bytes into KB/MB/GB.
 */
function formatBytes(int $byteCount): string
{
    if ($byteCount <= 0) {
        return '0 B';
    }

    if ($byteCount < 1024) {
        return $byteCount . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];

    $factor = (int) floor(log($byteCount, 1024));
    $factor = min($factor, count($units) - 1);

    return sprintf('%.1f %s', $byteCount / pow(1024, $factor), $units[$factor]);
}

/**
 * Choose an icon based on file type.
 */
function getFileTypeIcon(string $fullPath): string
{
    if (is_dir($fullPath)) {
        return '📁';
    }

    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

    $imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg', 'ico'];
    $codeExtensions = [
        'php', 'phtml', 'html', 'htm', 'css', 'js', 'json', 'xml',
        'py', 'c', 'cpp', 'h', 'hpp', 'java', 'sh', 'bat',
        'sql', 'yml', 'yaml', 'ts', 'jsx', 'tsx', 'vue'
    ];
    $textExtensions = ['txt', 'md', 'log', 'ini', 'conf', 'cfg', 'csv', 'env'];
    $archiveExtensions = ['zip', 'rar', '7z', 'tar', 'gz'];
    $audioExtensions = ['mp3', 'wav', 'ogg', 'flac'];
    $videoExtensions = ['mp4', 'webm', 'mkv', 'avi'];

    if (in_array($extension, $imageExtensions, true)) {
        return '🖼️';
    }

    if (in_array($extension, $codeExtensions, true)) {
        return '🧩';
    }

    if (in_array($extension, $textExtensions, true)) {
        return '📝';
    }

    if (in_array($extension, $archiveExtensions, true)) {
        return '🗜️';
    }

    if (in_array($extension, $audioExtensions, true)) {
        return '🎵';
    }

    if (in_array($extension, $videoExtensions, true)) {
        return '🎬';
    }

    return '📄';
}

/**
 * Decide if a file is probably text/code, so we can show it in the editor.
 */
function isProbablyTextFile(string $fullPath): bool
{
    if (!is_file($fullPath)) {
        return false;
    }

    $fileSize = filesize($fullPath);

    if ($fileSize === false) {
        return false;
    }

    // Do not try to load very big files into the editor.
    if ($fileSize > 2 * 1024 * 1024) {
        return false;
    }

    // Empty files are editable.
    if ($fileSize === 0) {
        return true;
    }

    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

    // Files without extension are treated as text for local fiddling.
    if ($extension === '') {
        return true;
    }

    $textExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phps',
        'html', 'htm', 'css', 'js', 'json', 'txt', 'md',
        'markdown', 'log', 'ini', 'cfg', 'conf', 'xml',
        'sql', 'py', 'c', 'cpp', 'h', 'hpp', 'java',
        'jsx', 'ts', 'tsx', 'vue', 'sh', 'bat',
        'yml', 'yaml', 'csv', 'env'
    ];

    if (in_array($extension, $textExtensions, true)) {
        return true;
    }

    if (function_exists('mime_content_type')) {
        $mimeType = @mime_content_type($fullPath);

        if ($mimeType !== false) {
            if (strpos($mimeType, 'text/') === 0) {
                return true;
            }

            $textLikeMimeTypes = [
                'application/json',
                'application/xml',
                'application/javascript',
                'application/x-php',
            ];

            if (in_array($mimeType, $textLikeMimeTypes, true)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Check if file is an image.
 */
function isImageFile(string $fullPath): bool
{
    if (!is_file($fullPath)) {
        return false;
    }

    return @getimagesize($fullPath) !== false;
}

/**
 * Redirect back to browse view.
 */
function redirectToBrowse(string $relativePath, string $message = '')
{
    $url = '?path=' . urlencode($relativePath);

    if ($message !== '') {
        $url .= '&message=' . urlencode($message);
    }

    header('Location: ' . $url);
    exit;
}


// ---------------------------------------------------------------------
// Read request values
// ---------------------------------------------------------------------

$action = $_GET['action'] ?? $_POST['action'] ?? 'browse';
$relativePath = $_POST['path'] ?? $_GET['path'] ?? '';
$relativeFilePath = $_GET['file'] ?? $_POST['file'] ?? '';

if (!is_string($action)) {
    $action = 'browse';
}

if (!is_string($relativePath)) {
    $relativePath = '';
}

if (!is_string($relativeFilePath)) {
    $relativeFilePath = '';
}

// Current folder being browsed.
$currentFolderPath = makeFullPath($realBaseFolderPath, $relativePath);

// If path is invalid, fall back to base folder.
if ($currentFolderPath === null || !is_dir($currentFolderPath)) {
    $currentFolderPath = $realBaseFolderPath;
    $relativePath = '';
}


// ---------------------------------------------------------------------
// Clipboard actions: copy / cut / paste
// ---------------------------------------------------------------------

// Start copying.
if ($action === 'start_copy' || $action === 'start_move') {
    $sourceRelativePath = (string)($_GET['source'] ?? '');
    $sourceFullPath = makeFullPath($realBaseFolderPath, $sourceRelativePath);

    if (
        $sourceFullPath !== null &&
        file_exists($sourceFullPath) &&
        $sourceFullPath !== $realBaseFolderPath
    ) {
        $_SESSION['clipboard'] = [
            'operation' => $action === 'start_copy' ? 'copy' : 'move',
            'source' => $sourceRelativePath,
        ];

        redirectToBrowse($relativePath, 'clipboard_set');
    }

    redirectToBrowse($relativePath, 'error');
}

// Clear clipboard.
if ($action === 'clear_clipboard') {
    unset($_SESSION['clipboard']);
    redirectToBrowse($relativePath, 'clipboard_cleared');
}

// Paste from clipboard into current folder.
if ($action === 'paste') {
    if (empty($_SESSION['clipboard']['operation']) || empty($_SESSION['clipboard']['source'])) {
        redirectToBrowse($relativePath, 'clipboard_empty');
    }

    $clipboardOperation = $_SESSION['clipboard']['operation'];
    $sourceRelativePath = (string)$_SESSION['clipboard']['source'];

    $sourceFullPath = makeFullPath($realBaseFolderPath, $sourceRelativePath);

    if (
        $sourceFullPath === null ||
        !file_exists($sourceFullPath) ||
        $sourceFullPath === $realBaseFolderPath
    ) {
        unset($_SESSION['clipboard']);
        redirectToBrowse($relativePath, 'error');
    }

    // Prevent pasting a folder into itself or into its own subfolder.
    if (
        $currentFolderPath === $sourceFullPath ||
        strpos($currentFolderPath . DIRECTORY_SEPARATOR, $sourceFullPath . DIRECTORY_SEPARATOR) === 0
    ) {
        redirectToBrowse($relativePath, 'cannot_paste_here');
    }

    $destinationFullPath = $currentFolderPath . DIRECTORY_SEPARATOR . basename($sourceFullPath);

    // If source and destination are the same:
    // - for move: do nothing
    // - for copy: make a duplicate name
    if ($sourceFullPath === $destinationFullPath) {
        if ($clipboardOperation === 'move') {
            redirectToBrowse($relativePath, 'already_here');
        }

        $destinationFullPath = makeUniqueDestinationPath($destinationFullPath);
    } elseif (file_exists($destinationFullPath)) {
        $destinationFullPath = makeUniqueDestinationPath($destinationFullPath);
    }

    $success = false;

    if ($clipboardOperation === 'copy') {
        if (is_file($sourceFullPath)) {
            $success = copy($sourceFullPath, $destinationFullPath);
        } elseif (is_dir($sourceFullPath)) {
            $success = copyFolderRecursively($sourceFullPath, $destinationFullPath);
        }
    } else {
        $success = moveFileOrFolder($sourceFullPath, $destinationFullPath);

        if ($success) {
            unset($_SESSION['clipboard']);
        }
    }

    redirectToBrowse($relativePath, $success ? 'pasted' : 'error');
}


// ---------------------------------------------------------------------
// Create folder
// ---------------------------------------------------------------------

if ($action === 'create_folder') {
    $newFolderName = (string)($_POST['folder_name'] ?? '');
    $newFolderPath = makeNewItemPath($realBaseFolderPath, $relativePath, $newFolderName);

    if ($newFolderPath === null || file_exists($newFolderPath)) {
        redirectToBrowse($relativePath, 'item_exists');
    }

    if (mkdir($newFolderPath, 0775)) {
        redirectToBrowse($relativePath, 'folder_created');
    }

    redirectToBrowse($relativePath, 'error');
}


// ---------------------------------------------------------------------
// Create empty file and open it in editor
// ---------------------------------------------------------------------

if ($action === 'create_file') {
    $newFileName = (string)($_POST['new_file_name'] ?? '');
    $newFileFullPath = makeNewItemPath($realBaseFolderPath, $relativePath, $newFileName);

    if ($newFileFullPath === null || file_exists($newFileFullPath)) {
        redirectToBrowse($relativePath, 'item_exists');
    }

    if (file_put_contents($newFileFullPath, '') !== false) {
        $newFileRelativePath = makeRelativePath($realBaseFolderPath, $newFileFullPath);

        header(
            'Location: ?action=edit&file=' .
            urlencode($newFileRelativePath) .
            '&message=file_created'
        );
        exit;
    }

    redirectToBrowse($relativePath, 'error');
}


// ---------------------------------------------------------------------
// Upload file
// ---------------------------------------------------------------------

if ($action === 'upload') {
    if (
        empty($_FILES['uploaded_file']) ||
        !is_array($_FILES['uploaded_file']) ||
        ($_FILES['uploaded_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
    ) {
        redirectToBrowse($relativePath, 'upload_failed');
    }

    $uploadedOriginalName = basename((string)($_FILES['uploaded_file']['name'] ?? ''));

    if ($uploadedOriginalName === '') {
        $uploadedOriginalName = 'uploaded_file';
    }

    $newFileFullPath = makeNewItemPath(
        $realBaseFolderPath,
        $relativePath,
        $uploadedOriginalName
    );

    if ($newFileFullPath === null) {
        redirectToBrowse($relativePath, 'invalid_name');
    }

    if (file_exists($newFileFullPath)) {
        $newFileFullPath = makeUniqueDestinationPath($newFileFullPath);
    }

    $uploadedTemporaryPath = (string)($_FILES['uploaded_file']['tmp_name'] ?? '');

    if (move_uploaded_file($uploadedTemporaryPath, $newFileFullPath)) {
        redirectToBrowse($relativePath, 'uploaded');
    }

    redirectToBrowse($relativePath, 'upload_failed');
}


// ---------------------------------------------------------------------
// Save edited text file
// ---------------------------------------------------------------------

if ($action === 'save_file') {
    $fileRelativePath = (string)($_POST['file'] ?? '');
    $fileContent = (string)($_POST['file_content'] ?? '');

    $fullFilePath = makeFullPath($realBaseFolderPath, $fileRelativePath);

    if (
        $fullFilePath !== null &&
        is_file($fullFilePath) &&
        $fullFilePath !== $realBaseFolderPath
    ) {
        if (file_put_contents($fullFilePath, $fileContent) !== false) {
            header(
                'Location: ?action=edit&file=' .
                urlencode($fileRelativePath) .
                '&message=saved'
            );
            exit;
        }
    }

    redirectToBrowse($relativePath, 'error');
}


// ---------------------------------------------------------------------
// Change permissions
// ---------------------------------------------------------------------

if ($action === 'chmod') {
    $targetRelativePath = (string)($_POST['file'] ?? '');
    $returnTo = (string)($_POST['return_to'] ?? 'browse');
    $chmodValue = (string)($_POST['chmod_value'] ?? '');

    $targetFullPath = makeFullPath($realBaseFolderPath, $targetRelativePath);

    if (
        $targetFullPath === null ||
        $targetFullPath === $realBaseFolderPath ||
        !file_exists($targetFullPath)
    ) {
        redirectToBrowse($relativePath, 'error');
    }

    // Keep only octal digits: 0-7
    $cleanMode = preg_replace('/[^0-7]/', '', $chmodValue) ?? '';

    // Allow input like 644 and convert it to 0644.
    if (strlen($cleanMode) === 3) {
        $cleanMode = '0' . $cleanMode;
    }

    $success = false;

    if (strlen($cleanMode) === 4) {
        $modeNumber = octdec($cleanMode);
        $success = @chmod($targetFullPath, $modeNumber);
    }

    $message = $success ? 'permissions_saved' : 'error';

    if ($returnTo === 'edit') {
        header(
            'Location: ?action=edit&file=' .
            urlencode($targetRelativePath) .
            '&message=' . urlencode($message)
        );
        exit;
    }

    redirectToBrowse($relativePath, $message);
}


// ---------------------------------------------------------------------
// Delete file or folder
// ---------------------------------------------------------------------

if ($action === 'delete') {
    $deleteRelativePath = (string)($_POST['file'] ?? '');
    $deleteFullPath = makeFullPath($realBaseFolderPath, $deleteRelativePath);

    if (
        $deleteFullPath !== null &&
        $deleteFullPath !== $realBaseFolderPath &&
        file_exists($deleteFullPath)
    ) {
        if (deleteFileOrFolder($deleteFullPath)) {
            redirectToBrowse($relativePath, 'deleted');
        }
    }

    redirectToBrowse($relativePath, 'error');
}


// ---------------------------------------------------------------------
// Download file
// ---------------------------------------------------------------------

if ($action === 'download') {
    $fullFilePath = makeFullPath($realBaseFolderPath, $relativeFilePath);

    if ($fullFilePath !== null && is_file($fullFilePath)) {
        $downloadFileName = str_replace('"', '', basename($fullFilePath));

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
        header('Content-Length: ' . (string)filesize($fullFilePath));

        readfile($fullFilePath);
        exit;
    }

    exit('File not found.');
}


// ---------------------------------------------------------------------
// Output raw file content, used for image preview
// ---------------------------------------------------------------------

if ($action === 'raw') {
    $fullFilePath = makeFullPath($realBaseFolderPath, $relativeFilePath);

    if ($fullFilePath !== null && is_file($fullFilePath)) {
        $mimeType = 'application/octet-stream';

        if (function_exists('mime_content_type')) {
            $detectedMimeType = @mime_content_type($fullFilePath);

            if ($detectedMimeType !== false && $detectedMimeType !== '') {
                $mimeType = $detectedMimeType;
            }
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string)filesize($fullFilePath));

        readfile($fullFilePath);
        exit;
    }

    exit('File not found.');
}


// ---------------------------------------------------------------------
// Prepare active file for edit/preview pages
// ---------------------------------------------------------------------

$activeFileFullPath = null;

if ($action === 'edit' || $action === 'preview') {
    $activeFileFullPath = makeFullPath($realBaseFolderPath, $relativeFilePath);

    if ($activeFileFullPath === null || !is_file($activeFileFullPath)) {
        redirectToBrowse($relativePath, 'error');
    }
}


// ---------------------------------------------------------------------
// Messages
// ---------------------------------------------------------------------

$messageKey = $_GET['message'] ?? '';

if (!is_string($messageKey)) {
    $messageKey = '';
}

$messageList = [
    'folder_created' => 'Folder created.',
    'file_created' => 'File created.',
    'uploaded' => 'File uploaded.',
    'saved' => 'File saved.',
    'deleted' => 'Deleted.',
    'permissions_saved' => 'Permissions updated.',
    'clipboard_set' => 'Clipboard set.',
    'clipboard_cleared' => 'Clipboard cleared.',
    'clipboard_empty' => 'Clipboard is empty.',
    'pasted' => 'Pasted.',
    'cannot_paste_here' => 'Cannot paste here.',
    'already_here' => 'Item is already here.',
    'item_exists' => 'Item already exists or invalid name.',
    'invalid_name' => 'Invalid name.',
    'upload_failed' => 'Upload failed.',
    'error' => 'Something went wrong.',
];

$messageText = $messageList[$messageKey] ?? '';


// ---------------------------------------------------------------------
// Page title
// ---------------------------------------------------------------------

$pageTitle = 'Local File Manager';

if ($action === 'edit') {
    $pageTitle = 'Edit File';
}

if ($action === 'preview') {
    $pageTitle = 'Preview File';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapeHtml($pageTitle) ?></title>

    <style>
        :root {
            color-scheme: dark;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, system-ui, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(76, 140, 255, 0.16), transparent 30%),
                radial-gradient(circle at top right, rgba(155, 92, 255, 0.14), transparent 25%),
                #0b1220;
            color: #eaf2ff;
        }

        .container {
            width: min(1250px, calc(100% - 32px));
            margin: 28px auto;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 28px;
        }

        h2 {
            margin: 10px 0;
            font-size: 22px;
        }

        .sub {
            opacity: 0.75;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .card {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 16px;
            padding: 14px;
            margin: 14px 0;
            backdrop-filter: blur(10px);
        }

        a {
            color: #8cc7ff;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        button,
        input[type="text"],
        input[type="file"] {
            background: rgba(8, 15, 31, 0.9);
            color: #eaf2ff;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 10px;
            padding: 9px 11px;
            outline: none;
        }

        button {
            cursor: pointer;
        }

        button:hover {
            border-color: #8cc7ff;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .toolbar form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            vertical-align: middle;
            text-align: left;
        }

        th {
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #9fb3d9;
        }

        tbody tr:hover td {
            background: rgba(255, 255, 255, 0.035);
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .actions form {
            display: inline;
        }

        .danger {
            color: #ff9c9c;
        }

        .notice {
            padding: 11px 13px;
            border-radius: 12px;
            border: 1px solid rgba(120, 220, 160, 0.28);
            background: rgba(80, 200, 120, 0.10);
        }

        .permissions-form {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .permissions-form input {
            width: 85px;
        }

        .code-editor {
            width: 100%;
            min-height: 430px;
            resize: vertical;
            background: #04070d;
            color: #d9e8ff;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 14px;
            font-family: Consolas, Menlo, Monaco, monospace;
            font-size: 14px;
            line-height: 1.5;
            tab-size: 4;
        }

        .preview-image {
            max-width: 100%;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        pre.code-preview {
            background: #04070d;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 14px;
            overflow: auto;
            min-height: 200px;
        }

        .small {
            font-size: 13px;
            opacity: 0.75;
        }

        .clipboard {
            border-left: 4px solid #8cc7ff;
        }

        .crumbs {
            margin-top: 10px;
            line-height: 1.8;
        }

        @media (max-width: 800px) {
            .hide-mobile {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <h1>🗂️ <?= escapeHtml($pageTitle) ?></h1>
    <div class="sub">
        Local playground. Managed folder: <code><?= escapeHtml($realBaseFolderPath) ?></code>
    </div>

    <?php if ($messageText !== ''): ?>
        <div class="notice">
            <?= escapeHtml($messageText) ?>
        </div>
    <?php endif; ?>


    <?php if ($action === 'edit'): ?>

        <?php
        $activeFileName = basename($activeFileFullPath);
        $activeFileDirectory = dirname($relativeFilePath);

        if ($activeFileDirectory === '.') {
            $activeFileDirectory = '';
        }

        $activeFilePermissions = getPermissionString($activeFileFullPath);
        $activeFileSize = (int)filesize($activeFileFullPath);
        $activeFileIsText = isProbablyTextFile($activeFileFullPath);
        $activeFileIsImage = isImageFile($activeFileFullPath);

        $fileContentForEditing = '';

        if ($activeFileIsText) {
            $fileContentForEditing = (string)@file_get_contents($activeFileFullPath);
        }
        ?>

        <div class="card">
            <div class="toolbar">
                <a href="?path=<?= escapeHtml(urlencode($activeFileDirectory)) ?>">⬅ Back to folder</a>
                <a href="?action=download&file=<?= escapeHtml(urlencode($relativeFilePath)) ?>">⬇ Download</a>

                <?php if ($activeFileIsImage): ?>
                    <a href="?action=preview&file=<?= escapeHtml(urlencode($relativeFilePath)) ?>">🖼 Preview Image</a>
                <?php endif; ?>
            </div>

            <h2><?= escapeHtml($activeFileName) ?></h2>

            <div class="small">
                Size: <?= escapeHtml(formatBytes($activeFileSize)) ?>
                &nbsp;|&nbsp;
                Permissions: <?= escapeHtml($activeFilePermissions) ?>
            </div>

            <br>

            <form method="post" class="toolbar">
                <input type="hidden" name="action" value="chmod">
                <input type="hidden" name="file" value="<?= escapeHtml($relativeFilePath) ?>">
                <input type="hidden" name="return_to" value="edit">

                <label>
                    chmod
                    <input type="text" name="chmod_value" value="<?= escapeHtml($activeFilePermissions) ?>">
                </label>

                <button type="submit">Set Permissions</button>
            </form>
        </div>

        <?php if ($activeFileIsText): ?>

            <div class="card">
                <form method="post">
                    <input type="hidden" name="action" value="save_file">
                    <input type="hidden" name="file" value="<?= escapeHtml($relativeFilePath) ?>">

                    <textarea
                        name="file_content"
                        class="code-editor"
                        spellcheck="false"
                    ><?= escapeHtml($fileContentForEditing) ?></textarea>

                    <div class="toolbar" style="margin-top: 12px;">
                        <button type="submit">💾 Save File</button>
                        <a href="?action=download&file=<?= escapeHtml(urlencode($relativeFilePath)) ?>">
                            ⬇ Download
                        </a>
                    </div>
                </form>
            </div>

        <?php else: ?>

            <div class="card">
                This does not look like a normal text/code file, so editing is disabled.

                <?php if ($activeFileIsImage): ?>
                    <br><br>
                    <img
                        class="preview-image"
                        src="?action=raw&file=<?= escapeHtml(urlencode($relativeFilePath)) ?>"
                        alt="Preview"
                    >
                <?php endif; ?>
            </div>

        <?php endif; ?>


    <?php elseif ($action === 'preview'): ?>

        <?php
        $activeFileName = basename($activeFileFullPath);
        $activeFileDirectory = dirname($relativeFilePath);

        if ($activeFileDirectory === '.') {
            $activeFileDirectory = '';
        }

        $activeFileIsImage = isImageFile($activeFileFullPath);
        $activeFileIsText = isProbablyTextFile($activeFileFullPath);
        ?>

        <div class="card">
            <div class="toolbar">
                <a href="?path=<?= escapeHtml(urlencode($activeFileDirectory)) ?>">⬅ Back to folder</a>
                <a href="?action=download&file=<?= escapeHtml(urlencode($relativeFilePath)) ?>">⬇ Download</a>

                <?php if ($activeFileIsText): ?>
                    <a href="?action=edit&file=<?= escapeHtml(urlencode($relativeFilePath)) ?>">✏ Edit</a>
                <?php endif; ?>
            </div>

            <h2><?= escapeHtml($activeFileName) ?></h2>
        </div>

        <div class="card">
            <?php if ($activeFileIsImage): ?>

                <img
                    class="preview-image"
                    src="?action=raw&file=<?= escapeHtml(urlencode($relativeFilePath)) ?>"
                    alt="Preview"
                >

            <?php elseif ($activeFileIsText): ?>

                <pre class="code-preview"><?= escapeHtml((string)@file_get_contents($activeFileFullPath)) ?></pre>

            <?php else: ?>

                No inline preview available for this file type.

            <?php endif; ?>
        </div>


    <?php else: ?>

        <?php
        // ---------------------------------------------------------------------
        // Browse view
        // ---------------------------------------------------------------------

        $parentRelativePath = dirname($relativePath);

        if ($parentRelativePath === '.') {
            $parentRelativePath = '';
        }

        $pathParts = $relativePath === '' ? [] : explode('/', $relativePath);

        // Read items in current folder.
        $allItemNames = scandir($currentFolderPath);

        if ($allItemNames === false) {
            $allItemNames = [];
        }

        $folderNames = [];
        $fileNames = [];

        foreach ($allItemNames as $itemName) {
            if ($itemName === '.' || $itemName === '..') {
                continue;
            }

            $itemFullPath = $currentFolderPath . DIRECTORY_SEPARATOR . $itemName;

            if (is_dir($itemFullPath)) {
                $folderNames[] = $itemName;
            } else {
                $fileNames[] = $itemName;
            }
        }

        sort($folderNames);
        sort($fileNames);
        ?>

        <div class="card">
            <div class="toolbar">
                <a href="?">🏠 Home</a>

                <?php if ($relativePath !== ''): ?>
                    <a href="?path=<?= escapeHtml(urlencode($parentRelativePath)) ?>">⬆ Up</a>
                <?php endif; ?>
            </div>

            <div class="crumbs">
                <strong>Path:</strong>
                <a href="?">files</a><?php
                $accumulatedPath = '';

                foreach ($pathParts as $pathPart) {
                    $accumulatedPath = $accumulatedPath === ''
                        ? $pathPart
                        : $accumulatedPath . '/' . $pathPart;

                    echo ' / <a href="?path=' . escapeHtml(urlencode($accumulatedPath)) . '">'
                        . escapeHtml($pathPart)
                        . '</a>';
                }
                ?>
            </div>
        </div>


        <?php if (!empty($_SESSION['clipboard'])): ?>

            <div class="card clipboard">
                <div class="toolbar">
                    <span>
                        Clipboard:
                        <strong><?= escapeHtml($_SESSION['clipboard']['operation'] ?? '') ?></strong>
                        <?= escapeHtml(basename($_SESSION['clipboard']['source'] ?? '')) ?>
                    </span>

                    <a href="?action=paste&path=<?= escapeHtml(urlencode($relativePath)) ?>">
                        📋 Paste Here
                    </a>

                    <a href="?action=clear_clipboard&path=<?= escapeHtml(urlencode($relativePath)) ?>">
                        🧹 Clear
                    </a>
                </div>
            </div>

        <?php endif; ?>


        <div class="card">
            <div class="toolbar">

                <form method="post">
                    <input type="hidden" name="action" value="create_folder">
                    <input type="hidden" name="path" value="<?= escapeHtml($relativePath) ?>">
                    <input type="text" name="folder_name" placeholder="Folder name" required>
                    <button type="submit">➕ New Folder</button>
                </form>

                <form method="post">
                    <input type="hidden" name="action" value="create_file">
                    <input type="hidden" name="path" value="<?= escapeHtml($relativePath) ?>">
                    <input type="text" name="new_file_name" placeholder="file.php" required>
                    <button type="submit">📄 New File</button>
                </form>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="path" value="<?= escapeHtml($relativePath) ?>">
                    <input type="file" name="uploaded_file" required>
                    <button type="submit">⬆ Upload</button>
                </form>

            </div>
        </div>


        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="hide-mobile">Size</th>
                        <th class="hide-mobile">Modified</th>
                        <th>Permissions</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($folderNames) && empty($fileNames)): ?>
                        <tr>
                            <td colspan="5">
                                This folder is empty.
                            </td>
                        </tr>
                    <?php endif; ?>


                    <?php foreach ($folderNames as $folderName): ?>

                        <?php
                        $itemRelativePath = ($relativePath === '' ? '' : $relativePath . '/') . $folderName;
                        $itemFullPath = $currentFolderPath . DIRECTORY_SEPARATOR . $folderName;

                        $itemIcon = getFileTypeIcon($itemFullPath);
                        $itemPermissions = getPermissionString($itemFullPath);
                        $itemModifiedTime = @filemtime($itemFullPath);
                        ?>

                        <tr>
                            <td>
                                <a href="?path=<?= escapeHtml(urlencode($itemRelativePath)) ?>">
                                    <?= escapeHtml($itemIcon . ' ' . $folderName) ?>/
                                </a>
                            </td>

                            <td class="hide-mobile">-</td>

                            <td class="hide-mobile">
                                <?= escapeHtml($itemModifiedTime ? date('Y-m-d H:i', $itemModifiedTime) : '?') ?>
                            </td>

                            <td>
                                <form method="post" class="permissions-form">
                                    <input type="hidden" name="action" value="chmod">
                                    <input type="hidden" name="path" value="<?= escapeHtml($relativePath) ?>">
                                    <input type="hidden" name="file" value="<?= escapeHtml($itemRelativePath) ?>">
                                    <input type="hidden" name="return_to" value="browse">

                                    <input type="text" name="chmod_value" value="<?= escapeHtml($itemPermissions) ?>">
                                    <button type="submit">Set</button>
                                </form>
                            </td>

                            <td class="actions">
                                <a href="?path=<?= escapeHtml(urlencode($itemRelativePath)) ?>">
                                    Open
                                </a>

                                <a href="?action=start_copy&source=<?= escapeHtml(urlencode($itemRelativePath)) ?>&path=<?= escapeHtml(urlencode($relativePath)) ?>">
                                    Copy
                                </a>

                                <a href="?action=start_move&source=<?= escapeHtml(urlencode($itemRelativePath)) ?>&path=<?= escapeHtml(urlencode($relativePath)) ?>">
                                    Cut
                                </a>

                                <form
                                    method="post"
                                    onsubmit="return confirm('Delete this folder and everything inside it?');"
                                >
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="path" value="<?= escapeHtml($relativePath) ?>">
                                    <input type="hidden" name="file" value="<?= escapeHtml($itemRelativePath) ?>">

                                    <button type="submit" class="danger">Delete</button>
                                </form>
                            </td>
                        </tr>

                    <?php endforeach; ?>


                    <?php foreach ($fileNames as $fileName): ?>

                        <?php
                        $itemRelativePath = ($relativePath === '' ? '' : $relativePath . '/') . $fileName;
                        $itemFullPath = $currentFolderPath . DIRECTORY_SEPARATOR . $fileName;

                        $itemIcon = getFileTypeIcon($itemFullPath);
                        $itemPermissions = getPermissionString($itemFullPath);
                        $itemModifiedTime = @filemtime($itemFullPath);
                        $itemSize = (int)@filesize($itemFullPath);

                        $itemIsText = isProbablyTextFile($itemFullPath);
                        $itemIsImage = isImageFile($itemFullPath);

                        if ($itemIsText) {
                            $mainActionLabel = 'Edit';
                            $mainActionUrl = '?action=edit&file=' . urlencode($itemRelativePath);
                        } elseif ($itemIsImage) {
                            $mainActionLabel = 'View';
                            $mainActionUrl = '?action=preview&file=' . urlencode($itemRelativePath);
                        } else {
                            $mainActionLabel = 'Download';
                            $mainActionUrl = '?action=download&file=' . urlencode($itemRelativePath);
                        }
                        ?>

                        <tr>
                            <td>
                                <a href="<?= escapeHtml($mainActionUrl) ?>">
                                    <?= escapeHtml($itemIcon . ' ' . $fileName) ?>
                                </a>
                            </td>

                            <td class="hide-mobile">
                                <?= escapeHtml(formatBytes($itemSize)) ?>
                            </td>

                            <td class="hide-mobile">
                                <?= escapeHtml($itemModifiedTime ? date('Y-m-d H:i', $itemModifiedTime) : '?') ?>
                            </td>

                            <td>
                                <form method="post" class="permissions-form">
                                    <input type="hidden" name="action" value="chmod">
                                    <input type="hidden" name="path" value="<?= escapeHtml($relativePath) ?>">
                                    <input type="hidden" name="file" value="<?= escapeHtml($itemRelativePath) ?>">
                                    <input type="hidden" name="return_to" value="browse">

                                    <input type="text" name="chmod_value" value="<?= escapeHtml($itemPermissions) ?>">
                                    <button type="submit">Set</button>
                                </form>
                            </td>

                            <td class="actions">

                                <?php if ($itemIsText): ?>
                                    <a href="?action=edit&file=<?= escapeHtml(urlencode($itemRelativePath)) ?>">
                                        Edit
                                    </a>
                                <?php endif; ?>

                                <?php if ($itemIsImage): ?>
                                    <a href="?action=preview&file=<?= escapeHtml(urlencode($itemRelativePath)) ?>">
                                        Preview
                                    </a>
                                <?php endif; ?>

                                <a href="?action=download&file=<?= escapeHtml(urlencode($itemRelativePath)) ?>">
                                    Download
                                </a>

                                <a href="?action=start_copy&source=<?= escapeHtml(urlencode($itemRelativePath)) ?>&path=<?= escapeHtml(urlencode($relativePath)) ?>">
                                    Copy
                                </a>

                                <a href="?action=start_move&source=<?= escapeHtml(urlencode($itemRelativePath)) ?>&path=<?= escapeHtml(urlencode($relativePath)) ?>">
                                    Cut
                                </a>

                                <form
                                    method="post"
                                    onsubmit="return confirm('Delete this file?');"
                                >
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="path" value="<?= escapeHtml($relativePath) ?>">
                                    <input type="hidden" name="file" value="<?= escapeHtml($itemRelativePath) ?>">

                                    <button type="submit" class="danger">Delete</button>
                                </form>

                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

</body>
</html>

<?php

declare(strict_types=1);

$timestamp = date('Y-m-d_H-i-s');   // Get the current timestamp

echo "\nProcessing files...\n";

//  Iterate through a directory structure while excluding specific files and directories
// (e.g. process_files.php, _backup files, and languages directory, in my use case)
// return true for dirs and files to be backed up
class BackupFolderFilter extends RecursiveFilterIterator
{
    public function accept(): bool
    {
        $path = $this->current()->getPathname();
        if ($this->current()->isDir()) {
            $basename = basename($path);
            return !str_contains($basename, '_backup') && $basename !== 'languages';
        }
        $filename = basename($path);
        return $filename !== 'process_files.php';
    }
}

//  Write log messages to a file
function writeLog(string $message): void
{
    global $logFile;
    if ($logFile === null) {
        echo "Error: \$logFile is null\n";
    } else {
        file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND);
    }
}

$emptyFileCount = 0;
$emptyFilePath = '';

//  Process a file and make changes to it
function processFile(string $filePath): bool
{
    //  Skip empty files
    if (filesize($filePath) === 0) {
        global $emptyFileCount;
        $emptyFileCount++;
        writeLog("Skipped empty file: $filePath");
        global $emptyFilePath;
        $emptyFilePath = $filePath;
        return false;
    }
    //  Skip corrupted files
    $content = file_get_contents($filePath);
    if ($content === false) {
        writeLog("Failed to read file: $filePath");
        return false;
    }
    //  Report where deprecated 'ereg' or 'session_register' is found, for manual review,
    //  usually to be updated with 'preg_match' and '_SESSION', respectively
    $eregCount = substr_count($content, 'ereg');
    $sessRegCount = substr_count($content, 'session_register');
    if ($eregCount > 0) {
        echo "Found $eregCount instance(s) of 'ereg' in file: $filePath\n";
    }
    if ($sessRegCount > 0) {
        echo "Found $sessRegCount instance(s) of 'session_register' in file: $filePath\n";
    }

    $changes = [];

    $patterns = [
        '/\b(require|include|require_once|include_once)\s*\((.*?)\)\s*;/i' =>
        fn ($matches) => "{$matches[1]} {$matches[2]};",
        '/(require|include|require_once|include_once)\s+([\'"].*?)(?<!\{)\$\{(\$[^}]+)\}(?!\})(.*?[\'"])/' =>
        fn ($matches) => "{$matches[1]} {$matches[2]}{\${{$matches[3]}}}{$matches[4]}",
        '/\b(require|include|require_once|include_once)(["\'])/' =>
        fn ($matches) => "{$matches[1]} {$matches[2]}",
        '/\b(TRUE|FALSE)\b/' =>
        fn ($matches) => strtolower($matches[1]),
    ];

    foreach ($patterns as $pattern => $replacement) {
        $newContent = preg_replace_callback($pattern, $replacement, $content, -1, $count);
        if ($count > 0) {
            $changeDescription = match ($pattern) {
                '/\b(require|include|require_once|include_once)\s*\((.*?)\)\s*;/i' => "Removed brackets from require/include in $count places.",
                '/(require|include|require_once|include_once)\s+([\'"].*?)(?<!\{)\$\{(\$[^}]+)\}(?!\})(.*?[\'"])/' => "Changed \${\$...} to {\${\$...}} for PHP 8.3 compatibility in $count places.",
                '/\b(require|include|require_once|include_once)(["\'])/' => "Ensured space between include/require and the string in $count places.",
                '/\b(TRUE|FALSE)\b/' => "Changed boolean literals to lowercase in $count places.",
            };
            if ($changeDescription !== null) {
                $changes[] = $changeDescription;
            }
            $content = $newContent;
        }
    }

    // Handle 'else if' to 'elseif'
    $newContent = preg_replace('/\belse\s+if\b/', 'elseif', $content, -1, $count);
    if ($count > 0) {
        $changes[] = "Replaced 'else if' with 'elseif' in $count places.";
        $content = $newContent;
    }

    // Handle 'and/AND' and 'or/OR' within if statements
    $newContent = preg_replace_callback('/\bif\s*\(((?:[^()]+|\((?:[^()]+|\([^()]*\))*\))*)\)/i', function ($matches) use (&$count) {
        $condition = $matches[1];
        $replaced = preg_replace_callback('/\b(and|AND|or|OR)\b(?=(?:[^"\']*["\'][^"\']*["\'])*[^"\']*$)/', function ($match) use (&$count) {
            $count++;
            return $match[1] === 'and' || $match[1] === 'AND' ? '&&' : '||';
        }, $condition);
        return "if (" . $replaced . ")";
    }, $content);

    if ($count > 0) {
        $changes[] = "Changed 'and/AND' to '&&' and 'or/OR' to '||' in if statements in $count places.";
        $content = $newContent;
    }

    if ($content !== null) {
        $lines = explode("\n", $content);
        $lines = array_map(fn ($line) => rtrim($line), $lines);
        $content = implode("\n", $lines);
    } else {
        writeLog("File content is null for: $filePath");
        return false;
    }

    if (file_put_contents($filePath, $content) === false) {
        writeLog("Failed to write file: $filePath");
        return false;
    }

    // Filter out null values from changes array
    $changes = array_filter($changes, fn ($change) => $change !== null);

    if (!empty($changes)) {
        echo "Changes made to file: $filePath\n";
        writeLog("Changes made to file: $filePath");
        array_walk($changes, fn ($change) => writeLog("  - $change"));
    }
    return true;
}

$dir = "your_directory_name";
$path = "your_path/{$dir}";
$logFile = "{$path}/logs/{$dir}_processing_log_{$timestamp}.txt";
$backupDirectory = "{$path}/{$dir}_backup_{$timestamp}";

// Ensure the log directory exists
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}
// Ensure the backup directory exists
if (!is_dir($backupDirectory)) {
    mkdir($backupDirectory, 0777, true);
}

$directoryIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
$filterIterator = new BackupFolderFilter($directoryIterator);
$iterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::SELF_FIRST);

$processedCount = 0;
$backedUpCount = 0;
$errorCount = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getPathname();

        $backupPath = "{$backupDirectory}/{$iterator->getSubPathName()}";
        if (!is_dir(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0777, true);
        }

        if (copy($filePath, $backupPath)) {
            $backedUpCount++;
            writeLog("Backed up: {$filePath}");
        } else {
            $errorCount++;
            writeLog("Failed to backup: {$filePath}");
        }

        if (processFile($filePath)) {
            writeLog("Processed: {$filePath}");
            $processedCount++;
        } elseif ($filePath === $emptyFilePath) {
            echo "\nSkipping empty file: " . basename($filePath);
        } else {
            $errorCount++;
            writeLog("Failed to process: {$filePath}");
        }
    }
}

writeLog("--------------------------\n");
writeLog("Processing complete!\n");
writeLog("Total files backed up: {$backedUpCount}\n");
writeLog("Total files processed: {$processedCount}\n");
writeLog("Total empty files skipped: {$emptyFileCount}\n");
writeLog("Total errors encountered: {$errorCount}\n");
echo "\n-------------------------\nProcessing complete!\n";
echo "\nTotal files backed up: {$backedUpCount}";
echo "\nTotal files processed: {$processedCount}";
echo "\nTotal empty files skipped: {$emptyFileCount}";
echo "\nTotal errors encountered: {$errorCount}";
echo "\n\nLog file: {$logFile}";
echo "\n-------------------------\n";

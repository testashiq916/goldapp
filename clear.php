<?php

declare(strict_types=1);

$root = __DIR__;

$targets = [
    $root . '/bootstrap/cache/config.php',
    $root . '/bootstrap/cache/routes.php',
    $root . '/bootstrap/cache/routes-v7.php',
    $root . '/bootstrap/cache/services.php',
    $root . '/bootstrap/cache/packages.php',
];

$directories = [
    $root . '/storage/framework/views',
    $root . '/storage/framework/cache/data',
    $root . '/storage/framework/sessions',
];

$deletedFiles = [];
$errors = [];

function deleteFileIfExists(string $path, array &$deletedFiles, array &$errors): void
{
    if (!file_exists($path)) {
        return;
    }

    if (!is_file($path)) {
        $errors[] = "Skipped non-file path: {$path}";
        return;
    }

    if (@unlink($path)) {
        $deletedFiles[] = $path;
        return;
    }

    $errors[] = "Failed to delete file: {$path}";
}

function clearDirectoryFiles(string $dir, array &$deletedFiles, array &$errors): void
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();

        if ($item->isFile() || $item->isLink()) {
            if (@unlink($path)) {
                $deletedFiles[] = $path;
            } else {
                $errors[] = "Failed to delete file: {$path}";
            }
            continue;
        }

        // Keep the top-level target directory, but remove nested empty folders.
        if ($path !== $dir && @rmdir($path)) {
            $deletedFiles[] = $path . ' [dir]';
        }
    }
}

foreach ($targets as $file) {
    deleteFileIfExists($file, $deletedFiles, $errors);
}

foreach ($directories as $dir) {
    clearDirectoryFiles($dir, $deletedFiles, $errors);
}

header('Content-Type: text/plain; charset=UTF-8');

echo "Manual cache clear finished.\n\n";
echo "Deleted entries: " . count($deletedFiles) . "\n";
foreach ($deletedFiles as $path) {
    echo " - {$path}\n";
}

if ($errors !== []) {
    echo "\nErrors: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo " - {$error}\n";
    }
} else {
    echo "\nNo errors.\n";
}

echo "\nImportant:\n";
echo " - Sessions were cleared, so users may need to log in again.\n";
echo " - Delete this clear.php file after use for safety.\n";

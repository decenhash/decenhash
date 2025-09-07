<?php
/**
 * Rename all files in "files" directory to their SHA-256 hash + original extension.
 * If a file with the target name already exists, delete the current file
 * (only if its name is not already the correct hash).
 */

$dir = __DIR__ . '/files';

if (!is_dir($dir)) {
    die("Directory 'files' not found.\n");
}

$files = scandir($dir);

foreach ($files as $file) {
    $filePath = $dir . '/' . $file;

    // Skip . and ..
    if ($file === '.' || $file === '..') {
        continue;
    }

    // Skip directories
    if (is_dir($filePath)) {
        continue;
    }

    // Get extension
    $extension = pathinfo($file, PATHINFO_EXTENSION);

    // Calculate hash
    $hash = hash_file('sha256', $filePath);

    // Build new filename
    $newName = $hash . ($extension ? '.' . $extension : '');
    $newPath = $dir . '/' . $newName;

    // If file already correctly named
    if ($file === $newName) {
        echo "Skipping (already correct): $file\n";
        continue;
    }

    // If target already exists -> delete current
    if (file_exists($newPath)) {
        if (unlink($filePath)) {
            echo "Deleted duplicate: $file (already exists as $newName)\n";
        } else {
            echo "Failed to delete $file\n";
        }
        continue;
    }

    // Otherwise rename
    if (rename($filePath, $newPath)) {
        echo "Renamed: $file -> $newName\n";
    } else {
        echo "Failed to rename $file\n";
    }
}

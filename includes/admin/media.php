<?php
declare(strict_types=1);

function admin_collect_referenced_media(array $content): array
{
    // Keep this collector conservative: files may be shared between pages, so
    // deletion only happens when a file is truly unreferenced.
    $referenced = [];
    $remember = static function ($file) use (&$referenced): void {
        $file = admin_safe_media_filename((string) $file);

        if ($file !== '') {
            $referenced[$file] = true;
        }
    };

    $remember($content['site']['logo'] ?? '');
    $remember($content['site']['favicon'] ?? '');

    foreach (($content['backgrounds'] ?? []) as $item) {
        if (is_array($item)) {
            $remember($item['file'] ?? '');
        } else {
            $remember($item);
        }
    }

    foreach (($content['galleries'] ?? []) as $gallery) {
        foreach ((array) $gallery as $item) {
            if (is_array($item)) {
                $remember($item['file'] ?? '');
            } else {
                $remember($item);
            }
        }
    }

    foreach (($content['rooms'] ?? []) as $room) {
        if (is_array($room)) {
            $remember($room['image'] ?? '');
        }
    }

    return $referenced;
}

function admin_delete_unreferenced_media(array $files, array $content): array
{
    $referenced = admin_collect_referenced_media($content);
    $directory = realpath(base_path('assets/img'));
    $result = [
        'deleted' => [],
        'failed' => [],
    ];

    if ($directory === false) {
        return $result;
    }

    $directoryPrefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    foreach (array_values(array_unique($files)) as $file) {
        $file = admin_safe_media_filename((string) $file);

        if ($file === '' || isset($referenced[$file])) {
            continue;
        }

        $target = $directoryPrefix . $file;
        $targetPath = realpath($target);

        if ($targetPath === false || !str_starts_with($targetPath, $directoryPrefix)) {
            continue;
        }

        if (!@unlink($targetPath)) {
            $result['failed'][] = $file;
            continue;
        }

        $result['deleted'][] = $file;
    }

    return $result;
}

function admin_queue_media_deletion(array $files): void
{
    if (!isset($GLOBALS['admin_pending_media_deletions']) || !is_array($GLOBALS['admin_pending_media_deletions'])) {
        $GLOBALS['admin_pending_media_deletions'] = [];
    }

    foreach ($files as $file) {
        $file = admin_safe_media_filename((string) $file);

        if ($file !== '') {
            $GLOBALS['admin_pending_media_deletions'][] = $file;
        }
    }

    $GLOBALS['admin_pending_media_deletions'] = array_values(array_unique($GLOBALS['admin_pending_media_deletions']));
}

function admin_flush_media_deletions(array $content): array
{
    $files = $GLOBALS['admin_pending_media_deletions'] ?? [];
    $GLOBALS['admin_pending_media_deletions'] = [];

    return is_array($files) ? admin_delete_unreferenced_media($files, $content) : [
        'deleted' => [],
        'failed' => [],
    ];
}

function admin_upload_images(array $files, string $directory = ''): array
{
    if (($files['name'] ?? []) === []) {
        return [];
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $temporaryNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $uploaded = [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $relativeDirectory = trim(str_replace('\\', '/', $directory), '/');
    $targetDirectory = base_path('assets/img' . ($relativeDirectory === '' ? '' : '/' . $relativeDirectory));

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
        throw new RuntimeException('De afbeeldingenmap kon niet worden aangemaakt.');
    }

    if (!is_writable($targetDirectory)) {
        throw new RuntimeException('De afbeeldingenmap is niet schrijfbaar. Controleer de rechten van assets/img.');
    }

    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'De afbeelding is groter dan de serverlimiet.',
        UPLOAD_ERR_FORM_SIZE => 'De afbeelding is groter dan toegestaan.',
        UPLOAD_ERR_PARTIAL => 'De afbeelding werd maar gedeeltelijk opgeladen.',
        UPLOAD_ERR_NO_TMP_DIR => 'De tijdelijke uploadmap ontbreekt op de server.',
        UPLOAD_ERR_CANT_WRITE => 'De server kon de afbeelding niet wegschrijven.',
        UPLOAD_ERR_EXTENSION => 'Een PHP-extensie heeft de upload gestopt.',
    ];

    foreach ($names as $index => $originalName) {
        if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (($errors[$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($errorMessages[$errors[$index]] ?? 'Een afbeelding kon niet worden geupload.');
        }

        $temporaryName = (string) ($temporaryNames[$index] ?? '');

        if (!is_uploaded_file($temporaryName) || getimagesize($temporaryName) === false) {
            throw new RuntimeException('Upload alleen geldige afbeeldingsbestanden.');
        }

        $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Alleen jpg, png, gif en webp zijn toegestaan.');
        }

        $baseName = strtolower(pathinfo((string) $originalName, PATHINFO_FILENAME));
        $baseName = trim((string) preg_replace('/[^a-z0-9-]+/', '-', $baseName), '-');
        $baseName = $baseName === '' ? 'afbeelding' : $baseName;
        $fileName = $baseName . '.' . $extension;
        $target = $targetDirectory . '/' . $fileName;
        $counter = 2;

        while (is_file($target)) {
            $fileName = $baseName . '-' . $counter . '.' . $extension;
            $target = $targetDirectory . '/' . $fileName;
            $counter++;
        }

        if (!move_uploaded_file($temporaryName, $target)) {
            throw new RuntimeException('De afbeelding kon niet worden opgeslagen.');
        }

        $uploaded[] = $relativeDirectory === '' ? $fileName : $relativeDirectory . '/' . $fileName;
    }

    return $uploaded;
}

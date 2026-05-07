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
        'kept' => [],
        'missing' => [],
    ];

    if ($directory === false) {
        return $result;
    }

    $directoryPrefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    foreach (array_values(array_unique($files)) as $file) {
        $file = admin_safe_media_filename((string) $file);

        if ($file === '') {
            continue;
        }

        if (isset($referenced[$file])) {
            $result['kept'][] = $file;
            continue;
        }

        $target = $directoryPrefix . str_replace('/', DIRECTORY_SEPARATOR, $file);
        $targetPath = realpath($target);

        if ($targetPath === false) {
            if (file_exists($target)) {
                $result['failed'][] = $file;
            } else {
                $result['missing'][] = $file;
            }
            continue;
        }

        $targetInsideMedia = DIRECTORY_SEPARATOR === '\\'
            ? strncasecmp($targetPath, $directoryPrefix, strlen($directoryPrefix)) === 0
            : strncmp($targetPath, $directoryPrefix, strlen($directoryPrefix)) === 0;

        if (!$targetInsideMedia || (!is_file($targetPath) && !is_link($target))) {
            $result['failed'][] = $file;
            continue;
        }

        if (!@unlink($target)) {
            $result['failed'][] = $file;
            continue;
        }

        $result['deleted'][] = $file;
    }

    return $result;
}

function admin_clean_media_file_list(array $files): array
{
    $clean = [];

    foreach ($files as $file) {
        $file = admin_safe_media_filename((string) $file);

        if ($file !== '') {
            $clean[] = $file;
        }
    }

    return array_values(array_unique($clean));
}

function admin_queue_media_deletion(array $files, bool $removeReferences = true): void
{
    if (!isset($GLOBALS['admin_pending_media_deletions']) || !is_array($GLOBALS['admin_pending_media_deletions'])) {
        $GLOBALS['admin_pending_media_deletions'] = [];
    }

    $GLOBALS['admin_pending_media_deletions'] = admin_clean_media_file_list(array_merge(
        $GLOBALS['admin_pending_media_deletions'],
        $files
    ));

    if (!$removeReferences) {
        return;
    }

    if (!isset($GLOBALS['admin_pending_media_reference_removals']) || !is_array($GLOBALS['admin_pending_media_reference_removals'])) {
        $GLOBALS['admin_pending_media_reference_removals'] = [];
    }

    $GLOBALS['admin_pending_media_reference_removals'] = admin_clean_media_file_list(array_merge(
        $GLOBALS['admin_pending_media_reference_removals'],
        $files
    ));
}

function admin_take_media_deletions(): array
{
    $files = $GLOBALS['admin_pending_media_deletions'] ?? [];
    $GLOBALS['admin_pending_media_deletions'] = [];

    return is_array($files) ? admin_clean_media_file_list($files) : [];
}

function admin_take_media_reference_removals(): array
{
    $files = $GLOBALS['admin_pending_media_reference_removals'] ?? [];
    $GLOBALS['admin_pending_media_reference_removals'] = [];

    return is_array($files) ? admin_clean_media_file_list($files) : [];
}

function admin_media_item_file($item): string
{
    if (is_array($item)) {
        return admin_safe_media_filename((string) ($item['file'] ?? ''));
    }

    return admin_safe_media_filename((string) $item);
}

function admin_remove_media_references(array &$content, array $files): void
{
    $remove = array_fill_keys(array_map('strval', $files), true);

    if ($remove === []) {
        return;
    }

    foreach (['logo', 'favicon'] as $field) {
        $file = admin_safe_media_filename((string) ($content['site'][$field] ?? ''));

        if ($file !== '' && isset($remove[$file])) {
            $content['site'][$field] = '';
        }
    }

    if (isset($content['backgrounds']) && is_array($content['backgrounds'])) {
        $content['backgrounds'] = array_values(array_filter(
            $content['backgrounds'],
            static function ($item) use ($remove): bool {
                $file = admin_media_item_file($item);

                return $file === '' || !isset($remove[$file]);
            }
        ));
    }

    if (isset($content['galleries']) && is_array($content['galleries'])) {
        foreach ($content['galleries'] as $galleryKey => $gallery) {
            if (!is_array($gallery)) {
                continue;
            }

            $content['galleries'][$galleryKey] = array_values(array_filter(
                $gallery,
                static function ($item) use ($remove): bool {
                    $file = admin_media_item_file($item);

                    return $file === '' || !isset($remove[$file]);
                }
            ));
        }
    }

    if (isset($content['rooms']) && is_array($content['rooms'])) {
        foreach ($content['rooms'] as $roomKey => $room) {
            if (!is_array($room)) {
                continue;
            }

            $file = admin_safe_media_filename((string) ($room['image'] ?? ''));

            if ($file === '' || !isset($remove[$file])) {
                continue;
            }

            $galleryKey = (string) ($room['gallery'] ?? $roomKey);
            $content['rooms'][$roomKey]['image'] = admin_first_media_file((array) ($content['galleries'][$galleryKey] ?? []));
        }
    }
}

function admin_ini_size_to_bytes(string $value): int
{
    $value = trim($value);

    if ($value === '') {
        return 0;
    }

    $number = (float) $value;

    if ($number <= 0) {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $multiplier = 1;

    if ($unit === 'g') {
        $multiplier = 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $multiplier = 1024 * 1024;
    } elseif ($unit === 'k') {
        $multiplier = 1024;
    }

    return (int) round($number * $multiplier);
}

function admin_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1, ',', ''), '0'), ',') . ' MB';
    }

    if ($bytes >= 1024) {
        return rtrim(rtrim(number_format($bytes / 1024, 1, ',', ''), '0'), ',') . ' KB';
    }

    return $bytes . ' bytes';
}

function admin_upload_post_limit_bytes(): int
{
    return admin_ini_size_to_bytes((string) ini_get('post_max_size'));
}

function admin_upload_file_limit_bytes(): int
{
    return admin_ini_size_to_bytes((string) ini_get('upload_max_filesize'));
}

function admin_effective_upload_limit_bytes(): int
{
    $limits = array_filter([
        admin_upload_post_limit_bytes(),
        admin_upload_file_limit_bytes(),
    ], static function (int $limit): bool {
        return $limit > 0;
    });

    return $limits === [] ? 0 : min($limits);
}

function admin_upload_limit_message(int $contentLength = 0): string
{
    $limits = [];
    $postLimit = admin_upload_post_limit_bytes();
    $fileLimit = admin_upload_file_limit_bytes();

    if ($postLimit > 0) {
        $limits[] = 'request maximaal ' . admin_format_bytes($postLimit);
    }

    if ($fileLimit > 0) {
        $limits[] = 'bestand maximaal ' . admin_format_bytes($fileLimit);
    }

    $message = 'De upload is te groot voor deze server';

    if ($contentLength > 0) {
        $message .= ' (' . admin_format_bytes($contentLength) . ')';
    }

    if ($limits !== []) {
        $message .= '; limiet: ' . implode(', ', $limits);
    }

    return $message . '. Upload minder foto\'s tegelijk of verklein de bestanden.';
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
        UPLOAD_ERR_INI_SIZE => admin_upload_limit_message(),
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

        if (!is_uploaded_file($temporaryName) || @getimagesize($temporaryName) === false) {
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

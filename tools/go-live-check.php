<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);

require_once $root . '/includes/bootstrap.php';
require_once $root . '/includes/admin.php';

$errors = [];
$warnings = [];
$ok = [];

$add = static function (array &$bucket, string $message): void {
    $bucket[] = $message;
};

$checkExists = static function (string $path, string $label) use (&$errors, &$ok, $add): void {
    if (file_exists($path)) {
        $add($ok, $label . ' bestaat.');
        return;
    }

    $add($errors, $label . ' ontbreekt.');
};

$checkWritableDir = static function (string $path, string $label) use (&$errors, &$ok, $add): void {
    if (!is_dir($path)) {
        $add($errors, $label . ' ontbreekt.');
        return;
    }

    if (!is_writable($path)) {
        $add($errors, $label . ' is niet schrijfbaar door PHP.');
        return;
    }

    $add($ok, $label . ' is schrijfbaar.');
};

$checkWritableDirectoryTree = static function (string $path, string $label) use (&$errors, &$ok, $add): void {
    if (!is_dir($path)) {
        return;
    }

    $notWritable = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isDir()) {
            continue;
        }

        if (!$fileInfo->isWritable()) {
            $notWritable[] = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($path) + 1));
        }
    }

    if ($notWritable === []) {
        $add($ok, $label . ' submappen zijn schrijfbaar.');
        return;
    }

    $add(
        $errors,
        $label . ' bevat niet-schrijfbare submappen: '
            . implode(', ', array_slice($notWritable, 0, 8))
            . (count($notWritable) > 8 ? ', ...' : '')
    );
};

$gitTrackedCache = null;
$gitTrackedPaths = static function () use (&$gitTrackedCache, $root): array {
    if ($gitTrackedCache !== null) {
        return $gitTrackedCache;
    }

    if (!is_dir($root . '/.git')) {
        $gitTrackedCache = [];
        return $gitTrackedCache;
    }

    $output = [];
    $exitCode = 0;
    exec('git -C ' . escapeshellarg($root) . ' ls-files', $output, $exitCode);

    $gitTrackedCache = $exitCode === 0 ? array_fill_keys($output, true) : [];

    return $gitTrackedCache;
};

$pathIsGitTracked = static function (string $relativePath) use ($gitTrackedPaths): bool {
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));

    return isset($gitTrackedPaths()[$relativePath]);
};

$checkGitTracked = static function (string $relativePath, string $label) use (&$errors, &$ok, $add, $pathIsGitTracked, $root): void {
    if (!is_dir($root . '/.git')) {
        return;
    }

    if ($pathIsGitTracked($relativePath)) {
        $add($ok, $label . ' staat in git.');
        return;
    }

    $add($errors, $label . ' bestaat lokaal maar staat niet in git; voeg dit toe aan het deploy-artefact.');
};

if (PHP_VERSION_ID < 70400) {
    $add($errors, 'PHP 7.4 of nieuwer is vereist. PHP 8.1 of nieuwer is aanbevolen. Gevonden: ' . PHP_VERSION);
} elseif (PHP_VERSION_ID < 80100) {
    $add($warnings, 'PHP 8.1 of nieuwer is aanbevolen. Gevonden: ' . PHP_VERSION);
} else {
    $add($ok, 'PHP-versie OK: ' . PHP_VERSION);
}

if (function_exists('mail')) {
    $add($ok, 'PHP mail() is beschikbaar voor contact- en resetmails.');
} else {
    $add($warnings, 'PHP mail() is niet beschikbaar. Contactmails en wachtwoordresetmails kunnen dan niet worden verzonden.');
}

$fileUploadsEnabled = in_array(strtolower((string) ini_get('file_uploads')), ['1', 'on', 'true', 'yes'], true);
$uploadFileLimit = admin_upload_file_limit_bytes();
$uploadPostLimit = admin_upload_post_limit_bytes();
$effectiveUploadLimit = admin_effective_upload_limit_bytes();

if ($fileUploadsEnabled) {
    $add($ok, 'PHP file_uploads is actief.');
} else {
    $add($errors, 'PHP file_uploads staat uit; foto-uploads in beheer zullen mislukken.');
}

if ($uploadFileLimit > 0) {
    $add($ok, 'upload_max_filesize: ' . admin_format_bytes($uploadFileLimit) . '.');
} else {
    $add($warnings, 'upload_max_filesize kon niet betrouwbaar worden gelezen.');
}

if ($uploadPostLimit > 0) {
    $add($ok, 'post_max_size: ' . admin_format_bytes($uploadPostLimit) . '.');
} else {
    $add($warnings, 'post_max_size kon niet betrouwbaar worden gelezen.');
}

if ($uploadFileLimit > 0 && $uploadPostLimit > 0 && $uploadPostLimit < $uploadFileLimit) {
    $add($warnings, 'post_max_size is kleiner dan upload_max_filesize; grotere foto-uploads kunnen leeg binnenkomen.');
}

if ($effectiveUploadLimit > 0 && $effectiveUploadLimit < 2 * 1024 * 1024) {
    $add($warnings, 'Effectieve uploadlimiet is laag (' . admin_format_bytes($effectiveUploadLimit) . '). Grote foto\'s zullen mislukken.');
}

foreach ([
    'index.php',
    '.htaccess',
    'README.md',
    'includes/bootstrap.php',
    'includes/content.php',
    'includes/admin.php',
    'includes/admin/settings.php',
    'includes/admin/session.php',
    'includes/admin/auth.php',
    'includes/admin/content-helpers.php',
    'includes/admin/media.php',
    'includes/admin/content-save.php',
    'views/layout.php',
    'views/pages',
    'views/partials',
    'assets/css/style.css',
    'assets/css/admin.css',
    'assets/js/app.js',
    'assets/js/admin.js',
    'beheer/index.php',
    'beheer/partials/form-helpers.php',
    'beheer/sections',
] as $requiredPath) {
    $checkExists($root . '/' . $requiredPath, $requiredPath);
}

foreach ([
    'storage/.htaccess',
    'includes/.htaccess',
    'views/.htaccess',
    'beheer/partials/.htaccess',
    'beheer/sections/.htaccess',
    'tools/.htaccess',
] as $protectedPath) {
    $checkExists($root . '/' . $protectedPath, $protectedPath . ' bescherming');
}

$checkWritableDir(storage_path(), 'storage/');
$checkWritableDir(base_path('assets/img'), 'assets/img/');
$checkWritableDirectoryTree(base_path('assets/img'), 'assets/img/');

if (is_dir(content_directory_path())) {
    $add($ok, 'Gesplitste contentmap bestaat: storage/content/.');

    foreach (glob(content_directory_path() . '/*.json') ?: [] as $contentFile) {
        $relativePath = str_replace('\\', '/', substr($contentFile, strlen($root) + 1));
        $checkGitTracked($relativePath, $relativePath);
    }
} elseif (is_file(content_json_path())) {
    $add($warnings, 'Legacy contentbestand gevonden: storage/content.json. Bewaren via admin migreert dit automatisch naar storage/content/.');
} else {
    $add($warnings, 'Geen content-opslag gevonden in storage/content/ of storage/content.json.');
}

foreach ([
    'includes/admin/settings.php',
    'includes/admin/session.php',
    'includes/admin/auth.php',
    'includes/admin/content-helpers.php',
    'includes/admin/media.php',
    'includes/admin/content-save.php',
    'beheer/sections/.htaccess',
    'beheer/sections/access.php',
    'beheer/sections/contact.php',
    'beheer/sections/general.php',
    'beheer/sections/links.php',
    'beheer/sections/page.php',
    'beheer/sections/room.php',
] as $trackedRuntimePath) {
    $checkGitTracked($trackedRuntimePath, $trackedRuntimePath);
}

if (is_file(admin_settings_path())) {
    if ($pathIsGitTracked('storage/admin.php')) {
        $add($errors, 'storage/admin.php staat in git. Dit bevat runtime login-instellingen en mag niet worden gedeployed vanuit development.');
    } else {
        $add($warnings, 'storage/admin.php bestaat lokaal. Upload dit runtime loginbestand niet vanuit development.');
    }
}

$content = load_content();

// Keep the deployment check strict about common editor/test artifacts so the
// site is not published with leftover local content.
$contentStrings = [];
$collectStrings = static function ($value, string $path = '') use (&$collectStrings, &$contentStrings): void {
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $collectStrings($item, $path === '' ? (string) $key : $path . '.' . $key);
        }

        return;
    }

    if (is_string($value)) {
        $contentStrings[$path] = $value;
    }
};
$collectStrings($content);

foreach ($contentStrings as $path => $value) {
    $plain = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if (strcasecmp($plain, 'test') === 0) {
        $add($warnings, 'Mogelijke testcontent gevonden in ' . $path . '.');
    }

    if (str_contains($value, '<strong><strong>') || str_contains($value, '<a></a>') || str_contains($value, '&amp;amp;')) {
        $add($warnings, 'Mogelijk rich-text artefact gevonden in ' . $path . '.');
    }
}

foreach (['site', 'pages', 'rooms', 'galleries', 'backgrounds', 'navigation', 'footer_navigation'] as $key) {
    if (!isset($content[$key]) || !is_array($content[$key])) {
        $add($errors, 'Contentstructuur mist array: ' . $key);
    }
}

$siteEmail = (string) ($content['site']['email'] ?? '');
if ($siteEmail === '' || filter_var($siteEmail, FILTER_VALIDATE_EMAIL) === false) {
    $add($warnings, 'Site e-mailadres lijkt ongeldig of leeg.');
} else {
    $add($ok, 'Site e-mailadres is geldig.');
}

$siteLogo = admin_safe_media_filename((string) ($content['site']['logo'] ?? ''));
if ($siteLogo !== '' && !is_file(base_path('assets/img/' . $siteLogo))) {
    $add($errors, 'Logo ontbreekt in assets/img: ' . $siteLogo);
}

$requiredPages = ['home', 'leuven', 'locatie', 'contact', 'links', 'voorwaarden'];
foreach ($requiredPages as $pageKey) {
    if (!isset($content['pages'][$pageKey]) || !is_array($content['pages'][$pageKey])) {
        $add($errors, 'Pagina ontbreekt in content: ' . $pageKey);
        continue;
    }

    foreach (supported_languages() as $language => $label) {
        $title = trim((string) ($content['pages'][$pageKey]['translations'][$language]['title'] ?? $content['pages'][$pageKey]['title'] ?? ''));

        if ($title === '') {
            $add($warnings, 'Pagina ' . $pageKey . ' mist een titel voor ' . $label . '.');
        }
    }
}

$mapUrl = trim((string) ($content['pages']['locatie']['map_url'] ?? ''));
if ($mapUrl !== '' && filter_var($mapUrl, FILTER_VALIDATE_URL) === false) {
    $add($errors, 'Google Maps URL op locatie is geen geldige URL.');
}

$requiredRooms = ['kamer-1', 'kamer-2', 'kamer-3'];
foreach ($requiredRooms as $roomKey) {
    if (!isset($content['rooms'][$roomKey]) || !is_array($content['rooms'][$roomKey])) {
        $add($errors, 'Kamer ontbreekt in content: ' . $roomKey);
        continue;
    }

    $gallery = $content['galleries'][$roomKey] ?? [];
    if (!is_array($gallery) || $gallery === []) {
        $add($warnings, $roomKey . ' heeft geen galerijfoto\'s.');
    }

    foreach (supported_languages() as $language => $label) {
        $translation = $content['rooms'][$roomKey]['translations'][$language] ?? [];
        $title = trim((string) ($translation['title'] ?? $content['rooms'][$roomKey]['title'] ?? ''));
        $bookingUrl = trim((string) ($translation['booking_url'] ?? $content['rooms'][$roomKey]['booking_url'] ?? ''));

        if ($title === '') {
            $add($warnings, $roomKey . ' mist een titel voor ' . $label . '.');
        }

        if ($bookingUrl === '' || filter_var($bookingUrl, FILTER_VALIDATE_URL) === false) {
            $add($errors, $roomKey . ' mist een geldige booking link voor ' . $label . '.');
        }
    }
}

$referencedMedia = admin_collect_referenced_media($content);
foreach (array_keys($referencedMedia) as $file) {
    if (!is_file(base_path('assets/img/' . $file))) {
        $add($errors, 'Afbeelding uit JSON ontbreekt in assets/img: ' . $file);
        continue;
    }

    $checkGitTracked('assets/img/' . $file, 'Afbeelding uit JSON: assets/img/' . $file);
}

$siteFavicon = admin_safe_media_filename((string) ($content['site']['favicon'] ?? ''));
if ($siteFavicon !== '') {
    if (!is_file(base_path('assets/img/' . $siteFavicon))) {
        $add($errors, 'Favicon ontbreekt in assets/img: ' . $siteFavicon);
    } else {
        $checkGitTracked('assets/img/' . $siteFavicon, 'Favicon: assets/img/' . $siteFavicon);
    }
}

$imageFiles = image_files();
$unusedImages = array_values(array_diff($imageFiles, array_keys($referencedMedia)));
if ($unusedImages !== []) {
    $add($warnings, count($unusedImages) . ' afbeeldingen in assets/img worden momenteel niet gebruikt: ' . implode(', ', array_slice($unusedImages, 0, 8)) . (count($unusedImages) > 8 ? ', ...' : ''));
}

if ($errors === []) {
    $add($ok, 'Go-live validatie afgerond zonder blokkerende fouten.');
}

$printGroup = static function (string $label, array $items): void {
    if ($items === []) {
        return;
    }

    echo $label . "\n";
    foreach ($items as $item) {
        echo ' - ' . $item . "\n";
    }
};

$printGroup('[OK]', $ok);
$printGroup('[WARN]', $warnings);
$printGroup('[ERROR]', $errors);

exit($errors === [] ? 0 : 1);

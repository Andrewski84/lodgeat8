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

if (PHP_VERSION_ID < 80000) {
    $add($errors, 'PHP 8.0 of nieuwer is vereist. Gevonden: ' . PHP_VERSION);
} elseif (PHP_VERSION_ID < 80100) {
    $add($warnings, 'PHP 8.1 of nieuwer is aanbevolen. Gevonden: ' . PHP_VERSION);
} else {
    $add($ok, 'PHP-versie OK: ' . PHP_VERSION);
}

foreach ([
    'index.php',
    '.htaccess',
    'README.md',
    'includes/bootstrap.php',
    'includes/content.php',
    'includes/admin.php',
    'views/layout.php',
    'views/pages',
    'views/partials',
    'assets/css/style.css',
    'assets/css/admin.css',
    'assets/js/app.js',
    'assets/js/admin.js',
    'beheer/index.php',
    'beheer/partials/form-helpers.php',
] as $requiredPath) {
    $checkExists($root . '/' . $requiredPath, $requiredPath);
}

foreach ([
    'storage/.htaccess',
    'includes/.htaccess',
    'views/.htaccess',
    'beheer/partials/.htaccess',
    'tools/.htaccess',
] as $protectedPath) {
    $checkExists($root . '/' . $protectedPath, $protectedPath . ' bescherming');
}

$checkWritableDir(storage_path(), 'storage/');
$checkWritableDir(base_path('assets/img'), 'assets/img/');

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

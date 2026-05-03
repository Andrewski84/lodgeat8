<?php
declare(strict_types=1);

// Keep the section router thin: each section has its own partial in /sections.
$sectionTemplate = null;

if ($section === 'algemeen') {
    $sectionTemplate = 'general.php';
} elseif (in_array($section, ['home', 'leuven', 'locatie', 'voorwaarden'], true)) {
    $sectionTemplate = 'page.php';
} elseif (in_array($section, ['kamer-1', 'kamer-2', 'kamer-3'], true)) {
    $sectionTemplate = 'room.php';
} elseif ($section === 'contact') {
    $sectionTemplate = 'contact.php';
} elseif ($section === 'links') {
    $sectionTemplate = 'links.php';
} elseif ($section === 'toegang') {
    $sectionTemplate = 'access.php';
}

if ($sectionTemplate !== null) {
    require __DIR__ . '/../sections/' . $sectionTemplate;
}

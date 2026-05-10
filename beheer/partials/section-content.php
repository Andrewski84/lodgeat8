<?php
declare(strict_types=1);

/*
 * Admin section template router.
 *
 * The sidebar section key is intentionally mapped to a small set of physical
 * templates. Public pages and rooms reuse generic editors, while contact,
 * links, general settings and access each get their own focused partial.
 */
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

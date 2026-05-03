<?php
declare(strict_types=1);

// Keep the section router thin: each section has its own partial in /sections.
$sectionTemplate = match (true) {
    $section === 'algemeen' => 'general.php',
    in_array($section, ['leuven', 'locatie', 'voorwaarden'], true) => 'page.php',
    in_array($section, ['kamer-1', 'kamer-2', 'kamer-3'], true) => 'room.php',
    $section === 'contact' => 'contact.php',
    $section === 'links' => 'links.php',
    $section === 'toegang' => 'access.php',
    default => null,
};

if ($sectionTemplate !== null) {
    require __DIR__ . '/../sections/' . $sectionTemplate;
}

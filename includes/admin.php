<?php
declare(strict_types=1);

// Admin logic is split into small modules to keep responsibilities clear:
// settings/session/auth/content helpers/media/content save routines.
foreach ([
    'settings.php',
    'session.php',
    'auth.php',
    'content-helpers.php',
    'media.php',
    'content-save.php',
] as $file) {
    require_once __DIR__ . '/admin/' . $file;
}

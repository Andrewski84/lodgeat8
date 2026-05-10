<?php
declare(strict_types=1);

/*
 * Admin module loader.
 *
 * The admin area is split by responsibility: settings and session/auth are
 * security-sensitive, content helpers normalize editable JSON, media handles
 * uploads/deletions, and content-save turns form posts into validated content
 * changes. Loading those modules here keeps beheer/index.php and the admin
 * controller focused on request flow instead of include bookkeeping.
 */
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

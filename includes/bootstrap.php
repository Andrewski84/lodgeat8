<?php
declare(strict_types=1);

// Load language configuration first (required by helpers.php)
require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/renderers.php';
require_once __DIR__ . '/contact.php';

$config = load_content();

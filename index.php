<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$currentLanguage = requested_language();
$route = resolve_page($config, requested_page_key());
$pageKey = $route['key'];
$page = localize_route_page($route['page'], $currentLanguage);
$contactResult = contact_empty_result();

if ($route['status'] !== 200) {
    http_response_code($route['status']);
}

if (($page['type'] ?? '') === 'contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $contactResult = handle_contact_submission($config, $_POST);
    } catch (Throwable $exception) {
        $contactResult = contact_empty_result();
        $contactResult['submitted'] = true;
        $contactResult['errors'][] = 'Je bericht kon niet worden verzonden. Probeer later opnieuw of mail ons rechtstreeks.';
    }
}

require __DIR__ . '/views/layout.php';

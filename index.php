<?php
declare(strict_types=1);

/*
 * Public front controller.
 *
 * Every visitor request enters here. The file keeps the request flow small:
 * bootstrap shared helpers and content, resolve the page key from the URL,
 * process the contact form only when the resolved page is the contact page,
 * and finally hand rendering to the shared layout.
 */
require __DIR__ . '/includes/bootstrap.php';

$currentLanguage = requested_language();
$route = resolve_page($config, requested_page_key());
$pageKey = $route['key'];
$page = localize_route_page($route['page'], $currentLanguage);
$contactResult = contact_empty_result();

if ($route['status'] !== 200) {
    http_response_code($route['status']);
}

if (($page['type'] ?? '') === 'contact' && ($page['contact_form_enabled'] ?? true) !== false) {
    contact_prepare_runtime();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $contactResult = handle_contact_submission($config, $_POST);
        } catch (Throwable $exception) {
            app_log_exception($exception, 'contact submission');
            $contactResult = contact_empty_result();
            $contactResult['submitted'] = true;
            $contactResult['errors'][] = 'Je bericht kon niet worden verzonden. Probeer later opnieuw of mail ons rechtstreeks.';
        }
    }

    $contactResult = contact_result_with_runtime($contactResult);
}

require __DIR__ . '/views/layout.php';

<?php
declare(strict_types=1);

function requested_page_key(): string
{
    // Routes are stored as simple content keys, so keep the incoming value
    // restricted to the same character set used by the JSON structure.
    if (!isset($_GET['p'])) {
        return 'home';
    }

    return preg_replace('/[^a-z0-9-]/', '', strtolower((string) $_GET['p'])) ?: 'home';
}

function resolve_page(array $config, string $pageKey): array
{
    // Rooms behave like pages but are stored separately so admin editing can
    // share gallery and booking logic across all rooms.
    if (isset($config['rooms'][$pageKey])) {
        return [
            'key' => $pageKey,
            'status' => 200,
            'page' => [
                'title' => $config['rooms'][$pageKey]['title'],
                'type' => 'room',
                'gallery' => $config['rooms'][$pageKey]['gallery'] ?? $pageKey,
                'room' => $config['rooms'][$pageKey],
            ],
        ];
    }

    if (isset($config['pages'][$pageKey])) {
        return [
            'key' => $pageKey,
            'status' => 200,
            'page' => $config['pages'][$pageKey],
        ];
    }

    return [
        'key' => 'home',
        'status' => 404,
        'page' => $config['pages']['home'],
    ];
}

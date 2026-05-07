<?php
/**
 * Language Configuration
 *
 * Centralized language definitions and utilities for multi-language support.
 * Provides constants for language codes, labels, and display names.
 *
 * Supported languages:
 * - nl: Dutch (Nederlands) - default
 * - fr: French (Français)
 * - en: English
 */

declare(strict_types=1);

/**
 * Get all supported languages with their display labels.
 *
 * @return array<string, string> Language code => Label mapping (e.g., ['nl' => 'Nederlands', 'fr' => 'Français', 'en' => 'English'])
 */
function get_supported_languages(): array
{
    return [
        'nl' => 'Nederlands',
        'fr' => 'Français',
        'en' => 'English',
    ];
}

/**
 * Get the language code from query parameter or default to Dutch.
 * Validates against supported languages list.
 *
 * @return string Validated language code (2 characters, lowercase)
 */
function get_requested_language(): string
{
    $language = strtolower((string) ($_GET['lang'] ?? 'nl'));
    return array_key_exists($language, get_supported_languages()) ? $language : 'nl';
}

/**
 * Get the current active language from global context or request.
 *
 * @return string Current active language code
 */
function get_current_language(): string
{
    return (string) ($GLOBALS['currentLanguage'] ?? get_requested_language());
}

/**
 * Get display name for a language code.
 * Used for language picker UI labels.
 *
 * @param string $code Language code (e.g., 'nl', 'fr', 'en')
 * @return string Display name (e.g., 'Nederlands', 'Français', 'English')
 */
function get_language_name(string $code): string
{
    $languages = get_supported_languages();
    return $languages[$code] ?? 'Unknown';
}

/**
 * Check if a language code is supported.
 *
 * @param string $code Language code to validate
 * @return bool True if language is supported
 */
function is_supported_language(string $code): bool
{
    return array_key_exists(strtolower($code), get_supported_languages());
}

/**
 * Get language name (full name) or abbreviation.
 * Used for mobile display where space is limited.
 *
 * @param string $code Language code (e.g., 'nl', 'fr', 'en')
 * @param bool $abbreviated If true, return 2-letter code; if false, return full name
 * @return string Language display value
 */
function get_language_display(string $code, bool $abbreviated = false): string
{
    if ($abbreviated) {
        return strtoupper($code);
    }
    return get_language_name($code);
}

/**
 * Get all languages with their abbreviations and full names.
 * Useful for creating language pickers with both formats.
 *
 * @return array<string, array{abbr: string, name: string}> Detailed language information
 */
function get_languages_detailed(): array
{
    $languages = get_supported_languages();
    $detailed = [];

    foreach ($languages as $code => $name) {
        $detailed[$code] = [
            'abbr' => strtoupper($code),
            'name' => $name,
        ];
    }

    return $detailed;
}

/**
 * Generate URL for current page with specified language.
 * Uses the same simple lang/page route format as the public navigation.
 *
 * @param string $page Page identifier (route name)
 * @param string|null $language Language code, or null for current language
 * @return string URL with language parameter
 */
function create_language_url(string $page, ?string $language = null): string
{
    if ($language === null) {
        $language = get_current_language();
    }

    if (!is_supported_language($language)) {
        $language = 'nl';
    }

    return 'index.php?lang=' . rawurlencode($language) . '&p=' . rawurlencode($page);
}

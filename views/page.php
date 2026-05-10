<?php
/*
 * Public page template dispatcher.
 *
 * Page data decides which template is used. Unknown or unsupported page types
 * fall back to generic.php so newly added content can still render safely while
 * a more specific template is being developed.
 */
$template = page_template_for((string) $page['type']);
$templatePath = __DIR__ . '/pages/' . $template . '.php';

if (is_file($templatePath)) {
    require $templatePath;
} else {
    require __DIR__ . '/pages/generic.php';
}

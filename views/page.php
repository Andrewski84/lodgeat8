<?php
$template = page_template_for((string) $page['type']);
$templatePath = __DIR__ . '/pages/' . $template . '.php';

if (is_file($templatePath)) {
    require $templatePath;
} else {
    require __DIR__ . '/pages/generic.php';
}

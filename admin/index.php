<?php
declare(strict_types=1);

/*
 * Legacy admin redirect.
 *
 * Older bookmarks may still point at /admin/. Keep this tiny redirect so those
 * URLs continue to land in the Dutch /beheer/ admin area without duplicating
 * any authentication or controller logic.
 */
header('Location: ../beheer/', true, 302);
exit;

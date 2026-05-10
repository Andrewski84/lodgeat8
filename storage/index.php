<?php
/*
 * Fallback denial for storage/.
 *
 * Apache .htaccess rules should block this directory before PHP runs. This file
 * is a secondary guard for hosts that ignore directory indexes differently.
 */
http_response_code(404);

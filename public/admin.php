<?php
/**
 * Admin redirect handler
 * Redirects /admin to /admin/
 */

// Redirect to admin directory with trailing slash
header('Location: /admin/', true, 301);
exit();
?>
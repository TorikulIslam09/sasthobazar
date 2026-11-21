<?php
/**
 * Fallback only: used if mod_rewrite is disabled. Normally .htaccess sends requests to public/.
 */
header('Location: public/', true, 302);
exit;

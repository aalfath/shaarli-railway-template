<?php
/* Health check for Railway: answers 200 only when the data volume is writable and
 * Shaarli's configuration (with its admin login) is in place. No login needed. */
header('Content-Type: text/plain');
header('Cache-Control: no-store');
$data = '/var/www/shaarli/data';
$problem = null;
if (!is_dir($data) || !is_writable($data)) {
    $problem = 'data directory is not writable';
} elseif (!is_readable($data . '/config.json.php')) {
    $problem = 'configuration missing';
} else {
    $raw = (string) file_get_contents($data . '/config.json.php');
    $conf = json_decode(trim(str_replace(['<?php /*', '*/ ?>'], '', $raw)), true);
    if (!is_array($conf) || empty($conf['credentials']['hash'])) {
        $problem = 'configuration has no admin login';
    }
}
if ($problem !== null) {
    http_response_code(503);
    echo "unhealthy: $problem\n";
    exit;
}
echo "ok\n";

<?php
/*
 * Seeds Shaarli's configuration before the web server starts, so the install page
 * (which hands the instance to whoever opens it first) is never reachable.
 *
 * - No config yet: create it the way Shaarli's own install page does, with the admin
 *   login from SHAARLI_USERNAME / SHAARLI_PASSWORD.
 * - Config exists: leave it alone, so settings changed in Tools > Configure survive
 *   every redeploy. Only if SHAARLI_USERNAME or SHAARLI_PASSWORD changed since they
 *   were last applied (tracked in data/.railway-admin) are the admin credentials
 *   reset to the new values. Changing the password in Shaarli itself still works.
 */
declare(strict_types=1);

use Shaarli\Config\ConfigManager;

chdir('/var/www/shaarli');
require 'vendor/autoload.php';
require_once 'application/Utils.php';

function out(string $m): void { fwrite(STDOUT, "[bootstrap] $m\n"); }
function fail(string $m): void { fwrite(STDERR, "[bootstrap] ERROR: $m\n"); exit(1); }

$login = trim((string) getenv('SHAARLI_USERNAME'));
$password = (string) getenv('SHAARLI_PASSWORD');
if ($login === '') {
    $login = 'admin';
}
if (strlen($password) < 8) {
    fail('SHAARLI_PASSWORD must be set and at least 8 characters long.');
}

$marker = 'data/.railway-admin';
$fingerprint = hash('sha256', "shaarli-railway\n" . $login . "\n" . $password);

@mkdir('data/sessions', 0700, true);

$conf = new ConfigManager();
$configFile = $conf->getConfigFileExt();

$setCredentials = function (ConfigManager $conf) use ($login, $password): void {
    $salt = sha1(uniqid('', true) . '_' . mt_rand());
    $conf->set('credentials.login', $login);
    $conf->set('credentials.salt', $salt);
    $conf->set('credentials.hash', sha1($password . $login . $salt));
};

if (!is_file($configFile)) {
    $setCredentials($conf);
    $conf->set('general.timezone', 'UTC');
    $conf->set('general.title', 'Shaarli');
    $conf->set('general.header_link', '/');
    $conf->set('translation.language', 'auto');
    $conf->set('updates.check_updates', true);
    $conf->set('api.enabled', true);
    $conf->set('api.secret', generate_api_secret($login, $conf->get('credentials.salt')));
    // Thumbnails are cached on the volume (see the Dockerfile).
    $conf->set('resource.thumbnails_cache', 'data/thumbnails');
    $conf->write(true);
    file_put_contents($marker, $fingerprint . "\n");
    out("created the configuration with admin login '$login'");
    exit(0);
}

$previous = is_file($marker) ? trim((string) file_get_contents($marker)) : '';
if ($previous === '') {
    // Configuration made elsewhere (for example restored from a backup): adopt it.
    file_put_contents($marker, $fingerprint . "\n");
    out('configuration found; keeping its admin login');
} elseif (!hash_equals($previous, $fingerprint)) {
    $setCredentials($conf);
    $conf->write(true);
    file_put_contents($marker, $fingerprint . "\n");
    out("SHAARLI_USERNAME or SHAARLI_PASSWORD changed: admin login reset to '$login'");
} else {
    out('configuration found; leaving it unchanged');
}

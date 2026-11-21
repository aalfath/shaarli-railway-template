<?php
/*
 * Runs before every Shaarli request (auto_prepend_file).
 *
 * Behind Railway's edge, REMOTE_ADDR is a rotating private proxy address and
 * X-Forwarded-For ends with a rotating CDN hop. Shaarli ties a login session to
 * REMOTE_ADDR + X-Forwarded-For and bans by REMOTE_ADDR, so without this every
 * request could log you out and the login ban would hit the proxy, not the visitor.
 * Railway's edge overwrites X-Real-IP with the real client address, so when the
 * request comes from Railway's proxy range (100.64.0.0/10) we use it as the client.
 */
if (PHP_SAPI === 'fpm-fcgi') {
    $peer = $_SERVER['REMOTE_ADDR'] ?? '';
    $fromEdge = false;
    if (filter_var($peer, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $n = ip2long($peer);
        $fromEdge = ($n & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000);
    }
    if ($fromEdge) {
        $real = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        if ($real !== '' && filter_var($real, FILTER_VALIDATE_IP)) {
            $_SERVER['REMOTE_ADDR'] = $real;
        }
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP']);
        $proto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        if ($proto === 'https') {
            $_SERVER['HTTPS'] = 'on';
            ini_set('session.cookie_secure', '1');
        }
    }
    unset($peer, $fromEdge, $n, $real, $proto);
}

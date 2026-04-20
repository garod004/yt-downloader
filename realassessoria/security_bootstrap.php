<?php
if (defined('APP_SECURITY_BOOTSTRAP_LOADED')) {
    return;
}
define('APP_SECURITY_BOOTSTRAP_LOADED', true);

if (!function_exists('sec_env')) {
    function sec_env($name, $default = '') {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
            return $_ENV[$name];
        }
        if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
            return $_SERVER[$name];
        }
        return $default;
    }
}

if (!function_exists('sec_is_https')) {
    function sec_is_https() {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        return false;
    }
}

if (!function_exists('sec_current_origin')) {
    function sec_current_origin() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return '';
        }
        $scheme = sec_is_https() ? 'https' : 'http';
        return $scheme . '://' . $host;
    }
}

if (!function_exists('sec_allowed_origins')) {
    function sec_allowed_origins() {
        $configured = trim((string) sec_env('CORS_ALLOWED_ORIGINS', ''));
        $origins = array();

        if ($configured !== '') {
            foreach (explode(',', $configured) as $origin) {
                $origin = trim($origin);
                if ($origin !== '') {
                    $origins[] = $origin;
                }
            }
        }

        $current = sec_current_origin();
        if ($current !== '') {
            $origins[] = $current;
        }

        // Facilita ambiente local de desenvolvimento sem abrir wildcard.
        $origins[] = 'http://localhost';
        $origins[] = 'http://127.0.0.1';
        $origins[] = 'http://localhost:8080';
        $origins[] = 'http://127.0.0.1:8080';

        return array_values(array_unique($origins));
    }
}

if (!function_exists('sec_apply_headers')) {
    function sec_apply_headers() {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-site');

        // CSP moderada para reduzir risco sem quebrar o sistema atual (usa muito inline script/style).
        $csp = "default-src 'self' https: data: blob:; "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; "
            . "style-src 'self' 'unsafe-inline' https:; "
            . "img-src 'self' data: blob: https:; "
            . "font-src 'self' data: https:; "
            . "connect-src 'self' https:; "
            . "frame-ancestors 'self'; "
            . "base-uri 'self'; "
            . "form-action 'self';";
        header('Content-Security-Policy: ' . $csp);

        if (sec_is_https()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

if (!function_exists('sec_apply_cors')) {
    function sec_apply_cors() {
        if (headers_sent()) {
            return;
        }

        $origin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string) $_SERVER['HTTP_ORIGIN']) : '';
        if ($origin === '') {
            return;
        }

        $allowed = sec_allowed_origins();
        $isAllowed = in_array($origin, $allowed, true);

        if (!$isAllowed) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('success' => false, 'message' => 'Origem nao permitida.'));
            exit();
        }

        header('Vary: Origin');
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
        header('Access-Control-Max-Age: 600');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit();
        }
    }
}

if (!function_exists('sec_harden_session_ini')) {
    function sec_harden_session_ini() {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_samesite', 'Lax');
        if (sec_is_https()) {
            @ini_set('session.cookie_secure', '1');
        }
    }
}

sec_harden_session_ini();
sec_apply_cors();
sec_apply_headers();

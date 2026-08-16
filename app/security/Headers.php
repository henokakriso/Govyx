<?php

declare(strict_types=1);

namespace Govyx\Security;

use Govyx\Core\App;

/**
 * HTTP security hardening.
 * Applied to every response before any body output.
 */
final class Headers
{
    public static function apply(): void
    {
        if (headers_sent()) {
            return;
        }

        // Clickjacking protection
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');

        // Referrer: never leak tokens/URLs to third parties
        header('Referrer-Policy: no-referrer');

        // Permissions policy: disable unnecessary browser features
        header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), fullscreen=()");

        // CSP: scripts self-hosted only; styles self-hosted; no inline scripts.
        // style-src 'unsafe-inline' required for inline style="" attributes used in dynamic views.
        header(
            "Content-Security-Policy: default-src 'self'; " .
            "script-src 'self'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data:; " .
            "font-src 'self'; " .
            "connect-src 'self'; " .
            "frame-ancestors 'none'; " .
            "base-uri 'self'; " .
            "form-action 'self'; " .
            "object-src 'none'"
        );

        // Force HTTPS in production (terminate before this app in reverse proxy setups).
        if (App::isSecure() && empty($_SERVER['HTTPS'])) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Cache control for sensitive/API responses (web shell sets its own).
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
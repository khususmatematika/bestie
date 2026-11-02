<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan standar: anti-clickjacking, anti-MIME-sniffing, kebijakan referrer,
 * pembatasan fitur peramban, HSTS (hanya HTTPS) dan Content-Security-Policy ber-nonce.
 * CSP dilewati saat server dev Vite aktif (public/hot) karena HMR memakai origin lain.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $dev = is_file(public_path('hot'));
        $nonce = null;

        if (! $dev) {
            $nonce = base64_encode(random_bytes(16));
            Vite::useCspNonce($nonce);
            View::share('cspNonce', $nonce);
        }

        $response = $next($request);

        $h = $response->headers;
        $h->set('X-Frame-Options', 'DENY');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($nonce !== null) {
            $h->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' data: https://fonts.gstatic.com",
                "img-src 'self' data: blob:",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]));
        }

        return $response;
    }
}

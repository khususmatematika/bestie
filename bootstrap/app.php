<?php

declare(strict_types=1);

use App\Exceptions\WorkflowException;
use App\Http\Middleware\EnsureActiveRole;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ActiveRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\ImpersonationTimeout::class,
            SecurityHeaders::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'active.role' => EnsureActiveRole::class,
            'password.changed' => EnsurePasswordChanged::class,
            'not.impersonating' => \App\Http\Middleware\NotImpersonating::class,
        ]);

        // Tamu diarahkan ke login; pengguna yang sudah login diarahkan ke beranda perannya.
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo(
            fn (Request $request) => route(ActiveRole::homeRoute(ActiveRole::current($request))),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pelanggaran aturan alur (mis. paket terkunci, catatan terlalu pendek) tampil sebagai pesan galat ramah.
        $exceptions->render(function (WorkflowException $e, Request $request) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson() && ! $request->header('X-Inertia'),
        );
    })->create();

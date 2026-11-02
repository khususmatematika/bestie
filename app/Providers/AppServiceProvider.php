<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Cegah N+1 query saat pengembangan (rules.md §4.8).
        Model::preventLazyLoading(! $this->app->isProduction());

        Date::use(\Carbon\CarbonImmutable::class);

        // Halaman galat yang ramah (Bahasa Indonesia) untuk kode HTTP umum.
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            $status = $response->statusCode();

            $showPage = in_array($status, [403, 404, 419, 429, 503], true)
                || ($status === 500 && ! config('app.debug'));

            if (! $showPage) {
                return null;
            }

            return $response->render('Error', ['status' => $status]);
        });
    }
}

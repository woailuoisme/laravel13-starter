<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', static function ($user = null) {
            if (app()->isLocal()) {
                return true;
            }

            return in_array(
                optional($user)->email,
                [
                    // 'admin@example.com'
                ],
                strict: true,
            );
        });
    }
}

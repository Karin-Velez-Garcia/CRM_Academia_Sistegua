<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // El rol Administrador tiene todos los permisos, incluidos los que se agreguen después.
        Gate::before(fn (User $user) => $user->hasRole(User::ROL_ADMINISTRADOR) ? true : null);
    }
}

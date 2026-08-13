<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Limite por combinação email+IP (não só IP): impede um atacante de
        // testar senhas contra um e-mail específico, sem derrubar outros
        // usuários que dividem o mesmo IP (ex.: NAT de empresa).
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // LogAccessListener NÃO é registrado aqui de propósito: o Laravel
        // já descobre automaticamente o listener pra FileUploaded,
        // FileDownloaded e ShareLinkAccessed (todos implementam
        // App\Contracts\AccessLoggable, que é o tipo declarado no
        // parâmetro de LogAccessListener::handle()) via reflection —
        // confirmável com "php artisan event:list". Registrar de novo
        // aqui faria o listener disparar duas vezes por evento.
    }
}

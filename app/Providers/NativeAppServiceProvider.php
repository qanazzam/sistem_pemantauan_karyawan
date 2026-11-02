<?php

namespace App\Providers;

use Native\Desktop\Facades\Window;
use Native\Desktop\Contracts\ProvidesPhpIni;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        Window::open()
            ->title(config('app.name', 'SIMPEG PU - Dinas PU Prov. Sulsel'))
            ->width(1280)
            ->height(850)
            ->minWidth(1024)
            ->minHeight(700)
            ->showDevTools(false)
            ->rememberState();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'opcache.enable' => '0',
            'opcache.enable_cli' => '0',
            'memory_limit' => '512M',
        ];
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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
        Blade::directive('rupiah', function ($expression) {
            return "<?php echo 'Rp. ' . number_format($expression,0,',','.'); ?>";
        });

        if (stripos((string) config('app.domisili'), 'semarang') !== false) {
            config(['app.domisili' => 'Sidoarjo']);
        }
        if (stripos((string) config('app.alamat'), 'semarang') !== false) {
            config(['app.alamat' => 'Kota Sidoarjo, Prov. Jawa Timur']);
        }
    }
}

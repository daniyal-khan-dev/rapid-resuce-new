<?php

namespace App\Providers;

use App\Models\Admin\Branch;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        View::composer(
            ['user.layouts.user_header', 'user.layouts.user_footer'],
            function ($view) {
                $data = $view->getData();
                if (empty($data['contactInfo'])) {
                    $view->with('contactInfo', Branch::find(1));
                }
            }
        );
    }
}

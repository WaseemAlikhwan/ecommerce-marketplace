<?php

use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:seed', function () {
    if ($this->laravel->environment('production')) {
        $this->error('Refusing to seed demo data in production (APP_ENV=production).');

        return 1;
    }

    $this->info('Seeding demo marketplace (local/staging only)…');
    $this->call('db:seed', ['--class' => DemoMarketplaceSeeder::class]);

    return 0;
})->purpose('Seed local/staging demo marketplace data (not for production)');

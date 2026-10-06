<?php

use App\Services\InventoryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inventory:expire', function (InventoryService $s) {
    $this->info('Released '.$s->expire().' expired reservations.');
})->purpose('Release expired pending reservations');
Schedule::command('inventory:expire')->everyMinute()->withoutOverlapping();

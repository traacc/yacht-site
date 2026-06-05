<?php

namespace App\Providers;

use App\Models\Team;
use App\Models\TeamMember;
use App\Observers\TeamMemberObserver;
use App\Policies\TeamPolicy;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

use Filament\Tables\Table;

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
        Gate::policy(Team::class, TeamPolicy::class);

        TeamMember::observe(TeamMemberObserver::class);

        Notification::configureUsing(function (Notification $notification): void {
            $notification->duration(6000); // 2000 мс = 2 секунды
        });

        Table::configureUsing(function (Table $table): void {
        $table
            // 1. Устанавливаем дефолтное количество записей на страницу
            ->defaultPaginationPageOption(50)
            
            // 2. Настраиваем доступные варианты в выпадающем списке (опционально)
            ->paginated([10, 25, 50, 100, 'all']);
        });
    }
}

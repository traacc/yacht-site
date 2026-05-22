<?php
namespace App\Filament\Widgets;

use App\Models\User;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingBirthdaysWidget extends BaseWidget
{
    protected static ?string $heading = '🎂 Именинники ближайших 7 дней';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::whereNotNull('birthday')
                    ->whereRaw("DATE_FORMAT(birthday, '%m-%d') BETWEEN
                        DATE_FORMAT(NOW(), '%m-%d') AND
                        DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 7 DAY), '%m-%d')"
                    )
                    ->orderByUpcomingBirthday()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Имя'),
                Tables\Columns\TextColumn::make('next_birthday')
                    ->label('Дата ДР')
                    ->getStateUsing(fn (User $r) => $r->next_birthday?->format('d.m') ?? '—'),
                Tables\Columns\TextColumn::make('days_until_birthday')
                    ->label('Осталось дней')
                    ->getStateUsing(fn (User $r) => $r->days_until_birthday === 0
                        ? '🎂 Сегодня!'
                        : $r->days_until_birthday . ' дн.'
                    ),
            ]);
    }
}
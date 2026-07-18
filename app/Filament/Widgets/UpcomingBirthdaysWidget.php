<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingBirthdaysWidget extends BaseWidget
{
    protected static ?string $heading = 'Ближайшие дни рождения';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        // Данные по всем пользователям ассоциации — не для админа-разработчика.
        return ! auth()->user()?->isDeveloperAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::whereNotNull('birth_date')
                    ->whereRaw("DATE_FORMAT(birth_date, '%m-%d') BETWEEN
                        DATE_FORMAT(NOW(), '%m-%d') AND
                        DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 14 DAY), '%m-%d')"
                    )
                    ->orderByUpcomingBirthday()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Имя')->url(fn (User $record): string => UserResource::getUrl('index', [
                    'tableAction' => 'edit',
                    'tableActionRecord' => $record->id,
                ])),
                Tables\Columns\TextColumn::make('next_birthday')
                    ->label('Дата рождения')
                    ->getStateUsing(fn (User $r) => $r->nextBirthday?->locale('ru')->translatedFormat('d F') ?? '—'),
                Tables\Columns\TextColumn::make('age')
                    ->label('Исполнится')
                    ->getStateUsing(fn (User $r) => $r->birth_date && $r->nextBirthday
                        ? ($r->nextBirthday->year - $r->birth_date->year)
                        : '—'),
                Tables\Columns\TextColumn::make('days_until_birthday')
                    ->label('Через')
                    ->getStateUsing(fn (User $r) => $r->daysUntilBirthday === 0
                        ? 'Сегодня!'
                        : $r->daysUntilBirthday.' дн.'
                    ),
            ])->stackedOnMobile()->emptyStateHeading('В ближайшие время нет ни у кого дней рождения');
    }
}

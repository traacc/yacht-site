<?php
namespace App\Filament\Widgets;

use App\Models\User;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingBirthdaysWidget extends BaseWidget
{
    protected static ?string $heading = 'Ближайшие дни рождения';
    protected int|string|array $columnSpan = 'full';

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
                Tables\Columns\TextColumn::make('name')->label('Имя')->url(fn (User $record): string => \App\Filament\Resources\Users\UserResource::getUrl('index', [
                    'tableAction'       => 'edit',
                    'tableActionRecord' => $record->id,
                ])),
                Tables\Columns\TextColumn::make('next_birthday')
                    ->label('Дата рождения')
                    ->getStateUsing(fn (User $r) => $r->nextBirthday?->format('d.m.Y') ?? '—'),
                Tables\Columns\TextColumn::make('days_until_birthday')
                    ->label('Через')
                    ->getStateUsing(fn (User $r) => $r->daysUntilBirthday === 0
                        ? 'Сегодня!'
                        : $r->daysUntilBirthday . ' дн.'
                    ),
            ])->stackedOnMobile()->emptyStateHeading('В ближайшие время нет ни у кого дней рождения');
    }
}
<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Adverts\Pages;

use App\Actions\Advert\SubmitAdvertAction;
use App\Enums\AdvertStatus;
use App\Filament\User\Resources\Adverts\AdvertResource;
use App\Models\Advert;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageAdverts extends ManageRecords
{
    protected static string $resource = AdvertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Разместить объявление')
                ->modalHeading('Новое объявление')
                ->using(function (array $data, string $model): Advert {
                    // Автора и статус проставляем здесь, а не в форме: из браузера
                    // они приходить не должны.
                    $data['user_id'] = auth()->id();
                    $data['status'] = AdvertStatus::Pending;

                    /** @var Advert $advert */
                    $advert = $model::create($data);

                    // Фотографии Filament прикрепит после создания записи,
                    // уведомление модераторам от них не зависит.
                    app(SubmitAdvertAction::class)->handle($advert);

                    return $advert;
                })
                ->createAnother(false)
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Объявление отправлено на модерацию')
                        ->body('После проверки модератором оно появится на сайте — вы получите уведомление.'),
                ),
        ];
    }
}

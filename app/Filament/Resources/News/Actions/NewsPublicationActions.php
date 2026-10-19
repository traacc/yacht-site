<?php

declare(strict_types=1);

namespace App\Filament\Resources\News\Actions;

use App\Actions\News\RepublishNewsAction;
use App\Actions\News\UnpublishNewsAction;
use App\Models\News;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Кнопки «Снять с публикации» / «Вернуть на сайт».
 *
 * Общие для раздела «Новости» и опубликованных AI-кандидатов: там запись
 * таблицы — кандидат, поэтому новость достаётся через $resolveNews.
 */
final class NewsPublicationActions
{
    /**
     * @param  Closure(Model): ?News  $resolveNews
     */
    public static function unpublish(Closure $resolveNews): Action
    {
        return Action::make('unpublishNews')
            ->label('Снять с публикации')
            ->icon('heroicon-o-eye-slash')
            ->color('warning')
            ->visible(function (Model $record) use ($resolveNews): bool {
                $news = $resolveNews($record);

                return $news !== null && ! $news->trashed() && ! $news->isUnpublished();
            })
            ->requiresConfirmation()
            ->modalHeading('Снять новость с публикации?')
            ->modalDescription(function (Model $record) use ($resolveNews): string {
                $description = 'Новость пропадёт с сайта, но останется в админке — её можно будет вернуть.';
                $news = $resolveNews($record);

                if ($news?->published_to_tg || $news?->published_to_vk) {
                    $description .= ' Уже отправленные посты в Telegram и VK не удаляются: ссылка из них перестанет открываться.';
                }

                return $description;
            })
            ->modalSubmitActionLabel('Снять')
            ->action(function (Model $record) use ($resolveNews): void {
                try {
                    app(UnpublishNewsAction::class)->handle($resolveNews($record));
                } catch (DomainException $exception) {
                    Notification::make()->warning()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Новость снята с публикации')->send();
            });
    }

    /**
     * @param  Closure(Model): ?News  $resolveNews
     */
    public static function republish(Closure $resolveNews): Action
    {
        return Action::make('republishNews')
            ->label('Вернуть на сайт')
            ->icon('heroicon-o-eye')
            ->color('success')
            ->visible(function (Model $record) use ($resolveNews): bool {
                $news = $resolveNews($record);

                return $news !== null && ! $news->trashed() && $news->isUnpublished();
            })
            ->requiresConfirmation()
            ->modalHeading('Вернуть новость на сайт?')
            ->modalDescription('Новость снова появится в ленте с прежней датой публикации.')
            ->modalSubmitActionLabel('Вернуть')
            ->action(function (Model $record) use ($resolveNews): void {
                try {
                    app(RepublishNewsAction::class)->handle($resolveNews($record));
                } catch (DomainException $exception) {
                    Notification::make()->warning()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Новость возвращена на сайт')->send();
            });
    }
}

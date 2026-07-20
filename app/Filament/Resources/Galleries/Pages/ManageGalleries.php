<?php

namespace App\Filament\Resources\Galleries\Pages;

use App\Filament\Resources\Galleries\GalleryResource;
use App\Models\Gallery;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageGalleries extends ManageRecords
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ★ ИЗМЕНЕНО: вместо обычного CreateAction (который сохраняет всё по кнопке)
            //   сразу создаём черновик галереи и открываем модалку редактирования.
            //   Так запись уже существует в БД, и загруженные фотографии сохраняются
            //   мгновенно (см. ->live()/afterStateUpdated в GalleryResource::form()).
            //   Брошенные безымянные черновики удаляются автоматически — см.
            //   Gallery::prunable() + плановый `model:prune` (routes/console.php).
            Action::make('create')
                ->label('Новая галерея')
                ->icon(Heroicon::Plus)
                ->action(function (): void {
                    $gallery = Gallery::create([
                        'name' => '',     // пустое имя = черновик; обязательно к заполнению в форме
                        'is_published' => false,  // черновик скрыт на публичной части до сохранения
                    ]);

                    // ★ ВАЖНО: именно replaceMountedAction, а не mountAction.
                    //   mountAction добавил бы 'edit' ПОВЕРХ выполняющегося 'create',
                    //   а Filament резолвит вложенный экшен как модальный экшен родителя
                    //   ($parentAction->getModalAction('edit')) — его нет, экшен молча
                    //   не резолвится и модалка не открывается.
                    //   replaceMountedAction очищает стек, и 'edit' резолвится как
                    //   табличный record-экшен (context: table + recordKey).
                    $this->replaceMountedAction('edit', [], [
                        'table' => true,
                        'recordKey' => $gallery->getKey(),
                    ]);
                }),
        ];
    }
}

<?php

namespace App\Providers;

use App\Contracts\AiNewsProvider;
use App\Models\News;
use App\Models\PaymentRegistry;
use App\Models\RegattaEntry;
use App\Models\RegattaResultItem;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\UserQuestion;
use App\Observers\NewsObserver;
use App\Observers\PaymentRegistryLogObserver;
use App\Observers\PaymentRegistryObserver;
use App\Observers\RegattaEntryFeeObserver;
use App\Observers\RegattaEntryPaymentLinksObserver;
use App\Observers\RegattaEntryResultObserver;
use App\Observers\RegattaResultItemObserver;
use App\Observers\TeamMemberObserver;
use App\Observers\UserQuestionObserver;
use App\Policies\TeamPolicy;
use App\Services\Ai\OpenAiNewsProvider;
use App\Services\ImageConverter;
use App\Services\Notifications\NotificationPreferences;
use App\Services\PaymentRegistryLogger;
use App\Services\ServiceContent;
use Filament\Actions\Action;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiNewsProvider::class, OpenAiNewsProvider::class);

        // Синглтон обязателен: withoutAutoLog() глушит запись через состояние экземпляра.
        $this->app->singleton(PaymentRegistryLogger::class);

        // Синглтон ради мемоизации: via() дёргает резолвер на каждого получателя рассылки.
        $this->app->singleton(NotificationPreferences::class);

        // Синглтон ради мемоизации: меню и карточки спрашивают название каждого
        // подраздела «Услуг», а группа настроек читается одним запросом.
        $this->app->singleton(ServiceContent::class);

        // Блок «Видео» в RichEditor отдаёт <iframe>, а санитайзер Filament
        // (Str::sanitizeHtml → HtmlSanitizerConfig) вырезает его целиком: iframe не входит
        // в allowSafeElements(). Разрешаем с минимальным набором атрибутов; адрес плеера
        // строит VideoEmbed по списку известных платформ, произвольный src туда не попадёт.
        //
        // class/style перечислены явно: Filament раздаёт их через allowAttribute('*'),
        // а '*' раскрывается по элементам, разрешённым на момент вызова — iframe в тот
        // список ещё не входил.
        $this->app->extend(
            HtmlSanitizerConfig::class,
            fn (HtmlSanitizerConfig $config): HtmlSanitizerConfig => $config->allowElement('iframe', [
                'src',
                'title',
                'class',
                'style',
                'width',
                'height',
                'loading',
                'allow',
                'allowfullscreen',
                'frameborder',
                'referrerpolicy',
            ]),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Team::class, TeamPolicy::class);

        TeamMember::observe(TeamMemberObserver::class);
        News::observe(NewsObserver::class);
        RegattaEntry::observe(RegattaEntryFeeObserver::class);
        RegattaEntry::observe(RegattaEntryPaymentLinksObserver::class);
        RegattaEntry::observe(RegattaEntryResultObserver::class);
        RegattaResultItem::observe(RegattaResultItemObserver::class);
        // Порядок важен: PaymentRegistryObserver::saving() заполняет
        // денормализованные связи, и только после этого лог-обсервер видит их
        // как изменённые (updated_by + запись в журнал).
        PaymentRegistry::observe(PaymentRegistryObserver::class);
        PaymentRegistry::observe(PaymentRegistryLogObserver::class);
        UserQuestion::observe(UserQuestionObserver::class);

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

        Action::configureUsing(function (Action $action) {
            $action->closeModalByClickingAway(false);
            $action->closeModalByEscaping(false);
        });

        // HEIC/HEIF нельзя хранить как оригинал в Spatie Media Library: Imagick не умеет
        // кодировать heic, а медиатека именует temp-файл конверсии по расширению оригинала,
        // из-за чего webp/avif выходят пустыми. Поэтому heic-загрузки нормализуем в JPEG
        // ДО сохранения — дальше с JPEG-оригинала штатно генерируются webp/avif (<picture>).
        // Переопределяет плагинный saveUploadedFileUsing (см. vendor SpatieMediaLibraryFileUpload).
        SpatieMediaLibraryFileUpload::configureUsing(function (SpatieMediaLibraryFileUpload $component): void {
            $component->saveUploadedFileUsing(static function (
                SpatieMediaLibraryFileUpload $component,
                TemporaryUploadedFile $file,
                ?Model $record
            ): ?string {
                if (! method_exists($record, 'addMediaFromString')) {
                    return $file;
                }

                try {
                    if (! $file->exists()) {
                        return null;
                    }
                } catch (UnableToCheckFileExistence) {
                    return null;
                }

                $bytes = $file->get();
                $filename = $component->getUploadedFileNameForStorage($file);
                $mime = $file->getMimeType();

                // HEIC/HEIF → JPEG до сохранения оригинала.
                $extension = strtolower(pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
                if (in_array($extension, ['heic', 'heif'], true) || in_array($mime, ['image/heic', 'image/heif'], true)) {
                    $jpeg = app(ImageConverter::class)->heicBytesToJpeg($bytes);
                    if ($jpeg !== null) {
                        $bytes = $jpeg;
                        $filename = preg_replace('/\.[^.]+$/', '.jpg', $filename);
                        $mime = 'image/jpeg';
                    }
                }

                /** @var FileAdder $mediaAdder */
                $mediaAdder = $record->addMediaFromString($bytes);

                $media = $mediaAdder
                    ->addCustomHeaders([...['ContentType' => $mime], ...$component->getCustomHeaders()])
                    ->usingFileName($filename)
                    ->usingName($component->getMediaName($file) ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    ->storingConversionsOnDisk($component->getConversionsDisk() ?? '')
                    ->withCustomProperties($component->getCustomProperties($file))
                    ->withManipulations($component->getManipulations())
                    ->withResponsiveImagesIf($component->hasResponsiveImages())
                    ->withProperties($component->getProperties())
                    ->toMediaCollection($component->getCollection() ?? 'default', $component->getDiskName());

                return $media->getAttributeValue('uuid');
            });
        });
    }
}

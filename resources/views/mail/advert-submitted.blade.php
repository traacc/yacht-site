<x-mail::message>
# Новое объявление на модерацию

| Поле | Значение |
|------|----------|
| **Раздел** | {{ $advert->type->label() }} |
@if($advert->kindLabel())
| **Вид** | {{ $advert->kindLabel() }} |
@endif
| **Заголовок** | {{ $advert->title }} |
| **Автор** | {{ $advert->author?->name ?? 'Неизвестен' }} |
@if($advert->category)
| **Категория** | {{ $advert->category->title }} |
@endif
@if($advert->position)
| **Позиция** | {{ $advert->position->label() }} |
@endif
@if($advert->sport_category)
| **Разряд** | {{ $advert->sport_category->getLabel() }} |
@endif
@if($advert->yachtLabel())
| **Яхта** | {{ $advert->yachtLabel() }} |
@endif
@if($advert->regattas->isNotEmpty())
| **Регаты** | {{ $advert->regattas->pluck('name')->implode(', ') }} |
@endif
@if($advert->datesLabel())
| **Когда** | {{ $advert->datesLabel() }} |
@endif
| **Цена** | {{ $advert->priceLabel() }} |
@if($advert->depositLabel())
| **Залог** | {{ $advert->depositLabel() }} |
@endif
@if($advert->city)
| **Город** | {{ $advert->city }} |
@endif
| **Подано** | {{ $advert->created_at->format('d.m.Y H:i') }} |

**Описание:**

{{ \Illuminate\Support\Str::limit($advert->description, 500) }}

<x-mail::button :url="$moderationUrl">
Открыть модерацию
</x-mail::button>
</x-mail::message>

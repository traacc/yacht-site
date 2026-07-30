<x-mail::message>
# Новое объявление на модерацию

| Поле | Значение |
|------|----------|
| **Раздел** | {{ $advert->type->label() }} |
| **Заголовок** | {{ $advert->title }} |
| **Автор** | {{ $advert->author?->name ?? 'Неизвестен' }} |
@if($advert->category)
| **Категория** | {{ $advert->category->title }} |
@endif
@if($advert->yacht)
| **Яхта** | {{ $advert->yacht->name }} |
@endif
| **Цена** | {{ $advert->priceLabel() }} |
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

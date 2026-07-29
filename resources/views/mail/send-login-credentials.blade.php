<x-mail::message>
# Здравствуйте, {{ $user->name }}!

Вам были выданы данные для входа на сайт **{{ config('mail.brand.name') }}**.

| Поле | Значение |
|------|----------|
| **Email** | {{ $email }} |
| **Пароль** | {{ $password }} |

<x-mail::button :url="route('home')">
Перейти на сайт
</x-mail::button>

Спасибо, что вы с нами!

</x-mail::message>

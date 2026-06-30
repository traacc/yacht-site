<x-mail::message>
# Зарегистрирован новый пользователь

На сайте зарегистрировался новый пользователь.

| Поле | Значение |
|------|----------|
| **ФИО** | {{ $user->name }} |
| **E-mail** | {{ $user->email }} |
@if($user->phone)
| **Телефон** | {{ $user->phone }} |
@endif
| **Дата регистрации** | {{ $user->created_at->format('d.m.Y H:i') }} |

</x-mail::message>

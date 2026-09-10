<x-mail::message>
# Ответ на запрос восстановления пароля

@if($request->requesterName())
Здравствуйте, {{ $request->requesterName() }}!
@else
Здравствуйте!
@endif

Вы оставляли запрос на восстановление пароля {{ $request->created_at->format('d.m.Y H:i') }}. Ответ администрации:

{!! nl2br(e($request->answer)) !!}

</x-mail::message>

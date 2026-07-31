<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ServiceSubject;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Model;

/**
 * Объект заявки раздела «Услуги» по id, пришедшему с публичной формы.
 *
 * Класс модели берёт ServiceType::subjectModel(), а не запрос: morph-класс из
 * формы позволил бы привязать заявку к произвольной модели. Из запроса
 * приходит только id, и объект обязан сам подтвердить, что принимает заявки
 * (прошедший поход или поход без мест их не принимает).
 */
final class ServiceSubjectResolver
{
    /**
     * @param  string|null  $id  id объекта из формы; null — свободная заявка с лендинга
     */
    public function resolve(ServiceType $type, ?string $id): ?Model
    {
        if ($id === null || trim($id) === '') {
            return null;
        }

        $class = $type->subjectModel();

        // Подраздел не работает с объектами, а форма прислала id — это не
        // «не нашли», а некорректный запрос.
        abort_if($class === null, 422, 'Этот подраздел не принимает заявки по объекту.');

        $subject = $class::query()->whereKey($id)->first();

        abort_unless(
            $subject instanceof ServiceSubject && $subject->acceptsServiceRequests(),
            404,
        );

        return $subject;
    }
}

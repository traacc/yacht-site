<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Объект, к которому можно привязать заявку раздела «Услуги».
 *
 * Заявка приходит с открытой формы, где пользователь передаёт только id, —
 * поэтому именно модель решает, принимает ли она сейчас заявки
 * (@see \App\Services\ServiceSubjectResolver). Класс модели берётся из
 * ServiceType::subjectModel(), а не из запроса.
 *
 * Реализуют Tour, ForeignRegatta и GiftCertificate.
 */
interface ServiceSubject
{
    /**
     * Можно ли сейчас оставить заявку на этот объект.
     *
     * Одновременно правило витрины (у прошедшего похода нет кнопки) и защита
     * от подделки id в запросе.
     */
    public function acceptsServiceRequests(): bool;

    /** Подпись объекта в письме, колокольчике и админке. */
    public function subjectLabel(): string;

    /** Публичный URL объекта; null — страницы нет. */
    public function subjectUrl(): ?string;
}

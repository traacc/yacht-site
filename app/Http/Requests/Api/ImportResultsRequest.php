<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Валидация JSON-запроса импорта результатов от внешней программы.
 *
 * Форма запроса приводится контроллером к канонической структуре
 * ApplyRegattaResultsAction (races + crews). Здесь — только проверка и типы.
 */
class ImportResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Аутентификация выполнена middleware api.token.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'result_type' => ['sometimes', 'in:preliminary,final'],
            'create_missing' => ['sometimes', 'boolean'],
            'replace' => ['sometimes', 'boolean'],

            'races' => ['present', 'array'],
            'races.*.name' => ['required', 'string', 'max:255'],
            'races.*.at' => ['nullable', 'date'],

            'crews' => ['required', 'array', 'min:1'],
            'crews.*.sail_number' => ['required', 'string', 'max:255'],
            'crews.*.yacht_name' => ['nullable', 'string', 'max:255'],
            'crews.*.type' => ['nullable', 'string', 'max:255'],
            'crews.*.city' => ['nullable', 'string', 'max:255'],
            'crews.*.team' => ['nullable', 'string', 'max:255'],
            'crews.*.final_position' => ['nullable', 'string', 'max:32'],
            'crews.*.total_points' => ['nullable', 'string', 'max:32'],
            'crews.*.races' => ['present', 'array'],
            'crews.*.races.*.position' => ['nullable', 'string', 'max:32'],
            'crews.*.races.*.points' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Ошибка валидации запроса.',
                'errors' => $validator->errors(),
            ], 422),
        );
    }
}

<?php

namespace App\Http\Requests;

use App\Exceptions\InvalidYandexMapsUrlException;
use App\Services\Yandex\YandexMapsUrlValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreYandexOrganizationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'yandex_url' => ['required', 'string', 'max:4096'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('yandex_url')) {
                    return;
                }

                try {
                    app(YandexMapsUrlValidator::class)->validate($this->string('yandex_url')->toString());
                } catch (InvalidYandexMapsUrlException $exception) {
                    $validator->errors()->add('yandex_url', $exception->getMessage());
                }
            },
        ];
    }
}

<?php

namespace JoeDixon\Translation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use JoeDixon\Translation\Rules\LanguageNotExists;

/**
 * @property $name
 * @property $locale
 */
class LanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string',
            'locale' => ['required', new LanguageNotExists],
        ];
    }
}

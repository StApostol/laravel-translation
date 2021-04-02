<?php

namespace JoeDixon\Translation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property $key
 * @property $value
 */
class TranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => 'required',
            'value' => 'required',
        ];
    }
}

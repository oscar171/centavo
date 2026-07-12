<?php

namespace App\Http\Requests;

use App\Models\Statement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReprocessStatementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $statement = $this->route('statement');

        return $statement instanceof Statement
            && $this->user()->can('view', $statement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Selecciona un archivo PDF.',
            'file.mimetypes' => 'El archivo debe ser un PDF.',
            'file.max' => 'El PDF no puede superar los 20 MB.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');
        return $course instanceof Course && $this->user()?->role === 'lecturer'
            && $course->lecturer_id === $this->user()->id;
    }

    public function rules(): array
    {
        return ['document' => [
            'bail', 'required', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx',
            'max:'.config('documents.max_kb'),
        ]];
    }

    public function messages(): array
    {
        return [
            'document.mimes' => 'Chỉ chấp nhận tài liệu PDF, DOC hoặc DOCX.',
            'document.extensions' => 'Tên tệp phải có đuôi PDF, DOC hoặc DOCX.',
            'document.max' => 'Tài liệu vượt quá giới hạn dung lượng cho phép.',
        ];
    }
}

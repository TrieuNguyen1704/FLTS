<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');
        return $this->user()?->role === 'lecturer'
            && (!$course instanceof Course || $course->lecturer_id === $this->user()->id);
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'code'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        $course = $this->route('course');
        $unique = Rule::unique('courses', 'code');
        if ($course instanceof Course) {
            $unique->ignore($course->id);
        }

        return [
            'name' => $course ? ['sometimes', 'required', 'string', 'max:160'] : ['required', 'string', 'max:160'],
            'code' => $course ? ['sometimes', 'required', 'string', 'max:50', $unique] : ['required', 'string', 'max:50', $unique],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['code.unique' => 'Mã môn học đã tồn tại. Vui lòng sử dụng mã khác.'];
    }
}

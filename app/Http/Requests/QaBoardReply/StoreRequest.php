<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoardReply;

use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 回答の投稿リクエスト。受講生と、担当資格のコーチだけが投稿できる(管理者は不可)。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');

        return $thread instanceof QaThread
            && ($this->user()?->can('create', [QaReply::class, $thread]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => '回答を入力してください。',
            'body.max' => '回答は5000文字以内で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => '回答',
        ];
    }
}

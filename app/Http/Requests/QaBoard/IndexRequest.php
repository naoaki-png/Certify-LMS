<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 質問掲示板の一覧(受講生 / コーチ / 管理者モデレーション共通)の絞り込みクエリを検証する。
 *
 * `status` は画面の値(`unresolved` / `resolved`)。空文字は「すべて」。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => ['nullable', 'string', 'ulid'],
            'status' => ['nullable', 'string', 'in:unresolved,resolved'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'status' => '解決状態',
            'keyword' => 'キーワード',
        ];
    }
}

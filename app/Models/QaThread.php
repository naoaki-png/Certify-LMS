<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QaThreadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 質問掲示板の質問スレッド。解決状態(`status` / `resolved_at`)は専用の Action でのみ変更する。
 */
class QaThread extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'certification_id',
        'title',
        'body',
        'user_id',
    ];

    /**
     * 作成直後のインスタンスでも DB の初期値(未解決)と同じ値を読めるようにする。
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    protected $casts = [
        'status' => QaThreadStatus::class,
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class, 'certification_id');
    }

    /**
     * @return HasMany<QaReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class, 'qa_thread_id');
    }
}

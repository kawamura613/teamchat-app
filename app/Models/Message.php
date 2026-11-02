<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * メッセージと返信（data.md 1-3 の決定1・2-4）。
 *
 * parent_id が NULL ならメッセージ、値が入っていれば返信。
 *
 * 単位2 では、チャンネル削除の連鎖（T-2-16）と削除の確認に出す件数（T-2-14）に
 * 必要な関連だけを持つ。投稿・一覧・編集・削除は単位4・単位5。
 *
 * 削除しても本文を残すので、deleting で body を触る処理は入れない
 * （questions.md 決定1・案1 / CLAUDE.md）。
 */
class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'channel_id',
        'user_id',
        'parent_id',
        'body',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}

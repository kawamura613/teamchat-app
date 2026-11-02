<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * メッセージと返信（data.md 1-3 の決定1・2-4）。
 *
 * parent_id が NULL ならメッセージ、値が入っていれば返信。
 *
 * 投稿・一覧・編集・削除は単位4（F-13〜F-16）。返信（parent_id に値が入る行）を
 * 作る画面は単位5 だが、返信件数の数え方は単位4 で使う（behavior.md 1-4）。
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

    /**
     * SC-07 のパネルに並べる返信（F-18 / screens.md 3-5）。
     *
     * 忘れると静かに壊れる決めを、この 1 か所にまとめてある
     * （Channel@timeline() と同じ考え方）。
     * - 跡地を出すために削除済みも取ってくる（questions.md 決定1 の出口1-c）
     * - 並びは古い順。同じ時刻のときは id で並び順を決める
     * - 投稿者名とアバターを出すので user を先読みする（N+1 を避ける）
     *
     * 返信に返信は付かないので（回答8）、ここから先は辿らない。
     */
    public function thread(): HasMany
    {
        return $this->replies()
            ->withTrashed()
            ->with('user')
            ->oldest()
            ->orderBy('id');
    }

    /**
     * 返信件数の数え方（behavior.md 1-4 / Q-23 の想定）。
     *
     * **画面は削除済みの親・子を含めて数える。**withCount / loadCount の既定だと
     * SoftDeletes のグローバルスコープが効いて、「返信3件」が静かに「返信2件」に
     * 減る。数え方をここ 1 か所に置いて、withCount（一覧）と loadCount（スレッド）の
     * 両方が同じものを通す。
     *
     * 公開API の reply_count は逆に削除済みを含めない（単位7）。そちらはこれを
     * 使わない（behavior.md 1-4 の表で向きが分かれている）。
     *
     * @return array<string, callable>
     */
    public static function replyCount(): array
    {
        return ['replies' => function (Builder $query) {
            $query->withTrashed();
        }];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_private',
        'created_by',
    ];

    protected $casts = [
        'is_private' => 'boolean',
    ];

    /**
     * その人が見られるチャンネル（permissions-api.md 1-6）。
     *
     * 公開である **または** channel_user に自分の行がある。サイドバー（F-04）・
     * チャンネル一覧（F-06）・検索（F-20）・チャンネルの取得は、すべてここ 1 か所を
     * 通す。自前の where を書くと、それが抜け道になる。
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('is_private', false)
                ->orWhereHas('members', function (Builder $members) use ($user) {
                    $members->whereKey($user->getKey());
                });
        });
    }

    /**
     * 公開チャンネル（permissions-api.md 1-6・2-3・3-2）。
     *
     * **公開API（F-21・F-22）の 2 本は、この 1 か所だけを通す。**
     *
     * visibleTo とは別の物差しであることに意味がある。visibleTo は利用者を
     * 引数に取るが、**公開API は呼ぶ相手を判定に使わない**（permissions-api.md
     * 1-3）ので、そもそも渡すものが無い。
     *
     * visibleTo の中身をこちらに寄せる（`$q->public()->orWhereHas(...)` にする）
     * ことはしない。単位2〜6 が通る道なので、書き換えると回帰が出る。
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_private', false);
    }

    /**
     * この人がこのチャンネルを見られるか（permissions-api.md 1-6）。
     *
     * **判定の中身は visibleTo スコープのままで、ここは薄い包み。**条件を書き写すと
     * 物差しが 2 つになり、スコープを広げたときに静かに食い違う。
     *
     * 1 枚の画面にそのチャンネルのメッセージが何件も並び、MessagePolicy が
     * **1 件ごとに** ChannelPolicy@view を呼ぶので、答えだけをこのインスタンスに
     * 覚える。**1 リクエストのあいだだけ**で、別の問い合わせで引き直した行は
     * 覚え直す（メンバーの出入りを跨いで古い答えを返さない）。
     *
     * メッセージ側は channel を先読みしておく（Channel@timeline・Message@thread）。
     * そうすると同じチャンネルのインスタンスが行をまたいで共有され、この覚えが効く。
     *
     * @var array<int, bool>
     */
    protected array $visibleToCache = [];

    public function isVisibleTo(User $user): bool
    {
        return $this->visibleToCache[$user->getKey()] ??= static::visibleTo($user)
            ->whereKey($this->getKey())
            ->exists();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * プライベートチャンネルのメンバー（data.md 2-3）。
     *
     * 中間テーブルに日時の列を持たないので withTimestamps() は付けない。
     * 公開チャンネルはメンバーという状態を持たないため、行がそもそも無い。
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * SC-06 に出すメッセージ（F-13）。
     *
     * 忘れると静かに壊れる決めを、この 1 か所にまとめてある。
     * - 返信は一覧に流さないので parent_id が NULL の行だけ（data.md 2-4）
     * - 並びは古い順（回答7）。同じ時刻のときは id で並び順を決める
     * - 跡地を出すために削除済みも取ってくる（questions.md 決定1 の出口1）
     * - 返信件数は削除済みの親・子を含めて数える。数え方は
     *   Message::replyCount() 1 か所に置いてあり、スレッド（loadCount）と
     *   同じものを通す。既定だと「返信3件」が静かに「返信2件」に減る
     *   （behavior.md 1-4）
     * - 投稿者名とアバターを出すので user を先読みする（N+1 を避ける）
     * - **channel も先読みする。**部品が 1 件ごとに can('update') を聞き、
     *   MessagePolicy が $message->channel を触るので、付けないと 1 件ごとに
     *   遅延ロードが走る（CLAUDE.md の N+1 の決め / permissions-api.md 1-6）
     */
    public function timeline(): HasMany
    {
        return $this->messages()
            ->withTrashed()
            ->whereNull('parent_id')
            ->with(['user', 'channel'])
            ->withCount(Message::replyCount())
            ->oldest()
            ->orderBy('id');
    }
}

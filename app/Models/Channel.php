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
}

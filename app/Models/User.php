<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    // personal_access_tokens は使わないので HasApiTokens は外す（data.md 2-6）
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * 自分がメンバーのプライベートチャンネル（data.md 2-3）。
     *
     * 公開チャンネルはメンバーという状態を持たないので、ここには出てこない。
     * 中間テーブルに日時の列が無いので withTimestamps() は付けない。
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class);
    }
}

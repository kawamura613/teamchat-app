<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * 初期データ（F-23 / acceptance.md 0章）。
     *
     * 単位1 ではアカウント3つだけ。チャンネル・メッセージ・返信は単位2〜5 で足します。
     * 3つともパスワードは "password"（受け入れ条件を上から試すため・acceptance.md 0章）。
     */
    public function run(): void
    {
        DB::transaction(function () {
            foreach ([
                ['name' => '佐藤 太郎', 'email' => 'sato@example.com'],
                ['name' => '鈴木 花子', 'email' => 'suzuki@example.com'],
                ['name' => '高橋 健', 'email' => 'takahashi@example.com'],
            ] as $attributes) {
                User::create($attributes + ['password' => 'password']);
            }
        });
    }
}

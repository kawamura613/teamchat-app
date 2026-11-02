<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * 初期データ（F-23 / acceptance.md 0章）。
     *
     * 単位2 の時点で入るのはアカウント3つとチャンネル4つまで。メッセージ・返信・
     * 「編集済み」・「削除されました」は、それを作る画面ができる単位4・単位5 で足す。
     *
     * 3つともパスワードは "password"（受け入れ条件を上から試すため）。
     * 途中で落ちた状態のデータでお披露目をしないよう、全部を1つの
     * トランザクションに入れる（behavior.md 3-5）。
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->createChannels($this->createUsers());
        });
    }

    /**
     * @return array<string, User>
     */
    private function createUsers(): array
    {
        $users = [];

        foreach ([
            'sato' => ['name' => '佐藤 太郎', 'email' => 'sato@example.com'],
            'suzuki' => ['name' => '鈴木 花子', 'email' => 'suzuki@example.com'],
            'takahashi' => ['name' => '高橋 健', 'email' => 'takahashi@example.com'],
        ] as $key => $attributes) {
            $users[$key] = User::create($attributes + ['password' => 'password']);
        }

        return $users;
    }

    /**
     * チャンネル4つ（公開3・プライベート1）。名前と説明はモックアップの見本に合わせる。
     *
     * プライベートの「採用プロジェクト」に高橋さんを入れない。この1人が
     * AC-2-2・T-2-13（と単位6 の AC-6-3・T-6-07）の前提（acceptance.md 0章）。
     *
     * @param  array<string, User>  $users
     */
    private function createChannels(array $users): void
    {
        foreach ([
            ['name' => '全体連絡', 'description' => '全員に共有したいこと', 'is_private' => false, 'creator' => 'suzuki'],
            ['name' => '開発', 'description' => '開発に関する相談と報告', 'is_private' => false, 'creator' => 'sato'],
            ['name' => '雑談', 'description' => 'なんでもどうぞ', 'is_private' => false, 'creator' => 'takahashi'],
            ['name' => '採用プロジェクト', 'description' => '採用まわりの進行', 'is_private' => true, 'creator' => 'sato', 'members' => ['sato', 'suzuki']],
        ] as $attributes) {
            $channel = Channel::create([
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'is_private' => $attributes['is_private'],
                'created_by' => $users[$attributes['creator']]->id,
            ]);

            // 公開チャンネルは行を作らない（data.md 1-3 の決定2）
            foreach ($attributes['members'] ?? [] as $member) {
                $channel->members()->attach($users[$member]);
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * 初期データ（F-23 / acceptance.md 0章）。
     *
     * 単位4 で 0章 の表が全部埋まる。アカウント3つ・チャンネル4つ（公開3・
     * プライベート1）・公開チャンネルに各10件・返信が付いたメッセージ3件
     * （うち1件は返信3件）・「編集済み」1件・「削除されました」1件（返信つき）。
     *
     * 返信を作る画面（SC-07）は単位5 だが、AC-4-3 の「跡地に返信件数はそのまま
     * 出る」を手で確かめるために、返信は画面より先に入れる（acceptance.md 0章）。
     *
     * 3つともパスワードは "password"（受け入れ条件を上から試すため）。
     * 途中で落ちた状態のデータでお披露目をしないよう、全部を1つの
     * トランザクションに入れる（behavior.md 3-5）。
     *
     * 日時はモックアップに合わせて 2026-08-17（月）から。日付をまたぐところに
     * 帯が1本入ることを、開いてすぐ確かめられるようにしている。
     */
    public function run(): void
    {
        DB::transaction(function () {
            $users = $this->createUsers();
            $this->createMessages($users, $this->createChannels($users));
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
     * @return array<string, Channel>
     */
    private function createChannels(array $users): array
    {
        $channels = [];

        foreach ([
            'announce' => ['name' => '全体連絡', 'description' => '全員に共有したいこと', 'is_private' => false, 'creator' => 'suzuki'],
            'dev' => ['name' => '開発', 'description' => '開発に関する相談と報告', 'is_private' => false, 'creator' => 'sato'],
            'chat' => ['name' => '雑談', 'description' => 'なんでもどうぞ', 'is_private' => false, 'creator' => 'takahashi'],
            'hiring' => ['name' => '採用プロジェクト', 'description' => '採用まわりの進行', 'is_private' => true, 'creator' => 'sato', 'members' => ['sato', 'suzuki']],
        ] as $key => $attributes) {
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

            $channels[$key] = $channel;
        }

        return $channels;
    }

    /**
     * メッセージと返信。
     *
     * 「開発」は channel-show.html と thread.html の見本をそのまま写し、日付を
     * またぐぶんを足して10件にしている（跡地と「編集済み」もその見本のもの）。
     *
     * @param  array<string, User>  $users
     * @param  array<string, Channel>  $channels
     */
    private function createMessages(array $users, array $channels): void
    {
        foreach ($this->messages() as $channelKey => $rows) {
            foreach ($rows as $row) {
                $parent = $this->makeMessage($channels[$channelKey], $users, $row);

                foreach ($row['replies'] ?? [] as $reply) {
                    // 返信の channel_id は親と同じ（data.md 4-3 / behavior.md）
                    $this->makeMessage($channels[$channelKey], $users, $reply, $parent);
                }
            }
        }
    }

    /**
     * 1行作る。
     *
     * created_at を見本の日時にしたいので、new してから入れる（fillable に
     * 入れると画面からも書き換えられてしまう）。deleted_at を入れた行は、
     * 最初から「削除されました」の状態で入る。**body はそのまま残す**
     * （questions.md 決定1・案1）。
     *
     * @param  array<string, User>  $users
     * @param  array<string, mixed>  $row
     */
    private function makeMessage(Channel $channel, array $users, array $row, ?Message $parent = null): Message
    {
        $message = new Message([
            'channel_id' => $channel->id,
            'user_id' => $users[$row['by']]->id,
            'parent_id' => $parent?->id,
            'body' => $row['body'],
        ]);

        $message->created_at = $row['at'];
        $message->updated_at = $row['at'];
        $message->edited_at = $row['edited'] ?? null;
        $message->deleted_at = $row['deleted'] ?? null;
        $message->save();

        return $message;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function messages(): array
    {
        return [
            'announce' => [
                ['at' => '2026-08-17 08:50', 'by' => 'suzuki', 'body' => '今週の全体会議は水曜10時からです。議題がある方は火曜の夕方までに連絡してください。'],
                ['at' => '2026-08-17 09:02', 'by' => 'sato', 'body' => '承知しました。チャンネル管理の進捗を1枚にまとめて出します。'],
                ['at' => '2026-08-17 11:30', 'by' => 'takahashi', 'body' => '来月の健康診断の日程表を共有フォルダに置きました。各自で希望日を入れてください。'],
                ['at' => '2026-08-17 13:05', 'by' => 'suzuki', 'body' => '来客の予定が入ったので、水曜の会議室は3階に変わりました。'],
                ['at' => '2026-08-17 16:40', 'by' => 'sato', 'body' => '経費の精算は、今月ぶんを25日までにお願いします。'],
                ['at' => '2026-08-19 09:15', 'by' => 'suzuki', 'body' => '全体会議、これから始めます。議事録はあとで共有します。'],
                ['at' => '2026-08-19 10:35', 'by' => 'takahashi', 'body' => '議事録を共有フォルダに置きました。補足があれば書き足してください。'],
                ['at' => '2026-08-19 11:00', 'by' => 'suzuki', 'body' => 'ありがとうございます。来週の予定も同じ場所に置いてあります。'],
                ['at' => '2026-08-19 14:20', 'by' => 'sato', 'body' => '社内の問い合わせ窓口が来月から変わります。詳しいことは追って連絡します。'],
                ['at' => '2026-08-19 17:10', 'by' => 'takahashi', 'body' => '今日はこれで失礼します。おつかれさまでした。'],
            ],
            'dev' => [
                // 返信2件（channel-show.html の「返信2件」）
                ['at' => '2026-08-17 09:12', 'by' => 'suzuki', 'body' => 'おはようございます。9月リリースの日程を今週中に確定させたいです。金曜の夕方までに、残っているタスクを各自共有してもらえますか。', 'replies' => [
                    ['at' => '2026-08-17 09:25', 'by' => 'takahashi', 'body' => '検索まわりとチャンネル管理が残っています。木曜までに終わる見込みです。'],
                    ['at' => '2026-08-17 09:40', 'by' => 'sato', 'body' => 'こちらは画面の文言の見直しだけです。水曜には出せます。'],
                ]],
                // 「編集済み」の1件（0章の表）
                ['at' => '2026-08-17 09:20', 'by' => 'takahashi', 'body' => '承知しました。検索まわりの実装は木曜に終わる見込みです。', 'edited' => '2026-08-17 09:22'],
                ['at' => '2026-08-17 09:31', 'by' => 'sato', 'body' => 'ありがとうございます。私はチャンネル管理画面のレビューを今日中に返します。'],
                // 「削除されました」の1件。返信が3件付いている（thread.html の形）。
                // body はデータベースに残る。画面・検索・公開APIには出さない
                ['at' => '2026-08-17 10:05', 'by' => 'takahashi', 'body' => 'ステージング環境でログインできません。原因を調べてから書き直します。', 'deleted' => '2026-08-17 10:30', 'replies' => [
                    ['at' => '2026-08-17 10:12', 'by' => 'sato', 'body' => 'こちらの環境でも同じ症状が出ました。ログインだけ失敗して、他の画面は問題ありません。'],
                    ['at' => '2026-08-17 10:48', 'by' => 'suzuki', 'body' => '原因が分かりました。ステージングの設定ファイルの参照先が古いままでした。更新しておきます。'],
                    ['at' => '2026-08-17 11:02', 'by' => 'takahashi', 'body' => '更新を確認しました。ログインできています。ありがとうございます。'],
                ]],
                ['at' => '2026-08-17 10:48', 'by' => 'suzuki', 'body' => 'ステージング環境でログインに失敗する件、原因が分かりました。設定ファイルの参照先が古いままでした。夕方までに直します。'],
                ['at' => '2026-08-17 11:15', 'by' => 'sato', 'body' => '助かりました。手順書のほうも合わせて直しておきます。'],
                ['at' => '2026-08-17 13:40', 'by' => 'takahashi', 'body' => '見積もりの件、来週の打ち合わせまでに数字を揃えておきます。必要な資料があれば教えてください。'],
                ['at' => '2026-08-17 14:32', 'by' => 'sato', 'body' => 'ありがとうございます。前回と同じ様式で大丈夫です。こちらでも先週分の実績をまとめておきます。'],
                ['at' => '2026-08-18 09:05', 'by' => 'suzuki', 'body' => 'おはようございます。昨日のリリース日程、金曜の午後で仮置きしました。'],
                ['at' => '2026-08-18 10:20', 'by' => 'takahashi', 'body' => '検索まわり、きのうの見込みより早く終わりました。レビューをお願いします。'],
            ],
            'chat' => [
                // 返信2件のうち1件が削除済み。「返信2件」のまま減らないことを
                // 画面で確かめられるようにしてある（behavior.md 1-4 / T-4-19）
                ['at' => '2026-08-17 12:10', 'by' => 'takahashi', 'body' => '駅前に新しいラーメン屋ができていました。お昼に行った方はいますか。', 'replies' => [
                    ['at' => '2026-08-17 12:20', 'by' => 'sato', 'body' => '行きました。並びましたが、思ったほどではなかったです。', 'deleted' => '2026-08-17 12:40'],
                    ['at' => '2026-08-17 12:35', 'by' => 'suzuki', 'body' => '気になっていました。今度の金曜に行ってみます。'],
                ]],
                ['at' => '2026-08-17 12:45', 'by' => 'suzuki', 'body' => 'となりのカフェも改装が終わったみたいです。'],
                ['at' => '2026-08-17 18:20', 'by' => 'takahashi', 'body' => 'おつかれさまでした。今日は早めに上がります。'],
                ['at' => '2026-08-18 12:05', 'by' => 'sato', 'body' => 'お弁当を持ってくる人は、どのくらいいますか。'],
                ['at' => '2026-08-18 12:30', 'by' => 'takahashi', 'body' => 'わたしは週の半分くらいです。'],
                ['at' => '2026-08-18 12:52', 'by' => 'suzuki', 'body' => '給湯室のポットを新しくしました。使い方を貼っておきました。'],
                ['at' => '2026-08-20 09:40', 'by' => 'sato', 'body' => 'おはようございます。今朝は電車が止まっていて、少し遅れます。'],
                ['at' => '2026-08-20 10:05', 'by' => 'takahashi', 'body' => '了解です。気をつけてお越しください。'],
                ['at' => '2026-08-20 15:30', 'by' => 'suzuki', 'body' => '差し入れのお菓子を休憩室に置きました。どうぞ。'],
                ['at' => '2026-08-20 17:45', 'by' => 'sato', 'body' => 'ありがとうございます。いただきました。'],
            ],
            // プライベートチャンネルにも中身を入れる。入っていない高橋さんから
            // 見えないことを画面で確かめるため（AC-2-2）。単位6 の AC-6-3 は
            // 「ここにしか無い語」で検索する前提なので、そのための文も置く
            'hiring' => [
                ['at' => '2026-08-17 10:00', 'by' => 'sato', 'body' => '来週の一次面接の日程を詰めます。候補日を共有フォルダに置きました。'],
                ['at' => '2026-08-17 10:30', 'by' => 'suzuki', 'body' => '確認しました。水曜の午後なら同席できます。'],
                ['at' => '2026-08-18 11:15', 'by' => 'sato', 'body' => '一次面接の質問票をまとめました。目を通しておいてください。'],
                ['at' => '2026-08-18 14:00', 'by' => 'suzuki', 'body' => '読みました。1問だけ言い回しを直しました。'],
            ],
        ];
    }
}

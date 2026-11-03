<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 単位4「メッセージ」（F-13〜F-16 ＋ F-05 の △3）。
 *
 * 並びは acceptance.md 3-4 のテスト観点（T-4-01〜T-4-23）に合わせる。
 *
 * 単位4 では判定できなかったものは、単位5 で ThreadTest に入れた:
 * - T-4-20 の「一覧とスレッドが同じ部品を通る」のスレッド側
 * - T-4-21 の「スレッドから消したらスレッド」
 */
class MessageTest extends TestCase
{
    use RefreshDatabase;

    private User $作成者;

    private User $見られる人;

    private User $見られない人;

    private Channel $公開チャンネル;

    private Channel $プライベートチャンネル;

    protected function setUp(): void
    {
        parent::setUp();

        $this->作成者 = User::create(['name' => '佐藤 太郎', 'email' => 'sato@example.com', 'password' => 'password']);
        $this->見られる人 = User::create(['name' => '鈴木 花子', 'email' => 'suzuki@example.com', 'password' => 'password']);
        $this->見られない人 = User::create(['name' => '高橋 健', 'email' => 'takahashi@example.com', 'password' => 'password']);

        $this->公開チャンネル = $this->makeChannel(['name' => '開発', 'description' => '開発に関する相談と報告']);
        $this->プライベートチャンネル = $this->makeChannel([
            'name' => '採用プロジェクト',
            'description' => '採用まわりの進行',
            'is_private' => true,
        ]);
        $this->プライベートチャンネル->members()->attach([$this->作成者->id, $this->見られる人->id]);
    }

    // 見る

    public function test_並びが古い順で日付の区切りが変わり目にだけ1本入る(): void
    {
        // T-4-06。同じ日に2件あっても帯は1本（screens.md 3-3 / 回答7）
        $this->makeMessage($this->公開チャンネル, ['body' => '十七日の朝', 'created_at' => '2026-08-17 09:12']);
        $this->makeMessage($this->公開チャンネル, ['body' => '十七日の夜', 'created_at' => '2026-08-17 21:30']);
        $this->makeMessage($this->公開チャンネル, ['body' => '十八日の朝', 'created_at' => '2026-08-18 09:05']);

        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));
        $response->assertOk();

        $本文 = $response->getContent();
        $this->assertLessThan(mb_strpos($本文, '十七日の夜'), mb_strpos($本文, '十七日の朝'));
        $this->assertLessThan(mb_strpos($本文, '十八日の朝'), mb_strpos($本文, '十七日の夜'));

        $this->assertSame(1, mb_substr_count($本文, '2026年8月17日（月）'));
        $this->assertSame(1, mb_substr_count($本文, '2026年8月18日（火）'));
        $this->assertSame(2, mb_substr_count($本文, 'class="day-sep"'));
    }

    public function test_ページ送りがなく全件出て末尾に着地点がある(): void
    {
        // T-4-07。ページ送り・もっと読む・無限スクロールは作らない（Q-16）。
        // 「一番下が見えている」のは app.js のスクロール（behavior.md 4-1 の #3）で、
        // ここで見るのはその着地点（#bottom）が1つあること（4-4）
        for ($分 = 1; $分 <= 25; $分++) {
            $this->makeMessage($this->公開チャンネル, [
                'body' => "本文{$分}番",
                'created_at' => sprintf('2026-08-17 09:%02d', $分),
            ]);
        }

        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));
        $response->assertOk();

        for ($分 = 1; $分 <= 25; $分++) {
            $response->assertSee("本文{$分}番", false);
        }

        $this->assertSame(1, mb_substr_count($response->getContent(), 'id="bottom"'));
    }

    public function test_メッセージが0件のチャンネルで空状態の枠が出る(): void
    {
        // T-4-18（screens.md 3-3 / Q-35 の想定）
        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));

        $response->assertSee('class="empty"', false);
        $response->assertSee('まだメッセージがありません', false);
        $response->assertSee('最初のメッセージを送ってみましょう', false);
        $response->assertDontSee('class="day-sep"', false);
    }

    public function test_日時と日付の区切りが決めた書き方で日本時間になる(): void
    {
        // T-4-22（screens.md 5 / Q-37 の想定）
        $this->makeMessage($this->公開チャンネル, ['created_at' => '2026-08-17 09:12']);

        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));

        $response->assertSee('2026/08/17 09:12', false);
        $response->assertSee('2026年8月17日（月）', false);
        $this->assertSame('Asia/Tokyo', config('app.timezone'));
    }

    public function test_本文の途中の改行と空白はそのまま残る(): void
    {
        // T-4-03。取り除くのは前後だけ（screens.md 4-1 / Q-20 の想定）
        $this->postMessage($this->公開チャンネル, ['body' => "一行目\n\n  三行目"]);

        $this->assertSame("一行目\n\n  三行目", Message::sole()->body);

        // 画面でも改行として出す（.msg__body の white-space:pre-line）
        $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル))
            ->assertSee("一行目\n\n  三行目", false);
    }

    public function test_装飾もメンションも自動リンクもしない(): void
    {
        // T-4-04（questions.md Q-20 の想定）
        $素のまま = '**太字** @佐藤 https://example.com/a';
        $this->postMessage($this->公開チャンネル, ['body' => $素のまま]);

        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));

        $response->assertSee($素のまま, false);
        // URL は文字のまま。リンクにしない
        $response->assertDontSee('<a href="https://example.com/a"', false);
        $response->assertDontSee('<strong>', false);
    }

    public function test_削除したメッセージの本文は画面の応答に出てこない(): void
    {
        // T-4-13（決定1 の出口1）。「跡地が出ている」ではなく
        // 「元の本文が出ていない」で書く（acceptance.md 3-8）
        $消した本文 = '消した本文はデータベースにだけ残る';
        $message = $this->makeMessage($this->公開チャンネル, ['body' => $消した本文, 'deleted_at' => now()]);

        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));
        $response->assertOk();
        $response->assertDontSee($消した本文, false);

        // 跡地は残る。その行には編集・ごみ箱を出さない（AC-4-3）
        $response->assertSee('このメッセージは削除されました', false);
        $response->assertDontSee(route('messages.edit', $message), false);
        // ごみ箱は**フォームの action** で見る。単位5 で返信アイコンが
        // messages.show のリンクになり、URL の文字列が destroy と同じに
        // なった（GET と DELETE で同じ /messages/{id}）
        $response->assertDontSee('action="'.route('messages.destroy', $message).'"', false);

        // 本文はデータベースに残っている（決定1・案1）
        $this->assertSame($消した本文, Message::withTrashed()->sole()->body);
    }

    public function test_本文と跡地の出し分けはメッセージ1件の部品だけにある(): void
    {
        // T-4-20。画面ごとに書くと、どれか1つで忘れたときに本文が出る
        // （questions.md 決定1 の出口1 / behavior.md 2-3 の決めどころ7）
        $部品 = file_get_contents(resource_path('views/messages/partials/message.blade.php'));
        $this->assertStringContainsString('trashed()', $部品);
        $this->assertStringContainsString('このメッセージは削除されました', $部品);

        // 一覧（SC-06）もスレッドのパネル（SC-07）も、同じ部品を通る。
        // **どちらの画面側にも出し分けを書き写さない**（単位5 で SC-07 を足した）
        foreach (['channels/partials/main', 'messages/partials/thread'] as $画面) {
            $中身 = file_get_contents(resource_path("views/{$画面}.blade.php"));
            $this->assertStringContainsString("@include('messages.partials.message'", $中身);
            $this->assertStringNotContainsString('このメッセージは削除されました', $中身);
            $this->assertStringNotContainsString('trashed', $中身);
        }
    }

    public function test_投稿欄は書ける人にだけ出て見られないチャンネルへの投稿は404(): void
    {
        // T-4-17（screens.md 3-3 / permissions-api.md 1-3 の F-14）
        $response = $this->actingAs($this->見られる人)->get(route('channels.show', $this->プライベートチャンネル));
        $response->assertOk();
        $response->assertSee(route('channels.messages.store', $this->プライベートチャンネル), false);

        $this->actingAs($this->見られない人)
            ->get(route('channels.show', $this->プライベートチャンネル))
            ->assertNotFound();

        // 投稿も 404。403 を返すとそこに部屋があることが漏れる（1-5）
        $this->actingAs($this->見られない人)
            ->post(route('channels.messages.store', $this->プライベートチャンネル), ['body' => 'メッセージ'])
            ->assertNotFound();

        $this->assertSame(0, Message::count());
    }

    public function test_アバターは表示名の先頭2文字で人ごとに色が決まる(): void
    {
        // T-1-17（Q-36 の想定）。アバターを描く画面は単位4 以降なので、観点は
        // 単位1 の番号のままここで確かめる（acceptance.md 3-1 の T-1-17）
        //
        // 色は ($user->id - 1) % 4 で決まる（partials/avatar.blade.php）。
        // **id を固定して作る。**RefreshDatabase はトランザクションを巻き戻すだけで
        // MySQL の AUTO_INCREMENT は戻らないので、id を採番に任せると、ほかの
        // テストクラスの件数で出る色が変わってしまう（2026-11-03 に修正）
        foreach ([1 => '一色 いちろう', 2 => '二色 じろう', 3 => '三色 さぶろう', 4 => '佐'] as $id => $名前) {
            $user = new User;
            $user->forceFill([
                'id' => $id,
                'name' => $名前,
                'email' => "avatar{$id}@example.com",
                'password' => 'password',
            ]);
            $user->save();
            $this->makeMessage($this->公開チャンネル, ['user_id' => $id]);
        }

        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));

        // 先頭2文字。1文字の表示名（id 4 の「佐」）は1文字だけ
        $response->assertSee('>一色<', false);
        $response->assertSee('>二色<', false);
        $response->assertSee('>三色<', false);
        $response->assertSee('>佐</span>', false);

        // 人ごとに固定の4色。id から機械的に割り当てる（既定・--b・--c・--d）
        $response->assertSee('class="avatar"', false);
        $response->assertSee('class="avatar avatar--b"', false);
        $response->assertSee('class="avatar avatar--c"', false);
        $response->assertSee('class="avatar avatar--d"', false);
    }

    // 送る

    public function test_本文は空で弾かれ1000文字は通り1001文字で弾かれる(): void
    {
        // T-4-01（screens.md 4-6）
        $this->postMessage($this->公開チャンネル, ['body' => ''])
            ->assertSessionHasErrors(['body' => 'メッセージを入力してください']);

        $this->postMessage($this->公開チャンネル, ['body' => str_repeat('あ', 1000)])
            ->assertSessionHasNoErrors();

        $this->postMessage($this->公開チャンネル, ['body' => str_repeat('あ', 1001)])
            ->assertSessionHasErrors(['body' => 'メッセージは1,000文字以内で入力してください']);

        $this->assertSame(1, Message::count());
    }

    public function test_前後の空白と改行を落としたあとの字数で数え落とした値を保存する(): void
    {
        // T-4-02（screens.md 4-1 / behavior.md 3-8）。全角スペースも落とす
        $this->postMessage($this->公開チャンネル, ['body' => "　\n  \n"])
            ->assertSessionHasErrors(['body' => 'メッセージを入力してください']);
        $this->assertSame(0, Message::count());

        // 前後を落としたあとが 1,000 文字なら通る
        $this->postMessage($this->公開チャンネル, ['body' => "  \n".str_repeat('あ', 1000)."　\n"])
            ->assertSessionHasNoErrors();

        $this->assertSame(str_repeat('あ', 1000), Message::sole()->body);
    }

    public function test_文字数の表示は打ちやすさのためで上限はサーバ側でも弾く(): void
    {
        // T-4-05（Q-21 の想定 / screens.md 4-1）
        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));
        $response->assertSee('data-count-for="composer-body"', false);
        $response->assertSee('data-count-max="1000"', false);
        $response->assertSee('0 / 1,000 文字', false);

        // 表示を外して送ってもサーバ側で弾く
        $this->postMessage($this->公開チャンネル, ['body' => str_repeat('あ', 1001)])
            ->assertSessionHasErrors('body');
        $this->assertSame(0, Message::count());
    }

    public function test_投稿すると一覧の末尾へ戻り知らせは出さない(): void
    {
        // AC-4-1 の戻り先（behavior.md 4-3）。投稿・返信・編集に知らせは出さない（4-6）
        $response = $this->postMessage($this->公開チャンネル, ['body' => '書いた本文']);

        $response->assertRedirect(route('channels.show', $this->公開チャンネル).'#bottom');
        $response->assertSessionMissing('status');

        $message = Message::sole();
        $this->assertSame($this->作成者->id, $message->user_id);
        $this->assertNull($message->parent_id);
        $this->assertNull($message->edited_at);
    }

    // 書き換える

    public function test_投稿欄と編集中の入力欄が打った内容を取り違えない(): void
    {
        // 同じ画面に body という名前の欄が2つある。どちらが落ちたかをエラーの袋で
        // 見分けないと、投稿欄の下書きが編集中の入力欄に入り、「保存」で本文が
        // 新規投稿のつもりの文に上書きされる（screens.md 4-6 / behavior.md 4-2）
        $message = $this->makeMessage($this->公開チャンネル, ['body' => '元の本文']);
        $長すぎる投稿 = str_repeat('あ', 1001);
        $長すぎる書き換え = str_repeat('い', 1001);

        // 編集中のアドレスを開いたまま、投稿欄が落ちたとき
        $response = $this->actingAs($this->作成者)
            ->from(route('messages.edit', $message))
            ->followingRedirects()
            ->post(route('channels.messages.store', $this->公開チャンネル), ['body' => $長すぎる投稿]);
        $response->assertOk();

        $画面 = $response->getContent();
        // 編集中の入力欄は元の本文のまま
        $this->assertSame('元の本文', $this->textareaOf($画面, 'message-body-'.$message->id));
        // 投稿欄には打った下書きが残る（直せるように見せる）
        $this->assertSame($長すぎる投稿, $this->textareaOf($画面, 'composer-body'));

        // 逆向き: 編集中が落ちたとき
        $response = $this->actingAs($this->作成者)
            ->followingRedirects()
            ->patch(route('messages.update', $message), ['body' => $長すぎる書き換え]);
        $response->assertOk();

        $画面 = $response->getContent();
        $this->assertSame($長すぎる書き換え, $this->textareaOf($画面, 'message-body-'.$message->id));
        $this->assertSame('', $this->textareaOf($画面, 'composer-body'));

        // どちらでも本文は変わっていない
        $this->assertSame('元の本文', $message->fresh()->body);
    }

    public function test_書き換えて空にして保存しても削除にならず編集中のまま残る(): void
    {
        // T-4-08（screens.md 4-6）。消すときはごみ箱を使う
        $message = $this->makeMessage($this->公開チャンネル, ['body' => '元の本文']);

        $response = $this->actingAs($this->作成者)
            ->patch(route('messages.update', $message), ['body' => '   ']);

        // 錨つきで戻す。無いと app.js が一覧を末尾まで送って編集中が画面の外になる。
        // from も引き継ぐ（送っていないので channel。behavior.md 4-2）
        $response->assertRedirect(
            route('messages.edit', [$message, 'from' => 'channel']).'#message-'.$message->id
        );
        $response->assertSessionHasErrors(['body' => 'メッセージを入力してください'], null, 'edit');

        $message->refresh();
        $this->assertSame('元の本文', $message->body);
        $this->assertNull($message->deleted_at);
        $this->assertNull($message->edited_at);
    }

    public function test_書き換えをやめると元の本文に戻り何も保存されない(): void
    {
        // T-4-09（screens.md 4-6）。「やめる」は元の画面へのリンクで何も送らない
        $message = $this->makeMessage($this->公開チャンネル, ['body' => '元の本文']);

        $response = $this->actingAs($this->作成者)->get(route('messages.edit', $message));
        $response->assertOk();
        $response->assertSee('data-edit-cancel', false);
        $response->assertSee(route('channels.show', $this->公開チャンネル).'#message-'.$message->id, false);
        // 入力欄には元の本文が入っている
        $response->assertSee('元の本文', false);

        $message->refresh();
        $this->assertSame('元の本文', $message->body);
        $this->assertNull($message->edited_at);
    }

    public function test_編集中のアドレスで開いてもサイドバーの行が選択中になる(): void
    {
        // screens.md 3-1 の「選択中の強調」。messages.edit には {channel} が
        // 無いので、{message} が属するチャンネルで決める（permissions-api.md 1-6）
        $message = $this->makeMessage($this->公開チャンネル, ['body' => '元の本文']);

        $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル))
            ->assertSee('class="side-item is-selected"', false);

        $response = $this->actingAs($this->作成者)->get(route('messages.edit', $message));
        $response->assertOk();
        $response->assertSee('class="side-item is-selected"', false);
        // 光るのは1行だけ
        $this->assertSame(1, mb_substr_count($response->getContent(), 'is-selected'));
    }

    public function test_編集済みの印は一度付いたら消えない(): void
    {
        // T-4-10（回答5 / behavior.md 1-3 の遷移4）
        $message = $this->makeMessage($this->公開チャンネル, ['body' => '一回目']);

        $this->actingAs($this->作成者)->patch(route('messages.update', $message), ['body' => '二回目']);
        $this->assertNotNull($message->fresh()->edited_at);

        $this->actingAs($this->作成者)->patch(route('messages.update', $message), ['body' => '三回目']);

        $message->refresh();
        $this->assertNotNull($message->edited_at);
        $this->assertSame('三回目', $message->body);

        $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル))
            ->assertSee('編集済み', false);
    }

    public function test_編集で投稿日時は動かず編集済みの日時だけ入る(): void
    {
        // T-4-11（Q-17 の想定 / data.md 2-4）
        $message = $this->makeMessage($this->公開チャンネル, [
            'body' => '元の本文',
            'created_at' => '2026-08-17 09:12',
        ]);

        $response = $this->actingAs($this->作成者)
            ->patch(route('messages.update', $message), ['body' => '直した本文']);

        $response->assertRedirect(route('channels.show', $this->公開チャンネル).'#message-'.$message->id);

        $message->refresh();
        $this->assertSame('2026-08-17 09:12:00', $message->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('直した本文', $message->body);
        $this->assertNotNull($message->edited_at);

        // 画面に出す日時も書いたときのまま
        $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル))
            ->assertSee('2026/08/17 09:12', false);
    }

    public function test_削除済みのメッセージの編集と再削除は404になる(): void
    {
        // T-4-14（Q-19 の想定 / permissions-api.md 1-5）。403 ではない
        $message = $this->makeMessage($this->公開チャンネル, ['deleted_at' => now()]);

        $this->actingAs($this->作成者)->get(route('messages.edit', $message))->assertNotFound();
        $this->actingAs($this->作成者)->patch(route('messages.update', $message), ['body' => '直した本文'])->assertNotFound();
        $this->actingAs($this->作成者)->delete(route('messages.destroy', $message))->assertNotFound();
    }

    public function test_見えているチャンネルで他人のメッセージを直すと403(): void
    {
        // T-4-15（permissions-api.md 1-5）。そのメッセージは本人に見えている
        $message = $this->makeMessage($this->公開チャンネル, ['body' => '作成者の本文']);

        $this->actingAs($this->見られる人)->get(route('messages.edit', $message))->assertForbidden();
        $this->actingAs($this->見られる人)->patch(route('messages.update', $message), ['body' => '直した本文'])->assertForbidden();
        $this->actingAs($this->見られる人)->delete(route('messages.destroy', $message))->assertForbidden();

        // 画面にもホバーの編集・ごみ箱は出ない。返信のアイコンだけ残る（AC-4-4）
        $response = $this->actingAs($this->見られる人)->get(route('channels.show', $this->公開チャンネル));
        $response->assertDontSee(route('messages.edit', $message), false);
        // ごみ箱はフォームの action で見る（上と同じ事情）
        $response->assertDontSee('action="'.route('messages.destroy', $message).'"', false);
        $response->assertSee('title="返信"', false);

        $message->refresh();
        $this->assertSame('作成者の本文', $message->body);
        $this->assertNull($message->deleted_at);
    }

    public function test_外された人は自分の過去のメッセージも直せず404になる(): void
    {
        // T-4-16（仕様書 3-3 / permissions-api.md 1-4 の △3・1-5）。
        // **403 が返っていたらチャンネルの存在が漏れている**（acceptance.md 3-8）
        $this->プライベートチャンネル->members()->attach($this->見られない人);
        $message = $this->makeMessage($this->プライベートチャンネル, [
            'user_id' => $this->見られない人->id,
            'body' => '外される前に書いた本文',
        ]);
        $this->プライベートチャンネル->members()->detach($this->見られない人);

        $this->actingAs($this->見られない人)->get(route('messages.edit', $message))->assertNotFound();
        $this->actingAs($this->見られない人)->patch(route('messages.update', $message), ['body' => '直した本文'])->assertNotFound();
        $this->actingAs($this->見られない人)->delete(route('messages.destroy', $message))->assertNotFound();

        // 書いたものはチャンネルにそのまま残る（AC-3-3）
        $message->refresh();
        $this->assertSame('外される前に書いた本文', $message->body);
        $this->assertNull($message->deleted_at);
    }

    // 消す

    public function test_削除すると日時が入り本文は残っている(): void
    {
        // T-4-12（questions.md 決定1）。案2a（本文を消す）に戻っていないことの歯止め
        $message = $this->makeMessage($this->公開チャンネル, ['body' => '消す本文']);

        $this->actingAs($this->作成者)->delete(route('messages.destroy', $message));

        // 論理削除。行は残る
        $this->assertSame(0, Message::count());
        $残り = Message::withTrashed()->sole();
        $this->assertNotNull($残り->deleted_at);
        $this->assertSame('消す本文', $残り->body);
    }

    public function test_削除すると元いた画面の跡地へ戻って知らせが出る(): void
    {
        // T-4-21（behavior.md 2-3 の決めどころ5・4-3・4-6）。
        // 「スレッドから消したらスレッド」は SC-07 ができる単位5
        $message = $this->makeMessage($this->公開チャンネル);

        $response = $this->actingAs($this->作成者)->delete(route('messages.destroy', $message));

        $response->assertRedirect(route('channels.show', $this->公開チャンネル).'#message-'.$message->id);
        $response->assertSessionHas('status', 'メッセージを削除しました');
    }

    public function test_ごみ箱には確認が1枚付いていて画面もアドレスも増えない(): void
    {
        // T-4-23（questions.md 決定11・仮 / behavior.md 4-1 の #4）
        $message = $this->makeMessage($this->公開チャンネル);

        $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル))
            ->assertSee('data-confirm="このメッセージを削除しますか？　元に戻せません。"', false);

        // 画面もアドレスも増やさない（SC番号なし・/messages/{id}/delete は作らない）
        $this->assertFalse(Route::has('messages.delete'));

        // やめたときは何も送られないので、行はそのまま
        $this->assertNull($message->fresh()->deleted_at);
    }

    // 変わっていない

    public function test_削除で返信に触らず返信件数も減らない(): void
    {
        // T-4-19（回答6 / behavior.md 1-4・2-3 の決めどころ4）
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);
        $生きた返信 = $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '生きた返信']);
        $消えた返信 = $this->makeMessage($this->公開チャンネル, [
            'parent_id' => $親->id,
            'body' => '消えた返信',
            'deleted_at' => now(),
        ]);

        $this->actingAs($this->作成者)->delete(route('messages.destroy', $親));

        // 返信の行には触らない
        $this->assertNull($生きた返信->fresh()->deleted_at);
        $this->assertSame('消えた返信', Message::withTrashed()->find($消えた返信->id)->body);

        // 跡地にも件数が出て、削除済みの返信も数に入っている
        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));
        $response->assertSee('このメッセージは削除されました', false);
        $response->assertSee('返信2件', false);
        $response->assertDontSee('親の本文', false);
    }

    private function makeChannel(array $差分 = []): Channel
    {
        return Channel::create($差分 + [
            'name' => '新しいチャンネル',
            'description' => '説明',
            'is_private' => false,
            'created_by' => $this->作成者->id,
        ]);
    }

    /**
     * 1行そのまま作る。created_at・edited_at・deleted_at は fillable に
     * 入れていないので、forceFill で入れる（要るテストだけが差分で渡す）。
     */
    private function makeMessage(Channel $channel, array $差分 = []): Message
    {
        $message = new Message;
        $message->forceFill($差分 + [
            'channel_id' => $channel->id,
            'user_id' => $this->作成者->id,
            'parent_id' => null,
            'body' => 'メッセージ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $message->save();

        return $message;
    }

    private function postMessage(Channel $channel, array $入力 = []): TestResponse
    {
        return $this->actingAs($this->作成者)
            ->post(route('channels.messages.store', $channel), $入力 + ['body' => 'メッセージ']);
    }

    /**
     * その id の入力欄に入っている文字列を取る。
     *
     * 投稿欄と編集中の入力欄は同じ name="body" なので、「どちらに何が入ったか」
     * は欄ごとに見ないと分からない。
     */
    private function textareaOf(string $html, string $id): string
    {
        preg_match('/id="'.preg_quote($id, '/').'"[^>]*>(.*?)<\/textarea>/s', $html, $一致);

        return $一致[1] ?? '';
    }
}

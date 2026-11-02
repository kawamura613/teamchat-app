<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 単位5「スレッド」（F-17〜F-19 ＋ F-05 の △4）。
 *
 * 並びは acceptance.md 3-5 のテスト観点（T-5-01〜T-5-12）に合わせる。
 *
 * **このコミットの時点では SC-07 の Blade（`messages/show.blade.php`）がまだ無い。**
 * そのため、画面を描かずに判定できる観点だけを先に置いてある。描いてから判定する
 * ものは、SC-07 を足す次のコミットでここに足す:
 * - T-5-01・T-5-02（「返信◯件」がリンクで出る／削除済みでも消えない）
 * - T-5-03（返信件数に削除済みの返信を含める。Channel@timeline() を
 *   Message::replyCount() に寄せるのも次のコミット）
 * - T-5-04（スレッドが開ける・× が SC-06 を指す）の 200 側
 * - T-5-05（削除済みの親で開いて本文が出ない。出口1-b）
 * - T-5-11（自分の返信は編集・削除でき、他人の返信にはアイコンが出ない）
 * - 出口1-c（削除した返信の跡地が出て件数が減らない）
 * - T-4-20 のスレッド側（出し分けが部品1か所）
 */
class ThreadTest extends TestCase
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

    public function test_見られないチャンネルのスレッドは見つかりませんになる(): void
    {
        // T-5-12（permissions-api.md 1-5）。**403 だとチャンネルの存在が漏れている**
        // ので、404 であることが合否（acceptance.md 3-8）
        $親 = $this->makeMessage($this->プライベートチャンネル, ['body' => '親の本文']);

        $response = $this->actingAs($this->見られない人)->get(route('messages.show', $親));

        $response->assertNotFound();
        $response->assertDontSee('親の本文', false);
    }

    public function test_返信のアドレスで開くと親のスレッドへ送られる(): void
    {
        // 決定6（behavior.md 4-5）。検索結果が返信に当たると必ず通る道。
        // 302 にするのは、永久の移転ではないのでブラウザに覚えさせないため
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);
        $返信 = $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '返信の本文']);

        $response = $this->actingAs($this->作成者)->get(route('messages.show', $返信));

        $response->assertStatus(302);
        $response->assertRedirect(route('messages.show', $親->id).'#message-'.$返信->id);
    }

    public function test_見られないチャンネルの返信は正規化より先に見つかりませんになる(): void
    {
        // 判定の順番（permissions-api.md 1-5）。先に正規化すると、見られない
        // チャンネルの返信の ID でも 302 が返って、その ID があることが漏れる
        $親 = $this->makeMessage($this->プライベートチャンネル, ['body' => '親の本文']);
        $返信 = $this->makeMessage($this->プライベートチャンネル, ['parent_id' => $親->id, 'body' => '返信の本文']);

        $this->actingAs($this->見られない人)
            ->get(route('messages.show', $返信))
            ->assertNotFound();
    }

    // 送る

    public function test_返信は親と同じチャンネルにぶら下がる(): void
    {
        // T-5-08（data.md 1-3 の決定3・4-3）。DB の制約では縛れないのでアプリ側で守る
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);

        $response = $this->postReply($親, ['body' => '返信の本文']);

        $返信 = Message::where('parent_id', $親->id)->sole();
        $this->assertSame($this->公開チャンネル->id, $返信->channel_id);
        $this->assertSame($親->id, $返信->parent_id);
        $this->assertSame('返信の本文', $返信->body);

        // 戻り先は親のスレッドの、いま書いた1件（behavior.md 4-3 の表）
        $response->assertRedirect(route('messages.show', $親->id).'#message-'.$返信->id);
        // 投稿・返信・編集に知らせは出さない（4-6）
        $response->assertSessionMissing('status');
    }

    public function test_返信はチャンネルの一覧に流れない(): void
    {
        // T-5-09（仕様書 3-5 / data.md 2-4）。一覧は parent_id が NULL の行だけ
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);
        $this->postReply($親, ['body' => '一覧に出てはいけない返信']);

        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));

        $response->assertOk();
        $response->assertSee('親の本文', false);
        $response->assertDontSee('一覧に出てはいけない返信', false);
        // 一覧に出るのは件数のほうだけ（F-17）
        $response->assertSee('返信1件', false);
    }

    public function test_削除されたメッセージにも返信できて件数が増える(): void
    {
        // T-5-06（回答6 / questions.md 決定1 の出口1-d）。そこで会話が続けられ
        // なくなるほうが困る、という回答に乗っている。親は withTrashed() の
        // バインディングで解決され、MessagePolicy@reply は trashed() を見ない
        $親 = $this->makeMessage($this->公開チャンネル, [
            'body' => '消えた親の本文',
            'deleted_at' => now(),
        ]);

        $this->postReply($親, ['body' => '跡地への返信'])
            ->assertSessionHasNoErrors();

        $this->assertSame('跡地への返信', Message::where('parent_id', $親->id)->sole()->body);

        // 一覧の跡地にも件数が出る（behavior.md 1-4）
        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));
        $response->assertSee('このメッセージは削除されました', false);
        $response->assertSee('返信1件', false);
        $response->assertDontSee('消えた親の本文', false);
    }

    public function test_返信に返信はできない(): void
    {
        // T-5-07（回答8 / permissions-api.md 1-4 の △4・1-5）。返信は1段まで。
        // その返信は本人のパネルに並んでいるので隠すものがなく、403
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);
        $返信 = $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '返信の本文']);

        $this->postReply($返信, ['body' => '返信への返信'])->assertForbidden();

        // 行が増えていない
        $this->assertSame(0, Message::where('parent_id', $返信->id)->count());
        $this->assertSame(2, Message::count());
    }

    public function test_見られないチャンネルへの返信は見つかりませんになる(): void
    {
        // T-5-12（permissions-api.md 1-5）。ここも 403 ではなく 404
        $親 = $this->makeMessage($this->プライベートチャンネル, ['body' => '親の本文']);

        $this->actingAs($this->見られない人)
            ->post(route('messages.replies.store', $親), ['body' => '入れないチャンネルへの返信'])
            ->assertNotFound();

        $this->assertSame(0, Message::where('parent_id', $親->id)->count());
    }

    public function test_返信も本文は空で弾かれ1000文字は通り1001文字で弾かれる(): void
    {
        // T-5-10（screens.md 4-6 / Q-25 の想定）。メッセージと同じチェックを
        // 同じ数だけかける。エラーは reply の袋に入れる（SC-07 には name="body" の
        // 欄が3種類あるので、袋を共有すると別の欄の下書きが流れ込む）
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);

        $this->postReply($親, ['body' => ''])
            ->assertSessionHasErrors(['body' => 'メッセージを入力してください'], null, 'reply');

        $this->postReply($親, ['body' => str_repeat('あ', 1000)])
            ->assertSessionHasNoErrors();

        $this->postReply($親, ['body' => str_repeat('あ', 1001)])
            ->assertSessionHasErrors(['body' => 'メッセージは1,000文字以内で入力してください'], null, 'reply');

        $this->assertSame(1, Message::where('parent_id', $親->id)->count());
    }

    public function test_返信も前後の空白と改行を落とした字数で数え落とした値を保存する(): void
    {
        // T-5-10（screens.md 4-1 / behavior.md 3-8）。全角スペースも落とす
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);

        $this->postReply($親, ['body' => "　\n  \n"])
            ->assertSessionHasErrors(['body' => 'メッセージを入力してください'], null, 'reply');
        $this->assertSame(0, Message::where('parent_id', $親->id)->count());

        $this->postReply($親, ['body' => "  \n".str_repeat('あ', 1000)."　\n"])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            str_repeat('あ', 1000),
            Message::where('parent_id', $親->id)->sole()->body
        );
    }

    public function test_入力チェックに落ちた返信は同じスレッドへ戻る(): void
    {
        // screens.md 4-6。返信欄の下にエラー文を出すので、戻り先は SC-07
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);

        $this->postReply($親, ['body' => ''])
            ->assertRedirect(route('messages.show', $親));
    }

    // 消す

    public function test_スレッドから消すとスレッドへ戻り一覧から消すと一覧へ戻る(): void
    {
        // T-4-21（behavior.md 4-3 の決定4）。隠し項目 from の2択だけを見て、
        // URL はサーバ側で組む。送られてきた URL に飛ばすと外部サイトへ
        // 飛ばせる口になるので、文字列は受け取らない
        $一覧から = $this->makeMessage($this->公開チャンネル, ['body' => '一覧から消す']);
        $スレッドから = $this->makeMessage($this->公開チャンネル, ['body' => 'スレッドから消す']);
        $既定 = $this->makeMessage($this->公開チャンネル, ['body' => 'from を送らない']);

        $this->actingAs($this->作成者)
            ->delete(route('messages.destroy', $一覧から), ['from' => 'channel'])
            ->assertRedirect(route('channels.show', $this->公開チャンネル->id).'#message-'.$一覧から->id);

        $this->actingAs($this->作成者)
            ->delete(route('messages.destroy', $スレッドから), ['from' => 'thread'])
            ->assertRedirect(route('messages.show', $スレッドから->id).'#message-'.$スレッドから->id);

        // from が無い・読めないときは channel として扱う（behavior.md 4-3）
        $this->actingAs($this->作成者)
            ->delete(route('messages.destroy', $既定))
            ->assertRedirect(route('channels.show', $this->公開チャンネル->id).'#message-'.$既定->id);
    }

    // 変わっていない

    public function test_返信が入っても親のメッセージは何も変わらない(): void
    {
        // behavior.md 2-3 の決めどころ。返信は親の行に触らない。「編集済み」が
        // 勝手に付いたり、画面に出す日時が動いたりしない（data.md 2-4 / Q-17）
        $親 = $this->makeMessage($this->公開チャンネル, [
            'body' => '親の本文',
            'created_at' => '2026-08-17 09:12',
        ]);

        $this->postReply($親, ['body' => '返信の本文'])->assertSessionHasNoErrors();

        $親->refresh();
        $this->assertSame('親の本文', $親->body);
        $this->assertSame('2026/08/17 09:12', $親->created_at->format('Y/m/d H:i'));
        $this->assertNull($親->edited_at);
        $this->assertNull($親->deleted_at);
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

    private function postReply(Message $親, array $入力 = []): TestResponse
    {
        return $this->actingAs($this->作成者)
            ->post(route('messages.replies.store', $親), $入力 + ['body' => '返信']);
    }
}

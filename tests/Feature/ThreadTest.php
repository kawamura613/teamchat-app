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
 * 「出ていないこと」で書く観点（決定1 の出口1-b・1-c）は、**跡地の文言が出て
 * いること**ではなく**元の本文が1文字も出ていないこと**で見る（acceptance.md 3-8）。
 * 出口ごとに独立に落ちるよう、1つのテストで兼ねない。
 *
 * T-4-20（出し分けが部品1か所に閉じている）の構造の確認は MessageTest にある。
 * ここはその振る舞い側（スレッドを通しても本文が出ない）を見る。
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

    public function test_返信件数は1件以上のときだけリンクで出る(): void
    {
        // T-5-01（screens.md 3-3）。0件では出さない。単位4 では押せない印だったが、
        // 単位5 で行き先（SC-07）ができたのでリンクにする
        $返信なし = $this->makeMessage($this->公開チャンネル, ['body' => '返信なしの本文']);

        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));
        $response->assertOk();
        $response->assertDontSee('class="thread-link"', false);

        $this->makeMessage($this->公開チャンネル, ['parent_id' => $返信なし->id, 'body' => '返信の本文']);

        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));
        $response->assertSee('<a class="thread-link" href="'.route('messages.show', $返信なし).'"', false);
        $response->assertSee('返信1件', false);
    }

    public function test_削除されたメッセージでも返信件数は消えない(): void
    {
        // T-5-02（design-guide.md 4「返信は残るので、返信件数の表示も消さない」/
        // behavior.md 1-4）。跡地にも押せるリンクが残る
        $親 = $this->makeMessage($this->公開チャンネル, [
            'body' => '消えた親の本文',
            'deleted_at' => now(),
        ]);
        $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '返信の本文']);

        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));

        $response->assertSee('このメッセージは削除されました', false);
        $response->assertSee('<a class="thread-link" href="'.route('messages.show', $親).'"', false);
        $response->assertSee('返信1件', false);
        $response->assertDontSee('消えた親の本文', false);
    }

    public function test_返信件数に削除済みの返信も入る(): void
    {
        // T-5-03（Q-23 の想定 / behavior.md 1-4）。**withTrashed() を忘れると
        // 静かに1つ減る。**スレッドを開いたときに並ぶ数と表示を一致させる
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);
        $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '生きた返信']);
        $this->makeMessage($this->公開チャンネル, [
            'parent_id' => $親->id,
            'body' => '消えた返信',
            'deleted_at' => now(),
        ]);

        $this->actingAs($this->作成者)
            ->get(route('channels.show', $this->公開チャンネル))
            ->assertSee('返信2件', false);
    }

    public function test_スレッドが開いて元のメッセージと返信が並ぶ(): void
    {
        // T-5-04・AC-5-1（screens.md 3-5 / Q-24 の想定）。それ自身の URL を持つ
        // 画面なので、同じアドレスで開き直しても同じスレッドが出る
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '元のメッセージの本文']);
        foreach (['一つめの返信', '二つめの返信', '三つめの返信'] as $分 => $本文) {
            $this->makeMessage($this->公開チャンネル, [
                'parent_id' => $親->id,
                'body' => $本文,
                'created_at' => sprintf('2026-08-17 10:%02d', 10 + $分),
            ]);
        }

        $response = $this->actingAs($this->作成者)->get(route('messages.show', $親));
        $response->assertOk();

        // 右に 400px のパネルが開く形（mockup の .app__body--thread）
        $response->assertSee('app__body--thread', false);
        $response->assertSee('<div class="t-label">スレッド</div>', false);
        // パネルの頭の所属（記号＋チャンネル名）
        $response->assertSee('<div class="t-caption"><span class="sig">#</span>開発</div>', false);
        // 元のメッセージ・区切り・返信
        $response->assertSee('元のメッセージの本文', false);
        $response->assertSee('class="thread__count">返信3件<', false);
        // 返信の投稿欄（プレースホルダは screens.md 3-5）
        $response->assertSee('placeholder="返信を送る"', false);
        // × は SC-06 の、開く前に見ていた位置へ（behavior.md 4-4）
        $response->assertSee('href="'.route('channels.show', $this->公開チャンネル->id).'#message-'.$親->id.'" title="閉じる"', false);

        // 返信は古い順に並ぶ
        $本文 = $response->getContent();
        $this->assertLessThan(mb_strpos($本文, '二つめの返信'), mb_strpos($本文, '一つめの返信'));
        $this->assertLessThan(mb_strpos($本文, '三つめの返信'), mb_strpos($本文, '二つめの返信'));
    }

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

    public function test_削除されたメッセージのスレッドも開いて本文は出ない(): void
    {
        // T-5-05・AC-5-3（決定1 の出口1-b / permissions-api.md 2-2）。
        // messages.show だけ ->withTrashed() にしてあるので 404 にならない。
        // **跡地が出ていること**ではなく**本文が出ていないこと**で見る
        $消した本文 = '消した親の本文はデータベースにだけ残る';
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => $消した本文, 'deleted_at' => now()]);
        $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '跡地への返信']);

        $response = $this->actingAs($this->作成者)->get(route('messages.show', $親));

        $response->assertOk();
        $response->assertDontSee($消した本文, false);
        $response->assertSee('このメッセージは削除されました', false);
        // 投稿者名・日時は跡地にも残る（behavior.md 1-2）
        $response->assertSee('佐藤 太郎', false);
        $response->assertSee('跡地への返信', false);
        // そのまま返信を続けられる。投稿欄は出したまま（回答6）
        $response->assertSee('placeholder="返信を送る"', false);

        // 本文はデータベースに残っている（決定1・案1）
        $this->assertSame($消した本文, Message::withTrashed()->find($親->id)->body);
    }

    public function test_削除された返信はスレッドで跡地になり本文は出ない(): void
    {
        // 出口1-c・AC-5-4（決定1）。**出口1-b とは別のテストにする。**1つで兼ねると
        // どちらが緩んだのか分からなくなる（acceptance.md 3-8）
        $消した本文 = '消した返信の本文はデータベースにだけ残る';
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);
        $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '生きた返信']);
        $消えた返信 = $this->makeMessage($this->公開チャンネル, [
            'parent_id' => $親->id,
            'body' => $消した本文,
            'deleted_at' => now(),
        ]);

        $response = $this->actingAs($this->作成者)->get(route('messages.show', $親));

        $response->assertOk();
        $response->assertDontSee($消した本文, false);
        $response->assertSee('このメッセージは削除されました', false);
        $response->assertSee('生きた返信', false);
        // 件数は減らない。並ぶ数（跡地を含めて2件）と一致する
        $response->assertSee('class="thread__count">返信2件<', false);

        $this->assertSame($消した本文, Message::withTrashed()->find($消えた返信->id)->body);
    }

    public function test_パネルの中には返信アイコンも返信件数も出ない(): void
    {
        // screens.md 3-5（2026-11-02 に足した行）。元のメッセージは行き先が
        // 自分自身のスレッドになり、返信は1段までで付けられない（回答8）
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);
        $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '返信の本文']);

        $response = $this->actingAs($this->作成者)->get(route('messages.show', $親));
        $response->assertOk();

        // 左の一覧には両方出る
        $response->assertSee('title="返信"', false);
        $response->assertSee('class="thread-link"', false);

        // パネルの中（<aside class="thread"> から後ろ）には出ない
        $本文 = $response->getContent();
        $パネル = mb_substr($本文, mb_strpos($本文, '<aside class="thread">'));
        $this->assertStringNotContainsString('title="返信"', $パネル);
        $this->assertStringNotContainsString('thread-link', $パネル);
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
        // T-5-06（回答6 / 決定1 の出口1-d）。そこで会話が続けられなくなるほうが
        // 困る、という回答に乗っている。親は withTrashed() のバインディングで
        // 解決され、MessagePolicy@reply は trashed() を見ない
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
        // 同じ数だけかける（ValidatesMessageBody 1 か所を通す）。エラーは reply の
        // 袋に入れる（SC-07 には name="body" の欄が3種類あるので、袋を共有すると
        // 別の欄の下書きが流れ込む）
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

    public function test_入力チェックに落ちた返信は同じスレッドへ戻り返信欄にエラーが出る(): void
    {
        // screens.md 4-6。エラー文は返信欄の下・文字数表示の左に出す
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);

        // 錨も付ける。無いと素で開いた扱いになり、app.js が左の一覧を末尾まで
        // 送って打っていた場所を見失う（behavior.md 4-3・4-4）
        $this->postReply($親, ['body' => ''])
            ->assertRedirect(route('messages.show', $親).'#message-'.$親->id);

        $response = $this->actingAs($this->作成者)
            ->followingRedirects()
            ->post(route('messages.replies.store', $親), ['body' => '']);

        $response->assertOk();
        $response->assertSee('<span class="err">メッセージを入力してください</span>', false);
        // 返信欄の枠が赤くなる（screens.md 4-1 / app.css の .composer__box.is-error）
        $response->assertSee('class="composer__box is-error"', false);
    }

    public function test_同じidが1枚の画面に2つ出ない(): void
    {
        // behavior.md 4-4。**SC-07 は親を左の一覧とパネルの両方に出す**ので、
        // パネルの「元のメッセージ」だけ id に thread- を付ける。同じ id が1枚に
        // 2つあると、#message-{id} の着地と :target が先に出てくる左側に当たり、
        // パネル側の文字数表示も getElementById が左を拾って動かなくなる
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '元のメッセージの本文']);
        $返信 = $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '返信の本文']);

        $本文 = $this->actingAs($this->作成者)->get(route('messages.show', $親))->getContent();

        // 親は左の一覧に1つ、パネルに1つ（接頭辞つき）
        $this->assertSame(1, mb_substr_count($本文, 'id="message-'.$親->id.'"'));
        $this->assertSame(1, mb_substr_count($本文, 'id="thread-message-'.$親->id.'"'));

        // 編集中の入力欄と、それを指す文字数表示も同じ分け方
        foreach (['id', 'data-count-for'] as $属性) {
            $this->assertSame(1, mb_substr_count($本文, $属性.'="message-body-'.$親->id.'"'));
            $this->assertSame(1, mb_substr_count($本文, $属性.'="thread-message-body-'.$親->id.'"'));
        }

        // **返信には接頭辞を付けない。**返信はパネルにしか出ないので重複せず、
        // 付けると投稿後の /messages/{親のID}#message-{新しい返信のID}（4-3）の
        // 着地が外れる
        $this->assertSame(1, mb_substr_count($本文, 'id="message-'.$返信->id.'"'));
        $this->assertSame(0, mb_substr_count($本文, 'id="thread-message-'.$返信->id.'"'));
    }

    // 消す

    public function test_スレッドから元のメッセージを編集しても画面はスレッドのまま(): void
    {
        // behavior.md 4-2（4-3 を正とした）。返信でないメッセージを SC-07 の中で
        // 編集したとき、保存が通れば SC-07 に戻るのに**落ちたときだけ SC-06 に
        // 出る**という非対称を塞ぐ。出す画面は from で決める
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '元のメッセージの本文']);
        $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '返信の本文']);

        // from=thread なら、返信でなくてもパネルは開いたまま
        $this->actingAs($this->作成者)
            ->get(route('messages.edit', [$親, 'from' => 'thread']))
            ->assertSee('app__body--thread', false);

        // from が無ければ SC-06（返信でないメッセージの既定）
        $this->actingAs($this->作成者)
            ->get(route('messages.edit', $親))
            ->assertDontSee('app__body--thread', false);

        // 落ちたときは from と錨を引き継いで messages.edit へ戻す
        $this->actingAs($this->作成者)
            ->patch(route('messages.update', $親), ['body' => '   ', 'from' => 'thread'])
            ->assertRedirect(route('messages.edit', [$親, 'from' => 'thread']).'#message-'.$親->id);

        // 追って開くと、まだスレッドのまま編集中（ここが非対称だった）
        $this->actingAs($this->作成者)
            ->followingRedirects()
            ->patch(route('messages.update', $親), ['body' => '   ', 'from' => 'thread'])
            ->assertSee('app__body--thread', false);

        // 保存が通ったときも同じスレッドへ戻る
        $this->actingAs($this->作成者)
            ->patch(route('messages.update', $親), ['body' => '直した本文', 'from' => 'thread'])
            ->assertRedirect(route('messages.show', $親->id).'#message-'.$親->id);

        $this->assertSame('直した本文', $親->fresh()->body);
    }

    public function test_自分の返信は直せて他人の返信には操作が出ない(): void
    {
        // T-5-11（Q-25 の想定 / screens.md 3-5）。返信もメッセージと同じ扱いで、
        // 「編集済み」も跡地も同じように出る。単位4 では3本そろって 404 だった
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);
        $自分の返信 = $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '自分の返信']);
        $他人の返信 = $this->makeMessage($this->公開チャンネル, [
            'parent_id' => $親->id,
            'body' => '他人の返信',
            'user_id' => $this->見られる人->id,
        ]);

        // 返信の編集中は SC-07 の中の状態。SC番号は増えない（behavior.md 4-2）
        $編集中 = $this->actingAs($this->作成者)->get(route('messages.edit', $自分の返信));
        $編集中->assertOk();
        $編集中->assertSee('app__body--thread', false);
        $編集中->assertSee('id="message-body-'.$自分の返信->id.'"', false);

        $this->actingAs($this->作成者)
            ->patch(route('messages.update', $自分の返信), ['body' => '直した返信', 'from' => 'thread'])
            ->assertRedirect(route('messages.show', $親->id).'#message-'.$自分の返信->id);

        $自分の返信->refresh();
        $this->assertSame('直した返信', $自分の返信->body);
        $this->assertNotNull($自分の返信->edited_at);

        // 他人の返信は 403（そのメッセージは本人のパネルに並んでいる）
        $this->actingAs($this->作成者)->get(route('messages.edit', $他人の返信))->assertForbidden();
        $this->actingAs($this->作成者)->patch(route('messages.update', $他人の返信), ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->作成者)->delete(route('messages.destroy', $他人の返信))->assertForbidden();

        // 画面にも他人の返信の操作は出ない（screens.md 4-9）
        $response = $this->actingAs($this->作成者)->get(route('messages.show', $親));
        $response->assertDontSee(route('messages.edit', $他人の返信), false);
        $response->assertDontSee('action="'.route('messages.destroy', $他人の返信).'"', false);
        $response->assertSee(route('messages.edit', $自分の返信), false);

        // 自分の返信は削除できて、跡地になる
        $this->actingAs($this->作成者)
            ->delete(route('messages.destroy', $自分の返信), ['from' => 'thread'])
            ->assertRedirect(route('messages.show', $親->id).'#message-'.$自分の返信->id);

        $this->assertNotNull($自分の返信->fresh()->deleted_at);
        $this->assertSame('他人の返信', $他人の返信->fresh()->body);
    }

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

    public function test_スレッドでもサイドバーのそのチャンネルが選択中になる(): void
    {
        // permissions-api.md 1-6（2026-11-02 の追記）。messages.show には
        // {channel} が無いので、{message} が属するチャンネルを見ないと
        // 選択中の行が消える。判定は AppServiceProvider の View Composer
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親の本文']);

        $this->actingAs($this->作成者)
            ->get(route('messages.show', $親))
            ->assertSee('class="side-item is-selected" href="'.route('channels.show', $this->公開チャンネル).'"', false);
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

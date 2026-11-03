<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\TestCase;

/**
 * 単位7「公開API」（F-21・F-22 ＋ F-05 の △5）。
 *
 * 観点は acceptance.md 3-7 の T-7-01〜T-7-16。**区切りコメントで 4 つに分け、
 * その中で T 番号の順に並べる**（番号順そのままだと、取れる／出さない／弾くが
 * 1 件ずつ入れ替わって読みにくくなるため）。
 *
 * **出口3 は出口1・出口2 と兼ねない**（acceptance.md 3-8）。T-7-05（許可リスト）と
 * T-7-06（削除済みが出ない）も別々に落ちるように分けてある。片方で兼ねると、
 * どちらが緩んだのか分からなくなる。
 *
 * **公開API はログインを見ない**（permissions-api.md 1-3）。だから既定では
 * actingAs を使わず、「誰が呼んでも同じ」を見る T-7-02・T-7-03 でだけ、
 * わざとログインした状態で叩く。
 */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    private User $作成者;

    private User $メンバー;

    private User $メンバーでない人;

    private Channel $公開チャンネル;

    private Channel $プライベートチャンネル;

    protected function setUp(): void
    {
        parent::setUp();

        $this->作成者 = User::create(['name' => '佐藤 太郎', 'email' => 'sato@example.com', 'password' => 'password']);
        $this->メンバー = User::create(['name' => '鈴木 花子', 'email' => 'suzuki@example.com', 'password' => 'password']);
        $this->メンバーでない人 = User::create(['name' => '高橋 健', 'email' => 'takahashi@example.com', 'password' => 'password']);

        $this->公開チャンネル = $this->makeChannel(['name' => '開発', 'description' => '開発に関する相談と報告']);
        $this->プライベートチャンネル = $this->makeChannel([
            'name' => '採用プロジェクト',
            'description' => '採用まわりの進行',
            'is_private' => true,
        ]);
        $this->プライベートチャンネル->members()->attach([$this->作成者->id, $this->メンバー->id]);
    }

    // 取れる

    public function test_ログインも鍵もなしに2本とも叩ける(): void
    {
        // T-7-01（仕様書 3-7 / permissions-api.md 3-1）。トークンも要らない
        $this->makeMessage($this->公開チャンネル);

        $this->getJson(route('api.channels.index'))->assertOk();
        $this->getJson($this->メッセージの経路($this->公開チャンネル))->assertOk();
    }

    public function test_返信は一覧に混ざらず件数だけで表される(): void
    {
        // T-7-07（permissions-api.md 3-3 / Q-31 の想定）
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親のメッセージ']);
        $返信 = $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '返信の本文']);

        $response = $this->getJson($this->メッセージの経路($this->公開チャンネル));

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $親->id);
        $response->assertJsonPath('data.0.reply_count', 1);
        // 返信そのものは data に並ばない
        $this->assertStringNotContainsString('返信の本文', $response->getContent());
        $this->assertNotContains($返信->id, array_column($response->json('data'), 'id'));
    }

    public function test_編集済みかどうかが真偽値で返る(): void
    {
        // T-7-02 の「編集済みかどうか」（permissions-api.md 3-3 / Q-17 の想定）。
        // **真と偽の両方を見る。**キーの有無だけ見ていると、`!==` を `===` に
        // 書き換えても気づけない。いつ直したか（edited_at）は返さない
        $直したもの = $this->makeMessage($this->公開チャンネル, [
            'body' => '直した本文',
            'created_at' => now()->subHour(),
            'edited_at' => now(),
        ]);
        $直していないもの = $this->makeMessage($this->公開チャンネル, [
            'body' => '直していない本文',
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->getJson($this->メッセージの経路($this->公開チャンネル));

        $並び = array_column($response->json('data'), 'edited', 'id');
        $this->assertTrue($並び[$直したもの->id]);
        $this->assertFalse($並び[$直していないもの->id]);
    }

    public function test_日時は8601の形でプラス9時間が付く(): void
    {
        // T-7-09（permissions-api.md 3-1・4-1 の #4。config/app.php の Asia/Tokyo）
        $this->makeMessage($this->公開チャンネル);

        $形 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+09:00$/';

        $this->assertMatchesRegularExpression(
            $形,
            $this->getJson(route('api.channels.index'))->json('data.0.created_at')
        );
        $this->assertMatchesRegularExpression(
            $形,
            $this->getJson($this->メッセージの経路($this->公開チャンネル))->json('data.0.posted_at')
        );
    }

    public function test_日本語はエスケープされずそのまま返る(): void
    {
        // T-7-10（permissions-api.md 3-1）。既定だと "開発" の形になり、
        // 社内ポータル側で目視確認できない
        $response = $this->getJson(route('api.channels.index'));

        $this->assertStringContainsString('開発', $response->getContent());
        // バックスラッシュ＋u の実体が 1 つも無いこと。chr(92) で書くのは、
        // ソースに書いた "\u…" が別の層で文字に展開されるのを避けるため
        $this->assertStringNotContainsString(chr(92).'u', $response->getContent());
    }

    public function test_1回50件で次のページが取れ範囲外は空の配列になる(): void
    {
        // T-7-11（permissions-api.md 3-1・3-2 / Q-32 の想定）
        foreach (range(1, 51) as $番) {
            $this->makeMessage($this->公開チャンネル, [
                'body' => "本文の{$番}番目",
                'created_at' => now()->subMinutes(100 - $番),
            ]);
        }

        $最初のページ = $this->getJson($this->メッセージの経路($this->公開チャンネル));
        $最初のページ->assertJsonCount(50, 'data');
        $最初のページ->assertJsonPath('meta.per_page', 50);
        $最初のページ->assertJsonPath('meta.total', 51);

        $this->getJson($this->メッセージの経路($this->公開チャンネル).'?page=2')
            ->assertJsonCount(1, 'data');

        // 範囲の外はエラーにせず、空の配列を返す（3-2）
        $範囲外 = $this->getJson($this->メッセージの経路($this->公開チャンネル).'?page=99');
        $範囲外->assertOk();
        $this->assertSame([], $範囲外->json('data'));
    }

    public function test_並びは新しい順で画面とは逆向き(): void
    {
        // T-7-12（permissions-api.md 3-3 / Q-32 の想定。**#8 は確認中**。
        // 画面（SC-06）は古いものが上なので、覆ったら latest() の 1 行で戻す）
        $古い = $this->makeMessage($this->公開チャンネル, ['created_at' => now()->subHours(2)]);
        $新しい = $this->makeMessage($this->公開チャンネル, ['created_at' => now()]);

        $response = $this->getJson($this->メッセージの経路($this->公開チャンネル));

        $this->assertSame([$新しい->id, $古い->id], array_column($response->json('data'), 'id'));
    }

    // 出さない

    public function test_誰が呼んでもプライベートチャンネルは一覧に出ない(): void
    {
        // T-7-02（仕様書 3-7 / 回答1 / permissions-api.md 1-3。F-21 は呼ぶ相手を
        // 判定に使わない）。**作成者やメンバーがログインしたままでも出さない**。
        // メンバーでない人も入れる（.claude/rules/testing.md の 3 人）
        foreach ([null, $this->作成者, $this->メンバー, $this->メンバーでない人] as $呼ぶ人) {
            $response = $this->叩く($呼ぶ人, route('api.channels.index', [], false));

            $response->assertOk();
            $response->assertJsonCount(1, 'data');
            // 名前も、件数の数にも現れない
            $response->assertJsonPath('meta.total', 1);
            $this->assertStringNotContainsString('採用プロジェクト', $response->getContent());
            $this->assertStringNotContainsString('採用まわりの進行', $response->getContent());
        }
    }

    public function test_返す列は許可リストのとおりで余分な列が出ない(): void
    {
        // T-7-05（permissions-api.md 3-4 / 決定1 の出口3）。
        // **「返す約束のない列が無いこと」で確かめる。**あとから列が増えたときに
        // 黙って漏れないようにするため
        $this->makeMessage($this->公開チャンネル);

        $チャンネル = $this->getJson(route('api.channels.index'));
        $this->assertSame(
            ['id', 'name', 'description', 'created_by', 'created_at'],
            array_keys($チャンネル->json('data.0'))
        );
        $this->assertSame(['name'], array_keys($チャンネル->json('data.0.created_by')));

        $メッセージ = $this->getJson($this->メッセージの経路($this->公開チャンネル));
        $this->assertSame(
            ['id', 'body', 'user', 'posted_at', 'edited', 'reply_count'],
            array_keys($メッセージ->json('data.0'))
        );
        $this->assertSame(['name'], array_keys($メッセージ->json('data.0.user')));

        foreach (['email', 'user_id', 'deleted_at', 'edited_at', 'updated_at', 'is_private', 'channel_id', 'parent_id', 'created_by_id'] as $列) {
            $this->assertStringNotContainsString($列, $チャンネル->getContent());
            $this->assertStringNotContainsString($列, $メッセージ->getContent());
        }

        // メールアドレスそのものが本文に出ていないことも見る
        $this->assertStringNotContainsString('sato@example.com', $チャンネル->getContent());
        $this->assertStringNotContainsString('sato@example.com', $メッセージ->getContent());
    }

    public function test_削除済みのメッセージはidも本文も出ない(): void
    {
        // T-7-06（回答11 / 決定1 の出口3）。問い合わせに withTrashed() を書かない。
        // **元の本文が1文字も出ていないこと**で見る（acceptance.md 3-8）
        $this->makeMessage($this->公開チャンネル, ['body' => '生きている本文']);
        $消した = $this->makeMessage($this->公開チャンネル, [
            'body' => 'けした本文のめじるし',
            'deleted_at' => now(),
        ]);

        $response = $this->getJson($this->メッセージの経路($this->公開チャンネル));

        $response->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString('けした', $response->getContent());
        $this->assertStringNotContainsString('めじるし', $response->getContent());
        $this->assertNotContains($消した->id, array_column($response->json('data'), 'id'));
        // 跡地の文言も出さない（画面側の出口1 とは向きが逆）
        $this->assertStringNotContainsString('削除されました', $response->getContent());
    }

    public function test_件数には削除済みの返信を含めない(): void
    {
        // T-7-08（behavior.md 1-4 / 回答11。**画面の「返信◯件」とは向きが逆**）。
        // 画面用の Message::replyCount() を使っていたらここで落ちる
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親のメッセージ']);
        $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id, 'body' => '生きている返信']);
        $this->makeMessage($this->公開チャンネル, [
            'parent_id' => $親->id,
            'body' => 'けした返信',
            'deleted_at' => now(),
        ]);

        $response = $this->getJson($this->メッセージの経路($this->公開チャンネル));

        $response->assertJsonPath('data.0.reply_count', 1);

        // 画面側は同じ親を 2 件と数える（向きが分かれていることの確認）
        $画面 = Message::withCount(Message::replyCount())->find($親->id);
        $this->assertSame(2, $画面->replies_count);
    }

    // 弾く

    public function test_プライベートチャンネルのidは無いときと同じ404になる(): void
    {
        // T-7-03（仕様書 3-7 / 回答1 / permissions-api.md 1-4 の △5・3-5）。
        // **文言を言い分けた瞬間に、プライベートチャンネルがあることが漏れる**
        $無いid = $this->プライベートチャンネル->id + 999;

        foreach ([null, $this->作成者, $this->メンバー, $this->メンバーでない人] as $呼ぶ人) {
            $プライベート = $this->叩く($呼ぶ人, $this->メッセージの経路($this->プライベートチャンネル));
            $存在しない = $this->叩く($呼ぶ人, '/api/channels/'.$無いid.'/messages');

            $プライベート->assertNotFound();
            $存在しない->assertNotFound();
            $プライベート->assertExactJson(['message' => '指定されたチャンネルは見つかりません']);
            // **まったく同じ応答であること**
            $this->assertSame($存在しない->getContent(), $プライベート->getContent());
        }
    }

    public function test_404にモデルのクラス名が漏れない(): void
    {
        // T-7-04（behavior.md 3-4）。Laravel の既定は
        // No query results for model [App\Models\Channel] 9
        //
        // **ブラウザのアドレス欄から開いた形（Accept は html）で叩く。**
        // acceptance.md 2-7 がその確かめ方なので、Handler の判定を
        // expectsJson() にしているとここで HTML が返って落ちる
        $response = $this->get($this->メッセージの経路($this->プライベートチャンネル));

        $response->assertNotFound();
        $response->assertHeader('content-type', 'application/json');
        $response->assertExactJson(['message' => '指定されたチャンネルは見つかりません']);
        $this->assertStringNotContainsString('No query results', $response->getContent());
        $this->assertStringNotContainsString('App\Models', $response->getContent());
    }

    public function test_数字でないidはアドレスが無いほうの文言になる(): void
    {
        // ルートの whereNumber で落ちる（permissions-api.md 3-5 の
        // 「そのURLのAPIがない」）。チャンネルを探す前なので文言が分かれる
        $response = $this->getJson('/api/channels/abc/messages');

        $response->assertNotFound();
        $response->assertExactJson(['message' => '指定されたURLは見つかりません']);
    }

    public function test_書き込みの3つは405で読み取り専用と返る(): void
    {
        // T-7-13（仕様書 3-7 / behavior.md 3-4）。GET しか定義していないので
        // Laravel が MethodNotAllowedHttpException にする。
        // **引数を取る messages のほうも見る。**Route::any で登録されても
        // 気づけるようにしておく（一覧だけ見ていると素通りする）
        $経路 = [
            route('api.channels.index', [], false),
            $this->メッセージの経路($this->公開チャンネル),
        ];

        foreach ($経路 as $それぞれ) {
            foreach (['postJson', 'patchJson', 'deleteJson'] as $やり方) {
                $response = $this->{$やり方}($それぞれ);

                $response->assertStatus(405);
                $response->assertExactJson(['message' => 'このAPIは読み取り専用です']);
                // 405 は Allow を返さないといけない（RFC 7231 6.5.5）。
                // 例外が持つヘッダーを落としていないことの確認（behavior.md 3-4）
                $response->assertHeader('allow', 'GET, HEAD');
            }
        }
    }

    public function test_60回を超えると429で日本語の文言が返る(): void
    {
        // T-7-14（permissions-api.md 3-1・3-5。RouteServiceProvider の
        // Limit::perMinute(60)）。既定の英語 Too Many Attempts. から差し替わっている
        foreach (range(1, 60) as $回) {
            $this->getJson(route('api.channels.index'))->assertOk();
        }

        $response = $this->getJson(route('api.channels.index'));

        $response->assertStatus(429);
        $response->assertExactJson(['message' => 'リクエストが多すぎます。しばらくしてからお試しください']);
        $this->assertStringNotContainsString('Too Many', $response->getContent());
        // 再試行の時期が分かるヘッダーを落としていないこと（behavior.md 3-4）。
        // これが無いと、ポータル側はいつ叩き直せるか決められない
        $response->assertHeader('retry-after');
        $response->assertHeader('x-ratelimit-limit', 60);
    }

    public function test_500で例外の中身が漏れない(): void
    {
        // T-7-15（behavior.md 3-1・3-4）。アプリ側にテスト用のルートを足さず、
        // ここで api ミドルウェア付きの使い捨てルートを 1 本だけ定義する。
        //
        // **APP_DEBUG が true のほうも見る。**Handler は debug の値で出し分けない
        // と決めている（.env.example は APP_DEBUG=true のまま配っているので、
        // true のほうが素の状態）。false だけ見ていると、将来 debug で分岐を
        // 足されても気づけない
        Route::middleware('api')->get('/api/テスト用に落とす', function () {
            throw new RuntimeException('秘密の中身とスタックトレース');
        });

        foreach ([false, true] as $デバッグ) {
            config(['app.debug' => $デバッグ]);

            $response = $this->getJson('/api/テスト用に落とす');

            $response->assertStatus(500);
            $response->assertExactJson(['message' => 'サーバ側で問題が発生しました']);
            $this->assertStringNotContainsString('秘密の中身', $response->getContent());
            $this->assertStringNotContainsString('RuntimeException', $response->getContent());
            $this->assertStringNotContainsString('ApiTest.php', $response->getContent());
        }
    }

    public function test_表に無いエラーは元のステータスを保つ(): void
    {
        // behavior.md 3-4 の「上の表に無い HttpException は、元のステータスを
        // 保つ」。500 に畳むと、保守モードの 503 が 500 になり Retry-After も落ちる
        Route::middleware('api')->get('/api/テスト用に止める', function () {
            throw new ServiceUnavailableHttpException(120);
        });

        $response = $this->getJson('/api/テスト用に止める');

        $response->assertStatus(503);
        $response->assertExactJson(['message' => 'ただいまご利用いただけません。しばらくしてからお試しください']);
        $response->assertHeader('retry-after', 120);
    }

    // 変わっていない

    public function test_初期状態のユーザー取得の経路が残っていない(): void
    {
        // T-7-16（permissions-api.md 2-4）。Laravel 初期状態の
        // GET /user（auth:sanctum）を消したままにする。
        // personal_access_tokens を使わない決め（data.md 2-6）
        $残っている経路 = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => $uri === 'api/user');

        $this->assertTrue($残っている経路->isEmpty());

        $this->getJson('/api/user')
            ->assertNotFound()
            ->assertExactJson(['message' => '指定されたURLは見つかりません']);
    }

    private function メッセージの経路(Channel $channel): string
    {
        return route('api.channels.messages.index', $channel, false);
    }

    /**
     * 「誰が呼んでも同じ」を見るための包み。
     *
     * **null のときは必ずログアウトした状態にする。**actingAs() はテストの中で
     * 残るので、null の回に actingAs を省くだけだと、前の回のログインが効いた
     * まま「未ログインのつもり」の検証になる（配列の順を変えると静かに壊れる）。
     */
    private function 叩く(?User $呼ぶ人, string $経路): TestResponse
    {
        if ($呼ぶ人 === null) {
            $this->app['auth']->forgetGuards();

            return $this->getJson($経路);
        }

        return $this->actingAs($呼ぶ人)->getJson($経路);
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
}

<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 単位6「検索」（F-20 ＋ F-05）。
 *
 * 並びは acceptance.md 3-6 のテスト観点（T-6-01〜T-6-14）の番号順に合わせる。
 * 区切りコメントは番号順を崩さない位置に置いてある。
 *
 * 「出ていないこと」で書く観点（決定1 の出口2）は、**跡地の文言が出ていること**
 * ではなく**元の本文が1文字も出ていないこと**で見る（acceptance.md 3-8）。
 * **出口2 は出口1・出口3 と兼ねない。**兼ねるとどこが緩んだのか分からなくなる。
 *
 * 本文は抜粋のときに一致部分が `<mark>` で割られるので、「本文が出ている／出て
 * いない」は**キーワードを含まない目印の語**で見る（`ひそかな`・`ふつうのめも` など）。
 */
class SearchTest extends TestCase
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

    public function test_キーワードが無いときはまだ検索していませんが出る(): void
    {
        // T-6-01（questions.md 決定9 / screens.md 4-8）。**「無い」と「空」を分ける。**
        // 上部バーの検索窓を押しただけの人・URL を直接開いた人にエラー文を見せない
        $response = $this->search($this->作成者);

        $response->assertOk();
        $response->assertSee('まだ検索していません', false);
        $response->assertSee('上の欄にキーワードを入れて「検索」を押してください。', false);
        $response->assertDontSee('キーワードを入力してください', false);
    }

    public function test_キーワードが空で送られたときだけエラー文が出る(): void
    {
        // T-6-01。空のときは 302 で SC-08 へ戻し、欄の下にエラー文（behavior.md 3-2）。
        // 戻り先では空状態の枠を出さない（screens.md 3-5）
        $response = $this->search($this->作成者, ['q' => '']);

        $response->assertRedirect(route('search'));
        $response->assertSessionHasErrors(['q' => 'キーワードを入力してください']);

        $戻り先 = $this->actingAs($this->作成者)->get(route('search'));
        $戻り先->assertSee('<span class="err">キーワードを入力してください</span>', false);
        $戻り先->assertDontSee('まだ検索していません', false);
    }

    public function test_キーワードは1000文字まで通り1001文字で弾かれる(): void
    {
        // T-6-01（screens.md 4-8 の上限。messages.body と同じ 1,000 文字）
        $this->search($this->作成者, ['q' => str_repeat('あ', 1000)])->assertOk();

        $response = $this->search($this->作成者, ['q' => str_repeat('あ', 1001)]);

        $response->assertRedirect(route('search'));
        $response->assertSessionHasErrors(['q' => 'キーワードは1,000文字以内で入力してください']);
    }

    public function test_0件はエラーではなく空状態で文言にキーワードが入る(): void
    {
        // T-6-02（screens.md 3-5 / search.html の原文）
        $this->makeMessage($this->公開チャンネル, ['body' => '9月リリースの日程を確定させたいです']);

        $response = $this->search($this->作成者, ['q' => '議事録']);

        $response->assertOk();
        $response->assertSee('「議事録」に一致するメッセージはありません', false);
        $response->assertSee('別のキーワードで試すか、キーワードを短くしてください。', false);
        $response->assertDontSee('class="rows"', false);
    }

    public function test_検索したキーワードが検索欄に残る(): void
    {
        // T-6-03（screens.md 3-5。search.html の検索欄に「リリース」が入っている）。
        // 上部バーの検索窓には残さない（screens.md 3-1 はプレースホルダだけ）
        $this->makeMessage($this->公開チャンネル, ['body' => '9月リリースの日程']);

        $response = $this->search($this->作成者, ['q' => 'リリース']);

        $response->assertSee('name="q" value="リリース"', false);
    }

    // 当てる

    public function test_当たるのは本文だけでチャンネル名や投稿者名では当たらない(): void
    {
        // T-6-04（回答10）。チャンネル名「開発」・説明「開発に関する相談と報告」・
        // 投稿者名「佐藤 太郎」はどれも対象外
        $this->makeMessage($this->公開チャンネル, ['body' => 'きょうは晴れです']);

        $this->search($this->作成者, ['q' => '開発'])->assertSee('に一致するメッセージはありません', false);
        $this->search($this->作成者, ['q' => '相談と報告'])->assertSee('に一致するメッセージはありません', false);
        $this->search($this->作成者, ['q' => '佐藤'])->assertSee('に一致するメッセージはありません', false);
    }

    public function test_返信も対象に含まれ行からそのスレッドへ行ける(): void
    {
        // T-6-05（Q-28・Q-29 の想定 / behavior.md 4-4 の着地位置）。
        // メッセージは SC-06 のそのアンカーへ、返信は親のスレッドへ送る
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '親のリリース予定']);
        $返信 = $this->makeMessage($this->公開チャンネル, [
            'parent_id' => $親->id,
            'body' => '返信のリリース予定',
        ]);

        $response = $this->search($this->作成者, ['q' => 'リリース']);

        $response->assertSee('の検索結果　2件', false);
        $response->assertSee('href="'.route('channels.show', $this->公開チャンネル).'#message-'.$親->id.'"', false);
        $response->assertSee('href="'.route('messages.show', $親).'#message-'.$返信->id.'"', false);
    }

    public function test_部分一致でアンド検索はせず大文字小文字を区別しない(): void
    {
        // T-6-06（決定10・仮 / data.md 4-4）
        $this->makeMessage($this->公開チャンネル, ['body' => '来週のリリース判定会議です']);
        $this->makeMessage($this->公開チャンネル, ['body' => 'Release Note を書きました']);

        // 語の途中でも当たる（部分一致）
        $this->search($this->作成者, ['q' => 'ース判'])->assertSee('の検索結果　1件', false);
        // スペースを含む入力は1つの語として扱う（アンド検索にしない）
        $this->search($this->作成者, ['q' => 'リリース 会議'])->assertSee('に一致するメッセージはありません', false);
        // 大文字小文字を区別しない
        $this->search($this->作成者, ['q' => 'release note'])->assertSee('の検索結果　1件', false);
    }

    // 出さない

    public function test_見られないプライベートチャンネルのメッセージは出ない(): void
    {
        // T-6-07（仕様書 3-6 / 回答1 / permissions-api.md 1-6）。
        // **チャンネル名も本文も出さない**
        $this->makeMessage($this->プライベートチャンネル, ['body' => 'ひそかなリリースの話']);

        $メンバー = $this->search($this->見られる人, ['q' => 'リリース']);
        $メンバー->assertSee('ひそかな', false);
        $メンバー->assertSee('採用プロジェクト', false);

        $response = $this->search($this->見られない人, ['q' => 'リリース']);
        $response->assertOk();
        $response->assertSee('に一致するメッセージはありません', false);
        $response->assertDontSee('ひそかな', false);
        $response->assertDontSee('採用プロジェクト', false);
    }

    public function test_メンバーから外されると結果から消える(): void
    {
        // T-6-08（Q-15 の想定）。書いたものは残るが、外された人の結果からは消える
        $this->makeMessage($this->プライベートチャンネル, ['body' => 'ひそかなリリースの話']);

        $this->search($this->見られる人, ['q' => 'リリース'])->assertSee('ひそかな', false);

        $this->プライベートチャンネル->members()->detach($this->見られる人);

        $response = $this->search($this->見られる人, ['q' => 'リリース']);
        $response->assertSee('に一致するメッセージはありません', false);
        $response->assertDontSee('ひそかな', false);
    }

    public function test_削除済みの本文は結果にも抜粋にも出ない(): void
    {
        // T-6-09（決定1 の出口2-a・2-b / behavior.md 3-6）。検索の問い合わせに
        // withTrashed() を書かない。**元の本文が1文字も出ていないこと**で見る
        $親 = $this->makeMessage($this->公開チャンネル, ['body' => '生きている親のめも']);
        $this->makeMessage($this->公開チャンネル, [
            'body' => 'けしたリリースのひとつめ',
            'deleted_at' => now(),
        ]);
        $this->makeMessage($this->公開チャンネル, [
            'parent_id' => $親->id,
            'body' => 'けしたリリースのふたつめ',
            'deleted_at' => now(),
        ]);

        $response = $this->search($this->作成者, ['q' => 'リリース']);

        $response->assertOk();
        $response->assertSee('に一致するメッセージはありません', false);
        $response->assertDontSee('けした', false);
        $response->assertDontSee('ひとつめ', false);
        $response->assertDontSee('ふたつめ', false);
        // 跡地の文言も出さない（検索は「取ってこない」側・出口1 とは向きが逆）
        $response->assertDontSee('このメッセージは削除されました', false);
    }

    public function test_生きている側と削除済みが同じ語を含むとき件数は生きている側と一致する(): void
    {
        // T-6-10（決定1 の出口2-c）。件数は並べた行数（screens.md 3-5）
        $this->makeMessage($this->公開チャンネル, ['body' => 'のこるリリースのいち']);
        $this->makeMessage($this->公開チャンネル, ['body' => 'のこるリリースのに']);
        $this->makeMessage($this->公開チャンネル, ['body' => 'きえるリリースのさん', 'deleted_at' => now()]);

        $response = $this->search($this->作成者, ['q' => 'リリース']);

        $response->assertSee('の検索結果　2件', false);
        $response->assertDontSee('きえる', false);
    }

    // 見せ方と並び

    public function test_抜粋は最初の一致を中心に切られ窓の中の一致はすべて強調される(): void
    {
        // T-6-11（Q-29 の想定 / screens.md 3-5）。幅は 100 文字で、キーワードの
        // 4 文字を除いた 96 文字を前後に 48 文字ずつ割る。両側を切ったので「…」が付く
        $this->makeMessage($this->公開チャンネル, [
            'body' => str_repeat('前', 300).'リリース'.str_repeat('後', 300),
        ]);

        $response = $this->search($this->作成者, ['q' => 'リリース']);

        $response->assertSee(
            '…'.str_repeat('前', 48).'<mark>リリース</mark>'.str_repeat('後', 48).'…',
            false
        );
        // 300 文字の前置きが丸ごと出ることはない
        $response->assertDontSee(str_repeat('前', 101), false);
    }

    public function test_切っていない抜粋には省略記号が付かない(): void
    {
        // T-6-11 の裏。100 文字に収まる本文は切らないので「…」を付けず、
        // **窓の中の一致はすべて**強調する
        $this->makeMessage($this->公開チャンネル, ['body' => 'リリースとリリース']);

        $response = $this->search($this->作成者, ['q' => 'リリース']);

        $response->assertSee('<div class="t-body"><mark>リリース</mark>と<mark>リリース</mark></div>', false);
        $response->assertDontSee('…', false);
    }

    public function test_並びは新しい順で上から50件まで(): void
    {
        // T-6-12（Q-27 の想定 / screens.md 3-5）
        foreach (range(1, 51) as $番) {
            $this->makeMessage($this->公開チャンネル, [
                'body' => "リリースの{$番}番目",
                'created_at' => now()->subMinutes(100 - $番),
            ]);
        }

        $response = $this->search($this->作成者, ['q' => 'リリース']);

        $response->assertSee('の検索結果　50件', false);
        // いちばん新しい 51 番目は出て、いちばん古い 1 番目は 50 件から落ちる
        $response->assertSee('の51番目', false);
        $response->assertDontSee('の1番目', false);
    }

    public function test_検索のアドレスを直接開いても同じ結果になる(): void
    {
        // T-6-13（permissions-api.md 2-2。GET なので URL を人に送れる）
        $this->makeMessage($this->公開チャンネル, ['body' => 'リリースのめも']);

        $response = $this->actingAs($this->作成者)->get('/search?q='.urlencode('リリース'));

        $response->assertOk();
        $response->assertSee('の検索結果　1件', false);
        $response->assertSee('<mark>リリース</mark>', false);
        $response->assertSee('name="q" value="リリース"', false);
    }

    public function test_記号を含むキーワードは文字として扱われる(): void
    {
        // T-6-14（決定10・仮）。`%`・`_`・`\` をエスケープする
        $this->makeMessage($this->公開チャンネル, ['body' => '達成率は100%です']);
        $this->makeMessage($this->公開チャンネル, ['body' => 'ふつうのめも']);

        // `%` だけで全件が当たらない
        $記号 = $this->search($this->作成者, ['q' => '%']);
        $記号->assertSee('の検索結果　1件', false);
        $記号->assertDontSee('ふつうのめも', false);

        // `_` も `\` も文字として扱うので、100% には当たらない
        $this->search($this->作成者, ['q' => '10_%'])->assertSee('に一致するメッセージはありません', false);
        $this->search($this->作成者, ['q' => '\\'])->assertSee('に一致するメッセージはありません', false);
    }

    private function search(User $user, array $クエリ = []): TestResponse
    {
        return $this->actingAs($user)->get(route('search', $クエリ));
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
     * 1行そのまま作る。created_at・deleted_at は fillable に入れていないので、
     * forceFill で入れる（要るテストだけが差分で渡す）。
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

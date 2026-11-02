<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 単位3「メンバー管理」（F-10・F-11・F-12 ＋ F-05 の △1・△2）。
 *
 * 並びは acceptance.md 3-3 のテスト観点（T-3-01〜T-3-12）に合わせる。
 *
 * T-3-09（外された人が自分の過去のメッセージを編集・削除できず 404）は、
 * 単位4 の MessageTest::test_外された人は自分の過去のメッセージも直せず404になる
 * が固定しているので、ここでは重ねない。
 */
class ChannelMemberTest extends TestCase
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

        $this->公開チャンネル = $this->makeChannel(['name' => '開発']);
        $this->プライベートチャンネル = $this->makeChannel([
            'name' => '採用プロジェクト',
            'description' => '採用まわりの進行',
            'is_private' => true,
        ]);
        $this->プライベートチャンネル->members()->attach([$this->作成者->id, $this->メンバー->id]);
    }

    // 見る

    public function test_一覧の並びは作成者が先頭であとは追加された順(): void
    {
        // T-3-05（screens.md 3-4 / data.md 2-3）。channel_user は日時の列を
        // 持たないので、追加された順は中間テーブルの id の順
        $あとから = $this->makeChannel(['name' => 'あとから', 'is_private' => true]);
        // 作成者をあえて最後に入れる。id の順だけだと作成者が末尾になる
        $あとから->members()->attach([$this->メンバー->id, $this->メンバーでない人->id, $this->作成者->id]);

        $response = $this->actingAs($this->作成者)->get(route('channels.members.index', $あとから));
        $response->assertOk();

        $本文 = $response->getContent();
        $this->assertLessThan(mb_strpos($本文, '鈴木 花子'), mb_strpos($本文, '佐藤 太郎'));
        $this->assertLessThan(mb_strpos($本文, '高橋 健'), mb_strpos($本文, '鈴木 花子'));
    }

    public function test_人数の表示が行数と一致する(): void
    {
        // T-3-06（screens.md 3-4）。作成者の行も数に入る
        $response = $this->actingAs($this->作成者)->get(route('channels.members.index', $this->プライベートチャンネル));

        $response->assertSee('メンバー　2人', false);
        $this->assertSame(2, mb_substr_count($response->getContent(), 'class="row"'));

        // 1 人増やすと表示も行数も増える
        $this->プライベートチャンネル->members()->attach($this->メンバーでない人);
        $response = $this->actingAs($this->作成者)->get(route('channels.members.index', $this->プライベートチャンネル));

        $response->assertSee('メンバー　3人', false);
        $this->assertSame(3, mb_substr_count($response->getContent(), 'class="row"'));
    }

    public function test_作成者の行には印が付いて外すが押せない状態で残る(): void
    {
        // T-3-07 の画面側（screens.md 3-4 / 回答4）。非表示にはしない
        $response = $this->actingAs($this->作成者)->get(route('channels.members.index', $this->プライベートチャンネル));

        $response->assertSee('badge badge--owner">作成者<', false);
        $response->assertSee('作成者は外せません', false);
        $response->assertSee('disabled>外す<', false);

        // 外すフォームは作成者のぶんだけ出ない（メンバー 2 人で 1 件）
        $this->assertSame(1, mb_substr_count($response->getContent(), 'members/'));
        $response->assertSee(route('channels.members.destroy', [$this->プライベートチャンネル, $this->メンバー]), false);
        $response->assertDontSee(route('channels.members.destroy', [$this->プライベートチャンネル, $this->作成者]), false);
    }

    public function test_一覧の行のメンバーはプライベートの作成者にだけ出る(): void
    {
        // mockup/channels.html の並び（メンバー → 編集 → 開く）
        $response = $this->actingAs($this->作成者)->get(route('channels.index'));
        $response->assertSee(route('channels.members.index', $this->プライベートチャンネル), false);
        // 公開チャンネルにはメンバーという状態が無い（data.md 1-3 の決定2）
        $response->assertDontSee(route('channels.members.index', $this->公開チャンネル), false);

        // 作成者でないメンバーには出ない
        $this->actingAs($this->メンバー)->get(route('channels.index'))
            ->assertDontSee(route('channels.members.index', $this->プライベートチャンネル), false);
    }

    public function test_公開チャンネルのメンバーのアドレスはどれも404で403にしない(): void
    {
        // T-3-10（permissions-api.md 1-4 の △1）。**作成者でも 404**。
        // 403 だと「プライベート専用の画面がある」ことが分かってしまう
        $this->actingAs($this->作成者)
            ->get(route('channels.members.index', $this->公開チャンネル))->assertNotFound();
        $this->actingAs($this->作成者)
            ->post(route('channels.members.store', $this->公開チャンネル), ['member' => 'takahashi@example.com'])->assertNotFound();
        $this->actingAs($this->作成者)
            ->delete(route('channels.members.destroy', [$this->公開チャンネル, $this->メンバー]))->assertNotFound();

        // 公開チャンネルには行がそもそも無い（data.md 1-3 の決定2）
        $this->assertSame(0, $this->公開チャンネル->members()->count());
    }

    public function test_作成者でないメンバーがメンバー画面を開くと403(): void
    {
        // T-3-11（Q-13 / permissions-api.md 1-5）。T-3-10 と兼ねない。
        // そのチャンネルは本人に見えているので、隠す意味がない
        $this->actingAs($this->メンバー)
            ->get(route('channels.members.index', $this->プライベートチャンネル))->assertForbidden();

        // メンバーでない人は画面そのものが 404（visibleTo で落ちる）
        $this->actingAs($this->メンバーでない人)
            ->get(route('channels.members.index', $this->プライベートチャンネル))->assertNotFound();
    }

    // 追加する

    public function test_探す欄の3つのエラー文が出し分けられる(): void
    {
        // T-3-01（screens.md 4-7 / Q-14）
        $this->addMember('いない人@example.com')
            ->assertSessionHasErrors(['member' => '該当する社員が見つかりません']);

        // すでにメンバー
        $this->addMember('suzuki@example.com')
            ->assertSessionHasErrors(['member' => 'この社員はすでにメンバーです']);

        // 同じ表示名が複数（T-3-04 の裏）
        User::create(['name' => '高橋 健', 'email' => 'takahashi2@example.com', 'password' => 'password']);
        $this->addMember('高橋 健')
            ->assertSessionHasErrors(['member' => '同じ表示名の社員が複数いるためメールアドレスで指定してください']);

        $this->assertSame(2, $this->プライベートチャンネル->members()->count());
    }

    public function test_探す欄が空のときのエラー文が出る(): void
    {
        // T-3-02（screens.md 4-7）。前後の空白だけでも弾く
        $this->addMember('')
            ->assertSessionHasErrors(['member' => '表示名またはメールアドレスを入力してください']);
        $this->addMember('　  ')
            ->assertSessionHasErrors(['member' => '表示名またはメールアドレスを入力してください']);

        // 型が合わない値でも欄の文言を出す（screens.md 4-1）
        $this->actingAs($this->作成者)
            ->post(route('channels.members.store', $this->プライベートチャンネル), ['member' => ['x']])
            ->assertSessionHasErrors(['member' => '表示名またはメールアドレスを入力してください']);

        $this->assertSame(2, $this->プライベートチャンネル->members()->count());
    }

    public function test_同じ人を二重に追加できない(): void
    {
        // T-3-03（data.md 2-5 の一意制約）。画面には文言を出し、行は増えない
        $this->addMember('takahashi@example.com')->assertSessionHasNoErrors();
        $this->assertSame(3, $this->プライベートチャンネル->members()->count());

        $this->addMember('takahashi@example.com')
            ->assertSessionHasErrors(['member' => 'この社員はすでにメンバーです']);
        $this->assertSame(3, $this->プライベートチャンネル->members()->count());

        // 一意制約そのものも効いている（アプリを通らない経路の歯止め）
        $this->expectException(QueryException::class);
        $this->プライベートチャンネル->members()->attach($this->メンバーでない人);
    }

    public function test_同じ表示名でもメールアドレスなら1人に絞れる(): void
    {
        // T-3-04（Q-14）。メールアドレスは一意なので絞れないのは表示名のときだけ
        $同姓同名 = User::create(['name' => '高橋 健', 'email' => 'takahashi2@example.com', 'password' => 'password']);

        $this->addMember('takahashi2@example.com')->assertSessionHasNoErrors();

        $this->assertTrue($this->プライベートチャンネル->members()->whereKey($同姓同名->getKey())->exists());
        $this->assertFalse($this->プライベートチャンネル->members()->whereKey($this->メンバーでない人->getKey())->exists());
    }

    public function test_追加された人はその時点からチャンネルを読めて書ける(): void
    {
        // T-3-12（仕様書 3-3 / F-11）。AC-3-2 の後半
        $this->actingAs($this->メンバーでない人)
            ->get(route('channels.show', $this->プライベートチャンネル))->assertNotFound();

        $response = $this->addMember('takahashi@example.com');
        $response->assertRedirect(route('channels.members.index', $this->プライベートチャンネル));
        $response->assertSessionHas('status', 'メンバーを追加しました');

        // サイドバーにも出て、画面が開けて、書き込める
        $開いた = $this->actingAs($this->メンバーでない人)->get(route('channels.show', $this->プライベートチャンネル));
        $開いた->assertOk();
        $開いた->assertSee('採用プロジェクト', false);
        $開いた->assertSee(route('channels.messages.store', $this->プライベートチャンネル), false);

        $this->actingAs($this->メンバーでない人)
            ->post(route('channels.messages.store', $this->プライベートチャンネル), ['body' => '入れてもらいました'])
            ->assertSessionHasNoErrors();
        $this->assertSame('入れてもらいました', Message::sole()->body);
    }

    // 外す

    public function test_外すとサイドバーから消えてアドレスも404になる(): void
    {
        // AC-3-3（permissions-api.md 1-5）
        $response = $this->actingAs($this->作成者)
            ->delete(route('channels.members.destroy', [$this->プライベートチャンネル, $this->メンバー]));

        $response->assertRedirect(route('channels.members.index', $this->プライベートチャンネル));
        $response->assertSessionHas('status', 'メンバーを外しました');

        // 一覧から消える
        $this->actingAs($this->作成者)->get(route('channels.members.index', $this->プライベートチャンネル))
            ->assertDontSee('鈴木 花子', false);

        // 外された人からは、名前もアドレスも見えなくなる
        $一覧 = $this->actingAs($this->メンバー)->get(route('channels.index'));
        $一覧->assertDontSee('採用プロジェクト', false);
        $this->actingAs($this->メンバー)
            ->get(route('channels.show', $this->プライベートチャンネル))->assertNotFound();
    }

    public function test_作成者は本人の操作でも外せず403になる(): void
    {
        // T-3-07（回答4 / permissions-api.md 1-4 の △2・1-5）
        $this->actingAs($this->作成者)
            ->delete(route('channels.members.destroy', [$this->プライベートチャンネル, $this->作成者]))
            ->assertForbidden();

        // 行は残っている
        $this->assertTrue($this->プライベートチャンネル->members()->whereKey($this->作成者->getKey())->exists());
        $this->assertSame(2, $this->プライベートチャンネル->members()->count());
    }

    // 変わっていない

    public function test_外した人が書いたメッセージと返信はそのまま残る(): void
    {
        // T-3-08（Q-15 / data.md 2-3）。messages には触らない。
        // 外された人が自分の過去のメッセージを直せないことは、単位4 の
        // MessageTest::test_外された人は自分の過去のメッセージも直せず404になる
        $メッセージ = Message::create([
            'channel_id' => $this->プライベートチャンネル->id,
            'user_id' => $this->メンバー->id,
            'parent_id' => null,
            'body' => '外される前に書いた本文',
        ]);
        $返信 = Message::create([
            'channel_id' => $this->プライベートチャンネル->id,
            'user_id' => $this->メンバー->id,
            'parent_id' => $メッセージ->id,
            'body' => '外される前に書いた返信',
        ]);

        $this->actingAs($this->作成者)
            ->delete(route('channels.members.destroy', [$this->プライベートチャンネル, $this->メンバー]));

        // 行はどちらも残る
        $this->assertSame('外される前に書いた本文', $メッセージ->fresh()->body);
        $this->assertSame('外される前に書いた返信', $返信->fresh()->body);
        $this->assertNull($メッセージ->fresh()->deleted_at);

        // 残っているメンバーの画面にも、本文と投稿者名がそのまま出る
        $開いた = $this->actingAs($this->作成者)->get(route('channels.show', $this->プライベートチャンネル));
        $開いた->assertSee('外される前に書いた本文', false);
        $開いた->assertSee('鈴木 花子', false);
        $開いた->assertSee('返信1件', false);
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

    private function addMember(string $探す値): TestResponse
    {
        return $this->actingAs($this->作成者)
            ->post(route('channels.members.store', $this->プライベートチャンネル), ['member' => $探す値]);
    }
}

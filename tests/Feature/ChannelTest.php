<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * 単位2「チャンネル」（F-06〜F-09 ＋ F-04 のサイドバー・F-05 の 404/403）。
 *
 * 並びは acceptance.md 3-2 のテスト観点（T-2-01〜T-2-18）に合わせる。
 * T-2-11 の「メンバー＝プライベート×作成者」は単位3（行き先の SC-09 がまだ無い）。
 */
class ChannelTest extends TestCase
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

    public function test_一覧とサイドバーに出るのは公開全部と自分がメンバーのプライベートで作成順に並ぶ(): void
    {
        $あとから = $this->makeChannel(['name' => 'あとから作った公開']);

        // メンバーでない人には、プライベートの名前すら出ない（T-2-10 / 回答1）
        $response = $this->actingAs($this->見られない人)->get(route('channels.index'));
        $response->assertOk();
        $response->assertSee('開発', false);
        $response->assertSee('あとから作った公開', false);
        $response->assertDontSee('採用プロジェクト', false);

        // 作成順（id の順）に並ぶ
        $本文 = $response->getContent();
        $this->assertLessThan(mb_strpos($本文, 'あとから作った公開'), mb_strpos($本文, '開発'));

        // メンバーには両方出る。サイドバー（side-item）と一覧（row__title）の両方
        $response = $this->actingAs($this->見られる人)->get(route('channels.index'));
        $response->assertSee('採用プロジェクト', false);
        $this->assertSame(3, mb_substr_count($response->getContent(), 'class="side-item'));

        $this->assertSame([$this->公開チャンネル->id, $this->プライベートチャンネル->id, $あとから->id],
            Channel::visibleTo($this->見られる人)->orderBy('id')->pluck('id')->all());
    }

    public function test_一覧の行は作成者にだけ編集が出て開くは常に出る(): void
    {
        // 作成者（T-2-11）
        $response = $this->actingAs($this->作成者)->get(route('channels.index'));
        $response->assertSee(route('channels.edit', $this->公開チャンネル), false);
        $response->assertSee(route('channels.show', $this->公開チャンネル), false);

        // 見られるが作成者でない人には編集が出ない
        $response = $this->actingAs($this->見られる人)->get(route('channels.index'));
        $response->assertDontSee(route('channels.edit', $this->公開チャンネル), false);
        $response->assertSee(route('channels.show', $this->公開チャンネル), false);

        // 「メンバー」はプライベート×作成者の行だけ（単位3 で足した）。この人は
        // 作成者でなく、相手も公開チャンネルなので、どちらの理由でも出ない
        $response->assertDontSee('>メンバー<', false);
    }

    public function test_チャンネルを見られるかの判定が一覧と同じ答えになる(): void
    {
        // ChannelPolicy@view は単位4 の MessagePolicy が先に通す入口。判定が
        // visibleTo スコープ 1 か所を見ていることを固定する（permissions-api.md 1-6）
        $this->assertTrue($this->作成者->can('view', $this->公開チャンネル));
        $this->assertTrue($this->見られない人->can('view', $this->公開チャンネル));
        $this->assertTrue($this->見られる人->can('view', $this->プライベートチャンネル));
        $this->assertFalse($this->見られない人->can('view', $this->プライベートチャンネル));
    }

    public function test_チャンネルの見出しは作成者にだけ編集と削除が出る(): void
    {
        $response = $this->actingAs($this->作成者)->get(route('channels.show', $this->公開チャンネル));
        $response->assertSee(route('channels.edit', $this->公開チャンネル), false);
        $response->assertSee(route('channels.delete', $this->公開チャンネル), false);

        // 他人のチャンネルの見出しには編集・削除を出さない（screens.md 4-9）
        $response = $this->actingAs($this->見られる人)->get(route('channels.show', $this->公開チャンネル));
        $response->assertOk();
        $response->assertDontSee(route('channels.edit', $this->公開チャンネル), false);
        $response->assertDontSee(route('channels.delete', $this->公開チャンネル), false);
    }

    public function test_一覧は同じ問い合わせを2回走らせない(): void
    {
        $本数 = 0;
        DB::listen(function ($query) use (&$本数) {
            if (str_contains($query->sql, 'from `channels`')) {
                $本数++;
            }
        });

        $this->actingAs($this->見られる人)->get(route('channels.index'))->assertOk();

        // 一覧とサイドバーが同じ行を使う（permissions-api.md 1-6）
        $this->assertSame(1, $本数);
    }

    public function test_チャンネルが0件のときに空状態の枠が出る(): void
    {
        Channel::query()->delete();

        $response = $this->actingAs($this->作成者)->get(route('channels.index'));

        $response->assertSee('まだチャンネルがありません', false);
        $response->assertSee('チャンネルを作って会話を始めましょう', false);
    }

    public function test_見えないプライベートチャンネルのアドレスはどれも404で403にしない(): void
    {
        $id = $this->プライベートチャンネル->id;

        foreach ([
            ['get', route('channels.show', $id)],
            ['get', route('channels.edit', $id)],
            ['get', route('channels.delete', $id)],
            ['patch', route('channels.update', $id)],
            ['delete', route('channels.destroy', $id)],
        ] as [$方法, $アドレス]) {
            $response = $this->actingAs($this->見られない人)->{$方法}($アドレス);

            // 403 を返すと「そこに部屋がある」ことが漏れる（T-2-13 / permissions-api.md 1-5）
            $response->assertNotFound();
            $this->assertNotSame(403, $response->getStatusCode());
        }
    }

    public function test_見えているチャンネルで作成者でない人が編集や削除を開くと403(): void
    {
        $id = $this->公開チャンネル->id;

        foreach ([
            ['get', route('channels.edit', $id)],
            ['get', route('channels.delete', $id)],
            ['patch', route('channels.update', $id)],
            ['delete', route('channels.destroy', $id)],
        ] as [$方法, $アドレス]) {
            $response = $this->actingAs($this->見られる人)->{$方法}($アドレス);

            // そのチャンネルは本人に見えているので隠す意味がない（T-2-12 / 1-5）
            $response->assertForbidden();
        }

        // 見るだけなら通る
        $this->actingAs($this->見られる人)->get(route('channels.show', $id))->assertOk();
    }

    // 作る

    public function test_チャンネル名は空で弾かれ50文字は通り51文字で弾かれる(): void
    {
        $response = $this->actingAs($this->作成者)->post(route('channels.store'), $this->作成の入力(['name' => '']));
        $response->assertSessionHasErrors(['name' => 'チャンネル名を入力してください']);

        $ちょうど = str_repeat('あ', 50);
        $this->actingAs($this->作成者)->post(route('channels.store'), $this->作成の入力(['name' => $ちょうど]));
        $this->assertDatabaseHas('channels', ['name' => $ちょうど]);

        $超過 = str_repeat('い', 51);
        $response = $this->actingAs($this->作成者)->post(route('channels.store'), $this->作成の入力(['name' => $超過]));
        $response->assertSessionHasErrors(['name' => 'チャンネル名は50文字以内で入力してください']);
        $this->assertDatabaseMissing('channels', ['name' => $超過]);
    }

    public function test_説明は空でも作れ200文字は通り201文字で弾かれる(): void
    {
        $this->actingAs($this->作成者)->post(route('channels.store'), $this->作成の入力(['name' => '説明なし', 'description' => '']));
        $this->assertDatabaseHas('channels', ['name' => '説明なし', 'description' => null]);

        $ちょうど = str_repeat('う', 200);
        $this->actingAs($this->作成者)->post(route('channels.store'), $this->作成の入力(['name' => '説明ちょうど', 'description' => $ちょうど]));
        $this->assertDatabaseHas('channels', ['name' => '説明ちょうど', 'description' => $ちょうど]);

        $response = $this->actingAs($this->作成者)->post(route('channels.store'), $this->作成の入力([
            'name' => '説明超過',
            'description' => str_repeat('え', 201),
        ]));
        $response->assertSessionHasErrors(['description' => '説明は200文字以内で入力してください']);
        $this->assertDatabaseMissing('channels', ['name' => '説明超過']);
    }

    public function test_公開範囲の既定は公開で選んでいる側が枠と地で分かる(): void
    {
        $response = $this->actingAs($this->作成者)->get(route('channels.create'));

        // 既定は「公開」（screens.md 3-5 の SC-04）
        $response->assertSee('name="is_private" value="0" checked', false);
        $response->assertDontSee('name="is_private" value="1" checked', false);

        // 枠と地は JavaScript ではなく :checked で付ける（behavior.md 4-1 の (4)）。
        // 見た目そのものは画面で確かめる
        $this->assertStringContainsString(
            '.radio:has(input:checked)',
            file_get_contents(public_path('css/app.css'))
        );

        // 公開範囲が選べていないときは、どちらも選んでいない形で出す
        $this->actingAs($this->作成者)->from(route('channels.create'))
            ->post(route('channels.store'), $this->作成の入力(['name' => '範囲なし', 'is_private' => '2']))
            ->assertSessionHasErrors(['is_private' => '公開範囲を選択してください']);

        $戻り = $this->actingAs($this->作成者)->get(route('channels.create'));
        $戻り->assertDontSee('name="is_private" value="0" checked', false);
        $戻り->assertDontSee('name="is_private" value="1" checked', false);
    }

    public function test_公開はメンバーの行ができずプライベートは作成者の行が1つだけできる(): void
    {
        $this->actingAs($this->作成者)->post(route('channels.store'), $this->作成の入力(['name' => '公開で作る', 'is_private' => '0']));
        $公開 = Channel::where('name', '公開で作る')->sole();
        $this->assertSame(0, $公開->members()->count());

        $this->actingAs($this->作成者)->post(route('channels.store'), $this->作成の入力(['name' => '秘密で作る', 'is_private' => '1']));
        $プライベート = Channel::where('name', '秘密で作る')->sole();
        $this->assertSame([$this->作成者->id], $プライベート->members()->pluck('users.id')->all());
    }

    public function test_作成の途中で失敗すると作成者の行がないプライベートチャンネルが残らない(): void
    {
        // channels の INSERT のあと、channel_user に入れる前で落ちたことにする
        Channel::created(function () {
            throw new RuntimeException('途中で失敗したことにする');
        });

        $this->actingAs($this->作成者)
            ->post(route('channels.store'), $this->作成の入力(['name' => '途中で失敗', 'is_private' => '1']))
            ->assertServerError();

        // 1つのトランザクションなので、チャンネルごと残らない（T-2-05 / behavior.md 3-5）
        $this->assertDatabaseMissing('channels', ['name' => '途中で失敗']);
    }

    public function test_自分に見えているチャンネルと同じ名前では作れない(): void
    {
        $response = $this->actingAs($this->作成者)->post(route('channels.store'), $this->作成の入力(['name' => '開発']));

        $response->assertSessionHasErrors(['name' => 'この名前のチャンネルはすでにあります']);
        $this->assertSame(1, Channel::where('name', '開発')->count());
    }

    public function test_自分に見えないプライベートチャンネルと同じ名前なら作れる(): void
    {
        // 存在を隠すほうを優先した判断（T-2-07 / questions.md Q-38 は未回答）
        $this->actingAs($this->見られない人)
            ->post(route('channels.store'), $this->作成の入力(['name' => '採用プロジェクト']));

        $this->assertSame(2, Channel::where('name', '採用プロジェクト')->count());

        // メンバーから見ると、作ったほうは弾かれる
        $this->actingAs($this->見られる人)
            ->post(route('channels.store'), $this->作成の入力(['name' => '採用プロジェクト']))
            ->assertSessionHasErrors(['name' => 'この名前のチャンネルはすでにあります']);
    }

    public function test_作成すると決めた文言の知らせが出てそのチャンネルが開く(): void
    {
        $response = $this->actingAs($this->作成者)->post(route('channels.store'), $this->作成の入力(['name' => '新しい公開']));

        $作った = Channel::where('name', '新しい公開')->sole();
        $response->assertRedirect(route('channels.show', $作った));
        $response->assertSessionHas('status', 'チャンネルを作成しました');

        // 知らせは共通の枠の本文の上に1行（screens.md 3-1 / behavior.md 4-6）
        $this->actingAs($this->作成者)->get(route('channels.show', $作った))
            ->assertSee('<div class="notice">チャンネルを作成しました</div>', false);
    }

    public function test_保存と削除でも決めた文言の知らせが出る(): void
    {
        $this->actingAs($this->作成者)
            ->patch(route('channels.update', $this->公開チャンネル), ['name' => '開発', 'description' => '直した説明'])
            ->assertSessionHas('status', 'チャンネルを保存しました');

        $this->actingAs($this->作成者)
            ->delete(route('channels.destroy', $this->公開チャンネル), ['name' => '開発'])
            ->assertSessionHas('status', 'チャンネルを削除しました');
    }

    public function test_型の合わない値を送っても戻った画面が落ちない(): void
    {
        $response = $this->actingAs($this->作成者)->from(route('channels.create'))
            ->post(route('channels.store'), ['name' => ['x'], 'description' => ['y'], 'is_private' => '0']);

        $response->assertRedirect(route('channels.create'));
        $response->assertSessionHasErrors([
            'name' => 'チャンネル名を入力してください',
            'description' => '説明を入力してください',
        ]);

        // 戻った画面が描けること。以前は mb_strlen(array) で 500 になっていた
        // （screens.md 4-1 の「型が合わない値」）
        $this->actingAs($this->作成者)->get(route('channels.create'))->assertOk();
        $this->assertDatabaseMissing('channels', ['name' => 'x']);
    }

    // 直す

    public function test_編集では自分を重複の相手に数えず名前を変えずに説明だけ直せる(): void
    {
        $this->actingAs($this->作成者)->patch(route('channels.update', $this->公開チャンネル), [
            'name' => '開発',
            'description' => '直した説明',
        ])->assertRedirect(route('channels.show', $this->公開チャンネル));

        $this->assertDatabaseHas('channels', [
            'id' => $this->公開チャンネル->id,
            'name' => '開発',
            'description' => '直した説明',
        ]);

        // ほかの見えているチャンネルと同じ名前にはできない
        $this->actingAs($this->作成者)->patch(route('channels.update', $this->公開チャンネル), [
            'name' => '採用プロジェクト',
            'description' => '直した説明',
        ])->assertSessionHasErrors(['name' => 'この名前のチャンネルはすでにあります']);
    }

    public function test_説明を空にすると消え送られてこないときも前の値が残らない(): void
    {
        $this->actingAs($this->作成者)->patch(route('channels.update', $this->公開チャンネル), [
            'name' => '開発',
            'description' => '',
        ]);
        $this->assertNull($this->公開チャンネル->fresh()->description);

        $this->公開チャンネル->update(['description' => '戻した説明']);

        // 欄ごと送られてこないときも前の値を残さない
        $this->actingAs($this->作成者)->patch(route('channels.update', $this->公開チャンネル), ['name' => '開発']);
        $this->assertNull($this->公開チャンネル->fresh()->description);
    }

    public function test_編集に公開範囲の欄がなく送っても無視される(): void
    {
        $response = $this->actingAs($this->作成者)->get(route('channels.edit', $this->公開チャンネル));
        $response->assertOk();
        $response->assertDontSee('name="is_private"', false);
        $response->assertSee('公開チャンネルです。公開範囲はあとから変更できません。', false);

        $this->actingAs($this->作成者)->patch(route('channels.update', $this->公開チャンネル), [
            'name' => '開発',
            'description' => '開発に関する相談と報告',
            'is_private' => '1',
        ]);

        // 作成後は変わらない（T-2-09 / questions.md Q-09）
        $this->assertFalse($this->公開チャンネル->fresh()->is_private);
    }

    // 消す

    public function test_削除の確認に出す件数は返信と別々で削除済みも含める(): void
    {
        $親 = $this->makeMessage($this->公開チャンネル);
        $this->makeMessage($this->公開チャンネル);
        $消した親 = $this->makeMessage($this->公開チャンネル);
        $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id]);
        $消した返信 = $this->makeMessage($this->公開チャンネル, ['parent_id' => $親->id]);
        $消した親->delete();
        $消した返信->delete();

        $response = $this->actingAs($this->作成者)->get(route('channels.delete', $this->公開チャンネル));

        // 削除済みも含めて メッセージ3件・返信2件（T-2-14 / questions.md Q-12）
        $response->assertSee('メッセージ 3件と返信 2件も削除されます。', false);
        $response->assertSee('「開発」を削除しますか？', false);
    }

    public function test_確認の入力が不一致なら削除されず確認画面にとどまる(): void
    {
        // 1文字でも違えば消えない（AC-2-4）
        $response = $this->actingAs($this->作成者)->delete(route('channels.destroy', $this->公開チャンネル), ['name' => '開発室']);

        // 編集画面には戻さない（T-2-15 / screens.md 4-5）
        $response->assertRedirect(route('channels.delete', $this->公開チャンネル));
        $response->assertSessionHasErrors(['name' => 'チャンネル名が一致しません']);
        $this->assertDatabaseHas('channels', ['id' => $this->公開チャンネル->id]);

        // 空のときは必須の1文だけ
        $response = $this->actingAs($this->作成者)->delete(route('channels.destroy', $this->公開チャンネル), ['name' => '']);
        $response->assertSessionHasErrors(['name' => 'チャンネル名を入力してください']);
        $this->assertSame(['チャンネル名を入力してください'], session('errors')->getBag('default')->get('name'));
        $this->assertDatabaseHas('channels', ['id' => $this->公開チャンネル->id]);

        // 前後に空白が付いただけなら一致として扱う。送られてきた値は前後の空白と
        // 改行を取り除いてから見る決めが全体にかかっており（screens.md 4-1）、
        // チャンネル名も取り除いたあとの値で保存されているため
        $this->actingAs($this->作成者)
            ->delete(route('channels.destroy', $this->公開チャンネル), ['name' => '　開発 '])
            ->assertRedirect(route('channels.index'));
        $this->assertDatabaseMissing('channels', ['id' => $this->公開チャンネル->id]);
    }

    public function test_削除するとチャンネルの行が消えてメッセージとメンバーも連鎖で消える(): void
    {
        $親 = $this->makeMessage($this->プライベートチャンネル);
        $消した = $this->makeMessage($this->プライベートチャンネル, ['parent_id' => $親->id]);
        $消した->delete();
        $id = $this->プライベートチャンネル->id;

        $response = $this->actingAs($this->作成者)->delete(route('channels.destroy', $id), ['name' => '採用プロジェクト']);

        $response->assertRedirect(route('channels.index'));
        $this->assertDatabaseMissing('channels', ['id' => $id]);
        // SoftDeletes で残らない。物理削除＋外部キーの連鎖（T-2-16 / data.md 2-5）
        $this->assertSame(0, DB::table('messages')->where('channel_id', $id)->count());
        $this->assertSame(0, DB::table('channel_user')->where('channel_id', $id)->count());
    }

    public function test_削除の途中で失敗したらチャンネルごと残る(): void
    {
        $this->makeMessage($this->公開チャンネル);

        Channel::deleted(function () {
            throw new RuntimeException('途中で失敗したことにする');
        });

        $this->actingAs($this->作成者)
            ->delete(route('channels.destroy', $this->公開チャンネル), ['name' => '開発'])
            ->assertServerError();

        // 1つのトランザクションなので、連鎖で消えたメッセージも戻る（T-2-17）
        $this->assertDatabaseHas('channels', ['id' => $this->公開チャンネル->id]);
        $this->assertSame(1, DB::table('messages')->where('channel_id', $this->公開チャンネル->id)->count());
    }

    // 変わっていない

    public function test_未ログインでチャンネルの画面を開くとログイン画面へ送られる(): void
    {
        // 単位1 の T-1-20 の範囲が、今回増えたアドレスにも効いていること
        foreach ([
            route('channels.create'),
            route('channels.show', $this->公開チャンネル),
            route('channels.edit', $this->公開チャンネル),
            route('channels.delete', $this->公開チャンネル),
        ] as $アドレス) {
            $this->get($アドレス)->assertRedirect(route('login'));
        }
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

    private function makeMessage(Channel $channel, array $差分 = []): Message
    {
        return Message::create($差分 + [
            'channel_id' => $channel->id,
            'user_id' => $this->作成者->id,
            'parent_id' => null,
            'body' => 'メッセージ',
        ]);
    }

    private function 作成の入力(array $差分 = []): array
    {
        return $差分 + [
            'name' => '新しいチャンネル',
            'description' => '説明',
            'is_private' => '0',
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 単位1「認証」（F-01〜F-03 ＋ F-04 の上部バー・F-05 の出入り）。
 *
 * 並びは acceptance.md 3-1 のテスト観点（T-1-01〜T-1-20）に合わせる。
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private User $既存の社員;

    protected function setUp(): void
    {
        parent::setUp();

        $this->既存の社員 = User::create([
            'name' => '佐藤 太郎',
            'email' => 'sato@example.com',
            'password' => 'password',
        ]);
    }

    // 登録する

    public function test_登録するとログインした状態でチャンネル一覧が開き上部バーに表示名が出る(): void
    {
        $response = $this->followingRedirects()->post(route('register.store'), $this->登録の入力());

        $response->assertOk();
        $response->assertSee('チャンネル一覧', false);
        $response->assertSee('鈴木 花子', false);
        $this->assertAuthenticated();
    }

    public function test_登録の4項目をそれぞれ空で送るとその欄の下にエラー文が出る(): void
    {
        foreach ([
            'name' => '表示名を入力してください',
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
            'password_confirmation' => 'パスワード（確認用）を入力してください',
        ] as $欄 => $文言) {
            $response = $this->post(route('register.store'), $this->登録の入力([$欄 => '']));

            $response->assertSessionHasErrors([$欄 => $文言]);
            // ほかの欄を巻き込まないこと（「その欄の下にだけ」）
            $this->assertSame([$欄], array_keys(session('errors')->getBag('default')->messages()));
            $this->assertGuest();
        }
    }

    public function test_表示名は30文字が通り31文字で弾かれる(): void
    {
        // 通る側を先にやると登録が済んでログイン状態になり、guest に弾かれて検証に届かない
        $this->post(route('register.store'), $this->登録の入力(['name' => str_repeat('あ', 31)]))
            ->assertSessionHasErrors(['name' => '表示名は30文字以内で入力してください']);

        $this->post(route('register.store'), $this->登録の入力(['name' => str_repeat('あ', 30)]))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_パスワードは8文字が通り7文字で弾かれる(): void
    {
        $this->post(route('register.store'), $this->登録の入力([
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]))->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);

        $this->post(route('register.store'), $this->登録の入力([
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]))->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_パスワードと確認用が一致しないと確認用の欄に出る(): void
    {
        $this->post(route('register.store'), $this->登録の入力(['password_confirmation' => 'chigaumono']))
            ->assertSessionHasErrors(['password_confirmation' => 'パスワードが一致しません']);

        // パスワード側は赤くしない（screens.md 4-3 はこの文言を確認用の行に置いている）
        $this->assertFalse(session('errors')->has('password'));
        $this->assertGuest();
    }

    public function test_メールアドレスの形になっていないものを弾く(): void
    {
        $this->post(route('register.store'), $this->登録の入力(['email' => 'suzuki.example.com']))
            ->assertSessionHasErrors(['email' => 'メールアドレスの形式が正しくありません']);
    }

    public function test_メールアドレスの重複は弾き表示名の重複は通す(): void
    {
        $this->post(route('register.store'), $this->登録の入力(['email' => 'sato@example.com']))
            ->assertSessionHasErrors(['email' => 'このメールアドレスはすでに使われています']);

        $this->assertSame(1, User::where('email', 'sato@example.com')->count());

        $this->post(route('register.store'), $this->登録の入力(['name' => '佐藤 太郎']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, User::where('name', '佐藤 太郎')->count());
    }

    public function test_入力エラーのとき打った内容が欄に残りパスワードは残らない(): void
    {
        $this->from(route('register'))->post(route('register.store'), $this->登録の入力([
            'email' => 'sato@example.com',
            'password' => 'himitsunopassword',
            'password_confirmation' => 'himitsunopassword',
        ]));

        $response = $this->get(route('register'));

        $response->assertSee('value="鈴木 花子"', false);
        $response->assertSee('value="sato@example.com"', false);
        $response->assertDontSee('himitsunopassword', false);
    }

    public function test_前後の空白だけを送ると必須エラーになる(): void
    {
        $this->post(route('register.store'), $this->登録の入力([
            'name' => "  \n  ",
            'email' => '   ',
        ]))->assertSessionHasErrors([
            'name' => '表示名を入力してください',
            'email' => 'メールアドレスを入力してください',
        ]);

        $this->assertGuest();
    }

    public function test_全角スペースだけを送っても必須エラーになる(): void
    {
        $this->post(route('register.store'), $this->登録の入力(['name' => '　　']))
            ->assertSessionHasErrors(['name' => '表示名を入力してください']);

        $this->assertGuest();
    }

    public function test_表示名の前後の全角スペースは取り除いて保存する(): void
    {
        $this->post(route('register.store'), $this->登録の入力(['name' => '　鈴木　花子　']));

        $this->assertSame('鈴木　花子', User::where('email', 'suzuki@example.com')->sole()->name);
    }

    public function test_パスワードがハッシュで保存され平文が残らない(): void
    {
        $this->post(route('register.store'), $this->登録の入力(['password' => 'himitsunopassword', 'password_confirmation' => 'himitsunopassword']));

        $登録された人 = User::where('email', 'suzuki@example.com')->sole();

        $this->assertNotSame('himitsunopassword', $登録された人->password);
        $this->assertTrue(Hash::check('himitsunopassword', $登録された人->password));
    }

    // ログインする

    public function test_登録したメールアドレスとパスワードでログインするとチャンネル一覧が開く(): void
    {
        $response = $this->post(route('login.store'), [
            'email' => 'sato@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('channels.index'));
        $this->assertAuthenticatedAs($this->既存の社員);
    }

    public function test_ログインの必須のエラー文が日本語で出る(): void
    {
        $this->post(route('login.store'), ['email' => '', 'password' => ''])
            ->assertSessionHasErrors([
                'email' => 'メールアドレスを入力してください',
                'password' => 'パスワードを入力してください',
            ]);

        $this->assertGuest();
    }

    public function test_パスワードが違うとパスワード欄に1文だけ出てログインできない(): void
    {
        $response = $this->post(route('login.store'), [
            'email' => 'sato@example.com',
            'password' => 'passwore',
        ]);

        $response->assertSessionHasErrors(['password' => 'メールアドレスまたはパスワードが正しくありません']);
        $response->assertSessionHasErrors(['password']);
        $this->assertCount(1, session('errors')->all());
        $this->assertFalse(session('errors')->has('email'));
        $this->assertGuest();
    }

    public function test_ログインの試行が多すぎると429の画面になる(): void
    {
        $入力 = ['email' => 'sato@example.com', 'password' => 'chigaupassword'];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), $入力);
        }

        $this->post(route('login.store'), $入力)
            ->assertStatus(429)
            ->assertSee('試行回数が多すぎます。しばらく待ってからやり直してください', false);
    }

    // ログアウトする

    public function test_ログアウトするとログイン画面に戻り中身は見られない(): void
    {
        $this->actingAs($this->既存の社員);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('channels.index'))->assertRedirect(route('login'));
    }

    public function test_ログアウトは送信のときだけ効き直接開くと効かない(): void
    {
        $this->actingAs($this->既存の社員);

        $this->get('/logout')
            ->assertStatus(405)
            ->assertSee('このページはこの方法では開けません', false);

        $this->assertAuthenticatedAs($this->既存の社員);
    }

    // 入れない・見えない

    public function test_ログインが要る画面にノーストアが付く(): void
    {
        $this->actingAs($this->既存の社員);

        $response = $this->get(route('channels.index'));

        // 付いていないと、ログアウトしたあとに戻るボタンで中身が履歴から出る（AC-1-4）
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
    }

    public function test_未ログインで画面のアドレスを開くとログイン画面へ送られる(): void
    {
        $this->get(route('channels.index'))->assertRedirect(route('login'));
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_ログイン済みでログインと新規登録を開くとチャンネル一覧へ送られる(): void
    {
        $this->actingAs($this->既存の社員);

        $this->get(route('login'))->assertRedirect(route('channels.index'));
        $this->get(route('register'))->assertRedirect(route('channels.index'));
        $this->get('/')->assertRedirect(route('channels.index'));
    }

    public function test_外した機能のアドレスはどれも404(): void
    {
        $this->actingAs($this->既存の社員);

        foreach ([
            '/forgot-password',
            '/reset-password/token',
            '/email/verify',
            '/user/profile-information',
            '/user/password',
            '/two-factor-challenge',
            '/user/confirm-password',
        ] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    // エラー画面

    public function test_エラー画面6枚の文言が決めたとおり(): void
    {
        foreach ([
            403 => 'この操作を行う権限がありません',
            404 => 'お探しのページは見つかりません',
            405 => 'このページはこの方法では開けません',
            419 => '時間が経ちすぎたため送信できませんでした。ページを開き直してください',
            429 => '試行回数が多すぎます。しばらく待ってからやり直してください',
            500 => '申し訳ありません。サーバ側で問題が発生しました',
        ] as $ステータス => $文言) {
            $this->assertStringContainsString($文言, view("errors.{$ステータス}")->render());
        }

        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('お探しのページは見つかりません', false);
    }

    public function test_エラー画面の次の一手がログインの有無で変わる(): void
    {
        $this->get('/no-such-page')->assertSee('>ログイン</a>', false);

        $this->actingAs($this->既存の社員);

        $this->get('/no-such-page')->assertSee('チャンネル一覧へ戻る', false);
    }

    // 変わっていない

    public function test_usersに2要素認証の列がない(): void
    {
        foreach (['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'] as $列) {
            $this->assertFalse(Schema::hasColumn('users', $列));
        }
    }

    /**
     * @param  array<string, string>  $差分
     * @return array<string, string>
     */
    private function 登録の入力(array $差分 = []): array
    {
        return array_merge([
            'name' => '鈴木 花子',
            'email' => 'suzuki@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $差分);
    }
}

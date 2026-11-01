# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## このリポジトリ

Tutorial 16「総仕上げ」のチームチャットアプリ。素の Laravel 10 と Sail だけが入っていて、アプリの機能のコードはまだ 1 行も無い。仕様書とモックアップから設計に落とし、`docs/design/acceptance.md` の 7 つの実装単位（認証・チャンネル・メンバー管理・メッセージ・スレッド・検索・公開 API）を 1 単位 = 1 Issue = 1 PR として順に作る。
書くもの（設計書・コミットメッセージ・画面の文言）はすべて日本語。コミットメッセージは末尾に Issue 番号を付ける（例: `チャンネルを作れるようにした (#2)`）。
コードを触る前に、次の表から対象の設計書を読む。

## 決めごとの在りか

中身はここに写さない。必要になったら開くこと。

| 何が書いてあるか | どこにあるか |
|:--|:--|
| 仕様書（クライアントの要求） | `docs/spec.md`。**変えない** |
| 設計書一式（このアプリの決めごとの正本） | `docs/design/`（構成は `docs/design/README.md`） |
| 画面の見本 | `mockup/`（`index.html` から全画面） |
| 受け入れ条件とテスト観点 | `docs/design/acceptance.md` |

実装と設計書が食い違ったら、**コードではなく `docs/design/` を先に直す**。設計書は提出物の中心で、最後まで実物と一致させる。

## Commands

すべて Sail（Docker）経由。素の `php artisan` は DB に繋がらない。

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test
./vendor/bin/sail down
```

テストは `phpunit.xml` で `DB_DATABASE=testing` に切り替わる。この `testing` DB は MySQL コンテナ初回起動時の init スクリプトが作るので、DB が無いと言われたら `sail down -v` からやり直す。

## 注意

`.env` の中身は読まない。設定値を確認したいときは `.env.example` を見る。

## 実装の決まり

- 利用者に見せるメッセージは日本語。**検査を書くそばでフィールドごとに指定する**（`lang/ja` は作らない）。置き場は画面によって 3 つに分かれる（`behavior.md` 3-8 の表が正）。
    - 自前のコントローラー（SC-04 以降）: `validate()` の**第 2 引数**（例: `'name.required' => 'チャンネル名を入力してください'`）
    - SC-02 新規登録: `app/Actions/Fortify/CreateNewUser.php` の `Validator::make()` の**第 3 引数**（Fortify の登録はこのアクションクラスの中で検査する）
    - SC-01 ログイン: `app/Http/Requests/LoginRequest.php` の `messages()`（Fortify の `LoginRequest` に `messages()` が無いため、継承して container で差し替えている）
- ログイン失敗の「メールアドレスまたはパスワードが正しくありません」だけは別口。`app/Actions/Fortify/AttemptToAuthenticate.php` で**パスワード欄に**投げる（Fortify の既定はメールアドレス欄に英語で出す）。
- `config/app.php` の `locale` は `en` で `lang/ja` も無いため、メッセージを指定していない項目は英語の既定文言になる。検証ルールを足すときは日本語メッセージも必ず付ける（**文言は `docs/design/screens.md` 4 章が正。エラー文は句点なしの 1 文**）。
- ビューが関連モデルを引くときは、コントローラーで `with()` / `load()` を付けて先読みする（N+1 を避ける）。

## 守ってほしい書き方

`docs/design/` の決めごとのうち、素直に書くと破れるもの。理由は括弧内の設計書にある。

- **認可の順番**: `MessagePolicy` の判定（本人か・削除済みでないか）より先に、必ず `ChannelPolicy@view` を通す（`permissions-api.md` 1-6）。
- **404 と 403**: 見られないものは 404、見られたうえでの不可は 403。この順で判定する（`permissions-api.md` 1-5）。
- **チャンネルの絞り込み**: `Channel` の `visibleTo` スコープだけを通す。自前の `where` を書かない（`permissions-api.md` 1-6）。
- **削除済みのメッセージ**: 画面は `withTrashed()` で引き、検索と公開 API には書かない。出し分けはメッセージ 1 件ぶんの Blade 部品に閉じる。`SoftDeletes` の `deleting` で本文を触らない（`questions.md` 決定1 / `behavior.md` 3-6）。
- **返信件数**: 画面は削除済みの親・子を含めて数える（`withCount` の既定では落ちる）。公開 API は含めない（`behavior.md` 1-4）。
- **公開 API**: 返す列を許可リストで持つ。モデルをそのまま JSON にしない（`permissions-api.md` 3-4）。
- **入力の前処理**: 前後の空白と改行を取り除いてから検査し、上限もその後の字数で数え、保存もその値（`screens.md` 4-1）。
- **DB で縛れない約束**: 返信は 1 段まで、返信の `channel_id` は親と同じ、チャンネル名の重複はその人が見られる範囲だけで見る（`data.md` 4-3）。
- **まとめて書く・消す**: チャンネル作成と削除、初期データはトランザクションに入れる。チャンネルは物理削除（`behavior.md` 3-5）。
- **JavaScript**: 1 枚・3 か所だけ（文字数・編集中の差し替え・一覧のスクロール）（`questions.md` 決定2 / `behavior.md` 4-1）。
- **マイグレーション**: `users.name` は 30 字、作る順番は `channels` → `channel_user` → `messages`、`channel_user` はプライベートチャンネルだけ行を作る、タイムゾーンは `Asia/Tokyo`（`data.md` 2・5）。

## テスト

- 機能のテストは `tests/Feature/` に置く。データベースを使うテストには `use RefreshDatabase;` を付ける。
- メソッド名は日本語（例: `test_他人のメッセージは削除できない`）。並びは `docs/design/acceptance.md` の「テスト観点」に合わせ、`// 見る` `// 送る` `// 消す` `// 変わっていない` のような区切りコメントで分ける。
- 実装を変えたらテストも書いて回し、通るまで直して、最後に結果を報告する。
- テストが落ちた状態で作業を終えない。原因が自分の変更以外にあっても直す。

## Pull Request

- イシュー 1 件につきブランチ 1 本（例: `feature/2-channel`）。`main` へは PR で入れる。
- 受け入れ条件は `docs/design/acceptance.md` から AC 番号ごとに写す（PR で足さない・言い換えない）。
- 設計書を直したときは、そのコミットも同じ PR に入れる。

本文はこの型を埋める。

````markdown
## 何をした

単位◯「〇〇」

## テスト結果

```
$ ./vendor/bin/sail artisan test
（出力をそのまま貼る）
```

## 受け入れ条件の判定

| AC番号 | 受け入れ条件 | 判定 | 根拠 |
|:--|:--|:--|:--|
| AC-◯-1 | （acceptance.md から 1 行写す） | 満たす | `ChannelTest::test_〇〇` |
| AC-◯-2 | （同上） | 満たす | 画面で確認（手順と結果） |

## 設計書との差

（`docs/design/` を直したファイルと理由。無ければ「なし」）

Closes #<イシュー番号>
````

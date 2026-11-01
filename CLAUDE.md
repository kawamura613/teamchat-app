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

- 利用者に見せるメッセージは日本語。`validate()` の第 2 引数でフィールドごとに指定する（例: `'name.required' => '表示名を入力してください。'`）。
- `config/app.php` の `locale` は `en` で `lang/ja` も無いため、メッセージを指定していない項目は英語の既定文言になる。検証ルールを足すときは日本語メッセージも必ず付ける（文言は `docs/design/screens.md` が正）。
- ビューが関連モデルを引くときは、コントローラーで `with()` / `load()` を付けて先読みする（N+1 を避ける）。

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

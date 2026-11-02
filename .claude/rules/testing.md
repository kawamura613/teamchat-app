# テストの決まり

- 機能のテストは `tests/Feature/` に置く。
- 実装を変えたら、テストも書いて `./vendor/bin/sail artisan test` で回し、通るまで直して、最後に結果を報告する。
- テストを1クラスだけ回すときは `./vendor/bin/sail artisan test --filter ChannelTest` の形で回す（`sail test` ではなく、CLAUDE.md と同じ `sail artisan test` に揃える）。
- データベースを使うテストには `use RefreshDatabase;` を付ける。
- テストが落ちた状態で作業を終えない。原因が自分の変更以外にあっても直す。

## 書き方

- メソッド名は日本語（例: `test_他人のメッセージは削除できない`）。
- `setUp()` でユーザー（作成者・メンバー・メンバーでない人）とチャンネルを用意し、操作は private ヘルパー（`makeChannel` / `postMessage` など）にまとめる。
- テストは `// 見る` `// 送る` `// 消す` `// 変わっていない` の区切りコメントで並べる（`docs/design/acceptance.md` の「テスト観点」と対応）。
- 画面の確認は生 HTML で見る（`assertSee(..., false)`）。文言は `docs/design/screens.md`、クラス名は `mockup/` に合わせているので、そこを変えるとここが落ちる。
- factory は `UserFactory` だけ。ほかのモデルは `::create()` を直書きする。

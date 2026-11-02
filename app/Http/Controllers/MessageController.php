<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class MessageController extends Controller
{
    /**
     * F-14 メッセージ投稿（列C）。
     *
     * 知らせは出さない。打ったものがその場に出るので二重になる（behavior.md 4-6）。
     */
    public function store(Request $request, int $channel): RedirectResponse
    {
        // 見られないチャンネルへの投稿はここで 404（permissions-api.md 1-5）
        $channel = $this->visibleChannel($request, $channel);

        // 落ちたときは元の画面（SC-06）へ戻り、投稿欄の下にエラー文が出る
        $validated = $request->validate($this->rules(), $this->messages());

        $message = Message::create([
            'channel_id' => $channel->id,
            'user_id' => $request->user()->id,
            // 一覧に出すメッセージ。返信は F-19（単位5）
            'parent_id' => null,
            'body' => $validated['body'],
        ]);

        // 投稿の戻り先は一覧の末尾（behavior.md 4-3 の表）
        return redirect()->to(route('channels.show', $message->channel_id).'#bottom');
    }

    /**
     * F-15 その場で書き換えるときの入口（列D ＋ △3 / behavior.md 4-2）。
     *
     * 新しい画面ではなく、SC-06 のその1件だけを編集中にして返す。
     */
    public function edit(Message $message): View
    {
        $this->abortIfReply($message);
        $this->authorize('update', $message);

        return $this->screen($message->channel, $message->getKey());
    }

    /**
     * F-15 メッセージ編集（列D ＋ △3）。
     */
    public function update(Request $request, Message $message): RedirectResponse
    {
        $this->abortIfReply($message);
        $this->authorize('update', $message);

        // $request->validate() だと落ちたときに直前の URL（SC-06）へ戻り、
        // 編集中の状態が消える。messages.edit へ戻すために自分で組む
        // （screens.md 4-6「編集中のまま残す」/ behavior.md 4-2）。
        // 文言は検査のそばに置く（CLAUDE.md）
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            // 錨を付けないと app.js が一覧を末尾まで送ってしまい、編集中の
            // 入力欄もエラー文も画面の外になる（behavior.md 4-4）
            return redirect()->to(route('messages.edit', $message).'#message-'.$message->getKey())
                // 投稿欄と同じ body なので、編集中のエラーは別の袋に入れる
                ->withErrors($validator, 'edit')
                ->withInput();
        }

        $message->body = $validator->validated()['body'];
        // created_at（画面に出す日時）は触らない。印は一度付いたら消えない
        // （behavior.md 1-3 の遷移3・4 / Q-17 の想定・回答5）
        $message->edited_at = now();
        $message->save();

        return $this->backTo($message);
    }

    /**
     * F-16 メッセージ削除（列D ＋ △3）。
     */
    public function destroy(Message $message): RedirectResponse
    {
        $this->abortIfReply($message);
        $this->authorize('delete', $message);

        // deleted_at を立てるだけ。body にも parent_id の返信にも触らない
        // （questions.md 決定1・案1 / behavior.md 2-3 の決めどころ3・4）
        $message->delete();

        // 跡地は自分が消したのか相手が消したのかを言わないので、ここだけは
        // 同じ画面に戻るのに知らせを出す（behavior.md 4-6）
        return $this->backTo($message)->with('status', 'メッセージを削除しました');
    }

    /**
     * 返信に対する編集・削除は単位5（behavior.md 4-2）。
     *
     * 出す先の SC-07 がまだ無いので、edit・update・destroy の **3 本そろって**
     * 404 を返す。1 本だけ 404 にすると、入力チェックに落ちたときの戻り先
     * （messages.edit）が 404 になって、打った内容が消える。
     *
     * これは「出す先が無い」ことによる 404 なので、**authorize より先に**見る。
     * 単位5 で SC-07 を作ったら、この 3 か所の呼び出しを外す。
     */
    private function abortIfReply(Message $message): void
    {
        abort_if($message->parent_id !== null, 404);
    }

    /**
     * SC-06 を組む。`ChannelController@show` と同じ中身で、1件だけ編集中にする。
     *
     * **view に渡す鍵はここと `ChannelController@show` の 2 か所にある。**
     * 片方だけ増やすと、もう片方の経路で「変数が無い」で落ちる（単位5 で
     * スレッドの鍵を足すときに効く）。
     */
    private function screen(Channel $channel, ?int $editingId): View
    {
        return view('channels.show', [
            'channel' => $channel,
            'messages' => $channel->timeline()->get(),
            'editingId' => $editingId,
        ]);
    }

    /**
     * 操作のあとの戻り先（behavior.md 4-3）。
     *
     * 送られてきた URL にそのまま飛ばすと外部サイトへ飛ばせる口になるので、
     * URL は受け取らずサーバ側で組む。本来は隠し項目 `from` の2択だが、
     * もう一方（thread）の行き先 messages.show は単位5。**`from` が無いときは
     * channel として扱う**という決めがあるので、単位4 はこれで足りる。
     */
    private function backTo(Message $message): RedirectResponse
    {
        return redirect()->to(
            route('channels.show', $message->channel_id).'#message-'.$message->getKey()
        );
    }

    /**
     * 投稿とその場で書き換えるときの入力チェック（screens.md 4-6）。
     *
     * 前後の空白と改行は TrimStrings が落とし、空になった値は
     * ConvertEmptyStringsToNull が null にするので required で弾ける。
     * 上限もその後の字数で数える（screens.md 4-1 / behavior.md 3-8）。
     *
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            // bail を先頭に置く。無いと required と max のエラー文が同時に出る
            'body' => ['bail', 'required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'body.required' => 'メッセージを入力してください',
            // 配列などが送られてきたときも欄の文言を出す（screens.md 4-1）
            'body.string' => 'メッセージを入力してください',
            'body.max' => 'メッセージは1,000文字以内で入力してください',
        ];
    }
}

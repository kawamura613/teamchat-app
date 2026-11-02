<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesMessageBody;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class MessageController extends Controller
{
    use ValidatesMessageBody;

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
     * F-18 スレッド（SC-07 / 列C）。
     *
     * それ自身の URL を持つ画面（Q-24 の想定 / permissions-api.md 2-1）。
     * バインディングは ->withTrashed() なので、削除されたメッセージでも開ける
     * （questions.md 決定1 の出口1-b）。
     *
     * **この 2 つは順番が大事。**
     * (1) 先に「見られるチャンネルか」で 404 にする。authorize('view', …) だと
     *     403 になって「そこに部屋がある」ことが漏れる（permissions-api.md 1-5）
     * (2) そのあとで、返信の ID なら親へ正規化する（決定6 / behavior.md 4-5）。
     *     逆にすると、見られないチャンネルの返信 ID でも 302 が返って、その ID が
     *     あることが漏れる
     */
    public function show(Request $request, Message $message): View|RedirectResponse
    {
        $channel = $this->visibleChannel($request, $message->channel_id);

        if ($message->parent_id !== null) {
            // 302。永久の移転ではないので 301 にはしない（behavior.md 4-5）。
            // 錨を付けて、人から送られた返信の URL でもその 1 件に着地させる
            return redirect()->to(
                route('messages.show', $message->parent_id).'#message-'.$message->getKey()
            );
        }

        return $this->threadScreen($channel, $message, null);
    }

    /**
     * F-15 その場で書き換えるときの入口（列D ＋ △3 / behavior.md 4-2）。
     *
     * 新しい画面ではない。どちらを出すかは **`from` で決める**（behavior.md 4-2）。
     * その1件だけを編集中にする（まわりのメッセージはそのまま）。
     */
    public function edit(Request $request, Message $message): View
    {
        $this->authorize('update', $message);

        return $this->screenFor($request, $message, $message->getKey());
    }

    /**
     * F-15 メッセージ編集（列D ＋ △3）。
     */
    public function update(Request $request, Message $message): RedirectResponse
    {
        $this->authorize('update', $message);

        // $request->validate() だと落ちたときに直前の URL（SC-06）へ戻り、
        // 編集中の状態が消える。messages.edit へ戻すために自分で組む
        // （screens.md 4-6「編集中のまま残す」/ behavior.md 4-2）。
        // ルールと文言は ValidatesMessageBody（返信と同じ1か所・screens.md 4-6）
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            // 錨を付けないと app.js が一覧を末尾まで送ってしまい、編集中の
            // 入力欄もエラー文も画面の外になる（behavior.md 4-4）。
            // **from も引き継ぐ。**引き継がないと、保存が通ったときは SC-07 に
            // 戻るのに落ちたときだけ SC-06 に出る、という非対称になる（4-2）
            return redirect()->to(
                route('messages.edit', [$message, 'from' => $this->from($request)])
                .'#message-'.$message->getKey()
            )
                // 投稿欄と同じ body なので、編集中のエラーは別の袋に入れる
                ->withErrors($validator, 'edit')
                ->withInput();
        }

        $message->body = $validator->validated()['body'];
        // created_at（画面に出す日時）は触らない。印は一度付いたら消えない
        // （behavior.md 1-3 の遷移3・4 / Q-17 の想定・回答5）
        $message->edited_at = now();
        $message->save();

        return $this->backTo($request, $message);
    }

    /**
     * F-16 メッセージ削除（列D ＋ △3）。
     */
    public function destroy(Request $request, Message $message): RedirectResponse
    {
        $this->authorize('delete', $message);

        // deleted_at を立てるだけ。body にも parent_id の返信にも触らない
        // （questions.md 決定1・案1 / behavior.md 2-3 の決めどころ3・4）
        $message->delete();

        // 跡地は自分が消したのか相手が消したのかを言わないので、ここだけは
        // 同じ画面に戻るのに知らせを出す（behavior.md 4-6）
        return $this->backTo($request, $message)->with('status', 'メッセージを削除しました');
    }

    /**
     * 編集中の1件を抱えた画面を返す（behavior.md 4-2）。
     *
     * **返信でないメッセージなら SC-06、返信なら SC-07。**どちらも「新しい画面」
     * ではなく、SC-06・SC-07 の中の状態なので SC番号は増えない。
     *
     * チャンネルは `$message->channel` から取る。ここへ来る前に
     * `authorize('update', …)` が `ChannelPolicy@view` を通しているので、
     * 「見られるか」はもう済んでいる（permissions-api.md 1-6）。
     *
     * **`from` が `thread` なら SC-07。**それ以外（`channel`・無い・読めない）は
     * `{message}` が返信なら SC-07、そうでなければ SC-06（behavior.md 4-2）。
     * `from` を見るのは、返信でないメッセージを SC-07 の中で編集したときに、
     * パネルごと SC-06 へ落ちてしまうのを防ぐため。
     *
     * 親は `withTrashed()` で引く。削除済みの親にぶら下がった返信を編集中に
     * するとき、既定のままだと親が解決できない（決定1 の出口1-b と同じ事情）。
     */
    private function screenFor(Request $request, Message $message, ?int $editingId): View
    {
        $返信である = $message->parent_id !== null;

        if (! $返信である && $this->from($request) !== 'thread') {
            return $this->screen($message->channel, $editingId);
        }

        return $this->threadScreen(
            $message->channel,
            $返信である ? $message->parent()->withTrashed()->sole() : $message,
            $editingId
        );
    }

    /**
     * SC-06 を組む。`ChannelController@show` と同じ中身で、1件だけ編集中にする。
     *
     * **view に渡す鍵はここと `ChannelController@show` の 2 か所にある。**
     * 片方だけ増やすと、もう片方の経路で「変数が無い」で落ちる。
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
     * SC-07 を組む。**左の本文側は SC-06 と同じものを出す**（screens.md 3-5）。
     *
     * 返信件数は削除済みの親・子を含めて数える。数え方は Message::replyCount()
     * 1 か所に置いてある（behavior.md 1-4）。返信も user を先読みする
     * （Message@thread() の中で済ませている）。
     *
     * **親は一覧にも並んでいる**（一覧は返信でない行だけなので、スレッドの親は
     * 必ず入っている）。同じ行をもう一度数えないよう、一覧で数え終わったものを
     * そのまま使う。見つからないときだけ自分で数える（保険）。
     */
    private function threadScreen(Channel $channel, Message $parent, ?int $editingId): View
    {
        $messages = $channel->timeline()->get();
        $親 = $messages->firstWhere('id', $parent->getKey());

        if ($親 === null) {
            $親 = $parent;
            $親->loadCount(Message::replyCount());
            $親->loadMissing('user');
        }

        return view('messages.show', [
            'channel' => $channel,
            'messages' => $messages,
            'parent' => $親,
            'replies' => $親->thread()->get(),
            'editingId' => $editingId,
        ]);
    }

    /**
     * 操作のあとの戻り先（behavior.md 4-3）。
     *
     * 隠し項目 `from` の **2 択（channel / thread）だけ**を見て、URL はサーバ側で
     * 組む。送られてきた文字列にそのまま飛ばすと外部サイトへ飛ばせる口
     * （オープンリダイレクト）になるので、URL は受け取らない。2 択なら、その口が
     * ない。`Referer` も使わない（ブラウザや設定で落ちる）。
     *
     * **`from` が無い・読めないときは channel として扱う**（4-3 の決め）。
     *
     * 錨はどちらにも付ける。付けないと app.js が一覧を末尾まで送ってしまい、
     * いま触った 1 件が画面の外になる（4-4）。
     */
    private function backTo(Request $request, Message $message): RedirectResponse
    {
        $錨 = '#message-'.$message->getKey();

        if ($this->from($request) === 'thread') {
            // 返信なら親のスレッド、返信でなければそのメッセージ自身のスレッド
            return redirect()->to(
                route('messages.show', $message->parent_id ?? $message->getKey()).$錨
            );
        }

        return redirect()->to(route('channels.show', $message->channel_id).$錨);
    }

    /**
     * 送られてきた `from` を **2 択（channel / thread）に丸める**（behavior.md 4-3）。
     *
     * 無い・読めないときは channel。読む場所をここ 1 か所にして、戻り先・編集中の
     * 画面選び・落ちたときの 302 が同じ物差しを通る。
     */
    private function from(Request $request): string
    {
        return $request->input('from') === 'thread' ? 'thread' : 'channel';
    }
}

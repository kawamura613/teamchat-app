<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesMessageBody;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * F-19 返信投稿（SC-07 の返信欄 / permissions-api.md 2-2）。
 *
 * 返信もメッセージと同じ `messages` テーブルの行で、返信用のテーブルは持たない
 * （data.md 1-3 の決定1）。`parent_id` に親の ID が入り、`channel_id` は
 * **親と同じ値**を重複して持つ（決定3）。この2つは DB の制約では縛れないので、
 * ここで守る（data.md 4-3）。
 *
 * 返信は1段まで。返信に返信は付けられない（回答8）。その判定は
 * MessagePolicy@reply（列C ＋ △4）にある。
 */
class ReplyController extends Controller
{
    use ValidatesMessageBody;

    public function store(Request $request, Message $message): RedirectResponse
    {
        // 列C ＋ △4。見られないチャンネルへの返信は、MessagePolicy@reply が
        // ChannelPolicy@view を先に通して **404** を返す（denyAsNotFound。
        // permissions-api.md 1-5・1-6）。返信の ID に返信しようとしたら 403。
        // 親が削除済みでも通る（回答6 / questions.md 決定1 の出口1-d）。
        //
        // **visibleChannel() を重ねて呼ばない。**同じ問い合わせが 2 回走るだけで、
        // 答えは変わらない（permissions-api.md 1-6 の 2026-11-02 の行）
        $this->authorize('reply', $message);

        // $request->validate() だと、落ちたときにエラーが default の袋に入る。
        // SC-07 には name="body" の欄が3種類（左の投稿欄＝default・編集中＝edit・
        // 返信欄＝reply）あるので、**袋を reply に分ける**。分けないと、別の欄に
        // 打った下書きがこちらへ流れ込む（単位4 が編集中で踏んだのと同じ穴）。
        // ルールと文言は ValidatesMessageBody。メッセージと**同じチェックを
        // 同じ数だけ**かける（screens.md 4-6 / T-5-10）
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            // 戻り先は同じスレッド。返信欄の下にエラー文が出る（screens.md 4-6）。
            // 錨を付けないと素で開いた扱いになり、app.js が左の一覧を末尾まで
            // 送って、打っていた場所を見失う（behavior.md 4-3・4-4）
            return redirect()->to(
                route('messages.show', $message).'#message-'.$message->getKey()
            )
                ->withErrors($validator, 'reply')
                ->withInput();
        }

        $返信 = Message::create([
            // 親と同じチャンネル。親をたどらずに検索で絞れるようにするため
            // 重複して持つ（data.md 1-3 の決定3）
            'channel_id' => $message->channel_id,
            'user_id' => $request->user()->id,
            'parent_id' => $message->getKey(),
            'body' => $validator->validated()['body'],
        ]);

        // 戻り先は親のスレッドの、いま書いた1件（behavior.md 4-3 の表）。
        // 知らせは出さない。打ったものがその場に出るので二重になる（4-6）
        return redirect()->to(
            route('messages.show', $message->getKey()).'#message-'.$返信->getKey()
        );
    }
}

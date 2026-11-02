<?php

namespace App\Http\Controllers;

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
    public function store(Request $request, Message $message): RedirectResponse
    {
        // 見られないチャンネルへの返信はここで 404（permissions-api.md 1-5）。
        // ポリシーに任せると 403 になり、「そこに部屋がある」ことが漏れる。
        // 絞り込みは visibleTo スコープ 1 か所を通す（CLAUDE.md）
        $this->visibleChannel($request, $message->channel_id);

        // 列C ＋ △4。返信の ID に返信しようとしたら 403。親が削除済みでも通る
        // （回答6 / questions.md 決定1 の出口1-d）
        $this->authorize('reply', $message);

        // $request->validate() だと、落ちたときにエラーが default の袋に入る。
        // SC-07 には name="body" の欄が3種類（左の投稿欄＝default・編集中＝edit・
        // 返信欄＝reply）あるので、**袋を reply に分ける**。分けないと、別の欄に
        // 打った下書きがこちらへ流れ込む（単位4 が編集中で踏んだのと同じ穴）。
        // 文言は検査のそば（validate の第2引数にあたる位置）に置く（CLAUDE.md）
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            // 戻り先は同じスレッド。返信欄の下にエラー文が出る（screens.md 4-6）
            return redirect()->route('messages.show', $message)
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

    /**
     * 返信の入力チェック（screens.md 4-6）。
     *
     * **新しく書くときと同じチェックを、同じ数だけ**かける（screens.md 4-6 が
     * SC-06 と SC-07 に同じ8行を並べている。T-5-10 がその数を見る）。
     *
     * 前後の空白と改行は TrimStrings が落とし、空になった値は
     * ConvertEmptyStringsToNull が null にするので required で弾ける。上限も
     * その後の字数で数え、保存もその値（screens.md 4-1 / behavior.md 3-8）。
     *
     * いまは MessageController と同じものが2か所にある。**次のコミットで
     * `Concerns\ValidatesMessageBody` に寄せて1か所にする**（置き場は
     * behavior.md 3-8 の表どおり、コントローラ側のまま）。
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

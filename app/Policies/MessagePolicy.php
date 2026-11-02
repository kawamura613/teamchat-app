<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * メッセージに対する可否（permissions-api.md 1-3 の △3）。
 *
 * update・delete のどちらも、メッセージ自体の判定（本人か・削除済みでないか）
 * より先に、必ず ChannelPolicy@view（いまもそのチャンネルを見られるか）を通す
 * （permissions-api.md 1-6 / behavior.md 2-3 の決めどころ2）。順番を逆にすると、
 * プライベートチャンネルから外された人が自分の過去のメッセージを直せてしまい
 * （仕様書 3-3 に反する）、403 でそのチャンネルの存在も漏れる。
 *
 * reply（列C ＋ △4）だけは向きが違う。親が削除済みでも通す（回答6）。
 */
class MessagePolicy
{
    /**
     * F-15 メッセージ編集（列D ＋ △3）。
     */
    public function update(User $user, Message $message): Response
    {
        return $this->own($user, $message);
    }

    /**
     * F-16 メッセージ削除（列D ＋ △3）。
     */
    public function delete(User $user, Message $message): Response
    {
        return $this->own($user, $message);
    }

    /**
     * F-19 返信投稿（列C ＋ △4 / permissions-api.md 1-4）。
     *
     * 順番は update・delete と同じで、**まず ChannelPolicy@view を通す**
     * （permissions-api.md 1-6）。
     *
     * (1) いまもそのチャンネルを見られるか。見られなければ 404。403 を返すと
     *     「そこに部屋がある」ことが漏れる（1-5）
     * (2) 親が返信なら 403。返信は1段までで、返信に返信は付けられない（回答8）。
     *     その返信は本人のパネルに並んでいるので隠すものがなく、無いのは操作の
     *     ほうなので 403（permissions-api.md 1-5 の △4 の行）
     * (3) **trashed() は見ない。**ここが own() と逆で、削除されたメッセージにも
     *     返信を続けられる（回答6 / questions.md 決定1 の出口1-d）。ここで
     *     落とすと、跡地にぶら下がった会話が続けられなくなる
     */
    public function reply(User $user, Message $message): Response
    {
        if ($user->cannot('view', $message->channel)) {
            return Response::denyAsNotFound();
        }

        if ($message->parent_id !== null) {
            // 文言は errors/403.blade.php が持つ（behavior.md 3-3）
            return Response::deny();
        }

        return Response::allow();
    }

    /**
     * △3 の3つを、決まった順番で見る（permissions-api.md 1-4）。
     *
     * (1) いまもそのチャンネルを見られるか。見られなければ 404。403 を返すと、
     *     外されたあともそのチャンネルが残っていることが分かってしまう（1-5）
     * (2) 自分が書いたものか。違えば 403。そのメッセージは本人の画面に
     *     並んでいるので、隠す意味がない（1-5）
     * (3) まだ削除されていないか。削除済みは 404（Q-19）。ふだんはルートモデル
     *     バインディングの既定で先に落ちるが、withTrashed() で引いた行を
     *     渡されたときも同じ答えになるよう、ここでも見る
     */
    private function own(User $user, Message $message): Response
    {
        if ($user->cannot('view', $message->channel)) {
            return Response::denyAsNotFound();
        }

        if ($message->user_id !== $user->id) {
            // 文言は errors/403.blade.php が持つ（behavior.md 3-3）
            return Response::deny();
        }

        return $message->trashed()
            ? Response::denyAsNotFound()
            : Response::allow();
    }
}

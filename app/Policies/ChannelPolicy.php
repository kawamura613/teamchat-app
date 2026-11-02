<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * チャンネルに対する可否（permissions-api.md 1-3 の権限マトリクス）。
 */
class ChannelPolicy
{
    /**
     * 列C: このチャンネルを見られるか。
     *
     * 画面からチャンネルを取るときは visibleTo スコープで絞って 404 にするので
     * （permissions-api.md 1-5）、この判定はそこを通らない呼び出しが使う。
     * 単位4 の MessagePolicy は、メッセージ自体の判定（本人か・削除済みでないか）より
     * 先にここを通す（1-6）。
     */
    public function view(User $user, Channel $channel): bool
    {
        // 「見られるか」の定義は visibleTo スコープ 1 か所に置く。ここに同じ条件を
        // 書き直すと物差しが 2 つになり、スコープを広げたときに静かに食い違う
        // （CLAUDE.md「visibleTo スコープだけを通す。自前の where を書かない」）。
        //
        // 1 枚の画面でメッセージ 1 件ごとに呼ばれるので、答えを覚えるのは
        // Channel@isVisibleTo に任せる（permissions-api.md 1-6）
        return $channel->isVisibleTo($user);
    }

    /**
     * 列E: 作成者だけが編集できる（仕様書 3-2）。
     */
    public function update(User $user, Channel $channel): bool
    {
        return $channel->created_by === $user->id;
    }

    /**
     * 列E: 作成者だけが削除できる（仕様書 3-2）。
     */
    public function delete(User $user, Channel $channel): bool
    {
        return $channel->created_by === $user->id;
    }

    /**
     * 列E ＋ △1: メンバー画面を開ける人（F-10・F-11 / permissions-api.md 1-4）。
     *
     * 公開チャンネルにはメンバーという状態が無いので（data.md 1-3 の決定2）、
     * **作成者であってもこの画面は開けない。**その画面は存在しないので 404 に
     * する（403 だと「プライベート専用の画面がある」ことになる・1-5）。
     * 見えているが作成者でないときは 403（そのチャンネルは本人に見えている）。
     */
    public function manageMembers(User $user, Channel $channel): Response
    {
        if (! $channel->is_private) {
            return Response::denyAsNotFound();
        }

        return $channel->created_by === $user->id
            ? Response::allow()
            // 文言は errors/403.blade.php が持つ（behavior.md 3-3）
            : Response::deny();
    }

    /**
     * 列E ＋ △2: メンバーを外せる人（F-12 / permissions-api.md 1-4）。
     *
     * △1 に加えて、**作成者の行は外せない。作成者本人の操作でも外せない**
     * （回答4）。画面の「外す」は押せない状態で残すが、URL を直接叩かれたら
     * ここで 403 にする（1-5。作成者にはそのチャンネルもメンバー画面も
     * 見えているので、隠すものがない）。
     */
    public function removeMember(User $user, Channel $channel, User $target): Response
    {
        $開ける = $this->manageMembers($user, $channel);

        if ($開ける->denied()) {
            return $開ける;
        }

        return $channel->created_by === $target->id
            ? Response::deny()
            : Response::allow();
    }
}

<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;

/**
 * チャンネルに対する可否（permissions-api.md 1-3 の権限マトリクス）。
 *
 * manageMembers（列E ＋ △1）は単位3（メンバー管理）で足す。
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
        // （CLAUDE.md「visibleTo スコープだけを通す。自前の where を書かない」）
        return Channel::visibleTo($user)->whereKey($channel->getKey())->exists();
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
}

<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelMemberController extends Controller
{
    /**
     * SC-09 メンバー管理（F-10）。
     *
     * プライベートチャンネルの作成者だけが開ける。公開チャンネルの ID で
     * 来たら 404（permissions-api.md 1-4 の △1）。判定は ChannelPolicy。
     */
    public function index(Request $request, int $channel): View
    {
        $channel = $this->visibleChannel($request, $channel);
        $this->authorize('manageMembers', $channel);

        return view('channels.members', [
            'channel' => $channel,
            'members' => $this->members($channel),
        ]);
    }

    /**
     * F-11 メンバー追加（列E ＋ △1）。
     */
    public function store(Request $request, int $channel): RedirectResponse
    {
        $channel = $this->visibleChannel($request, $channel);
        $this->authorize('manageMembers', $channel);

        // 検査の中で引いた人をそのまま使う。二度引かないため参照で受ける
        $見つかった人 = null;

        // 文言は screens.md 4-7 の表から写す。locale が en なので、型の
        // ルールにも日本語を付ける（CLAUDE.md）
        $request->validate([
            'member' => [
                // bail を先頭に置く。無いと型が合わないまま下のクロージャまで
                // 進み、配列が where に渡って落ちる
                'bail',
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($channel, &$見つかった人) {
                    // ぴたり一致する社員を1人だけ（questions.md Q-14）。
                    // 打ちながら候補は出さないので、部分一致では探さない
                    $候補 = User::where('name', $value)->orWhere('email', $value)->get();

                    if ($候補->isEmpty()) {
                        $fail('該当する社員が見つかりません');

                        return;
                    }

                    // メールアドレスは一意なので、絞れないのは表示名のときだけ
                    if ($候補->count() > 1) {
                        $fail('同じ表示名の社員が複数いるためメールアドレスで指定してください');

                        return;
                    }

                    $見つかった人 = $候補->first();

                    // 二重追加は (channel_id, user_id) の一意制約でも弾けるが、
                    // 画面には文言を出す（data.md 2-5 / screens.md 4-7）
                    if ($channel->members()->whereKey($見つかった人->getKey())->exists()) {
                        $fail('この社員はすでにメンバーです');
                    }
                },
            ],
        ], [
            'member.required' => '表示名またはメールアドレスを入力してください',
            'member.string' => '表示名またはメールアドレスを入力してください',
        ]);

        // 1 行の INSERT で終わるのでトランザクションは要らない（behavior.md 3-5）
        $channel->members()->attach($見つかった人);

        return redirect()->route('channels.members.index', $channel)
            ->with('status', 'メンバーを追加しました');
    }

    /**
     * F-12 メンバー削除（列E ＋ △2）。
     */
    public function destroy(Request $request, int $channel, User $user): RedirectResponse
    {
        $channel = $this->visibleChannel($request, $channel);
        // 作成者の行は、作成者本人の操作でも外せない（403）
        $this->authorize('removeMember', [$channel, $user]);

        // channel_user の行を 1 つ消すだけ。messages には触らないので、
        // その人が過去に書いたメッセージ・返信はそのまま残る
        // （data.md 2-3 / questions.md Q-15）
        $channel->members()->detach($user);

        return redirect()->route('channels.members.index', $channel)
            ->with('status', 'メンバーを外しました');
    }

    /**
     * 並びは「作成者を先頭にし、あとは追加された順」（data.md 2-3 /
     * screens.md 3-4）。channel_user は日時の列を持たないので、追加された順は
     * 中間テーブルの id の順。
     *
     * @return Collection<int, User>
     */
    private function members(Channel $channel): Collection
    {
        return $channel->members()
            // 作成者を先頭に。MySQL の真偽は 1/0 なので降順で先に来る
            ->orderByRaw('users.id = ? desc', [$channel->created_by])
            ->orderBy('channel_user.id')
            ->get();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ChannelController extends Controller
{
    /**
     * SC-03 チャンネル一覧（F-06）。
     */
    public function index(Request $request): View
    {
        $channels = Channel::visibleTo($request->user())
            // 行に「作成者：◯◯」を出すので先読みする（N+1 を避ける・CLAUDE.md）
            ->with('creator')
            // 作成された順（questions.md Q-06 の想定）
            ->orderBy('id')
            ->get();

        return view('channels.index', ['channels' => $channels]);
    }

    /**
     * SC-04 チャンネルを作る（F-07）。
     */
    public function create(): View
    {
        return view('channels.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);

        $channel = DB::transaction(function () use ($request, $validated) {
            $channel = Channel::create($validated + ['created_by' => $request->user()->id]);

            // プライベートだけ行を作る（data.md 1-3 の決定2）。片方だけ成ると
            // 作成者が入っていないプライベートチャンネルができて誰も管理できない
            // ので、同じトランザクションの中で入れる（behavior.md 3-5）
            if ($channel->is_private) {
                $channel->members()->attach($request->user());
            }

            return $channel;
        });

        return redirect()->route('channels.show', $channel)
            ->with('status', 'チャンネルを作成しました');
    }

    /**
     * SC-06 チャンネル（F-13・F-14・F-17）。
     *
     * 単位2 では見出しだけ。メッセージ一覧・投稿欄・ホバー操作は単位4。
     */
    public function show(Request $request, int $channel): View
    {
        return view('channels.show', ['channel' => $this->visibleChannel($request, $channel)]);
    }

    /**
     * SC-05 チャンネルを編集する（F-08）。
     */
    public function edit(Request $request, int $channel): View
    {
        $channel = $this->visibleChannel($request, $channel);
        $this->authorize('update', $channel);

        return view('channels.edit', ['channel' => $channel]);
    }

    public function update(Request $request, int $channel): RedirectResponse
    {
        $channel = $this->visibleChannel($request, $channel);
        $this->authorize('update', $channel);

        // 公開範囲は SC-05 に欄が無いので検査しない。送られてきても
        // $validated に入らないため無視される（screens.md 4-4 / questions.md Q-09）
        $channel->update($this->validateForm($request, $channel));

        return redirect()->route('channels.show', $channel)
            ->with('status', 'チャンネルを保存しました');
    }

    /**
     * SC-10 チャンネル削除の確認（F-09）。
     */
    public function confirmDelete(Request $request, int $channel): View
    {
        $channel = $this->visibleChannel($request, $channel);
        $this->authorize('delete', $channel);

        return view('channels.delete', [
            'channel' => $channel,
            // 返信でないものと返信を別々に数え、「削除されました」になっている
            // ものも含める。画面を開いた時点の値（screens.md 3-5 の SC-10）
            'messageCount' => $channel->messages()->withTrashed()->whereNull('parent_id')->count(),
            'replyCount' => $channel->messages()->withTrashed()->whereNotNull('parent_id')->count(),
        ]);
    }

    public function destroy(Request $request, int $channel): RedirectResponse
    {
        $channel = $this->visibleChannel($request, $channel);
        $this->authorize('delete', $channel);

        $validator = Validator::make($request->all(), [
            'name' => [
                // bail を先頭に置くと、空のときに「入力してください」と
                // 「一致しません」が同時に出ない（screens.md 4-1「その欄の下」に1文）
                'bail',
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($channel) {
                    // 一字一句の一致を求める（screens.md 4-5）
                    if ($value !== $channel->name) {
                        $fail('チャンネル名が一致しません');
                    }
                },
            ],
        ], [
            'name.required' => 'チャンネル名を入力してください',
        ]);

        if ($validator->fails()) {
            // 確認画面にとどまる。編集画面には戻さない（screens.md 4-5）
            return redirect()->route('channels.delete', $channel)
                ->withErrors($validator)
                ->withInput();
        }

        DB::transaction(function () use ($channel) {
            // channels は論理削除を使わないので、これは物理削除。中の messages と
            // channel_user は外部キーの連鎖で消える。Eloquent を経由しないので
            // messages の SoftDeletes に邪魔されない（data.md 2-5 / behavior.md 3-5）
            $channel->delete();
        });

        return redirect()->route('channels.index')
            ->with('status', 'チャンネルを削除しました');
    }

    /**
     * URL の {channel} は、必ず「見られるチャンネル」に絞って取る。
     *
     * 見られないものはここで 404 になる。ポリシーに任せると 403 が返り、
     * 「そこに部屋がある」ことが漏れる（permissions-api.md 1-5・1-6）。
     * 作成者かどうかは、これを通したあとに authorize で見る。
     */
    private function visibleChannel(Request $request, int $id): Channel
    {
        return Channel::visibleTo($request->user())->findOrFail($id);
    }

    /**
     * SC-04・SC-05 の入力チェック（screens.md 4-4）。文言は同じ表から写す。
     *
     * $current を渡すと編集。公開範囲は SC-04 だけで見る。
     *
     * @return array<string, mixed>
     */
    private function validateForm(Request $request, ?Channel $current = null): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:50',
                function (string $attribute, mixed $value, Closure $fail) use ($request, $current) {
                    // 重複はその人が見られる範囲だけで見る。見えないプライベート
                    // チャンネルと名前がぶつかってもそのまま作れる（存在を隠すほうを
                    // 優先した判断・screens.md 4-4 / questions.md Q-38 は未回答）
                    $duplicated = Channel::visibleTo($request->user())
                        ->where('name', $value)
                        // 編集では自分自身を重複の相手に数えない（名前を変えずに
                        // 説明だけ直せるようにするため・screens.md 4-4）
                        ->when($current, function (Builder $query) use ($current) {
                            $query->whereKeyNot($current->getKey());
                        })
                        ->exists();

                    if ($duplicated) {
                        $fail('この名前のチャンネルはすでにあります');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:200'],
        ];

        if (! $current) {
            $rules['is_private'] = ['required', 'boolean'];
        }

        return $request->validate($rules, [
            'name.required' => 'チャンネル名を入力してください',
            'name.max' => 'チャンネル名は50文字以内で入力してください',
            'description.max' => '説明は200文字以内で入力してください',
            'is_private.required' => '公開範囲を選択してください',
        ]);
    }
}

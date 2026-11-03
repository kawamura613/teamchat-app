<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SC-08 検索結果（F-20 ＋ F-05 / permissions-api.md 2-2 の `GET /search`）。
 */
class SearchController extends Controller
{
    /** 1 回に出す上限（questions.md Q-27 の想定 / screens.md 3-5） */
    private const 上限 = 50;

    public function index(Request $request): View
    {
        // **「q が無い」と「q が空」を分ける**（questions.md 決定9 / behavior.md 3-2）。
        // 付いていなければ検査にかけず、「まだ検索していない」状態の SC-08 を出す。
        // 上部バーの検索窓を押しただけの人・URL を直接開いた人に、いきなりエラー文を
        // 見せないため。
        //
        // `?q=` は ConvertEmptyStringsToNull で値が null になるが**キーは在る**ので
        // has() は true になり、下の required で「キーワードを入力してください」に
        // なる（behavior.md 3-8 の初期ミドルウェアの表）。
        if (! $request->has('q')) {
            return view('search.index', ['keyword' => null, 'results' => null]);
        }

        $keyword = $this->検査する($request);

        return view('search.index', [
            'keyword' => $keyword,
            'results' => $this->探す($request, $keyword),
        ]);
    }

    /**
     * キーワードの入力チェック（screens.md 4-8）。
     *
     * 文言は validate() の第 2 引数に置く（CLAUDE.md / behavior.md 3-8 の表）。
     */
    private function 検査する(Request $request): string
    {
        try {
            $validated = $request->validate([
                // bail を先頭に置く。無いと required と max のエラー文が同時に出る
                'q' => ['bail', 'required', 'string', 'max:1000'],
            ], [
                'q.required' => 'キーワードを入力してください',
                // 配列などが送られてきたときも欄の文言を出す（screens.md 4-1）
                'q.string' => 'キーワードを入力してください',
                'q.max' => 'キーワードは1,000文字以内で入力してください',
            ]);
        } catch (ValidationException $e) {
            // **戻り先を SC-08 に付け替える。**既定の back() は直前の SC-06 へ戻して
            // しまい、そこにはエラー文を出す欄が無い（behavior.md 3-2 の「302 で元の
            // 画面へ戻し、欄の下にエラー文」）。打った内容は ValidationException が
            // withInput() するので、戻り先の欄に残る（screens.md 4-1）
            throw $e->redirectTo(route('search'));
        }

        return $validated['q'];
    }

    /**
     * 当てて並べる（F-20）。
     *
     * **忘れると静かに壊れる決めを、この 1 か所に集めてある。**
     *
     * @return Collection<int, Message>
     */
    private function 探す(Request $request, string $keyword): Collection
    {
        return Message::query()
            // **絞り込みは visibleTo スコープ 1 か所だけを通す。**自前の where を
            // 足すと、それが抜け道になる（permissions-api.md 1-6 / CLAUDE.md）
            ->whereHas('channel', fn (Builder $query) => $query->visibleTo($request->user()))
            // 当て方は部分一致・大文字小文字を区別しない（決定10・仮）。`%`・`_`・`\`
            // はエスケープして文字として扱う（`100%` で全件が当たらないように）
            ->where('body', 'like', '%'.addcslashes($keyword, '\\%_').'%')
            // **withTrashed() を書かない。**既定の除外に任せるのが決定1 の出口2
            // （behavior.md 3-6）。ここに足すと、消した本文が結果と抜粋に出る
            //
            // **parent_id でも絞らない。**返信も検索の対象（Q-28 の想定）
            //
            // 行にチャンネル名と投稿者名を出すので先読みする（CLAUDE.md の N+1 の決め）
            ->with(['user', 'channel'])
            // 新しい順。同じ時刻のときは id で並び順を決める（Q-27 の想定）
            ->latest()
            ->orderByDesc('id')
            ->limit(self::上限)
            ->get();
    }
}

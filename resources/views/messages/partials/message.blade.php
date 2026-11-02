{{-- メッセージ1件（screens.md 3-3 の「メッセージ一覧」/ mockup の .msg）。

     **本文と跡地の出し分けは、この部品の中だけで行う**（questions.md 決定1 の
     出口1 / behavior.md 2-3 の決めどころ7 / T-4-20）。画面ごとに書くと、どれか1つで
     出し分けを忘れたときに削除済みの本文が出る。SC-06 の一覧も SC-07 のパネル
     （元のメッセージ・返信）も、全部ここを通る。

     アンカーは跡地にも振る（behavior.md 4-4）。

     $place … どちらの画面に出しているか。'channel'（SC-06 の一覧）か
              'thread'（SC-07 のパネル）。既定値は持たせない。渡し漏れで落ちる
              ほうが、黙って片方の見え方になるより安全。 --}}
@php
    $編集中 = $editingId === $message->id;
    // 編集・ごみ箱を出す条件は **MessagePolicy に聞く**（screens.md 3-3・4-9）。
    // ここに条件を書き写すと物差しが 2 つになり、ポリシーに条件が増えたときに
    // ボタンだけ出続けて押すたび 403 になる（CLAUDE.md「認可の順番」）。
    // F-15 と F-16 は権限マトリクスの行が同じ（△3）なので 1 回聞けば足りる
    // （permissions-api.md 1-3）。1 件につき 1 回に抑えるため変数に入れる
    $直せる = auth()->user()->can('update', $message);
    $返信件数 = $message->replies_count ?? 0;
    // パネルの中では返信アイコンも「返信◯件」も出さない（screens.md 3-5）。
    // 元のメッセージは行き先が自分自身のスレッドになり、返信は1段までで
    // 付けられない（回答8）
    $一覧に出している = $place === 'channel';
    // 操作のあとの戻り先。隠し項目 from の 2 択だけを送る（behavior.md 4-3）
    $戻り先 = $一覧に出している ? 'channel' : 'thread';
@endphp
<article class="msg{{ $message->trashed() ? ' msg--deleted' : '' }}" id="message-{{ $message->id }}">
    @if ($一覧に出している || $直せる)
        <div class="msg__acts">
            {{-- 返信 → SC-07。削除されたメッセージにも残す（screens.md 3-3 / AC-4-4） --}}
            @if ($一覧に出している)
                <a class="icon-btn" href="{{ route('messages.show', $message) }}" title="返信"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="M2.5 3.5h11v7h-6l-3 2.5v-2.5h-2z"/></svg></a>
            @endif
            @if ($直せる)
                <a class="icon-btn" href="{{ route('messages.edit', $message) }}" title="編集" data-edit-open><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="M11 2.6 13.4 5 5.4 13H3v-2.4z"/></svg></a>
                {{-- 確認は JavaScript の1枚で、画面は増やさない（questions.md 決定11・仮） --}}
                <form method="POST" action="{{ route('messages.destroy', $message) }}" data-confirm="このメッセージを削除しますか？　元に戻せません。">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="from" value="{{ $戻り先 }}">
                    <button class="icon-btn" type="submit" title="削除"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.6 8.5h5.8l.6-8.5"/></svg></button>
                </form>
            @endif
        </div>
    @endif
    @include('partials.avatar', ['user' => $message->user])
    <div>
        <div class="msg__head">
            {{-- 投稿者名と日時は跡地にも残す（behavior.md 1-2） --}}
            <span class="msg__name">{{ $message->user->name }}</span>
            {{-- 画面に出すのは created_at。編集しても動かない（data.md 2-4 / Q-17） --}}
            <span class="msg__time">{{ $message->created_at->format('Y/m/d H:i') }}</span>
        </div>
        @if ($message->trashed())
            {{-- 跡地。本文は出さない。「編集済み」の印もここには出ない（behavior.md 1-2） --}}
            <div class="msg__deleted-note">このメッセージは削除されました</div>
        @else
            <div class="msg__body" @if ($編集中) hidden @endif>{{ $message->body }}@if ($message->edited_at)<span class="msg__edited">編集済み</span>@endif</div>
            @if ($直せる)
                @include('messages.partials.editor', ['message' => $message, 'open' => $編集中, 'place' => $place])
            @endif
        @endif
        @if ($返信件数 >= 1 && $一覧に出している)
            {{-- 削除されたメッセージでも消さない（screens.md 3-3）。削除済みの返信も
                 数に入っている（behavior.md 1-4）。押すと SC-07 が開く --}}
            <a class="thread-link" href="{{ route('messages.show', $message) }}"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="M2.5 3.5h11v7h-6l-3 2.5v-2.5h-2z"/></svg>返信{{ $返信件数 }}件</a>
        @endif
    </div>
</article>

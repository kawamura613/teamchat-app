{{-- SC-07 の右のパネル（screens.md 3-5 / mockup/thread.html の <aside class="thread">）。
     幅 400px 固定は .app__body--thread が決める。

     元のメッセージも返信も、一覧と同じ messages.partials.message を通す。
     **本文と跡地の出し分けをここに書き写さない**（questions.md 決定1 の出口1 /
     T-4-20）。place がパネル側の値なので、返信アイコンと「返信◯件」は出ず、
     操作のあとの戻り先は thread になる。

     元のメッセージだけ place を 'thread-origin' にする。**親は左の一覧にも
     出ているので、id が重複しないよう接頭辞を付ける**（behavior.md 4-4）。
     返信はパネルにしか出ないので 'thread' のまま（接頭辞を付けると、投稿後の
     #message-{新しい返信のID} の着地が外れる）。 --}}
@php
    // 返信欄に戻すのは、落ちたのが返信欄のときだけ。左の投稿欄は 'default'、
    // 編集中は 'edit' の袋なので、袋で見分ける。同じ画面に name="body" の欄が
    // 3種類あるため、$errors を素で見ると別の欄の下書きが流れ込む
    $返信欄のエラー = $errors->getBag('reply')->first('body');
    $返信欄の本文 = $返信欄のエラー && is_string($値 = old('body', '')) ? $値 : '';
@endphp
<aside class="thread">
    <div class="thread__head">
        <div>
            <div class="t-label">スレッド</div>
            <div class="t-caption"><span class="sig">@include('channels.partials.sign')</span>{{ $channel->name }}</div>
        </div>
        {{-- × → SC-06。錨を付けて、開く前に見ていた位置に戻す。付けないと素で
             開いた扱いになり、app.js が一覧を末尾まで送って元の位置を見失う
             （behavior.md 4-4） --}}
        <a class="icon-btn" href="{{ route('channels.show', $channel->id) }}#message-{{ $parent->id }}" title="閉じる"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg></a>
    </div>
    <div class="thread__body">
        {{-- 元のメッセージ。**削除されていても出す**（跡地の枠＋投稿者名＋日時）。
             ここが 404 にならないことが決定1 の出口1-b --}}
        <div class="thread__origin">
            @include('messages.partials.message', ['message' => $parent, 'editingId' => $editingId, 'place' => 'thread-origin'])
        </div>
        @if ($parent->replies_count >= 1)
            {{-- 元のメッセージと返信の間の区切り。削除済みの返信も数に入っている
                 （behavior.md 1-4）。0件のときは区切るものがないので出さない
                 （screens.md 3-5） --}}
            <div class="thread__count">返信{{ $parent->replies_count }}件</div>
        @endif
        @foreach ($replies as $返信)
            @include('messages.partials.message', ['message' => $返信, 'editingId' => $editingId, 'place' => 'thread'])
        @endforeach
    </div>
    {{-- 返信の投稿欄。**親が削除済みでも出したまま**（回答6。そこで会話が
         続けられなくなるほうが困る） --}}
    <div class="composer">
        <form method="POST" action="{{ route('messages.replies.store', $parent) }}">
            @csrf
            <div class="composer__box{{ $返信欄のエラー ? ' is-error' : '' }}">
                <textarea class="composer__ta" id="reply-body" name="body" placeholder="返信を送る">{{ $返信欄の本文 }}</textarea>
                <div class="composer__foot">
                    {{-- 欄の下は左にエラー文・右に文字数（screens.md 4-1） --}}
                    <span class="inline-3">
                        @if ($返信欄のエラー)
                            <span class="err">{{ $返信欄のエラー }}</span>
                        @endif
                        <span class="count" data-count-for="reply-body" data-count-max="1000" data-count-suffix="文字">{{ number_format(mb_strlen($返信欄の本文)) }} / 1,000 文字</span>
                    </span>
                    <button class="btn btn--primary btn--sm" type="submit">送信</button>
                </div>
            </div>
        </form>
    </div>
</aside>

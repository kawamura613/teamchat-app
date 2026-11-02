{{-- 編集中（screens.md 3-3 の「編集中」/ 4-6 / design-guide.md 4）。
     本文があった場所がこれに変わる。別の画面もダイアログも使わない。

     JavaScript があるときは往復なしでここに差し替え、無効なときは
     messages.edit の URL で同じ状態の画面が開く（behavior.md 4-1 の #2・4-2）。
     見た目はフォームの既存の部品（.field・.field-foot・.inline-2）で足りるので、
     app.css への追記は要らない。

     $open     … この1件がいま編集中か。URL（messages.edit）で開いたときだけ true。
     $from     … 操作のあとの戻り先（'channel' か 'thread'）。**値は呼び出し側の
                 messages.partials.message が決める**。ここで組み直さない
                 （2 か所で別々に組むと同じ行の削除と編集で食い違う・behavior.md 4-3）。
     $idPrefix … 入力欄の id に付ける接頭辞。SC-07 のパネルの「元のメッセージ」だけ
                 'thread-' が入る（親は左の一覧にも出るので id が重複する・4-4）。 --}}
@php
    // どちらのフォームが落ちたかは、**エラーの袋**で見分ける。$open だけで見ると、
    // 同じ画面の投稿欄が落ちたときの old('body') をここに流し込んでしまい、
    // 「保存」でメッセージの本文が新規投稿のつもりの文に上書きされる
    $編集中のエラー = $open ? $errors->getBag('edit')->first('body') : null;
    // 打った内容を戻すのは、落ちたのがこの1件の編集中のときだけ
    $編集中の本文 = $編集中のエラー && is_string($値 = old('body', $message->body)) ? $値 : $message->body;
    // 「やめる」は元の画面のその1件へ。SC-07 なら、返信は親のスレッド、
    // 元のメッセージは自分自身のスレッド。錨は行き先の画面の左の一覧に当たる
    // ので接頭辞は付けない（behavior.md 4-4）
    $やめる先 = $from === 'thread'
        ? route('messages.show', $message->parent_id ?? $message->id)
        : route('channels.show', $message->channel_id);
    $欄のid = $idPrefix.'message-body-'.$message->id;
@endphp
<form method="POST" action="{{ route('messages.update', $message) }}" data-edit-form="{{ $open ? 'open' : 'closed' }}" @unless ($open) hidden @endunless>
    @csrf
    @method('PATCH')
    <input type="hidden" name="from" value="{{ $from }}">
    <div class="field @if ($編集中のエラー) is-error @endif" style="margin-bottom:var(--sp-2)">
        <textarea class="textarea" id="{{ $欄のid }}" name="body">{{ $編集中の本文 }}</textarea>
        {{-- エラー文は入力欄の下・「保存」「やめる」の上。右に文字数（screens.md 4-1・4-6） --}}
        <div class="field-foot">
            @if ($編集中のエラー)
                <span class="err">{{ $編集中のエラー }}</span>
            @else
                <span class="help"></span>
            @endif
            <span class="count" data-count-for="{{ $欄のid }}" data-count-max="1000" data-count-suffix="文字">{{ number_format(mb_strlen($編集中の本文)) }} / 1,000 文字</span>
        </div>
    </div>
    <div class="inline-2">
        <button class="btn btn--primary btn--sm" type="submit">保存</button>
        {{-- 何も送らない。元の本文に戻すだけ（screens.md 4-6） --}}
        <a class="btn btn--sm" href="{{ $やめる先 }}#message-{{ $message->id }}" data-edit-cancel>やめる</a>
    </div>
</form>

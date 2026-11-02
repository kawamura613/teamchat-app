{{-- 編集中（screens.md 3-3 の「編集中」/ 4-6 / design-guide.md 4）。
     本文があった場所がこれに変わる。別の画面もダイアログも使わない。

     JavaScript があるときは往復なしでここに差し替え、無効なときは
     messages.edit の URL で同じ状態の画面が開く（behavior.md 4-1 の #2・4-2）。
     見た目はフォームの既存の部品（.field・.field-foot・.inline-2）で足りるので、
     app.css への追記は要らない。

     $open … この1件がいま編集中か。URL（messages.edit）で開いたときだけ true。 --}}
@php
    // 打った内容を戻すのは、落ちたのがこの1件のときだけ。投稿欄が落ちた
    // ときの old('body') を、ほかのメッセージの入力欄に流し込まないため
    $編集中の本文 = $open && is_string($値 = old('body', $message->body)) ? $値 : $message->body;
    $編集中のエラー = $open ? $errors->getBag('edit')->first('body') : null;
@endphp
<form method="POST" action="{{ route('messages.update', $message) }}" data-edit-form="{{ $open ? 'open' : 'closed' }}" @unless ($open) hidden @endunless>
    @csrf
    @method('PATCH')
    <div class="field @if ($編集中のエラー) is-error @endif" style="margin-bottom:var(--sp-2)">
        <textarea class="textarea" id="message-body-{{ $message->id }}" name="body">{{ $編集中の本文 }}</textarea>
        {{-- エラー文は入力欄の下・「保存」「やめる」の上。右に文字数（screens.md 4-1・4-6） --}}
        <div class="field-foot">
            @if ($編集中のエラー)
                <span class="err">{{ $編集中のエラー }}</span>
            @else
                <span class="help"></span>
            @endif
            <span class="count" data-count-for="message-body-{{ $message->id }}" data-count-max="1000" data-count-suffix="文字">{{ number_format(mb_strlen($編集中の本文)) }} / 1,000 文字</span>
        </div>
    </div>
    <div class="inline-2">
        <button class="btn btn--primary btn--sm" type="submit">保存</button>
        {{-- 何も送らない。元の本文に戻すだけ（screens.md 4-6） --}}
        <a class="btn btn--sm" href="{{ route('channels.show', $message->channel_id) }}#message-{{ $message->id }}" data-edit-cancel>やめる</a>
    </div>
</form>

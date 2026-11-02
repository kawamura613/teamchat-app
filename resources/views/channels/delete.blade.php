{{-- SC-10 チャンネル削除の確認（F-09 / screens.md 3-5 の SC-10）
     件数は「返信でないメッセージ」と「返信」を別々に数え、削除済みも含める。
     この画面を開いた時点の値（questions.md Q-12 の残り）。
     入力が不一致ならこの画面にとどまる。SC-05 には戻さない（screens.md 4-5）。 --}}
@extends('layouts.app')

@section('title', 'チャンネルを削除する')

@section('content')
    @php
        // 戻ってきた入力は文字列とは限らない（screens.md 4-1 の「型が合わない値」）
        $打った名前 = is_string($値 = old('name', '')) ? $値 : '';
    @endphp
    <div class="page" style="max-width:680px">
        <h1 class="h-title" style="margin-bottom:var(--sp-6)">チャンネルを削除する</h1>
        <div class="card" style="border-color:var(--danger-line)">
            <div class="card__body">
                {{-- 記号（#／鍵）は付けない（モックアップのとおり） --}}
                <div class="h-heading" style="margin-bottom:var(--sp-2)">「{{ $channel->name }}」を削除しますか？</div>
                <p class="t-body t-muted" style="margin-bottom:var(--sp-4)">メッセージ {{ $messageCount }}件と返信 {{ $replyCount }}件も削除されます。この操作は取り消せません。確認のためチャンネル名を入力してください。</p>
                <form method="POST" action="{{ route('channels.destroy', $channel) }}">
                    @csrf
                    @method('DELETE')
                    <div class="field @error('name') is-error @enderror">
                        <input class="input" type="text" name="name" value="{{ $打った名前 }}" placeholder="{{ $channel->name }}" style="max-width:280px">
                        @error('name')
                            <span class="err">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="inline-2" style="justify-content:flex-end;margin-top:var(--sp-5)">
                        <a class="btn" href="{{ route('channels.edit', $channel) }}">やめる</a>
                        <button class="btn btn--danger-solid" type="submit">削除する</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

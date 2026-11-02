{{-- 公開は #、プライベートは鍵（mockup/channels.html の .sig） --}}
@if ($channel->is_private)<svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3.25" y="7" width="9.5" height="6.5" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"/></svg>@else#@endif

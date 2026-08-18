{{--
    Shared search row for any Wood Art register.

    A plain GET form: the result is bookmarkable, survives a reload, and needs
    no script inside [data-wa-view].

    Expects:
      $action      — the URL to submit back to
      $q           — the current term
      $placeholder — what this particular register searches
--}}
<form method="GET" class="wap-search" action="{{ $action }}">
    <i class="bi bi-search"></i>
    <input type="search" name="q" value="{{ $q }}" class="wap-input"
           placeholder="{{ $placeholder ?? 'Search…' }}" aria-label="{{ $placeholder ?? 'Search' }}">
    <button type="submit" class="wap-btn wap-btn-ghost">Search</button>
    @if($q !== '')
    <a class="wap-btn wap-btn-ghost" href="{{ $action }}">Clear</a>
    @endif
</form>

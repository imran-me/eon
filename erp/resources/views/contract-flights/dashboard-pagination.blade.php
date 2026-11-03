<div class="fd-pagination" aria-label="{{ $paginationTitle }} pagination">
    <label for="fd{{ $paginationSection }}PageSize">Flights per page
        <select id="fd{{ $paginationSection }}PageSize" onchange="setFlightPageSize('{{ $paginationSection }}', this.value)">
            <option value="5" selected>5</option>
            <option value="10">10</option>
        </select>
    </label>
    <span id="fd{{ $paginationSection }}PageRange" aria-live="polite"></span>
    <div class="fd-page-actions">
        <button type="button" id="fd{{ $paginationSection }}PagePrevious" onclick="changeFlightPage('{{ $paginationSection }}', -1)" aria-label="Previous page of {{ strtolower($paginationTitle) }}" disabled>Previous</button>
        <span id="fd{{ $paginationSection }}PageNumber">Page 1 of 1</span>
        <button type="button" id="fd{{ $paginationSection }}PageNext" onclick="changeFlightPage('{{ $paginationSection }}', 1)" aria-label="Next page of {{ strtolower($paginationTitle) }}" disabled>Next</button>
    </div>
</div>

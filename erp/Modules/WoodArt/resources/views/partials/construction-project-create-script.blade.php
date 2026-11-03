<script>
(() => {
    const initProjectCreate = () => {
        const form = document.querySelector('form.cpc');
        if (!form || form.dataset.previewReady) return;
        form.dataset.previewReady = '1';
        const draw = () => {
            form.querySelectorAll('[data-preview]').forEach(output => {
                const input = form.elements[output.dataset.preview];
                output.textContent = input?.value?.trim() || '—';
            });
            form.querySelectorAll('[data-preview-select]').forEach(output => {
                const select = form.elements[output.dataset.previewSelect];
                output.textContent = select?.selectedOptions?.[0]?.text?.replace(/^●\s*/, '') || '—';
            });
        };
        form.addEventListener('input', draw);
        form.addEventListener('change', draw);
        draw();
    };
    initProjectCreate();
    document.addEventListener('wa:navigated', initProjectCreate);
})();
</script>

document.querySelectorAll('[data-live-party-search]').forEach(function (input) {
    const form = input.closest('form');
    if (!form) return;

    let timer = null;
    let submittedValue = input.value.trim();
    let composing = false;

    input.addEventListener('compositionstart', function () {
        composing = true;
    });
    input.addEventListener('compositionend', function () {
        composing = false;
        input.dispatchEvent(new Event('input'));
    });
    input.addEventListener('input', function () {
        if (composing) return;
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
            const value = input.value.trim();
            if (value === submittedValue) return;
            submittedValue = value;
            form.requestSubmit();
        }, 400);
    });
});

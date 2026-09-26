(() => {
    const native = document.querySelector('[data-share-native]');
    if (native) {
        if (!navigator.share) {
            native.hidden = true;
        } else {
            native.addEventListener('click', async () => {
                try {
                    await navigator.share({
                        title: native.dataset.shareTitle || document.title,
                        url: native.dataset.shareUrl || location.href,
                    });
                } catch (_) {
                    // User cancellation is normal and should not create UI noise.
                }
            });
        }
    }

    document.querySelectorAll('[data-copy-link]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.dataset.shareUrl || location.href;
            try {
                await navigator.clipboard.writeText(url);
                const original = button.textContent;
                button.textContent = 'Скопировано';
                window.setTimeout(() => {
                    button.textContent = original;
                }, 1600);
            } catch (_) {
                window.prompt('Скопируйте ссылку:', url);
            }
        });
    });
})();

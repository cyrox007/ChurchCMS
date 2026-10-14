(() => {
    'use strict';

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const animations = new WeakMap();

    const directSummary = (details) => {
        const first = details.firstElementChild;
        return first instanceof HTMLElement && first.tagName === 'SUMMARY'
            ? first
            : null;
    };

    const clearInlineState = (details) => {
        details.style.removeProperty('height');
        details.style.removeProperty('overflow');
    };

    const toggle = (details) => {
        const summary = directSummary(details);
        if (!(summary instanceof HTMLElement)
            || reduceMotion.matches
            || typeof details.animate !== 'function') {
            details.open = !details.open;
            return;
        }

        animations.get(details)?.cancel();

        const opening = !details.open;
        const startHeight = details.offsetHeight;

        if (opening) {
            details.open = true;
        }

        const endHeight = opening
            ? details.scrollHeight
            : summary.offsetHeight;

        details.style.height = `${startHeight}px`;
        details.style.overflow = 'hidden';

        const animation = details.animate(
            [
                {height: `${startHeight}px`, opacity: opening ? .75 : 1},
                {height: `${endHeight}px`, opacity: opening ? 1 : .9},
            ],
            {
                duration: 180,
                easing: 'cubic-bezier(.2,.7,.2,1)',
            },
        );

        animations.set(details, animation);

        animation.addEventListener('finish', () => {
            details.open = opening;
            clearInlineState(details);
            animations.delete(details);
        }, {once: true});

        animation.addEventListener('cancel', () => {
            clearInlineState(details);
            animations.delete(details);
        }, {once: true});
    };

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const summary = target.closest('summary');
        const details = summary?.parentElement;
        if (!(summary instanceof HTMLElement)
            || !(details instanceof HTMLDetailsElement)
            || !details.closest('.admin-app')) {
            return;
        }

        event.preventDefault();
        toggle(details);
    });
})();

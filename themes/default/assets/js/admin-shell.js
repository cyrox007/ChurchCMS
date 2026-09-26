(() => {
    'use strict';

    const frame = document.querySelector('[data-admin-frame]');
    const toggle = document.querySelector('[data-admin-sidebar-toggle]');
    const label = toggle?.querySelector('[data-admin-sidebar-toggle-label]');

    if (!(frame instanceof HTMLElement) || !(toggle instanceof HTMLButtonElement)) {
        return;
    }

    const desktop = window.matchMedia('(min-width: 56.01rem)');
    const storageKey = 'churchcms.admin.sidebar.collapsed';
    let collapsed = false;

    try {
        collapsed = window.localStorage.getItem(storageKey) === '1';
    } catch {
        collapsed = false;
    }

    const apply = () => {
        const active = desktop.matches && collapsed;

        frame.classList.toggle('admin-frame--collapsed', active);
        toggle.hidden = !desktop.matches;
        toggle.setAttribute('aria-expanded', active ? 'false' : 'true');
        toggle.setAttribute(
            'aria-label',
            active ? 'Показать боковое меню' : 'Скрыть боковое меню',
        );

        if (label instanceof HTMLElement) {
            label.textContent = active ? 'Показать меню' : 'Скрыть меню';
        }
    };

    toggle.addEventListener('click', () => {
        collapsed = !collapsed;

        try {
            window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch {
            // Интерфейс продолжает работать без сохранения предпочтения.
        }

        apply();
    });

    if (typeof desktop.addEventListener === 'function') {
        desktop.addEventListener('change', apply);
    } else if (typeof desktop.addListener === 'function') {
        desktop.addListener(apply);
    }

    apply();
})();

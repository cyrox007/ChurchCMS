(() => {
    'use strict';

    const contentSelector = '#admin-content';
    const statusSelector = '[data-admin-async-status]';
    const busyClass = 'admin-content--busy';

    const status = () => document.querySelector(statusSelector);

    const announce = (message, state = 'info') => {
        const region = status();
        if (!(region instanceof HTMLElement)) {
            return;
        }

        region.textContent = message;
        region.dataset.state = state;
        region.hidden = false;

        if (state === 'success') {
            window.clearTimeout(Number(region.dataset.hideTimer || 0));
            const timer = window.setTimeout(() => {
                region.hidden = true;
            }, 3200);
            region.dataset.hideTimer = String(timer);
        }
    };

    const setBusy = (form, active) => {
        const content = document.querySelector(contentSelector);
        content?.classList.toggle(busyClass, active);
        content?.setAttribute('aria-busy', active ? 'true' : 'false');

        for (const control of form.elements) {
            if (control instanceof HTMLButtonElement || control instanceof HTMLInputElement) {
                if (active) {
                    control.dataset.asyncWasDisabled = control.disabled ? '1' : '0';
                    control.disabled = true;
                } else if (control.dataset.asyncWasDisabled === '0') {
                    control.disabled = false;
                    delete control.dataset.asyncWasDisabled;
                }
            }
        }
    };

    const isSameOrigin = (url) => {
        try {
            return new URL(url, window.location.href).origin === window.location.origin;
        } catch {
            return false;
        }
    };

    const shouldHandle = (form) => {
        if (form.dataset.noAjax !== undefined || form.dataset.adminAjax === 'off') {
            return false;
        }

        const method = (form.method || 'get').toLowerCase();
        if (method !== 'post') {
            return false;
        }

        if (form.target && form.target !== '_self') {
            return false;
        }

        return isSameOrigin(form.action || window.location.href);
    };

    const syncShell = (parsed) => {
        const nextTitle = parsed.querySelector('.admin-topbar__context strong');
        const currentTitle = document.querySelector('.admin-topbar__context strong');
        if (nextTitle instanceof HTMLElement && currentTitle instanceof HTMLElement) {
            currentTitle.textContent = nextTitle.textContent;
        }

        const nextTask = parsed.querySelector('.admin-task-center-link');
        const currentTask = document.querySelector('.admin-task-center-link');
        if (nextTask instanceof HTMLElement && currentTask instanceof HTMLElement) {
            currentTask.innerHTML = nextTask.innerHTML;
        }

        const nextNav = parsed.querySelector('.admin-nav');
        const currentNav = document.querySelector('.admin-nav');
        if (nextNav instanceof HTMLElement && currentNav instanceof HTMLElement) {
            currentNav.innerHTML = nextNav.innerHTML;
        }

        if (parsed.title) {
            document.title = parsed.title;
        }
    };

    const replaceContent = (html, responseUrl) => {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const nextContent = parsed.querySelector(contentSelector);
        const currentContent = document.querySelector(contentSelector);

        if (!(nextContent instanceof HTMLElement) || !(currentContent instanceof HTMLElement)) {
            throw new Error('Ответ не содержит административную рабочую область.');
        }

        currentContent.replaceChildren(...nextContent.childNodes);
        syncShell(parsed);

        if (responseUrl && isSameOrigin(responseUrl)) {
            const nextUrl = new URL(responseUrl);
            const currentUrl = new URL(window.location.href);
            if (nextUrl.pathname !== currentUrl.pathname || nextUrl.search !== currentUrl.search) {
                window.history.replaceState({}, '', nextUrl.pathname + nextUrl.search + nextUrl.hash);
            }
        }

        const autofocus = currentContent.querySelector('[autofocus]');
        if (autofocus instanceof HTMLElement) {
            autofocus.focus({preventScroll: true});
        }
    };

    const submit = async (form, submitter) => {
        setBusy(form, true);
        announce('Выполняется операция…', 'loading');

        try {
            const body = new FormData(form);
            if (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) {
                if (submitter.name) {
                    body.set(submitter.name, submitter.value);
                }
            }

            const response = await fetch(form.action || window.location.href, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                redirect: 'follow',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-ChurchCMS-Async': '1',
                },
            });

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('text/html')) {
                throw new Error('Сервер вернул неподдерживаемый формат ответа.');
            }

            replaceContent(await response.text(), response.url);

            if (response.ok) {
                announce('Операция выполнена.', 'success');
                return;
            }

            announce(`Операция завершилась ошибкой (${response.status}). Проверьте сообщение на странице.`, 'error');
        } catch (error) {
            console.error(error);
            announce('Не удалось выполнить операцию без перезагрузки. Данные не следует отправлять повторно, пока не проверено состояние страницы.', 'error');
        } finally {
            setBusy(form, false);
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !shouldHandle(form)) {
            return;
        }

        event.preventDefault();
        void submit(form, event.submitter);
    });
})();

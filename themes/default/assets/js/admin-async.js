(() => {
    'use strict';

    const contentSelector = '#admin-content';
    const statusSelector = '[data-admin-async-status]';
    const busyClass = 'admin-content--busy';

    const status = () => document.querySelector(statusSelector);
    const content = () => document.querySelector(contentSelector);

    const announce = (message, state = 'info') => {
        const region = status();
        if (!(region instanceof HTMLElement)) {
            return;
        }

        region.textContent = message;
        region.dataset.state = state;
        region.hidden = false;

        if (state !== 'success') {
            return;
        }

        window.clearTimeout(Number(region.dataset.hideTimer || 0));
        const timer = window.setTimeout(() => {
            region.hidden = true;
        }, 3200);
        region.dataset.hideTimer = String(timer);
    };

    const setContentBusy = (active) => {
        const current = content();
        current?.classList.toggle(busyClass, active);
        current?.setAttribute('aria-busy', active ? 'true' : 'false');
    };

    const setFormBusy = (form, active) => {
        setContentBusy(active);

        for (const control of form.elements) {
            if (!(control instanceof HTMLButtonElement)
                && !(control instanceof HTMLInputElement)
                && !(control instanceof HTMLSelectElement)
                && !(control instanceof HTMLTextAreaElement)) {
                continue;
            }

            if (active) {
                control.dataset.asyncWasDisabled = control.disabled ? '1' : '0';
                control.disabled = true;
                continue;
            }

            if (control.dataset.asyncWasDisabled === '0') {
                control.disabled = false;
            }
            delete control.dataset.asyncWasDisabled;
        }
    };

    const parseUrl = (value) => {
        try {
            return new URL(value, window.location.href);
        } catch {
            return null;
        }
    };

    const isSameOrigin = (value) => {
        const url = parseUrl(value);
        return url instanceof URL && url.origin === window.location.origin;
    };

    const canUseAjax = (element) =>
        element.dataset.noAjax === undefined && element.dataset.adminAjax !== 'off';

    const hasSelfTarget = (element) => !element.target || element.target === '_self';

    const shouldHandlePost = (form) => {
        const method = (form.method || 'get').toLowerCase();
        return canUseAjax(form)
            && method === 'post'
            && hasSelfTarget(form)
            && isSameOrigin(form.action || window.location.href);
    };

    const shouldHandleLocalGet = (form) => {
        if (!canUseAjax(form) || !hasSelfTarget(form)) {
            return false;
        }

        const method = (form.method || 'get').toLowerCase();
        if (method !== 'get' || !form.closest(contentSelector)) {
            return false;
        }

        const target = parseUrl(form.action || window.location.href);
        return target instanceof URL
            && target.origin === window.location.origin
            && target.pathname === window.location.pathname;
    };

    const shouldHandleLocalLink = (anchor, event) => {
        if (!canUseAjax(anchor)
            || anchor.hasAttribute('download')
            || !hasSelfTarget(anchor)
            || event.button !== 0
            || event.ctrlKey
            || event.metaKey
            || event.shiftKey
            || event.altKey
            || !anchor.closest(contentSelector)) {
            return false;
        }

        const target = parseUrl(anchor.href);
        if (!(target instanceof URL)
            || target.origin !== window.location.origin
            || target.pathname !== window.location.pathname) {
            return false;
        }

        const current = new URL(window.location.href);
        const onlyHashChanged = target.pathname === current.pathname
            && target.search === current.search
            && target.hash !== current.hash;
        return !onlyHashChanged;
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

    const replaceContent = (html) => {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const nextContent = parsed.querySelector(contentSelector);
        const currentContent = content();

        if (!(nextContent instanceof HTMLElement) || !(currentContent instanceof HTMLElement)) {
            throw new Error('Ответ не содержит административную рабочую область.');
        }

        currentContent.replaceChildren(...nextContent.childNodes);
        syncShell(parsed);

        const autofocus = currentContent.querySelector('[autofocus]');
        if (autofocus instanceof HTMLElement) {
            autofocus.focus({preventScroll: true});
        }
    };

    const requireHtml = (response) => {
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('text/html')) {
            throw new Error('Сервер вернул неподдерживаемый формат ответа.');
        }
    };

    const requestHtml = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            redirect: 'follow',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-ChurchCMS-Async': '1',
                ...(options.headers || {}),
            },
            ...options,
        });
        requireHtml(response);
        const html = await response.text();
        return {response, html};
    };

    const applyResponseUrl = (responseUrl, mode) => {
        if (!responseUrl || !isSameOrigin(responseUrl)) {
            return;
        }

        const next = new URL(responseUrl);
        const current = new URL(window.location.href);
        const nextLocation = next.pathname + next.search + next.hash;
        const currentLocation = current.pathname + current.search + current.hash;
        if (nextLocation === currentLocation) {
            return;
        }

        if (mode === 'push') {
            window.history.pushState({churchCmsAsync: true}, '', nextLocation);
            return;
        }
        window.history.replaceState({churchCmsAsync: true}, '', nextLocation);
    };

    const submitPost = async (form, submitter) => {
        setFormBusy(form, true);
        announce('Выполняется операция…', 'loading');

        try {
            const body = new FormData(form);
            if ((submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement)
                && submitter.name) {
                body.set(submitter.name, submitter.value);
            }

            const result = await requestHtml(form.action || window.location.href, {
                method: 'POST',
                body,
            });
            replaceContent(result.html);
            applyResponseUrl(result.response.url, 'replace');

            if (result.response.ok) {
                announce('Операция выполнена.', 'success');
                return;
            }

            announce(
                `Операция завершилась ошибкой (${result.response.status}). Проверьте сообщение на странице.`,
                'error',
            );
        } catch (error) {
            console.error(error);
            announce(
                'Не удалось выполнить операцию без перезагрузки. Проверьте состояние страницы перед повторной отправкой.',
                'error',
            );
        } finally {
            setFormBusy(form, false);
        }
    };

    const buildGetUrl = (form) => {
        const target = new URL(form.action || window.location.href, window.location.href);
        const params = new URLSearchParams();
        const data = new FormData(form);
        for (const [name, value] of data.entries()) {
            if (typeof value === 'string') {
                params.append(name, value);
            }
        }
        target.search = params.toString();
        return target;
    };

    const loadLocal = async (url, historyMode = 'push') => {
        setContentBusy(true);
        announce('Обновляется список…', 'loading');

        try {
            const result = await requestHtml(url, {method: 'GET'});
            replaceContent(result.html);
            applyResponseUrl(result.response.url, historyMode);

            if (result.response.ok) {
                announce('Данные обновлены.', 'success');
                return;
            }

            announce(
                `Не удалось обновить данные (${result.response.status}).`,
                'error',
            );
        } catch (error) {
            console.error(error);
            announce('Не удалось обновить данные без перезагрузки.', 'error');
        } finally {
            setContentBusy(false);
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (shouldHandlePost(form)) {
            event.preventDefault();
            void submitPost(form, event.submitter);
            return;
        }

        if (shouldHandleLocalGet(form)) {
            event.preventDefault();
            void loadLocal(buildGetUrl(form), 'push');
        }
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const anchor = target.closest('a[href]');
        if (!(anchor instanceof HTMLAnchorElement) || !shouldHandleLocalLink(anchor, event)) {
            return;
        }

        event.preventDefault();
        void loadLocal(anchor.href, 'push');
    });

    window.addEventListener('popstate', () => {
        void loadLocal(window.location.href, 'none');
    });
})();

(() => {
    'use strict';

    const sourceSelector = '[data-rich-editor-source]';
    const rubricSelector = '.publication-editor input[name="categories"]';
    let rubricRequest = null;

    const createButton = (label, title, command, value = null) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.title = title;
        button.setAttribute('aria-label', title);
        button.dataset.editorCommand = command;
        if (value !== null) {
            button.dataset.editorValue = value;
        }
        return button;
    };

    const plainTextToFragment = (text) => {
        const fragment = document.createDocumentFragment();
        const normalized = text.replace(/\r\n?/g, '\n').trim();
        if (normalized === '') {
            return fragment;
        }

        normalized.split(/\n{2,}/).forEach((paragraphText) => {
            const paragraph = document.createElement('p');
            paragraphText.split('\n').forEach((line, index) => {
                if (index > 0) {
                    paragraph.append(document.createElement('br'));
                }
                paragraph.append(document.createTextNode(line));
            });
            fragment.append(paragraph);
        });

        return fragment;
    };

    const selectionInside = (surface) => {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) {
            return false;
        }
        return surface.contains(selection.anchorNode);
    };

    const enhance = (source) => {
        if (!(source instanceof HTMLTextAreaElement)
            || source.dataset.richEditorReady === '1') {
            return;
        }

        source.dataset.richEditorReady = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'rich-editor';
        wrapper.dataset.richEditor = '';

        const toolbar = document.createElement('div');
        toolbar.className = 'rich-editor__toolbar';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'Форматирование текста');

        [
            ['Абзац', 'Обычный абзац', 'formatBlock', 'p'],
            ['H2', 'Заголовок второго уровня', 'formatBlock', 'h2'],
            ['H3', 'Заголовок третьего уровня', 'formatBlock', 'h3'],
            ['Ж', 'Полужирный', 'bold', null],
            ['К', 'Курсив', 'italic', null],
            ['Цитата', 'Цитата', 'formatBlock', 'blockquote'],
            ['• Список', 'Маркированный список', 'insertUnorderedList', null],
            ['1. Список', 'Нумерованный список', 'insertOrderedList', null],
            ['Ссылка', 'Добавить ссылку', 'createLink', null],
            ['↶', 'Отменить', 'undo', null],
            ['↷', 'Повторить', 'redo', null],
        ].forEach(([label, title, command, value]) => {
            toolbar.append(createButton(label, title, command, value));
        });

        const surface = document.createElement('div');
        surface.className = 'rich-editor__surface';
        surface.contentEditable = 'true';
        surface.setAttribute('role', 'textbox');
        surface.setAttribute('aria-label', 'Текст публикации');
        surface.setAttribute('aria-multiline', 'true');
        surface.setAttribute('data-placeholder', 'Напишите текст публикации…');

        if (source.dataset.richEditorFormat === 'html') {
            surface.innerHTML = source.value;
        } else {
            surface.append(plainTextToFragment(source.value));
        }

        wrapper.append(toolbar, surface);
        source.insertAdjacentElement('afterend', wrapper);
        source.classList.add('rich-editor-source--enhanced');
        source.tabIndex = -1;
        source.setAttribute('aria-hidden', 'true');

        const sync = () => {
            source.value = surface.innerHTML.trim();
        };

        toolbar.addEventListener('mousedown', (event) => {
            if (event.target instanceof HTMLButtonElement) {
                event.preventDefault();
            }
        });

        toolbar.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            const button = target.closest('[data-editor-command]');
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            surface.focus();
            const command = button.dataset.editorCommand || '';
            let value = button.dataset.editorValue || null;

            if (command === 'createLink') {
                if (!selectionInside(surface)) {
                    return;
                }

                const href = window.prompt('Адрес ссылки');
                if (href === null || href.trim() === '') {
                    return;
                }
                value = href.trim();
            }

            document.execCommand(command, false, value);
            sync();
        });

        surface.addEventListener('input', sync);
        surface.addEventListener('blur', sync);

        const form = source.closest('form');
        if (form) {
            form.addEventListener('submit', sync);
        }
    };

    const parseNames = (value) => value
        .split(',')
        .map((name) => name.trim())
        .filter((name, index, names) => (
            name !== ''
            && names.findIndex((candidate) => (
                candidate.toLocaleLowerCase('ru')
                === name.toLocaleLowerCase('ru')
            )) === index
        ));

    const loadRubrics = () => {
        if (rubricRequest !== null) {
            return rubricRequest;
        }

        rubricRequest = fetch('/admin/publications/rubrics?format=json', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Не удалось загрузить рубрики.');
                }
                return response.json();
            })
            .then((payload) => (
                Array.isArray(payload?.rubrics)
                    ? payload.rubrics
                    : []
            ))
            .catch(() => {
                rubricRequest = null;
                return null;
            });

        return rubricRequest;
    };

    const rubricChoice = ({name, depth = 0, checked = false, legacy = false}) => {
        const label = document.createElement('label');
        label.className = legacy
            ? 'rubric-choice rubric-choice--legacy'
            : 'rubric-choice';
        label.dataset.depth = String(Math.min(3, Math.max(0, Number(depth) || 0)));

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = name;
        checkbox.checked = checked;
        checkbox.dataset.rubricValue = name;

        const text = document.createElement('span');
        text.textContent = name;

        label.append(checkbox, text);
        return label;
    };

    const buildRubricPicker = (source, rubrics) => {
        const field = source.closest('label.field');
        if (!(field instanceof HTMLElement)) {
            return;
        }

        const selected = parseNames(source.value);
        const selectedKeys = new Set(
            selected.map((name) => name.toLocaleLowerCase('ru')),
        );
        const knownKeys = new Set(
            rubrics.map((rubric) => (
                String(rubric?.name || '').toLocaleLowerCase('ru')
            )),
        );
        const legacy = selected.filter((name) => (
            !knownKeys.has(name.toLocaleLowerCase('ru'))
        ));

        const picker = document.createElement('div');
        picker.className = 'field rubric-picker';
        picker.dataset.rubricPicker = '';

        const heading = document.createElement('div');
        heading.className = 'rubric-picker__heading';

        const title = document.createElement('span');
        title.textContent = 'Рубрики';

        const manage = document.createElement('a');
        manage.href = '/admin/publications/rubrics';
        manage.textContent = 'Настроить рубрики';

        heading.append(title, manage);
        picker.append(heading);

        if (rubrics.length === 0 && legacy.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'rubric-picker__empty';
            empty.textContent = 'Рубрики ещё не созданы. Добавьте структуру рубрик, чтобы классифицировать публикации.';
            picker.append(empty);
        }

        if (rubrics.length > 0) {
            const list = document.createElement('div');
            list.className = 'rubric-choice-list';

            rubrics.forEach((rubric) => {
                const name = String(rubric?.name || '').trim();
                if (name === '') {
                    return;
                }

                list.append(rubricChoice({
                    name,
                    depth: rubric?.depth,
                    checked: selectedKeys.has(name.toLocaleLowerCase('ru')),
                }));
            });

            picker.append(list);
        }

        if (legacy.length > 0) {
            const legacyBlock = document.createElement('div');
            legacyBlock.className = 'rubric-picker__legacy';

            const legacyTitle = document.createElement('small');
            legacyTitle.textContent = 'Ранее указанные категории. Снимите отметку, если они больше не нужны.';

            const legacyList = document.createElement('div');
            legacyList.className = 'rubric-choice-list';
            legacy.forEach((name) => {
                legacyList.append(rubricChoice({
                    name,
                    checked: true,
                    legacy: true,
                }));
            });

            legacyBlock.append(legacyTitle, legacyList);
            picker.append(legacyBlock);
        }

        const sync = () => {
            const values = Array.from(
                picker.querySelectorAll('input[data-rubric-value]:checked'),
            ).map((checkbox) => checkbox.value.trim())
                .filter((name) => name !== '');

            source.value = values.join(', ');
        };

        picker.addEventListener('change', sync);
        source.dataset.rubricReady = '1';
        field.insertAdjacentElement('afterend', picker);
        field.hidden = true;

        const form = source.closest('form');
        if (form) {
            form.addEventListener('submit', sync);
        }
    };

    const enhanceRubrics = (source) => {
        if (!(source instanceof HTMLInputElement)
            || source.dataset.rubricReady === '1') {
            return;
        }

        source.dataset.rubricReady = 'loading';
        loadRubrics().then((rubrics) => {
            if (rubrics === null || !source.isConnected) {
                delete source.dataset.rubricReady;
                return;
            }

            buildRubricPicker(source, rubrics);
        });
    };

    const scan = (root = document) => {
        if (root instanceof Element && root.matches(sourceSelector)) {
            enhance(root);
        }
        if (root instanceof Element && root.matches(rubricSelector)) {
            enhanceRubrics(root);
        }

        root.querySelectorAll?.(sourceSelector).forEach(enhance);
        root.querySelectorAll?.(rubricSelector).forEach(enhanceRubrics);
    };

    scan();

    const content = document.querySelector('#admin-content');
    if (!(content instanceof HTMLElement)) {
        return;
    }

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node instanceof Element) {
                    scan(node);
                }
            }
        }
    });

    observer.observe(content, {
        childList: true,
        subtree: true,
    });
})();

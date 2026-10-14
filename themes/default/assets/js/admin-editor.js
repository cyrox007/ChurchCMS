(() => {
    'use strict';

    const sources = document.querySelectorAll('[data-rich-editor-source]');
    if (sources.length === 0) {
        return;
    }

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

    sources.forEach((source) => {
        if (!(source instanceof HTMLTextAreaElement)) {
            return;
        }

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
        surface.setAttribute('aria-multiline', 'true');
        surface.setAttribute(
            'data-placeholder',
            'Напишите текст публикации…',
        );

        if (source.dataset.richEditorFormat === 'html') {
            surface.innerHTML = source.value;
        } else {
            surface.append(plainTextToFragment(source.value));
        }

        wrapper.append(toolbar, surface);
        source.insertAdjacentElement('afterend', wrapper);
        source.classList.add('rich-editor-source--enhanced');
        source.tabIndex = -1;

        const sync = () => {
            source.value = surface.innerHTML.trim();
        };

        toolbar.addEventListener('mousedown', (event) => {
            if (event.target instanceof HTMLButtonElement) {
                event.preventDefault();
            }
        });

        toolbar.addEventListener('click', (event) => {
            const button = event.target.closest('[data-editor-command]');
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
    });
})();

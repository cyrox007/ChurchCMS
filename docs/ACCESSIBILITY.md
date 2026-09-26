# Accessibility Admin Shell

## Цель

Административная панель ChurchCMS должна оставаться рабочей без мыши, не скрывать важные состояния от assistive technology и не требовать стороннего JavaScript runtime.

Этот аудит относится к общей оболочке Admin Shell и существующим административным экранам Publications, Comments, Search и Tasks.

## Что проверяется автоматически

`php tools/accessibility/admin-audit.php` завершается ошибкой CI при регрессии следующих правил:

- язык документа `lang="ru"`;
- skip-link к основному содержимому;
- явный `main#admin-content`;
- подписанные navigation landmarks;
- `aria-current` для активного раздела;
- `aria-controls` и `aria-expanded` у кнопки сворачивания бокового меню;
- подписанное поле глобального поиска и `role="search"`;
- явный `type` у всех кнопок admin-шаблонов;
- отсутствие положительного `tabindex`;
- отсутствие inline `onclick/onkeydown/onkeyup/onkeypress`;
- `target="_blank"` только вместе с `rel="noopener"`;
- наличие общего `:focus-visible`;
- поддержка `prefers-reduced-motion`;
- отключение smooth scroll при reduced motion;
- `role/aria-live/aria-busy` у унифицированных admin states;
- минимальный контраст 4.5:1 для ключевых пар боковой панели и основных кнопок.

CI также выполняет PHP lint, theme-check и проверку JavaScript-файла Admin Shell.

## Клавиатурная навигация

Текущая оболочка использует обычные ссылки, кнопки, формы и `summary/details`, поэтому основной порядок фокуса следует DOM и не переопределяется положительным `tabindex`.

Кнопка сворачивания меню:

- является обычным `button type="button"`;
- сообщает состояние через `aria-expanded`;
- связывается с sidebar через `aria-controls`;
- не является обязательной для работы — без JavaScript меню остаётся видимым.

Фокус виден на интерактивных элементах через общий `:focus-visible`.

## Движение и анимации

При `prefers-reduced-motion: reduce`:

- отключается анимация loading state;
- отключаются transitions сворачивания Admin Shell;
- smooth scrolling заменяется обычной прокруткой.

## Что остаётся ручной проверкой pilot QA

Dependency-free CI не заменяет проверку реальным браузером и screen reader.

Перед выпуском пилотной установки нужно пройти короткий ручной сценарий:

1. Только клавиатурой войти в админку и пройти Dashboard → Publications → Edit → Comments → Search → Tasks.
2. Проверить skip-link сразу после загрузки страницы.
3. Проверить видимость фокуса при Tab/Shift+Tab.
4. Проверить работу sidebar toggle с Enter и Space.
5. Проверить поиск с клавиатуры и возврат фокуса после перехода.
6. Проверить сообщения error/success/loading с NVDA, VoiceOver или TalkBack.
7. Проверить reflow при browser zoom 200% и 400%.
8. Проверить mobile viewport без горизонтальной потери управляющих элементов.

Найденные при pilot QA проблемы должны исправляться как обычные accessibility-регрессии; наличие этого документа не считается заменой пользовательской проверки.

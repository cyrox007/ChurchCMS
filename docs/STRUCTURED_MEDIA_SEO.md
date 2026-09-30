# Структурированные изображения и видео SEO

ChurchCMS добавляет JSON-LD к публичным публикациям через существующий модуль
SEO и Media usage registry. Отдельной таблицы связей SEO ↔ Media нет.

## Связи с Media

Для публикации используется consumer:

```text
consumer_type = publication-seo
consumer_public_id = <public ID публикации>
```

Поддерживаются slots:

- `image` — основное изображение Article/NewsArticle;
- `video` — локальный публичный видеофайл;
- `video-thumbnail` — публичное изображение-превью VideoObject.

Usage references защищают выбранные файлы от архивации до снятия SEO-связи.

## Доступ и сохранение

В редакторе показываются только:

- Media из organization-веток, доступных текущему редактору публикаций;
- неархивные assets;
- assets с `visibility=public`;
- типы `image` и `video`.

Выбор повторно проверяется сервером до сохранения самой публикации. Подмена
Media public ID не позволяет сослаться на asset из чужой organization-ветки.

Обычные SEO-поля и Media usage slots сохраняются одной транзакцией. Ошибка
structured Media откатывает изменения SEO-настроек.

## Article / NewsArticle

Для типа `news` формируется `NewsArticle`, для остальных типов публикаций —
`Article`.

JSON-LD включает доступные данные:

- `headline`;
- `mainEntityOfPage`;
- `datePublished`;
- `dateModified`;
- `description`;
- `author`;
- `image`;
- `video`, если выполнены требования ниже.

Для изображения предпочтителен derivative `medium`. Если его нет, используется
проверенный публичный оригинал. В `ImageObject` добавляются размеры и подпись,
если эти данные известны.

Выбранное Media-изображение также становится Open Graph/Twitter image, если
оператор не задал явный `social_image_url`. Ручной URL всегда имеет приоритет.

## VideoObject

VideoObject формируется только когда одновременно доступны:

- публичный video asset;
- публичный image asset для thumbnail;
- дата публикации материала или дата регистрации video asset;
- проверяемый публичный URL видеофайла.

Выбор видео без thumbnail отклоняется ещё до сохранения публикации.

Первая версия не публикует `duration`: Media-модель пока не хранит надёжную
длительность локального видео. ChurchCMS не генерирует фиктивное значение.

## Безопасность JSON-LD

`SeoRenderer` принимает только массив structured data и сериализует его через
`json_encode()` с `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS` и
`JSON_HEX_QUOT`. Сырой JSON из формы или базы в HTML не вставляется.

Текст вида `</script><script>...` поэтому остаётся данными JSON и не может
закрыть JSON-LD script-тег.

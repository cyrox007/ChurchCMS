import {test, expect} from '@playwright/test';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
const adminAsyncPath = path.join(root, 'themes/default/assets/js/admin-async.js');
const adminAsyncScript = fs.readFileSync(adminAsyncPath, 'utf8');

let server;
let baseUrl;
const requests = [];

const html = (content, title = 'Тест AJAX') => `<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>${title}</title></head>
<body>
<header class="admin-topbar__context"><strong>${title}</strong></header>
<nav class="admin-nav"><a href="/admin/items">Список</a></nav>
<a class="admin-task-center-link" href="/admin/tasks">Задачи</a>
<div data-admin-async-status hidden aria-live="polite"></div>
<main id="admin-content">${content}</main>
<script>window.__churchCmsDocumentBoot = (window.__churchCmsDocumentBoot || 0) + 1;</script>
<script src="/assets/admin-async.js"></script>
</body>
</html>`;

const listContent = (url) => {
    const status = url.searchParams.get('status') || 'all';
    const page = url.searchParams.get('page') || '1';
    const saved = url.searchParams.get('saved') || '0';

    return `
<section data-view-state="${status}:${page}:${saved}">
    <form id="save-form" method="post" action="/admin/items/save" enctype="multipart/form-data">
        <input name="title" value="Тестовая запись">
        <input id="attachment" name="attachment" type="file">
        <button id="save-button" type="submit">Сохранить</button>
    </form>
    <form id="filter-form" method="get" action="/admin/items">
        <select name="status">
            <option value="all"${status === 'all' ? ' selected' : ''}>Все</option>
            <option value="published"${status === 'published' ? ' selected' : ''}>Опубликованные</option>
        </select>
        <button type="submit">Фильтр</button>
    </form>
    <a id="page-two" href="/admin/items?status=${encodeURIComponent(status)}&page=2">Страница 2</a>
    <a id="other-section" href="/admin/other">Другой раздел</a>
    <form id="no-ajax-form" method="post" action="/admin/items/no-ajax" data-no-ajax>
        <button type="submit">Обычная отправка</button>
    </form>
</section>`;
};

const readBody = (request) => new Promise((resolve, reject) => {
    const chunks = [];
    request.on('data', (chunk) => chunks.push(chunk));
    request.on('end', () => resolve(Buffer.concat(chunks)));
    request.on('error', reject);
});

const sendHtml = (response, status, body, headers = {}) => {
    response.writeHead(status, {
        'content-type': 'text/html; charset=utf-8',
        ...headers,
    });
    response.end(body);
};

test.beforeAll(async () => {
    server = http.createServer(async (request, response) => {
        const url = new URL(request.url, 'http://127.0.0.1');

        if (url.pathname === '/assets/admin-async.js') {
            response.writeHead(200, {'content-type': 'application/javascript; charset=utf-8'});
            response.end(adminAsyncScript);
            return;
        }

        if (url.pathname === '/admin/items' && request.method === 'GET') {
            sendHtml(response, 200, html(listContent(url), 'Список материалов'));
            return;
        }

        if (url.pathname === '/admin/items/save' && request.method === 'POST') {
            const body = await readBody(request);
            requests.push({
                path: url.pathname,
                headers: request.headers,
                body: body.toString('utf8'),
            });
            await new Promise((resolve) => setTimeout(resolve, 180));
            response.writeHead(303, {location: '/admin/items?saved=1'});
            response.end();
            return;
        }

        if (url.pathname === '/admin/items/fail' && request.method === 'POST') {
            sendHtml(response, 500, html('<p id="server-error">Ошибка сохранения</p>', 'Ошибка'));
            return;
        }

        if (url.pathname === '/admin/items/no-ajax' && request.method === 'POST') {
            sendHtml(response, 200, '<!doctype html><html><body><p id="classic-post">Обычная отправка</p></body></html>');
            return;
        }

        if (url.pathname === '/admin/other') {
            sendHtml(response, 200, '<!doctype html><html><body><p id="other-page">Другой раздел</p></body></html>');
            return;
        }

        response.writeHead(404, {'content-type': 'text/plain; charset=utf-8'});
        response.end('Не найдено');
    });

    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    const address = server.address();
    baseUrl = `http://127.0.0.1:${address.port}`;
});

test.afterAll(async () => {
    await new Promise((resolve, reject) => server.close((error) => error ? reject(error) : resolve()));
});

test.beforeEach(() => {
    requests.length = 0;
});

test('POST выполняется без полной перезагрузки и отправляет все данные формы', async ({page}) => {
    await page.goto(`${baseUrl}/admin/items`);
    await expect(page.locator('[data-view-state]')).toHaveAttribute('data-view-state', 'all:1:0');
    await expect.poll(() => page.evaluate(() => window.__churchCmsDocumentBoot)).toBe(1);

    await page.setInputFiles('#attachment', {
        name: 'fixture.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('browser-audit'),
    });

    await page.click('#save-button');
    await expect(page.locator('#admin-content')).toHaveAttribute('aria-busy', 'true');
    await expect(page.locator('[data-admin-async-status]')).toHaveAttribute('data-state', 'loading');
    await expect(page.locator('#save-button')).toBeDisabled();

    await expect(page.locator('[data-view-state]')).toHaveAttribute('data-view-state', 'all:1:1');
    await expect(page.locator('[data-admin-async-status]')).toHaveAttribute('data-state', 'success');
    await expect.poll(() => page.evaluate(() => window.__churchCmsDocumentBoot)).toBe(1);

    expect(requests).toHaveLength(1);
    expect(requests[0].headers['x-requested-with']).toBe('XMLHttpRequest');
    expect(requests[0].headers['x-churchcms-async']).toBe('1');
    expect(requests[0].body).toContain('Тестовая запись');
    expect(requests[0].body).toContain('fixture.txt');
    expect(requests[0].body).toContain('browser-audit');
});

test('повторной отправки во время активного POST не происходит', async ({page}) => {
    await page.goto(`${baseUrl}/admin/items`);
    await page.dblclick('#save-button', {delay: 20});

    await expect(page.locator('[data-view-state]')).toHaveAttribute('data-view-state', 'all:1:1');
    expect(requests).toHaveLength(1);
});

test('GET-фильтр и пагинация работают через AJAX, Back восстанавливает состояние', async ({page}) => {
    await page.goto(`${baseUrl}/admin/items`);
    await page.selectOption('#filter-form select[name="status"]', 'published');
    await page.click('#filter-form button[type="submit"]');

    await expect(page).toHaveURL(`${baseUrl}/admin/items?status=published`);
    await expect(page.locator('[data-view-state]')).toHaveAttribute('data-view-state', 'published:1:0');
    await expect.poll(() => page.evaluate(() => window.__churchCmsDocumentBoot)).toBe(1);

    await page.click('#page-two');
    await expect(page).toHaveURL(`${baseUrl}/admin/items?status=published&page=2`);
    await expect(page.locator('[data-view-state]')).toHaveAttribute('data-view-state', 'published:2:0');
    await expect.poll(() => page.evaluate(() => window.__churchCmsDocumentBoot)).toBe(1);

    await page.goBack();
    await expect(page).toHaveURL(`${baseUrl}/admin/items?status=published`);
    await expect(page.locator('[data-view-state]')).toHaveAttribute('data-view-state', 'published:1:0');
    await expect.poll(() => page.evaluate(() => window.__churchCmsDocumentBoot)).toBe(1);
});

test('ошибка сервера обновляет рабочую область и показывает информативное состояние', async ({page}) => {
    await page.goto(`${baseUrl}/admin/items`);
    await page.locator('#save-form').evaluate((form) => form.action = '/admin/items/fail');
    await page.click('#save-button');

    await expect(page.locator('#server-error')).toHaveText('Ошибка сохранения');
    await expect(page.locator('[data-admin-async-status]')).toHaveAttribute('data-state', 'error');
    await expect.poll(() => page.evaluate(() => window.__churchCmsDocumentBoot)).toBe(1);
});

test('переход в другой раздел остаётся обычной навигацией', async ({page}) => {
    await page.goto(`${baseUrl}/admin/items`);
    await page.click('#other-section');
    await expect(page).toHaveURL(`${baseUrl}/admin/other`);
    await expect(page.locator('#other-page')).toHaveText('Другой раздел');
});

test('data-no-ajax сохраняет обычную отправку формы', async ({page}) => {
    await page.goto(`${baseUrl}/admin/items`);
    await page.click('#no-ajax-form button');
    await expect(page.locator('#classic-post')).toHaveText('Обычная отправка');
});

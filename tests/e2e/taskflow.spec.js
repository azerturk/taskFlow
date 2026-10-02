import { expect, test } from '@playwright/test';

const password = 'browser-password';

async function visit(page, url) {
    await page.goto(url, { waitUntil: 'domcontentloaded' });
}

async function signIn(page, email) {
    await visit(page, '/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Sign in to TaskFlow' }).click();
    await expect(page).not.toHaveURL(/login/);
}

async function signOut(page) {
    if ((page.viewportSize()?.width || 1024) < 1024) {
        const toggle = page.getByRole('button', { name: 'Open navigation' });
        const navigation = page.locator('[data-mobile-nav]');
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(navigation).not.toHaveClass(/-translate-x-full/);
        await expect(navigation).toBeInViewport();
    }
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page).toHaveURL(/login/);
}

async function projectHref(page, name = 'E2E Project') {
    await visit(page, '/projects');
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const link = page.getByRole('link', { name: new RegExp(`^${escaped}(?:\\s|$)`) }).first();
    await expect(link).toBeVisible();
    return await link.getAttribute('href');
}

async function taskHref(page, title) {
    await visit(page, `/tasks?q=${encodeURIComponent(title)}`);
    const escaped = title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const titleNode = page.locator('p:visible').filter({ hasText: new RegExp(`^${escaped}$`) }).first();
    await expect(titleNode).toBeVisible();
    const direct = titleNode.locator('xpath=ancestor-or-self::a[contains(@href,"/tasks/")][1]');
    if (await direct.count()) return await direct.getAttribute('href');
    return await titleNode.locator('xpath=ancestor::tr[1]').locator('a[href*="/tasks/"]').first().getAttribute('href');
}

test('journey 1: login logout and unauthorized redirect', async ({ page }) => {
    await visit(page, '/tasks');
    await expect(page).toHaveURL(/login/);
    await signIn(page, 'member@e2e.test');
    await expect(page.locator('body')).toContainText('E2E Member');
    await signOut(page);
});

test('journey 2: admin creates and suspends a user whose login is denied', async ({ page }, testInfo) => {
    const profile = testInfo.project.name;
    const email = `created-${profile}@e2e.test`;
    await signIn(page, 'admin@e2e.test');
    await visit(page, '/admin/users/create');
    await page.getByLabel('Name').fill(`Created ${profile}`);
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Temporary password').fill(password);
    await page.getByLabel('Confirm password').fill(password);
    await page.getByLabel('Global TaskFlow Role').selectOption('member');
    await page.getByRole('button', { name: 'Save user' }).click();
    await expect(page.getByRole('heading', { name: `Edit Created ${profile}` })).toBeVisible();
    await page.getByRole('button', { name: 'Suspend account' }).click();
    await expect(page.getByText('Status: suspended')).toBeVisible();
    await signOut(page);
    await visit(page, '/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Sign in to TaskFlow' }).click();
    await expect(page).toHaveURL(/login/);
});

test('journey 3: manager creates and activates a project then manages membership', async ({ page }, testInfo) => {
    const profile = testInfo.project.name;
    const name = `E2E ${profile} managed project`;
    await signIn(page, 'manager@e2e.test');
    await visit(page, '/projects/create');
    await page.getByLabel('Project name').fill(name);
    const rawKey = profile === 'desktop' ? 'dsk' : 'mOb';
    const canonicalKey = rawKey.toUpperCase();
    await page.getByLabel('Project key').fill(rawKey);
    await expect(page.getByLabel('Project key')).toHaveValue(canonicalKey);
    await page.getByRole('button', { name: 'Create project' }).click();
    await expect(page.getByRole('heading', { name })).toBeVisible();
    await expect(page.getByText(canonicalKey, { exact: true }).first()).toBeVisible();
    await page.getByRole('button', { name: 'Activate project' }).click();
    await expect(page.getByText('Active', { exact: true }).first()).toBeVisible();
    await page.getByRole('link', { name: 'Manage members' }).click();
    const userSelect = page.getByLabel('User');
    const candidateValue = await userSelect.locator('option').filter({ hasText: /E2E Candidate/ }).getAttribute('value');
    await userSelect.selectOption(candidateValue);
    await page.getByLabel('Project role').selectOption('member');
    await page.getByRole('button', { name: 'Add member' }).click();
    await expect(page.getByText('candidate@e2e.test')).toBeVisible();
});

test('journey 4: member reports a bug manager assigns it and assignee progresses it', async ({ page }, testInfo) => {
    const title = `E2E ${testInfo.project.name} reported bug`;
    await signIn(page, 'member@e2e.test');
    const project = await projectHref(page);
    await visit(page, `${project}/tasks/create`);
    await page.getByLabel('Task title').fill(title);
    await page.getByLabel('Work type').selectOption('bug');
    await page.getByLabel('Priority').selectOption('high');
    await page.getByRole('button', { name: 'Create task' }).click();
    const task = page.url();
    await expect(page.getByRole('heading', { name: title })).toBeVisible();
    await signOut(page);

    await signIn(page, 'manager@e2e.test');
    await visit(page, task);
    const assigneeSelect = page.getByLabel('Assignee');
    const assigneeValue = await assigneeSelect.locator('option').filter({ hasText: /^E2E Member/ }).getAttribute('value');
    await assigneeSelect.selectOption(assigneeValue);
    await page.getByRole('button', { name: 'Save assignee' }).click();
    await expect(page.getByLabel('Assignee').locator('option:checked')).toContainText('E2E Member');
    await signOut(page);

    await signIn(page, 'member@e2e.test');
    await visit(page, task);
    await page.getByLabel('New status').selectOption('todo');
    await page.getByRole('button', { name: 'Update status' }).click();
    await expect(page.getByText('Todo', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Task status changed', { exact: true }).first()).toBeVisible();
});

test('journey 5: backlog reorder and Kanban drag drop persist', async ({ page }, testInfo) => {
    const query = `E2E ${testInfo.project.name} backlog`;
    await signIn(page, 'manager@e2e.test');
    const project = await projectHref(page);
    await visit(page, `${project}/backlog?q=${encodeURIComponent(query)}`);
    const second = page.locator('article').filter({ hasText: `${query} second` });
    const rankSelect = second.getByLabel(/Reorder/);
    const firstValue = await rankSelect.locator('option').filter({ hasText: /Before/ }).getAttribute('value');
    await rankSelect.selectOption(firstValue);
    await second.getByRole('button', { name: 'Reorder' }).click();
    await expect(page.locator('article').first()).toContainText(`${query} second`);

    await visit(page, `${project}/board?q=${encodeURIComponent(`${query} second`)}`);
    const card = page.locator('[data-board-card]').filter({ hasText: `${query} second` });
    const statusUrl = await card.getAttribute('data-status-url');
    const responsePromise = page.waitForResponse((response) => response.request().method() === 'PATCH' && response.url() === statusUrl);
    await card.dragTo(page.locator('[data-column="todo"] [data-cards]'));
    const response = await responsePromise;
    expect(response.ok()).toBeTruthy();

    // The board reloads after the fetch completes. Navigate explicitly so the
    // assertion proves persisted state without depending on the browser's load
    // event, which can be delayed by non-critical local development assets.
    await visit(page, `${project}/board?q=${encodeURIComponent(`${query} second`)}`);
    await expect(page.locator('[data-column="todo"] [data-board-card]').filter({ hasText: `${query} second` })).toBeVisible();
});

test('journey 6: labels filter URL state and responsive task list agree', async ({ page }, testInfo) => {
    const profile = testInfo.project.name;
    await signIn(page, 'manager@e2e.test');
    const project = await projectHref(page);
    await visit(page, `${project}/labels`);
    await page.getByLabel('Label name').fill(`E2E ${profile} label`);
    await page.getByRole('button', { name: 'Add label' }).click();
    await expect(page.locator(`article input[name="name"][value="E2E ${profile} label"]`)).toBeVisible();

    await visit(page, '/tasks');
    await page.getByLabel('Search').fill('E2E browser task');
    await page.getByLabel('Todo').check();
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page).toHaveURL(/q=E2E(?:\+|%20)browser(?:\+|%20)task/);
    await expect(page.locator('p:visible').filter({ hasText: /^E2E browser task$/ }).first()).toBeVisible();
    if (profile === 'mobile') await expect(page.locator('table')).toBeHidden();
    else await expect(page.locator('table')).toBeVisible();
});

test('journey 7: watch comment and notification flow reaches the watcher', async ({ page }, testInfo) => {
    const body = `E2E ${testInfo.project.name} watcher comment`;
    await signIn(page, 'member@e2e.test');
    const task = await taskHref(page, 'E2E browser task');
    await visit(page, task);
    await expect(page.getByRole('heading', { name: 'Watchers' })).toBeVisible();
    await expect(page.getByText('E2E Member', { exact: true }).first()).toBeVisible();
    await page.getByRole('button', { name: 'Unwatch task' }).click();
    await expect(page.getByRole('button', { name: 'Watch task' })).toBeVisible();
    await page.getByRole('button', { name: 'Watch task' }).click();
    await expect(page.getByRole('button', { name: 'Unwatch task' })).toBeVisible();
    await page.getByLabel('Comment').fill(body);
    await page.getByRole('button', { name: 'Add comment' }).click();
    await expect(page.getByText(body, { exact: true })).toBeVisible();
    await signOut(page);

    await signIn(page, 'manager@e2e.test');
    await visit(page, '/notifications');
    await expect(page.getByText(/New task comment · .*E2E browser task/).first()).toBeVisible();
});

test('journey 8: multiple media upload preview private download and delete', async ({ page }, testInfo) => {
    const profile = testInfo.project.name;
    const pngName = `${profile}-pixel.png`;
    const textName = `${profile}-notes.txt`;
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64');
    await signIn(page, 'member@e2e.test');
    const task = await taskHref(page, 'E2E browser task');
    await visit(page, task);
    await page.getByLabel('Choose up to five files').setInputFiles([
        { name: pngName, mimeType: 'image/png', buffer: png },
        { name: textName, mimeType: 'text/plain', buffer: Buffer.from('TaskFlow E2E notes') },
    ]);
    await page.getByRole('button', { name: 'Upload' }).click();
    const attachments = page.getByRole('heading', { name: 'Attachments' }).locator('xpath=ancestor::article[1]');
    const pngLabel = attachments.getByText(pngName, { exact: true }).first();
    const textLabel = attachments.getByText(textName, { exact: true }).first();
    await expect(pngLabel).toBeVisible();
    await expect(textLabel).toBeVisible();

    const imageRow = pngLabel.locator('xpath=ancestor::div[contains(@class,"rounded-xl")][1]');
    await imageRow.getByRole('link', { name: 'Preview' }).click();
    await expect(page.locator('[data-preview-modal]')).toBeVisible();
    await page.getByRole('button', { name: 'Close', exact: true }).click();

    const textRow = textLabel.locator('xpath=ancestor::div[contains(@class,"rounded-xl")][1]');
    const downloadHref = await textRow.getByRole('link', { name: 'Download' }).getAttribute('href');
    const response = await page.request.get(downloadHref);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-disposition']).toContain('attachment');
    await textRow.getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(attachments.getByText(textName, { exact: true })).toHaveCount(0);
});

test('journey 9: completed and archived project screens are read only', async ({ page }) => {
    await signIn(page, 'manager@e2e.test');
    const projects = [['E2E Completed', true], ['E2E Archived', false]];
    const projectLinks = new Map();
    await visit(page, '/projects');
    for (const [name] of projects) {
        const link = page.getByRole('link', { name: new RegExp(`^${name}(?:\\s|$)`) }).first();
        await expect(link).toBeVisible();
        projectLinks.set(name, await link.getAttribute('href'));
    }

    for (const [name, canReopen] of projects) {
        const href = projectLinks.get(name);
        await visit(page, href);
        await expect(page.getByRole('link', { name: 'Create task' })).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'Edit project' })).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'Manage members' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Reopen project' })).toHaveCount(canReopen ? 1 : 0);
    }
});

test('journey 10: navigation keyboard modal focus and JS-disabled core forms remain usable', async ({ page, browser }, testInfo) => {
    const profile = testInfo.project.name;
    await signIn(page, 'manager@e2e.test');
    if (profile === 'mobile') {
        const toggle = page.getByRole('button', { name: 'Open navigation' });
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await page.keyboard.press('Escape');
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(toggle).toBeFocused();
    } else {
        const project = await projectHref(page);
        await visit(page, project);
        const archive = page.getByRole('button', { name: 'Archive' });
        await archive.click();
        await expect(page.locator('[data-confirm-modal]')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('[data-confirm-modal]')).not.toBeVisible();
        await expect(archive).toBeFocused();
    }

    const context = await browser.newContext({ javaScriptEnabled: false });
    const noJs = await context.newPage();
    await signIn(noJs, 'manager@e2e.test');
    await visit(noJs, '/projects/create');
    await noJs.getByLabel('Project name').fill(`E2E ${profile} no JS`);
    const noJsKey = profile === 'desktop' ? 'njd' : 'nJm';
    await noJs.getByLabel('Project key').fill(noJsKey);
    await expect(noJs.getByLabel('Project key')).toHaveValue(noJsKey);
    await noJs.getByRole('button', { name: 'Create project' }).click();
    await expect(noJs.getByRole('heading', { name: `E2E ${profile} no JS` })).toBeVisible();
    await expect(noJs.getByText(noJsKey.toUpperCase(), { exact: true }).first()).toBeVisible();
    await context.close();
});

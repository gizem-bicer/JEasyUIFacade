const {test, expect} = require(process.env.PLAYWRIGHT_MODULE || '@playwright/test');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');

test.beforeEach(async ({page}) => {
    await page.goto('/exface/jeasyuifacade/Tests/Playwrite/markian.html');
    await page.evaluate(() => Promise.all([editor.ready, viewer.ready]));
});

test('mention bridge sends bounded filtered requests and preserves plain insertion and activation', async ({page}) => {
    await page.addScriptTag({url: '/bower-asset/jquery/dist/jquery.min.js'});
    const requests = [];
    await page.route('**/mention-fixture', async route => {
        requests.push(new URLSearchParams(route.request().postData()));
        await route.fulfill({json: {rows: [{SEARCH_TEXT: 'Hidden filter value', LABEL: 'Review', NUMBER: 123}]}});
    });
    await page.evaluate(async () => {
        editor.destroy();
        window.tagClicks = [];
        document.getElementById('editor').addEventListener('exf-markian-tag-click', event => tagClicks.push(event.detail.value));
        const tags = MarkianAdapter.buildTags([{id: 'ticket', prefix: '#', textRegex: '\\d*', color: '#add8e6', caption: 'Ticket', filterAttribute: 'SEARCH_TEXT', tagAttribute: 'NUMBER', suggestionAttribute: 'LABEL', limit: 10, url: '/mention-fixture', headers: {}, request: {action: 'exface.Core.ReadData', object: 'record-object', resource: 'example.App.page', element: 'suggestion-button'}}]);
        window.editor = MarkianAdapter.mount(document.getElementById('editor'), {markdown: 'Review', tags}, false);
        await editor.ready;
    });
    const prose = page.locator('#editor .ProseMirror');
    await prose.focus(); await page.keyboard.press('Control+End'); await page.keyboard.type(' #');
    await expect(page.locator('#editor .markian-tag-suggestions button')).toHaveText(['Review']);
    expect(requests[0].get('rows')).toBe('10');
    expect(requests[0].get('element')).toBe('suggestion-button');
    expect(requests[0].has('data[filters][conditions][0][value]')).toBe(false);
    await page.keyboard.type('12');
    await expect.poll(() => requests.length).toBe(2);
    await expect(page.locator('#editor .markian-tag-suggestions button')).toHaveText(['Review']);
    expect(requests[1].get('data[filters][conditions][0][value]')).toBe('12');
    expect(requests[1].getAll('data[columns][]')).toEqual(['SEARCH_TEXT', 'NUMBER', 'LABEL']);
    expect(requests[1].get('data[filters][conditions][0][expression]')).toBe('SEARCH_TEXT');
    expect(requests[1].get('data[filters][operator]')).toBe('OR');
    expect(requests[1].get('data[filters][conditions][1][expression]')).toBe('NUMBER');
    expect(requests[1].get('data[filters][conditions][1][value]')).toBe('12');
    await page.keyboard.press('Enter');
    expect(await page.evaluate(() => editor.get())).toBe('Review #123 ');
    const chip = page.locator('#editor [data-markian-tag]');
    await chip.click();
    expect(await page.evaluate(() => tagClicks)).toEqual(['#123']);
    expect(await chip.evaluate(element => ({tint: element.style.getPropertyValue('--markian-tag-color'), color: getComputedStyle(element).color, proseColor: getComputedStyle(element.parentElement).color}))).toEqual({tint: '#add8e6', color: 'rgb(38, 44, 43)', proseColor: 'rgb(38, 44, 43)'});
    await prose.focus(); await page.keyboard.press('Control+End'); await page.keyboard.type('#');
    await expect(page.locator('#editor .markian-tag-suggestions')).toBeVisible();
    await page.evaluate(() => editor.setDisabled(true));
    await expect(page.locator('#editor .markian-tag-suggestions')).toHaveCount(0);
});

test('mention click lookup uses complete plain and legacy tag values without editing the draft', async ({page}) => {
    await page.addScriptTag({url: '/bower-asset/jquery/dist/jquery.min.js'});
    const requests = [];
    await page.route('**/mention-click-fixture', async route => {
        requests.push(new URLSearchParams(route.request().postData()));
        await route.fulfill({json: {rows: [{LABEL: 'Review', NUMBER: 123}]}});
    });
    for (const markdown of ['Review #123 ', 'Review [#123]() ']) {
        await page.evaluate(async markdown => {
            editor.destroy();
            const tags = MarkianAdapter.buildTags([{id: 'ticket', prefix: '#', textRegex: '\\d*', filterAttribute: 'SEARCH_TEXT', tagAttribute: 'NUMBER', suggestionAttribute: 'LABEL', limit: 10, url: '/mention-click-fixture', request: {object: 'record-object'}}]);
            window.editor = MarkianAdapter.mount(document.getElementById('editor'), {markdown, tags}, false);
            await editor.ready;
        }, markdown);
        const original = await page.evaluate(() => editor.get());
        const chip = page.locator('#editor [data-markian-tag]');
        await chip.click();
        await expect(page.locator('#editor .markian-tag-suggestions button')).toHaveText(['Review']);
        expect(requests.at(-1).get('data[filters][conditions][0][value]')).toBe('123');
        expect(requests.at(-1).get('data[filters][conditions][1][expression]')).toBe('NUMBER');
        expect(requests.at(-1).get('data[filters][conditions][1][value]')).toBe('123');
        expect(await page.evaluate(() => editor.get())).toBe(original);
        await page.keyboard.press('Escape');
        await chip.focus(); await page.keyboard.press('Enter');
        await expect(page.locator('#editor .markian-tag-suggestions button')).toHaveText(['Review']);
        expect(requests.at(-1).get('data[filters][conditions][1][value]')).toBe('123');
        if (markdown === 'Review #123 ') {
            await page.locator('#editor .markian-tag-suggestions button').click();
            expect(await page.evaluate(() => editor.get())).toBe(original);
        }
        await page.evaluate(() => editor.setDisabled(true));
        await chip.click();
        await expect(page.locator('#editor .markian-tag-suggestions')).toHaveCount(0);
    }
});

test('mention bridge skips numeric fields for text queries without dropping numeric tag lookup', async ({page}) => {
    await page.addScriptTag({url: '/bower-asset/jquery/dist/jquery.min.js'});
    const requests = [];
    await page.route('**/mention-numeric-fixture', async route => {
        requests.push(new URLSearchParams(route.request().postData()));
        await route.fulfill({json: {rows: [{LABEL: 'Review record', NUMBER: 123}]}});
    });
    const result = await page.evaluate(async () => {
        const model = {id: 'ticket', prefix: '#', filterAttribute: 'LABEL', filterAttributeIsNumeric: false, tagAttribute: 'NUMBER', tagAttributeIsNumeric: true, suggestionAttribute: 'LABEL', limit: 10, url: '/mention-numeric-fixture', request: {object: 'record-object'}};
        const tag = MarkianAdapter.buildTags([model])[0];
        const lookup = query => tag.onSuggest({query, signal: new AbortController().signal});
        const text = await lookup('roll');
        const number = await lookup('123');
        const empty = await lookup('');
        const numericOnly = MarkianAdapter.buildTags([Object.assign({}, model, {filterAttribute: 'NUMBER', filterAttributeIsNumeric: true})])[0];
        const invalid = await numericOnly.onSuggest({query: 'roll', signal: new AbortController().signal});
        return {text, number, empty, invalid};
    });
    expect(result.text).toEqual([{label: 'Review record', value: '#123'}]);
    expect(result.number).toEqual(result.text);
    expect(result.empty).toEqual(result.text);
    expect(result.invalid).toEqual([]);
    expect(requests).toHaveLength(3);
    expect(requests[0].get('data[filters][conditions][0][expression]')).toBe('LABEL');
    expect(requests[0].get('data[filters][conditions][0][value]')).toBe('roll');
    expect(requests[0].has('data[filters][conditions][1][expression]')).toBe(false);
    expect(requests[1].get('data[filters][conditions][1][expression]')).toBe('NUMBER');
    expect(requests[1].get('data[filters][conditions][1][value]')).toBe('123');
    expect(requests[2].has('data[filters][conditions][0][expression]')).toBe(false);
});

test('mention bridge aborts an in-flight transport when the provider signal is cancelled', async ({page}) => {
    await page.addScriptTag({url: '/bower-asset/jquery/dist/jquery.min.js'});
    const result = await page.evaluate(async () => {
        const original = jQuery.ajax;
        let aborted = false;
        jQuery.ajax = () => {
            const request = jQuery.Deferred();
            request.abort = () => { aborted = true; request.reject({}, 'abort'); };
            return request;
        };
        try {
            const tag = MarkianAdapter.buildTags([{id: 'ticket', prefix: '#', filterAttribute: 'NUMBER', tagAttribute: 'NUMBER', suggestionAttribute: 'LABEL', limit: 10, request: {object: 'record'}}])[0];
            const controller = new AbortController();
            const pending = tag.onSuggest({query: '12', signal: controller.signal}).catch(error => error.name);
            controller.abort();
            return {aborted, error: await pending};
        } finally { jQuery.ajax = original; }
    });
    expect(result).toEqual({aborted: true, error: 'AbortError'});
});

test('prefill, explicit clear, drafts and disabled transitions retain the binding value', async ({page}) => {
    await page.evaluate(() => {
        window.changes = 0;
        document.getElementById('editor').addEventListener('change', () => changes++);
        editor.set('# Loaded');
        editor.instance.setMode('raw');
    });
    await page.locator('#editor .cm-content').fill('# Draft');
    await expect.poll(() => page.evaluate(() => editor.get())).toBe('# Draft');
    const changes = await page.evaluate(() => window.changes);
    expect(changes).toBeGreaterThan(0);
    await page.evaluate(() => {
        editor.set(editor.get());
        editor.setDisabled(true);
    });
    expect(await page.evaluate(() => ({value: editor.get(), readonly: editor.instance.readOnly, changes}))).toEqual({value: '# Draft', readonly: true, changes});
    await page.evaluate(() => {
        editor.setDisabled(false);
        editor.set('');
    });
    expect(await page.evaluate(() => ({value: editor.get(), changes: window.changes}))).toEqual({value: '', changes});
});

test('pre-readiness replacement and permission changes reach the initialized editor', async ({page}) => {
    await page.evaluate(async () => {
        editor.destroy();
        window.editor = MarkianAdapter.mount(document.getElementById('editor'), {markdown: '# Initial'}, false);
        editor.set('# Prefill');
        editor.setDisabled(true);
        if (editor.get() !== '# Prefill') throw new Error('Pending prefill was not projected');
        await editor.ready;
    });
    expect(await page.evaluate(() => ({value: editor.get(), readonly: editor.instance.readOnly}))).toEqual({value: '# Prefill', readonly: true});
    await expect(page.locator('#editor [data-markian]')).toBeVisible();
});

test('disabling image insertion preserves default menus and custom nested toolbar entries', async ({page}) => {
    await expect(page.locator('#editor .markian-toolbar')).toContainText('Formatting');
    await expect(page.locator('#editor .markian-toolbar')).toContainText('View mode');
    await expect(page.locator('#editor .markian-toolbar [data-command="image"]')).toHaveCount(0);
    const unchanged = await page.evaluate(async () => {
        editor.destroy();
        const toolbar = [{id: 'host-tools', type: 'menu', label: 'Host tools', items: [
            {id: 'nested', type: 'group', items: ['image', {id: 'host-copy', command: 'copy', label: 'Host copy'}]},
            'help'
        ]}];
        const original = JSON.stringify(toolbar);
        window.editor = MarkianAdapter.mount(document.getElementById('editor'), {markdown: '# Custom', toolbar}, false, null, false);
        await editor.ready;
        return JSON.stringify(toolbar) === original;
    });
    expect(unchanged).toBe(true);
    const toolbar = page.locator('#editor .markian-toolbar');
    await expect(toolbar).toContainText('Host tools');
    await expect(toolbar).toContainText('Host copy');
    await expect(toolbar).not.toContainText('Formatting');
    await expect(toolbar.locator('[data-command="image"]')).toHaveCount(0);
    await expect(toolbar.locator('[data-command="copy"]')).toHaveCount(1);
    await page.evaluate(() => editor.instance.setMode('raw'));
    await expect(toolbar).toContainText('Host copy');
    await expect(toolbar.locator('[data-command="image"]')).toHaveCount(0);
});

for (const template of [
    {name: 'metro blue', theme: 'metro-blue', emphasis: '', focus: 'rgb(161, 196, 207)'},
    {name: 'material', theme: 'material', emphasis: '', focus: 'rgb(98, 0, 238)'},
    {name: 'material light', theme: 'material-light', emphasis: '', focus: 'rgb(98, 0, 238)'},
    {name: 'material grey', theme: 'material', emphasis: '#546E7A', focus: 'rgb(84, 110, 122)'},
    {name: 'material purple', theme: 'material', emphasis: '#6A1B9A', focus: 'rgb(106, 27, 154)'},
    {name: 'material red', theme: 'material', emphasis: '#BF360C', focus: 'rgb(191, 54, 12)'}
]) {
    test(`page template ${template.name} shares left-border focus across Markdown and text inputs`, async ({page}) => {
        const themeRoot = '/exface/jeasyuifacade/Facades/js/themes/';
        await page.addStyleTag({url: themeRoot + template.theme + '/easyui.css'});
        await page.addStyleTag({url: themeRoot + 'jeasyui.exface.css'});
        await page.addStyleTag({url: themeRoot + 'jeasyui-' + (template.theme === 'metro-blue' ? 'metro-blue' : 'material') + '.exface.css'});
        if (template.theme === 'material-light') {
            await page.addStyleTag({url: themeRoot + 'jeasyui-material-light.exface.css'});
        }
        const source = readFileSync(join(__dirname, '../../Facades/Templates/EuiDefaultTemplate.html'), 'utf8');
        const emphasisCss = await page.evaluate(({source, emphasis}) => {
            const parsed = new DOMParser().parseFromString(source, 'text/html');
            return parsed.querySelector('style').textContent.replaceAll('[#~facade:theme_emphasis_color#]', emphasis);
        }, {source, emphasis: template.emphasis});
        await page.addStyleTag({content: emphasisCss});
        await page.evaluate(() => {
            document.getElementById('editor').classList.add('exf-input-markdown');
            const wrapper = document.createElement('div');
            wrapper.className = 'exf-input exf-inputtext';
            wrapper.style.height = '70px';
            const textarea = document.createElement('textarea');
            textarea.value = 'Plain text';
            wrapper.append(textarea);
            document.getElementById('editor').before(wrapper);
        });
        const markdown = page.locator('#editor .markian');
        const text = page.locator('.exf-inputtext textarea');
        for (const width of [1100, 390]) {
            await page.setViewportSize({width, height: 850});
            for (const [control, target, other] of [[markdown, markdown.locator('.ProseMirror'), text], [text, text, markdown.locator('.ProseMirror')]]) {
                await other.click();
                const before = await control.evaluate(element => {
                    const style = getComputedStyle(element);
                    return {top: style.borderTopColor, right: style.borderRightColor, bottom: style.borderBottomColor, left: style.borderLeftColor, width: element.offsetWidth, height: element.offsetHeight};
                });
                await target.click();
                await expect(control).toHaveCSS('border-radius', '0px');
                await expect(control).toHaveCSS('border-left-color', template.focus);
                await expect(control).toHaveCSS('border-top-color', before.top);
                await expect(control).toHaveCSS('border-right-color', before.right);
                await expect(control).toHaveCSS('border-bottom-color', before.bottom);
                expect(await control.evaluate(element => ({width: element.offsetWidth, height: element.offsetHeight}))).toEqual({width: before.width, height: before.height});
                await other.click();
                await expect(control).toHaveCSS('border-left-color', before.left);
            }
        }
    });
}

test('mention menus share neutral dropdown rows and selected styling', async ({page}) => {
    await page.addStyleTag({url: '/exface/jeasyuifacade/Facades/js/themes/jeasyui.exface.css'});
    await page.addStyleTag({url: '/exface/jeasyuifacade/Facades/js/themes/jeasyui-material.exface.css'});
    await page.evaluate(async () => {
        editor.destroy();
        document.getElementById('editor').classList.add('exf-input-markdown');
        window.editor = MarkianAdapter.mount(document.getElementById('editor'), {markdown: 'Review', tags: [{id: 'ticket', prefix: '#', onSuggest: () => [
            {label: 'First record', value: '#123'}, {label: 'Second record', value: '#124'}, {label: 'Third record', value: '#125'}
        ]}]}, false);
        await editor.ready;
    });
    await page.locator('#editor .markian-menu[data-toolbar-item="formatting"] > summary').click();
    const regular = page.locator('#editor .markian-menu-items:popover-open [data-command="bold"]');
    const normal = await regular.evaluate(element => ({background: getComputedStyle(element).backgroundColor, color: getComputedStyle(element).color}));
    await regular.hover();
    const hover = await regular.evaluate(element => ({background: getComputedStyle(element).backgroundColor, color: getComputedStyle(element).color}));
    await page.keyboard.press('Escape');
    const prose = page.locator('#editor .ProseMirror');
    await prose.focus(); await page.keyboard.press('Control+End'); await page.keyboard.type(' #');
    const choices = page.locator('#editor .markian-tag-suggestions button');
    await expect(choices).toHaveCount(3);
    for (const width of [1100, 390]) {
        await page.setViewportSize({width, height: 850});
        for (const index of [1, 2]) {
            expect(await choices.nth(index).evaluate(element => ({background: getComputedStyle(element).backgroundColor, color: getComputedStyle(element).color}))).toEqual(normal);
        }
        expect(await choices.first().evaluate(element => ({background: getComputedStyle(element).backgroundColor, color: getComputedStyle(element).color}))).toEqual(hover);
    }
    await prose.focus(); await page.keyboard.press('ArrowDown');
    await expect(choices.nth(1)).toHaveAttribute('aria-selected', 'true');
    expect(await choices.first().evaluate(element => ({background: getComputedStyle(element).backgroundColor, color: getComputedStyle(element).color}))).toEqual(normal);
});

test('jEasyUI themes scope dropdown and image link search popup styling', async ({page}) => {
    await page.addStyleTag({url: '/exface/jeasyuifacade/Facades/js/themes/jeasyui.exface.css'});
    await page.addStyleTag({url: '/exface/jeasyuifacade/Facades/js/themes/jeasyui-material.exface.css'});
    const original = await page.evaluate(async () => {
        document.getElementById('editor').classList.add('exf-input-markdown');
        const host = document.createElement('div');
        host.id = 'unthemed';
        host.style.height = '300px';
        document.body.append(host);
        window.unthemed = MarkianAdapter.mount(host, {markdown: '# Independent'}, false);
        await unthemed.ready;
        await unthemed.instance.execute('search');
        return editor.get();
    });
    await expect(page.locator('#unthemed .markian-popup .markian-panel input[type=text]').first()).toHaveCSS('min-height', '34px');
    await page.keyboard.press('Escape');
    await page.locator('#editor .markian-menu[data-toolbar-item="formatting"] > summary').click();
    const menu = page.locator('#editor .markian-menu-items:popover-open');
    await expect(menu).toHaveCSS('font-size', '12px');
    await expect(menu).toHaveCSS('border-radius', '0px');
    const bold = menu.locator('[data-command="bold"]');
    await bold.hover();
    await expect(bold).toHaveCSS('background-color', 'rgb(221, 221, 221)');
    await bold.focus();
    await page.keyboard.press('Escape');
    await expect(menu).toHaveCount(0);
    for (const width of [1100, 390]) {
        await page.setViewportSize({width, height: 850});
        for (const command of ['search', 'link', 'image']) {
            await page.evaluate(command => editor.instance.execute(command), command);
            const popup = page.locator('#editor .markian-popup:popover-open');
            await expect(popup).toBeVisible();
            const input = popup.locator('input[type=text]').first();
            await input.focus();
            await expect(input).toHaveCSS('height', '26px');
            await expect(input).toHaveCSS('font-size', '12px');
            await expect(input).toHaveCSS('border-bottom-color', 'rgb(98, 0, 238)');
            const panel = popup.locator('.markian-panel');
            await expect(panel).toHaveCSS('border-radius', '2px');
            if (command !== 'search') {
                await expect(panel.locator('.markian-panel-actions > button:not(.markian-button)').first()).toHaveCSS('background-color', 'rgb(98, 0, 238)');
            }
            expect(await popup.evaluate(element => {
                const bounds = element.getBoundingClientRect();
                return bounds.left >= 0 && bounds.right <= innerWidth && element.scrollWidth <= element.clientWidth;
            })).toBe(true);
            await popup.screenshot({path: test.info().outputPath(`${command}-${width}.png`)});
            await page.keyboard.press('Escape');
            await expect(popup).toHaveCount(0);
        }
    }
    expect(await page.evaluate(() => editor.get())).toBe(original);
});

test('GitHub wrapper classes style editor preview and updated viewers in isolation', async ({page}) => {
    await page.addStyleTag({url: '/exface/jeasyuifacade/Facades/js/themes/jeasyui.exface.css'});
    await page.evaluate(async () => {
        editor.destroy();
        viewer.destroy();
        document.getElementById('editor').classList.add('exf-input-markdown');
        document.getElementById('viewer').classList.add('exf-markdown');
        const classes = ['markdown-body'];
        const options = {
            markdown: '# Heading\n\nA `token`.\nNext line.\n\n- One\n- Two\n\n| A | B |\n| --- | --- |\n| a | b |',
            markdownCssUrls: ['/npm-asset/github-markdown-css/github-markdown-light.css'],
            markdownCssClasses: classes,
            softbreaks: true
        };
        window.editor = MarkianAdapter.mount(document.getElementById('editor'), options, false);
        window.viewer = MarkianAdapter.mount(document.getElementById('viewer'), options, true);
        classes.push('late-mutation');
        const host = document.createElement('div');
        host.id = 'independent-viewer';
        document.body.append(host);
        window.independentViewer = MarkianViewer.create(host, {markdown: '`Default`'});
        await Promise.all([editor.ready, viewer.ready, independentViewer.ready]);
    });
    const visual = page.locator('#editor .ProseMirror.markian-document');
    const display = page.locator('#viewer .markian-document');
    for (const root of [visual, display]) {
        await expect(root).toHaveClass(/markdown-body/);
        await expect(root).not.toHaveClass(/late-mutation/);
        await expect(root.locator('code').first()).not.toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
        await expect(root).toHaveCSS('font-size', '14px');
    }
    await expect(page.locator('#editor [data-markian]')).not.toHaveAttribute('data-markian-markdown-default');
    await expect(page.locator('#viewer [data-markian-viewer]')).not.toHaveAttribute('data-markian-markdown-default');
    await expect(page.locator('#viewer .markian-document > br, #viewer ul > br')).toHaveCount(0);
    await expect(display.locator('table')).toHaveCSS('display', 'table');
    await expect(page.locator('#independent-viewer code')).toHaveCSS('background-color', 'rgb(241, 243, 244)');
    await expect(page.locator('#independent-viewer .markdown-body')).toHaveCount(0);
    await page.evaluate(() => editor.instance.openView('preview'));
    const preview = page.locator('#editor .markian-view.markian-document');
    await expect(preview).toHaveClass(/markdown-body/);
    await expect(preview.locator('code').first()).not.toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await page.evaluate(() => editor.instance.back());
    await page.evaluate(() => editor.instance.openView('preview'));
    await expect(preview).toHaveClass(/markdown-body/);
    await page.evaluate(() => viewer.set('# Replacement\n\n`Updated`\nnext line.'));
    await expect(display.locator('h1')).toHaveText('Replacement');
    await expect(display).toHaveClass(/markdown-body/);
    await expect(display.locator('code')).not.toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await expect(page.locator('#viewer > link[data-markian-stylesheet]')).toHaveCount(1);
    await display.screenshot({path: test.info().outputPath('github-viewer.png')});
    expect(await page.evaluate(() => {
        const links = [...document.querySelectorAll('#viewer > link[data-markian-stylesheet]')];
        viewer.destroy();
        return links.every(link => !link.isConnected);
    })).toBe(true);
});

test('floating toolbar stays outside double-click and multiline selections with GitHub styling', async ({page}) => {
    await page.addStyleTag({url: '/exface/jeasyuifacade/Facades/js/themes/jeasyui.exface.css'});
    const original = await page.evaluate(async () => {
        editor.destroy();
        document.getElementById('editor').classList.add('exf-input-markdown');
        window.editor = MarkianAdapter.mount(document.getElementById('editor'), {
            markdown: 'First selectable word in the document.\n\nSecond paragraph extends the selection.\n\nThird paragraph stays outside it.',
            markdownCssUrls: ['/npm-asset/github-markdown-css/github-markdown-light.css'],
            markdownCssClasses: ['markdown-body'],
            sidebarVisible: false
        }, false);
        await editor.ready;
        return editor.get();
    });
    const prose = page.locator('#editor .ProseMirror');
    const toolbar = page.locator('#editor [aria-label="Selection commands"]');
    const separated = () => toolbar.evaluate(element => {
        const range = getSelection().getRangeAt(0).getBoundingClientRect();
        const bounds = element.getBoundingClientRect();
        return bounds.bottom <= range.top - 7 || bounds.top >= range.bottom + 7;
    });
    for (const width of [1100, 390]) {
        await page.setViewportSize({width, height: 850});
        await prose.locator('p').first().dblclick();
        await expect(toolbar).toBeVisible();
        await expect.poll(separated).toBe(true);
        await prose.evaluate(element => {
            const paragraphs = element.querySelectorAll('p');
            const range = document.createRange();
            range.setStart(paragraphs[0].firstChild, 0);
            range.setEnd(paragraphs[1].firstChild, paragraphs[1].textContent.length);
            const selection = getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        });
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        await expect(toolbar).toBeVisible();
        await expect.poll(separated).toBe(true);
        await page.locator('#editor [data-markian]').screenshot({path: test.info().outputPath(`selection-${width}.png`)});
    }
    expect(await page.evaluate(() => editor.get())).toBe(original);
});

test('viewer content stylesheet failure restores the original host', async ({page}) => {
    await page.route('**/missing-viewer.css', route => route.fulfill({status: 404, body: ''}));
    expect(await page.evaluate(async () => {
        const host = document.createElement('div');
        host.textContent = 'Original content';
        document.body.append(host);
        const instance = MarkianViewer.create(host, {markdown: '# Pending', markdownCssUrls: ['/missing-viewer.css']});
        try { await instance.ready; return false; }
        catch { return host.textContent === 'Original content' && host.querySelectorAll('[data-markian-viewer], link').length === 0; }
    })).toBe(true);
});

test('viewer keeps source, sanitizes output, updates and has no editing controls', async ({page}) => {
    await page.evaluate(async () => {
        viewer.set('# Replacement\n\n- [x] Complete\n\n<script>window.xss = true</script>\n\n[unsafe](javascript:alert(1))');
        await viewer.instance.ready;
    });
    await expect(page.locator('#viewer h1')).toHaveText('Replacement');
    await expect(page.locator('#viewer input[type=checkbox]')).toBeDisabled();
    expect(await page.evaluate(() => ({xss: window.xss, value: viewer.get()}))).toEqual({xss: undefined, value: '# Replacement\n\n- [x] Complete\n\n<script>window.xss = true</script>\n\n[unsafe](javascript:alert(1))'});
    await expect(page.locator('#viewer .markian-toolbar, #viewer [contenteditable], #viewer a[href^="javascript:"]')).toHaveCount(0);
    await page.evaluate(() => viewer.set(''));
    await expect(page.locator('#viewer h1')).toHaveCount(0);
    expect(await page.evaluate(() => viewer.get())).toBe('');
});

test('removal disposes owned instances and a reused ID receives fresh source', async ({page}) => {
    await page.evaluate(() => document.getElementById('editor').remove());
    await expect.poll(() => page.evaluate(() => editor.instance.element.isConnected)).toBe(false);
    await page.evaluate(async () => {
        const host = document.createElement('div');
        host.id = 'editor';
        host.style.height = '400px';
        document.body.prepend(host);
        window.editor = MarkianAdapter.mount(host, {markdown: '# New record'}, false);
        await editor.ready;
    });
    expect(await page.evaluate(() => editor.get())).toBe('# New record');
    await expect(page.locator('#editor [data-markian]')).toHaveCount(1);
    await expect(page.locator('#viewer [data-markian-viewer]')).toHaveCount(1);
});

test('compact layout keeps the editor and viewer within the viewport', async ({page}) => {
    await page.setViewportSize({width: 390, height: 844});
    await expect(page.locator('#editor [data-markian]')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({path: test.info().outputPath('compact.png'), fullPage: true});
});

test('AJAX factory replacement registers Mermaid for the next dialog', async ({page}) => {
    await page.addScriptTag({url: '/axenox/markian/dist/extensions/mermaid.min.js'});
    await page.evaluate(async () => {
        editor.destroy();
        const source = '/exface/core/Facades/AbstractAjaxFacade/js/mermaid/mermaid.min.js';
        window.editor = MarkianAdapter.mount(document.getElementById('editor'), {markdown: '# First dialog'}, false, source);
        await editor.ready;
        editor.destroy();
    });
    await page.addScriptTag({url: '/axenox/markian/dist/markian.min.js'});
    await page.evaluate(async () => {
        const source = '/exface/core/Facades/AbstractAjaxFacade/js/mermaid/mermaid.min.js';
        window.editor = MarkianAdapter.mount(document.getElementById('editor'), {markdown: '```mermaid\nflowchart LR\n  A[Start] --> B[Finish]\n```'}, false, source);
        await editor.ready;
    });
    await expect(page.locator('#editor .markian-diagram-canvas svg')).toBeVisible();
    await expect(page.locator('#editor')).not.toHaveAttribute('data-markian-error');
    expect(await page.evaluate(() => editor.get())).toContain('flowchart LR');
});

for (const [runtime, source] of [
    ['Core', '/exface/core/Facades/AbstractAjaxFacade/js/mermaid/mermaid.min.js'],
    ['Mermaid 12', '/axenox/markian/node_modules/mermaid/dist/mermaid.min.js']
]) test(`Mermaid keeps the ${runtime} runtime default presentation in editor and viewer`, async ({page}) => {
    await page.addScriptTag({url: '/axenox/markian/dist/extensions/mermaid.min.js'});
    await page.addScriptTag({url: '/axenox/markian/dist/extensions/viewer-mermaid.min.js'});
    await page.addStyleTag({url: '/axenox/markian/dist/extensions/mermaid.css'});
    await page.addStyleTag({url: '/axenox/markian/dist/extensions/viewer-mermaid.css'});
    await page.evaluate(async source => {
        editor.destroy();
        viewer.destroy();
        window.diagramSource = 'flowchart LR\nsubgraph Group\n A[Start] -->|Input label| B[Finish]\nend\nB --> C[Custom]\nclassDef custom fill:#fff4cc,stroke:#bb6600,color:#552200\nclass C custom';
        const markdown = '```mermaid\n' + diagramSource + '\n```';
        window.editor = MarkianAdapter.mount(document.getElementById('editor'), {markdown}, false, source);
        window.viewer = MarkianAdapter.mount(document.getElementById('viewer'), {markdown}, true, source);
        await Promise.all([editor.ready, viewer.ready]);
    }, source);
    await expect(page.locator('#editor .markian-diagram[data-state=ready]')).toHaveCount(1);
    await expect(page.locator('#viewer svg')).toHaveCount(1);
    for (const theme of ['light', 'dark']) {
        await page.evaluate(async theme => {
            editor.instance.setTheme(theme);
            await viewer.instance.setTheme(theme);
        }, theme);
        await expect(page.locator('#editor .markian-diagram[data-state=ready]')).toHaveCount(1);
        const compared = await page.evaluate(async ({theme, source}) => {
            const runtimeUrl = new URL(source, location.href).href;
            const frame = [...document.querySelectorAll('.markian-mermaid-runtime')].find(frame =>
                frame.contentWindow.mermaid && [...frame.contentDocument.scripts].some(script => script.src === runtimeUrl));
            const local = frame.contentDocument;
            const api = frame.contentWindow.mermaid;
            api.initialize({startOnLoad: false, securityLevel: 'strict', theme: theme === 'dark' ? 'dark' : 'default', htmlLabels: false});
            const container = local.createElement('div');
            local.body.append(container);
            try {
                const result = await api.render('direct-mermaid-baseline', diagramSource, container);
                container.innerHTML = result.svg;
                const properties = ['fill', 'stroke', 'stroke-width', 'font-family', 'font-size', 'text-anchor', 'stop-color', 'stop-opacity'];
                const selectors = ['.node rect', '.node text', '.cluster rect', '.edgeLabel text', '.edgeLabel rect', '.flowchart-link', 'marker path', 'linearGradient stop'];
                const capture = root => Object.fromEntries(selectors.map(selector => [selector, [...root.querySelectorAll(selector)].map(element => {
                    const style = element.ownerDocument.defaultView.getComputedStyle(element);
                    return Object.fromEntries(properties.map(property => [property, style.getPropertyValue(property).replaceAll(root.id, 'diagram')]));
                })]));
                return {
                    expected: capture(container.querySelector('svg')),
                    editor: capture(document.querySelector('#editor .markian-diagram-canvas > svg')),
                    viewer: capture(document.querySelector('#viewer svg'))
                };
            } finally { container.remove(); }
        }, {theme, source});
        expect(compared.expected['.node rect'].length).toBeGreaterThanOrEqual(3);
        expect(compared.expected['.edgeLabel text'].length).toBeGreaterThan(0);
        expect(compared.editor).toEqual(compared.expected);
        expect(compared.viewer).toEqual(compared.expected);
        await expect(page.locator('#viewer svg style')).toHaveCount(1);
        await expect(page.locator('#editor .markian-diagram-canvas > svg style')).toHaveCount(1);
    }
    await page.evaluate(async () => { editor.instance.setTheme('light'); await viewer.instance.setTheme('light'); });
    await expect(page.locator('#editor .markian-diagram[data-state=ready]')).toHaveCount(1);
    for (const width of [1100, 390]) {
        await page.setViewportSize({width, height: 850});
        await page.locator('#viewer').screenshot({path: test.info().outputPath(`mermaid-default-${width}.png`)});
    }
});

test('Mermaid retains original CSS while removing active SVG content', async ({page}) => {
    const external = [];
    page.on('request', request => { if (request.url().startsWith('https://example.test/')) external.push(request.url()); });
    await page.route('**/mermaid-styles-test.js', route => route.fulfill({contentType: 'text/javascript', body: `
        window.mermaid = {initialize() {}, async render(id) {
            return {svg: '<svg xmlns="http://www.w3.org/2000/svg" id="' + id + '" viewBox="0 0 100 50">'
                + '<style>body{--markian-mermaid-style-probe:retained} rect{fill:#ececff}</style>'
                + '<rect width="90" height="40" style="fill:#fff4cc;stroke:#bb6600" onload="parent.compromised=true"/>'
                + '<text x="5" y="20">Safe</text><image href="https://example.test/track"/><use href="https://example.test/use.svg#x"/>'
                + '<foreignObject><iframe src="https://example.test/frame"/></foreignObject><script>parent.compromised=true</script></svg>'};
        }};`
    }));
    await page.addScriptTag({url: '/axenox/markian/dist/extensions/viewer-mermaid.min.js'});
    await page.evaluate(async () => {
        viewer.destroy();
        window.viewer = MarkianAdapter.mount(document.getElementById('viewer'), {markdown: '```mermaid\nflowchart LR\n A-->B\n```'}, true, '/mermaid-styles-test.js');
        await viewer.ready;
    });
    await expect(page.locator('#viewer svg rect')).toHaveCSS('fill', 'rgb(255, 244, 204)');
    await expect(page.locator('#viewer svg style')).toHaveText('body{--markian-mermaid-style-probe:retained} rect{fill:#ececff}');
    await expect(page.locator('#viewer svg rect')).toHaveAttribute('style', 'fill:#fff4cc;stroke:#bb6600');
    await expect(page.locator('#viewer script, #viewer foreignObject, #viewer image, #viewer use, #viewer [onload]')).toHaveCount(0);
    expect(await page.locator('body').evaluate(element => getComputedStyle(element).getPropertyValue('--markian-mermaid-style-probe').trim())).toBe('retained');
    expect(await page.evaluate(() => window.compromised)).toBeUndefined();
    expect(external).toEqual([]);
});

test('editor and viewer render diagrams using the actual Core Mermaid runtime', async ({page}) => {
    await page.addScriptTag({url: '/axenox/markian/dist/extensions/mermaid.min.js'});
    await page.addScriptTag({url: '/axenox/markian/dist/extensions/viewer-mermaid.min.js'});
    await page.evaluate(async () => {
        editor.destroy();
        viewer.destroy();
        const markdown = '```mermaid\nflowchart LR\n  A[Start] --> B[Finish]\n```';
        const source = '/exface/core/Facades/AbstractAjaxFacade/js/mermaid/mermaid.min.js';
        window.editor = MarkianAdapter.mount(document.getElementById('editor'), {markdown}, false, source);
        window.viewer = MarkianAdapter.mount(document.getElementById('viewer'), {markdown}, true, source);
        await Promise.all([editor.ready, viewer.ready]);
    });
    await expect(page.locator('#editor .markian-diagram-canvas svg')).toBeVisible();
    await expect(page.locator('#viewer .markian-rendered-fence svg')).toBeVisible();
    await expect(page.locator('#viewer .markian-rendered-fence')).toContainText('Finish');
    expect(await page.evaluate(() => viewer.get())).toContain('flowchart LR');
    expect(await page.evaluate(() => editor.get())).toContain('flowchart LR');
});
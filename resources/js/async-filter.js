let activeRequest = null;
let requestSequence = 0;

const scopeSelector = (name) => `[data-async-filter-scope="${CSS.escape(name)}"]`;

function announce(message) {
    let status = document.getElementById('tpss-filter-status');

    if (!status) {
        status = document.createElement('div');
        status.id = 'tpss-filter-status';
        status.className = 'tpss-sr-only';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        document.body.appendChild(status);
    }

    status.textContent = message;
}

function scopeNameFor(element) {
    return element?.closest('[data-async-filter-scope]')?.dataset.asyncFilterScope || '';
}

function formUrl(form) {
    const url = new URL(form.action || window.location.href, window.location.origin);
    const params = new URLSearchParams();

    for (const [name, value] of new FormData(form).entries()) {
        if (String(value).trim() !== '') {
            params.append(name, value);
        }
    }

    url.search = params.toString();
    return url;
}

function disconnectEnhancedSelects(root) {
    root.querySelectorAll('select').forEach((select) => {
        select._tpssSelect?.observer?.disconnect?.();
    });
}

function replaceScope(currentScope, nextScope) {
    nextScope.querySelectorAll('script').forEach((script) => script.remove());
    disconnectEnhancedSelects(currentScope);

    if (window.Alpine?.destroyTree) {
        window.Alpine.destroyTree(currentScope);
    }

    const replace = () => currentScope.replaceWith(nextScope);

    if (window.Alpine?.mutateDom) {
        window.Alpine.mutateDom(replace);
    } else {
        replace();
    }

    if (window.Alpine?.initTree) {
        window.Alpine.initTree(nextScope);
    }

    window.tpssInitChoices?.(nextScope);
    document.dispatchEvent(new CustomEvent('tpss:filter-updated', {
        detail: { scope: nextScope.dataset.asyncFilterScope },
    }));
}

async function navigate(urlValue, options = {}) {
    const url = new URL(urlValue, window.location.origin);
    const requestedScope = options.scope || scopeNameFor(options.source || document.activeElement);
    const currentScope = requestedScope ? document.querySelector(scopeSelector(requestedScope)) : null;

    if (url.origin !== window.location.origin || !currentScope) {
        window.location.assign(url.toString());
        return;
    }

    activeRequest?.abort();
    activeRequest = new AbortController();
    const sequence = ++requestSequence;
    const focusedName = document.activeElement?.getAttribute?.('name');

    currentScope.classList.add('is-filter-loading');
    currentScope.setAttribute('aria-busy', 'true');
    document.documentElement.classList.add('is-async-filtering');
    announce('กำลังกรองข้อมูล');

    try {
        const response = await fetch(url.toString(), {
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: activeRequest.signal,
        });

        if (!response.ok) {
            throw new Error(`Filter request failed with ${response.status}`);
        }

        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const nextScope = page.querySelector(scopeSelector(requestedScope));

        if (!nextScope) {
            throw new Error(`Filter scope "${requestedScope}" was not found`);
        }

        replaceScope(currentScope, nextScope);
        document.title = page.title || document.title;

        if (options.history !== false) {
            window.history.pushState({ asyncFilterScope: requestedScope }, '', url.toString());
        }

        if (focusedName) {
            nextScope.querySelector(`[name="${CSS.escape(focusedName)}"]`)?.focus({ preventScroll: true });
        }

        announce('กรองข้อมูลเรียบร้อยแล้ว');
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }

        window.location.assign(url.toString());
    } finally {
        if (sequence === requestSequence) {
            document.documentElement.classList.remove('is-async-filtering');
            document.querySelector(scopeSelector(requestedScope))?.classList.remove('is-filter-loading');
            document.querySelector(scopeSelector(requestedScope))?.removeAttribute('aria-busy');
        }
    }
}

document.addEventListener('change', (event) => {
    const field = event.target.closest?.('[data-async-filter-change]');
    const form = field?.form;

    if (!field || !form || !field.closest('[data-async-filter-scope]')) {
        return;
    }

    const resetNames = (field.dataset.asyncFilterReset || '')
        .split(',')
        .map((name) => name.trim())
        .filter(Boolean);

    resetNames.forEach((name) => {
        const resetField = form.elements.namedItem(name);
        if (resetField) resetField.value = '';
    });

    navigate(formUrl(form), { source: field });
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest?.('form[data-async-filter-form]');
    if (!form) return;

    event.preventDefault();
    navigate(formUrl(form), { source: form });
});

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[data-async-filter-link]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    navigate(link.href, { source: link });
});

window.addEventListener('popstate', () => {
    const scope = document.querySelector('[data-async-filter-scope]');
    if (!scope) return;

    navigate(window.location.href, {
        scope: scope.dataset.asyncFilterScope,
        history: false,
    });
});

window.tpssAsyncFilter = { navigate };

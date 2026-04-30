const storageKey = 'memoshot.theme';
const themeOptions = ['light', 'dark', 'system'];
let systemThemeQuery = null;

const isValidOption = (value) => themeOptions.includes(value);

const normalizeTheme = (value) => {
    const normalized = String(value ?? '').toLowerCase();

    if (normalized.includes('light')) {
        return 'light';
    }

    if (normalized.includes('dark')) {
        return 'dark';
    }

    return 'dark';
};

const readStoredPreference = () => {
    try {
        return window.localStorage.getItem(storageKey);
    } catch {
        return null;
    }
};

const storePreference = (preference) => {
    try {
        window.localStorage.setItem(storageKey, preference);
    } catch {
        // Theme switching still works for the current page when storage is blocked.
    }
};

const systemTheme = () => {
    if (!window.matchMedia) {
        return 'dark';
    }

    return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
};

const resolvedTheme = (preference) => (preference === 'system' ? systemTheme() : preference);

const setButtonState = (preference, theme) => {
    document.querySelectorAll('[data-theme-option]').forEach((button) => {
        const active = button.getAttribute('data-theme-option') === preference;

        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        button.toggleAttribute('data-active', active);
    });

    document.querySelectorAll('[data-theme-current]').forEach((element) => {
        element.textContent = preference === 'system' ? `System (${theme})` : theme;
    });
};

export const applyTheme = (preference, persist = true) => {
    const nextPreference = isValidOption(preference) ? preference : 'dark';
    const nextTheme = resolvedTheme(nextPreference);

    document.documentElement.setAttribute('data-theme', nextTheme);
    document.documentElement.setAttribute('data-theme-preference', nextPreference);

    if (document.body) {
        document.body.setAttribute('data-theme', nextTheme);
    }

    if (persist) {
        storePreference(nextPreference);
    }

    setButtonState(nextPreference, nextTheme);

    window.dispatchEvent(new CustomEvent('memoshot-theme-change', {
        detail: {
            preference: nextPreference,
            theme: nextTheme,
        },
    }));
};

const bindThemeButtons = () => {
    document.querySelectorAll('[data-theme-option]').forEach((button) => {
        if (button.dataset.themeBound === 'true') {
            return;
        }

        button.dataset.themeBound = 'true';
        button.addEventListener('click', () => applyTheme(button.getAttribute('data-theme-option')));
    });
};

const bindSystemListener = () => {
    if (!window.matchMedia || systemThemeQuery) {
        return;
    }

    systemThemeQuery = window.matchMedia('(prefers-color-scheme: light)');
    systemThemeQuery.addEventListener('change', () => {
        if (document.documentElement.getAttribute('data-theme-preference') === 'system') {
            applyTheme('system', false);
        }
    });
};

export const initThemeSwitcher = () => {
    const initialTheme = normalizeTheme(document.body?.dataset.theme || document.documentElement.dataset.theme || 'dark');
    const storedPreference = readStoredPreference();
    const preference = isValidOption(storedPreference) ? storedPreference : initialTheme;

    bindSystemListener();
    bindThemeButtons();
    applyTheme(preference, isValidOption(storedPreference));

    window.memoShotTheme = {
        apply: applyTheme,
        current: () => ({
            preference: document.documentElement.getAttribute('data-theme-preference') || preference,
            theme: document.documentElement.getAttribute('data-theme') || resolvedTheme(preference),
        }),
    };
};

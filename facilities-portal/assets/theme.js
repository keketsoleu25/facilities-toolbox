// --------------------------------------------------
// Facilities Toolbox - Theme Controller
// --------------------------------------------------
//
// Responsibilities:
// - follow the user's OS theme on first visit
// - persist an explicit light/dark preference
// - apply the selected theme before interaction
// - add a compact theme toggle to portal top bars
// - inject light-theme overrides without duplicating
//   the existing shared design system stylesheet
// --------------------------------------------------

(() => {
    const storageKey = 'facilities-toolbox-theme';
    const root = document.documentElement;

    const lightThemeCss = `
        html[data-theme="light"] {
            color-scheme: light;
            --bg: #f5f7fb;
            --bg-elevated: #ffffff;
            --panel: #ffffff;
            --panel-soft: #f7f9fc;
            --border: rgba(15, 23, 42, 0.12);
            --text: #111827;
            --muted: #64748b;
            --cyan: #0891b2;
            --cyan-soft: rgba(8, 145, 178, 0.10);
            --violet: #7c3aed;
            --violet-soft: rgba(124, 58, 237, 0.09);
            --green: #16a34a;
            --green-soft: rgba(22, 163, 74, 0.09);
            --amber: #d97706;
            --amber-soft: rgba(217, 119, 6, 0.09);
            --red: #e11d48;
            --red-soft: rgba(225, 29, 72, 0.08);
            --shadow: 0 14px 38px rgba(15, 23, 42, 0.08);
        }

        html[data-theme="light"] body {
            background:
                radial-gradient(circle at top right, rgba(8, 145, 178, 0.07), transparent 32rem),
                radial-gradient(circle at bottom left, rgba(124, 58, 237, 0.05), transparent 28rem),
                var(--bg);
        }

        html[data-theme="light"] .sidebar {
            background: rgba(255, 255, 255, 0.92);
        }

        html[data-theme="light"] .nav-section-label,
        html[data-theme="light"] .sidebar-footer,
        html[data-theme="light"] .activity-time,
        html[data-theme="light"] th {
            color: #64748b;
        }

        html[data-theme="light"] .kpi-card,
        html[data-theme="light"] .panel {
            background: linear-gradient(180deg, #ffffff, #fbfdff);
        }

        html[data-theme="light"] .activity-item,
        html[data-theme="light"] .progress-card,
        html[data-theme="light"] .action-link,
        html[data-theme="light"] .button,
        html[data-theme="light"] .notice {
            background: rgba(255, 255, 255, 0.82);
        }

        html[data-theme="light"] input,
        html[data-theme="light"] select {
            background: #ffffff;
            color: var(--text);
        }

        html[data-theme="light"] .kpi-meta {
            color: #64748b;
        }

        html[data-theme="light"] .button.danger {
            color: #be123c;
        }

        html[data-theme="light"] .button.success,
        html[data-theme="light"] .notice.success,
        html[data-theme="light"] .badge.active {
            color: #15803d;
        }

        html[data-theme="light"] .notice.error {
            color: #be123c;
        }

        .theme-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--panel-soft);
            color: var(--text);
            font: inherit;
            font-size: 0.76rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: var(--shadow);
        }

        .theme-toggle:hover {
            border-color: rgba(45, 212, 255, 0.38);
        }

        .theme-toggle__icon {
            font-size: 0.95rem;
            line-height: 1;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 760px) {
            .topbar-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    `;

    const style = document.createElement('style');
    style.setAttribute('data-facilities-theme', 'true');
    style.textContent = lightThemeCss;
    document.head.appendChild(style);

    function systemTheme() {
        return window.matchMedia('(prefers-color-scheme: light)').matches
            ? 'light'
            : 'dark';
    }

    function savedTheme() {
        const value = window.localStorage.getItem(storageKey);
        return value === 'light' || value === 'dark' ? value : null;
    }

    function applyTheme(theme) {
        root.dataset.theme = theme;
        root.style.colorScheme = theme;

        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            const nextTheme = theme === 'dark' ? 'light' : 'dark';
            const icon = theme === 'dark' ? '☀' : '☾';
            const label = nextTheme === 'light' ? 'Light mode' : 'Dark mode';

            button.setAttribute('aria-label', `Switch to ${label}`);
            button.setAttribute('title', `Switch to ${label}`);
            button.innerHTML = `<span class="theme-toggle__icon" aria-hidden="true">${icon}</span><span>${label}</span>`;
        });
    }

    function installToggle() {
        if (document.querySelector('[data-theme-toggle]')) {
            applyTheme(root.dataset.theme || savedTheme() || systemTheme());
            return;
        }

        const topbar = document.querySelector('.topbar');
        if (!topbar) {
            return;
        }

        let actions = topbar.querySelector('.topbar-actions');

        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'topbar-actions';

            const movable = Array.from(topbar.children).filter((element) => {
                return !element.matches(':first-child');
            });

            movable.forEach((element) => actions.appendChild(element));
            topbar.appendChild(actions);
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'theme-toggle';
        button.dataset.themeToggle = 'true';

        button.addEventListener('click', () => {
            const current = root.dataset.theme || systemTheme();
            const next = current === 'dark' ? 'light' : 'dark';
            window.localStorage.setItem(storageKey, next);
            applyTheme(next);
        });

        actions.prepend(button);
        applyTheme(root.dataset.theme || savedTheme() || systemTheme());
    }

    applyTheme(savedTheme() || systemTheme());

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', installToggle);
    } else {
        installToggle();
    }

    window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', () => {
        if (!savedTheme()) {
            applyTheme(systemTheme());
        }
    });
})();

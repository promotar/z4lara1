(() => {
    const workspace = document.querySelector('.ainpa-account-workspace');
    if (!workspace || !workspace.querySelector('#account-tab-learning, #account-tab-teaching')) {
        return;
    }

    const tabs = Array.from(workspace.querySelectorAll('.ainpa-account-tabs [role="tab"][aria-controls]'));
    const panels = Array.from(workspace.querySelectorAll('.ainpa-account-tab-panels [role="tabpanel"]'));
    if (!tabs.length || !panels.length) {
        return;
    }

    const activate = (tab, focus = false) => {
        const panelId = tab.getAttribute('aria-controls');

        tabs.forEach((item) => {
            const active = item === tab;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
            item.tabIndex = active ? 0 : -1;
        });

        panels.forEach((panel) => {
            const active = panel.id === panelId;
            panel.removeAttribute('x-cloak');
            panel.hidden = !active;
            panel.style.display = active ? '' : 'none';
        });

        if (focus) {
            tab.focus();
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }

            event.preventDefault();
            const direction = document.documentElement.dir === 'rtl' ? -1 : 1;
            let targetIndex = index;
            if (event.key === 'Home') targetIndex = 0;
            if (event.key === 'End') targetIndex = tabs.length - 1;
            if (event.key === 'ArrowRight') targetIndex = (index + direction + tabs.length) % tabs.length;
            if (event.key === 'ArrowLeft') targetIndex = (index - direction + tabs.length) % tabs.length;
            activate(tabs[targetIndex], true);
        });
    });

    const selected = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true' || tab.classList.contains('is-active'));
    activate(selected || tabs[0]);
})();

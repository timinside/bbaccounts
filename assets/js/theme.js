(function () {
    'use strict';

    var key = 'bb-theme';
    var locked = document.documentElement.getAttribute('data-theme-locked');
    var pref = locked === 'dark' ? 'dark' : 'auto';
    if (!locked) {
        try {
            var saved = localStorage.getItem(key);
            if (saved === 'light' || saved === 'dark' || saved === 'auto') {
                pref = saved;
            }
        } catch (error) { /* storage disabled */ }
    }

    var query = window.matchMedia('(prefers-color-scheme: dark)');

    function resolved() {
        if (locked === 'dark') { return 'dark'; }
        return pref === 'auto' ? (query.matches ? 'dark' : 'light') : pref;
    }

    function apply() {
        var theme = resolved();
        var root = document.documentElement;
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-theme-pref', locked === 'dark' ? 'dark' : pref);
        root.style.colorScheme = theme;
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', theme === 'dark' ? '#0a0c11' : '#f4f5f8');
        }
    }

    apply();

    query.addEventListener('change', function () {
        if (!locked && pref === 'auto') {
            apply();
        }
    });

    window.bbTheme = {
        get: function () { return locked === 'dark' ? 'dark' : pref; },
        resolved: resolved,
        locked: locked === 'dark',
        set: function (value) {
            if (locked === 'dark') { apply(); return; }
            pref = value === 'light' || value === 'dark' ? value : 'auto';
            try { localStorage.setItem(key, pref); } catch (error) { /* ignore */ }
            apply();
        },
        cycle: function () {
            if (locked === 'dark') { apply(); return 'dark'; }
            var order = ['auto', 'light', 'dark'];
            var next = order[(order.indexOf(pref) + 1) % order.length];
            window.bbTheme.set(next);
            return next;
        }
    };
})();

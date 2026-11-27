(function () {
    'use strict';

    function themeLabel(pref) {
        return pref === 'light' ? 'Theme: light' : pref === 'dark' ? 'Theme: dark' : 'Theme: system';
    }

    function syncThemeButtons() {
        var pref = window.bbTheme ? window.bbTheme.get() : 'auto';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.setAttribute('aria-label', themeLabel(pref) + ' — click to change');
            button.setAttribute('title', themeLabel(pref));
            button.setAttribute('data-theme-pref', pref);
        });
    }

    document.addEventListener('click', function (event) {
        var studioAnchor = event.target.closest('a[href^="#studio-"]');
        if (studioAnchor) {
            var section = document.getElementById(studioAnchor.getAttribute('href').slice(1));
            if (section && section.tagName === 'DETAILS' && !section.open) {
                section.open = true;
            }
        }

        var studioEdit = event.target.closest('[data-studio-edit]');
        if (studioEdit) {
            var target = document.getElementById(studioEdit.getAttribute('data-studio-edit'));
            if (target) {
                event.preventDefault();
                if (!target.open) {
                    target.open = true;
                }
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            return;
        }

        var toggle = event.target.closest('[data-theme-toggle]');
        if (toggle && window.bbTheme) {
            window.bbTheme.cycle();
            syncThemeButtons();
            return;
        }

        var closer = event.target.closest('[data-alert-close]');
        if (closer) {
            var alert = closer.closest('.alert');
            if (alert) {
                alert.remove();
            }
            return;
        }

        var sidebarToggle = event.target.closest('[data-sidebar-toggle]');
        if (sidebarToggle) {
            var sidebar = document.getElementById('sidebar');
            var scrim = document.querySelector('[data-sidebar-close]');
            if (sidebar) {
                sidebar.classList.toggle('is-open');
                if (scrim) {
                    scrim.hidden = !sidebar.classList.contains('is-open');
                }
            }
            return;
        }

        if (event.target.closest('[data-sidebar-close]')) {
            var openSidebar = document.getElementById('sidebar');
            if (openSidebar) {
                openSidebar.classList.remove('is-open');
            }
            event.target.closest('[data-sidebar-close]').hidden = true;
            return;
        }

        var passwordToggle = event.target.closest('[data-toggle-password]');
        if (passwordToggle) {
            var input = passwordToggle.parentElement.querySelector('input');
            if (!input) {
                return;
            }
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            passwordToggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            return;
        }

        var copy = event.target.closest('[data-copy]');
        if (copy) {
            var text = copy.getAttribute('data-copy-value') || '';
            if (!text) {
                var target = copy.getAttribute('data-copy-target');
                var source = target ? document.querySelector(target) : copy.previousElementSibling;
                text = source ? (source.value || source.textContent || '') : '';
            }
            if (text) {
                copyText(text.trim(), copy);
            }
            return;
        }

        var emoji = event.target.closest('[data-emoji]');
        if (emoji) {
            setEmoji(emoji.getAttribute('data-emoji') || '', true);
            var pickerDialog = document.querySelector('[data-emoji-dialog]');
            if (pickerDialog && pickerDialog.open) {
                pickerDialog.close();
            }
            return;
        }

        var emojiOpen = event.target.closest('[data-emoji-open]');
        if (emojiOpen) {
            var dialog = document.querySelector('[data-emoji-dialog]');
            if (dialog) {
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.hidden = false;
                }
            }
            return;
        }

        var emojiClose = event.target.closest('[data-emoji-close]');
        if (emojiClose) {
            var closeDialog = emojiClose.closest('[data-emoji-dialog]');
            if (closeDialog) {
                closeDialog.close();
            }
            return;
        }

        var emojiTab = event.target.closest('[data-emoji-tab]');
        if (emojiTab) {
            var group = emojiTab.getAttribute('data-emoji-tab');
            document.querySelectorAll('[data-emoji-tab]').forEach(function (tab) {
                var active = tab === emojiTab;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            document.querySelectorAll('[data-emoji-group]').forEach(function (section) {
                section.hidden = section.getAttribute('data-emoji-group') !== group;
            });
            var scroll = document.querySelector('[data-emoji-scroll]');
            if (scroll) {
                scroll.scrollTop = 0;
            }
            return;
        }

        var historyBack = event.target.closest('[data-history-back]');
        if (historyBack) {
            window.history.back();
            return;
        }

        var gateContinue = event.target.closest('[data-mobile-gate-continue]');
        if (gateContinue) {
            var gate = gateContinue.closest('[data-mobile-gate]');
            if (gate) {
                gate.hidden = true;
            }
            document.body.classList.remove('mobile-gate-open');
            try {
                window.sessionStorage.setItem('bb_mobile_gate', '1');
            } catch (error) { /* storage unavailable */ }
            return;
        }
    });

    function setEmoji(value, markChoice) {
        var input = document.querySelector('input[name="avatar_emoji"]');
        var radio = document.querySelector('input[name="avatar"][value="emoji"]');
        if (input) {
            input.value = value;
        }
        if (radio) {
            radio.checked = true;
        }
        document.querySelectorAll('[data-emoji-preview]').forEach(function (preview) {
            preview.textContent = value || '🙂';
        });
        document.querySelectorAll('.emoji-choice').forEach(function (button) {
            var active = markChoice && (button.getAttribute('data-emoji') || '') === value;
            button.classList.toggle('is-active', active);
        });
    }

    function syncStatusFields(select) {
        var restore = select.value === 'restore';
        var form = select.closest('form');
        if (!form) {
            return;
        }
        form.querySelectorAll('[data-status-field]').forEach(function (field) {
            field.hidden = restore;
        });
    }

    function initBanners() {
        document.querySelectorAll('[data-banner-carousel]').forEach(function (carousel) {
            var slides = Array.prototype.slice.call(carousel.querySelectorAll('[data-banner-slide]'));
            if (slides.length < 2) {
                return;
            }
            var index = 0;
            var timer = null;
            var interval = parseInt(carousel.getAttribute('data-banner-interval'), 10) || 10000;
            var progress = carousel.querySelector('[data-banner-progress]');
            var dots = Array.prototype.slice.call(carousel.querySelectorAll('[data-banner-dot]'));
            var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            function restart() {
                if (progress) {
                    progress.classList.remove('is-running');
                    progress.style.animationDuration = interval + 'ms';
                    void progress.offsetWidth;
                    progress.classList.add('is-running');
                }
                if (timer) {
                    window.clearTimeout(timer);
                }
                if (!reduced) {
                    timer = window.setTimeout(function () {
                        show(index + 1);
                    }, interval);
                }
            }

            function show(next) {
                index = (next + slides.length) % slides.length;
                slides.forEach(function (slide, i) {
                    slide.classList.toggle('is-active', i === index);
                });
                dots.forEach(function (dot, i) {
                    dot.classList.toggle('is-active', i === index);
                    dot.setAttribute('aria-selected', i === index ? 'true' : 'false');
                });
                restart();
            }

            carousel.querySelectorAll('[data-banner-prev]').forEach(function (button) {
                button.addEventListener('click', function () { show(index - 1); });
            });
            carousel.querySelectorAll('[data-banner-next]').forEach(function (button) {
                button.addEventListener('click', function () { show(index + 1); });
            });
            dots.forEach(function (dot) {
                dot.addEventListener('click', function () {
                    show(parseInt(dot.getAttribute('data-banner-dot') || '0', 10));
                });
            });

            restart();
        });
    }

    function copyText(text, button) {
        var done = function () {
            var previous = button.getAttribute('data-label') || button.textContent;
            button.setAttribute('data-label', previous);
            button.classList.add('is-copied');
            var label = button.querySelector('[data-copy-label]');
            if (label) {
                label.textContent = 'Copied';
            }
            window.setTimeout(function () {
                button.classList.remove('is-copied');
                if (label) {
                    label.textContent = button.getAttribute('data-copy-reset') || 'Copy';
                }
            }, 1800);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function () { fallback(text, done); });
        } else {
            fallback(text, done);
        }
    }

    function fallback(text, done) {
        var area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        try {
            document.execCommand('copy');
            done();
        } catch (error) { /* clipboard unavailable */ }
        document.body.removeChild(area);
    }

    document.addEventListener('input', function (event) {
        var emojiInput = event.target.closest('input[name="avatar_emoji"]');
        if (emojiInput) {
            setEmoji(emojiInput.value.trim(), false);
            return;
        }

        var bannerColor = event.target.closest('input[name="bg_color"]');
        if (bannerColor) {
            var defaultBg = document.querySelector('input[name="use_default_bg"]');
            if (defaultBg) {
                defaultBg.checked = false;
            }
        }

        var picker = event.target.closest('[data-avatar-color]');
        if (!picker) {
            return;
        }
        var preview = document.querySelector('[data-avatar-preview]');
        var defaultColor = document.querySelector('input[name="use_default_color"]');
        if (defaultColor) {
            defaultColor.checked = false;
        }
        if (preview) {
            preview.style.background = picker.value;
        }
        document.querySelectorAll('[data-avatar-color-target]').forEach(function (target) {
            target.style.background = picker.value;
        });
    });

    document.addEventListener('change', function (event) {
        var statusAction = event.target.closest('[data-status-action]');
        if (statusAction) {
            syncStatusFields(statusAction);
            return;
        }

        var checkbox = event.target.closest('input[name="use_default_color"]');
        if (!checkbox) {
            return;
        }
        var preview = document.querySelector('[data-avatar-preview]');
        var picker = document.querySelector('[data-avatar-color]');
        var color = checkbox.checked || !picker ? '' : picker.value;
        if (preview) {
            preview.style.background = color;
        }
        document.querySelectorAll('[data-avatar-color-target]').forEach(function (target) {
            target.style.background = color;
        });
    });

    var confirmAction = null;
    var confirmReturnFocus = null;

    function confirmOverlay() {
        var overlay = document.querySelector('[data-confirm-overlay]');
        if (overlay) {
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.className = 'confirm-overlay';
        overlay.setAttribute('data-confirm-overlay', '');
        overlay.hidden = true;
        overlay.innerHTML = '<div class="confirm-box" role="dialog" aria-modal="true" aria-labelledby="confirm-title">'
            + '<h2 id="confirm-title">Are you sure?</h2>'
            + '<p class="confirm-text"></p>'
            + '<div class="btn-row confirm-actions">'
            + '<button type="button" class="btn btn-secondary" data-confirm-cancel>Cancel</button>'
            + '<button type="button" class="btn btn-danger" data-confirm-ok>Confirm</button>'
            + '</div></div>';
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay || event.target.closest('[data-confirm-cancel]')) {
                closeConfirm();
            } else if (event.target.closest('[data-confirm-ok]')) {
                var action = confirmAction;
                closeConfirm();
                if (action) {
                    action();
                }
            }
        });

        return overlay;
    }

    function openConfirm(message, action) {
        var overlay = confirmOverlay();
        overlay.querySelector('.confirm-text').textContent = message;
        confirmAction = action;
        confirmReturnFocus = document.activeElement;
        overlay.hidden = false;
        document.body.classList.add('confirm-open');
        overlay.querySelector('[data-confirm-ok]').focus();
    }

    function closeConfirm() {
        var overlay = document.querySelector('[data-confirm-overlay]');
        if (overlay) {
            overlay.hidden = true;
        }
        document.body.classList.remove('confirm-open');
        confirmAction = null;
        if (confirmReturnFocus && confirmReturnFocus.focus) {
            confirmReturnFocus.focus();
        }
        confirmReturnFocus = null;
    }

    document.addEventListener('keydown', function (event) {
        var overlay = document.querySelector('[data-confirm-overlay]');
        if (event.key === 'Escape' && overlay && !overlay.hidden) {
            closeConfirm();
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        var submitter = event.submitter;
        var message = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
        if (!message) {
            return;
        }
        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }

        event.preventDefault();
        openConfirm(message, function () {
            form.dataset.confirmed = '1';
            if (submitter) {
                form.requestSubmit(submitter);
            } else {
                form.requestSubmit();
            }
        });
    });

    function initShowcaseOrdering() {
        document.querySelectorAll('[data-showcase-list]').forEach(function (list) {
            var dragged = null;
            var sync = function () {
                var rows = list.querySelectorAll('[data-showcase-sort]');
                var inputs = list.querySelectorAll('[data-showcase-order]');
                rows.forEach(function (row, index) {
                    if (inputs[index]) {
                        inputs[index].value = row.getAttribute('data-showcase-id');
                    }
                });
            };
            list.querySelectorAll('[data-showcase-sort]').forEach(function (row) {
                row.addEventListener('dragstart', function () {
                    dragged = row;
                    row.classList.add('is-dragging');
                });
                row.addEventListener('dragend', function () {
                    row.classList.remove('is-dragging');
                    list.querySelectorAll('.is-drop-target').forEach(function (el) { el.classList.remove('is-drop-target'); });
                    dragged = null;
                });
                row.addEventListener('dragover', function (event) {
                    if (!dragged || dragged === row) { return; }
                    event.preventDefault();
                    row.classList.add('is-drop-target');
                });
                row.addEventListener('dragleave', function () { row.classList.remove('is-drop-target'); });
                row.addEventListener('drop', function (event) {
                    event.preventDefault();
                    row.classList.remove('is-drop-target');
                    if (!dragged || dragged === row) { return; }
                    var rows = Array.prototype.slice.call(list.querySelectorAll('[data-showcase-sort]'));
                    if (rows.indexOf(dragged) < rows.indexOf(row)) {
                        row.parentNode.insertBefore(dragged, row.nextSibling);
                    } else {
                        row.parentNode.insertBefore(dragged, row);
                    }
                    sync();
                });
            });
        });
    }

    function initShowcaseResize() {
        var widths = ['mini', 'normal', 'wide'];
        document.querySelectorAll('[data-showcase-resize]').forEach(function (handle) {
            var control = handle.closest('[data-showcase-width-control]');
            if (!control) { return; }
            var select = control.querySelector('[data-showcase-width-select]');
            var label = control.querySelector('[data-showcase-width-label]');
            if (!select) { return; }
            var startX = 0;
            var startIndex = 1;
            handle.addEventListener('pointerdown', function (event) {
                startX = event.clientX;
                startIndex = Math.max(0, widths.indexOf(select.value));
                handle.setPointerCapture(event.pointerId);
                event.preventDefault();
            });
            handle.addEventListener('pointermove', function (event) {
                if (!handle.hasPointerCapture(event.pointerId)) { return; }
                var index = Math.max(0, Math.min(widths.length - 1, startIndex + Math.round((event.clientX - startX) / 80)));
                select.value = widths[index];
                if (label) { label.textContent = widths[index].charAt(0).toUpperCase() + widths[index].slice(1); }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncThemeButtons();
        initBanners();

        document.querySelectorAll('[data-status-action]').forEach(function (select) {
            syncStatusFields(select);
        });

        var showcaseConfig = {
            text: { title: true, body: true, titleRequired: true, label: 'Text', hint: 'Shown as a text card. @mentions become links.', placeholder: 'Games I play, tools I use, or a project note.' },
            projects: { title: false, body: false },
            badges: { title: false, body: false },
            media: { title: false, body: false, media: true },
            repos: { title: false, body: true, titleRequired: false, label: 'GitHub repositories', hint: 'One full GitHub repository link per line, up to 5.', placeholder: 'https://github.com/owner/repo' },
            links: { title: false, body: true, titleRequired: false, label: 'Links', hint: 'One link per line as URL | label, up to 10.', placeholder: 'https://example.com | My website' },
            reviews: { title: false, body: false, reviews: true },
            friends: { title: false, body: false },
            bestfriends: { title: false, body: false, friends: true }
        };
        document.querySelectorAll('[data-showcase-type]').forEach(function (showcaseType) {
            var form = showcaseType.closest('form');
            var find = function (selector) { return form ? form.querySelector(selector) : null; };
            var findAll = function (selector) { return form ? form.querySelectorAll(selector) : []; };
            var showcaseTitle = find('[data-showcase-title]');
            var showcaseBody = find('[data-showcase-body]');
            var titleInput = find('[data-showcase-title-input]');
            var titleHint = find('[data-showcase-title-hint]');
            var bodyInput = find('[data-showcase-body-input]');
            var bodyLabel = find('[data-showcase-body-label]');
            var bodyHint = find('[data-showcase-body-hint]');
            var showcaseFriends = find('[data-showcase-friends]');
            var showcaseReviews = find('[data-showcase-reviews]');
            var reviewInputs = findAll('[data-review-required]');
            var showcaseMedia = find('[data-showcase-media]');
            var mediaInput = find('[data-showcase-media-input]');
            var syncShowcase = function () {
                var config = showcaseConfig[showcaseType.value] || showcaseConfig.text;
                if (showcaseTitle) {
                    showcaseTitle.hidden = !config.title;
                }
                if (showcaseBody) {
                    showcaseBody.hidden = !config.body;
                }
                if (showcaseFriends) {
                    showcaseFriends.hidden = !config.friends;
                }
                if (showcaseMedia) {
                    showcaseMedia.hidden = !config.media;
                }
                if (showcaseReviews) {
                    showcaseReviews.hidden = !config.reviews;
                }
                if (mediaInput) {
                    mediaInput.required = !!config.media;
                }
                reviewInputs.forEach(function (input) {
                    input.required = !!config.reviews;
                });
                if (titleInput) {
                    titleInput.required = !!config.titleRequired;
                }
                if (titleHint) {
                    titleHint.textContent = config.titleRequired ? '' : 'optional';
                }
                if (bodyInput) {
                    bodyInput.required = !!config.body;
                    bodyInput.placeholder = config.placeholder || '';
                }
                if (bodyLabel) {
                    bodyLabel.textContent = config.label || 'Text';
                }
                if (bodyHint) {
                    bodyHint.textContent = config.hint || '';
                }
            };
            showcaseType.addEventListener('change', syncShowcase);
            syncShowcase();
        });

        initShowcaseOrdering();
        initShowcaseResize();

        document.querySelectorAll('[data-handle-input]').forEach(function (input) {
            var status = input.closest('.field') ? input.closest('.field').querySelector('[data-handle-status]') : null;
            var timer = null;
            var check = function () {
                var value = input.value.trim();
                if (!status) {
                    return;
                }
                if (value.length < 3) {
                    status.textContent = '';
                    status.className = '';
                    return;
                }
                var endpoint = input.getAttribute('data-handle-check') || '/account/handle-check';
                fetch(endpoint + '?username=' + encodeURIComponent(value), { credentials: 'same-origin' })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (data) {
                        if (!data || input.value.trim() !== value) {
                            return;
                        }
                        status.textContent = data.available ? 'Available' : 'Already taken or invalid';
                        status.className = data.available ? 'is-ok' : 'is-taken';
                    })
                    .catch(function () {});
            };
            input.addEventListener('input', function () {
                if (timer) { clearTimeout(timer); }
                timer = setTimeout(check, 300);
            });
        });

        document.querySelectorAll('[data-blur-range]').forEach(function (range) {
            var output = range.parentElement.querySelector('[data-blur-output]');
            var syncBlur = function () {
                if (output) {
                    output.textContent = range.value + 'px';
                }
            };
            range.addEventListener('input', syncBlur);
            syncBlur();
        });

        document.querySelectorAll('[data-opacity-range]').forEach(function (range) {
            var output = range.parentElement.querySelector('[data-opacity-output]');
            var syncOpacity = function () {
                if (output) {
                    output.textContent = range.value + '%';
                }
            };
            range.addEventListener('input', syncOpacity);
            syncOpacity();
        });

        var mobileGate = document.querySelector('[data-mobile-gate]');
        if (mobileGate && window.matchMedia('(max-width: 640px)').matches) {
            var seen = '';
            try {
                seen = window.sessionStorage.getItem('bb_mobile_gate') || '';
            } catch (error) { /* storage unavailable */ }
            if (seen !== '1') {
                mobileGate.hidden = false;
                document.body.classList.add('mobile-gate-open');
            }
        }

        document.querySelectorAll('[data-autohide]').forEach(function (alert) {
            window.setTimeout(function () {
                if (alert.isConnected) {
                    alert.remove();
                }
            }, 9000);
        });
    });
})();

/* Suppress CSS tooltips during drag so they don't stick. */
document.addEventListener('pointerdown', function (event) {
    var tip = event.target.closest('.has-tip');
    if (tip) tip.classList.add('is-tip-suppressed');
});
['pointerup', 'pointercancel', 'pointerleave', 'dragend'].forEach(function (name) {
    document.addEventListener(name, function (event) {
        var tip = event.target.closest('.has-tip');
        if (tip) tip.classList.remove('is-tip-suppressed');
        document.querySelectorAll('.has-tip.is-tip-suppressed').forEach(function (el) {
            if (!el.matches(':hover')) el.classList.remove('is-tip-suppressed');
        });
    }, true);
});

/* Generic modal dialogs (social login, auth app). */
function showLikersPage(dialog, page) {
    var list = dialog.querySelector('[data-likers-list]');
    var pager = dialog.querySelector('[data-likers-pager]');
    if (!list) return;
    var pages = pager ? Math.max(1, parseInt(pager.getAttribute('data-pages') || '1', 10)) : 1;
    page = Math.max(1, Math.min(page, pages));
    list.querySelectorAll('[data-likers-item]').forEach(function (item) {
        item.hidden = parseInt(item.getAttribute('data-page') || '1', 10) !== page;
    });
    if (pager) {
        pager.setAttribute('data-current', String(page));
        var status = pager.querySelector('[data-likers-status]');
        if (status) status.textContent = page + ' / ' + pages;
        var prev = pager.querySelector('[data-likers-prev]');
        var next = pager.querySelector('[data-likers-next]');
        if (prev) prev.disabled = page <= 1;
        if (next) next.disabled = page >= pages;
    }
}
document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-dialog-open]');
    if (opener) {
        var target = document.getElementById(opener.getAttribute('data-dialog-open'));
        if (target && typeof target.showModal === 'function') {
            showLikersPage(target, 1);
            target.showModal();
        }
        return;
    }
    var closer = event.target.closest('[data-dialog-close]');
    if (closer) {
        var dialog = closer.closest('dialog');
        if (dialog) {
            dialog.close();
        }
        return;
    }
    var prev = event.target.closest('[data-likers-prev]');
    if (prev) {
        var pager = prev.closest('[data-likers-pager]');
        var dialog = prev.closest('dialog');
        if (pager && dialog) showLikersPage(dialog, parseInt(pager.getAttribute('data-current') || '1', 10) - 1);
        return;
    }
    var next = event.target.closest('[data-likers-next]');
    if (next) {
        var pagerN = next.closest('[data-likers-pager]');
        var dialogN = next.closest('dialog');
        if (pagerN && dialogN) showLikersPage(dialogN, parseInt(pagerN.getAttribute('data-current') || '1', 10) + 1);
        return;
    }
    if (event.target.tagName === 'DIALOG') {
        event.target.close();
    }
});

/* Warn before leaving bbaccounts for another site. */
document.addEventListener('click', function (event) {
    var leaveUrl = document.body.getAttribute('data-leave-warning');
    if (!leaveUrl) return;
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    var link = event.target.closest('a[href]');
    if (!link || link.hasAttribute('data-direct') || link.hasAttribute('download')) return;
    var href = link.getAttribute('href');
    if (!/^https?:/i.test(href)) return;
    var url = new URL(href, location.href);
    if (url.host === location.host) return;
    var trusted = [];
    try { trusted = JSON.parse(document.body.getAttribute('data-trusted-sites') || '[]'); } catch (e) { trusted = []; }
    for (var i = 0; i < trusted.length; i++) {
        var site = trusted[i].toLowerCase();
        if (url.hostname === site || url.hostname.endsWith('.' + site)) return;
    }
    event.preventDefault();
    location.href = leaveUrl + '?url=' + encodeURIComponent(url.href);
});


/* Account tutorial. */
(function () {
    var dialog = document.getElementById('account-tutorial');
    if (!dialog) return;
    var steps = Array.isArray(window.BBA_TUTORIAL_STEPS) ? window.BBA_TUTORIAL_STEPS : [];
    var title = document.getElementById('tutorial-title');
    var sub = document.getElementById('tutorial-sub');
    var body = document.getElementById('tutorial-body');
    var prev = dialog.querySelector('[data-tutorial-prev]');
    var next = dialog.querySelector('[data-tutorial-next]');
    var skip = dialog.querySelector('[data-tutorial-skip]');
    var closeBtn = dialog.querySelector('[data-tutorial-close]');
    var i = 0;
    var seenKey = 'bb-tutorial-seen';

    function render() {
        var s = steps[i] || {};
        if (title) title.textContent = s.title || 'Tutorial';
        if (sub) sub.textContent = s.sub || '';
        if (body) {
            body.innerHTML = '';
            if (s.img) {
                var img = document.createElement('img');
                img.src = s.img;
                img.alt = s.title || '';
                img.loading = 'lazy';
                img.style.cssText = 'display:block;width:100%;max-width:520px;border-radius:12px;margin:0 0 12px';
                img.onerror = function () { this.remove(); };
                body.appendChild(img);
            }
            var p = document.createElement('p');
            p.style.margin = '0';
            p.textContent = s.body || '';
            body.appendChild(p);
        }
        if (prev) prev.disabled = i <= 0;
        if (next) next.textContent = i >= steps.length - 1 ? 'Done' : 'Next';
    }
    function markSeen() { try { localStorage.setItem(seenKey, '1'); } catch (e) {} }
    function open(start) {
        if (!steps.length) return;
        i = Math.max(0, Math.min(start || 0, steps.length - 1));
        render();
        if (typeof dialog.showModal === 'function') dialog.showModal();
    }
    function close(seen) {
        if (seen) markSeen();
        if (dialog.open) dialog.close();
    }
    if (next) next.addEventListener('click', function () {
        if (i >= steps.length - 1) { close(true); return; }
        i++; render();
    });
    if (prev) prev.addEventListener('click', function () { if (i > 0) { i--; render(); } });
    if (skip) skip.addEventListener('click', function () { close(true); });
    if (closeBtn) closeBtn.addEventListener('click', function () { close(true); });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) close(true); });

    window.bbTutorialOpen = function () { open(0); };
    window.bbTutorialReset = function () { try { localStorage.removeItem(seenKey); } catch (e) {} open(0); };
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-tutorial-replay]')) { e.preventDefault(); open(0); }
    });

    var enabled = document.body && document.body.getAttribute('data-tutorial') === '1';
    var seen = false;
    try { seen = localStorage.getItem(seenKey) === '1'; } catch (e) {}
    if (enabled && !seen) { open(0); }
})();


/* Version update prompt. */
(function () {
    var key = 'bb-app-version';
    function promptUpdate(current, force) {
        if (!current) return;
        if (window.confirm('A new version of bbaccounts is available. Refresh now to update? This will reload the page (Ctrl+F5).')) {
            try { localStorage.setItem(key, current); } catch (e) {}
            var reload = function () { window.location.reload(); };
            if (window.caches && caches.keys) {
                caches.keys().then(function (names) { return Promise.all(names.map(function (n) { return caches.delete(n); })); }).then(reload, reload);
            } else {
                reload();
            }
        } else if (!force) {
            try { localStorage.setItem(key, current); } catch (e) {}
        }
    }
    window.bbVersionPrompt = function (force) { promptUpdate(document.body && document.body.getAttribute('data-app-version'), !!force); };

    var current = document.body && document.body.getAttribute('data-app-version');
    if (!current) return;
    var seen;
    try { seen = localStorage.getItem(key); } catch (e) { return; }
    if (!seen) { try { localStorage.setItem(key, current); } catch (e) {} return; }
    if (seen === current) return;
    promptUpdate(current);
})();

document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-simulate]');
    if (!btn) return;
    var action = btn.getAttribute('data-simulate');
    if (action === 'version') {
        if (window.bbVersionPrompt) window.bbVersionPrompt(true);
        return;
    }
    if (action === 'tutorial') {
        if (window.bbTutorialOpen) window.bbTutorialOpen();
        return;
    }
    if (action === 'tutorial-reset') {
        try { localStorage.removeItem('bb-tutorial-seen'); } catch (err) {}
        btn.textContent = 'Tutorial reset';
        setTimeout(function () { btn.innerHTML = '<span>Reset tutorial seen</span>'; }, 1200);
    }
});

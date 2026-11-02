/*
 * Wendfold - RC attendance interactions
 * - Add/remove/check-in actions without full page refresh
 * - Status updates without scroll reset
 * - Add walk-in panel interactions
 */

(function () {
    'use strict';

    function getPageRoot() {
        return document.querySelector('.hc-rc-page');
    }

    function getDock() {
        return document.querySelector('.hc-rc-dock');
    }

    function getPanel() {
        return document.getElementById('addPanel');
    }

    function getDialog() {
        return document.getElementById('hcRcDialog');
    }

    function getDialogStrings() {
        var root = getPageRoot();
        var data = root ? root.dataset : {};

        return {
            confirmTitle: data.dialogConfirmTitle || 'Please confirm',
            alertTitle: data.dialogAlertTitle || 'Notice',
            confirmLabel: data.dialogConfirmLabel || 'OK',
            cancelLabel: data.dialogCancelLabel || 'Cancel',
            closeLabel: data.dialogCloseLabel || 'Close'
        };
    }

    function ensureDialog() {
        var dialog = getDialog();

        if (dialog) {
            return dialog;
        }

        dialog = document.createElement('div');
        dialog.id = 'hcRcDialog';
        dialog.className = 'hc-rc-dialog-backdrop';
        dialog.hidden = true;
        dialog.innerHTML = [
            '<div class="hc-rc-dialog" role="dialog" aria-modal="true" aria-labelledby="hcRcDialogTitle" aria-describedby="hcRcDialogMessage">',
            '  <div class="hc-rc-dialog__header">',
            '    <h2 class="hc-rc-dialog__title" id="hcRcDialogTitle"></h2>',
            '    <button type="button" class="hc-rc-dialog__close" data-hc-dialog-close aria-label="Close">&#x2715;</button>',
            '  </div>',
            '  <div class="hc-rc-dialog__body">',
            '    <p class="hc-rc-dialog__message" id="hcRcDialogMessage"></p>',
            '  </div>',
            '  <div class="hc-rc-dialog__footer">',
            '    <button type="button" class="hc-dialog__btn hc-dialog__btn--neutral" data-hc-dialog-cancel></button>',
            '    <button type="button" class="hc-dialog__btn hc-dialog__btn--confirm" data-hc-dialog-confirm></button>',
            '  </div>',
            '</div>'
        ].join('');
        document.body.appendChild(dialog);

        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                closeDialog(false);
                return;
            }

            if (event.target.closest('[data-hc-dialog-close], [data-hc-dialog-cancel]')) {
                closeDialog(false);
                return;
            }

            if (event.target.closest('[data-hc-dialog-confirm]')) {
                closeDialog(true);
            }
        });

        return dialog;
    }

    function closeDialog(result) {
        var dialog = getDialog();
        var resolver;

        if (!dialog) {
            return;
        }

        resolver = dialog._resolver;
        dialog._resolver = null;
        dialog.classList.remove('is-open');
        document.body.classList.remove('hc-rc-dialog-open');

        window.setTimeout(function () {
            dialog.hidden = true;
            if (dialog._lastActive && typeof dialog._lastActive.focus === 'function') {
                dialog._lastActive.focus();
            }
            dialog._lastActive = null;
        }, 180);

        if (typeof resolver === 'function') {
            resolver(result === true);
        }
    }

    function openDialog(options) {
        var dialog = ensureDialog();
        var strings = getDialogStrings();
        var closeButton = dialog.querySelector('[data-hc-dialog-close]');
        var cancelButton = dialog.querySelector('[data-hc-dialog-cancel]');
        var confirmButton = dialog.querySelector('[data-hc-dialog-confirm]');
        var title = dialog.querySelector('#hcRcDialogTitle');
        var message = dialog.querySelector('#hcRcDialogMessage');
        var isAlert = options && options.mode === 'alert';

        title.textContent = (options && options.title) || (isAlert ? strings.alertTitle : strings.confirmTitle);
        message.textContent = (options && options.message) || '';
        closeButton.setAttribute('aria-label', strings.closeLabel);
        cancelButton.textContent = (options && options.cancelLabel) || strings.cancelLabel;
        confirmButton.textContent = (options && options.confirmLabel) || strings.confirmLabel;
        cancelButton.hidden = isAlert;
        closeButton.hidden = false;
        confirmButton.classList.toggle('hc-dialog__btn--confirm', !isAlert);
        confirmButton.classList.toggle('hc-dialog__btn--neutral', isAlert);

        dialog._lastActive = document.activeElement;
        dialog.hidden = false;
        document.body.classList.add('hc-rc-dialog-open');

        return new Promise(function (resolve) {
            dialog._resolver = resolve;
            window.requestAnimationFrame(function () {
                dialog.classList.add('is-open');
                confirmButton.focus();
            });
        });
    }

    function showConfirm(message, title) {
        return openDialog({
            mode: 'confirm',
            title: title,
            message: message
        });
    }

    function showAlert(message, title) {
        return openDialog({
            mode: 'alert',
            title: title,
            message: message
        });
    }

    function setBusyState(element, busy) {
        if (!element) {
            return;
        }

        if (busy) {
            element.classList.add('is-busy');
            if ('disabled' in element) {
                element.disabled = true;
            }
        } else {
            element.classList.remove('is-busy');
            if ('disabled' in element) {
                element.disabled = false;
            }
        }
    }

    function requestJson(url, options) {
        var fetchOptions = Object.assign({
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Wendfold-Ajax': '1',
                'Accept': 'application/json'
            }
        }, options || {});

        fetchOptions.headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest',
            'X-Wendfold-Ajax': '1',
            'Accept': 'application/json'
        }, (options && options.headers) || {});

        return fetch(url, fetchOptions).then(function (response) {
            return response.text().then(function (text) {
                var data = null;

                try {
                    data = JSON.parse(text);
                } catch (error) {
                    throw new Error('Unexpected response');
                }

                if (!response.ok) {
                    throw new Error(data.message || 'Request failed');
                }

                return data;
            });
        });
    }

    function refreshRcView(scrollY) {
        return fetch(window.location.href, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Wendfold-Ajax': '1'
            }
        }).then(function (response) {
            return response.text();
        }).then(function (html) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var nextPage = doc.querySelector('.hc-rc-page');
            var nextDock = doc.querySelector('.hc-rc-dock');
            var nextPanel = doc.getElementById('addPanel');
            var currentPage = getPageRoot();
            var currentDock = getDock();
            var currentPanel = getPanel();

            if (!nextPage || !currentPage) {
                window.location.reload();
                return;
            }

            currentPage.replaceWith(nextPage);

            if (currentDock && nextDock) {
                currentDock.replaceWith(nextDock);
            } else if (currentDock && !nextDock) {
                currentDock.remove();
            } else if (!currentDock && nextDock) {
                document.body.appendChild(nextDock);
            }

            if (currentPanel && nextPanel) {
                currentPanel.replaceWith(nextPanel);
            } else if (currentPanel && !nextPanel) {
                currentPanel.remove();
            } else if (!currentPanel && nextPanel) {
                document.body.appendChild(nextPanel);
            }

            if (doc.title) {
                document.title = doc.title;
            }

            document.body.classList.remove('hc-rc-panel-open');

            window.requestAnimationFrame(function () {
                window.scrollTo(0, scrollY || 0);
            });
        });
    }

    function handleAjaxSuccess(data, scrollY) {
        if (data.redirect) {
            window.location.href = data.redirect;
            return;
        }

        refreshRcView(scrollY).catch(function () {
            window.location.reload();
        });
    }

    function handleAjaxError(error) {
        showAlert(error && error.message ? error.message : 'Something went wrong.');
    }

    function submitFormAjax(form, trigger) {
        var confirmText = trigger && trigger.getAttribute ? trigger.getAttribute('data-confirm') : '';

        if (!confirmText && form && form.getAttribute) {
            confirmText = form.getAttribute('data-confirm') || '';
        }
        var runRequest = function () {
            var formData = new FormData(form);
            var scrollY = window.scrollY;

            formData.append('ajax', '1');
            setBusyState(trigger, true);

            requestJson(form.action, {
                method: (form.getAttribute('method') || 'POST').toUpperCase(),
                body: formData
            }).then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'Request failed');
                }

                handleAjaxSuccess(data, scrollY);
            }).catch(function (error) {
                handleAjaxError(error);
            }).finally(function () {
                setBusyState(trigger, false);
            });
        };

        if (confirmText) {
            showConfirm(confirmText).then(function (confirmed) {
                if (confirmed) {
                    runRequest();
                }
            });
            return;
        }

        runRequest();
    }

    function openAddPanel() {
        var panel = getPanel();
        var searchInput;

        if (!panel) {
            return;
        }

        panel.hidden = false;
        document.body.classList.add('hc-rc-panel-open');

        window.requestAnimationFrame(function () {
            panel.classList.add('is-open');
        });

        searchInput = panel.querySelector('[data-hc-search]');

        if (searchInput) {
            window.setTimeout(function () {
                searchInput.focus();
            }, 120);
        }
    }

    function closeAddPanel() {
        var panel = getPanel();

        if (!panel) {
            return;
        }

        panel.classList.remove('is-open');
        document.body.classList.remove('hc-rc-panel-open');

        window.setTimeout(function () {
            if (panel) {
                panel.hidden = true;
            }
        }, 180);
    }

    function filterMembers(term) {
        var panel = getPanel();
        var query = (term || '').toLowerCase();
        var rows;
        var visible = 0;
        var empty;

        if (!panel) {
            return;
        }

        rows = panel.querySelectorAll('[data-member-row]');
        rows.forEach(function (row) {
            var name = row.getAttribute('data-name') || '';
            var match = query === '' || name.indexOf(query) !== -1;
            row.hidden = !match;
            if (match) {
                visible += 1;
            }
        });

        empty = panel.querySelector('#searchNoResults');
        if (empty) {
            empty.hidden = visible !== 0;
        }
    }

    window.openAddPanel = openAddPanel;
    window.closeAddPanel = closeAddPanel;
    window.filterMembers = filterMembers;

    document.addEventListener('click', function (event) {
        var target = event.target;
        var openButton = target.closest('[data-hc-open-panel]');
        var closeButton = target.closest('[data-hc-close-panel]');
        var backdrop = target.closest('[data-hc-panel-backdrop]');
        if (openButton) {
            event.preventDefault();
            openAddPanel();
            return;
        }

        if (closeButton) {
            event.preventDefault();
            closeAddPanel();
            return;
        }

        if (backdrop && target === backdrop) {
            event.preventDefault();
            closeAddPanel();
            return;
        }

    });

    document.addEventListener('input', function (event) {
        var searchInput = event.target.closest('[data-hc-search]');

        if (searchInput) {
            filterMembers(searchInput.value);
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        var action = form.getAttribute('action') || '';
        var trigger;

        if (action.indexOf('task=rcattendance.') === -1) {
            return;
        }

        trigger = document.activeElement && form.contains(document.activeElement) ? document.activeElement : null;

        if (!trigger || trigger.tagName !== 'BUTTON') {
            trigger = form.querySelector('button[type="submit"]');
        }

        event.preventDefault();
        submitFormAjax(form, trigger);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && getDialog() && !getDialog().hidden) {
            event.preventDefault();
            closeDialog(false);
        }
    });
})();

/*
 * Wendfold - Event detail interactions
 * - Description expand/collapse
 */

(function () {
    'use strict';

    function initDescriptionToggle() {
        var desc = document.querySelector('[data-hc-desc]');
        var btn = document.querySelector('[data-hc-desc-toggle]');

        if (!desc || !btn) {
            return;
        }

        desc.classList.add('hc-desc-collapsed');

        window.requestAnimationFrame(function () {
            var needsToggle = desc.scrollHeight > (desc.clientHeight + 2);

            if (!needsToggle) {
                desc.classList.remove('hc-desc-collapsed');
                btn.hidden = true;
                return;
            }

            btn.addEventListener('click', function () {
                var expanded = desc.classList.contains('hc-desc-expanded');
                var moreText = btn.getAttribute('data-more') || 'More';
                var lessText = btn.getAttribute('data-less') || 'Less';

                if (expanded) {
                    desc.classList.remove('hc-desc-expanded');
                    desc.classList.add('hc-desc-collapsed');
                    btn.textContent = moreText;
                    btn.setAttribute('aria-expanded', 'false');
                } else {
                    desc.classList.remove('hc-desc-collapsed');
                    desc.classList.add('hc-desc-expanded');
                    btn.textContent = lessText;
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initDescriptionToggle();
    });
})();

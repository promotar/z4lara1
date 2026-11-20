/*!
 * Art INPA hero slider.
 * Behaviour only: slides, arrows, dots, autoplay, swipe, keyboard, a11y.
 * Slide content lives in the Page Builder page, so it stays editable.
 */
(function () {
    'use strict';

    var INTERVAL = 7000;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function direction() {
        return (document.documentElement.getAttribute('dir') || 'ltr').toLowerCase();
    }

    function init(root) {
        if (!root || root.dataset.artSliderReady === '1') {
            return;
        }

        var slides = Array.prototype.slice.call(root.querySelectorAll('[data-art-slide]'));

        if (slides.length === 0) {
            return;
        }

        root.dataset.artSliderReady = '1';
        root.classList.add('is-ready');

        var dots = Array.prototype.slice.call(root.querySelectorAll('[data-art-dot]'));
        var prev = root.querySelector('[data-art-prev]');
        var next = root.querySelector('[data-art-next]');
        var index = 0;
        var timer = null;
        var paused = false;

        function render() {
            slides.forEach(function (slide, i) {
                var on = i === index;
                slide.classList.toggle('is-active', on);
                slide.setAttribute('aria-hidden', on ? 'false' : 'true');
            });

            dots.forEach(function (dot, i) {
                var on = i === index;
                dot.classList.toggle('is-active', on);
                dot.setAttribute('aria-current', on ? 'true' : 'false');
                dot.setAttribute('tabindex', on ? '0' : '-1');
            });
        }

        function show(target) {
            index = (target + slides.length) % slides.length;
            render();
        }

        function stop() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        function play() {
            if (reduceMotion) {
                return;
            }

            stop();
            timer = window.setInterval(function () {
                if (!paused && !document.hidden) {
                    show(index + 1);
                }
            }, INTERVAL);
        }

        function restart() {
            stop();
            play();
        }

        if (prev) {
            prev.addEventListener('click', function () { show(index - 1); restart(); });
        }

        if (next) {
            next.addEventListener('click', function () { show(index + 1); restart(); });
        }

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                show(parseInt(dot.dataset.artDot || '0', 10));
                restart();
            });
        });

        root.addEventListener('mouseenter', function () { paused = true; });
        root.addEventListener('mouseleave', function () { paused = false; });
        root.addEventListener('focusin', function () { paused = true; });
        root.addEventListener('focusout', function (event) {
            if (!root.contains(event.relatedTarget)) {
                paused = false;
            }
        });

        document.addEventListener('visibilitychange', function () {
            paused = document.hidden;
        });

        root.addEventListener('keydown', function (event) {
            var rtl = direction() === 'rtl';

            if (event.key === 'ArrowLeft') {
                show(index + (rtl ? 1 : -1));
                restart();
            } else if (event.key === 'ArrowRight') {
                show(index + (rtl ? -1 : 1));
                restart();
            }
        });

        var touchX = 0;
        var touchY = 0;

        root.addEventListener('touchstart', function (event) {
            var touch = event.changedTouches[0];
            touchX = touch.clientX;
            touchY = touch.clientY;
        }, { passive: true });

        root.addEventListener('touchend', function (event) {
            var touch = event.changedTouches[0];
            var dx = touch.clientX - touchX;
            var dy = touch.clientY - touchY;

            if (Math.abs(dx) < 45 || Math.abs(dx) < Math.abs(dy)) {
                return;
            }

            var forward = direction() === 'rtl' ? dx > 0 : dx < 0;
            show(index + (forward ? 1 : -1));
            restart();
        }, { passive: true });

        // Reset so the entrance animation runs on first load.
        slides.forEach(function (slide) { slide.classList.remove('is-active'); });
        void root.offsetWidth;

        render();
        play();
    }

    function boot() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-art-slider]'), init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

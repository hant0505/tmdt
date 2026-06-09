define([], function () {
    'use strict';

    return function (config, element) {
        var header = element;
        var allProductsToggle = header.querySelector('.ttk-all-products-btn');
        var categoryLayer = header.querySelector('.ttk-icon-layer');
        var closeLayerTimer = null;

        if (!allProductsToggle || !categoryLayer || header.dataset.ttkHeaderMenuReady === '1') {
            return;
        }

        header.dataset.ttkHeaderMenuReady = '1';

        function openLayer() {
            if (closeLayerTimer) {
                window.clearTimeout(closeLayerTimer);
                closeLayerTimer = null;
            }

            categoryLayer.hidden = false;
            window.requestAnimationFrame(function () {
                categoryLayer.classList.add('is-open');
                allProductsToggle.setAttribute('aria-expanded', 'true');
            });
        }

        function closeLayer() {
            categoryLayer.classList.remove('is-open');
            allProductsToggle.setAttribute('aria-expanded', 'false');

            if (closeLayerTimer) {
                window.clearTimeout(closeLayerTimer);
            }

            closeLayerTimer = window.setTimeout(function () {
                if (!categoryLayer.classList.contains('is-open')) {
                    categoryLayer.hidden = true;
                }
            }, 240);
        }

        allProductsToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            if (categoryLayer.classList.contains('is-open')) {
                closeLayer();
            } else {
                openLayer();
            }
        });

        categoryLayer.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        document.addEventListener('click', function (event) {
            if (!header.contains(event.target) && categoryLayer.classList.contains('is-open')) {
                closeLayer();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && categoryLayer.classList.contains('is-open')) {
                closeLayer();
                allProductsToggle.focus();
            }
        });
    };
});

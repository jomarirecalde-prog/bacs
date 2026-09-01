/**
 * Mobile enhancements: card-style data tables and sidebar helpers.
 * Labels table cells from thead text so mobile card rows show field names.
 */

function closeMobileSidebar() {
    const body = document.body;
    const stack = body._x_dataStack;
    if (stack?.[0] && typeof stack[0].sidebar === 'boolean') {
        stack[0].sidebar = false;
    }
}

export function enhanceResponsiveTables(root = document) {
    root.querySelectorAll('.table-wrap:not(.table-scroll-mobile)').forEach((wrap) => {
        const table = wrap.querySelector('.data-table');
        if (!table) {
            return;
        }

        const headers = [...table.querySelectorAll('thead th')]
            .filter((th) => !th.classList.contains('hidden') && getComputedStyle(th).display !== 'none')
            .map((th) => th.textContent.trim());

        if (!headers.length) {
            return;
        }

        table.querySelectorAll('tbody tr').forEach((row) => {
            const cells = [...row.querySelectorAll('td')].filter(
                (cell) => !cell.classList.contains('hidden') && getComputedStyle(cell).display !== 'none',
            );

            cells.forEach((cell, index) => {
                if (cell.hasAttribute('colspan')) {
                    cell.dataset.label = '';
                    return;
                }
                if (headers[index]) {
                    cell.dataset.label = headers[index];
                }
            });
        });
    });
}

export function bootResponsive() {
    const run = () => enhanceResponsiveTables(document.getElementById('app-main') ?? document);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }

    window.addEventListener('bacs:pageshow', run);

    document.addEventListener('click', (event) => {
        const link = event.target.closest('aside a.nav-link[href], aside button.nav-link');
        if (link) {
            closeMobileSidebar();
        }
    });
}

export { closeMobileSidebar };

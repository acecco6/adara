let openControl = null;
const enhancedControls = new WeakMap();

function enhanceSelect(root) {
    const select = root.querySelector('[data-adara-select-native]');
    const label = root.querySelector('label');
    const custom = root.querySelector('[data-adara-select-ui]');
    const trigger = root.querySelector('[data-adara-select-trigger]');
    const value = root.querySelector('[data-adara-select-value]');
    const listbox = root.querySelector('[data-adara-select-listbox]');
    const dropdown = root.querySelector('[data-adara-select-dropdown]') || listbox;
    const searchInput = root.querySelector('[data-adara-select-search]');
    const emptyMessage = root.querySelector('[data-adara-select-empty]');
    const validation = root.querySelector('[data-adara-select-validation]');
    let typed = '';
    let typedAt = 0;

    function positionDropdown() {
        if (root.hasAttribute('data-adara-select-inline')) {
            dropdown.style.position = 'static';
            dropdown.style.left = '';
            dropdown.style.width = '';
            dropdown.style.top = '';
            dropdown.style.bottom = '';
            dropdown.style.maxHeight = '';
            dropdown.style.marginTop = '';
            listbox.style.maxHeight = '';

            return;
        }

        const triggerBounds = trigger.getBoundingClientRect();
        const dialog = root.closest('dialog[open]');
        const dialogBounds = dialog?.getBoundingClientRect();
        const topLimit = Math.max(8, dialogBounds?.top ?? 8) + 8;
        const bottomLimit = Math.min(window.innerHeight - 8, dialogBounds?.bottom ?? window.innerHeight - 8) - 8;

        if (triggerBounds.bottom < topLimit || triggerBounds.top > bottomLimit) {
            close();
            return;
        }

        const spaceBelow = bottomLimit - triggerBounds.bottom - 8;
        const spaceAbove = triggerBounds.top - topLimit - 8;
        const desiredHeight = Math.min(dropdown.scrollHeight, 256);
        const placeAbove = spaceBelow < desiredHeight && spaceAbove > spaceBelow;
        const availableHeight = Math.max(48, placeAbove ? spaceAbove : spaceBelow);

        dropdown.style.position = 'fixed';
        dropdown.style.left = `${triggerBounds.left}px`;
        dropdown.style.width = `${triggerBounds.width}px`;
        dropdown.style.top = placeAbove ? 'auto' : `${triggerBounds.bottom + 8}px`;
        dropdown.style.bottom = placeAbove ? `${window.innerHeight - triggerBounds.top + 8}px` : 'auto';
        dropdown.style.maxHeight = `${availableHeight}px`;
        dropdown.style.marginTop = '0';
        listbox.style.maxHeight = `${Math.max(40, availableHeight - (searchInput ? searchInput.parentElement.offsetHeight : 0) - 2)}px`;
    }

    if (!select || select.multiple || !label || !custom || !trigger || !value || !listbox) {
        return;
    }

    const previous = enhancedControls.get(root);

    if (previous?.select === select && previous.trigger === trigger && previous.listbox === listbox && previous.label === label && previous.searchInput === searchInput) {
        select.dataset.adaraEnhanced = 'true';
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        custom.classList.remove('hidden');
        previous.sync();
        return;
    }

    if (openControl?.root === root) {
        openControl.close();
    }

    previous?.listeners.abort();
    const listeners = new AbortController();
    const listenerOptions = { signal: listeners.signal };

    root.dataset.adaraSelectReady = 'true';
    select.dataset.adaraEnhanced = 'true';
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    custom.classList.remove('hidden');

    function sync() {
        value.textContent = select.selectedOptions[0]?.textContent.trim() || 'Elegir opción';
        trigger.disabled = select.disabled;

        for (const option of listbox.querySelectorAll('[role="option"]')) {
            option.setAttribute('aria-selected', String(Number(option.dataset.index) === select.selectedIndex));
        }

        if (select.value !== '' && validation) {
            validation.classList.add('hidden');
            trigger.setAttribute('aria-invalid', select.getAttribute('aria-invalid') || 'false');
            trigger.setAttribute('aria-describedby', select.getAttribute('aria-describedby') || '');
        }
    }

    function normalizeSearchText(text) {
        return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es-AR');
    }

    function filterOptions() {
        const query = normalizeSearchText(searchInput?.value.trim() || '');
        let visibleOptions = 0;

        for (const option of listbox.querySelectorAll('[role="option"]')) {
            const matches = query === '' || normalizeSearchText(option.textContent).includes(query);
            option.hidden = !matches;

            if (matches) {
                visibleOptions++;
            }
        }

        if (emptyMessage) {
            emptyMessage.textContent = query === '' ? 'No hay opciones disponibles.' : 'No encontramos coincidencias. Probá con otro texto.';
            emptyMessage.classList.toggle('hidden', visibleOptions > 0);
        }
    }

    function close(restoreFocus = false) {
        dropdown.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
        delete root.dataset.adaraOpen;

        if (searchInput) {
            searchInput.value = '';
        }

        if (openControl?.root === root) {
            openControl = null;
        }

        window.removeEventListener('resize', positionDropdown);
        document.removeEventListener('scroll', positionDropdown, true);

        if (restoreFocus) {
            trigger.focus();
        }
    }

    function enabledOptions() {
        return [...listbox.querySelectorAll('[role="option"]:not([aria-disabled="true"])')].filter((option) => !option.hidden);
    }

    function focusOption(position) {
        const options = enabledOptions();

        if (options.length > 0) {
            options[(position + options.length) % options.length].focus();
        }
    }

    function buildOptions() {
        const items = [...select.options].map((option, index) => {
            const item = document.createElement('div');
            item.id = `${select.id}-option-${index}`;
            item.className = 'adara-select-option';
            item.role = 'option';
            item.tabIndex = -1;
            item.dataset.index = String(index);
            item.textContent = option.textContent.trim();
            item.setAttribute('aria-selected', String(index === select.selectedIndex));
            item.setAttribute('aria-disabled', String(option.disabled || option.parentElement?.disabled || false));

            return item;
        });

        listbox.replaceChildren(...items);
    }

    function open() {
        if (select.disabled) {
            return;
        }

        openControl?.close();
        if (searchInput) {
            searchInput.value = '';
        }
        buildOptions();
        filterOptions();
        dropdown.classList.remove('hidden');
        positionDropdown();
        if (!root.hasAttribute('data-adara-select-inline')) {
            window.addEventListener('resize', positionDropdown);
            document.addEventListener('scroll', positionDropdown, true);
        }
        trigger.setAttribute('aria-expanded', 'true');
        root.dataset.adaraOpen = 'true';
        openControl = { root, close };

        if (searchInput) {
            searchInput.focus();
        } else {
            const options = enabledOptions();
            const selectedPosition = options.findIndex((option) => Number(option.dataset.index) === select.selectedIndex);
            focusOption(selectedPosition >= 0 ? selectedPosition : 0);
        }
    }

    function choose(index) {
        const option = select.options[index];

        if (!option || option.disabled || option.parentElement?.disabled) {
            return;
        }

        const previousValue = select.value;
        select.selectedIndex = index;
        sync();
        close(true);

        if (select.value !== previousValue) {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    trigger.addEventListener('click', () => {
        if (trigger.getAttribute('aria-expanded') === 'true') {
            close(true);
        } else {
            open();
        }
    }, listenerOptions);

    trigger.addEventListener('keydown', (event) => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            open();
        } else if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
            event.preventDefault();
            close(true);
        }
    }, listenerOptions);

    listbox.addEventListener('click', (event) => {
        const option = event.target instanceof Element ? event.target.closest('[role="option"]') : null;

        if (option) {
            choose(Number(option.dataset.index));
        }
    }, listenerOptions);

    listbox.addEventListener('keydown', (event) => {
        const options = enabledOptions();
        const current = options.indexOf(document.activeElement);

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            focusOption(current + (event.key === 'ArrowDown' ? 1 : -1));
        } else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            focusOption(event.key === 'Home' ? 0 : options.length - 1);
        } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const option = document.activeElement;
            choose(Number(option.dataset.index));
        } else if (event.key === 'Escape') {
            event.preventDefault();
            close(true);
        } else if (event.key === 'Tab') {
            close();
        } else if (event.key.length === 1 && event.key !== ' ') {
            typed = Date.now() - typedAt > 700 ? event.key : typed + event.key;
            typedAt = Date.now();
            const match = options.find((option) => option.textContent.toLocaleLowerCase('es-AR').startsWith(typed.toLocaleLowerCase('es-AR')));

            if (match) {
                match.focus();
            }
        }
    }, listenerOptions);

    searchInput?.addEventListener('input', filterOptions, listenerOptions);
    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' && enabledOptions().length > 0) {
            event.preventDefault();
            focusOption(0);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            close(true);
        } else if (event.key === 'Tab') {
            close();
        }
    }, listenerOptions);

    label.addEventListener('click', (event) => {
        event.preventDefault();
        open();
    }, listenerOptions);

    select.addEventListener('change', sync, listenerOptions);
    select.addEventListener('invalid', (event) => {
        event.preventDefault();
        trigger.setAttribute('aria-invalid', 'true');

        if (validation) {
            validation.classList.remove('hidden');
            trigger.setAttribute('aria-describedby', `${select.getAttribute('aria-describedby') || ''} ${validation.id}`.trim());
        }

        open();
    }, listenerOptions);

    select.form?.addEventListener('reset', () => queueMicrotask(sync), listenerOptions);
    enhancedControls.set(root, { select, trigger, listbox, label, searchInput, listeners, sync });
    sync();
}

document.addEventListener('pointerdown', (event) => {
    if (openControl && !openControl.root.contains(event.target)) {
        openControl.close();
    }
});

document.addEventListener('livewire:navigating', () => openControl?.close());

export function initializeAdaraSelects() {
    document.querySelectorAll('[data-adara-select-root]').forEach(enhanceSelect);
}

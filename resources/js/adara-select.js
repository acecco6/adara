let openControl = null;

function enhanceSelect(root) {
    if (root.dataset.adaraSelectReady === 'true') {
        return;
    }

    const select = root.querySelector('[data-adara-select-native]');
    const label = root.querySelector('label');
    const custom = root.querySelector('[data-adara-select-ui]');
    const trigger = root.querySelector('[data-adara-select-trigger]');
    const value = root.querySelector('[data-adara-select-value]');
    const listbox = root.querySelector('[data-adara-select-listbox]');
    const validation = root.querySelector('[data-adara-select-validation]');
    let typed = '';
    let typedAt = 0;

    if (!select || select.multiple || !label || !custom || !trigger || !value || !listbox) {
        return;
    }

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

    function close(restoreFocus = false) {
        listbox.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
        delete root.dataset.adaraOpen;

        if (openControl?.root === root) {
            openControl = null;
        }

        if (restoreFocus) {
            trigger.focus();
        }
    }

    function enabledOptions() {
        return [...listbox.querySelectorAll('[role="option"]:not([aria-disabled="true"])')];
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

        if (items.length === 0) {
            const message = document.createElement('p');
            message.className = 'px-3 py-2 text-sm text-adara-readable-muted';
            message.textContent = 'No hay opciones disponibles.';
            items.push(message);
        }

        listbox.replaceChildren(...items);
    }

    function open() {
        if (select.disabled) {
            return;
        }

        openControl?.close();
        buildOptions();
        listbox.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        root.dataset.adaraOpen = 'true';
        openControl = { root, close };

        const options = enabledOptions();
        const selectedPosition = options.findIndex((option) => Number(option.dataset.index) === select.selectedIndex);
        focusOption(selectedPosition >= 0 ? selectedPosition : 0);
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
    });

    trigger.addEventListener('keydown', (event) => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            open();
        } else if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
            event.preventDefault();
            close(true);
        }
    });

    listbox.addEventListener('click', (event) => {
        const option = event.target instanceof Element ? event.target.closest('[role="option"]') : null;

        if (option) {
            choose(Number(option.dataset.index));
        }
    });

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
    });

    label.addEventListener('click', (event) => {
        event.preventDefault();
        open();
    });

    select.addEventListener('change', sync);
    select.addEventListener('invalid', (event) => {
        event.preventDefault();
        trigger.setAttribute('aria-invalid', 'true');

        if (validation) {
            validation.classList.remove('hidden');
            trigger.setAttribute('aria-describedby', `${select.getAttribute('aria-describedby') || ''} ${validation.id}`.trim());
        }

        open();
    });

    select.form?.addEventListener('reset', () => queueMicrotask(sync));
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

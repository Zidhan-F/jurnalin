export function initChoiceDialog() {
    const dialog = document.getElementById('choice-dialog');
    if (typeof dialog?.showModal !== 'function') return;

    const search = dialog.querySelector('input');
    const list = dialog.querySelector('[role=listbox]');
    const title = dialog.querySelector('h2');
    const status = dialog.querySelector('[role=status]');
    const clear = dialog.querySelector('[data-clear-choice]');
    let field;
    let active = -1;
    let matches = [];

    function highlight(index, scroll = true) {
        list.children[active]?.classList.remove('is-active');
        active = index;
        const item = list.children[index];
        if (item) {
            item.classList.add('is-active');
            search.setAttribute('aria-activedescendant', item.id);
            if (scroll) item.scrollIntoView({ block: 'nearest' });
        } else {
            search.removeAttribute('aria-activedescendant');
        }
    }

    function filter() {
        const words = search.value.trim().toLocaleLowerCase('id').split(/\s+/);
        matches = [...field.options].filter((option) => !option.hidden && !option.matches(':disabled')
            && words.every((word) => option.label.toLocaleLowerCase('id').includes(word)));
        const fragment = document.createDocumentFragment();
        for (const option of matches) {
            const item = document.createElement('li');
            item.id = 'choice-option-' + option.index;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(option.selected));
            item.textContent = option.label;
            fragment.append(item);
        }
        list.replaceChildren(fragment);
        const selected = matches.findIndex((option) => option.selected);
        highlight(matches.length ? Math.max(0, selected) : -1);
        clear.hidden = !search.value;
        status.textContent = matches.length ? (search.value ? matches.length + ' hasil pencarian' : matches.length + ' pilihan')
            : search.value ? 'Tidak ada hasil. Coba kata lain.' : 'Belum ada pilihan yang tersedia.';
    }

    function choose(index) {
        const option = matches[index];
        if (!option || field.matches(':disabled')) return;
        field.selectedIndex = option.index;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        dialog.close();
    }

    dialog.querySelector('[data-close-choice]').addEventListener('click', () => dialog.close());
    clear.addEventListener('click', () => {
        search.value = '';
        search.focus();
        filter();
    });
    list.addEventListener('click', (event) => {
        const item = event.target.closest('[role=option]');
        if (item) choose([...list.children].indexOf(item));
    });
    search.addEventListener('input', filter);
    search.addEventListener('keydown', (event) => {
        if (event.isComposing) return;
        if (event.key === 'Enter') {
            event.preventDefault();
            choose(active);
        } else if (matches.length && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            highlight(Math.max(0, Math.min(matches.length - 1, active + (event.key === 'ArrowDown' ? 1 : -1))));
        }
    });

    document.querySelectorAll('select:not([multiple])').forEach((select, index) => {
        if (select.size > 1) return;
        const label = select.labels[0];
        const labelCopy = label?.cloneNode(true);
        labelCopy?.querySelectorAll('select').forEach((element) => element.remove());
        const name = select.getAttribute('aria-label') || labelCopy?.textContent.trim() || 'Pilihan';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = select.id || 'choice-field-' + index;
        select.id = trigger.id + '-native';
        trigger.className = 'choice-trigger';
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-controls', dialog.id);
        if (select.hasAttribute('aria-describedby')) trigger.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
        if (label) label.htmlFor = trigger.id;
        select.before(trigger);
        select.hidden = true;

        function sync() {
            const value = select.selectedOptions[0]?.label || 'Pilih opsi';
            trigger.textContent = value;
            trigger.disabled = select.disabled;
            trigger.setAttribute('aria-label', name + ', ' + value);
        }
        function open(query = '') {
            field = select;
            title.textContent = name;
            search.placeholder = select.dataset.searchHint || 'Ketik untuk mencari';
            search.value = query;
            dialog.showModal();
            search.focus();
            filter();
        }
        trigger.addEventListener('click', () => open());
        trigger.addEventListener('keydown', (event) => {
            if (event.ctrlKey || event.metaKey || event.altKey || event.isComposing) return;
            if (event.key === 'ArrowDown' || (event.key.length === 1 && event.key !== ' ')) {
                event.preventDefault();
                open(event.key === 'ArrowDown' ? '' : event.key);
            }
        });
        select.addEventListener('change', () => {
            sync();
            trigger.removeAttribute('aria-invalid');
        });
        select.addEventListener('invalid', (event) => {
            event.preventDefault();
            trigger.setAttribute('aria-invalid', 'true');
            if (select.form?.querySelector(':invalid') === select && !dialog.open) {
                trigger.focus();
                open();
            }
        });
        new MutationObserver(sync).observe(select, { attributes: true, childList: true, subtree: true,
            attributeFilter: ['data-selected', 'disabled', 'selected', 'label'] });
        select.form?.addEventListener('reset', () => requestAnimationFrame(sync));
        window.addEventListener('pageshow', sync);
        sync();
    });
}

(() => {
    const reviewRows = (form) => {
        const rows = [];
        form.querySelectorAll('[data-review-label]').forEach((field) => {
            if (field.disabled || ((field.type === 'checkbox' || field.type === 'radio') && !field.checked)) return;
            const value = field.dataset.reviewValue || (field.tagName === 'SELECT' ? field.selectedOptions[0]?.textContent.trim() : field.value.trim());
            if (value) rows.push([field.dataset.reviewLabel, value]);
        });
        return rows;
    };
    const renderRows = (target, rows) => {
        target.replaceChildren();
        rows.forEach(([label, value]) => {
            const row = document.createElement('div');
            const term = document.createElement('dt');
            const detail = document.createElement('dd');
            term.textContent = label;
            detail.textContent = value;
            row.append(term, detail);
            target.append(row);
        });
    };
    document.querySelectorAll('[data-guided-form]').forEach((form) => {
        const steps = [...form.querySelectorAll('[data-step]')];
        const controls = form.querySelector('[data-workflow-controls]');
        const next = controls.querySelector('[data-step-next]');
        const back = controls.querySelector('[data-step-back]');
        const progress = form.querySelector('[data-workflow-progress]');
        const indicators = [...progress.querySelectorAll('[data-step-indicator]')];
        let current = 0;
        const populateReview = () => {
            form.querySelectorAll('[data-workflow-review]').forEach((target) => renderRows(target, reviewRows(form)));
            form.querySelectorAll('[data-selected-parcel-review]').forEach((target) => {
                target.replaceChildren();
                form.querySelectorAll('[name="shipments[]"]:checked').forEach((field) => {
                    const preview = field.closest('[data-parcel-option]')?.querySelector('[data-parcel-preview]');
                    if (preview) {
                        const copy = preview.cloneNode(true);
                        copy.querySelectorAll('[data-parcel-extra]').forEach((details) => { details.open = true; });
                        target.append(copy);
                    }
                });
            });
        };
        const show = (index, focus = true) => {
            current = Math.max(0, Math.min(index, steps.length - 1));
            steps.forEach((step, i) => { step.hidden = i !== current; });
            indicators.forEach((item, i) => {
                if (i === current) item.setAttribute('aria-current', 'step');
                else item.removeAttribute('aria-current');
            });
            back.hidden = current === 0;
            next.hidden = current === steps.length - 1;
            progress.querySelector('[data-step-announcement]').textContent = form.dataset.stepLabel.replace(':current', String(current + 1)).replace(':total', String(steps.length)) + ' · ' + steps[current].dataset.stepTitle;
            if (current === steps.length - 1) populateReview();
            if (focus) steps[current].querySelector('h2')?.focus();
        };
        const invalidField = (scope) => [...scope.querySelectorAll('input,select,textarea')].find((field) => !field.disabled && !field.validity.valid);
        const validate = (scope) => {
            if (form.dataset.selectionRequired) {
                const checks = [...form.querySelectorAll('[name="shipments[]"]')];
                if (checks.length) checks[0].setCustomValidity(checks.some((field) => field.checked) ? '' : form.dataset.selectionRequired);
            }
            const field = invalidField(scope);
            if (!field) return true;
            const index = steps.indexOf(field.closest('[data-step]'));
            if (index !== -1) show(index, false);
            field.reportValidity();
            field.focus();
            return false;
        };
        next.addEventListener('click', () => { if (validate(steps[current])) show(current + 1); });
        back.addEventListener('click', () => show(current - 1));
        form.addEventListener('submit', (event) => {
            if (event.submitter?.formNoValidate) return;
            if (current !== steps.length - 1) {
                event.preventDefault();
                if (validate(steps[current])) show(current + 1);
                return;
            }
            if (!validate(form)) event.preventDefault();
        });
        const updateSelectionCount = () => {
            const count = form.querySelectorAll('[name="shipments[]"]:checked').length;
            form.querySelectorAll('[data-selection-count]').forEach((output) => {
                output.textContent = output.dataset.countLabel.replace(':count', String(count));
            });
        };
        updateSelectionCount();
        form.addEventListener('change', () => {
            updateSelectionCount();
            const first = form.querySelector('[name="shipments[]"]');
            if (first) first.setCustomValidity('');
        });
        form.noValidate = true;
        controls.hidden = false;
        progress.hidden = false;
        const serverFields = JSON.parse(form.dataset.serverErrors || '[]').map((name) => {
            const base = name.split('.')[0];
            return [...form.elements].find((field) => field.name === name || field.name === base + '[]');
        }).filter(Boolean);
        serverFields.forEach((field) => {
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', 'form-errors');
            field.addEventListener('input', () => field.removeAttribute('aria-invalid'));
        });
        const initial = serverFields[0] || invalidField(form);
        const hasErrors = document.querySelector('.notice.error[role="alert"]');
        show(hasErrors && initial ? steps.indexOf(initial.closest('[data-step]')) : 0, false);
        if (hasErrors) {
            const error = document.querySelector('.notice.error[role="alert"]');
            error.tabIndex = -1;
            error.focus();
        }
    });

    document.querySelectorAll('[data-customer-picker]').forEach((picker) => {
        const form = picker.closest('form');
        const search = picker.querySelector('[data-customer-search]');
        const button = picker.querySelector('[data-customer-find]');
        const results = picker.querySelector('[data-customer-matches]');
        const status = picker.querySelector('[data-customer-status]');
        const names = ['customer_name', 'customer_phone', 'customer_address'];
        let controller;
        const find = async () => {
            const query = search.value.trim();
            if (!query) { search.focus(); return; }
            controller?.abort();
            controller = new AbortController();
            const signal = controller.signal;
            results.replaceChildren();
            status.textContent = '';
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            try {
                const url = new URL(picker.dataset.url, location.href);
                url.searchParams.set('q', query);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal });
                if (!response.ok) throw new Error('search');
                const data = await response.json();
                if (signal.aborted) return;
                status.textContent = data.customers.length ? '' : picker.dataset.empty;
                data.customers.forEach((customer) => {
                    const select = document.createElement('button');
                    select.type = 'button';
                    select.className = 'button secondary';
                    select.textContent = [customer.name, customer.phone, customer.address].filter(Boolean).join(' · ');
                    select.addEventListener('click', () => {
                        form.elements.namedItem('customer_id').value = customer.id;
                        names.forEach((name) => {
                            const input = form.elements.namedItem(name);
                            input.value = customer[name.replace('customer_', '')] ?? '';
                            input.readOnly = true;
                        });
                        picker.querySelector('[data-customer-clear]').hidden = false;
                        status.textContent = picker.dataset.selected;
                        results.replaceChildren();
                    });
                    results.append(select);
                });
            } catch (error) {
                if (error.name !== 'AbortError') status.textContent = picker.dataset.error;
            } finally {
                if (!signal.aborted) { button.disabled = false; button.removeAttribute('aria-busy'); }
            }
        };
        button.addEventListener('click', find);
        search.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); find(); } });
        const clear = picker.querySelector('[data-customer-clear]');
        clear.addEventListener('click', () => {
            form.elements.namedItem('customer_id').value = '';
            names.forEach((name) => { form.elements.namedItem(name).readOnly = false; });
            clear.hidden = true;
            status.textContent = '';
            form.elements.namedItem('customer_name').focus();
        });
    });

    // The existing app.js confirmation is retained as the no-dialog fallback.
    // Replace it with a review only after the dialog has been initialized.
    document.querySelectorAll('form[data-review-confirm]').forEach((form) => {
        if (!window.HTMLDialogElement?.prototype.showModal) return;
        const message = form.dataset.confirm;
        form.removeAttribute('data-confirm');
        const dialog = document.createElement('dialog');
        dialog.className = 'review-dialog';
        const title = document.createElement('h2');
        const description = document.createElement('p');
        const summary = document.createElement('dl');
        const actions = document.createElement('div');
        const cancel = document.createElement('button');
        const confirm = document.createElement('button');
        title.textContent = form.dataset.reviewTitle || message;
        title.id = 'review-title-' + [...document.querySelectorAll('.review-dialog')].length;
        dialog.setAttribute('aria-labelledby', title.id);
        description.textContent = form.dataset.reviewContext || '';
        summary.className = 'summary-list';
        actions.className = 'actions';
        cancel.type = confirm.type = 'button';
        cancel.className = 'button secondary';
        confirm.className = 'button';
        cancel.textContent = document.documentElement.dataset.cancelLabel;
        confirm.textContent = document.documentElement.dataset.confirmLabel;
        actions.append(cancel, confirm);
        dialog.append(title, description, summary, actions);
        document.body.append(dialog);
        let confirmed = false;
        let submitter;
        cancel.addEventListener('click', () => dialog.close());
        confirm.addEventListener('click', () => {
            confirmed = true;
            dialog.close();
            if (submitter) form.requestSubmit(submitter);
            else form.requestSubmit();
            confirmed = false;
        });
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented || event.submitter?.formNoValidate || confirmed) return;
            // Saving a future flight plan is distinct from physically sending it.
            if (event.submitter?.name === 'quick_action' && event.submitter.value === 'draft') return;
            event.preventDefault();
            submitter = event.submitter;
            const rows = reviewRows(form);
            if (!rows.length) {
                form.querySelectorAll('label').forEach((label) => {
                    const field = label.querySelector('input:not([type="hidden"]), select, textarea');
                    if (!field || field.disabled || ((field.type === 'checkbox' || field.type === 'radio') && !field.checked)) return;
                    const labelText = [...label.childNodes].filter((node) => node.nodeType === Node.TEXT_NODE).map((node) => node.textContent.trim()).join(' ').trim();
                    const value = field.tagName === 'SELECT' ? field.selectedOptions[0]?.textContent.trim() : field.value;
                    if (labelText && value) rows.push([labelText, field.type === 'checkbox' ? '✓' : value]);
                });
            }
            renderRows(summary, rows);
            dialog.showModal();
            cancel.focus();
        });
    });
})();

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

const quoteForm = document.querySelector('[data-quote-form]');

if (quoteForm) {
    const cents = (value) => {
        const [whole, fraction = ''] = String(value).split('.');
        return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    };

    const update = () => {
        const option = quoteForm.querySelector('[name=destination_id]').selectedOptions[0];
        const weightInput = quoteForm.querySelector('[name=actual_weight]').value;
        const output = document.querySelector('#estimated-price');

        if (!option?.dataset.rate || !/^\d{1,5}(\.\d{1,3})?$/.test(weightInput)) {
            output.textContent = '—';
            return;
        }

        const rate = JSON.parse(option.dataset.rate);
        const [wholeWeight, weightFraction = ''] = weightInput.split('.');
        const weight = BigInt(wholeWeight) * 1000n + BigInt(weightFraction.padEnd(3, '0'));
        const weightedCharge = (weight * cents(rate.per_weight) + 999n) / 1000n;
        const base = cents(rate.base_charge) + weightedCharge;
        const minimum = cents(rate.minimum_charge);
        const total = (base > minimum ? base : minimum) + cents(rate.additional_charge);

        output.textContent = (total / 100n).toLocaleString() + '.' + String(total % 100n).padStart(2, '0');
    };

    quoteForm.addEventListener('input', update);
    quoteForm.addEventListener('change', update);
    update();
}

document.querySelectorAll('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});

const originBranch = document.querySelector('[data-origin-branch]');
const destinationBranch = document.querySelector('[data-destination-branch]');
if (originBranch && destinationBranch) {
    const alignBranches = () => {
        Array.from(destinationBranch.options).forEach((option) => { option.disabled = option.value === originBranch.value; });
        if (destinationBranch.value === originBranch.value) destinationBranch.value = Array.from(destinationBranch.options).find((option) => !option.disabled)?.value ?? '';
    };
    originBranch.addEventListener('change', alignBranches);
    alignBranches();
}


document.querySelectorAll('[data-parcel-photos]').forEach((input) => {
    const form = input.closest('form');
    const preview = form.querySelector('[data-photo-preview]');
    const feedback = form.querySelector('[data-photo-validation]');
    const clearWrap = form.querySelector('[data-photo-clear-wrap]');
    let selectedFiles = Array.from(input.files);
    let urls = [];
    const update = () => {
        const transfer = new DataTransfer();
        selectedFiles.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        urls.forEach((url) => URL.revokeObjectURL(url));
        urls = [];
        preview.replaceChildren();
        const files = selectedFiles;
        let message = '';
        if (files.length > Number(input.dataset.maxPhotos)) {
            message = input.dataset.photoCountError;
        } else if (files.some((file) => file.size > 1024 * 1024)) {
            message = input.dataset.photoSizeError;
        } else if (files.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) {
            message = input.dataset.photoTypeError;
        }
        input.setCustomValidity(message);
        feedback.textContent = message;
        feedback.classList.toggle('hide', !message);
        clearWrap.classList.toggle('hide', files.length === 0);
        form.querySelector('[data-photo-selected-count]').textContent = `${files.length} / ${input.dataset.maxPhotos}`;
        files.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'photo-preview-item';
            if (file.type.startsWith('image/')) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'photo-preview-open';
                button.dataset.photoOpen = '';
                const img = document.createElement('img');
                const url = URL.createObjectURL(file);
                urls.push(url);
                img.src = url;
                img.alt = file.name;
                button.append(img);
                item.append(button);
            }
            const name = document.createElement('span');
            name.className = 'photo-preview-name';
            name.textContent = file.name;
            name.title = file.name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'photo-preview-remove';
            remove.textContent = '×';
            remove.setAttribute('aria-label', `${input.dataset.photoRemoveLabel}: ${file.name}`);
            remove.addEventListener('click', () => {
                selectedFiles.splice(index, 1);
                update();
            });
            item.append(name, remove);
            preview.append(item);
        });
    };
    input.addEventListener('change', () => {
        const fileKey = (file) => `${file.name}:${file.size}:${file.lastModified}:${file.type}`;
        const existing = new Set(selectedFiles.map(fileKey));
        Array.from(input.files).forEach((file) => {
            const key = fileKey(file);
            if (!existing.has(key)) {
                selectedFiles.push(file);
                existing.add(key);
            }
        });
        update();
    });
    form.querySelector('[data-photo-clear]').addEventListener('click', () => {
        selectedFiles = [];
        update();
    });
    form.addEventListener('reset', () => {
        selectedFiles = [];
        update();
    });
});

document.querySelectorAll('[data-payment-details]').forEach((section) => {
    const method = section.querySelector('[data-payment-method]');
    const remark = section.querySelector('[data-method-remark]');
    const cash = section.querySelector('[data-cash-received]');
    const amount = section.closest('form').querySelector('[name="amount"]');
    const cents = (value) => {
        if (!/^\d+(\.\d{1,2})?$/.test(value)) return null;
        const [whole, fraction = ''] = value.split('.');
        return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    };
    const update = () => {
        const name = method.selectedOptions[0]?.dataset.method;
        const needsRemark = ['Bank transfer', 'Pay'].includes(name);
        const providerLabel = remark.querySelector('[data-provider-label]');
        providerLabel.textContent = name === 'Pay' ? providerLabel.dataset.payLabel : providerLabel.dataset.bankLabel;
        remark.hidden = !needsRemark;
        remark.querySelector('input').disabled = !needsRemark;
        remark.querySelector('input').required = needsRemark;
        cash.hidden = name !== 'Cash';
        cash.querySelector('input').disabled = name !== 'Cash';
        const applied = cents(amount ? amount.value : section.dataset.paymentAmount);
        const tendered = cents(cash.querySelector('input').value) ?? applied;
        const change = applied !== null && tendered !== null && tendered >= applied ? tendered - applied : 0n;
        cash.querySelector('output').textContent = `${change / 100n}.${String(change % 100n).padStart(2, '0')}`;
    };
    method.addEventListener('change', update);
    cash.addEventListener('input', update);
    amount?.addEventListener('input', update);
    update();
});

const photoViewer = document.querySelector('[data-photo-viewer]');
if (photoViewer) {
    let gallery = [];
    let current = 0;
    const showPhoto = () => {
        const photo = gallery[current];
        photoViewer.querySelector('img').src = photo.src;
        photoViewer.querySelector('img').alt = photo.alt;
        photoViewer.querySelector('[data-photo-position]').textContent = `${current + 1} / ${gallery.length} · ${photo.alt}`;
    };
    const stepPhoto = (direction) => {
        current = (current + direction + gallery.length) % gallery.length;
        showPhoto();
    };
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-photo-open]');
        if (!trigger) return;
        const container = trigger.closest('[data-photo-preview], [data-saved-gallery]');
        if (!container) return;
        event.preventDefault();
        gallery = Array.from(container.querySelectorAll('img'));
        current = gallery.indexOf(trigger.querySelector('img'));
        showPhoto();
        photoViewer.showModal();
    });
    photoViewer.querySelector('[data-photo-previous]').addEventListener('click', () => stepPhoto(-1));
    photoViewer.querySelector('[data-photo-next]').addEventListener('click', () => stepPhoto(1));
    photoViewer.querySelector('[data-photo-close]').addEventListener('click', () => photoViewer.close());
    photoViewer.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') { event.preventDefault(); stepPhoto(1); }
        if (event.key === 'ArrowLeft') { event.preventDefault(); stepPhoto(-1); }
    });
}

// Filter the displayed rows without changing report totals or form submissions.
document.querySelectorAll('[data-table-search] .table-wrap').forEach((wrapper, index) => {
    const rows = Array.from(wrapper.querySelectorAll('tbody > tr')).filter((row) => !row.querySelector('.empty'));
    if (rows.length === 0) return;
    const context = wrapper.closest('[data-table-search]');
    const heading = wrapper.closest('.card')?.querySelector('h2')?.textContent.trim();
    const search = document.createElement('form');
    search.className = 'record-search';
    search.setAttribute('role', 'search');
    const label = document.createElement('label');
    label.textContent = heading ? `${context.dataset.searchLabel} · ${heading}` : context.dataset.searchLabel;
    const input = document.createElement('input');
    input.type = 'search';
    input.maxLength = 150;
    input.id = `record-search-${index}`;
    label.htmlFor = input.id;
    label.append(input);
    const clear = document.createElement('button');
    clear.type = 'reset';
    clear.className = 'button secondary small';
    clear.textContent = context.dataset.searchClear;
    const status = document.createElement('output');
    status.setAttribute('aria-live', 'polite');
    const update = () => {
        const query = input.value.trim().normalize('NFKC').toLocaleLowerCase();
        let count = 0;
        rows.forEach((row) => {
            const match = row.textContent.normalize('NFKC').toLocaleLowerCase().includes(query);
            row.hidden = !match;
            if (match) count++;
        });
        status.textContent = query ? (count ? `${count} / ${rows.length}` : context.dataset.searchEmpty) : '';
    };
    input.addEventListener('input', update);
    search.addEventListener('submit', (event) => { event.preventDefault(); update(); });
    search.addEventListener('reset', () => { input.value = ''; update(); });
    search.append(label, clear, status);
    wrapper.before(search);
});

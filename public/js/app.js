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
    const preview = input.closest('form').querySelector('[data-photo-preview]');
    let urls = [];
    input.addEventListener('change', () => {
        urls.forEach((url) => URL.revokeObjectURL(url));
        urls = [];
        preview.replaceChildren();
        const files = Array.from(input.files);
        const invalid = files.length > Number(input.dataset.maxPhotos) || files.some((file) => file.size > 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
        input.setCustomValidity(invalid ? input.dataset.photoError : '');
        if (invalid) {
            input.reportValidity();
            return;
        }
        files.forEach((file) => {
            const img = document.createElement('img');
            const url = URL.createObjectURL(file);
            urls.push(url);
            img.src = url;
            img.alt = file.name;
            preview.append(img);
        });
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

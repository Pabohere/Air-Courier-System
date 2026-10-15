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

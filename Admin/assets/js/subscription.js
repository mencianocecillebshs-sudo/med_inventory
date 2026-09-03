document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('subscriptionModal');
    const closeBtn = document.querySelector('.close-modal');
    const unlockBtns = document.querySelectorAll('.unlock-btn');

    // Open modal + set package
    unlockBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const pkg = btn.getAttribute('data-package') || 'full';
            document.getElementById('selectedPackage').value = pkg;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });

    // Close modal
    closeBtn.onclick = () => { modal.style.display = 'none'; document.body.style.overflow = 'auto'; };
    window.onclick = e => { if (e.target === modal) modal.style.display = 'none'; document.body.style.overflow = 'auto'; };

    // Card formatting
    document.getElementById('card_number')?.addEventListener('input', e => {
        let v = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        let matches = v.match(/\d{4,16}/g);
        let match = (matches && matches[0]) || '';
        let parts = [];
        for (let i = 0; i < match.length; i += 4) parts.push(match.substring(i, i + 4));
        e.target.value = parts.length ? parts.join(' ') : v;
    });

    document.getElementById('expiry')?.addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g, '');
        if (v.length >= 3) v = v.substring(0,2) + '/' + v.substring(2,4);
        e.target.value = v;
    });
});

// Helper functions
function selectPackage(pkg) {
    document.querySelectorAll('.package-card').forEach(c => c.classList.remove('selected'));
    event.target.closest('.package-card').classList.add('selected');
    document.getElementById('selectedPackage').value = pkg;
}

function selectPayment(method) {
    document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
    event.target.closest('.payment-method').classList.add('selected');
    document.querySelectorAll('.payment-details').forEach(d => d.classList.remove('active'));
    document.getElementById(method + 'Details').classList.add('active');
    document.querySelector(`input[value="${method}"]`).checked = true;
}
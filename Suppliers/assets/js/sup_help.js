document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('faq-search');
    const accordionItems = document.querySelectorAll('.accordion-item');
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('toggle-sidebar');

    // Sidebar toggle
    if (localStorage.getItem('sidebar') === 'open') {
        sidebar.classList.add('active');
    }
    toggleButton.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        localStorage.setItem('sidebar', sidebar.classList.contains('active') ? 'open' : 'closed');
    });

    // Theme toggle
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        document.querySelectorAll('.sidebar, .card, .table, .alert, .accordion').forEach(el => el.classList.add('dark-mode'));
    }
    document.getElementById('theme-toggle').addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        document.querySelectorAll('.sidebar, .card, .table, .alert, .accordion').forEach(el => el.classList.toggle('dark-mode'));
        localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    });

    // Toast notification
    function showToast(message, type = 'success') {
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.innerHTML = `
            <div class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        document.body.appendChild(toastContainer);
        const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
        toast.show();
        setTimeout(() => toastContainer.remove(), 3000);
    }

    // FAQ search
    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase();
        accordionItems.forEach(item => {
            const question = item.querySelector('.accordion-button').textContent.toLowerCase();
            const answer = item.querySelector('.accordion-body').textContent.toLowerCase();
            item.style.display = (question.includes(query) || answer.includes(query)) ? '' : 'none';
        });
    });

    // Keyboard shortcut for search focus (Ctrl+F)
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            searchInput.focus();
        }
    });
});
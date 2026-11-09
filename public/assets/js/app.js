function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';

    document.documentElement.setAttribute('data-theme', next);

    try {
        localStorage.setItem('attendance-theme', next);
    } catch (error) {}
}

function togglePassword() {
    const password = document.getElementById('password');
    const button = document.querySelector('.show-password');

    if (!password || !button) return;

    const showing = password.type === 'text';
    password.type = showing ? 'password' : 'text';
    button.textContent = showing ? 'Show' : 'Hide';
}

function filterTable() {
    const search = document.getElementById('searchInput');
    const table = document.getElementById('studentTable');

    if (!search || !table) return;

    const query = search.value.toLowerCase();

    table.querySelectorAll('tbody tr').forEach(row => {
        row.hidden = !row.textContent.toLowerCase().includes(query);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    try {
        const saved = localStorage.getItem('attendance-theme');

        if (saved === 'dark' || saved === 'light') {
            document.documentElement.setAttribute('data-theme', saved);
        }
    } catch (error) {}
});
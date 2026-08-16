'use strict';

document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const err = document.getElementById('login-error');
    err.style.display = 'none';
    const btn = document.getElementById('login-btn');
    btn.disabled = true; btn.textContent = 'Signing in…';
    try {
        const data = await fetch('/api/v1/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                username: document.getElementById('username').value,
                password: document.getElementById('password').value,
            }),
            credentials: 'same-origin',
        });
        const json = await data.json();
        if (!data.ok) throw new Error(json.message || 'Login failed');
        window.location.href = '/';
    } catch (ex) {
        err.textContent = ex.message;
        err.style.display = 'block';
        btn.disabled = false; btn.textContent = 'Sign in';
    }
});
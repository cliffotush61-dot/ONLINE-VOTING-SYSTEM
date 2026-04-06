function confirmVote() {
    return confirm("Are you sure you want to cast your vote?");
}

function validateRegistration() {
    const reg = document.getElementById("reg_number");
    const password = document.getElementById("password");
    const department = document.getElementById("department");

    if (!reg.value.trim() || !password.value.trim() || !department.value.trim()) {
        alert("Please complete every field before you register.");
        return false;
    }

    if (password.value.length < 6) {
        alert("Password must be at least 6 characters.");
        password.focus();
        return false;
    }

    return true;
}

function initScrollReveal() {
    const items = document.querySelectorAll('.scroll-reveal');

    if (!items.length) {
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.16,
    });

    items.forEach((item) => observer.observe(item));
}

function initCandidateSelection() {
    const cards = document.querySelectorAll('.candidate-card');

    cards.forEach((card) => {
        const radio = card.querySelector('input[type="radio"]');
        if (!radio) return;

        radio.addEventListener('change', () => {
            const group = card.parentElement.querySelectorAll('.candidate-card');
            group.forEach((item) => item.classList.remove('active'));
            card.classList.add('active');
        });
    });
}

function initPasswordToggles() {
    const toggles = document.querySelectorAll('.password-toggle');

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const targetId = toggle.getAttribute('data-password-target');
            const input = document.getElementById(targetId);

            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            toggle.textContent = isHidden ? 'Hide' : 'Show';
            toggle.setAttribute('aria-pressed', String(isHidden));
        });
    });
}

window.addEventListener('DOMContentLoaded', () => {
    initScrollReveal();
    initCandidateSelection();
    initPasswordToggles();
});

// ── Mobile hamburger menu ────────────────────────────────────
const menuBtn  = document.getElementById('menuBtn');
const navLinks = document.getElementById('navLinks');

if (menuBtn && navLinks) {
    menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        navLinks.classList.toggle('open');
        menuBtn.textContent = navLinks.classList.contains('open') ? '✕' : '☰';
    });

    // Close when a link is clicked
    navLinks.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => {
            navLinks.classList.remove('open');
            menuBtn.textContent = '☰';
        });
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!navLinks.contains(e.target) && e.target !== menuBtn) {
            navLinks.classList.remove('open');
            menuBtn.textContent = '☰';
        }
    });
}

// ── Scroll reveal ────────────────────────────────────────────
const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
        if (entry.isIntersecting)
            setTimeout(() => entry.target.classList.add('visible'), i * 80);
    });
}, { threshold: 0.1 });
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

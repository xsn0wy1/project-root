document.addEventListener('DOMContentLoaded', () => {

    document.querySelectorAll('.profile-wrapper').forEach(window.HospitalUI.bindProfileMenu);

    const appShell = document.querySelector('.app-shell');
    const menuBtn = document.querySelector('.sidebar-action');
    const navItems = document.querySelectorAll('.nav-item');
    const sidebarNav = document.querySelector('.sidebar-nav');
    const indicator = document.querySelector('.nav-indicator');

    function updateIndicator(item) {
        if (!indicator || !sidebarNav || !item) return;
        const topPos = item.offsetTop;
        sidebarNav.style.setProperty('--indicator-top', `${topPos}px`);
    }

    window.HospitalUI.bindNavigation({shell: appShell, button: menuBtn});

    // Keep indicator aligned on window resize
    window.addEventListener('resize', () => {
        const activeItem = document.querySelector('.nav-item.active');
        if (activeItem) updateIndicator(activeItem);
    });

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const href = item.getAttribute('href');

            if (!href || href === '#') e.preventDefault();

            document.querySelector('.nav-item.active')?.classList.remove('active');
            item.classList.add('active');

            // Animates smoothly to the clicked item
            updateIndicator(item);
        });
    });

    // Position immediately on load without animation
    const activeStart = document.querySelector('.nav-item.active');
    if (activeStart) {
        updateIndicator(activeStart);
    }

});

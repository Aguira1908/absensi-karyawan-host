// Initialize Swiper
const swiper = new Swiper('.mySwiper', {
    slidesPerView: 1,
    spaceBetween: 0,
    loop: false,
    effect: 'slide',
    speed: 500,
    
    // Navigation arrows
    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
    },
    
    // Pagination dots
    pagination: {
        el: '.swiper-pagination',
        clickable: true,
    },
    
    // Keyboard control
    keyboard: {
        enabled: true,
    },
    
    // Mousewheel control
    mousewheel: {
        sensitivity: 1,
        eventsTarget: '.swiper',
    },
    
    // Touch swipe
    touchRatio: 1,
    touchAngle: 45,
    simulateTouch: true,
    grabCursor: true,
});

// Navigate using navbar links
document.querySelectorAll('[data-slide]').forEach(element => {
    element.addEventListener('click', (e) => {
        e.preventDefault();
        const slideIndex = parseInt(element.getAttribute('data-slide'));
        if (!isNaN(slideIndex)) {
            swiper.slideTo(slideIndex);
        }
    });
});

// Update active nav link on slide change
swiper.on('slideChange', function() {
    const currentIndex = swiper.activeIndex;
    
    document.querySelectorAll('.nav-link').forEach((link, index) => {
        if (index === currentIndex) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
});

// Navbar scroll effect
window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.style.padding = '0.5rem 0';
    } else {
        navbar.style.padding = '1rem 0';
    }
});

// Smooth scroll for slide content (fix for anchor links inside slide)
document.querySelectorAll('.btn-readmore, .footer-links a[data-slide]').forEach(btn => {
    btn.addEventListener('click', (e) => {
        if (btn.getAttribute('data-slide')) {
            e.preventDefault();
            const slideIndex = parseInt(btn.getAttribute('data-slide'));
            swiper.slideTo(slideIndex);
        }
    });
});
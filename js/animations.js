
const observer = new IntersectionObserver((entries, observer) => {

    entries.forEach(entry => {

        if (entry.isIntersecting) {

            entry.target.classList.add("active");

            // Animate only once
            observer.unobserve(entry.target);
        }

    });

}, {
    threshold: 0.2
});

document.querySelectorAll(".reveal").forEach(el => {
    observer.observe(el);
});

// HMO Card Animations
const cards = document.querySelectorAll(".hmo-card");

cards.forEach((card, index) => {
    card.style.transitionDelay = `${index * 100}ms`;
});

const navLinks = document.querySelectorAll(".navbar-nav .nav-link");
const navbarCollapse = document.querySelector(".navbar-collapse");

navLinks.forEach(link => {
    link.addEventListener("click", () => {
        if (navbarCollapse.classList.contains("show")) {
            new bootstrap.Collapse(navbarCollapse).hide();
        }
    });
});

const servicesBackground = document.querySelector(".services-background");

const serviceImages = [
    "assets/1.png",
    "assets/2.png",
    "assets/3.png",
    "assets/4.png",
    "assets/5.png",
    "assets/6.png",
    "assets/7.png",
    "assets/8.png",
    "assets/9.png",
    "assets/10.png",
];

let currentServiceImage = 1;

function changeServiceBackground() {
    servicesBackground.style.opacity = "0";

    setTimeout(() => {
        servicesBackground.style.backgroundImage =
            `url("${serviceImages[currentServiceImage]}")`;

        servicesBackground.style.opacity = "1";

        currentServiceImage =
            (currentServiceImage + 1) % serviceImages.length;

    }, 1000);
}

/* Initial image */
servicesBackground.style.backgroundImage =
    `url("${serviceImages[0]}")`;

/* Change every 5 seconds */
setInterval(changeServiceBackground, 5000);

// ------------------------------------------------------------
// Elastic Accordion — Services section
// Click a panel to expand it with a springy GSAP ease.
// ------------------------------------------------------------
const serviceItems = document.querySelectorAll(".service-item");

function openServiceItem(target) {
    serviceItems.forEach(it => it.classList.remove("open"));
    target.classList.add("open");

    gsap.killTweensOf(serviceItems);
    serviceItems.forEach(it => {
        gsap.to(it, {
            flexGrow: it === target ? 6 : 1,
            duration: 1.1,
            ease: "elastic.out(1, 0.6)"
        });
    });
}

serviceItems.forEach(item => {
    item.addEventListener("click", () => openServiceItem(item));
});

// ------------------------------------------------------------
// Head dentist photo lightbox — click to enlarge
// ------------------------------------------------------------
const headDentistPhoto = document.getElementById("headDentistPhoto");
const lightboxOverlay = document.getElementById("lightboxOverlay");
const lightboxImage = document.getElementById("lightboxImage");
const lightboxClose = document.getElementById("lightboxClose");

function openLightbox() {
    lightboxImage.src = headDentistPhoto.src;
    lightboxOverlay.classList.add("active");
    document.body.style.overflow = "hidden";
}

function closeLightbox() {
    lightboxOverlay.classList.remove("active");
    document.body.style.overflow = "";
}

headDentistPhoto.addEventListener("click", openLightbox);
lightboxClose.addEventListener("click", closeLightbox);
lightboxOverlay.addEventListener("click", (e) => {
    if (e.target === lightboxOverlay) closeLightbox();
});
document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeLightbox();
});
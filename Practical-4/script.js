function toggleTheme() {

    document.body.classList.toggle("dark-mode");

    let button = document.getElementById("themeButton");

    if (document.body.classList.contains("dark-mode")) {
        button.innerHTML = "☀️ Light Mode";
    } else {
        button.innerHTML = "🌙 Dark Mode";
    }
}

function toggleFAQ(number) {

    let answer = document.getElementById("faq" + number);

    if (answer.style.display === "block") {

        answer.style.display = "none";

    } else {

        answer.style.display = "block";

    }

}

// =========================
// MODAL POPUP
// =========================

function openModal() {
    document.getElementById("announcementModal").style.display = "flex";
}

function closeModal() {
    document.getElementById("announcementModal").style.display = "none";
}

// =========================
// CONTENT SLIDER
// =========================

let currentSlide = 0;

let slides = document.querySelectorAll(".slide");


function showSlide(number) {

    slides.forEach(function(slide) {

        slide.classList.remove("active");

    });

    slides[number].classList.add("active");

}


function nextSlide() {

    currentSlide++;

    if (currentSlide >= slides.length) {

        currentSlide = 0;

    }

    showSlide(currentSlide);

}


function previousSlide() {

    currentSlide--;

    if (currentSlide < 0) {

        currentSlide = slides.length - 1;

    }

    showSlide(currentSlide);

}
function closeNotification() {

    document.getElementById("notification").style.display = "none";

}

// HAMBURGER MENU

function toggleMenu() {

    let menu = document.getElementById("navLinks");

    menu.classList.toggle("show");

}
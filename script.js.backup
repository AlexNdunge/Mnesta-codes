const services = [
  {
    title: "Home Cleaning",
    location: "Available across Kenya",
    image: "https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=900&q=85"
  },
  {
    title: "Plumbing",
    location: "Verified local professionals",
    image: "https://images.unsplash.com/photo-1607472586893-edb57bdc0e39?auto=format&fit=crop&w=900&q=85"
  },
  {
    title: "Electrical",
    location: "Safe & qualified technicians",
    image: "https://images.unsplash.com/photo-1621905252507-b35492cc74b4?auto=format&fit=crop&w=900&q=85"
  },
  {
    title: "Moving & Delivery",
    location: "Fast help when you need it",
    image: "https://images.unsplash.com/photo-1600518464441-9154a4dea21b?auto=format&fit=crop&w=900&q=85"
  },
  {
    title: "Home Repairs",
    location: "Book a trusted expert",
    image: "https://images.unsplash.com/photo-1581244277943-fe4a9c777189?auto=format&fit=crop&w=900&q=85"
  }
];

let current = 0;
const serviceImage = document.getElementById("serviceImage");
const serviceTitle = document.getElementById("serviceTitle");
const serviceLocation = document.getElementById("serviceLocation");
const progressBar = document.getElementById("progressBar");
const slideNumber = document.getElementById("slideNumber");

function renderService(index) {
  const service = services[index];
  serviceImage.style.backgroundImage = `url("${service.image}")`;
  serviceTitle.textContent = service.title;
  serviceLocation.textContent = service.location;
  slideNumber.textContent = `${String(index + 1).padStart(2, "0")}/${String(services.length).padStart(2, "0")}`;
  progressBar.style.width = `${((index + 1) / services.length) * 100}%`;
}

document.getElementById("nextService").addEventListener("click", () => {
  current = (current + 1) % services.length;
  renderService(current);
});

setInterval(() => {
  current = (current + 1) % services.length;
  renderService(current);
}, 6000);

renderService(current);

// Mobile menu
const mobileToggle = document.getElementById("mobileToggle");
const navMenu = document.getElementById("navMenu");

mobileToggle.addEventListener("click", () => {
  navMenu.classList.toggle("mobile-open");
  if (navMenu.classList.contains("mobile-open")) {
    navMenu.style.display = "flex";
    navMenu.style.position = "absolute";
    navMenu.style.top = "70px";
    navMenu.style.left = "20px";
    navMenu.style.right = "20px";
    navMenu.style.flexDirection = "column";
    navMenu.style.alignItems = "stretch";
    navMenu.style.borderRadius = "20px";
    navMenu.style.padding = "12px";
    navMenu.style.background = "rgba(10,15,16,.96)";
  } else {
    navMenu.removeAttribute("style");
  }
});

// Auth modal
const modal = document.getElementById("authModal");
const modalTitle = document.getElementById("modalTitle");
const modalEyebrow = document.getElementById("modalEyebrow");
const switchAuth = document.getElementById("switchAuth");

function openAuth(type) {
  modal.classList.add("active");
  const signup = type === "signup";
  modalTitle.textContent = signup ? "Create your Juakazi account" : "Log in to Juakazi";
  modalEyebrow.textContent = signup ? "JOIN JUAKAZI" : "WELCOME BACK";
  switchAuth.innerHTML = signup
    ? `Already have an account? <button>Log in</button>`
    : `Don't have an account? <button>Sign up</button>`;
  switchAuth.querySelector("button").onclick = () => openAuth(signup ? "login" : "signup");
}

document.getElementById("loginBtn").onclick = () => openAuth("login");
document.getElementById("signupBtn").onclick = () => openAuth("signup");
document.getElementById("providerBtn").onclick = () => openAuth("signup");
document.getElementById("modalClose").onclick = () => modal.classList.remove("active");

modal.addEventListener("click", (e) => {
  if (e.target === modal) modal.classList.remove("active");
});

document.getElementById("authForm").addEventListener("submit", (e) => {
  e.preventDefault();
  alert("Demo only — connect this form to your Juakazi backend/API.");
});

// Close mobile menu after clicking a link
document.querySelectorAll(".nav-pill a").forEach(link => {
  link.addEventListener("click", () => {
    navMenu.classList.remove("mobile-open");
    if (window.innerWidth <= 1000) navMenu.removeAttribute("style");
  });
});

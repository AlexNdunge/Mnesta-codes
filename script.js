// Global state management
let currentUser = null;
let isAuthenticated = false;


// Utility functions
function showError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.remove('hidden');
    }
}

function hideError(elementId) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.classList.add('hidden');
    }
}

function setLoading(buttonId, loading) {
    const button = document.getElementById(buttonId);
    if (button) {
        if (loading) {
            button.disabled = true;
            button.textContent = button.textContent.includes('Sign in') ? 'Signing in...' : 
                               button.textContent.includes('Create') ? 'Creating Account...' : 'Loading...';
        } else {
            button.disabled = false;
            button.textContent = button.textContent.includes('Signing') ? 'Sign in' : 
                               button.textContent.includes('Creating') ? 'Create Account' : 'Submit';
        }
    }
}

// Authentication functions
function login(email, password) {
    // Basic client-side validation only
    if (email && password) {
        currentUser = {
            id: Date.now(),
            name: email.split('@')[0],
            email: email,
            role: 'customer'
        };
        isAuthenticated = true;
        localStorage.setItem('user', JSON.stringify(currentUser));
        localStorage.setItem('isAuthenticated', 'true');
        return Promise.resolve({ success: true });
    } else {
        return Promise.resolve({ success: false, error: 'Please enter email and password' });
    }
}

function signup(name, email, password, role) {
    // Basic client-side validation only
    if (name && email && password) {
        currentUser = {
            id: Date.now(),
            name: name,
            email: email,
            role: role
        };
        isAuthenticated = true;
        localStorage.setItem('user', JSON.stringify(currentUser));
        localStorage.setItem('isAuthenticated', 'true');
        return Promise.resolve({ success: true });
    } else {
        return Promise.resolve({ success: false, error: 'Please fill in all fields' });
    }
}

function logout() {
    currentUser = null;
    isAuthenticated = false;
    localStorage.removeItem('user');
    localStorage.removeItem('isAuthenticated');
    updateAuthNav();
}

function updateAuthNav() {
    const authNav = document.getElementById('auth-nav');
    if (!authNav) return;

    if (isAuthenticated && currentUser) {
        authNav.innerHTML = `
            <div class="flex items-center space-x-4">
                <span class="text-gray-700">Welcome, ${currentUser.name}</span>
                <button onclick="logout()" class="whitespace-nowrap inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-red-600 hover:bg-red-700">
                    Logout
                </button>
            </div>
        `;
    } else {
        authNav.innerHTML = `
            <a href="signin.html" class="whitespace-nowrap text-base font-medium text-gray-500 hover:text-gray-900">Sign in</a>
            <a href="signup.html" class="ml-8 whitespace-nowrap inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700">Sign up</a>
        `;
    }
}

// Services functions
function filterServices(services, searchQuery, category) {
    return services.filter(service => {
        const matchesSearch = !searchQuery || 
            service.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
            service.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
            service.provider.name.toLowerCase().includes(searchQuery.toLowerCase());
        
        const matchesCategory = !category || service.category === category;
        
        return matchesSearch && matchesCategory;
    });
}

function renderServices(services) {
    const grid = document.getElementById('services-grid');
    const noResults = document.getElementById('no-results');
    
    if (!grid) return;

    if (services.length === 0) {
        grid.innerHTML = '';
        if (noResults) noResults.classList.remove('hidden');
        return;
    }

    if (noResults) noResults.classList.add('hidden');

    grid.innerHTML = services.map(service => `
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-3">
                <h3 class="text-lg font-semibold text-gray-900">${service.title}</h3>
                <span class="text-lg font-bold text-blue-600">KES ${service.price}</span>
            </div>
            
            <p class="text-gray-600 mb-3 line-clamp-2">${service.description}</p>
            
            <div class="flex items-center mb-3">
                <img
                    src="${service.provider.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(service.provider.name)}&background=3b82f6&color=fff`}"
                    alt="${service.provider.name}"
                    class="w-8 h-8 rounded-full mr-2"
                />
                <div>
                    <p class="text-sm font-medium text-gray-900">${service.provider.name}</p>
                    <p class="text-xs text-gray-500">${service.location}</p>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <span class="text-yellow-400">★</span>
                    <span class="text-sm text-gray-600 ml-1">
                        ${service.provider.rating || 'New'} (${service.provider.reviewCount || 0} reviews)
                    </span>
                </div>
                <button
                    onclick="openBookingModal(${service.id})"
                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors"
                >
                    Book Now
                </button>
            </div>
        </div>
    `).join('');
}

function loadServices() {
    const loading = document.getElementById('loading');
    const searchInput = document.getElementById('search-input');
    const categoryFilter = document.getElementById('category-filter');
    
    if (loading) loading.classList.remove('hidden');
    
    // No services to load without backend
    setTimeout(() => {
        renderServices([]);
        if (loading) loading.classList.add('hidden');
    }, 300);
}

// Booking functions
function openBookingModal(serviceId) {
    if (!isAuthenticated) {
        alert('Please sign in to book a service');
        window.location.href = 'signin.html';
        return;
    }

    // Without backend, just show modal with generic info
    const modal = document.getElementById('booking-modal');
    const serviceTitle = document.getElementById('service-title');
    const providerName = document.getElementById('provider-name');

    if (serviceTitle) serviceTitle.textContent = 'Service Booking';
    if (providerName) providerName.textContent = 'Service Provider';
    if (modal) modal.classList.remove('hidden');

    // Store service ID for form submission
    const form = document.getElementById('booking-form');
    if (form) form.dataset.serviceId = serviceId;
}

function closeBookingModal() {
    const modal = document.getElementById('booking-modal');
    if (modal) modal.classList.add('hidden');
}

// Initialize page-specific functionality
function initPage() {
    // Check authentication status
    const storedAuth = localStorage.getItem('isAuthenticated');
    const storedUser = localStorage.getItem('user');
    
    if (storedAuth === 'true' && storedUser) {
        isAuthenticated = true;
        currentUser = JSON.parse(storedUser);
    }
    
    updateAuthNav();

    // Page-specific initialization
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    
    switch (currentPage) {
        case 'index.html':
        case '':
            // Homepage initialization
            break;
            
        case 'signin.html':
            initSignInPage();
            break;
            
        case 'signup.html':
            initSignUpPage();
            break;
            
        case 'services.html':
            initServicesPage();
            break;
    }
}

function initSignInPage() {
    const form = document.getElementById('signin-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        const email = formData.get('email');
        const password = formData.get('password');
        
        if (!email || !password) {
            showError('error-message', 'Please fill in all fields');
            return;
        }
        
        hideError('error-message');
        setLoading('submit-btn', true);
        
        try {
            const result = await login(email, password);
            if (result.success) {
                window.location.href = 'services.html';
            } else {
                showError('error-message', result.error);
            }
        } catch (error) {
            showError('error-message', 'Network error. Please try again.');
        } finally {
            setLoading('submit-btn', false);
        }
    });
}

function initSignUpPage() {
    const form = document.getElementById('signup-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        const name = formData.get('name');
        const email = formData.get('email');
        const password = formData.get('password');
        const confirmPassword = formData.get('confirmPassword');
        const role = formData.get('role');
        
        // Validation
        if (!name || !email || !password || !confirmPassword) {
            showError('error-message', 'Please fill in all fields');
            return;
        }
        
        if (password !== confirmPassword) {
            showError('error-message', 'Passwords do not match');
            return;
        }
        
        if (password.length < 6) {
            showError('error-message', 'Password must be at least 6 characters');
            return;
        }
        
        hideError('error-message');
        setLoading('submit-btn', true);
        
        try {
            const result = await signup(name, email, password, role);
            if (result.success) {
                window.location.href = 'services.html';
            } else {
                showError('error-message', result.error);
            }
        } catch (error) {
            showError('error-message', 'Network error. Please try again.');
        } finally {
            setLoading('submit-btn', false);
        }
    });
}

function initServicesPage() {
    // Load initial services
    loadServices();
    
    // Set up search and filter
    const searchInput = document.getElementById('search-input');
    const categoryFilter = document.getElementById('category-filter');
    
    if (searchInput) {
        searchInput.addEventListener('input', loadServices);
    }
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', loadServices);
    }
    
    // Set up booking modal
    const closeModalBtn = document.getElementById('close-modal');
    const cancelBookingBtn = document.getElementById('cancel-booking');
    const bookingForm = document.getElementById('booking-form');
    
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeBookingModal);
    }
    
    if (cancelBookingBtn) {
        cancelBookingBtn.addEventListener('click', closeBookingModal);
    }
    
    if (bookingForm) {
        bookingForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const formData = new FormData(bookingForm);
            const serviceId = bookingForm.dataset.serviceId;
            
            // Simple client-side booking confirmation
            if (formData.get('date') && formData.get('time')) {
                alert('Booking request submitted! (Note: No backend connected)');
                closeBookingModal();
                bookingForm.reset();
            } else {
                alert('Please fill in date and time');
            }
        });
    }
    
    // Close modal when clicking outside
    const modal = document.getElementById('booking-modal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeBookingModal();
            }
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initPage);

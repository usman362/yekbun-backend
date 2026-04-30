

document.addEventListener('DOMContentLoaded', function () {

    initializeBootstrapComponents();


    initializeSidebar();


    animateProgressBars();


    initializeCharts();
});


class CartManager {
    constructor() {
        this.cart = JSON.parse(localStorage.getItem('cart')) || [];
        this.cartCount = 0;
        this.updateCartDisplay();
    }

    addItem(item) {
        this.cart.push(item);
        this.saveCart();
        this.updateCartDisplay();
    }

    removeItem(itemId) {
        this.cart = this.cart.filter(item => item.id !== itemId);
        this.saveCart();
        this.updateCartDisplay();
    }

    updateCartDisplay() {
        this.cartCount = this.cart.length;
        const cartBadge = document.querySelector('.cart-badge');
        if (cartBadge) {
            cartBadge.textContent = this.cartCount;
            cartBadge.style.display = this.cartCount > 0 ? 'block' : 'none';
        }
    }

    saveCart() {
        localStorage.setItem('cart', JSON.stringify(this.cart));
    }

    clearCart() {
        this.cart = [];
        this.saveCart();
        this.updateCartDisplay();
    }
}


let cartManager;


class SidebarManager {
    constructor() {
        this.sidebar = document.getElementById('sidebarMenu');
        this.layoutWrapper = document.getElementById('layoutWrapper');
        this.layoutContainer = document.getElementById('layoutContainer');
        this.layoutPage = document.getElementById('layoutPage');
        this.overlay = document.getElementById('sidebarOverlay');
        this.desktopToggle = document.getElementById('sidebarToggle');
        this.sidebarToggledesk = document.getElementById('sidebarToggledesk');
        this.mobileToggle = document.getElementById('sidebarToggle');

        this.isCollapsed = false;
        this.isMobile = window.innerWidth < 1200;
        this.isOpen = false;

        this.init();
    }

    init() {
        this.loadSavedState();
        this.bindEvents();
        this.handleResize();
        this.updateSidebarState();
        this.setupSidebarIcons();
    }

    setupSidebarIcons() {


        const navLinks = document.querySelectorAll('.nav-pills .nav-link[data-icon]');

        navLinks.forEach(link => {
            const icon = link.getAttribute('data-icon');
            if (icon) {

                let iconElement = link.querySelector('i.fas, i.fa');
                if (!iconElement) {
                    iconElement = document.createElement('i');
                    iconElement.className = icon + ' me-2';


                    const navText = link.querySelector('.nav-text');
                    if (navText) {
                        navText.insertBefore(iconElement, navText.firstChild);
                    } else {
                        link.insertBefore(iconElement, link.firstChild);
                    }
                }


                if (!iconElement.classList.contains('fas') && !iconElement.classList.contains('fa')) {
                    iconElement.className = icon + ' me-2';
                }
            }
        });
    }

    bindEvents() {

        if (this.desktopToggle) {
            this.desktopToggle.addEventListener('click', () => this.toggleDesktop());
        }


        if (this.sidebarToggledesk) {
            this.sidebarToggledesk.addEventListener('click', () => this.toggleMobile());
        }

        if (this.mobileToggle) {
            this.mobileToggle.addEventListener('click', () => this.toggleMobile());
        }


        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.closeMobile());
        }


        window.addEventListener('resize', () => this.handleResize());


        document.addEventListener('keydown', (e) => this.handleKeydown(e));


        this.preventBodyScroll();
    }

    toggleDesktop() {
        if (this.isMobile) return;

        this.isCollapsed = !this.isCollapsed;
        this.updateSidebarState();
        this.saveState();
    }

    toggleMobile() {
        if (!this.isMobile) return;

        this.isOpen = !this.isOpen;
        this.updateSidebarState();
    }

    closeMobile() {
        if (!this.isMobile) return;

        this.isOpen = false;
        this.updateSidebarState();
    }

    updateSidebarState() {
        if (this.isMobile) {

            if (this.isOpen) {
                this.layoutWrapper.classList.add('sidebar-open');
                this.overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            } else {
                this.layoutWrapper.classList.remove('sidebar-open');
                this.overlay.classList.remove('show');
                document.body.style.overflow = '';
            }


            this.layoutWrapper.classList.remove('sidebar-collapsed');
        } else {

            if (this.isCollapsed) {
                this.layoutWrapper.classList.add('sidebar-collapsed');
                this.showSidebarIcons();
            } else {
                this.layoutWrapper.classList.remove('sidebar-collapsed');
                this.hideSidebarIcons();
            }


            this.layoutWrapper.classList.remove('sidebar-open');
            this.overlay.classList.remove('show');
            document.body.style.overflow = '';
        }


        this.updateAriaAttributes();


        this.dispatchStateChangeEvent();
    }

    showSidebarIcons() {
        const navLinks = document.querySelectorAll('.nav-pills .nav-link');
        navLinks.forEach(link => {
            const icon = link.getAttribute('data-icon');


            let textSpan = link.querySelector('.nav-text span') || link.querySelector('span:last-child');
            let textContent = '';

            if (textSpan) {
                textContent = textSpan.textContent.trim();
            } else {

                const directSpan = link.querySelector('span');
                if (directSpan) {
                    textContent = directSpan.textContent.trim();
                    textSpan = directSpan;
                }
            }

            if (icon && textContent) {

                let iconElement = link.querySelector('i.fas, i.fa');
                if (!iconElement) {
                    iconElement = document.createElement('i');
                    iconElement.className = icon;
                    link.insertBefore(iconElement, link.firstChild);
                }

                iconElement.style.display = 'inline-block';
                iconElement.style.fontSize = '20px';
                iconElement.style.margin = '0';


                link.setAttribute('title', textContent);
                link.setAttribute('data-bs-toggle', 'tooltip');
                link.setAttribute('data-bs-placement', 'right');
            }
        });





        this.initializeTooltips();
    }

    hideSidebarIcons() {
        const navLinks = document.querySelectorAll('.nav-pills .nav-link');
        navLinks.forEach(link => {
            const iconElement = link.querySelector('i.fas, i.fa');

            if (iconElement) {
                iconElement.style.fontSize = '';
                iconElement.style.margin = '';
            }
        });


        this.disposeTooltips();
    }

    initializeTooltips() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {

                const existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (!existingTooltip) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                }
                return existingTooltip;
            });
        }
    }

    disposeTooltips() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(tooltip => {
                const bsTooltip = bootstrap.Tooltip.getInstance(tooltip);
                if (bsTooltip) {
                    bsTooltip.dispose();
                }
            });
        }
    }




    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth < 1200;

        if (wasMobile !== this.isMobile) {

            this.isOpen = false;
            this.updateSidebarState();

            if (!this.isMobile) {

                this.loadSavedState();
                this.updateSidebarState();
            }
        }


        clearTimeout(this.resizeTimeout);
        this.resizeTimeout = setTimeout(() => {
            this.updateSidebarState();
        }, 100);
    }

    handleKeydown(e) {

        if (e.key === 'Escape' && this.isMobile && this.isOpen) {
            this.closeMobile();
        }


        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            if (this.isMobile) {
                this.toggleMobile();
            } else {
                this.toggleDesktop();
            }
        }
    }

    preventBodyScroll() {
        let startY = 0;

        if (this.sidebar) {
            this.sidebar.addEventListener('touchstart', (e) => {
                startY = e.touches[0].clientY;
            }, { passive: true });

            this.sidebar.addEventListener('touchmove', (e) => {
                const currentY = e.touches[0].clientY;
                const sidebar = e.currentTarget;
                const scrollTop = sidebar.scrollTop;
                const scrollHeight = sidebar.scrollHeight;
                const height = sidebar.clientHeight;

                if ((scrollTop <= 0 && currentY > startY) ||
                    (scrollTop >= scrollHeight - height && currentY < startY)) {
                    e.preventDefault();
                }
            }, { passive: false });
        }
    }

    updateAriaAttributes() {
        if (this.desktopToggle) {
            this.desktopToggle.setAttribute('aria-expanded',
                this.isMobile ? 'false' : (!this.isCollapsed).toString());
        }

        if (this.mobileToggle) {
            this.mobileToggle.setAttribute('aria-expanded',
                this.isMobile ? this.isOpen.toString() : 'false');
        }

        if (this.sidebar) {
            this.sidebar.setAttribute('aria-hidden',
                this.isMobile ? (!this.isOpen).toString() : 'false');
        }
    }

    saveState() {
        if (!this.isMobile) {
            localStorage.setItem('sidebarCollapsed', this.isCollapsed.toString());
        }
    }

    loadSavedState() {
        if (!this.isMobile) {
            const saved = localStorage.getItem('sidebarCollapsed');
            if (saved !== null) {
                this.isCollapsed = saved === 'true';
            }
        }
    }

    dispatchStateChangeEvent() {
        const event = new CustomEvent('sidebarStateChange', {
            detail: {
                isCollapsed: this.isCollapsed,
                isOpen: this.isOpen,
                isMobile: this.isMobile
            }
        });
        document.dispatchEvent(event);
    }


    collapse() {
        if (!this.isMobile) {
            this.isCollapsed = true;
            this.updateSidebarState();
            this.saveState();
        }
    }

    expand() {
        if (!this.isMobile) {
            this.isCollapsed = false;
            this.updateSidebarState();
            this.saveState();
        }
    }

    open() {
        if (this.isMobile) {
            this.isOpen = true;
            this.updateSidebarState();
        }
    }

    close() {
        if (this.isMobile) {
            this.isOpen = false;
            this.updateSidebarState();
        }
    }

    getState() {
        return {
            isCollapsed: this.isCollapsed,
            isOpen: this.isOpen,
            isMobile: this.isMobile
        };
    }
}


function initializeBootstrapComponents() {

    if (typeof bootstrap === 'undefined') {
        console.warn('Bootstrap JavaScript not loaded');
        return;
    }


    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

}


let sidebarManager;

function initializeSidebar() {
    sidebarManager = new SidebarManager();
    cartManager = new CartManager();



    window.addEventListener('load', () => {

        setTimeout(() => {
            sidebarManager.updateSidebarState();

            if (typeof Chart !== 'undefined' && Object.keys(chartInstances).length === 0) {
                initializeCharts();
            }
        }, 100);
    });
}


function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const progressBar = entry.target;
                const targetWidth = progressBar.style.width;


                progressBar.style.width = '0%';


                setTimeout(() => {
                    progressBar.style.transition = 'width 1s ease-out';
                    progressBar.style.width = targetWidth;
                }, 100);
            }
        });
    }, {
        threshold: 0.1
    });

    progressBars.forEach(bar => {
        observer.observe(bar);
    });
}


function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}


window.dashboardFunctions = {

    getSidebarManager: () => sidebarManager,
    toggleSidebar: () => {
        if (sidebarManager) {
            if (sidebarManager.isMobile) {
                sidebarManager.toggleMobile();
            } else {
                sidebarManager.toggleDesktop();
            }
        }
    },
    collapseSidebar: () => sidebarManager?.collapse(),
    expandSidebar: () => sidebarManager?.expand(),
    openSidebar: () => sidebarManager?.open(),
    closeSidebar: () => sidebarManager?.close(),
    getSidebarState: () => sidebarManager?.getState(),


    getCartManager: () => cartManager,
    addToCart: (item) => cartManager?.addItem(item),
    removeFromCart: (itemId) => cartManager?.removeItem(itemId),
    clearCart: () => cartManager?.clearCart(),
    getCartCount: () => cartManager?.cartCount || 0,


    initializeCharts: initializeCharts,
    refreshCharts: function () {
        initializeCharts();
    },
    getChartInstances: function () {
        return chartInstances;
    }
};




var chartInstances = {};

function initializeCharts() {

    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded yet, retrying...');
        setTimeout(initializeCharts, 100);
        return;
    }

    console.log('Initializing charts...');

    var chartIds = ['chart1', 'chart2', 'chart3'];
    var chartData = [
        [20, 45, 30, 60],
        [30, 55, 40, 70],
        [40, 65, 50, 80]
    ];

    var chartsCreated = 0;

    for (var i = 0; i < chartIds.length; i++) {
        var chartId = chartIds[i];
        var canvas = document.getElementById(chartId);

        if (canvas) {

            if (chartInstances[chartId]) {
                chartInstances[chartId].destroy();
            }


            chartInstances[chartId] = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: ['8:00', '12:00', '16:00', '20:00'],
                    datasets: [{
                        data: chartData[i],
                        borderColor: '#28B5E0',
                        backgroundColor: 'rgba(40, 181, 224, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    },
                    scales: {
                        x: { display: false, grid: { display: false } },
                        y: { display: false, grid: { display: false } }
                    },
                    elements: {
                        line: { borderJoinStyle: 'round' }
                    }
                }
            });

            chartsCreated++;
            console.log('Chart created:', chartId);
        } else {
            console.warn('Canvas element not found:', chartId);
        }
    }

    console.log('Charts initialization complete. Created:', chartsCreated, 'charts');
}

document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('verticalLinesChart').getContext('2d');

    const chartData = {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        datasets: [
            {
                label: 'Users',
                data: [45000, 38000, 50000, 42000, 55000, 48000, 52000],
                backgroundColor: '#FF6638',
            },
            {
                label: 'Tickets',
                data: [25000, 30000, 22000, 35000, 28000, 31000, 29000],
                backgroundColor: '#1BC469',
            },
            {
                label: 'Playlist',
                data: [15000, 18000, 12000, 20000, 17000, 19000, 16000],
                backgroundColor: '#3AACE6',
            },
            {
                label: 'Market',
                data: [8000, 10000, 7000, 11000, 9000, 12000, 10000],
                backgroundColor: '#8571F4',
            },
            {
                label: 'Shops',
                data: [5000, 6000, 4000, 7000, 5500, 6500, 5800],
                backgroundColor: '#F19B1F',
            },
            {
                label: 'G-Ads',
                data: [3000, 4000, 2500, 4500, 3500, 4200, 3800],
                backgroundColor: '#45D0EE',
            },
            {
                label: 'User-Ads',
                data: [2000, 2500, 1800, 3000, 2200, 2800, 2600],
                backgroundColor: '#B5179E',
            }
        ]
    };

    const verticalLinesChart = new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'start',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10,
                        padding: 20
                    }
                },
                title: {
                    display: false,
                }
            },
            scales: {
                x: {
                    stacked: false,
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    min: 0,
                    max: 60000,
                    ticks: {
                        callback: function (value) {
                            if (value >= 1000) {
                                return value / 1000 + 'k';
                            }
                            return value;
                        },
                        stepSize: 10000
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                }
            }
        },
        data: {
            labels: chartData.labels,
            datasets: chartData.datasets.map(ds => ({
                ...ds,
                barThickness: 10,
                borderRadius: {
                    topLeft: 8,
                    topRight: 8
                },
                borderColor: 'white',
                borderWidth: 2,
            }))
        }
    });
    


    const timeFilterButtons = document.querySelectorAll('.time-filters button');
    timeFilterButtons.forEach(button => {
        button.addEventListener('click', () => {
            timeFilterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

        });
    });

    const ctxmyDoughnutChart = document.getElementById('myDoughnutChart').getContext('2d');

    // Data from your image
    const data = {
        labels: ['Users', 'Events', 'Playlist', 'Market', 'Shops', 'Google Ads', 'User Ads'],
        datasets: [{
            label: 'Percentage',
            data: [46, 46, 46, 46, 45, 46, 46], // Corresponding percentages
            backgroundColor: [
                '#FF6384', // Red for Users
                '#36A2EB', // Blue for Events
                '#4BC0C0', // Green for Playlist
                '#9966FF', // Purple for Market
                '#FFCD56', // Yellow for Shops
                '#FF9F40', // Orange for Google Ads
                '#C9CBCE'  // Grey for User Ads
            ],
            hoverOffset: 4,
            borderColor: 'white', // Border color between slices
            borderWidth: 2
        }]
    };

    const config = {
        type: 'doughnut',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false // We will create a custom legend
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += context.parsed + '%';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    };

    const myDoughnutChart = new Chart(ctxmyDoughnutChart, config);

    // Custom Legend Generation
    const customLegend = document.getElementById('custom-legend');
    data.labels.forEach((label, index) => {
        const listItem = document.createElement('li');
        listItem.classList.add('legend-item');

        const colorBox = document.createElement('span');
        colorBox.classList.add('legend-color-box');
        colorBox.style.backgroundColor = data.datasets[0].backgroundColor[index];

        const textSpan = document.createElement('span');
        textSpan.textContent = `${label} ${data.datasets[0].data[index]}%`;

        listItem.appendChild(colorBox);
        listItem.appendChild(textSpan);
        customLegend.appendChild(listItem);
    });
});


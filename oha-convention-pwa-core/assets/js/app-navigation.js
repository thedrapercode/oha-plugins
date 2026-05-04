/**
 * OHA Convention PWA - App Navigation System
 * Modern bottom tab navigation for mobile devices
 */

// IMMEDIATE PWA DETECTION - Runs before DOM ready
(function() {
    'use strict';
    
    // Check if we're in PWA mode or should be in app mode
    function detectAppMode() {
        const isMobile = window.innerWidth <= 768;
        const isPWA = window.matchMedia('(display-mode: standalone)').matches || 
                     window.navigator.standalone === true ||
                     window.matchMedia('(display-mode: fullscreen)').matches;
        const isOffline = !navigator.onLine;
        const wasAppMode = sessionStorage.getItem('oha-app-mode') === 'enabled';
        
        return isMobile && (isPWA || isOffline || wasAppMode);
    }
    
    // Apply app mode IMMEDIATELY if detected
    if (detectAppMode()) {
        console.log('[App Nav] IMMEDIATE app mode detected - applying body class');
        document.documentElement.classList.add('oha-app-mode');
        if (document.body) {
            document.body.classList.add('oha-app-mode', 'loading');
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('oha-app-mode', 'loading');
            });
        }
        sessionStorage.setItem('oha-app-mode', 'enabled');
        
        // Remove loading class when page is ready (with fade out)
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.body.classList.add('loading-out');
                setTimeout(function() {
                    document.body.classList.remove('loading', 'loading-out');
                }, 300); // Match CSS fade out duration
            }, 200); // Small delay to ensure smooth transition
        });
        
        // Fallback: Remove loading after 3 seconds max
        setTimeout(function() {
            document.body.classList.add('loading-out');
            setTimeout(function() {
                document.body.classList.remove('loading', 'loading-out');
            }, 300);
        }, 3000);
    }
})();

// Main app navigation (runs on DOM ready)
(function($) {
    'use strict';
    
    window.OHAAppNav = window.OHAAppNav || {};
    
    const APP_CONFIG = {
        tabs: [
            {id: 'schedule', icon: '📅', label: 'Schedule', url: '/convention/2026-convention/schedule/'},
            {id: 'map',      icon: '🗺️', label: 'Map',      url: '/convention/2026-convention/map/'},
            {id: 'myohc',   icon: '⭐',  label: 'My OHC',   url: '/convention/2026-convention/my-ohc/'},
            {id: 'vendors',  icon: '🛍️', label: 'Vendors',  url: '/convention/2026-convention/vendors/'},
            {id: 'info',    icon: 'ℹ️',  label: 'Info',     url: '/convention/2026-convention/info/'}
        ],
        mobileBreakpoint: 768
    };
    
    OHAAppNav.isMobile = function() {
        return window.innerWidth <= APP_CONFIG.mobileBreakpoint;
    };
    
    OHAAppNav.isPWA = function() {
        const standalone = window.matchMedia('(display-mode: standalone)').matches;
        const ios = window.navigator.standalone === true;
        const fullscreen = window.matchMedia('(display-mode: fullscreen)').matches;
        return standalone || ios || fullscreen;
    };
    
    OHAAppNav.shouldEnableAppMode = function() {
        if (!this.isMobile()) {
            return false;
        }
        
        const storedMode = sessionStorage.getItem('oha-app-mode');
        if (storedMode === 'enabled') {
            console.log('[App Nav] App mode enabled via sessionStorage');
            return true;
        }
        
        const isPWA = this.isPWA();
        const isOffline = !navigator.onLine;
        
        if (isPWA || isOffline) {
            sessionStorage.setItem('oha-app-mode', 'enabled');
            console.log('[App Nav] App mode enabled:', isPWA ? 'PWA detected' : 'Offline detected');
            return true;
        }
        
        return false;
    };
    
    OHAAppNav.init = function() {
        console.log('[App Nav] Initializing...');
        console.log('[App Nav] Is mobile:', this.isMobile());
        console.log('[App Nav] Is PWA:', this.isPWA());
        console.log('[App Nav] Is offline:', !navigator.onLine);
        console.log('[App Nav] Session storage:', sessionStorage.getItem('oha-app-mode'));
        
        if (this.shouldEnableAppMode()) {
            this.enableAppMode();
            this.createNavigation();
            this.setActiveTab();
        } else {
            console.log('[App Nav] App mode not enabled');
        }
    };
    
    OHAAppNav.enableAppMode = function() {
        console.log('[App Nav] Enabling app mode');
        
        document.documentElement.classList.add('oha-app-mode');
        document.body.classList.add('oha-app-mode');
        sessionStorage.setItem('oha-app-mode', 'enabled');
        
        // Force hide Kadence elements
        setTimeout(() => {
            if (window.innerWidth <= 768) {
                const elementsToHide = document.querySelectorAll(
                    '.site-header, .mobile-navigation, .site-top-header, ' +
                    '.site-main-header-wrap, .header-navigation-wrap, ' +
                    '.mobile-toggle-open-container, #mobile-drawer, .kadence-mobile-header'
                );
                elementsToHide.forEach(el => {
                    el.style.display = 'none';
                });
            }
        }, 100);
    };
    
    OHAAppNav.disableAppMode = function() {
        console.log('[App Nav] Disabling app mode');
        document.documentElement.classList.remove('oha-app-mode');
        document.body.classList.remove('oha-app-mode');
        $('.oha-app-tabs').remove();
        $('#oha-fab-scan').remove();
        sessionStorage.removeItem('oha-app-mode');
    };

    OHAAppNav.createNavigation = function() {
        $('.oha-app-tabs').remove();
        $('#oha-fab-scan').remove();

        var passportUrl = (typeof ohaNavConfig !== 'undefined' && ohaNavConfig.passport_url)
            ? ohaNavConfig.passport_url
            : '/convention/2026-convention/passport/';
        var scannerUrl = (typeof ohaNavConfig !== 'undefined' && ohaNavConfig.scanner_url)
            ? ohaNavConfig.scanner_url
            : passportUrl;

        // All 5 tabs — rendered uniformly
        let navHTML = '<nav class="oha-app-tabs"><div class="oha-app-tabs-container">';
        APP_CONFIG.tabs.forEach(tab => {
            navHTML += `<a href="${tab.url}" class="oha-app-tab" data-tab-id="${tab.id}">
                <span class="oha-app-tab-icon">${tab.icon}</span>
                <span class="oha-app-tab-label">${tab.label}</span>
            </a>`;
        });
        navHTML += '</div></nav>';
        $('body').append(navHTML);

        // FAB — separate fixed element, bottom-right corner above nav
        var showFab = !(typeof ohaNavConfig !== 'undefined' && ohaNavConfig.show_scan_fab === '0');
        if (!showFab) { return; }

        var fabHTML =
            '<button id="oha-fab-scan" class="oha-fab-scan" aria-label="Scan QR Code">' +
                '<span class="oha-fab-scan-icon">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22" aria-hidden="true">' +
                    '<path d="M3 11V3h8v8H3zm2-2h4V5H5v4zM3 21v-8h8v8H3zm2-2h4v-4H5v4zm8-8V3h8v8h-8zm2-2h4V5h-4v4z' +
                    'M19 19v-2h2v2h-2zm-6-6v-2h2v2h-2zm2 2v-2h2v2h-2zm-2 2v-2h2v2h-2zm2 2v-2h2v2h-2zm2-2v-2h2v2h-2z' +
                    'M17 17v-2h2v2h-2zm2-4v-2h2v2h-2z"/>' +
                    '</svg>' +
                '</span>' +
                '<span class="oha-fab-scan-label">Scan</span>' +
            '</button>';
        $('body').append(fabHTML);

        $('#oha-fab-scan').on('click', function() {
            window.location.href = scannerUrl;
        });
    };
    
    OHAAppNav.setActiveTab = function() {
        const currentPath = window.location.pathname;
        $('.oha-app-tab').removeClass('active');
        
        APP_CONFIG.tabs.forEach(tab => {
            if (currentPath.includes(tab.id) || currentPath.startsWith(tab.url) || currentPath === tab.url) {
                $(`.oha-app-tab[data-tab-id="${tab.id}"]`).addClass('active');
            }
        });
        
        // Special case for my-ohc page (also matches favorites/my-schedule)
        if (currentPath.includes('my-ohc') || currentPath.includes('my-schedule') || currentPath.includes('favorite')) {
            $('.oha-app-tab[data-tab-id="myohc"]').addClass('active');
        }
    };
    
    OHAAppNav.getActiveTab = function() {
        const $active = $('.oha-app-tab.active');
        return $active.length ? $active.attr('data-tab-id') : null;
    };
    
    OHAAppNav.debug = function() {
        console.log('=== OHA App Nav Debug Info ===');
        console.log('Is Mobile:', this.isMobile());
        console.log('Is PWA:', this.isPWA());
        console.log('Is Offline:', !navigator.onLine);
        console.log('Should Enable:', this.shouldEnableAppMode());
        console.log('Body has class:', document.body.classList.contains('oha-app-mode'));
        console.log('HTML has class:', document.documentElement.classList.contains('oha-app-mode'));
        console.log('SessionStorage:', sessionStorage.getItem('oha-app-mode'));
        console.log('Tabs visible:', $('.oha-app-tabs').length > 0);
        console.log('Active tab:', this.getActiveTab());
        console.log('===============================');
    };
    
    $(document).ready(function() {
        OHAAppNav.init();
    });
    
})(jQuery);

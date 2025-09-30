

    // CSRF token setup
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const csrfTokenHeader = 'X-CSRF-Token';

    // Global AJAX request setup
    axios.defaults.headers.common[csrfTokenHeader] = csrfToken;
    axios.interceptors.request.use(request => {
        request.headers[csrfTokenHeader] = csrfToken;
    });

    // Global navigation
    const navItems = document.querySelectorAll('.nav-link');
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            document.querySelectorAll('a.active').forEach(activeLink => {
                activeLink.classList.remove('active');
            });
            this.classList.add('active');
        });
    });

    // Global search
    const searchInput = document.getElementById('global-search');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            // Perform search
        });
    }

    // Global modal
    const modalClose = document.querySelectorAll('.modal-close');
    modalClose.forEach(closeButton => {
        closeButton.addEventListener('click', function() {
            const modal = closeButton.closest('.modal');
            modal.style.display = 'none';
        });
    });

    // Global print
    const printButtons = document.querySelectorAll('.btn-print');
    printButtons.forEach(printButton => {
        printButton.addEventListener('click', function() {
            const preview = printButton.closest('.modal-preview');
            if (preview) {
                const printContents = preview.innerHTML;
                const win = window.open('', '', 'width=600,height=600');
                win.document.write('<!DOCTYPE html><html><head><title>Print</head><body>' + printContents + '</body></html>');
                // Main JavaScript file for ON Service CRM
                document.addEventListener('DOMContentLoaded', function() {
                    // CSRF token setup (meta tag may be absent in old templates)
                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    const csrfToken = csrfMeta ? csrfMeta.content : null;
                    const csrfTokenHeader = 'X-CSRF-Token';

                    // Global AJAX request setup (if axios is available)
                    if (typeof axios !== 'undefined' && csrfToken) {
                        axios.defaults.headers.common[csrfTokenHeader] = csrfToken;
                        axios.interceptors.request.use(request => {
                            request.headers[csrfTokenHeader] = csrfToken;
                            return request;
                        });
                    }

                    // Global navigation
                    const navItems = document.querySelectorAll('.nav-link');
                    navItems.forEach(item => {
                        item.addEventListener('click', function() {
                            document.querySelectorAll('a.active').forEach(activeLink => {
                                activeLink.classList.remove('active');
                            });
                            this.classList.add('active');
                        });
                    });

                    // Global search
                    const searchInput = document.getElementById('global-search');
                    if (searchInput) {
                        searchInput.addEventListener('keyup', function() {
                            // Perform search
                        });
                    }

                    // Global modal
                    const modalClose = document.querySelectorAll('.modal-close');
                    modalClose.forEach(closeButton => {
                        closeButton.addEventListener('click', function() {
                            const modal = closeButton.closest('.modal');
                            // Main JavaScript file for ON Service CRM
                            document.addEventListener('DOMContentLoaded', function() {
                                // CSRF token setup (meta tag may be absent in old templates)
                                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                                const csrfToken = csrfMeta ? csrfMeta.content : null;
                                const csrfTokenHeader = 'X-CSRF-Token';

                                // Global AJAX request setup (if axios is available)
                                if (typeof axios !== 'undefined' && csrfToken) {
                                    axios.defaults.headers.common[csrfTokenHeader] = csrfToken;
                                    axios.interceptors.request.use(request => {
                                        request.headers[csrfTokenHeader] = csrfToken;
                                        return request;
                                    });
                                }

                                // Global navigation
                                const navItems = document.querySelectorAll('.nav-link');
                                navItems.forEach(item => {
                                    item.addEventListener('click', function() {
                                        document.querySelectorAll('a.active').forEach(activeLink => {
                                            activeLink.classList.remove('active');
                                        });
                                        this.classList.add('active');
                                    });
                                });

                                // Global search
                                const searchInput = document.getElementById('global-search');
                                if (searchInput) {
                                    searchInput.addEventListener('keyup', function() {
                                        // Perform search
                                    });
                                }

                                // Global modal
                                const modalClose = document.querySelectorAll('.modal-close');
                                modalClose.forEach(closeButton => {
                                    closeButton.addEventListener('click', function() {
                                        const modal = closeButton.closest('.modal');
                                        // Main JavaScript file for ON Service CRM
                                        document.addEventListener('DOMContentLoaded', function() {
                                            // CSRF token setup (meta tag may be absent in old templates)
                                            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                                            const csrfToken = csrfMeta ? csrfMeta.content : null;
                                            const csrfTokenHeader = 'X-CSRF-Token';

                                            // Global AJAX request setup (if axios is available)
                                            if (typeof axios !== 'undefined' && csrfToken) {
                                                axios.defaults.headers.common[csrfTokenHeader] = csrfToken;
                                                axios.interceptors.request.use(request => {
                                                    request.headers[csrfTokenHeader] = csrfToken;
                                                    return request;
                                                });
                                            }

                                            // Global navigation
                                            const navItems = document.querySelectorAll('.nav-link');
                                            navItems.forEach(item => {
                                                item.addEventListener('click', function() {
                                                    document.querySelectorAll('a.active').forEach(activeLink => {
                                                        activeLink.classList.remove('active');
                                                    });
                                                    this.classList.add('active');
                                                });
                                            });

                                            // Global search
                                            const searchInput = document.getElementById('global-search');
                                            if (searchInput) {
                                                searchInput.addEventListener('keyup', function() {
                                                    // Perform search
                                                });
                                            }

                                            // Global modal
                                            const modalClose = document.querySelectorAll('.modal-close');
                                            modalClose.forEach(closeButton => {
                                                closeButton.addEventListener('click', function() {
                                                    const modal = closeButton.closest('.modal');
                                                    // Main JavaScript file for ON Service CRM
                                                    document.addEventListener('DOMContentLoaded', function() {
                                                        // CSRF token setup (meta tag may be absent in old templates)
                                                        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                                                        const csrfToken = csrfMeta ? csrfMeta.content : null;
                                                        const csrfTokenHeader = 'X-CSRF-Token';

                                                        // Global AJAX request setup (if axios is available)
                                                        if (typeof axios !== 'undefined' && csrfToken) {
                                                            axios.defaults.headers.common[csrfTokenHeader] = csrfToken;
                                                            axios.interceptors.request.use(request => {
                                                                request.headers[csrfTokenHeader] = csrfToken;
                                                                return request;
                                                            });
                                                        }

                                                        // Global navigation
                                                        const navItems = document.querySelectorAll('.nav-link');
                                                        navItems.forEach(item => {
                                                            item.addEventListener('click', function() {
                                                                document.querySelectorAll('a.active').forEach(activeLink => {
                                                                    activeLink.classList.remove('active');
                                                                });
                                                                this.classList.add('active');
                                                            });
                                                        });

                                                        // Global search
                                                        const searchInput = document.getElementById('global-search');
                                                        if (searchInput) {
                                                            searchInput.addEventListener('keyup', function() {
                                                                // Perform search
                                                            });
                                                        }

                                                        // Global modal
                                                        const modalClose = document.querySelectorAll('.modal-close');
                                                        modalClose.forEach(closeButton => {
                                                            closeButton.addEventListener('click', function() {
                                                                const modal = closeButton.closest('.modal');
                                                                // Main JavaScript file for ON Service CRM
                                                                document.addEventListener('DOMContentLoaded', function() {
                                                                    // CSRF token setup (meta tag may be absent in old templates)
                                                                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                                                                    const csrfToken = csrfMeta ? csrfMeta.content : null;
                                                                    const csrfTokenHeader = 'X-CSRF-Token';

                                                                    // Global AJAX request setup (if axios is available)
                                                                    if (typeof axios !== 'undefined' && csrfToken) {
                                                                        axios.defaults.headers.common[csrfTokenHeader] = csrfToken;
                                                                        axios.interceptors.request.use(request => {
                                                                            request.headers[csrfTokenHeader] = csrfToken;
                                                                            return request;
                                                                        });
                                                                    }

                                                                    // Global navigation
                                                                    const navItems = document.querySelectorAll('.nav-link');
                                                                    navItems.forEach(item => {
                                                                        item.addEventListener('click', function() {
                                                                            document.querySelectorAll('a.active').forEach(activeLink => {
                                                                                activeLink.classList.remove('active');
                                                                            });
                                                                            this.classList.add('active');
                                                                        });
                                                                    });

                                                                    // Global search
                                                                    const searchInput = document.getElementById('global-search');
                                                                    if (searchInput) {
                                                                        searchInput.addEventListener('keyup', function() {
                                                                            // Perform search
                                                                        });
                                                                    }

                                                                    // Global modal
                                                                    const modalClose = document.querySelectorAll('.modal-close');
                                                                    modalClose.forEach(closeButton => {
                                                                        closeButton.addEventListener('click', function() {
                                                                            const modal = closeButton.closest('.modal');
                                                                            if (modal) modal.style.display = 'none';
                                                                        });
                                                                    });

                                                                    // Global print
                                                                    const printButtons = document.querySelectorAll('.btn-print');
                                                                    printButtons.forEach(printButton => {
                                                                        printButton.addEventListener('click', function() {
                                                                            const preview = printButton.closest('.modal-preview');
                                                                            if (preview) {
                                                                                const printContents = preview.innerHTML;
                                                                                const win = window.open('', '', 'width=600,height=600');
                                                                                win.document.write('<!DOCTYPE html><html><head><title>Print</title></head><body>' + printContents + '</body></html>');
                                                                                win.document.close();
                                                                            }
                                                                        });
                                                                    });
                                                                });
                                                                modal.style.display = 'none';
                                                            });
                                                        });

                                                        // Global print
                                                        const printButtons = document.querySelectorAll('.btn-print');
                                                        printButtons.forEach(printButton => {
                                                            printButton.addEventListener('click', function() {
                                                                const preview = printButton.closest('.modal-preview');
                                                                if (preview) {
                                                                    const printContents = preview.innerHTML;
                                                                    const win = window.open('', '', 'width=600,height=600');
                                                                    win.document.write('<!DOCTYPE html><html><head><title>Print</title></head><body>' + printContents + '</body></html>');
                                                                    win.document.close();
                                                                }
                                                            });
                                                        });
                                                    });
                                                    modal.style.display = 'none';
                                                });
                                            });

                                            // Global print
                                            const printButtons = document.querySelectorAll('.btn-print');
                                            printButtons.forEach(printButton => {
                                                printButton.addEventListener('click', function() {
                                                    const preview = printButton.closest('.modal-preview');
                                                    if (preview) {
                                                        const printContents = preview.innerHTML;
                                                        const win = window.open('', '', 'width=600,height=600');
                                                        win.document.write('<!DOCTYPE html><html><head><title>Print</title></head><body>' + printContents + '</body></html>');
                                                        win.document.close();
                                                    }
                                                });
                                            });
                                        });
                                        modal.style.display = 'none';
                                    });

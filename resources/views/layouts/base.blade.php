<!-- Sidebar Section -->
<div class="sidebar">
    <div class="brand">
        <div class="icon"><i class="fas fa-map-marker-alt" style="color: #FF5722; font-size: 24px;"></i></div>
        <div class="logo-text">ProxymTracking</div>
    </div>

    <ul class="sidebar-nav">
        <li>
            <a href="{{ route('dashboard') }}" class="{{ request()->is('dashboard') ? 'active' : '' }}">
                <span class="icon"><i class="fas fa-tachometer-alt"></i></span>
                <span class="title">Dashboard</span>
            </a>
        </li>
        <li>
            <a href="{{ route('tracking.users') }}" class="{{ request()->is('tracking_users') ? 'active' : '' }}">
                <span class="icon"><i class="fas fa-users"></i></span>
                <span class="title">Users</span>
            </a>
        </li>
        <li>
            <a href="{{ route('tracking.vehicles') }}" class="{{ request()->is('tracking.vehicles') ? 'active' : '' }}">
                <span class="icon"><i class="fas fa-car"></i></span>
                <span class="title">Vehicles</span>
            </a>
        </li>
        <li>
            <a href="{{ route('association.index') }}" class="{{ request()->is('association*') ? 'active' : '' }}">
                <span class="icon"><i class="fas fa-link"></i></span>
                <span class="title">Association</span>
            </a>
        </li>
        <li>
            <a href="{{ route('alerts.index') }}" class="{{ request()->routeIs('alerts.index') ? 'active' : '' }}">
                <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
                <span class="title">Alerts</span>
            </a>
        </li>
     
      
    
    </ul>
</div>

<!-- Toggle Button for Mobile -->
<div class="toggle-sidebar">
    <i class="fas fa-bars"></i>
</div>


<div class="main-content">
    @yield('content')
</div>

<!-- CSS Styles for Sidebar -->
<style>
    :root {
        --primary: #FF5722;
        --primary-light: #FF7D51;
        --primary-dark: #E64A19;
        --secondary: #212121;
        --secondary-light: #484848;
        --secondary-dark: #000000;
        --white: #FFFFFF;
        --light-gray: #F5F5F5;
        --mid-gray: #E0E0E0;
        --dark-gray: #757575;
    }

    /* Sidebar */
    .sidebar {
        position: fixed;
        width: 260px;
        height: 100vh;
        background-color: var(--secondary);
        transition: 0.3s ease;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar.collapsed {
        width: 80px;
    }

    .sidebar .brand {
        display: flex;
        align-items: center;
        padding: 24px 20px;
        color: var(--white);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar .brand .icon {
        min-width: 40px;
        display: flex;
        justify-content: center;
    }

    .sidebar .brand .logo-text {
        font-size: 20px;
        font-weight: 700;
        white-space: nowrap;
        opacity: 1;
        transition: 0.3s ease;
        letter-spacing: 0.5px;
    }

    .sidebar.collapsed .brand .logo-text {
        opacity: 0;
        pointer-events: none;
    }

    .sidebar-nav {
        padding: 10px 0;
        list-style: none;
        margin: 0;
    }

    .sidebar-nav li {
        position: relative;
        margin: 8px 12px;
        list-style: none;
    }

    .sidebar-nav li a {
        display: flex;
        align-items: center;
        padding: 14px 16px;
        color: var(--mid-gray);
        text-decoration: none;
        transition: all 0.3s ease;
        border-radius: 8px;
        border-left: 3px solid transparent;
    }

    .sidebar-nav li a:hover,
    .sidebar-nav li a.active {
        color: var(--primary);
        background: rgba(255, 87, 34, 0.08);
        border-left: 3px solid var(--primary);
    }

    .sidebar-nav li a .icon {
        font-size: 20px;
        min-width: 40px;
        display: flex;
        justify-content: center;
    }

    .sidebar-nav li a .title {
        white-space: nowrap;
        opacity: 1;
        transition: 0.3s ease;
        font-weight: 500;
    }

    .sidebar.collapsed .sidebar-nav li a .title {
        opacity: 0;
        pointer-events: none;
    }

    /* Toggle Button */
    .toggle-sidebar {
        position: fixed;
        top: 20px;
        left: 20px;
        width: 40px;
        height: 40px;
        background: var(--white);
        border-radius: 50%;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        z-index: 1001;
        font-size: 18px;
        color: var(--secondary);
        transition: 0.3s ease;
    }

    .toggle-sidebar:hover {
        background: var(--primary);
        color: var(--white);
    }

    /* Main Content Adjustment */
    .main-content {
        margin-left: 260px;
        transition: 0.3s ease;
    }

    .main-content.expanded {
        margin-left: 80px;
    }

    /* Responsive */
    @media (max-width: 991px) {
        .sidebar {
            width: 80px;
        }

        .sidebar .brand .logo-text {
            opacity: 0;
            pointer-events: none;
        }

        .sidebar .sidebar-nav li a .title {
            opacity: 0;
            pointer-events: none;
        }

        .main-content {
            margin-left: 80px;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            left: -80px;
        }

        .sidebar.active {
            left: 0;
            width: 260px;
        }

        .sidebar.active .brand .logo-text,
        .sidebar.active .sidebar-nav li a .title {
            opacity: 1;
            pointer-events: auto;
        }

        .main-content {
            margin-left: 0;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .overlay.active {
            display: block;
        }
    }

    /* Transitions and Animations */
    .sidebar-nav li a {
        position: relative;
        overflow: hidden;
    }

    .sidebar-nav li a::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 87, 34, 0.1), transparent);
        transition: 0.5s;
    }

    .sidebar-nav li a:hover::before {
        left: 100%;
    }
</style>

<!-- JavaScript for Sidebar Toggle -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle sidebar
        const toggleBtn = document.querySelector('.toggle-sidebar');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');

        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('expanded');

            // Create overlay for mobile
            let overlay = document.querySelector('.overlay');
            if (!overlay && window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                overlay = document.createElement('div');
                overlay.className = 'overlay active';
                document.body.appendChild(overlay);

                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    setTimeout(function() {
                        overlay.remove();
                    }, 300);
                });
            } else if (overlay) {
                overlay.classList.remove('active');
                setTimeout(function() {
                    overlay.remove();
                }, 300);
            }
        });

        // Mobile responsive
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }

        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            } else if (window.innerWidth > 768 && window.innerWidth <= 991) {
                sidebar.classList.add('collapsed');
                sidebar.classList.remove('active');
                mainContent.classList.add('expanded');

                let overlay = document.querySelector('.overlay');
                if (overlay) {
                    overlay.remove();
                }
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            }
        });
    });
</script>

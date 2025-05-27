<div class="nk-sidebar nk-sidebar-fixed is-white " data-content="sidebarMenu">
    <div class="nk-sidebar-element nk-sidebar-head">
        <div class="nk-menu-trigger">
            <a href="#" class="nk-nav-toggle nk-quick-nav-icon d-xl-none" data-target="sidebarMenu"><em
                    class="icon ni ni-arrow-left"></em></a>
            <a href="#" class="nk-nav-compact nk-quick-nav-icon d-none d-xl-inline-flex"
                data-target="sidebarMenu"><em class="icon ni ni-menu"></em></a>
        </div>
        <div class="nk-sidebar-brand">
            <a href="{{ route('admin.admin-dashboard') }}" class="logo-link nk-sidebar-logo">
                <img class="logo-light logo-img" src="{{ asset('theme/images/logo.png') }}"
                    srcset="{{ asset('theme/images/logo.png') }} 2x" alt="logo">
                <img class="logo-dark logo-img" src="{{ asset('theme/images/logo.png') }}"
                    srcset="{{ asset('theme/images/logo.png') }} 2x" alt="logo-dark">
            </a>
        </div>
    </div><!-- .nk-sidebar-element -->
    <div class="nk-sidebar-element nk-sidebar-body">
        <div class="nk-sidebar-content">
            <div class="nk-sidebar-menu" data-simplebar>
                <ul class="nk-menu">
                    <li class="nk-menu-item">
                        <a href="{{ route('admin.admin-dashboard') }}" class="nk-menu-link">
                            <span class="nk-menu-icon"><em class="icon ni ni-home"></em></span>
                            <span class="nk-menu-text">Dashboard</span>
                        </a>
                    </li><!-- .nk-menu-item -->
                    <li class="nk-menu-heading">
                        <h6 class="overline-title text-primary-alt">Management Area</h6>
                    </li><!-- .nk-menu-heading -->
                    <li class="nk-menu-item">
                        <a href="{{ route('admin.admin-profile') }}" class="nk-menu-link">
                            <span class="nk-menu-icon"><em class="icon ni ni-user"></em></span>
                            <span class="nk-menu-text">Profile</span>
                        </a>
                    </li><!-- .nk-menu-item -->
                    @if (Auth::user()->user_type != 'employee')
                        <li class="nk-menu-item">
                            <a href="{{ route('admin.admin-departments') }}" class="nk-menu-link">
                                <span class="nk-menu-icon"><em class="icon ni ni-building"></em></span>
                                <span class="nk-menu-text">Department</span>
                            </a>
                        </li><!-- .nk-menu-item -->
                        <li class="nk-menu-item"></li><!-- .nk-menu-item -->
                        <li class="nk-menu-item">
                            <a href="{{ route('admin.admin-holidays') }}" class="nk-menu-link">
                                <span class="nk-menu-icon"><em class="icon ni ni-briefcase"></em></span>
                                <span class="nk-menu-text">Holidays</span>
                            </a>
                        </li><!-- .nk-menu-item -->
                        <li class="nk-menu-item">
                            <a href="{{ url('admin/employees') }}" class="nk-menu-link">
                                <span class="nk-menu-icon"><em class="icon ni ni-list"></em></span>
                                <span class="nk-menu-text">Employees List</span>
                            </a>
                        </li>
                        <li class="nk-menu-item">
                            <a href="{{ url('admin/attendance') }}" class="nk-menu-link">
                                <span class="nk-menu-icon"><em class="icon ni ni-calendar-check"></em></span>
                                <span class="nk-menu-text">Attendance</span>
                            </a>
                        </li>
                        <li class="nk-menu-item">
                            <a href="{{ url('admin/shifts') }}" class="nk-menu-link">
                                <span class="nk-menu-icon"><em class="icon ni ni-briefcase"></em></span>
                                <span class="nk-menu-text">Shifts</span>
                            </a>
                        </li>
                    @endif
                    <li class="nk-menu-heading">
                        <h6 class="overline-title text-primary-alt">Settings</h6>
                    </li><!-- .nk-menu-heading -->
                     <li class="nk-menu-item">
                        <a href="{{ url('admin/users') }}" class="nk-menu-link">
                            <span class="nk-menu-icon"><em class="icon ni ni-users"></em></span>
                            <span class="nk-menu-text">Users</span>
                        </a>
                    </li><!-- .nk-menu-item -->
                    @if(auth()->user()->user_type == 'admin')
                     <li class="nk-menu-item">
                        <a href="{{ url('admin/logs') }}" class="nk-menu-link">
                            <span class="nk-menu-icon"><em class="icon ni ni-history"></em></span>
                            <span class="nk-menu-text">Logs</span>
                        </a>
                    </li><!-- .nk-menu-item -->
                    @endif
                    <li class="nk-menu-item">
                        <a href="{{ route('admin.admin-site') }}" class="nk-menu-link">
                            <span class="nk-menu-icon"><em class="icon ni ni-setting"></em></span>
                            <span class="nk-menu-text">Site Settings</span>
                        </a>
                    </li><!-- .nk-menu-item -->
                </ul><!-- .nk-menu -->
            </div><!-- .nk-sidebar-menu -->
        </div><!-- .nk-sidebar-content -->
    </div><!-- .nk-sidebar-element -->
</div>

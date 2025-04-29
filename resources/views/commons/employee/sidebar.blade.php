<div class="nk-sidebar nk-sidebar-fixed is-white " data-content="sidebarMenu">
    <div class="nk-sidebar-element nk-sidebar-head">
        <div class="nk-menu-trigger">
            <a href="#" class="nk-nav-toggle nk-quick-nav-icon d-xl-none" data-target="sidebarMenu"><em
                    class="icon ni ni-arrow-left"></em></a>
            <a href="#" class="nk-nav-compact nk-quick-nav-icon d-none d-xl-inline-flex"
                data-target="sidebarMenu"><em class="icon ni ni-menu"></em></a>
        </div>
        <div class="nk-sidebar-brand">
            <a href="{{ route('employee.employee-dashboard') }}" class="logo-link nk-sidebar-logo">
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
                        <a href="{{ route('employee.employee-dashboard') }}" class="nk-menu-link">
                            <span class="nk-menu-icon"><em class="icon ni ni-home"></em></span>
                            <span class="nk-menu-text">Dashboard</span>
                        </a>
                    </li><!-- .nk-menu-item -->
                    <li class="nk-menu-heading">
                        <h6 class="overline-title text-primary-alt">Management Area</h6>
                    </li><!-- .nk-menu-heading -->
                    <li class="nk-menu-item">
                        <a href="{{ route('employee.employee-profile') }}" class="nk-menu-link">
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
                        <li class="nk-menu-item">
                            <a href="{{ route('admin.admin-leavetypes') }}" class="nk-menu-link">
                                <span class="nk-menu-icon"><em class="icon ni ni-todo-fill"></em></span>
                                <span class="nk-menu-text">Leave Type</span>
                            </a>
                        </li><!-- .nk-menu-item -->
                        <li class="nk-menu-item"></li><!-- .nk-menu-item -->

                        <li class="nk-menu-item">
                            <a href="{{ route('admin.admin-employees') }}" class="nk-menu-link">
                                <span class="nk-menu-icon"><em class="icon ni ni-list"></em></span>
                                <span class="nk-menu-text">Employees List</span>
                            </a>
                        </li>
                    @endif
                    <li class="nk-menu-item">
                        <a href="{{ route('employee.employee-holidays') }}" class="nk-menu-link">
                            <span class="nk-menu-icon"><em class="icon ni ni-calendar"></em></span>
                            <span class="nk-menu-text">Holidays</span>
                        </a>
                    </li><!-- .nk-menu-item -->
                    <li class="nk-menu-item">
                        <a href="{{ route('fullcalender') }}" class="nk-menu-link">
                            <span class="nk-menu-icon"><em class="icon ni ni-calendar"></em></span>
                            <span class="nk-menu-text">Planner</span>
                        </a>
                    </li><!-- .nk-menu-item -->
                    <li class="nk-menu-item has-sub">
                        <a href="#" class="nk-menu-link nk-menu-toggle">
                            <span class="nk-menu-icon"><em class="icon ni ni-tile-thumb"></em></span>
                            <span class="nk-menu-text">Leave</span>
                        </a>
                        <ul class="nk-menu-sub">
                            <li class="nk-menu-item">
                                <a href="{{ route('employee.employee-apply-leave') }}" class="nk-menu-link"><span
                                        class="nk-menu-text">Apply</span></a>
                            </li>
                            <li class="nk-menu-item">
                                <a href="{{ route('employee.employee-approved-leave') }}" class="nk-menu-link"><span
                                        class="nk-menu-text">Approved</span></a>
                            </li>
                            @if (Auth::user()->user_type != 'employee')
                                <li class="nk-menu-item">
                                    <a href="{{ route('employee.employee-manage-leave') }}" class="nk-menu-link"><span
                                            class="nk-menu-text">Manage</span></a>
                                </li>
                            @endif
                            <li class="nk-menu-item">
                                <a href="{{ route('employee.employee-rejected-leave') }}" class="nk-menu-link"><span
                                        class="nk-menu-text">Rejected</span></a>
                            </li>
                        </ul><!-- .nk-menu-sub -->
                    </li><!-- .nk-menu-item -->
                    @if (Auth::user()->user_type == 'general_manager')
                        <li class="nk-menu-item">
                            <a href="{{ route('admin.admin-site') }}" class="nk-menu-link">
                                <span class="nk-menu-icon"><em class="icon ni ni-circle"></em></span>
                                <span class="nk-menu-text">Companies</span>
                            </a>
                        </li><!-- .nk-menu-item -->
                        <li class="nk-menu-heading">
                            <h6 class="overline-title text-primary-alt">Settings</h6>
                        </li><!-- .nk-menu-heading -->
                        <li class="nk-menu-item">
                            <a href="#" class="nk-menu-link">
                                <span class="nk-menu-icon"><em class="icon ni ni-setting"></em></span>
                                <span class="nk-menu-text">Site Settings</span>
                            </a>
                        </li><!-- .nk-menu-item -->
                    @else
                        <li></li>
                    @endif
                </ul><!-- .nk-menu -->
            </div><!-- .nk-sidebar-menu -->
        </div><!-- .nk-sidebar-content -->
    </div><!-- .nk-sidebar-element -->
</div>

<!DOCTYPE html>
<html lang="zxx" class="js">

<head>
    <base href="../">
    <meta charset="utf-8">
    <meta name="author" content="LS">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="description" content="Workforce Management Platform">
    <!-- Fav Icon  -->
    <link rel="shortcut icon" href="{{ asset('theme/images/IMG-20220401-WA0010.jpg') }}">
    <!-- CSRF LARAVEL -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Page Title  -->
    <title>{{ $title }} | LS </title>
    <!-- StyleSheets  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/izitoast/1.4.0/css/iziToast.min.css">
    <link rel="stylesheet" href="{{ asset('theme/assets/css/dashlite.css?ver=2.9.0') }}">
    <link id="skin-default" rel="stylesheet" href="{{ asset('theme/assets/css/skins/theme-red.css?ver=2.9.0') }}">
    @yield('header')

    <!-- Livewire Styles -->
    @livewireStyles
    @stack('styles')

    <style>
        .pac-container {
            z-index: 1051 !important;
        }
    </style>
</head>

<body class="nk-body bg-lighter npc-general has-sidebar {{ Auth::user()->isDark ? 'dark-mode' : '' }}">
    <div class="nk-app-root">
        <!-- main @s -->
        <div class="nk-main ">
            <!-- sidebar @s -->
            @include('commons.general.sidebar')
            <!-- sidebar @e -->
            <!-- wrap @s -->
            <div class="nk-wrap ">
                <!-- main header @s -->
                <div class="nk-header nk-header-fixed is-light">
                    <div class="container-fluid">
                        <div class="nk-header-wrap">
                            <div class="nk-menu-trigger d-xl-none ml-n1">
                                <a href="#" class="nk-nav-toggle nk-quick-nav-icon" data-target="sidebarMenu"><em
                                        class="icon ni ni-menu"></em></a>
                            </div>
                            <div class="nk-header-brand d-xl-none">
                                <a href="#" class="logo-link">
                                    <img class="logo-light logo-img" src="{{ asset('theme/images/logo.png') }}"
                                        srcset="{{ asset('theme/images/logo.png') }} 2x" alt="logo">
                                    <img class="logo-dark logo-img" src="{{ asset('theme/images/logo.png') }}"
                                        srcset="{{ asset('theme/images/logo.png') }} 2x" alt="logo-dark">
                                </a>
                            </div><!-- .nk-header-brand -->
                            <div class="nk-header-news d-none d-xl-block">
                                <div class="nk-news-list">
                                    <a class="nk-news-item" href="#">
                                        <div class="nk-news-icon">
                                            <em class="icon ni ni-card-view"></em>
                                        </div>
                                        {{-- <div class="nk-news-text">
                                            <p>Need support? Access knowledgebase area <span>Support</span></p>
                                            <em class="icon ni ni-external"></em>
                                        </div> --}}
                                    </a>
                                </div>
                            </div><!-- .nk-header-news -->
                            <div class="nk-header-tools">
                                <ul class="nk-quick-nav">
                                    <li class="dropdown notification-dropdown mr-n1">
                                        <a href="#" class="dropdown-toggle nk-quick-nav-icon"
                                            data-toggle="dropdown">
                                            <div class="icon-status icon-status-info"><em class="icon ni ni-bell"></em>
                                            </div>
                                        </a>
                                        <div
                                            class="dropdown-menu dropdown-menu-xl dropdown-menu-right dropdown-menu-s1">
                                            <div class="dropdown-head">
                                                <span class="sub-title nk-dropdown-title">Notifications</span>
                                                <a href="#">Mark All as Read</a>
                                            </div>
                                            <div class="dropdown-body">
                                                <div class="nk-notification">
                                                    <div class="nk-notification-item dropdown-inner">
                                                        <div class="nk-notification-icon">
                                                            <em
                                                                class="icon icon-circle bg-warning-dim ni ni-curve-down-right"></em>
                                                        </div>
                                                        <div class="nk-notification-content">
                                                            <div class="nk-notification-text">No new notifications
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div><!-- .nk-notification -->
                                            </div><!-- .nk-dropdown-body -->
                                            <div class="dropdown-foot center">
                                                <a href="#">View All</a>
                                            </div>
                                        </div>
                                    </li><!-- .dropdown -->
                                    <li class="dropdown user-dropdown">
                                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                            <div class="user-toggle">
                                                <div class="user-avatar sm">
                                                    <img src="{{ Auth::user()->email }}" alt="">
                                                </div>
                                                <div class="user-info d-none d-md-block">
                                                    <div class="user-status">
                                                        <div class="user-name dropdown-indicator">
                                                            {{ Auth::user()->name }}</div>
                                                    </div>
                                                </div>
                                        </a>
                                        <div
                                            class="dropdown-menu dropdown-menu-md dropdown-menu-right dropdown-menu-s1">
                                            <div class="dropdown-inner user-card-wrap bg-lighter d-none d-md-block">
                                                <div class="user-card">
                                                    <div class="user-avatar">
                                                        <img src="{{ Auth::user()->email }}" alt="">
                                                    </div>
                                                    <div class="user-info">
                                                        <span class="lead-text">{{ Auth::user()->name }}</span>
                                                        <span class="sub-text">{{ Auth::user()->email }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="dropdown-inner">
                                                <ul class="link-list">
                                                    <li><a href="{{ route('activity') }}">
                                                            <em class="icon ni ni-activity-alt">
                                                            </em><span>Login
                                                                Activity</span></a>
                                                    </li>
                                                    <li>
                                                        @livewire('toggle-dark-mode', [
                                                            'model' => Auth::user(),
                                                            'field' => 'isDark',
                                                        ])
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="dropdown-inner">
                                                <ul class="link-list">
                                                    <li>
                                                        <form method="POST" action="{{ route('logout') }}">
                                                            @csrf <!-- {{ csrf_field() }} -->
                                                            <button class="btn btn-white"><em
                                                                    class="icon ni ni-signout"></em><span>Sign
                                                                    out</span></button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </li><!-- .dropdown -->
                                </ul><!-- .nk-quick-nav -->
                            </div><!-- .nk-header-tools -->
                        </div><!-- .nk-header-wrap -->
                    </div><!-- .container-fliud -->
                </div>
                <!-- main header @e -->
                <!-- content @s -->
                @yield('content')
                <!-- content @e -->
                <!-- footer @s -->
                <div class="nk-footer">
                    <div class="container-fluid">
                        <div class="nk-footer-wrap">
                            <div class="nk-footer-copyright"> &copy; <?php echo date('Y'); ?>
                                {{ implode('', $company_name) }}
                            </div>
                            <div class="nk-footer-links">
                                <ul class="nav nav-sm">
                                    <li class="nav-item"><a class="nav-link" href="#">Terms</a></li>
                                    <li class="nav-item"><a class="nav-link" href="#">Privacy</a></li>
                                    <li class="nav-item"><a class="nav-link" href="#">Help</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- footer @e -->
            </div>
            <!-- wrap @e -->
        </div>
        <!-- main @e -->
    </div>
    <!-- app-root @e -->
    <!-- JavaScript -->
    <script src="{{ asset('theme/assets/js/bundle.js?ver=2.9.0') }}"></script>
    <script src="{{ asset('theme/assets/js/scripts.js?ver=2.9.0') }}"></script>
    {{-- <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDVASYM1HKkCHxWUaaeKbq6BEX5lgGBZLE"></script> --}}

    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places&callback=initialize"
        async defer></script>

    @if (request()->routeIs(['org.site-overview', 'org.dashboard']))
        <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDVASYM1HKkCHxWUaaeKbq6BEX5lgGBZLE"></script>
    @endif



    <!-- Livewire Scripts -->
    @livewireScripts
    <!--Livewire -->
    {{-- Toastr alerts --}}
    <script>
        window.addEventListener('success', event => {
            NioApp.Toast(event.detail.message, 'success', {
                position: 'top-right'
            });
        });

        window.addEventListener('info', event => {
            NioApp.Toast(event.detail.message, 'info', {
                position: 'top-right'
            });
        });

        window.addEventListener('warning', event => {
            NioApp.Toast(event.detail.message, 'warning', {
                position: 'top-right'
            });
        });
    </script>

    <script type="text/javascript">
        window.livewire.on('userStore', () => {
            $('#addModal').modal('hide');
        });
    </script>

    {{-- Sweet alerts --}}
    <script>
        window.addEventListener('swal:success', event => {
            Swal.fire(event.detail.title, event.detail.message, event.detail.type);
            e.preventDefault();
        });
    </script>
    <script>
        window.addEventListener('swal:confirm', event => {
            Swal.fire({
                title: event.detail.title,
                text: event.detail.message,
                icon: event.detail.type,
                showCancelButton: true,
                confirmButtonText: event.detail.confirmButtonText
            }).then(function(result) {
                if (result.value) {
                    window.livewire.emit('delete', event.detail.id);
                }
            });
            e.preventDefault();
        });
    </script>

    {{-- Normal alerts --}}
    <script>
        @if (Session::has('success'))
            NioApp.Toast('<h5>Successful!</h5>{!! session('success') !!}', 'success', {
                position: 'top-right'
            })
        @endif

        @if (Session::has('warning'))
            NioApp.Toast('<h5>Warning!</h5>{!! session('warning') !!}', 'warning', {
                position: 'top-right'
            })
        @endif

        @if (Session::has('error'))
            NioApp.Toast('<h5>Error!</h5>{!! session('error') !!}', 'error', {
                position: 'top-right'
            })
        @endif
    </script>
    <script>
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                NioApp.Toast('{{ $error }}', 'error', {
                    position: 'top-right'
                })
            @endforeach
        @endif
    </script>



<script src="https://js.pusher.com/7.2/pusher.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/izitoast/1.4.0/js/iziToast.min.js"></script>
<script>
    // Enable pusher logging - don't include this in production
    // Pusher.logToConsole = true;
    var pusher = new Pusher('31e3b02ae0fa6d5f5a00', {
        cluster: 'us2'
    });
    var hours = new Date().getHours();
    var username = <?= json_encode(Auth::user()->name) ?>;

    if (hours < 12) {
        var channel = pusher.subscribe('apply');
        channel.bind('leave', (data) => {
            iziToast.show({
                title: ` Goodmorning ${username} ! `,
                message: data.message,
                theme: 'dark',
                color: 'green'
            });
        });
    } else if (hours == 12) {
        var channel = pusher.subscribe('apply');
        var name = <?= json_encode(Auth::user()->name) ?>;
        channel.bind('leave', (data) => {
            iziToast.show({
                title: ` Howdy ${username} ! `,
                message: data.message,
                theme: 'dark',
                color: 'green'
            });
        });
    } else if (hours > 13) {
        var channel = pusher.subscribe('apply');
        var name = <?= json_encode(Auth::user()->name) ?>;
        channel.bind('leave', (data) => {
            iziToast.show({
                title: ` Goodafternoon ${username} ! `,
                message: data.message,
                theme: 'dark',
                color: 'green'
            });
        });
    } else {
        var channel = pusher.subscribe('apply');
        var name = <?= json_encode(Auth::user()->name) ?>;
        channel.bind('leave', (data) => {
            iziToast.show({
                title: ` Goodevening ${username} ! `,
                message: data.message,
                theme: 'dark',
                color: 'green'
            });
        });
    }
</script>

    @yield('scripts')
    @stack('scripts')
</body>

</html>

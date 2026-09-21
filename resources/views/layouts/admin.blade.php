<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', config('app.name'))</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Ionicons -->
    <!-- <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css"> -->
    <!-- overlayScrollbars -->
    <!-- <link rel="stylesheet" href="{{ asset('css/app.css') }}"> -->
    <!-- Installable / offline-capable POS -->
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#343a40">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/srsfavicon.svg') }}">


    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @yield('css')
    <script>
        window.APP = <?php echo json_encode([
                            'currency_symbol' => config('settings.currency_symbol'),
                            'warning_quantity' => config('settings.warning_quantity'),
                            'enable_discount' => (bool) config('settings.enable_discount'),
                            'enable_tax' => (bool) config('settings.enable_tax'),
                            'tax_name' => config('settings.tax_name') ?: 'Tax',
                            'tax_rate' => (float) config('settings.tax_rate', 0),
                            'user_id' => auth()->id(),
                            'store_id' => auth()->user()->store_id,
                            'cashier' => auth()->user()->getFullname()
                        ]) ?>
    </script>
</head>

<body class="hold-transition sidebar-mini sidebar-no-expand">
    <!-- Site wrapper -->
    <div class="wrapper">

        @include('layouts.partials.navbar')
        @include('layouts.partials.sidebar')
        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1>@yield('content-header')</h1>
                        </div>
                        <div class="col-sm-6 text-right">
                            @yield('content-actions')
                        </div><!-- /.col -->
                    </div>
                </div><!-- /.container-fluid -->
            </section>

            <!-- Main content -->
            <section class="content">
                @include('layouts.partials.alert.success')
                @include('layouts.partials.alert.error')
                @yield('content')
            </section>

        </div>
        <!-- /.content-wrapper -->

        @include('layouts.partials.footer')

        <!-- Control Sidebar -->
        <aside class="control-sidebar control-sidebar-dark">
            <!-- Control sidebar content goes here -->
        </aside>
        <!-- /.control-sidebar -->
    </div>
    <!-- ./wrapper -->
    <!-- <script src="{{ asset('js/app.js') }}"></script> -->


    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () {});
                // once the worker controls the page, re-request what this page already loaded so it is stored for offline use
                navigator.serviceWorker.ready.then(function () {
                    setTimeout(function () {
                        performance.getEntriesByType('resource').forEach(function (r) {
                            if (r.name.indexOf(location.origin) === 0 && /\.(js|css|woff2?|png|jpe?g|svg|gif|webp|ico)(\?|$)/i.test(r.name)) {
                                fetch(r.name).catch(function () {});
                            }
                        });
                    }, 1500);
                });
            });
        }
    </script>
    @yield('js')
    @yield('model')
</body>

</html>
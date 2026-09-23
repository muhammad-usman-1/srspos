<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="{{route('home')}}" class="brand-link" style="background:#343a40;display:flex;align-items:center;justify-content:center;padding:10px 10px;height:70px;overflow:hidden">
        <img src="{{ app_logo_url() }}" alt="{{ config('app.name') }}" style="display:block;margin:0 auto;max-height:70px;max-width:100%;width:auto;height:auto;object-fit:contain;object-position:center">
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                @if(auth()->user()->isSuperAdmin())
                <li class="nav-header">{{ __('Owner') }}</li>
                <li class="nav-item">
                    <a href="{{ route('superadmin.stores.index') }}" class="nav-link {{ request()->routeIs('superadmin.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-store"></i>
                        <p>{{ __('Stores') }}</p>
                    </a>
                </li>
                @else
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="{{route('home')}}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>{{ __('dashboard.title') }}</p>
                    </a>
                </li>

                <!-- Products: the catalogue itself, shown in both stock modes -->
                <li class="nav-item">
                    <a href="{{ route('products.index') }}" class="nav-link {{ activeSegment('products') }}">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>{{ __('product.title') }}</p>
                    </a>
                </li>

                <!-- Stock Management: quantity, low-stock alerts, ledger — only for stores that track it -->
                @if(store_tracks_stock())
                <li class="nav-item">
                    <a href="{{ route('stock.index') }}" class="nav-link {{ activeSegment('stock') }}">
                        <i class="nav-icon fas fa-boxes"></i>
                        <p>{{ __('Stock Management') }}</p>
                    </a>
                </li>
                @endif

                <li class="nav-header">{{ __('Sales') }}</li>
                <!-- POS Cart -->
                <li class="nav-item">
                    <a href="{{ route('cart.index') }}" class="nav-link {{ activeSegment('cart') }}">
                        <i class="nav-icon fas fa-cart-plus"></i>
                        <p>{{ __('POS') }}</p>
                    </a>
                </li>

                <!-- Orders -->
                <li class="nav-item">
                    <a href="{{ route('orders.index') }}" class="nav-link {{ activeSegment('orders') }}">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <p>{{ __('Order List') }}</p>
                    </a>
                </li>

                <!-- Customers -->
                <li class="nav-item">
                    <a href="{{ route('customers.index') }}" class="nav-link {{ activeSegment('customers') }}">
                        <i class="nav-icon fas fa-users"></i>
                        <p>{{ __('customer.title') }}</p>
                    </a>
                </li>

                @if(store_tracks_stock())
                <li class="nav-header">{{ __('Purchases') }}</li>
                <!-- Purchases (Dropdown) -->
                <li class="nav-item {{ request()->routeIs('purchases.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ activeSegment('purchases') }}">
                        <i class="nav-icon fas fa-box"></i>
                        <p>
                            {{ __('Purchases') }}
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('purchases.create') }}" class="nav-link {{ request()->routeIs('purchases.create') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>{{ __('New Purchase') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('purchases.index') }}" class="nav-link {{ request()->routeIs('purchases.index') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>{{ __('All Purchases') }}</p>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif
                <!-- Suppliers -->
                <li class="nav-item">
                    <a href="{{ route('suppliers.index') }}" class="nav-link {{ activeSegment('suppliers') }}">
                        <i class="nav-icon fas fa-truck"></i>
                        <p>{{ __('Supplier') }}</p>
                    </a>
                </li>

                <li class="nav-header">{{ __('Reports') }}</li>
                <li class="nav-item">
                    <a href="{{ route('reports.index') }}" class="nav-link {{ activeSegment('reports') }}">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <p>{{ store_tracks_stock() ? __('Sales & Profit') : __('Sales Report') }}</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('activity.index') }}" class="nav-link {{ activeSegment('activity') }}">
                        <i class="nav-icon fas fa-history"></i>
                        <p>{{ __('Activity Log') }}</p>
                    </a>
                </li>

                <li class="nav-header">{{ __('Extra') }}</li>
                <!-- Settings -->
                <li class="nav-item">
                    <a href="{{ route('settings.index') }}" class="nav-link {{ activeSegment('settings') }}">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>{{ __('settings.title') }}</p>
                    </a>
                </li>

                @endif

                <!-- Logout -->
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>{{ __('common.Logout') }}</p>
                    </a>
                    <form action="{{route('logout')}}" method="POST" id="logout-form" class="d-none">
                        @csrf
                    </form>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>

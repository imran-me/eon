<style>
    #sidebar {
        background: linear-gradient(180deg, #0f172a 0%, #111827 45%, #0b1220 100%);
        border-right: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12), 0 18px 45px rgba(2, 6, 23, 0.45);
    }

    #sidebar::-webkit-scrollbar {
        width: 6px;
    }

    #sidebar::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.4);
        border-radius: 9999px;
    }

    #sidebar a.sidebar-item,
    #sidebar button.sidebar-item,
    #sidebar .sidebar-item > a,
    #sidebar .sidebar-item > button {
        border-radius: 10px;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        color: #e2e8f0;
        font-weight: 500;
        font-size: 14px;
    }

    #sidebar a.sidebar-item:hover,
    #sidebar button.sidebar-item:hover,
    #sidebar .sidebar-item > a:hover,
    #sidebar .sidebar-item > button:hover {
        background: rgba(59, 130, 246, 0.14);
        border-color: rgba(59, 130, 246, 0.22);
        color: #ffffff;
        transform: translateX(2px);
    }

    #sidebar a.sidebar-item.active,
    #sidebar button.sidebar-item.active,
    #sidebar .sidebar-item > a.active,
    #sidebar .sidebar-item > button.active,
    #sidebar .submenu-item.active {
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.24), rgba(59, 130, 246, 0.06));
        border: 1px solid rgba(96, 165, 250, 0.34);
        color: #ffffff;
    }

    #sidebar .submenu {
        margin-top: 6px;
        margin-left: 8px;
        padding-left: 8px;
        border-left: 1px solid rgba(148, 163, 184, 0.24);
    }

    #sidebar .submenu-item {
        border-radius: 8px;
        color: #bfdbfe;
        transition: all 0.2s ease;
        font-size: 13px;
    }

    #sidebar .submenu-item:hover {
        background: rgba(96, 165, 250, 0.12);
        color: #ffffff;
    }

    #sidebar h3 {
        letter-spacing: 0.08em;
        color: #93c5fd;
        font-weight: 700;
        font-size: 11px;
    }

    #sidebar .sidebar-logo {
        height: 40px;
        max-width: 170px;
        width: auto;
        object-fit: contain;
        border: 1px solid rgba(255, 255, 255, 0.55);
        border-radius: 10px;
        padding: 4px 8px;
        background: rgba(255, 255, 255, 0.06);
        backdrop-filter: blur(2px);
    }
</style>

<aside id="sidebar"
    class="sidebar fixed top-0 left-0 h-full w-64 z-20 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out overflow-y-auto">
    <!-- Header -->
    <div class="flex justify-between items-center px-4 py-4 border-b border-white/10">
        <div class="flex items-center text-left">
            @php
                $defaultLogoPath = 'image/company/69cb5771cc187.png';
                $defaultLogoUrl = asset($defaultLogoPath);
                $companyLogoPath = Auth::user()?->company?->logo;
                $logoUrl = $defaultLogoUrl;

                if (!empty($companyLogoPath)) {
                    if (\Illuminate\Support\Str::startsWith($companyLogoPath, ['http://', 'https://'])) {
                        $logoUrl = $companyLogoPath;
                    } else {
                        $logoUrl = asset($companyLogoPath);
                    }
                }
            @endphp
            <img src="{{ $logoUrl }}" alt="{{ Auth::user()?->company?->name ?? 'EPAL Group' }}"
                class="sidebar-logo"
                onerror="this.onerror=null;this.src='{{ $defaultLogoUrl }}';">
        </div>
        <button id="closeSidebar" class="text-slate-200 text-xl md:hidden hover:text-white transition-colors">
            <i class="fas fa-times"></i>
        </button>
    </div>

    @php
        $role = Str::slug(Auth::user()->getRoleNames()->first());
    @endphp

    <!-- Navigation -->
    <nav class="p-3 space-y-1.5">
        <!-- Dashboard -->
        <a href="{{ route('role.dashboard', ['role' => $role]) }}"
            class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.dashboard') ? 'active' : '' }}">
            <i class="fas fa-chart-line w-6 text-center mr-1"></i>
            <span>Dashboard</span>
        </a>
        @can('view branch')
            <!-- branch -->
            {{-- <a href="{{ route('role.branches.index', ['role' => $role]) }}"
                class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.branches.index') ? 'active' : '' }}">
                <i class="fas fa-code-branch w-6 text-center mr-1"></i>
                <span>Branch</span>
            </a> --}}
        @endcan

        @can('view vendor')
            <!-- Vendor -->
            <div class="sidebar-item">
                <button onclick="toggleSubmenu('vendorSubmenu', this)"
                    class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                    <div class="flex items-center text-left">
                        <i class="fas fa-users w-6 text-center mr-1"></i>
                        <span>Vendor</span>
                    </div>
                    <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                </button>
                <div id="vendorSubmenu"
                    class="submenu pl-0 mt-1 space-y-1 text-sm {{ request()->routeIs('role.vendor.create') || request()->routeIs('role.vendor.index') ? '' : 'hidden' }}">
                    <a href="{{ route('role.vendor.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.vendor.create') ? 'active' : '' }}"><i
                            class="fas fa-plus"></i> Add Vendor</a>
                    <a href="{{ route('role.vendor.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.vendor.index') ? 'active' : '' }}"><i
                            class="fas fa-list"></i> Manage Vendor</a>
                </div>
            </div>
        @endcan
        @can('view users')
            <!-- Users -->
            <div class="sidebar-item">
                <button onclick="toggleSubmenu('userSubmenu', this)"
                    class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                    <div class="flex items-center text-left">
                        <i class="fas fa-user-friends w-6 text-center mr-1"></i>
                        <span>User Managements</span>
                    </div>
                    <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                </button>
                <div id="userSubmenu"
                    class="submenu pl-0 mt-1 space-y-1 text-sm {{ request()->routeIs('role.user.create') || request()->routeIs('role.customers.index') || request()->routeIs('role.suppliers.index') || request()->routeIs('role.user.index') || request()->routeIs('role.user.documents') ? '' : 'hidden' }}">
                    @can('create users')
                        <a href="{{ route('role.user.create', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.user.create') ? 'active' : '' }}"><i
                                class="fas fa-plus"></i> Add User</a>
                    @endcan
                    @can('view users')
                    @php
                      $roles = \Spatie\Permission\Models\Role::whereNotIn('name', ['customer', 'supplier'])->get();
                    @endphp
                    @foreach($roles as $role_name)
                    <a href="{{ route('role.user.index', ['role' => $role_name->name]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white 
                    {{ request()->role == $role_name->name ? 'active' : '' }}">
                    
                    <i class="fas fa-user"></i> {{ ucfirst($role_name->name) }} Users
                    </a>
                    @endforeach
                        <a href="{{ route('role.user.documents', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.user.documents') ? 'active' : '' }}"><i
                                class="fas fa-id-card"></i> Employees Document</a>
                    @endcan
                    
                    @can('view customers')
                        <a href="{{ route('role.customers.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.customers.index') ? 'active' : '' }}"><i
                                class="fas fa-user-group"></i> Manage Customers</a>
                    @endcan
                    @can('view suppliers')
                        <a href="{{ route('role.suppliers.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.suppliers.index') ? 'active' : '' }}"><i
                                class="fas fa-truck"></i> Manage Suppliers</a>
                    @endcan
                    <a href="{{ route('role.promotions.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.promotions.index') ? 'active' : '' }}"><i
                            class="fas fa-trophy"></i> User Promotions</a>
                </div>
            </div>
        @endcan
        @can('view product')
            <!-- Item Management -->
            <div class="sidebar-item">
                <button onclick="toggleSubmenu('itemSubmenu', this)"
                    class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                    <div class="flex items-center text-left">
                        <i class="fas fa-box w-6 text-center mr-1"></i>
                        <span>Product Management</span>
                    </div>
                    <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                </button>
                <div id="itemSubmenu"
                    class="submenu pl-0 mt-1 space-y-1 text-sm {{ request()->routeIs('role.units.index') || request()->routeIs('role.brands.index') || request()->routeIs('role.categories.index') || request()->routeIs('role.sub-categories.index') ? '' : 'hidden' }}">
                    @can('view unit')
                        <a href="{{ route('role.units.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.units.index') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Unit</a>
                    @endcan
                    @can('view brand')
                        <a href="{{ route('role.brands.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.brands.create') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Brand</a>
                    @endcan
                    @can('view category')
                        <a href="{{ route('role.categories.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.categories.index') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Category</a>
                    @endcan
                    @can('view subcategory')
                        <a href="{{ route('role.sub-categories.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.sub-categories.index') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Sub Category</a>
                    @endcan
                    @can('view product')
                        <a href="{{ route('role.products.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.products.index') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Product</a>
                    @endcan
                </div>
            </div>
        @endcan
        @can('view portal')
            <!-- Portal Management -->
            <div class="sidebar-item">
                <button onclick="toggleSubmenu('portalSubmenu', this)"
                    class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                    <div class="flex items-center text-left">
                        <i class="fas fa-globe w-6 text-center mr-1"></i>
                        <span>Portal Management</span>
                    </div>
                    <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                </button>
                <div id="portalSubmenu"
                    class="submenu pl-0 mt-1 space-y-1 text-sm {{ request()->routeIs('role.portal-management.create') || request()->routeIs('role.portal-management.index') ? '' : 'hidden' }}">
                    @can('create portal')
                        <a href="{{ route('role.portal-management.create', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.portal-management.create') ? 'active' : '' }}"><i
                                class="fas fa-plus"></i> Add Portal</a>
                    @endcan
                    <a href="{{ route('role.portal-management.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.portal-management.index') ? 'active' : '' }}"><i
                            class="fas fa-list"></i> Manage Portal</a>
                </div>
            </div>
        @endcan
        @canany(['view passport holder', 'view visa', 'view geography'])
            <!-- Passport & Visa -->
            <div class="sidebar-item">
                <button onclick="toggleSubmenu('passportVisaSubmenu', this)"
                    class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                    <div class="flex items-center text-left">
                        <i class="fas fa-passport w-6 text-center mr-1"></i>
                        <span>Passport & Visa</span>
                    </div>
                    <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                </button>
                <div id="passportVisaSubmenu"
                    class="submenu pl-0 mt-1 space-y-1 text-sm 
                    {{
                     request()->routeIs('role.passport-holder.*') ||
                     request()->routeIs('role.passport-holder-category.*') ||
                     request()->routeIs('role.visa.*') ||
                     request()->routeIs('role.visa-category.*') ||
                     request()->routeIs('role.countries.*') ||
                     request()->routeIs('role.states.*') ? '' : 'hidden' }}">
                    @can('view passport holder')
                        <a href="{{ route('role.passport-holder-category.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.passport-holder-category.index') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Passport Category</a>
                        <a href="{{ route('role.passport-holder.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.passport-holder.index') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Passport Holder</a>
                    @endcan
                    @can('view visa')
                        <a href="{{ route('role.visa-category.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.visa-category.*') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Visa Category</a>
                        <a href="{{ route('role.visa.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.visa.*') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Visa Processing</a>
                    @endcan
                    @can('view geography')
                        <a href="{{ route('role.countries.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.countries.index') ? 'active' : '' }}"><i
                                class="fas fa-flag"></i> Country</a>
                        <a href="{{ route('role.states.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.states.index') ? 'active' : '' }}"><i
                                class="fas fa-city"></i> States</a>
                    @endcan
                </div>
            </div>
        @endcanany
        @canany(['view ticket', 'view ticket purchase', 'view ticket sale'])
            <!-- Ticketing System -->
            <div class="sidebar-item">
                <button onclick="toggleSubmenu('ticketingSubmenu', this)"
                    class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                    <div class="flex items-center text-left">
                        <i class="fas fa-ticket-alt w-6 text-center mr-1"></i>
                        <span>Ticketing System</span>
                    </div>
                    <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                </button>
                <div id="ticketingSubmenu"
                    class="submenu pl-0 mt-1 space-y-1 text-sm {{ request()->routeIs('role.tickets.index') || request()->routeIs('role.ticket-purchase.*') || request()->routeIs('role.airport.*') || request()->routeIs('role.ticket-sales.*') ? '' : 'hidden' }}">
                    @can('view ticket')
                        <a href="{{ route('role.tickets.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.tickets.index') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Manage Tickets</a>
                    @endcan
                    @can('view ticket purchase')
                        <a href="{{ route('role.ticket-purchase.create', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.ticket-purchase.create') ? 'active' : '' }}"><i
                                class="fas fa-plus"></i> Add Purchase</a>
                        <a href="{{ route('role.ticket-purchase.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.ticket-purchase.index') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Manage Purchase</a>
                    @endcan
                    @can('view ticket sale')
                        <a href="{{ route('role.ticket-sales.create', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.ticket-sales.create') ? 'active' : '' }}"><i
                                class="fas fa-plus"></i> Add Sales</a>
                        <a href="{{ route('role.ticket-sales.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.ticket-sales.index') ? 'active' : '' }}"><i
                                class="fas fa-list"></i> Manage Sales</a>
                    @endcan
                    @can('view geography')
                        <a href="{{ route('role.airport.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.airport.index') ? 'active' : '' }}"><i
                                class="fas fa-plane-departure"></i> Airport Management</a>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany(['view purchase', 'view sales', 'view stock transfer', 'view stock movement', 'view return reference'])
            <!-- Inventory -->
            <div class="sidebar-item">
                <button onclick="toggleSubmenu('inventorySubmenu', this)"
                    class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                    <div class="flex items-center text-left">
                        <i class="fas fa-boxes-stacked w-6 text-center mr-1"></i>
                        <span>Inventory</span>
                    </div>
                    <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                </button>
                <div id="inventorySubmenu"
                    class="submenu pl-0 mt-1 space-y-1 text-sm {{ request()->routeIs('role.return-refs.index') ||
                    request()->routeIs('role.return-refs.create') ||
                    request()->routeIs('role.return-refs.edit') ||
                    request()->routeIs('role.stock-transfers.index') ||
                    request()->routeIs('role.stock-transfers.create') ||
                    request()->routeIs('role.stock-transfers.edit') ||
                    request()->routeIs('role.stock-movements.index') ||
                    request()->routeIs('role.stock-movements.create') ||
                    request()->routeIs('role.stock-movements.edit') ||
                    request()->routeIs('role.sales.index') ||
                    request()->routeIs('role.sales.create') ||
                    request()->routeIs('role.sales.edit') ||
                    request()->routeIs('role.purchases.index') ||
                    request()->routeIs('role.purchases.create') ||
                    request()->routeIs('role.purchases.edit')
                        ? ''
                        : 'hidden' }}">
                    @can('view sales')
                        <a href="{{ route('role.sales.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.sales.index') || request()->routeIs('role.sales.create') || request()->routeIs('role.sales.edit') ? 'active' : '' }}"><i
                                class="fas fa-chart-pie"></i> Sale</a>
                    @endcan
                    @can('view purchases')
                        <a href="{{ route('role.purchases.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.purchases.index') || request()->routeIs('role.purchases.create') || request()->routeIs('role.purchases.edit') ? 'active' : '' }}"><i
                                class="fas fa-dollar"></i> Purchase</a>
                    @endcan
                    @can('view stock transfer')
                        <a href="{{ route('role.stock-transfers.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.stock-transfers.index') || request()->routeIs('role.stock-transfers.create') || request()->routeIs('role.stock-transfers.edit') ? 'active' : '' }}"><i
                                class="fas fa-exchange"></i> Stock Transfer</a>
                    @endcan
                    @can('view stock movement')
                        <a href="{{ route('role.stock-movements.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.stock-movements.index') || request()->routeIs('role.stock-movements.create') || request()->routeIs('role.stock-movements.edit') ? 'active' : '' }}"><i
                                class="fas fa-warehouse"></i> Stock Adjustment</a>
                    @endcan
                    @can('view return reference')
                        <a href="{{ route('role.return-refs.index', ['role' => $role]) }}"
                            class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.return-refs.index') || request()->routeIs('role.return-refs.create') || request()->routeIs('role.return-refs.edit') ? 'active' : '' }}"><i
                                class="fas fa-undo"></i> Return Reference</a>
                    @endcan
                </div>
            </div>
        @endcanany
        @can('view expense')
            <!-- Expenses -->
            <div class="sidebar-item">
                <button onclick="toggleSubmenu('expensesSubmenu', this)"
                    class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                    <div class="flex items-center text-left">
                        <i class="fas fa-money-bill-wave w-6 text-center mr-1"></i>
                        <span>Expenses</span>
                    </div>
                    <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                </button>
                <div id="expensesSubmenu"
                    class="submenu pl-0 mt-1 space-y-1 text-sm {{ request()->routeIs('role.expense-subcategories.index') || request()->routeIs('role.expense-categories.index') || request()->routeIs('role.expenses.index') ? '' : 'hidden' }}">
                    <a href="{{ route('role.expense-categories.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.expense-categories.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-folder"></i> Expense Category
                    </a>
                    <a href="{{ route('role.expense-subcategories.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.expense-subcategories.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-folder"></i> Sub Category
                    </a>
                    <a href="{{ route('role.expenses.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.expenses.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-list"></i> All Expenses
                    </a>
                </div>
            </div>
        @endcan
        @canany(['view department', 'view designation', 'view attendance','view attendance settings', 'view shift', 'view holidays', 'view leave types', 'view leaves', 'view attendances'])
        <!-- HRM -->
        <div class="sidebar-item">
            <button onclick="toggleSubmenu('hrmSubmenu', this)"
                class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                <div class="flex items-center text-left">
                    <i class="fas fa-users w-6 text-center mr-1"></i>
                    <span>HRM</span>
                </div>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="hrmSubmenu"
                class="submenu pl-0 mt-1 space-y-1 text-sm
                {{ request()->routeIs('role.departments.index') ||
                request()->routeIs('role.designations.index') ||
                request()->routeIs('role.holidays.index') ||
                request()->routeIs('role.shifts.index') ||
                request()->routeIs('role.leaves.index') ||
                request()->routeIs('role.attendances.index') ||
                request()->routeIs('role.leave-types.index') ||
                request()->routeIs('role.attendence-settings.index')
                    ? ''
                    : 'hidden' }}">
                @can('view department')
                    <a href="{{ route('role.departments.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.departments.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-building"></i> Department
                    </a>
                @endcan
                @can('view designation')
                    <a href="{{ route('role.designations.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.designations.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-building"></i> Designation
                    </a>
                @endcan
                @can('view attendance settings')
                    <a href="{{ route('role.attendence-settings.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.attendence-settings.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-cog"></i> Attendance Settings
                    </a>
                @endcan
                @can('view shift')
                <a href="{{ route('role.shifts.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.shifts.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-cog"></i> Shift
                </a>
                @endcan
                @can('view holiday')
                <a href="{{ route('role.holidays.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.holidays.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-cog"></i> Holidays
                </a>
                @endcan
                @can('view leave')
                <a href="{{ route('role.leave-types.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.leave-types.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-cog"></i> Leave Types
                </a>
                <a href="{{ route('role.leaves.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.leaves.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-cog"></i> All Leaves
                </a>
                @endcan
                @can('view resignation')
                <a href="{{ route('role.resignations.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.resignations.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-cog"></i> Resignations
                </a>
                @endcan
                @can('view attendance')
                <a href="{{ route('role.attendances.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.attendances.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-cog"></i> All Attendances
                </a>
                @endcan
            </div>
        </div>
        @endcanany
        @canany(['view salary template', 'view employee salary', 'view loan', 'view payment', 'view payslip', 'view advance salary', 'view commission'])
        <!-- Payroll -->
        <div class="sidebar-item">
            <button onclick="toggleSubmenu('payrollSubmenu', this)"
                class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                <div class="flex items-center text-left">
                    <i class="fas fa-money-check-alt w-6 text-center mr-1"></i>
                    <span>Payroll</span>
                </div>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="payrollSubmenu"
                class="submenu pl-0 mt-1 space-y-1 text-sm
                {{ request()->routeIs('role.salary-templates.index') ||
                request()->routeIs('role.departments.index') ||
                request()->routeIs('role.salary-templates.index') ||
                request()->routeIs('role.sales-records.index') ||
                request()->routeIs('role.employee-salaries.index') ||
                request()->routeIs('role.loans.index') ||
                request()->routeIs('role.payments.index') ||
                request()->routeIs('role.payslips.index') ||
                request()->routeIs('role.advance-salaries.index') ||
                request()->routeIs('role.commissions.index')
                    ? ''
                    : 'hidden' }}">
                @can('edit salary template')
                    <a href="{{ route('role.salary-templates.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.salary-templates.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-file-invoice-dollar"></i> Salary Template
                    </a>
                @endcan
                    {{-- <a href="{{ route('role.sales-records.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.sales-records.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-calculator"></i> Manage Salary
                </a> --}}
                @can('view salary')
                    <a href="{{ route('role.employee-salaries.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.employee-salaries.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-list"></i> Salary Manage
                    </a>
                @endcan
                @can('view loan')
                    <a href="{{ route('role.loans.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.loans.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-hand-holding-dollar"></i> Loan Management
                    </a>
                @endcan
                {{-- <a href="{{ route('role.payments.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.payments.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-money-bill-transfer"></i> Make Payment
                </a> --}}
                @can('view payslip')
                    <a href="{{ route('role.payslips.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.payslips.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-file-lines"></i> Payslip
                    </a>
                @endcan
                @can('view advance salary')
                <a href="{{ route('role.advance-salaries.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.advance-salaries.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-sack-dollar"></i> Advance Salary
                </a>
                @endcan
                @can('view commission')
                {{-- <a href="{{ route('role.commissions.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.commissions.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-percent"></i> Commission
                </a> --}}
                @endcan
            </div>
        </div>
        @endcanany
        @can('view bank')
            <!-- Bank -->
            <div class="sidebar-item">
                <button onclick="toggleSubmenu('bankSubmenu', this)"
                    class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                    <div class="flex items-center text-left">
                        <i class="fas fa-building-columns w-6 text-center mr-1"></i>
                        <span>Manage Banks</span>
                    </div>
                    <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                </button>
                <div id="bankSubmenu" class="submenu pl-0 mt-1 space-y-1 text-sm {{ request()->routeIs('role.banks.index') || request()->routeIs('role.bank_transfers.index') ? '' : 'hidden' }}">
                    <a href="{{ route('role.banks.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.banks.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-list"></i> All Banks
                    </a>
                    <a href="{{ route('role.bank_transfers.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.bank_transfers.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-exchange-alt"></i> Bank Transfers
                    </a>
                </div>
            </div>
        @endcan
        <a href="{{ route('role.accounts.index', ['role' => $role]) }}"
            class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.accounts.index') ? 'active' : '' }}">
            <i class="fas fa-user-circle w-6 text-center mr-1"></i>
            <span>Manage Accounts</span>
        </a>
        @canany(['view lead source', 'view lead manager', 'view project category', 'view contract type', 'view deal', 'view proposal', 'view project', 'view contract', 'view ticket department', 'view support ticket', 'view estimate'])
        <!-- CRM -->
        <div class="sidebar-item">
            <button onclick="toggleSubmenu('crmSubmenu', this)"
                class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                <div class="flex items-center text-left">
                    <i class="fas fa-money-check-alt w-6 text-center mr-1"></i>
                    <span>CRM</span>
                </div>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="crmSubmenu"
                class="submenu pl-0 mt-1 space-y-1 text-sm
                {{ request()->routeIs('role.lead-source.index') ||
                request()->routeIs('role.lead-followup.index') ||
                request()->routeIs('role.lead-reminders.index') ||
                request()->routeIs('role.lead-manager.index') ||
                request()->routeIs('role.project-categories.index') ||
                request()->routeIs('role.contract-types.index') ||
                request()->routeIs('role.deals.index') ||
                request()->routeIs('role.proposals.index') ||
                request()->routeIs('role.projects.index') ||
                request()->routeIs('role.contracts.index') ||
                request()->routeIs('role.estimates.index')
                    ? ''
                    : 'hidden' }}">
                @can('view lead source')
                <a href="{{ route('role.lead-source.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.lead-source.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-bullseye"></i> Lead Source
                </a>
                @endcan
                @can('view lead manager')
                <a href="{{ route('role.lead-manager.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.lead-manager.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-user-plus"></i> Lead Manager
                </a>
                @endcan
                @can('view lead followup')
                <a href="{{ route('role.lead-followup.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.lead-followup.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-phone"></i> Lead Followup
                </a>
                @endcan
                @can('view lead reminder')
                <a href="{{ route('role.lead-reminders.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.lead-reminders.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-bell"></i> Lead Reminder
                </a>
                @endcan
                @can('view project category')
                <a href="{{ route('role.project-categories.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.project-categories.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-folder-tree"></i> Project Category
                </a>
                @endcan
                @can('view contract')
                <a href="{{ route('role.contract-types.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.contract-types.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-file-signature"></i> Contract Type
                </a>
                <a href="{{ route('role.contracts.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.contracts.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-file-contract"></i> Contracts
                </a>
                @endcan
                @can('view deal')
                    <a href="{{ route('role.deals.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.deals.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-handshake"></i> Deal Manager
                    </a>
                @endcan
                @can('view proposal')
                <a href="{{ route('role.proposals.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.proposals.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-file-contract"></i> Proposal Manager
                </a>
                @endcan
                @can('view project')
                    <a href="{{ route('role.projects.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.projects.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-diagram-project"></i> Projects
                    </a>
                @endcan
                
                @can('view estimate')
                <a href="{{ route('role.estimates.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.estimates.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-calculator"></i> Estimate
                </a>
                @endcan
            </div>
        </div>
        @endcanany

        @can('view task')
        <div class="sidebar-item">
            <button onclick="toggleSubmenu('taskSubmenu', this)"
                class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                <div class="flex items-center text-left">
                    <i class="fa-solid fa-list-check w-6 text-center mr-1"></i>
                    <span>Task</span>
                </div>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="taskSubmenu"
                class="submenu pl-0 mt-1 space-y-1 text-sm {{ request()->routeIs('role.workspace-users.index') || request()->routeIs('role.boards.index') || request()->routeIs('role.columns.index') || request()->routeIs('role.labels.index') || request()->routeIs('role.tasks.index') || request()->routeIs('role.tasks.board') ? '' : 'hidden' }}">
                @can('view workspace')
                    <a href="{{ route('role.workspace-users.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.workspace-users.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-people-group"></i> Workspace
                    </a>
                @endcan
                @can('view board')
                    <a href="{{ route('role.boards.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.boards.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-table-columns"></i> Board
                    </a>
                    <a href="{{ route('role.columns.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.columns.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-bars-staggered"></i> Column
                    </a>
                @endcan
                @can('view label')
                    <a href="{{ route('role.labels.index', ['role' => $role]) }}"
                        class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.labels.index') ? 'active' : '' }}">
                        <i style="margin-right: 5px;" class="fa-solid fa-tag"></i> Label
                    </a>
                @endcan
                <a href="{{ route('role.tasks.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.tasks.index') || request()->routeIs('role.tasks.board') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-tasks"></i> My Tasks
                </a>
            </div>
        </div>
        @endcan

        @can('view report')
        <!-- Report -->
        <div class="sidebar-item">
            <button onclick="toggleSubmenu('reportMenu', this)"
                class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                <div class="flex items-center text-left">
                    <i class="fas fa-book w-6 text-center mr-1"></i>
                    <span>Report</span>
                </div>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            @can('view attendance')
            <div id="reportMenu"
                class="submenu pl-0 mt-1 space-y-1 text-sm {{ request()->routeIs('role.report.*') ? '' : 'hidden' }}">
                <a href="{{ route('role.report.monthly-attendances', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.report.monthly-attendances') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-list"></i> Monthly Attendance
                </a>
                <a href="{{ route('role.report.task.reports', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.report.task.reports') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-list"></i> Employee Task Report
                </a>
                <a href="{{ route('role.report.general-ledger', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.report.general-ledger') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-list"></i> General Ledger
                </a>                                
                <a href="{{ route('role.report.trial-balance', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.report.trial-balance') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-list"></i> Trial Balance
                </a>                                
                <a href="{{ route('role.report.profit-loss', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.report.profit-loss') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-list"></i> Profit & Loss
                </a>                                
                <a href="{{ route('role.report.balance-sheet', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.report.balance-sheet') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-list"></i> Balance Sheet
                </a>                                
                <a href="{{ route('role.report.account-ledger', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.report.account-ledger') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-list"></i> Account Ledger
                </a>                                
                <a href="{{ route('role.report.account-statement', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.report.account-statement') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-list"></i> Account Statement
                </a>                                
                <a href="{{ route('role.report.journal-entries', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.report.journal-entries') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-list"></i> Journal Entry Report
                </a>                                
                <a href="{{ route('role.report.account-balances', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.report.account-balances') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-list"></i> Account Balance
                </a>                
            </div>
            @endcan
        </div>
        @endcan
        @can('view notice')
        <!-- Notice -->
        <div class="pt-4 mt-4 border-t border-blue-500/30">
            <a href="{{ route('role.notices.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.notices.index') ? 'active' : '' }}">
                <i class="fas fa-envelope w-6 text-center mr-1"></i>
                <span>Notices</span>
            </a>
        </div>
        @endcan

        <!-- Notification Section -->
        <div class="pt-4 mt-4 border-t border-blue-500/30">
            <a href="{{ route('role.notifications.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.notifications.index') ? 'active' : '' }}">
                <i class="fas fa-envelope w-6 text-center mr-1"></i>
                <span>Notifications</span>
            </a>
        </div>

        @can('view support ticket')
        <!-- Report -->
        <div class="sidebar-item">
            <button onclick="toggleSubmenu('supportMenu', this)"
                class="w-full flex justify-between items-center p-3 text-white hover:text-blue-200 focus:outline-none cursor-pointer">
                <div class="flex items-center text-left">
                    <i class="fas fa-book w-6 text-center mr-1"></i>
                    <span>Support Ticket</span>
                </div>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            @can('view support ticket')
            <div id="supportMenu"
                class="submenu pl-0 mt-1 space-y-1 text-sm {{ request()->routeIs('role.ticket-departments.index') || request()->routeIs('role.support-tickets.index') ? '' : 'hidden' }}">
                <a href="{{ route('role.ticket-departments.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.ticket-departments.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-ticket"></i> Ticket Department
                </a>

                <a href="{{ route('role.support-tickets.index', ['role' => $role]) }}"
                    class="submenu-item block p-2 text-blue-100 hover:text-white cursor-pointer {{ request()->routeIs('role.support-tickets.index') ? 'active' : '' }}">
                    <i style="margin-right: 5px;" class="fa-solid fa-headset"></i> Support Ticket
                </a>
            </div>
            @endcan
        </div>
        @endcan
        @can('view marketing')
        <!-- Marketing Section -->
        <div class="pt-4 mt-4 border-t border-blue-500/30">
            <h3 class="text-xs uppercase text-blue-300 font-semibold px-3 mb-2">Marketing</h3>

            <!-- Email Marketing -->
            <a href="{{ route('role.email-marketing.index', ['role' => $role]) }}"
                class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.email-marketing.index') ? 'active' : '' }}">
                <i class="fas fa-envelope w-6 text-center mr-1"></i>
                <span>Email Marketing</span>
            </a>

            <!-- SMS Marketing -->
            <a href="{{ route('role.sms-marketing.index', ['role' => $role]) }}"
                class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.sms-marketing.index') ? 'active' : '' }}">
                <i class="fas fa-sms w-6 text-center mr-1"></i>
                <span>SMS Marketing</span>
            </a>

            <!-- WhatsApp Marketing -->
            <a href="{{ route('role.whatsapp-marketing.index', ['role' => $role]) }}"
                class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.whatsapp-marketing.index') ? 'active' : '' }}">
                <i class="fab fa-whatsapp w-6 text-center mr-1"></i>
                <span>WhatsApp Marketing</span>
            </a>
        </div>
        @endcan
        @can('view company setting')
        <!-- Settings -->
        <div class="pt-4 mt-4 border-t border-blue-500/30">
            <h3 class="text-xs uppercase text-blue-300 font-semibold px-3 mb-2">Settings</h3>
            <a href="{{ route('role.sms_templates.index', ['role' => $role]) }}"
                class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.sms_templates.index') ? 'active' : '' }}">
                <i class="fas fa-cog w-6 text-center mr-1"></i>
                <span>SMS Templates</span>
            </a>
            <a href="{{ route('role.company-settings.index', ['role' => $role]) }}"
                class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.company-settings.index') ? 'active' : '' }}">
                <i class="fas fa-cog w-6 text-center mr-1"></i>
                <span>Company Settings</span>
            </a>
            <a href="{{ route('role.device-settings.index', ['role' => $role]) }}"
                class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.device-settings.index') ? 'active' : '' }}">
                <i class="fas fa-cog w-6 text-center mr-1"></i>
                <span>Device Settings</span>
            </a>
            <a href="{{ route('role.invoice-templates.index', ['role' => $role]) }}"
                class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.invoice-templates.index') ? 'active' : '' }}">
                <i class="fas fa-print w-6 text-center mr-1"></i>
                <span>Invoice Settings</span>
            </a>
            <a href="{{ route('role.role-permission.index', ['role' => $role]) }}"
                class="sidebar-item flex items-center p-3 text-white hover:text-blue-200 cursor-pointer {{ request()->routeIs('role.roles.index') ? 'active' : '' }}">
                <i class="fas fa-user-friends w-6 text-center mr-1"></i>
                <span>Role Settings</span>
            </a>
        </div>
        @endcan
    </nav>
</aside>

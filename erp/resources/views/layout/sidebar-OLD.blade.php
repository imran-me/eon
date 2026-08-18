<aside id="sidebar"
    class="fixed top-0 left-0 h-full w-full md:w-64 bg-white shadow-md z-20 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out overflow-y-auto">
    <!-- Close Button (mobile only) -->
    <div class="flex justify-between border-b">

        <div class="p-6 text-xl font-bold">My Dashboard </div>
        <button onclick="toggleSidebar()" class="text-2xl md:hidden  mr-4">
            <i class="fas fa-times"></i> <!-- Font Awesome close icon -->
        </button>
    </div>

    <nav class="p-4 space-y-4">
        <a href="{{ route('role.dashboard', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="block text-gray-700 hover:text-blue-600 cursor-pointer"><i
                class="fas fa-chart-line mr-2"></i> Dashboard</a>
        <a href="{{ route('role.banks.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="block text-gray-700 hover:text-blue-600 cursor-pointer"><i
                class="fas fa-building-columns mr-2"></i> Bank</a>
        <!-- Users with submenu -->
        <div>
            @php
                $isVendorRoute = request()->is('super-admin/vendor/create') || request()->is('super-admin/vendor*');
            @endphp

            <button onclick="toggleSubmenu('supplierSubmenu', this)"
                class="w-full flex justify-between items-center hover:text-blue-600 focus:outline-none cursor-pointer">
                <span><i class="fas fa-users mr-2"></i> Vendor</span>
                <i class="fas {{ $isVendorRoute ? 'fa-chevron-up' : 'fa-chevron-down' }} text-sm transition-transform duration-300"></i>
            </button>
            <div id="supplierSubmenu" class="pl-6 mt-2 {{ $isVendorRoute ? '' : 'hidden' }} space-y-1 text-sm">
                <a href="{{ route('role.vendor.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Add Vendor</a>
                <a href="{{ route('role.vendor.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Manage Vendor</a>
            </div>
        </div>
        <div>
            <button onclick="toggleSubmenu('portalSubmenu', this)"
                class="w-full flex justify-between items-center hover:text-blue-600 focus:outline-none cursor-pointer">
                <span><i class="fas fa-users mr-2"></i> Portal Management</span>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="portalSubmenu" class="pl-6 mt-2 hidden space-y-1 text-sm">
                <a href="{{ route('role.portal-management.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Add Protal</a>
                <a href="{{ route('role.portal-management.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Manage Protal</a>
            </div>
        </div>
        <div>
            <button onclick="toggleSubmenu('passportCategory', this)"
                class="w-full flex justify-between items-center hover:text-blue-600 focus:outline-none cursor-pointer">
                <span><i class="fas fa-plus mr-2"></i> Passport Category</span>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="passportCategory" class="pl-6 mt-2 hidden space-y-1 text-sm">
                <a href="{{ route('role.passport-holder-category.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Add Passport Category</a>
                <a href="{{ route('role.passport-holder-category.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Manage Passport Category</a>
            </div>
        </div>
         <div>
            <button onclick="toggleSubmenu('passportSubmenu', this)"
                class="w-full flex justify-between items-center hover:text-blue-600 focus:outline-none cursor-pointer">
                <span><i class="fas fa-plus mr-2"></i> Passport Holder</span>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="passportSubmenu" class="pl-6 mt-2 hidden space-y-1 text-sm">
                <a href="{{ route('role.passport-holder.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Add Passport Holder</a>
                <a href="{{ route('role.passport-holder.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Manage Passport Holder</a>
            </div>
        </div>
        <div>
            <button onclick="toggleSubmenu('purchaseSubmenu', this)"
                class="w-full flex justify-between items-center hover:text-blue-600 focus:outline-none cursor-pointer">
                <span><i class="fas fa-shopping-cart mr-2"></i>Ticket Purchase</span>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="purchaseSubmenu" class="pl-6 mt-2 hidden space-y-1 text-sm">
                <a href="{{ route('role.ticket-purchase.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Add Ticket Purchase</a>
                <a href="{{ route('role.ticket-purchase.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Manage Ticket Purchase</a>
            </div>
        </div>
        <div>
            <button onclick="toggleSubmenu('ticketSell', this)"
                class="w-full flex justify-between items-center hover:text-blue-600 focus:outline-none cursor-pointer">
                <span><i class="fas fa-check mr-2"></i>Ticket Sales</span>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="ticketSell" class="pl-6 mt-2 hidden space-y-1 text-sm">
                <a href="{{ route('role.ticket-sales.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Add Sales</a>
                <a href="{{ route('role.ticket-sales.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Manage Sales</a>
            </div>
        </div>
        <div>
            <button onclick="toggleSubmenu('inventorySubmenu', this)"
                class="w-full flex justify-between items-center hover:text-blue-600 focus:outline-none cursor-pointer">
                <span><i class="fas fa-pallet mr-2"></i> Inventory</span>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="inventorySubmenu" class="pl-6 mt-2 hidden space-y-1 text-sm">
                <a href="{{ route('role.user.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Add Inventory</a>
                <a href="{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Manage Inventory</a>
            </div>
        </div>

        <div>
            <button onclick="toggleSubmenu('userSubmenu', this)"
                class="w-full flex justify-between items-center hover:text-blue-600 focus:outline-none cursor-pointer">
                <span><i class="fas fa-users mr-2"></i> Users</span>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="userSubmenu" class="pl-6 mt-2 hidden space-y-1 text-sm">
                <a href="{{ route('role.user.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Add User</a>
                <a href="{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Manage Users</a>
            </div>
        </div>
        <div>
            <button onclick="toggleSubmenu('airportSubmenu', this)"
                class="w-full flex justify-between items-center hover:text-blue-600 focus:outline-none cursor-pointer">
                <span><i class="fas fa-pallet mr-2"></i> Airport Management</span>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="airportSubmenu" class="pl-6 mt-2 hidden space-y-1 text-sm">
                <a href="{{ route('role.route.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Add Airport</a>
                <a href="{{ route('role.route.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Manage Airport</a>
            </div>
        </div>
        <div>
            <button onclick="toggleSubmenu('userSubmenu', this)"
                class="w-full flex justify-between items-center hover:text-blue-600 focus:outline-none cursor-pointer">
                <span><i class="fas fa-users mr-2"></i> Payroll</span>
                <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
            </button>
            <div id="userSubmenu" class="pl-6 mt-2 hidden space-y-1 text-sm">
                <a href="{{ route('role.user.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Department</a>
                <a href="{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Salary Template</a>
                <a href="{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Manage salary</a>
                <a href="{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Employee salary list</a>
                <a href="{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Loan management</a>
                <a href="{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Make payment</a>
                <a href="{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Payslip</a>
                <a href="{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Advance Salary</a>
                <a href="{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="block hover:text-blue-500 cursor-pointer">Commission</a>
            </div>
        </div>
        <a href="#" class="block text-gray-700 hover:text-blue-600 cursor-pointer"><i
                class="fas fa-file-alt mr-2"></i> Employees</a>
        <a href="#" class="block text-gray-700 hover:text-blue-600 cursor-pointer"><i
                class="fas fa-file-alt mr-2"></i> Salary</a>
        <a href="#" class="block text-gray-700 hover:text-blue-600 cursor-pointer"><i
                class="fas fa-file-alt mr-2"></i> Reports</a>
        <a href="#" class="block text-gray-700 hover:text-blue-600 cursor-pointer"><i class="fas fa-cog mr-2"></i>
            Settings</a>
        <a href="{{ route('role.email-marketing.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="block text-gray-700 hover:text-blue-600 cursor-pointer"><i class="fas fa-cog mr-2"></i>
            Email Marketing</a>
        <a href="{{ route('role.sms-marketing.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="block text-gray-700 hover:text-blue-600 cursor-pointer"><i class="fas fa-cog mr-2"></i>
            Sms Marketing</a>
        <a href="{{ route('role.whatsapp-marketing.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="block text-gray-700 hover:text-blue-600 cursor-pointer"><i class="fas fa-cog mr-2"></i>
            What'sApp Marketing</a>
    </nav>
</aside>
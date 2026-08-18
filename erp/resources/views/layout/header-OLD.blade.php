<!-- Header -->
<header class="bg-white shadow p-4 flex justify-between items-center">
    <div class="text-2xl font-semibold">{{ isset($title) ? $title : 'Dashboard' }}</div>
    <div class="flex items-center space-x-6 relative">
        <!-- Hamburger Icon -->
        <button onclick="toggleSidebar()" class="md:hidden text-2xl mr-4">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Notifications -->
        <div class="relative">
            <button class="relative dropdown-toggle cursor-pointer" data-target="dropdown-notifications"
                aria-haspopup="true" aria-expanded="false" aria-controls="dropdown-notifications">
                <i class="fas fa-bell text-xl"></i>
                <span class="absolute -top-1 -right-2 bg-red-500 text-white text-xs rounded-full px-1">3</span>
            </button>
            <div id="dropdown-notifications"
                class="hidden z-10 bg-white shadow-lg rounded
            fixed left-0 right-0 top-[60px] w-full h-[calc(100vh-60px)] overflow-y-auto
            md:absolute md:right-0 md:w-56 md:left-auto md:top-full md:h-auto">
                <div class="p-4 font-semibold border-b">Notifications</div>
                <ul class="text-sm">
                    <li class="px-4 py-2 hover:bg-gray-100">New user registered</li>
                    <li class="px-4 py-2 hover:bg-gray-100">Server restarted</li>
                    <li class="px-4 py-2 hover:bg-gray-100">New order placed</li>
                    <!-- Add more items if needed -->
                </ul>
            </div>
        </div>

        <!-- Messages -->
        <div class="relative">
            <button class="relative dropdown-toggle cursor-pointer" data-target="dropdown-messages" aria-haspopup="true"
                aria-expanded="false" aria-controls="dropdown-messages">
                <i class="fas fa-envelope text-xl"></i>
                <span class="absolute -top-1 -right-2 bg-blue-500 text-white text-xs rounded-full px-1">5</span>
            </button>
            <div id="dropdown-messages"
                class="hidden z-10 bg-white shadow-lg rounded
            fixed left-0 right-0 top-[60px] w-full h-[calc(100vh-60px)] overflow-y-auto
            md:absolute md:right-0 md:w-56 md:left-auto md:top-full md:h-auto">
                <div class="p-4 font-semibold border-b">Messages</div>
                <ul class="text-sm">
                    <li class="px-4 py-2 hover:bg-gray-100">New support message</li>
                    <li class="px-4 py-2 hover:bg-gray-100">Email from admin</li>
                    <li class="px-4 py-2 hover:bg-gray-100">Feedback received</li>
                </ul>
            </div>
        </div>

        <!-- Profile -->
        <div class="relative">
            <button class="dropdown-toggle" data-target="dropdown-profile" aria-haspopup="true" aria-expanded="false"
                aria-controls="dropdown-profile">
                <img src="https://i.pravatar.cc/40" class="w-10 h-10 rounded-full cursor-pointer" alt="Profile" />
            </button>
            <div id="dropdown-profile"
                class="hidden z-10 bg-white shadow-lg rounded
            fixed left-0 right-0 top-[60px] w-full h-[calc(100vh-60px)] overflow-y-auto
            md:absolute md:right-0 md:w-56 md:left-auto md:top-full md:h-auto">
                <a href="#" class="block px-4 py-2 hover:bg-gray-100">Profile</a>
                {{-- <a href="#" class="block px-4 py-2 hover:bg-gray-100">Logout</a> --}}
                <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full text-left px-4 py-2 hover:bg-gray-100">
                    Logout
                </button>
                </form>
            </div>
        </div>
    </div>
</header>

<script>
    document.querySelectorAll('.dropdown-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const dropdown = document.getElementById(targetId);

            // Close other dropdowns
            document.querySelectorAll('.dropdown-toggle').forEach(btn => {
                const otherId = btn.getAttribute('data-target');
                const otherDropdown = document.getElementById(otherId);
                if (otherDropdown !== dropdown) {
                    otherDropdown.classList.add('hidden');
                }
            });

            // Toggle current dropdown
            const isHidden = dropdown.classList.contains('hidden');
            dropdown.classList.toggle('hidden');

            // Toggle body scroll only for mobile
            if (window.innerWidth < 640) {
                if (isHidden) {
                    document.body.classList.add('overflow-hidden');
                } else {
                    document.body.classList.remove('overflow-hidden');
                }
            }
        });
    });

    // Optional: Click outside to close
    document.addEventListener('click', function(e) {
        const isDropdownBtn = e.target.closest('.dropdown-toggle');
        const isDropdownContent = e.target.closest(
            '#dropdown-notifications, #dropdown-messages, #dropdown-profile');

        if (!isDropdownBtn && !isDropdownContent) {
            document.querySelectorAll('.dropdown-toggle').forEach(btn => {
                const dropdown = document.getElementById(btn.getAttribute('data-target'));
                dropdown.classList.add('hidden');
            });
            console.log('clicking')
            const sidebar = document.getElementById('sidebar');
            const isOpen = !sidebar.classList.contains('-translate-x-full');
            if (isOpen) {
                document.body.classList.add('overflow-hidden');
            } else {
                document.body.classList.remove('overflow-hidden');
            }
        }
    });
</script>




<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const isOpen = !sidebar.classList.contains('-translate-x-full');
        if (isOpen) {
            sidebar.classList.add('-translate-x-full');
        } else {
            sidebar.classList.remove('-translate-x-full');
        }
    }
</script>

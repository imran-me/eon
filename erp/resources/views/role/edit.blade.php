@extends('layout.app')
@section('meta-information')
    <title>Edit Role</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .permission-row:hover { background-color: #f9fafb; }
        .select-all-row { opacity: 0.6; cursor: pointer; border: none; background: transparent; }
        .permission-row:hover .select-all-row { opacity: 1; }
        .select-all-row {
    color: #3b82f6;
    transition: all 0.2s;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 10px;
}
.select-all-row:hover {
    color: #1d4ed8;
    text-decoration: underline;
}
        .permission-submenu {
            width: 100%;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #f8fbff;
            padding: 10px 12px;
        }

        .permission-submenu summary {
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            color: #1d4ed8;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .permission-submenu summary::-webkit-details-marker {
            display: none;
        }

        .permission-submenu summary::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 10px;
            color: #60a5fa;
            transition: transform 0.2s;
        }

        .permission-submenu[open] summary::after {
            transform: rotate(180deg);
        }

        .permission-submenu-row {
            border-top: 1px solid #e5efff;
            padding: 10px 0;
        }

        .permission-submenu-row:first-child {
            border-top: none;
        }
    </style>
@endsection

@section('main-content')
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden mt-0">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-edit mr-2"></i> Edit Role: {{ $role->name }}
                </h2>
                <a href="{{ route('role.role-permission.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="btn btn-primary bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition">
                    <i class="fas fa-list mr-2"></i> Role List
                </a>
            </div>

            <div class="states-table-content p-6">
                <form action="{{ route('role.role-permission.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'role_permission' => $role->id]) }}"
                    method="POST">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Role Name</label>
                            <input type="text" name="name" value="{{ old('name', $role->name) }}" required
                                class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <h2 class="font-semibold mb-4 text-gray-800 flex items-center border-b pb-2">
                        <i class="fas fa-shield-alt mr-2 text-blue-600"></i> Permissions
                    </h2>

                    <div class="permission-matrix border rounded-xl overflow-hidden bg-white">
                        @php
                            $travelSubmenuGroups = [
                                'Operations' => [
                                    'Vendor & Agent' => 'vendor',
                                    'Portal Management' => 'portal',
                                ],
                                'Air Ticketing' => [
                                    'Ticket Manage' => 'ticket',
                                    'Ticketing' => 'ticket direct sale',
                                    'Ticket Purchase' => 'ticket purchase',
                                    'Manage Sales' => 'ticket sale',
                                    'Airlines' => 'airline',
                                    'Airport / Country / States' => 'geography',
                                ],
                                'Contract Flight' => [
                                    'Flight Schedule / Add Flight' => 'flight schedule',
                                    'Manage Flight' => 'contract flight',
                                    'Manage Sales' => 'contract flight sale',
                                    'Flight Category' => 'flight category',
                                ],
                                'Visa Processing' => [
                                    'Application Board / Manage Sales / Other Services / Service Types / Visa Category' => 'visa',
                                    'Passport Category / Passport Holder' => 'passport holder',
                                ],
                                'Contract File' => [
                                    'File Category' => 'contract file category',
                                    'Applications Board / New File' => 'contract file',
                                    'Manage Sales' => 'contract file sale',
                                ],
                            ];
                            $travelSubmenuModules = collect($travelSubmenuGroups)->flatMap(fn ($items) => array_values($items))->values()->all();
                            $hrmSubmenuGroups = [
                                'HRM' => [
                                    'Department' => 'department',
                                    'Designation' => 'designation',
                                    'Attendance Settings' => 'attendence setting',
                                    'Shift' => 'shift',
                                    'Holidays' => 'holiday',
                                    'Leave Types / All Leaves' => 'leave',
                                    'Resignations' => 'resignation',
                                    'All Attendances' => 'attendance',
                                ],
                            ];
                            $hrmSubmenuModules = collect($hrmSubmenuGroups)->flatMap(fn ($items) => array_values($items))->values()->all();
                            $accountsSubmenuGroups = [
                                'Expenses' => [
                                    'All Expenses / Budget Setup / Expense Report' => 'expense',
                                ],
                                'Accounts' => [
                                    'Manage Accounts' => 'account',
                                    'Manage Journals' => 'journal',
                                    'Payment Schedules' => 'payment schedule',
                                    'Manage Banks' => 'bank',
                                ],
                            ];
                            $accountsSubmenuModules = collect($accountsSubmenuGroups)->flatMap(fn ($items) => array_values($items))->values()->all();
                            $businessSubmenuGroups = [
                                'Products' => [
                                    'Unit' => 'unit',
                                    'Brand' => 'brand',
                                    'Category' => 'category',
                                    'Sub Category' => 'subcategory',
                                    'Product' => 'product',
                                ],
                                'Inventory' => [
                                    'Customers' => 'customers',
                                    'Suppliers' => 'suppliers',
                                    'Sale' => 'sales',
                                    'Purchase' => 'purchases',
                                    'Stock Transfer' => 'stock transfer',
                                    'Stock Adjustment' => 'stock movement',
                                    'Return Reference' => 'return reference',
                                ],
                            ];
                            $businessSubmenuModules = collect($businessSubmenuGroups)->flatMap(fn ($items) => array_values($items))->values()->all();
                            $payrollSubmenuGroups = [
                                'Payroll' => [
                                    'Salary Template' => 'salary template',
                                    'Salary Manage' => 'salary',
                                    'Loan Management' => 'loan',
                                    'Payslip' => 'payslip',
                                    'Advance Salary' => 'advance salary',
                                    'Commission' => 'commission',
                                ],
                            ];
                            $payrollSubmenuModules = collect($payrollSubmenuGroups)->flatMap(fn ($items) => array_values($items))->values()->all();
                            $crmSubmenuGroups = [
                                'CRM' => [
                                    'CRM Dashboard / Deal Manager' => 'deal',
                                ],
                                'Lead' => [
                                    'Lead Source' => 'lead source',
                                    'Lead Manager' => 'lead manager',
                                    'Lead Followup' => 'lead followup',
                                    'Lead Reminder' => 'lead reminder',
                                ],
                                'Management' => [
                                    'Contract Type / Contracts' => 'contract',
                                    'Proposal Manager' => 'proposal',
                                    'Estimate' => 'estimate',
                                ],
                                'Project' => [
                                    'Project Category' => 'lead project category',
                                    'Projects' => 'lead project',
                                ],
                            ];
                            $crmSubmenuModules = collect($crmSubmenuGroups)->flatMap(fn ($items) => array_values($items))->values()->all();
                            $communicationsSubmenuGroups = [
                                'Reminder' => [
                                    'Ticket Department' => 'ticket department',
                                    'Support Ticket' => 'support ticket',
                                    'Notices' => 'notice',
                                ],
                                'Marketing' => [
                                    'Email / SMS / WhatsApp Marketing' => 'marketing',
                                ],
                            ];
                            $communicationsSubmenuModules = collect($communicationsSubmenuGroups)->flatMap(fn ($items) => array_values($items))->values()->all();
                            $settingsSubmenuGroups = [
                                'Settings' => [
                                    'SMS Templates / Site Settings / Company Settings / Device Settings / Invoice Settings / Demo Data' => 'company setting',
                                    'Role Settings' => 'role',
                                ],
                            ];
                            $settingsSubmenuModules = collect($settingsSubmenuGroups)->flatMap(fn ($items) => array_values($items))->values()->all();
                            $taskSubmenuGroups = [
                                'Task' => [
                                    'Workspace' => 'workspace',
                                    'Board' => 'board',
                                    'Column' => 'column',
                                    'Label' => 'label',
                                    'My Tasks' => 'task',
                                ],
                                'Project' => [
                                    'Task Projects' => 'task project',
                                ],
                            ];
                            $taskSubmenuModules = collect($taskSubmenuGroups)->flatMap(fn ($items) => array_values($items))->values()->all();
                            $requireSubmenuGroups = [
                                'Require' => [
                                    'Require Type / All Require / My Require / Report' => 'employee request',
                                ],
                            ];
                            $requireSubmenuModules = collect($requireSubmenuGroups)->flatMap(fn ($items) => array_values($items))->values()->all();
                            $userDashboardSubmenuGroups = [
                                'User & Dashboard' => [
                                    'Dashboard' => 'dashboard',
                                    'User Management' => 'users',
                                ],
                            ];
                            $userDashboardSubmenuModules = collect($userDashboardSubmenuGroups)->flatMap(fn ($items) => array_values($items))->values()->all();
                            $requireStandalonePermissions = [
                                'verify require assignment',
                                'escalate require assignment',
                                'approve employee request',
                                'disburse employee request',
                                'fulfill employee request',
                                'generate noc',
                            ];

                            $permissionModuleName = function ($name) use ($requireStandalonePermissions) {
                                $name = trim($name);
                                if ($name === 'view report' || str_ends_with($name, ' report')) {
                                    return 'report';
                                }
                                if (in_array($name, $requireStandalonePermissions, true)) {
                                    return 'employee request';
                                }
                                if (str_starts_with($name, 'view all ')) {
                                    return substr($name, strlen('view all '));
                                }
                                $parts = explode(' ', $name);
                                array_shift($parts);
                                return implode(' ', $parts);
                            };

                            $hiddenPermissionModules = ['branch', 'project category', 'project'];
                            $visiblePermissions = $permissions->reject(function ($item) use ($permissionModuleName, $hiddenPermissionModules) {
                                return in_array($permissionModuleName($item->name), $hiddenPermissionModules, true);
                            });

                            $groupedPermissions = $visiblePermissions->groupBy(function ($item) use ($permissionModuleName, $travelSubmenuModules, $hrmSubmenuModules, $accountsSubmenuModules, $businessSubmenuModules, $payrollSubmenuModules, $crmSubmenuModules, $communicationsSubmenuModules, $settingsSubmenuModules, $taskSubmenuModules, $requireSubmenuModules, $userDashboardSubmenuModules) {
                                $moduleName = $permissionModuleName($item->name);

                                if (in_array($moduleName, $travelSubmenuModules, true)) {
                                    return 'travel';
                                }

                                if (in_array($moduleName, $hrmSubmenuModules, true)) {
                                    return 'hrm';
                                }

                                if (in_array($moduleName, $accountsSubmenuModules, true)) {
                                    return 'accounts';
                                }

                                if (in_array($moduleName, $businessSubmenuModules, true)) {
                                    return 'business operations';
                                }

                                if (in_array($moduleName, $payrollSubmenuModules, true)) {
                                    return 'payroll';
                                }

                                if (in_array($moduleName, $crmSubmenuModules, true)) {
                                    return 'crm';
                                }

                                if (in_array($moduleName, $communicationsSubmenuModules, true)) {
                                    return 'communications';
                                }

                                if (in_array($moduleName, $settingsSubmenuModules, true)) {
                                    return 'settings';
                                }

                                if (in_array($moduleName, $taskSubmenuModules, true)) {
                                    return 'task module';
                                }

                                if (in_array($moduleName, $requireSubmenuModules, true)) {
                                    return 'require';
                                }

                                if (in_array($moduleName, $userDashboardSubmenuModules, true)) {
                                    return 'user dashboard';
                                }

                                return $moduleName;
                            });
                        @endphp

                        @foreach ($groupedPermissions as $moduleName => $items)
                            @php
                                $viewAllPermission = $items->first(function ($permission) {
                                    return str_starts_with(trim($permission->name), 'view all ');
                                });
                                $mainReportPermissions = ['view report', 'create report', 'edit report', 'delete report'];
                                $reportSubmenuItems = $items->filter(function ($permission) use ($mainReportPermissions) {
                                    $name = trim($permission->name);
                                    return str_ends_with($name, ' report')
                                        && !str_starts_with($name, 'view all ')
                                        && !in_array($name, $mainReportPermissions, true);
                                });
                                $travelSubmenuItems = collect();
                                $hrmSubmenuItems = collect();
                                $accountsSubmenuItems = collect();
                                $businessSubmenuItems = collect();
                                $payrollSubmenuItems = collect();
                                $crmSubmenuItems = collect();
                                $communicationsSubmenuItems = collect();
                                $settingsSubmenuItems = collect();
                                $taskSubmenuItems = collect();
                                $requireSubmenuItems = collect();
                                $userDashboardSubmenuItems = collect();

                                if ($moduleName === 'travel') {
                                    $travelSubmenuItems = collect($travelSubmenuGroups)->map(function ($groupItems) use ($items, $permissionModuleName) {
                                        return collect($groupItems)->map(function ($travelModuleName) use ($items, $permissionModuleName) {
                                            return $items->filter(function ($permission) use ($permissionModuleName, $travelModuleName) {
                                                return $permissionModuleName($permission->name) === $travelModuleName;
                                            });
                                        })->filter(function ($travelItems) {
                                            return $travelItems->isNotEmpty();
                                        });
                                    })->filter(function ($groupItems) {
                                        return $groupItems->isNotEmpty();
                                    });
                                }

                                if ($moduleName === 'hrm') {
                                    $hrmSubmenuItems = collect($hrmSubmenuGroups)->map(function ($groupItems) use ($items, $permissionModuleName) {
                                        return collect($groupItems)->map(function ($hrmModuleName) use ($items, $permissionModuleName) {
                                            return $items->filter(function ($permission) use ($permissionModuleName, $hrmModuleName) {
                                                return $permissionModuleName($permission->name) === $hrmModuleName;
                                            });
                                        })->filter(function ($hrmItems) {
                                            return $hrmItems->isNotEmpty();
                                        });
                                    })->filter(function ($groupItems) {
                                        return $groupItems->isNotEmpty();
                                    });
                                }

                                if ($moduleName === 'accounts') {
                                    $accountsSubmenuItems = collect($accountsSubmenuGroups)->map(function ($groupItems) use ($items, $permissionModuleName) {
                                        return collect($groupItems)->map(function ($accountsModuleName) use ($items, $permissionModuleName) {
                                            return $items->filter(function ($permission) use ($permissionModuleName, $accountsModuleName) {
                                                return $permissionModuleName($permission->name) === $accountsModuleName;
                                            });
                                        })->filter(fn ($accountsItems) => $accountsItems->isNotEmpty());
                                    })->filter(fn ($groupItems) => $groupItems->isNotEmpty());
                                }

                                if ($moduleName === 'business operations') {
                                    $businessSubmenuItems = collect($businessSubmenuGroups)->map(function ($groupItems) use ($items, $permissionModuleName) {
                                        return collect($groupItems)->map(function ($businessModuleName) use ($items, $permissionModuleName) {
                                            return $items->filter(function ($permission) use ($permissionModuleName, $businessModuleName) {
                                                return $permissionModuleName($permission->name) === $businessModuleName;
                                            });
                                        })->filter(fn ($businessItems) => $businessItems->isNotEmpty());
                                    })->filter(fn ($groupItems) => $groupItems->isNotEmpty());
                                }


                                if ($moduleName === 'payroll') {
                                    $payrollSubmenuItems = collect($payrollSubmenuGroups)->map(function ($groupItems) use ($items, $permissionModuleName) {
                                        return collect($groupItems)->map(function ($payrollModuleName) use ($items, $permissionModuleName) {
                                            return $items->filter(function ($permission) use ($permissionModuleName, $payrollModuleName) {
                                                return $permissionModuleName($permission->name) === $payrollModuleName;
                                            });
                                        })->filter(fn ($payrollItems) => $payrollItems->isNotEmpty());
                                    })->filter(fn ($groupItems) => $groupItems->isNotEmpty());
                                }

                                if ($moduleName === 'crm') {
                                    $crmSubmenuItems = collect($crmSubmenuGroups)->map(function ($groupItems) use ($items, $permissionModuleName) {
                                        return collect($groupItems)->map(function ($crmModuleName) use ($items, $permissionModuleName) {
                                            return $items->filter(function ($permission) use ($permissionModuleName, $crmModuleName) {
                                                return $permissionModuleName($permission->name) === $crmModuleName;
                                            });
                                        })->filter(fn ($crmItems) => $crmItems->isNotEmpty());
                                    })->filter(fn ($groupItems) => $groupItems->isNotEmpty());
                                }

                                if ($moduleName === 'communications') {
                                    $communicationsSubmenuItems = collect($communicationsSubmenuGroups)->map(function ($groupItems) use ($items, $permissionModuleName) {
                                        return collect($groupItems)->map(function ($communicationsModuleName) use ($items, $permissionModuleName) {
                                            return $items->filter(function ($permission) use ($permissionModuleName, $communicationsModuleName) {
                                                return $permissionModuleName($permission->name) === $communicationsModuleName;
                                            });
                                        })->filter(fn ($communicationsItems) => $communicationsItems->isNotEmpty());
                                    })->filter(fn ($groupItems) => $groupItems->isNotEmpty());
                                }

                                if ($moduleName === 'settings') {
                                    $settingsSubmenuItems = collect($settingsSubmenuGroups)->map(function ($groupItems) use ($items, $permissionModuleName) {
                                        return collect($groupItems)->map(function ($settingsModuleName) use ($items, $permissionModuleName) {
                                            return $items->filter(function ($permission) use ($permissionModuleName, $settingsModuleName) {
                                                return $permissionModuleName($permission->name) === $settingsModuleName;
                                            });
                                        })->filter(fn ($settingsItems) => $settingsItems->isNotEmpty());
                                    })->filter(fn ($groupItems) => $groupItems->isNotEmpty());
                                }

                                if ($moduleName === 'task module') {
                                    $taskSubmenuItems = collect($taskSubmenuGroups)->map(function ($groupItems) use ($items, $permissionModuleName) {
                                        return collect($groupItems)->map(function ($taskModuleName) use ($items, $permissionModuleName) {
                                            return $items->filter(function ($permission) use ($permissionModuleName, $taskModuleName) {
                                                return $permissionModuleName($permission->name) === $taskModuleName;
                                            });
                                        })->filter(fn ($taskItems) => $taskItems->isNotEmpty());
                                    })->filter(fn ($groupItems) => $groupItems->isNotEmpty());
                                }

                                if ($moduleName === 'require') {
                                    $requireSubmenuItems = collect($requireSubmenuGroups)->map(function ($groupItems) use ($items, $permissionModuleName) {
                                        return collect($groupItems)->map(function ($requireModuleName) use ($items, $permissionModuleName) {
                                            return $items->filter(function ($permission) use ($permissionModuleName, $requireModuleName) {
                                                return $permissionModuleName($permission->name) === $requireModuleName;
                                            });
                                        })->filter(fn ($requireItems) => $requireItems->isNotEmpty());
                                    })->filter(fn ($groupItems) => $groupItems->isNotEmpty());
                                }

                                if ($moduleName === 'user dashboard') {
                                    $userDashboardSubmenuItems = collect($userDashboardSubmenuGroups)->map(function ($groupItems) use ($items, $permissionModuleName) {
                                        return collect($groupItems)->map(function ($userDashboardModuleName) use ($items, $permissionModuleName) {
                                            return $items->filter(function ($permission) use ($permissionModuleName, $userDashboardModuleName) {
                                                return $permissionModuleName($permission->name) === $userDashboardModuleName;
                                            });
                                        })->filter(fn ($userDashboardItems) => $userDashboardItems->isNotEmpty());
                                    })->filter(fn ($groupItems) => $groupItems->isNotEmpty());
                                }
                            @endphp
                            <div class="permission-row flex flex-col md:flex-row items-start md:items-center px-6 py-4 border-b last:border-b-0">
                                <div class="w-full md:w-1/4">
                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $moduleName }}</span><br>
                                    <button type="button" class="select-all-row text-[10px] text-blue-500 font-bold uppercase">
                                        Select All
                                    </button>
                                </div>

                                <div class="w-full md:w-3/4">
                                    <div class="flex flex-wrap gap-4">
                                    @if ($viewAllPermission && !in_array($moduleName, ['report', 'travel', 'hrm', 'accounts', 'business operations', 'payroll', 'crm', 'communications', 'settings', 'task module', 'require', 'user dashboard'], true))
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="permissions[]" value="{{ $viewAllPermission->name }}"
                                                {{ in_array($viewAllPermission->name, $rolePermissions) ? 'checked' : '' }}
                                                class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">
                                                View All
                                            </span>
                                        </label>
                                    @endif

                                    @foreach ($items as $permission)
                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                        @php
                                            $permissionName = trim($permission->name);
                                            $label = explode(' ', $permissionName)[0];

                                            if ($moduleName === 'report' && $permissionName !== 'view report') {
                                                $label = trim((string) str($permissionName)->after('view ')->beforeLast(' report'));
                                            }
                                        @endphp
                                        @if ($moduleName === 'report')
                                            @continue
                                        @endif
                                        @if (in_array($moduleName, ['travel', 'hrm', 'accounts', 'business operations', 'payroll', 'crm', 'communications', 'settings', 'task module', 'require', 'user dashboard'], true))
                                            @continue
                                        @endif
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                                {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }}
                                                class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                            <span class="ml-2 text-sm text-gray-600 capitalize">
                                                {{ $label }}
                                            </span>
                                        </label>
                                    @endforeach
                                    </div>

                                    @if ($moduleName === 'report' && $reportSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>Report submenu</summary>
                                            <div class="mt-3 flex flex-wrap gap-4">
                                                @foreach ($reportSubmenuItems as $permission)
                                                    @php
                                                        $label = trim((string) str($permission->name)->after('view ')->beforeLast(' report'));
                                                    @endphp
                                                    <label class="inline-flex items-center cursor-pointer">
                                                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                                            {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }}
                                                            class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                        <span class="ml-2 text-sm text-gray-600 capitalize">
                                                            {{ $label }}
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @if ($moduleName === 'travel' && $travelSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>Travel submenu</summary>
                                            <div class="mt-3">
                                                @foreach ($travelSubmenuItems as $travelGroupName => $travelGroupItems)
                                                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-3 mb-1">{{ $travelGroupName }}</div>
                                                    @foreach ($travelGroupItems as $travelMenuLabel => $travelItems)
                                                        @php
                                                            $travelViewAllPermission = $travelItems->first(function ($permission) {
                                                                return str_starts_with(trim($permission->name), 'view all ');
                                                            });
                                                        @endphp
                                                        <div class="permission-submenu-row">
                                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-3">
                                                                <div class="w-full md:w-1/4">
                                                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $travelMenuLabel }}</span><br>
                                                                    <button type="button"
                                                                        class="select-all-submenu-row text-[10px] text-blue-500 font-bold uppercase">
                                                                        Select All
                                                                    </button>
                                                                </div>
                                                                <div class="w-full md:w-3/4 flex flex-wrap gap-4">
                                                                    @if ($travelViewAllPermission)
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]"
                                                                                value="{{ $travelViewAllPermission->name }}"
                                                                                {{ in_array($travelViewAllPermission->name, $rolePermissions) ? 'checked' : '' }}
                                                                                class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">
                                                                                View All
                                                                            </span>
                                                                        </label>
                                                                    @endif

                                                                    @foreach ($travelItems as $permission)
                                                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                                                        @php
                                                                            $permissionName = trim($permission->name);
                                                                            $label = explode(' ', $permissionName)[0];
                                                                        @endphp
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]"
                                                                                value="{{ $permission->name }}"
                                                                                {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }}
                                                                                class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 capitalize">
                                                                                {{ $label }}
                                                                            </span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @if ($moduleName === 'hrm' && $hrmSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>HRM submenu</summary>
                                            <div class="mt-3">
                                                @foreach ($hrmSubmenuItems as $hrmGroupName => $hrmGroupItems)
                                                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-3 mb-1">{{ $hrmGroupName }}</div>
                                                    @foreach ($hrmGroupItems as $hrmMenuLabel => $hrmItems)
                                                        @php
                                                            $hrmViewAllPermission = $hrmItems->first(function ($permission) {
                                                                return str_starts_with(trim($permission->name), 'view all ');
                                                            });
                                                        @endphp
                                                        <div class="permission-submenu-row">
                                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-3">
                                                                <div class="w-full md:w-1/4">
                                                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $hrmMenuLabel }}</span><br>
                                                                    <button type="button"
                                                                        class="select-all-submenu-row text-[10px] text-blue-500 font-bold uppercase">
                                                                        Select All
                                                                    </button>
                                                                </div>
                                                                <div class="w-full md:w-3/4 flex flex-wrap gap-4">
                                                                    @if ($hrmViewAllPermission)
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]"
                                                                                value="{{ $hrmViewAllPermission->name }}"
                                                                                {{ in_array($hrmViewAllPermission->name, $rolePermissions) ? 'checked' : '' }}
                                                                                class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">
                                                                                View All
                                                                            </span>
                                                                        </label>
                                                                    @endif

                                                                    @foreach ($hrmItems as $permission)
                                                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                                                        @php
                                                                            $permissionName = trim($permission->name);
                                                                            $label = explode(' ', $permissionName)[0];
                                                                        @endphp
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]"
                                                                                value="{{ $permission->name }}"
                                                                                {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }}
                                                                                class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 capitalize">
                                                                                {{ $label }}
                                                                            </span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @if ($moduleName === 'accounts' && $accountsSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>Accounts submenu</summary>
                                            <div class="mt-3">
                                                @foreach ($accountsSubmenuItems as $accountsGroupName => $accountsGroupItems)
                                                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-3 mb-1">{{ $accountsGroupName }}</div>
                                                    @foreach ($accountsGroupItems as $accountsMenuLabel => $accountsItems)
                                                        @php
                                                            $accountsViewAllPermission = $accountsItems->first(fn ($permission) => str_starts_with(trim($permission->name), 'view all '));
                                                        @endphp
                                                        <div class="permission-submenu-row">
                                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-3">
                                                                <div class="w-full md:w-1/4">
                                                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $accountsMenuLabel }}</span><br>
                                                                    <button type="button" class="select-all-submenu-row text-[10px] text-blue-500 font-bold uppercase">Select All</button>
                                                                </div>
                                                                <div class="w-full md:w-3/4 flex flex-wrap gap-4">
                                                                    @if ($accountsViewAllPermission)
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $accountsViewAllPermission->name }}" {{ in_array($accountsViewAllPermission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">View All</span>
                                                                        </label>
                                                                    @endif
                                                                    @foreach ($accountsItems as $permission)
                                                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 capitalize">{{ explode(' ', trim($permission->name))[0] }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @if ($moduleName === 'business operations' && $businessSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>Business Operations submenu</summary>
                                            <div class="mt-3">
                                                @foreach ($businessSubmenuItems as $businessGroupName => $businessGroupItems)
                                                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-3 mb-1">{{ $businessGroupName }}</div>
                                                    @foreach ($businessGroupItems as $businessMenuLabel => $businessItems)
                                                        @php
                                                            $businessViewAllPermission = $businessItems->first(fn ($permission) => str_starts_with(trim($permission->name), 'view all '));
                                                        @endphp
                                                        <div class="permission-submenu-row">
                                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-3">
                                                                <div class="w-full md:w-1/4">
                                                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $businessMenuLabel }}</span><br>
                                                                    <button type="button" class="select-all-submenu-row text-[10px] text-blue-500 font-bold uppercase">Select All</button>
                                                                </div>
                                                                <div class="w-full md:w-3/4 flex flex-wrap gap-4">
                                                                    @if ($businessViewAllPermission)
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $businessViewAllPermission->name }}" {{ in_array($businessViewAllPermission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">View All</span>
                                                                        </label>
                                                                    @endif
                                                                    @foreach ($businessItems as $permission)
                                                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 capitalize">{{ explode(' ', trim($permission->name))[0] }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @if ($moduleName === 'payroll' && $payrollSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>Payroll submenu</summary>
                                            <div class="mt-3">
                                                @foreach ($payrollSubmenuItems as $payrollGroupName => $payrollGroupItems)
                                                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-3 mb-1">{{ $payrollGroupName }}</div>
                                                    @foreach ($payrollGroupItems as $payrollMenuLabel => $payrollItems)
                                                        @php
                                                            $payrollViewAllPermission = $payrollItems->first(fn ($permission) => str_starts_with(trim($permission->name), 'view all '));
                                                        @endphp
                                                        <div class="permission-submenu-row">
                                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-3">
                                                                <div class="w-full md:w-1/4">
                                                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $payrollMenuLabel }}</span><br>
                                                                    <button type="button" class="select-all-submenu-row text-[10px] text-blue-500 font-bold uppercase">Select All</button>
                                                                </div>
                                                                <div class="w-full md:w-3/4 flex flex-wrap gap-4">
                                                                    @if ($payrollViewAllPermission)
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $payrollViewAllPermission->name }}" {{ in_array($payrollViewAllPermission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">View All</span>
                                                                        </label>
                                                                    @endif
                                                                    @foreach ($payrollItems as $permission)
                                                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 capitalize">{{ explode(' ', trim($permission->name))[0] }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @if ($moduleName === 'crm' && $crmSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>CRM submenu</summary>
                                            <div class="mt-3">
                                                @foreach ($crmSubmenuItems as $crmGroupName => $crmGroupItems)
                                                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-3 mb-1">{{ $crmGroupName }}</div>
                                                    @foreach ($crmGroupItems as $crmMenuLabel => $crmItems)
                                                        @php
                                                            $crmViewAllPermission = $crmItems->first(fn ($permission) => str_starts_with(trim($permission->name), 'view all '));
                                                        @endphp
                                                        <div class="permission-submenu-row">
                                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-3">
                                                                <div class="w-full md:w-1/4">
                                                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $crmMenuLabel }}</span><br>
                                                                    <button type="button" class="select-all-submenu-row text-[10px] text-blue-500 font-bold uppercase">Select All</button>
                                                                </div>
                                                                <div class="w-full md:w-3/4 flex flex-wrap gap-4">
                                                                    @if ($crmViewAllPermission)
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $crmViewAllPermission->name }}" {{ in_array($crmViewAllPermission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">View All</span>
                                                                        </label>
                                                                    @endif
                                                                    @foreach ($crmItems as $permission)
                                                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 capitalize">{{ explode(' ', trim($permission->name))[0] }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @if ($moduleName === 'communications' && $communicationsSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>Communications submenu</summary>
                                            <div class="mt-3">
                                                @foreach ($communicationsSubmenuItems as $communicationsGroupName => $communicationsGroupItems)
                                                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-3 mb-1">{{ $communicationsGroupName }}</div>
                                                    @foreach ($communicationsGroupItems as $communicationsMenuLabel => $communicationsItems)
                                                        @php
                                                            $communicationsViewAllPermission = $communicationsItems->first(fn ($permission) => str_starts_with(trim($permission->name), 'view all '));
                                                        @endphp
                                                        <div class="permission-submenu-row">
                                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-3">
                                                                <div class="w-full md:w-1/4">
                                                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $communicationsMenuLabel }}</span><br>
                                                                    <button type="button" class="select-all-submenu-row text-[10px] text-blue-500 font-bold uppercase">Select All</button>
                                                                </div>
                                                                <div class="w-full md:w-3/4 flex flex-wrap gap-4">
                                                                    @if ($communicationsViewAllPermission)
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $communicationsViewAllPermission->name }}" {{ in_array($communicationsViewAllPermission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">View All</span>
                                                                        </label>
                                                                    @endif
                                                                    @foreach ($communicationsItems as $permission)
                                                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 capitalize">{{ explode(' ', trim($permission->name))[0] }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @if ($moduleName === 'settings' && $settingsSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>Settings submenu</summary>
                                            <div class="mt-3">
                                                @foreach ($settingsSubmenuItems as $settingsGroupName => $settingsGroupItems)
                                                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-3 mb-1">{{ $settingsGroupName }}</div>
                                                    @foreach ($settingsGroupItems as $settingsMenuLabel => $settingsItems)
                                                        @php
                                                            $settingsViewAllPermission = $settingsItems->first(fn ($permission) => str_starts_with(trim($permission->name), 'view all '));
                                                        @endphp
                                                        <div class="permission-submenu-row">
                                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-3">
                                                                <div class="w-full md:w-1/4">
                                                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $settingsMenuLabel }}</span><br>
                                                                    <button type="button" class="select-all-submenu-row text-[10px] text-blue-500 font-bold uppercase">Select All</button>
                                                                </div>
                                                                <div class="w-full md:w-3/4 flex flex-wrap gap-4">
                                                                    @if ($settingsViewAllPermission)
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $settingsViewAllPermission->name }}" {{ in_array($settingsViewAllPermission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">View All</span>
                                                                        </label>
                                                                    @endif
                                                                    @foreach ($settingsItems as $permission)
                                                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 capitalize">{{ explode(' ', trim($permission->name))[0] }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @if ($moduleName === 'task module' && $taskSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>Task submenu</summary>
                                            <div class="mt-3">
                                                @foreach ($taskSubmenuItems as $taskGroupName => $taskGroupItems)
                                                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-3 mb-1">{{ $taskGroupName }}</div>
                                                    @foreach ($taskGroupItems as $taskMenuLabel => $taskItems)
                                                        @php
                                                            $taskViewAllPermission = $taskItems->first(fn ($permission) => str_starts_with(trim($permission->name), 'view all '));
                                                        @endphp
                                                        <div class="permission-submenu-row">
                                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-3">
                                                                <div class="w-full md:w-1/4">
                                                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $taskMenuLabel }}</span><br>
                                                                    <button type="button" class="select-all-submenu-row text-[10px] text-blue-500 font-bold uppercase">Select All</button>
                                                                </div>
                                                                <div class="w-full md:w-3/4 flex flex-wrap gap-4">
                                                                    @if ($taskViewAllPermission)
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $taskViewAllPermission->name }}" {{ in_array($taskViewAllPermission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">View All</span>
                                                                        </label>
                                                                    @endif
                                                                    @foreach ($taskItems as $permission)
                                                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 capitalize">{{ explode(' ', trim($permission->name))[0] }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @if ($moduleName === 'require' && $requireSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>Require submenu</summary>
                                            <div class="mt-3">
                                                @foreach ($requireSubmenuItems as $requireGroupName => $requireGroupItems)
                                                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-3 mb-1">{{ $requireGroupName }}</div>
                                                    @foreach ($requireGroupItems as $requireMenuLabel => $requireItems)
                                                        @php
                                                            $requireViewAllPermission = $requireItems->first(fn ($permission) => str_starts_with(trim($permission->name), 'view all '));
                                                        @endphp
                                                        <div class="permission-submenu-row">
                                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-3">
                                                                <div class="w-full md:w-1/4">
                                                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $requireMenuLabel }}</span><br>
                                                                    <button type="button" class="select-all-submenu-row text-[10px] text-blue-500 font-bold uppercase">Select All</button>
                                                                </div>
                                                                <div class="w-full md:w-3/4 flex flex-wrap gap-4">
                                                                    @if ($requireViewAllPermission)
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $requireViewAllPermission->name }}" {{ in_array($requireViewAllPermission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">View All</span>
                                                                        </label>
                                                                    @endif
                                                                    @foreach ($requireItems as $permission)
                                                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                                                        @php
                                                                            $permissionName = trim($permission->name);
                                                                            $label = in_array($permissionName, $requireStandalonePermissions, true)
                                                                                ? trim((string) str($permissionName)->before(' employee request')->before(' require assignment'))
                                                                                : explode(' ', $permissionName)[0];
                                                                        @endphp
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 capitalize">{{ $label }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @if ($moduleName === 'user dashboard' && $userDashboardSubmenuItems->isNotEmpty())
                                        <details class="permission-submenu mt-3">
                                            <summary>User & Dashboard submenu</summary>
                                            <div class="mt-3">
                                                @foreach ($userDashboardSubmenuItems as $userDashboardGroupName => $userDashboardGroupItems)
                                                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-3 mb-1">{{ $userDashboardGroupName }}</div>
                                                    @foreach ($userDashboardGroupItems as $userDashboardMenuLabel => $userDashboardItems)
                                                        @php
                                                            $userDashboardViewAllPermission = $userDashboardItems->first(fn ($permission) => str_starts_with(trim($permission->name), 'view all '));
                                                        @endphp
                                                        <div class="permission-submenu-row">
                                                            <div class="flex flex-col md:flex-row items-start md:items-center gap-3">
                                                                <div class="w-full md:w-1/4">
                                                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ $userDashboardMenuLabel }}</span><br>
                                                                    <button type="button" class="select-all-submenu-row text-[10px] text-blue-500 font-bold uppercase">Select All</button>
                                                                </div>
                                                                <div class="w-full md:w-3/4 flex flex-wrap gap-4">
                                                                    @if ($userDashboardViewAllPermission)
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $userDashboardViewAllPermission->name }}" {{ in_array($userDashboardViewAllPermission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 font-semibold capitalize">View All</span>
                                                                        </label>
                                                                    @endif
                                                                    @foreach ($userDashboardItems as $permission)
                                                                        @continue(str_starts_with(trim($permission->name), 'view all '))
                                                                        <label class="inline-flex items-center cursor-pointer">
                                                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }} class="row-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded">
                                                                            <span class="ml-2 text-sm text-gray-600 capitalize">{{ explode(' ', trim($permission->name))[0] }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end mt-8">
                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-8 py-2 rounded-md shadow-md transition">
                            Update Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2();

            // Function to update the button text (Select All vs Deselect All)
            function updateButtonText(row) {
                const button = row.find('.select-all-row');
                const checkboxes = row.find('.row-checkbox');
                const allChecked = checkboxes.length > 0 && checkboxes.length === checkboxes.filter(':checked').length;
                
                button.text(allChecked ? 'Deselect All' : 'Select All');
            }

            // Run on Page Load to set initial button states
            $('.permission-row').each(function() {
                updateButtonText($(this));
            });

            // Handle Click on "Select All / Deselect All" button
            $('.select-all-row').on('click', function(e) {
                e.preventDefault();
                const row = $(this).closest('.permission-row');
                const checkboxes = row.find('.row-checkbox');
                
                // If all are checked, uncheck them. Otherwise, check all.
                const allChecked = checkboxes.length === checkboxes.filter(':checked').length;
                checkboxes.prop('checked', !allChecked);
                
                updateButtonText(row);
            });

            // Handle individual checkbox changes
            $('.row-checkbox').on('change', function() {
                const row = $(this).closest('.permission-row');
                updateButtonText(row);
            });

            $('.select-all-submenu-row').on('click', function(e) {
                e.preventDefault();
                const row = $(this).closest('.permission-submenu-row');
                const checkboxes = row.find('.row-checkbox');
                const allChecked = checkboxes.length === checkboxes.filter(':checked').length;

                checkboxes.prop('checked', !allChecked);
                $(this).text(allChecked ? 'Select All' : 'Deselect All');
                updateButtonText($(this).closest('.permission-row'));
            });
        });
    </script>
@endsection

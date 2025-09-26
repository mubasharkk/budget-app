{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

{{-- User Management --}}
<x-backpack::menu-item title="Users" icon="la la-users" :link="backpack_url('user')" />

{{-- Content Management --}}
<x-backpack::menu-item title="Categories" icon="la la-tags" :link="backpack_url('category')" />
<x-backpack::menu-item title="Receipts" icon="la la-receipt" :link="backpack_url('receipt')" />
<x-backpack::menu-item title="Receipt Items" icon="la la-list" :link="backpack_url('receipt-item')" />

{{-- Role & Permission Management --}}
<x-backpack::menu-item title="Roles" icon="la la-user-shield" :link="backpack_url('role')" />
<x-backpack::menu-item title="Permissions" icon="la la-key" :link="backpack_url('permission')" />

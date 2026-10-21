<div class="vertical-menu">

    <div data-simplebar class="h-100">
        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title">{{ transWord('Menu') }}</li>
                <li>
                    <a href="{{ route('dashboard') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('Dashboard') }}</span>
                    </a>
                </li>

                @can('show_roles')
                    <li>
                        <a href="{{ route('roles') }}" class="waves-effect">
                            <i class="ri-dashboard-line"></i>
                            <span>{{ transWord('Roles') }}</span>
                        </a>
                    </li>
                @endcan

                @can('show_permissions')
                    <li>
                        <a href="{{ route('permissions') }}" class="waves-effect">
                            <i class="ri-dashboard-line"></i>
                            <span>{{ transWord('Permissions') }}</span>
                        </a>
                    </li>
                @endcan


                @can('show_users')
                    <li>
                        <a href="javascript: void(0);" class="has-arrow waves-effect">
                            <i class="ri-store-2-line"></i>
                            <span>{{ transWord('Users') }}</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            @can('create_users')
                                <li><a href="{{ route('create_users') }}">{{ transWord('New User') }}</a></li>
                            @endcan
                            <li><a href="{{ route('users') }}">{{ transWord('Users') }}</a></li>
                            <li><a href="{{ route('show_wallets') }}">{{ transWord('Wallets') }}</a></li>
                            <li><a href="{{ route('pending_deposit') }}">{{ transWord('Pending Deposit') }}</a></li>
                            <li><a href="{{ route('approved_deposit') }}">{{ transWord('Approved Deposit') }}</a></li>
                            <li><a href="{{ route('pending_withdrawal') }}">{{ transWord('Pending Withdrawal') }}</a></li>
                            <li><a href="{{ route('approved_withdrawal') }}">{{ transWord('Approved Withdrawal') }}</a></li>
                            <li><a href="{{ route('show_usdts') }}">{{ transWord('USDT') }}</a></li>
                        </ul>
                    </li>
                @endcan

                @can('show_sliders')
                    <li>
                        <a href="javascript: void(0);" class="has-arrow waves-effect">
                            <i class="fa fa-image"></i>
                            <span>{{ transWord('Sliders') }}</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            @can('create_sliders')
                                <li><a href="{{ route('create_slider_banners') }}">{{ transWord('New Slider') }}</a></li>
                            @endcan
                            <li><a href="{{ route('slider_banners') }}">{{ transWord('Sliders') }}</a></li>
                        </ul>
                    </li>
                @endcan
                
                @can('show_pages')
                <li>
                    <a href="{{ route('show_pages') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('Pages') }}</span>
                    </a>
                </li>
                @endcan
                
                

                @can('show_blogs')
                    <li>
                        <a href="javascript: void(0);" class="has-arrow waves-effect">
                            <i class="fa fa-file"></i>
                            <span>{{ transWord('Blogs') }}</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            @can('create_blogs')
                                <li><a href="{{ route('create_blogs') }}">{{ transWord('New Blog') }}</a></li>
                            @endcan
                            <li><a href="{{ route('blogs') }}">{{ transWord('Blogs') }}</a></li>
                        </ul>
                    </li>
                @endcan

                @can('show_faqs')
                <li>
                    <a href="{{ route('show_faq_settings') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('FAQs') }}</span>
                    </a>
                </li>
                @endcan

                @can('show_product_category')
                <li>
                    <a href="{{ route('show_product_category') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('Product Category') }}</span>
                    </a>
                </li>
                @endcan
                
                @can('show_product')
                <li>
                    <a href="{{ route('show_product') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('Products') }}</span>
                    </a>
                </li>
                @endcan

                
                @can('show_orders')
                    <li>
                        <a href="javascript: void(0);" class="has-arrow waves-effect">
                            <i class="ri-store-2-line"></i>
                            <span>{{ transWord('Orders') }}</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            <!-- @can('create_orders')
                                <li><a href="{{ route('create_orders') }}">{{ transWord('New Order') }}</a></li>
                            @endcan -->
                            <li><a href="{{ route('show_orders') }}">{{ transWord('Orders') }}</a></li>
                        </ul>
                    </li>
                @endcan



    
                @can('show_tickets')
                <li>
                    <a href="{{ route('show_tickets') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('Tickets') }}</span>
                    </a>
                </li>
                @endcan
                
                @can('show_homepage_social_media')
                <li>
                    <a href="{{ route('show_social_settings') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('Social Media') }}</span>
                    </a>
                </li>
                @endcan
                
                @can('show_settings')
                <li>
                    <a href="{{ route('mainsettings') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('Main Settings') }}</span>
                    </a>
                </li>
                @endcan

                @can('show_settings')
                <li>
                    <a href="{{ route('appsettings') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('App Settings') }}</span>
                    </a>
                </li>
                @endcan
                @can('show_settings')
                <li>
                    <a href="{{ route('payment_methods.index') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('Payment Methods') }}</span>
                    </a>
                </li>
                @endcan

                <li>
                    <a href="{{ route('language_settings') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('Languages') }}</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('clear_cache') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        <span>{{ transWord('Clear Cache') }}</span>
                    </a>
                </li>

            </ul>
        </div>
        <!-- Sidebar -->
    </div>
</div>

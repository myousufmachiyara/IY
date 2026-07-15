<aside id="sidebar-left" class="sidebar-left">
  <div class="sidebar-header">
    <div class="sidebar-title" style="display: flex; justify-content: space-between;">
      <a href="{{ route('dashboard') }}" class="logo">
        <img src="/assets/img/billtrix-logo-1.png" class="sidebar-logo" alt="Vehicle Bidding Logo" />
      </a>
      <div class="d-md-none toggle-sidebar-left col-1"
           data-toggle-class="sidebar-left-opened"
           data-target="html"
           data-fire-event="sidebar-left-opened">
        <i class="fas fa-times" aria-label="Toggle sidebar"></i>
      </div>
    </div>
    <div class="sidebar-toggle d-none d-md-block"
         data-toggle-class="sidebar-left-collapsed"
         data-target="html"
         data-fire-event="sidebar-left-toggle">
      <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
    </div>
  </div>

  <div class="nano">
    <div class="nano-content">
      <nav id="menu" class="nav-main" role="navigation">
        <ul class="nav nav-main">

          {{-- Dashboard --}}
          <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('dashboard') }}">
              <i class="fa fa-home" aria-hidden="true"></i>
              <span>Dashboard</span>
            </a>
          </li>

          {{-- Team --}}
          @if(auth()->user()->can('team.index') || auth()->user()->can('user_roles.index'))
          <li class="nav-parent {{ request()->routeIs('team.*') || request()->routeIs('roles.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#">
              <i class="fa fa-user-shield"></i>
              <span>Team</span>
            </a>
            <ul class="nav nav-children">
              @can('team.index')
                <li class="{{ request()->routeIs('team.*') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('team.index') }}">All Members</a>
                </li>
              @endcan
              @can('user_roles.index')
                <li class="{{ request()->routeIs('roles.*') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('roles.index') }}">Roles &amp; Permissions</a>
                </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- Customers & Vehicles --}}
          @if(auth()->user()->can('customers.index') || auth()->user()->can('vehicles.index'))
          <li class="nav-parent {{ request()->routeIs('customers.*') || request()->routeIs('vehicles.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#">
              <i class="fa fa-users"></i>
              <span>Customers</span>
            </a>
            <ul class="nav nav-children">
              @can('customers.index')
                <li class="{{ request()->routeIs('customers.*') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('customers.index') }}">All Customers</a>
                </li>
              @endcan
              @can('vehicles.index')
                <li class="{{ request()->routeIs('vehicles.*') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('vehicles.index') }}">Vehicle Requirements</a>
                </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- Bidding --}}
          @if(auth()->user()->can('bid_sheets.index') || auth()->user()->can('bids.index'))
          <li class="nav-parent {{ request()->routeIs('bid-sheets.*') || request()->routeIs('bids.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#">
              <i class="fa fa-gavel"></i>
              <span>Bidding</span>
            </a>
            <ul class="nav nav-children">
              @can('bid_sheets.index')
                <li class="{{ request()->routeIs('bid-sheets.*') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('bid-sheets.index') }}">My Bid Sheets</a>
                </li>
              @endcan
              @can('bids.index')
                <li class="{{ request()->routeIs('bids.index') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('bids.index') }}">Merge &amp; Export</a>
                </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- Bidding Results --}}
          @can('results.index')
          <li class="{{ request()->routeIs('results.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('results.index') }}">
              <i class="fa fa-trophy"></i>
              <span>Bidding Results</span>
            </a>
          </li>
          @endcan

          {{-- Invoices --}}
          @can('invoices.index')
          <li class="{{ request()->routeIs('invoices.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('invoices.index') }}">
              <i class="fa fa-file-invoice-dollar"></i>
              <span>Invoices</span>
            </a>
          </li>
          @endcan

          {{-- Shipments --}}
          @can('shipments.index')
          <li class="{{ request()->routeIs('shipments.*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('shipments.index') }}">
              <i class="fa fa-ship"></i>
              <span>Shipments</span>
            </a>
          </li>
          @endcan

          {{-- Payments & Expenses --}}
          @if(auth()->user()->can('payments.index') || auth()->user()->can('vendor_payments.index') || auth()->user()->can('expenses.index'))
          <li class="nav-parent {{ request()->routeIs('payments.*') || request()->routeIs('vendor-payments.*') || request()->routeIs('expenses.*') ? 'nav-expanded nav-active' : '' }}">
              <a class="nav-link" href="#">
                  <i class="fa fa-money-bill-wave"></i>
                  <span>Payments &amp; Expenses</span>
              </a>
              <ul class="nav nav-children">
                  @can('payments.index')
                      <li class="{{ request()->routeIs('payments.*') && !request()->routeIs('payments.customer_ledger') ? 'active' : '' }}">
                          <a class="nav-link" href="{{ route('payments.index') }}">All Payments</a>
                      </li>
                  @endcan
                  @can('vendor_payments.index')
                      <li class="{{ request()->routeIs('vendor-payments.*') ? 'active' : '' }}">
                          <a class="nav-link" href="{{ route('vendor-payments.index') }}">Vendor Payments</a>
                      </li>
                  @endcan
                  @can('expenses.index')
                      <li class="{{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                          <a class="nav-link" href="{{ route('expenses.index') }}">Expenses</a>
                      </li>
                  @endcan
              </ul>
          </li>
          @endif

          {{-- Accounting --}}
          @can('accounting.index')
          <li class="nav-parent {{ request()->routeIs('accounting.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#">
              <i class="fa fa-book"></i>
              <span>Accounting</span>
            </a>
            <ul class="nav nav-children">
              <li class="{{ request()->routeIs('accounting.chart') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('accounting.chart') }}">Chart of Accounts</a>
              </li>
              <li class="{{ request()->routeIs('accounting.journal') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('accounting.journal') }}">Journal</a>
              </li>
              <li class="{{ request()->routeIs('accounting.cash_bank') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('accounting.cash_bank') }}">Cash &amp; Bank Book</a>
              </li>
              <li class="{{ request()->routeIs('accounting.receivables') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('accounting.receivables') }}">Receivables</a>
              </li>
              <li class="{{ request()->routeIs('accounting.payables') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('accounting.payables') }}">Payables</a>
              </li>
              <li class="{{ request()->routeIs('accounting.profit_loss') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('accounting.profit_loss') }}">Profit &amp; Loss</a>
              </li>
            </ul>
          </li>
          @endcan

          {{-- Reports --}}
          @if(auth()->user()->can('reports.agent_wise') || auth()->user()->can('reports.vendor_wise') || auth()->user()->can('reports.bid_wise') || auth()->user()->can('reports.bid_won'))
          <li class="nav-parent {{ request()->routeIs('reports.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#">
              <i class="fa fa-chart-bar"></i>
              <span>Reports</span>
            </a>
            <ul class="nav nav-children">
              @can('reports.agent_wise')
                <li class="{{ request()->routeIs('reports.agent_wise') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('reports.agent_wise') }}">Agent-wise</a>
                </li>
              @endcan
              @can('reports.vendor_wise')
                <li class="{{ request()->routeIs('reports.vendor_wise') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('reports.vendor_wise') }}">Vendor-wise</a>
                </li>
              @endcan
              @can('reports.bid_wise')
                <li class="{{ request()->routeIs('reports.bid_wise') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('reports.bid_wise') }}">Bid-wise</a>
                </li>
              @endcan
              @can('reports.bid_won')
                <li class="{{ request()->routeIs('reports.bid_won') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('reports.bid_won') }}">Bid Won</a>
                </li>
              @endcan
            </ul>
          </li>
          @endif

        </ul>
      </nav>
    </div>

    <script>
      if (typeof localStorage !== 'undefined') {
        if (localStorage.getItem('sidebar-left-position') !== null) {
          var sidebarLeft = document.querySelector('#sidebar-left .nano-content');
          sidebarLeft.scrollTop = localStorage.getItem('sidebar-left-position');
        }
      }
    </script>
  </div>
</aside>
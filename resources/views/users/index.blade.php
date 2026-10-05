@extends('layouts.master')

@section('title', __('sidebar.Assign Roles to Users'))

@push('styles')
  <style>
    .settings-user-roles .hero-metric {
      background-color: rgba(255, 255, 255, 0.14);
      border: 1px solid rgba(255, 255, 255, 0.28);
      border-radius: 0.9rem;
      padding: 1rem 1.25rem;
      backdrop-filter: blur(8px);
      height: 100%;
    }

    .settings-user-roles .hero-metric .metric-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: rgba(255, 255, 255, 0.75);
      margin-bottom: 0.35rem;
      display: block;
    }

    .settings-user-roles .hero-metric .metric-value {
      font-weight: 700;
      font-size: 1.35rem;
      color: #fff;
      margin: 0;
    }

    .settings-user-roles .search-input-group .form-control {
      min-height: 44px;
    }

    .settings-user-roles .search-input-group .input-group-text {
      border-inline-end: 0;
    }

    .settings-user-roles .search-input-group .form-control {
      border-inline-start: 0;
    }

    .settings-user-roles .stat-list {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .settings-user-roles .stat-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 0;
      border-bottom: 1px solid var(--bs-border-color);
    }

    .settings-user-roles .stat-item:last-child {
      border-bottom: 0;
    }

    .settings-user-roles .stat-icon {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .settings-user-roles .results-alert {
      border-radius: 0.9rem;
    }

    .settings-user-roles .empty-state {
      padding: 3rem 1rem;
    }

    @media (max-width: 991.98px) {
      .settings-user-roles .hero-metric {
        padding: 0.85rem 1rem;
      }
    }

    @media (max-width: 767.98px) {
      .settings-user-roles .card .p-4 > .d-flex {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
        text-align: start;
      }

      .settings-user-roles .card .p-4 > .d-flex .text-end {
        width: 100%;
        text-align: inherit !important;
      }

      .settings-user-roles .card .d-flex.align-items-center.gap-3 {
        flex-wrap: wrap;
      }

      .settings-user-roles .search-input-group {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
      }

      .settings-user-roles .search-input-group > .input-group-text,
      .settings-user-roles .search-input-group > .form-control,
      .settings-user-roles .search-input-group > .btn {
        width: 100%;
        border-radius: 0.75rem !important;
      }

      .settings-user-roles .search-input-group > .input-group-text {
        justify-content: flex-start;
      }

      .settings-user-roles .stat-item {
        align-items: flex-start;
      }

      .settings-user-roles .results-alert {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
      }
    }

    @media (max-width: 575.98px) {
      .settings-user-roles .hero-metric {
        text-align: start;
      }

      .settings-user-roles .search-input-group > .btn {
        margin-top: 0.25rem;
      }
    }
  </style>
@endpush

@section('content')
  @php
    $locale = app()->getLocale();
    $localeRoot = strtolower(strtok($locale, '_'));
    $rtlLocales = ['ar', 'he'];
    $isRtl = in_array($localeRoot, $rtlLocales, true);
    $withRolesPercentage = $totalUsers > 0 ? round(($usersWithRoles / $totalUsers) * 100) : 0;
    $withoutRolesPercentage = $totalUsers > 0 ? max(0, min(100, 100 - $withRolesPercentage)) : 0;
  @endphp

  <div class="container-xxl py-4 settings-user-roles" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">@lang('sidebar.Dashboard')</a></li>
        <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">@lang('sidebar.Settings')</a></li>
        <li class="breadcrumb-item active" aria-current="page">@lang('sidebar.Assign Roles to Users')</li>
      </ol>
    </nav>

    <div class="card border-0 shadow-sm overflow-hidden mb-4">
      <div class="p-4 position-relative text-white" style="background: linear-gradient(135deg, var(--bs-primary) 0%, #0d6efd 50%, #6f42c1 100%);">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div class="d-flex align-items-center gap-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-25" style="width: 64px; height: 64px;">
              <i class="bi bi-people fs-3"></i>
            </span>
            <div>
              <p class="text-uppercase fw-semibold small mb-1" style="letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.75);">
                @lang('users.Users Overview')
              </p>
              <h1 class="h3 mb-2">@lang('sidebar.Assign Roles to Users')</h1>
              <p class="mb-0" style="color: rgba(255, 255, 255, 0.8);">@lang('users.Assign Roles Description')</p>
            </div>
          </div>
          <div class="text-end">
            <span class="badge rounded-pill bg-white text-primary fw-semibold">
              <i class="bi bi-shield-lock me-1"></i>
              @lang('users.Manage Roles')
            </span>
          </div>
        </div>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3 mt-3">
          <div class="col">
            <div class="hero-metric">
              <span class="metric-label">@lang('users.Total Users')</span>
              <p class="metric-value">{{ number_format($totalUsers) }}</p>
            </div>
          </div>
          <div class="col">
            <div class="hero-metric">
              <span class="metric-label">@lang('users.With Roles')</span>
              <p class="metric-value">{{ number_format($usersWithRoles) }}</p>
            </div>
          </div>
          <div class="col">
            <div class="hero-metric">
              <span class="metric-label">@lang('users.Without Roles')</span>
              <p class="metric-value">{{ number_format($usersWithoutRoles) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 align-items-start">
      <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-4">
              <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary" style="width: 48px; height: 48px;">
                <i class="bi bi-funnel"></i>
              </span>
              <div>
                <h5 class="mb-1">@lang('users.Search Users')</h5>
                <p class="text-muted small mb-0">@lang('users.Assign Roles Description')</p>
              </div>
            </div>

            @can('register')
              <a href="{{ route('register') }}" class="btn btn-primary w-100 mb-4">
                <i class="bi bi-person-plus me-1"></i> @lang('users.Add User')
              </a>
            @endcan

            <form method="GET" action="{{ route('users.index') }}" class="mb-4">
              <label for="user-search" class="form-label fw-semibold">@lang('users.Search Users')</label>
              <div class="input-group search-input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input
                  type="search"
                  id="user-search"
                  name="search"
                  class="form-control"
                  placeholder="@lang('users.Search Users Placeholder')"
                  value="{{ $searchTerm }}"
                >
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-search me-1"></i>@lang('users.Search Users')
                </button>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-2">
                <small class="text-muted">@lang('users.Search Users Help')</small>
                @if ($hasSearch)
                  <a href="{{ route('users.index') }}" class="btn btn-link btn-sm px-0">@lang('users.Clear Search')</a>
                @endif
              </div>
            </form>

            <div class="mb-4">
              <h6 class="text-uppercase text-muted fs-6 mb-3">@lang('users.Users Overview')</h6>
              <ul class="stat-list">
                <li class="stat-item">
                  <span class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></span>
                  <div>
                    <div class="fw-semibold">@lang('users.Total Users')</div>
                    <div class="text-muted">{{ number_format($totalUsers) }}</div>
                  </div>
                </li>
                <li class="stat-item">
                  <span class="stat-icon bg-success-subtle text-success"><i class="bi bi-check2-circle"></i></span>
                  <div>
                    <div class="fw-semibold">@lang('users.With Roles')</div>
                    <div class="text-muted">{{ number_format($usersWithRoles) }}</div>
                  </div>
                </li>
                <li class="stat-item">
                  <span class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-exclamation-circle"></i></span>
                  <div>
                    <div class="fw-semibold">@lang('users.Without Roles')</div>
                    <div class="text-muted">{{ number_format($usersWithoutRoles) }}</div>
                  </div>
                </li>
              </ul>

              <div class="border rounded-3 p-3 bg-body-tertiary mt-3">
                <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                  <span>@lang('users.With Roles')</span>
                  <span>{{ $withRolesPercentage }}%</span>
                </div>
                <div class="progress" style="height: 6px;">
                  <div class="progress-bar bg-success" role="progressbar" style="width: {{ $withRolesPercentage }}%;" aria-valuenow="{{ $withRolesPercentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center text-muted small mt-2">
                  <span>@lang('users.Without Roles')</span>
                  <span>{{ $withoutRolesPercentage }}%</span>
                </div>
              </div>
            </div>

            <div class="alert alert-info border-0 shadow-sm small mb-0" role="alert">
              <div class="fw-semibold mb-1">@lang('users.Tip Title')</div>
              <div>@lang('users.Tip Description')</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white border-0 pb-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
              <div class="d-flex align-items-center gap-2">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary" style="width: 40px; height: 40px;">
                  <i class="bi bi-people"></i>
                </span>
                <div>
                  <h5 class="mb-0">@lang('users.Users')</h5>
                  <small class="text-muted">@lang('users.Assign Roles Description')</small>
                </div>
              </div>
              <span class="badge bg-primary-subtle text-primary fw-semibold">
                {{ number_format($filteredCount) }}
              </span>
            </div>
          </div>
          <div class="card-body">
            @if ($users->count())
              <div class="alert alert-light border results-alert d-flex align-items-center gap-2 small mb-3" role="alert">
                <i class="bi bi-info-circle text-primary"></i>
                <span>
                  @if ($hasSearch)
                    {{ trans_choice('users.Results Summary For Query', $filteredCount, ['count' => number_format($filteredCount), 'query' => $searchTerm]) }}
                  @else
                    {{ trans_choice('users.Results Summary', $filteredCount, ['count' => number_format($filteredCount)]) }}
                  @endif
                </span>
              </div>

              <x-table head-class="table-light">
                <x-slot name="head">
                  <tr>
                    <th style="width: 70px;">#</th>
                    <th>@lang('users.Name')</th>
                    <th>@lang('users.Email')</th>
                    <th>@lang('users.Roles')</th>
                    <th class="text-end">@lang('users.Actions')</th>
                  </tr>
                </x-slot>

                @foreach ($users as $u)
                  <tr>
                    <td>{{ $u->id }}</td>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>
                      @forelse ($u->roles as $r)
                        <span class="badge text-bg-secondary me-1">{{ $r->name }}</span>
                      @empty
                        <span class="text-muted">—</span>
                      @endforelse
                    </td>
                    <td class="text-end">
                      <x-button.edit href="{{ route('users.roles.edit', $u) }}" size="sm">
                        @lang('users.Manage Roles')
                      </x-button.edit>
                    </td>
                  </tr>
                @endforeach
              </x-table>
            @else
              <div class="empty-state text-center">
                <div class="mb-3">
                  <span class="display-5 text-muted"><i class="bi bi-person-slash"></i></span>
                </div>
                <h5 class="mb-2">
                  @if ($hasSearch)
                    @lang('users.No users match your search.')
                  @else
                    @lang('users.Empty State Title')
                  @endif
                </h5>
                <p class="text-muted mb-4">
                  @if ($hasSearch)
                    @lang('users.No users match query', ['query' => $searchTerm])
                  @else
                    @lang('users.Empty State Description')
                  @endif
                </p>
                @if ($hasSearch)
                  <a href="{{ route('users.index') }}" class="btn btn-outline-primary btn-sm">@lang('users.Clear Search')</a>
                @endif
              </div>
            @endif
          </div>
          @if ($users->hasPages())
            <div class="card-footer bg-white border-0">
              {{ $users->links() }}
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection

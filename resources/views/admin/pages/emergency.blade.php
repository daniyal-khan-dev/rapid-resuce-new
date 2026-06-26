@extends('admin.layouts.admin')
@section('title', 'Emergency Requests')
@section('page_title', 'Emergency Requests')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    <div class="adm-page-header">
        <div>
            <h2>Emergency Requests</h2>
            <p>Active (Pending &amp; In Progress) requests only. Completed &amp; cancelled rides are in <a href="{{ route('admin.emergency.past-rides') }}" style="color:#60a5fa;text-decoration:none;font-weight:600;">Past Rides <i class="fa fa-arrow-right" style="font-size:.75em;"></i></a></p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="status-pill status-1" style="font-size:0.8rem;">
                {{ $requests->total() }} Total
            </span>
        </div>
    </div>

    <div class="card">
        @if ($requests->isEmpty())
            <div class="adm-empty">
                <i class="fa fa-inbox"></i>
                <p>No emergency requests yet.</p>
            </div>
        @else
            <div class="pgd-scroll">
                <table class="table table-hover mb-0" id="reqTable">
                    <thead>
                        <tr>
                            <th class="ps-4">RREB ID</th>
                            <th>User / Mobile</th>
                            <th>Hospital</th>
                            <th>Pickup Address</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Ambulance</th>
                            <th>Driver</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $r)
                            <tr class="pgd-row" data-req-id="{{ $r->id }}">
                                <td class="ps-4">
                                    <span
                                        style="font-family:monospace;font-size:0.8rem;background:rgba(255,255,255,0.06);padding:3px 8px;border-radius:6px;color:rgba(255,255,255,0.75);white-space:nowrap;">
                                        {{ $r->rreb_id ?? '—' }}
                                    </span>
                                </td>
                                <td class="fs-xs" style="color:var(--adm-muted);">
                                    <div>{{ $r->user?->details?->first_name ?? 'Guest' }}</div>
                                    <small class="adm-muted">{{ $r->mobile_no }}</small>
                                </td>
                                <td class="fs-xs" style="color:var(--adm-muted);">{{ Str::limit($r->hospital_name, 22) }}
                                </td>
                                <td class="fs-xs" style="color:var(--adm-muted);">{{ Str::limit($r->pickup_address, 22) }}
                                </td>
                                <td>
                                    @if ($r->type === '1')
                                        <span class="status-pill status-4">Emergency</span>
                                    @elseif ($r->type === '2')
                                        <span class="status-pill status-3">Non-Emergency</span>
                                    @else
                                        <span class="status-pill status-3">{{ $r->type }}</span>
                                    @endif
                                </td>
                                <td data-status-cell>
                                    @php
                                        $statusMap = [
                                            '1' => ['label' => 'Pending', 'class' => 'status-3'],
                                            '2' => ['label' => 'Dispatched', 'class' => 'status-2'],
                                            '3' => ['label' => 'En Route', 'class' => 'status-2'],
                                            '4' => ['label' => 'Arrived', 'class' => 'status-1'],
                                            '5' => ['label' => 'Transporting', 'class' => 'status-2'],
                                            '6' => ['label' => 'Completed', 'class' => 'status-1'],
                                            '7' => ['label' => 'Cancelled', 'class' => 'status-4'],
                                        ];
                                        $st = $statusMap[$r->status] ?? [
                                            'label' => ucfirst($r->status),
                                            'class' => 'status-3',
                                        ];
                                    @endphp
                                    <span class="status-pill {{ $st['class'] }}">{{ $st['label'] }}</span>
                                </td>
                                <td class="fs-xs" style="color:var(--adm-muted);" data-ambulance-cell>
                                    {{ $r->ambulance?->vehicle_number ?? '—' }}</td>
                                <td class="fs-xs" style="color:var(--adm-muted);" data-driver-cell>{{ $r->driver?->name ?? '—' }}</td>
                                <td class="fs-xs" style="color:var(--adm-muted);white-space:nowrap;">
                                    {{ $r->created_at->format('d M, H:i') }}</td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn-adm-icon" title="View / Dispatch"
                                            onclick="viewRequest({{ $r->id }})">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        @if (!in_array($r->status, ['6', '7']))
                                            <button class="btn-adm-icon btn-adm-icon--danger" title="Delete"
                                                onclick="deleteRequest({{ $r->id }})">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>


        @endif
    </div>

    <div class="modal fade" id="reqDetailModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <span class="modal-title-icon"><i class="fa fa-circle-question"></i></span>
                        Request Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" id="reqDetailBody">
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
    <script src="{{ asset('assets/admin/js/emergency.js') }}"></script>

    <script>
        window.adminRoutes.requestsShow          = "{{ url('admin/emergency/show') }}";
        window.adminRoutes.requestsDispatch      = "{{ url('admin/emergency/dispatch') }}";
        window.adminRoutes.requestsDelete        = "{{ url('admin/emergency/delete') }}";
        window.adminRoutes.requestsNearbyDrivers = "{{ url('admin/emergency/nearby-drivers') }}";
        window.reqAmbulances = @json($ambulances->map(fn($a) => ['id' => $a->id, 'label' => $a->vehicle_number . ' — ' . $a->type]));
        window.reqDrivers = @json($drivers->map(fn($d) => ['id' => $d->id, 'label' => $d->name . ' — ' . $d->phone]));
    </script>
@endpush

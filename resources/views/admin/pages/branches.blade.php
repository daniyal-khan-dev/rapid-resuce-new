@extends('admin.layouts.admin')
@section('title', 'Branches')
@section('page_title', 'Branches')

@section('content')
    <div class="adm-page-header">
        <div>
            <h2>Branch Locations</h2>
            <p>Manage the branch addresses shown on the homepage.</p>
        </div>

        <button class="btn-adm-primary" onclick="openAddBranchModal()">
            <i class="fa fa-plus"></i> Add Branch
        </button>
    </div>

    <div class="card">
        <div class="pgd-scroll">
            <table class="table table-hover mb-0" id="branchTable">
                <thead>
                    <tr>
                        <th class="ps-4">#</th>
                        <th>Branch Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="branchTbody">
                    @forelse ($branches as $branch)
                        <tr data-branch-id="{{ $branch->id }}">
                            <td class="ps-4 fs-xs" style="color:var(--adm-muted);">{{ $loop->iteration }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="adm-icon-preview"
                                        style="width:30px;height:30px;border-radius:50%;font-size:0.75rem;flex-shrink:0;">
                                        <i class="fa fa-building"></i>
                                    </div>
                                    <strong>{{ $branch->name }}</strong>
                                </div>
                            </td>
                            <td style="color:var(--adm-muted);font-size:0.85rem;max-width:280px;">
                                {{ Str::limit($branch->address, 20) }}
                            </td>
                            <td class="fs-xs" style="color:var(--adm-muted);">{{ $branch->phone }}</td>
                            <td class="fs-xs" style="color:var(--adm-muted);">{{ $branch->email }}</td>
                            <td>
                                <span class="status-pill {{ $branch->status == '2' ? 'status-4' : 'status-1' }}">
                                    @if ($branch->status == 1)
                                        Active
                                    @elseif ($branch->status == 2)
                                        Inactive
                                    @endif
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn-adm-icon btn-adm-icon--edit" title="Edit"
                                        onclick="openEditBranchModal({{ $branch->id }}, {{ Js::from(['id' => $branch->id, 'name' => $branch->name, 'address' => $branch->address, 'phone' => $branch->phone, 'email' => $branch->email, 'status' => $branch->status, 'added_by' => $branch->added_by, 'updated_by' => $branch->updated_by, 'created_at' => $branch->created_at, 'updated_at' => $branch->updated_at]) }})">
                                        <i class="fa fa-pen"></i>
                                    </button>
                                    <button class="btn-adm-icon btn-adm-icon--danger" title="Delete"
                                        onclick="deleteBranch({{ $branch->id }})">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="branchNoResults">
                            <td colspan="7" class="text-center py-4" style="color:var(--adm-muted);">
                                <i class="fa fa-building me-2"></i>No branches yet. Click "Add Branch" to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="branchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="branchModalTitle">
                        <span class="modal-title-icon"><i class="fa fa-building"></i></span>
                        Add Branch
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <form id="branchForm">
                        @csrf
                        <input type="hidden" id="branch_id" name="branch_id">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                                <input type="text" id="branch_name" name="branch_name" class="form-control"
                                    placeholder="e.g. Main Branch" maxlength="30" oninput="allowOnlyLetters(this)">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Address <span class="text-danger">*</span></label>
                                <input type="text" id="branch_address" name="branch_address" class="form-control"
                                    placeholder="Full address" maxlength="100" oninput="allowAlphaNumericCommaDot(this)">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="text" id="branch_phone" name="branch_phone" class="form-control"
                                    placeholder="e.g. +92 300 1234567" maxlength="13" oninput="validatePakPhone(this)">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" id="branch_email" name="branch_email" class="form-control"
                                    placeholder="e.g. branch@example.com" maxlength="100">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="status" class="form-select">
                                    <option value="0">Select Status</option>
                                    <option value="1">Active</option>
                                    <option value="2">Inctive</option>
                                </select>
                            </div>
                        </div>
                    </form>

                    <div id="auditTrail">
                        <div class="form-section-divider"><i class="fa fa-clock-rotate-left"></i> Record Info</div>
                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <div class="audit-label">Added By</div>
                                <div id="auditAddedBy" class="audit-data">—</div>
                            </div>
                            <div class="col-6">
                                <div class="audit-label">Added At</div>
                                <div id="auditAddedAt" class="audit-data">—</div>
                            </div>
                            <div class="col-6 audit-upd" style="display:none;">
                                <div class="audit-label">Updated By</div>
                                <div id="auditUpdatedBy" class="audit-data">—</div>
                            </div>
                            <div class="col-6 audit-upd" style="display:none;">
                                <div class="audit-label">Updated At</div>
                                <div id="auditUpdatedAt" class="audit-data">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="branchSubmitBtn" onclick="saveBranch()">
                        <i class="fa fa-check me-1"></i> Save Branch
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window._rrPageModule = 'branch';
        window.branchesRoutes = {
            branchAdd: "{{ route('admin.branch.add') }}",
            branchUpdate: "{{ url('admin/branch/update') }}",
            branchDelete: "{{ url('admin/branch/delete') }}",
        };
    </script>
    <script src="{{ asset('assets/admin/js/branches.js') }}"></script>
@endpush

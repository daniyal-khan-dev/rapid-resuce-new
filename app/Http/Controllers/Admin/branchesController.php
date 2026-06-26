<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class branchesController extends Controller
{
    public function index()
    {
        $branches = Branch::orderBy('id', 'ASC')->get();
        return view('admin.pages.branches', compact('branches'));
    }

    public function add(Request $request): JsonResponse
    {
        $request->validate([
            'branch_name'    => 'required|string|max:30',
            'branch_address' => 'required|string|max:100',
            'branch_phone'   => 'required|string|max:13',
            'branch_email'   => 'required|email|max:100',
            'status'         => 'required|string',
        ], [
            'branch_name.required' => 'Branch name is required.',
            'branch_name.string'   => 'Branch name must be valid text.',
            'branch_name.max'      => 'Branch name cannot exceed 30 characters.',
            'branch_address.required' => 'Branch address is required.',
            'branch_address.string'   => 'Branch address must be valid text.',
            'branch_address.max'      => 'Branch address cannot exceed 100 characters.',
            'branch_phone.required' => 'Branch phone number is required.',
            'branch_phone.string'   => 'Branch phone number must be valid text.',
            'branch_phone.max'      => 'Branch phone number cannot exceed 13 characters.',
            'branch_email.required' => 'Branch email address is required.',
            'branch_email.email'    => 'Please enter a valid email address.',
            'branch_email.max'      => 'Branch email cannot exceed 100 characters.',
            'status.required' => 'Status is required.',
            'status.string'   => 'Status must be valid text.',
        ]);
            
        DB::beginTransaction();
        try {
            $actor = Auth::guard('admin')->user();
            $branch = Branch::create([
                'name'     => $request->branch_name,
                'address'  => $request->branch_address,
                'phone'    => $request->branch_phone,
                'email'    => $request->branch_email,
                'status'   => $request->status,
                'added_by' => $actor->username,
            ]);

            DB::commit();
            logHistory($actor->username, request()->ip(), "Added branch: {$branch->name}");
            return response()->json([
                'success' => true,
                'message' => 'Branch added successfully.',
                'branch'  => $branch,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'branch_name'    => 'required|string|max:30',
            'branch_address' => 'required|string|max:100',
            'branch_phone'   => 'required|string|max:13',
            'branch_email'   => 'required|email|max:100',
            'status'         => 'required|string',
        ], [
            'branch_name.required' => 'Branch name is required.',
            'branch_name.string'   => 'Branch name must be valid text.',
            'branch_name.max'      => 'Branch name cannot exceed 30 characters.',
            'branch_address.required' => 'Branch address is required.',
            'branch_address.string'   => 'Branch address must be valid text.',
            'branch_address.max'      => 'Branch address cannot exceed 100 characters.',
            'branch_phone.required' => 'Branch phone number is required.',
            'branch_phone.string'   => 'Branch phone number must be valid text.',
            'branch_phone.max'      => 'Branch phone number cannot exceed 13 characters.',
            'branch_email.required' => 'Branch email address is required.',
            'branch_email.email'    => 'Please enter a valid email address.',
            'branch_email.max'      => 'Branch email cannot exceed 100 characters.',
            'status.required' => 'Status is required.',
            'status.string'   => 'Status must be valid text.',
        ]);
            
        DB::beginTransaction();
        try {
            $actor = Auth::guard('admin')->user();
            $branch = Branch::findOrFail($id);
            $branch->update([
                'name'       => $request->branch_name,
                'address'    => $request->branch_address,
                'phone'      => $request->branch_phone,
                'email'      => $request->branch_email,
                'status'     => $request->status,
                'updated_by' => $actor->username,
            ]);

            DB::commit();
            logHistory($actor->username, request()->ip(), "Updated branch: {$branch->name}");
            return response()->json([
                'success' => true,
                'message' => 'Branch updated successfully.',
                'branch'  => $branch,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function delete($id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $name   = $branch->name;
        $branch->delete();
        $actor = Auth::guard('admin')->user();
        logHistory($actor->username, request()->ip(), "Deleted branch: {$name}");
        return response()->json(['success' => true, 'message' => 'Branch deleted successfully.']);
    }
}

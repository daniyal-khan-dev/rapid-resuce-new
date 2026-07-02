<?php

namespace App\Http\Controllers\Admin;

use App\Events\ContentUpdated;
use App\Http\Controllers\Controller;
use App\Models\Driver\Driver;
use App\Models\EmergencyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DriverManagementController extends Controller
{
    public function index()
    {
        $drivers = Driver::withCount([
            'emergencyRequests as total_jobs',
            'emergencyRequests as completed_jobs' => fn ($q) => $q->where('status', '6'),
        ])->latest()->get();
        return view('admin.pages.drivers', compact('drivers'));
    }
    
    public function checkUsername(Request $request): JsonResponse
    {
        $username  = trim($request->input('username', ''));
        $excludeId = $request->input('exclude_id');

        if (!$username) {
            return response()->json(['available' => false, 'message' => 'Username is required.']);
        }

        $query = Driver::where('username', $username);
        if ($excludeId) {
            $query->where('id', '!=', (int) $excludeId);
        }

        $taken = $query->exists();

        return response()->json([
            'available' => !$taken,
            'message'   => $taken ? 'Username is already taken.' : 'Username is available.',
        ]);
    }

    public function add(Request $request): JsonResponse
    {
        $request->validate([
            'name'       => 'required|string|max:50',
            'username'   => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_.]+$/', 'unique:drivers,username'],
            'email'      => 'required|email|unique:drivers,email',
            'phone'      => 'required|regex:/^03[0-9]{9}$/',
            'license_no' => 'required|string|max:30',
            'password'   => 'required|min:6',
            'status'     => 'required|in:1,2',
            'photo'      => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'name.required'       => 'Driver name is required.',
            'username.required'   => 'Username is required.',
            'username.regex'      => 'Username may only contain lowercase letters (a-z), numbers, underscore (_) and dot (.).',
            'username.unique'     => 'This username is already taken.',
            'email.required'      => 'Email address is required.',
            'email.email'         => 'Please enter a valid email address.',
            'email.unique'        => 'This email is already registered.',
            'phone.required'      => 'Phone number is required.',
            'phone.regex'         => 'Enter a valid Pakistani number (03XXXXXXXXX).',
            'license_no.required' => 'License number is required.',
            'password.required'   => 'Password is required.',
            'password.min'        => 'Password must be at least 6 characters long.',
            'status.required'     => 'Please select a driver status.',
            'status.in'           => 'The selected driver status is invalid.',
            'photo.required'      => 'Driver photo is required.',
            'photo.image'         => 'Please upload a valid image.',
            'photo.mimes'         => 'Photo must be a JPG, JPEG, PNG, or WebP file.',
            'photo.max'           => 'Photo size cannot exceed 2 MB.',
        ]);
            
        DB::beginTransaction();
        try {
            $file      = $request->file('photo');
            $photoName = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('assets/driver/img'), $photoName);
            $admin = Auth::guard('admin')->user();

            $driver = Driver::create([
                'name'       => $request->name,
                'username'   => $request->username,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'password'   => Hash::make($request->password),
                'license_no' => $request->license_no,
                'photo'      => $photoName,
                'status'     => $request->status,
                'added_by'   => $admin->username,
            ]);

            DB::commit();
            $drivers = Driver::withCount([
                'emergencyRequests as total_jobs',
                'emergencyRequests as completed_jobs' => fn ($q) => $q->where('status', '6'),
            ])->find($driver->id);
            
            
            logHistory($admin->username, $request->ip(), "Added driver: {$drivers->name} ({$drivers->email})");
            try { broadcast(new ContentUpdated('driver', 'added', $drivers->toArray(), $admin->name)); } catch (\Throwable $ignored) {}
            return response()->json([
                'success'   => true,
                'message'   => 'Driver added successfully.',
                'driver'    => $drivers
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'name'       => 'required|string|max:50',
            'username'   => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_.]+$/', Rule::unique('drivers', 'username')->ignore($id)],
            'email'      => ['required', 'email', Rule::unique('drivers', 'email')->ignore($id)],
            'phone'      => 'required|regex:/^03[0-9]{9}$/',
            'license_no' => 'required|string|max:30',
            'status'     => 'required|in:1,2',
            'password'   => 'nullable|min:6',
            'photo'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'name.required'       => 'Driver name is required.',
            'name.max'            => 'Driver name cannot exceed 50 characters.',
            'username.required'   => 'Username is required.',
            'username.max'        => 'Username cannot exceed 50 characters.',
            'username.regex'      => 'Username may only contain lowercase letters (a-z), numbers, underscore (_) and dot (.).',
            'username.unique'     => 'This username is already taken.',
            'email.required'      => 'Email address is required.',
            'email.email'         => 'Please enter a valid email address.',
            'email.unique'        => 'This email is already registered.',
            'phone.required'      => 'Phone number is required.',
            'phone.regex'         => 'Enter a valid Pakistani number (03XXXXXXXXX).',
            'license_no.required' => 'License number is required.',
            'license_no.max'      => 'License number cannot exceed 30 characters.',
            'status.required'     => 'Please select a driver status.',
            'status.in'           => 'The selected driver status is invalid.',
            'password.min'        => 'Password must be at least 6 characters long.',
            'photo.image'         => 'Please upload a valid image.',
            'photo.mimes'         => 'Photo must be a JPG, JPEG, PNG, or WebP file.',
            'photo.max'           => 'Photo size cannot exceed 2 MB.',
        ]);
            
        DB::beginTransaction();
        try {
            $driver   = Driver::findOrFail($id);
            $photoUrl = null;
            $admin = Auth::guard('admin')->user();

            // Block availability change while driver is on an active ride
            if ($driver->availability == '2') {
                $hasActiveRide = EmergencyRequest::where('driver_id', $driver->id)->whereNotIn('status', ['6', '7'])->exists();
                if ($hasActiveRide) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'This driver is currently on an active ride and cannot update.',
                    ], 422);
                }
            }

            if ($request->hasFile('photo')) {
                // Delete old photo
                if ($driver->photo && $driver->photo !== 'default.jpg') {
                    $oldPath = public_path('assets/driver/img/' . $driver->photo);
            
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                // Upload new photo
                $file = $request->file('photo');
                $photoName = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('assets/driver/img'), $photoName);
                $driver->photo = $photoName;
                $photoUrl = asset('assets/driver/img/' . $photoName);
            }

            $driver->name       = $request->name;
            $driver->username   = $request->username;
            $driver->email      = $request->email;
            $driver->phone      = $request->phone;
            $driver->license_no = $request->license_no;
            $driver->status     = $request->status;
            $driver->updated_by   = $admin->username;
            if ($request->filled('password')) {
                $driver->password = Hash::make($request->password);
            }
            $driver->save();
            DB::commit();

            $drivers = Driver::withCount([
                'emergencyRequests as total_jobs',
                'emergencyRequests as completed_jobs' => fn ($q) => $q->where('status', '6'),
            ])->find($driver->id);

            logHistory($admin->username, $request->ip(), "Updated driver: {$drivers->name} — status: {$drivers->availability}");
            try { broadcast(new ContentUpdated('driver', 'updated', $drivers->fresh()->toArray(), $admin->name)); } catch (\Throwable $ignored) {}
            return response()->json([
                'success'   => true,
                'message'   => 'Driver updated successfully.',
                'driver'    => $drivers
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function delete($id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $driver     = Driver::findOrFail($id);

            // Block deletion while driver has an active ride or is On Duty
            $hasActiveRide = EmergencyRequest::where('driver_id', $driver->id)
                ->whereNotIn('status', ['6', '7'])
                ->exists();

            if ($hasActiveRide || $driver->availability === '2') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Driver cannot be deleted while assigned to an active ride.',
                ], 422);
            }

            if ($driver->photo && $driver->photo !== 'default.jpg') {
                $photoPath = public_path('assets/driver/img/' . $driver->photo);
            
                if (file_exists($photoPath)) {
                    @unlink($photoPath);
                }
            }
            $drivers = Driver::withCount([
                'emergencyRequests as total_jobs',
                'emergencyRequests as completed_jobs' => fn ($q) => $q->where('status', '6'),
            ])->find($driver->id);
            $driver->delete();

            $admin = Auth::guard('admin')->user();
            
            logHistory($admin->username, request()->ip(), "Deleted driver: {$drivers->name}");
            DB::commit();
            try { broadcast(new ContentUpdated('driver', 'deleted', ['id' => $drivers->id], $admin->name)); } catch (\Throwable $ignored) {}
            return response()->json([
                'success'   => true,
                'message'   => 'Driver removed successfully.',
                'driver'    => $drivers
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

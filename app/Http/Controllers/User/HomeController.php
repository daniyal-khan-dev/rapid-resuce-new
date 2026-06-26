<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Admin\Ambulance;
use App\Models\Admin\Faq;
use App\Models\Admin\Service;
use App\Models\Admin\Testimonial;
use App\Models\Admin\Branch;
use App\Models\User\ContactMessage;
use App\Models\EmergencyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    private function noCache($response)
    {
        return $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')->header('Pragma', 'no-cache')->header('Expires', '0');
    }

    public function index()
    {
        $services = Service::whereStatus(1)->get();
        $testimonials = Testimonial::whereStatus(1)->get();
        $faqs = Faq::whereStatus(1)->get();
        $fleetAmbs    = Ambulance::whereNotNull('card_title')->orderBy('type')->get();
        $branches     = Branch::orderBy('id','ASC')->get();
        $contactInfo = Branch::orderBy('id', 'asc')->first();
        return $this->noCache(response()->view('user.pages.index', compact('services','testimonials','faqs','fleetAmbs','branches', 'contactInfo')));
    }

    public function profile()
    {
        $user = Auth::guard('users')->user();
        $userDetail = $user->details;
        $medicalCard = $user->medicalCard;
        $contactMessages = ContactMessage::where('user_id', $user->id)->latest()->get();
        $myBookings = EmergencyRequest::with(['ambulance'])->where('user_id', $user->id)->latest()->get();

        if (!$userDetail) {
            $userDetail = $user->details()->create([
                'first_name' => $user->username,
                'last_name'  => '',
                'email'      => '',
                'phone'      => '',
            ]);
        }

        return $this->noCache(
            response()->view('user.pages.profile', compact('user', 'userDetail', 'medicalCard', 'contactMessages', 'myBookings'))
        );
    }

    public function rtAmbulances(): \Illuminate\Http\JsonResponse
    {
        $items = Ambulance::whereNotNull('card_title')->orderBy('type')->get();
        return response()->json(['items' => $items]);
    }

    public function rtTestimonials(): \Illuminate\Http\JsonResponse
    {
        $items = Testimonial::whereStatus(1)->get();
        return response()->json(['items' => $items]);
    }

    public function tracking(Request $request, $id)
    {
        $req = EmergencyRequest::with(['ambulance', 'driver'])->findOrFail($id);
        return $this->noCache(response()->view('user.pages.tracking', compact('req')));
    }

    public function firstAid()
    {
        return view('user.pages.first_aid');
    }

    public function terms()
    { 
        $contactInfo = Branch::orderBy('id', 'asc')->first();
        return $this->noCache(response()->view('user.pages.terms', compact('contactInfo')));
    }
    public function privacy()
    {
        $contactInfo = Branch::orderBy('id', 'asc')->first();
        return $this->noCache(response()->view('user.pages.privacy', compact('contactInfo')));
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Cookie;

use App\Models\ReferralLink;

class ReferralController extends Controller
{
    public function CreateReferralLink(Request $request)
    {
        $refId = time();

        $ref = new ReferralLink();

        $ref->ref_id = $refId;
        $ref->creator = Auth::user()->id;
        $ref->type = $request->type;
        $ref->to = $request->to;

        if (!empty($request->comment))
        {
            $ref->comment = $request->comment;
        }

        $ref->save();

        return $refId;
    }

    public function GetReferralLinksByType(Request $request, $type)
    {
        if ($request->personal)
        {
            $links = ReferralLink::where('type', $type)->where('creator', Auth::user()->id)->get();
        }
        else 
        {
            $links = ReferralLink::where('type', $type)->get();
        }
        $result = [];

        foreach ($links as $link)
        {
            $result[] = [
                'ref_id' => $link->ref_id,
                'link' => config('app.ref')."/link/".$link->ref_id,
                'count' => $link->count,
                'requests' => $link->requests,
                'sum' => $link->sum,
                'comment' => $link->comment,
            ];
        }

        return $result;
    }

    public function DeleteReferralLink(Request $request, $ref)
    {
        ReferralLink::where('ref_id', $ref)->delete();
    }

    public function RedirectReferralLink(Request $request, $ref)
    {
        $referralLink = ReferralLink::where('ref_id', $ref)->first();
        $referralLink->count = $referralLink->count + 1;
        $referralLink->save();

        Cookie::queue('ref_id', $referralLink->ref_id, 60);

        return redirect($referralLink->to);
    }

    public function InviteUser(Request $request, $userId)
    {
        //$request->session()->put('invite', $userId);
        Cookie::queue('invite_user', $userId, 60);

        return redirect(config('app.reg'));
    }

    public function Session(Request $request)
    {
        //return $request->session()->all();
        return $request->cookie('invite_user');
    }
}

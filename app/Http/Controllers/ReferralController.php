<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $request->session()->push('ref', $referralLink->ref_id);

        return redirect($referralLink->to);
    }
}

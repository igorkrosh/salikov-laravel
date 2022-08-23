<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\Promocode;
use App\Models\Order;

class PromocodeController extends Controller
{
    public function CreatePromocode(Request $request)
    {
        $promocode = new Promocode();

        $promocode->code = $request->code;
        $promocode->value = $request->value;
        $promocode->date_start = $this->ConvertDate($request->date_start);
        $promocode->deadline = $this->ConvertDate($request->deadline);

        $promocode->save();
    }

    public function GetPromocode(Request $request)
    {
        $promocode = Promocode::where('code', $request->promocode)->first();

        if (empty($promocode))
        {
            return response()->json([
                'message' => 'Промокод не найден'
            ], 422);
        }

        $isPast = Carbon::parse($promocode->deadline)->isPast();

        if ($isPast)
        {
            return response()->json([
                'message' => 'Промокод не действителен'
            ], 422);
        }

        return $promocode->value;
    }

    public function GetAllPromocodes(Request $request)
    {
        $promocodes = Promocode::get();
        $result = [];

        foreach($promocodes as $promocode)
        {
            $count = count(Order::where('promocode', $promocode->code)->get());

            $result[] = [
                'id' => $promocode->id,
                'code' => $promocode->code,
                'value' => $promocode->value,
                'count' => $count,
                'sum' => $count * $promocode->value,
                'date_start' => $promocode->date_start,
                'deadline' => $promocode->deadline
            ];
        }

        return $result;
    }

    public function DeletePromocode(Request $request, $id)
    {
        Promocode::where('id', $id)->delete();
    }
}

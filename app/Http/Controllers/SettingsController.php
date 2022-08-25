<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Setting;

class SettingsController extends Controller
{
    public function GetSettings()
    {
        return Setting::get();
    }

    public function UpdateSettings(Request $request)
    {
        foreach ($request->settings as $newSetting)
        {
            $oldSetting = Setting::where('key', $newSetting['key'])->first();
            $oldSetting->value = $newSetting['value'];
            $oldSetting->save();
        }
    }
}

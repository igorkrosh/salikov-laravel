<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

use App\Models\Webinar;
use App\Models\Course;
use App\Models\CourseBlock;
use App\Models\ModuleStream;
use App\Models\ModuleVideo;
use App\Models\ModuleJob;
use App\Models\ModuleTest;

class WebinarController extends Controller
{
    public function CreateWebinar(Request $request)
    {
        $newWebinar = new Webinar();

        $request->validate([
            'name' => ['required'],
            'authors' => ['required'],
            'date_start' => ['required'],
        ]);

        $newWebinar->name = $request->name;
        $newWebinar->creator = Auth::user()->id;
        $newWebinar->authors = $request->authors;
        $newWebinar->link = $request->link;
        $newWebinar->date_start = $this->ConvertDate($request->date_start);

        $newWebinar->save();

        if (!empty($request->image))
        {
            $filePath = $this->LoadImage($request->image, $newWebinar->id, 'images/webinars/cover/');
            $newWebinar->image_path = $filePath;
        }

        $newWebinar->save();

        return $newWebinar;
    }

    public function EditWebinar(Request $request, $webinarId)
    {
        $webinar = Webinar::where('id', $webinarId)->first();

        $webinar->name = $request->name;
        $webinar->authors = $request->authors;
        $webinar->link = $request->link;
        $webinar->date_start = $this->ConvertDate($request->date_start);

        if (!empty($request->image))
        {
            $filePath = $this->LoadImage($request->image, $webinar->id, 'images/webinars/cover/');
            $webinar->image_path = $filePath;
        }

        $webinar->save();

        return $webinar;
    }

    public function GetWebinarsByUser(Request $request)
    {
        $user_id = Auth::user()->id;

        $courses = Webinar::where('creator', $user_id)->get();
        $result = [];

        foreach ($courses as $course)
        {
            $result[] = [
                'id' => $course->id,
                'image' => url('/').'/'.$course->image_path,
                'lectors' => $course->authors,
                'title' => $course->name,
                'type' => 'Вебинар',
            ];
        }

        return $result;
    }

    public function GetWebinarById(Request $request, $webinarId)
    {
        $webinar = Webinar::where('id', $webinarId)->first();

        $result = [
            'id' => $webinar->id,
            'name' => $webinar->name,
            'authors' => $webinar->authors,
            'date_start' => $webinar->date_start,
            'image' => url('/').'/'.$webinar->image_path,
            'link' => $webinar->link,
        ];

        return $result;
    }

    public function GetStreams(Request $request)
    {
        $user_id = Auth::user()->id;

        $webinars = Webinar::where('creator', $user_id)->get();
        $courses = Course::where('creator', $user_id)->get();
        $stream = [];

        foreach ($courses as $course)
        {
            $modules = $this->GetModules($course->id);

            $stream = array_merge($stream, $modules['stream']);
        }

        $result = [];

        foreach ($stream as $item)
        {
            $result[] = [
                'title' => $item->title,
                'date' => $item->date_start,
                'lectors' => $item->authors,
                'type' => 'stream',
            ];
        }

        foreach($webinars as $item)
        {
            $result[] = [
                'title' => $item->name,
                'date' => $item->date_start,
                'lectors' => $item->authors,
                'type' => 'webinar',
            ];
        }

        usort($result, function($a, $b){
            return $a['date'] <=> $b['date'];
        });

        return $result;
    }

    public function DeleteWebinar(Request $request, $webinarId)
    {
        Webinar::where('id', $webinarId)->delete();

        if (Storage::disk('public')->exists("images/webinars/cover/$webinarId.jpeg"))
        {
            Storage::disk('public')->delete("images/webinars/cover/$webinarId.jpeg");
        }

        if (Storage::disk('public')->exists("images/webinars/cover/$webinarId.png"))
        {
            Storage::disk('public')->delete("images/webinars/cover/$webinarId.png");
        }
    }

    public function GetWebinarAll(Request $request)
    {
        $result = [];

        foreach(Webinar::get() as $webinar)
        {
            $result[] = [
                'date' => $webinar->date_start,
                'duration' => $webinar->duration,
                'title' => $webinar->name,
                'lectors' => $webinar->authors,
                'type' => 'Вебинар',
                'image' => url('/').'/'.$webinar->image_path,
            ];
        }

        return $result;
    }
}

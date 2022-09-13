<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

use App\Models\Webinar;
use App\Models\Course;
use App\Models\CourseBlock;
use App\Models\ModuleStream;
use App\Models\ModuleVideo;
use App\Models\ModuleJob;
use App\Models\ModuleTest;
use App\Models\WebinarAccess;

class WebinarController extends Controller
{
    public function CreateWebinar(Request $request)
    {
        $newWebinar = new Webinar();

        $config = json_decode($request->input('webinar'));

        $newWebinar->name = $config->name;
        $newWebinar->creator = Auth::user()->id;
        $newWebinar->authors = $config->authors;
        $newWebinar->link = $config->link;
        $newWebinar->date_start = $this->ConvertDate($config->date_start);

        $newWebinar->save();

        if(!empty($config->new_files))
        {
            foreach ($config->new_files as $file)
            {
                app(FileController::class)->StoreWebinarFile($request->file($file->id), $newWebinar->id);
            }
        }

        if (!empty($config->deleted_files))
        {
            foreach($config->deleted_files as $fileId)
            {
                app(FileController::class)->DeleteModuleFile($fileId);
            }
        }

        if (!empty($request->image))
        {
            $filePath = $this->LoadImage($request->image, $newWebinar->id, 'images/webinars/cover/');
            $newWebinar->image_path = $filePath;
        }

        $newWebinar->save();

        $imgUrl = url('/').'/'.$newWebinar->image_path;

        if (config('modx.sync'))
        {
            $response = Http::post(config('modx.api').'/CreateWebinar', [
                'pagetitle' => $newWebinar->name,
                'image' => $imgUrl,
                'date' => $this->ConvertDate($newWebinar->date_start),
                'id' => $newWebinar->id,
            ]);
        }

        return $newWebinar;
    }

    public function EditWebinar(Request $request, $webinarId)
    {
        $webinar = Webinar::where('id', $webinarId)->first();
        
        $config = json_decode($request->input('webinar'));

        $webinar->name = $config->name;
        $webinar->authors = $config->authors;
        $webinar->link = $config->link;
        $webinar->date_start = $this->ConvertDate($config->date_start);

        if (!empty($request->image))
        {
            $filePath = $this->LoadImage($request->image, $webinar->id, 'images/webinars/cover/');
            $webinar->image_path = $filePath;
        }

        if(!empty($config->new_files))
        {
            foreach ($config->new_files as $file)
            {
                app(FileController::class)->StoreWebinarFile($request->file($file->id), $webinar->id);
            }
        }

        if(!empty($config->deleted_files))
        {
            foreach ($config->deleted_files as $fileId)
            {
                app(FileController::class)->DeleteModuleFile($fileId);
            }
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
            'files' => $this->GetModuleFiles($webinarId, 'webinar'),
            'preview' => $this->GetModulePreview($webinarId, 'webinar'),
            'new_files' => [],
            'deleted_files' => [],
            'status' => $this->GetKinescopeVideoStatus($webinar->link)
        ];

        return $result;
    }

    public function GetWebinar(Request $request, $webinarId)
    {
        $webinar = Webinar::where('id', $webinarId)->first();

        if (empty($webinar))
        {
            return response()->json([
                'message' => 'Вебинар не найден'
            ] , 422);
        }

        $result = [
            'id' => $webinar->id,
            'name' => $webinar->name,
            'authors' => $webinar->authors,
            'date_start' => $webinar->date_start,
            'image' => url('/').'/'.$webinar->image_path,
        ];

        $response = Http::withOptions([
            'verify' => false,
        ])->get(config('modx.api')."/GetWebinarConfig?webinarId=$webinarId");

        $result['modx'] = json_decode($response->body(), true);

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
            if (Carbon::parse($item->date_start)->addDays(1)->isPast())
            {
                continue;
            }

            $result[] = [
                'id' => $item->id,
                'course_id' => $this->GetCourseIdByModule($item->id, 'stream'),
                'title' => $item->title,
                'date' => Carbon::parse($item->date_start)->translatedFormat('d.m.Y H:i'),
                'lectors' => $item->authors,
                'type' => 'stream',
                'status' => $this->GetKinescopeVideoStatus($item->link)
            ];
        }

        foreach($webinars as $item)
        {
            if (Carbon::parse($item->date_start)->addDays(1)->isPast())
            {
                continue;
            }

            $result[] = [
                'id' => $item->id,
                'title' => $item->name,
                'date' => Carbon::parse($item->date_start)->translatedFormat('d.m.Y H:i'),
                'lectors' => $item->authors,
                'type' => 'webinar',
                'status' => $this->GetKinescopeVideoStatus($item->link)
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

    public function GetCurrentWebinars(Request $request)
    {
        $webinars = [];

        foreach (WebinarAccess::where('user_id', Auth::user()->id)->get() as $webinarAccess)
        {
            $webinar = Webinar::where('id', $webinarAccess->webinar_id)->first();

            if (empty($webinar))
            {
                continue;
            }

            $webinars[] = [
                'id' => $webinar->id,
                'type' => 'webinar',
                'title' => $webinar->name,
                'date' => Carbon::parse($webinar->date_start)->translatedFormat('d.m.Y H:i'),
                'lectors' => $webinar->authors,
                'image' => url('/').'/'.$webinar->image_path,
                'status' => $this->GetKinescopeVideoStatus($webinar->link)
            ];
        }

        usort($webinars, function($a, $b){
            return $a['date'] <=> $b['date'];
        });

        return $webinars;
    }

    public function GetWebinatStatus(Request $request, $webinarId)
    {
        $webinar = Webinar::where('id', $webinarId)->first(); 

        return $this->GetKinescopeVideoStatus($webinar->link);
    }
}

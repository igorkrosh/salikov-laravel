<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use App\Models\Course;
use App\Models\CourseBlock;
use App\Models\ModuleVideo;

class KinescopeController extends Controller
{
    public function CreateProject($title, $courseId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('kinescope.token')
        ])->withOptions([
            'verify' => false,
        ])->post('https://api.kinescope.io/v1/projects', [
            'name' => $title
        ]);

        $course = Course::where('id', $courseId)->first();

        $course->kinescope_id = $response['data']['id'];

        $course->save();
    }

    public function LoadVideo($moduleId, $video)
    {
        $module = ModuleVideo::where('id', $moduleId)->first();
        $block = CourseBlock::where('id', $module->block_id)->first();
        $course = Course::where('id', $block->course_id)->first();

        if (!empty($module->kinescope_id))
        {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('kinescope.token'),
            ])->withOptions([
                'verify' => false,
            ])->delete('https://api.kinescope.io/v1/videos/'.$module->kinescope_id);
        }

        $path = 'files/temp/';

        Storage::disk('public')->putFileAs($path, $video, $video->getClientOriginalName());

        $uploadUrl = url('/').'/'.'storage/'.$path.$video->getClientOriginalName();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('kinescope.token'),
            'X-Project-ID' => $course->kinescope_id,
            'X-Folder-ID' => '',
            'X-Video-Title' => $module->title,
            'X-Video-Description' => 'Видео из блока '.$block->title,
            'X-File-Name' => $module->id.'.mp4',
        ])->withBody(
            Storage::disk('public')->get($path.$video->getClientOriginalName()),
            'text/plain'
        )->withOptions([
            'verify' => false,
        ])->post('https://uploader.kinescope.io/video');

        Storage::disk('public')->delete($path.$video->getClientOriginalName());

        $module->kinescope_id = $response['data']['id'];
        $module->link = $response['data']['embed_link'];

        $module->save();
    }
}

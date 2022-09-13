<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

use DateTime;
use DateTimeZone;

use App\Models\Course;
use App\Models\CourseBlock;
use App\Models\ModuleStream;
use App\Models\ModuleVideo;
use App\Models\ModuleJob;
use App\Models\ModuleTest;
use App\Models\Progress;
use App\Models\File;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function LoadImage($image, $name, $folderPath)
    {
        $imageParts = explode(";base64,", $image);
        $imageTypeAux = explode("image/", $imageParts[0]);
        $imageType = $imageTypeAux[1];
        $imageBase64 = base64_decode($imageParts[1]);
        $filePath = $folderPath.$name.'.'.$imageType;

        $linkParam = '';

        if (Storage::disk('public')->exists($filePath))
        {
            $time = time();
            $linkParam = "?v=$time";
        }


        $disk = Storage::disk('public')->put($filePath, base64_decode($imageParts[1])); 

        return 'storage/'.$filePath.$linkParam;
    }

    public function ConvertDate($date)
    {
        $result = strtotime($date);
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('Europe/Moscow'));
        $dt->setTimestamp($result);
        $result = date('Y-m-d H:i:s', $result);

        return $dt->format('Y-m-d H:i:s');
    }

    public function IsStart($date)
    {
        $timeStart = strtotime($date);

        return !($timeStart > time());
    }

    public function GetModules($courseId)
    {
        $result = [
            'stream' => [],
            'video' => [],
            'job' => [],
            'test' => [],
        ];

        $blocks = CourseBlock::where('course_id', $courseId)->get();

        foreach ($blocks as $block)
        {
            $moduleStream = ModuleStream::where('block_id', $block->id)->get();

            foreach ($moduleStream as $module)
            {
                $result['stream'][] = $module;
            }

            $modulesVideo = ModuleVideo::where('block_id', $block->id)->get();

            foreach ($modulesVideo as $module)
            {
                $result['video'][] = $module;
            }

            $modulesJob = ModuleJob::where('block_id', $block->id)->get();

            foreach ($modulesJob as $module)
            {
                $result['job'][] = $module;
            }

            $modulesTest = ModuleTest::where('block_id', $block->id)->get();

            foreach ($modulesTest as $module)
            {
                $result['test'][] = $module;
            }
        }

        return $result;

    }

    public function GetModuleStatus($userId, $moduleId, $type)
    {
        $progress = Progress::where([['user_id', $userId], ['module_id', $moduleId], ['type', $type]])->first();

        if (empty($progress))
        {
            return 'none';
        }

        return $progress->status;
    }

    public function GetModuleByType($moduleId, $type)
    {
        switch ($type) 
        {
            case 'stream':
                $result = ModuleStream::where('id', $moduleId)->first();
                break;
            case 'video':
                $result = ModuleVideo::where('id', $moduleId)->first();
                break;
            case 'job':
                $result = ModuleJob::where('id', $moduleId)->first();
                break;
            case 'test':
                $result = ModuleTest::where('id', $moduleId)->first();
                break;
            default:
                $result = null;
                break;
        }

        return $result;
    }

    public function GetCourseIdByModule($moduleId, $type)
    {
        $module = $this->GetModuleByType($moduleId, $type);
        $block = CourseBlock::where('id', $module->block_id)->first();

        return $block->course_id;
    }

    public function GetModuleFiles($moduleId, $type)
    {
        $files = File::where([['module_id', $moduleId], ['type', $type]])->get();
        $result = [];

        foreach ($files as $file)
        {
            $result[] = [
                'id' => $file->id,
                'url' => url('/').'/'.$file->path,
                'filename' => $file->filename,
            ];
        }

        return $result;
    }

    public function GetModulePreview($moduleId, $type)
    {
        $files = File::where([['module_id', $moduleId], ['type', $type]])->whereIn('extension', ['jpg', 'jpeg', 'png', 'gif', 'svg'])->get();
        $result = [];

        foreach ($files as $file)
        {
            $result[] = [
                'id' => $file->id,
                'url' => url('/').'/'.$file->path,
                'filename' => $file->filename,
            ];
        }

        return $result;
    }

    public function GetKinescopeVideoStatus($link)
    {
        $videoId = explode('/', $link);
        $videoId = end($videoId);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('kinescope.token')
        ])->withOptions([
            'verify' => false,
        ])->get('https://api.kinescope.io/v1/videos/'.$videoId);
        
        if (!empty($response['data']['status']))
        {

           return $response['data']['status'];
        }

        return 'null';
    }
}

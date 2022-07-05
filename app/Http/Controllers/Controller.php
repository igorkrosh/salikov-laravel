<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;

use App\Models\Course;
use App\Models\CourseBlock;
use App\Models\ModuleStream;
use App\Models\ModuleVideo;
use App\Models\ModuleJob;
use App\Models\ModuleTest;
use App\Models\Progress;

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
        $result = date('Y-m-d',$result);

        return $result;
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
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

use App\Models\Course;
use App\Models\CourseBlock;
use App\Models\ModuleStream;
use App\Models\ModuleVideo;
use App\Models\ModuleJob;
use App\Models\ModuleTest;

class CourseController extends Controller
{
    public function CreateCourse(Request $request)
    {
        $course = new Course();
        
        $request->validate([
            'name' => ['required'],
            'authors' => ['required'],
            'date_start' => ['required'],
            'duration' => ['required'],
        ]);

        $date_start = $request->date_start;
        $date_start = strtotime($date_start);
        $date_start = date('Y-m-d',$date_start);

        $course->name = $request->name;
        $course->authors = $request->authors;
        $course->date_start = $date_start;
        $course->duration = $request->duration;
        $course->creator = Auth::user()->id;
        $course->image_path = '';

        $course->save();

        if (!empty($request->image))
        {
            $image = $request->image; 
            
            $imageParts = explode(";base64,", $image);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $imageType = $imageTypeAux[1];
            $imageBase64 = base64_decode($imageParts[1]);
            $filePath = 'images/courses/cover/'.$course->id.'.'.$imageType;

            $disk = Storage::disk('public')->put($filePath, base64_decode($imageParts[1])); 
        }

        $course->image_path = 'storage/'.$filePath;
        $course->save();


        foreach ($request->blocks as $index => $block)
        {
            $newBlock = new CourseBlock();

            $date_start = $block['date'];
            $date_start = strtotime($date_start);
            $date_start = date('Y-m-d',$date_start);

            $newBlock->course_id = $course->id;
            $newBlock->index = $index;
            $newBlock->title = $block['title'];
            $newBlock->date_start = $date_start;

            $newBlock->save();

            foreach ($block['modules'] as $index => $module)
            {
                if ($module['type'] == 'stream')
                {
                    $newModuleStream = new ModuleStream();

                    $date_start = $module['date'];
                    $date_start = strtotime($date_start);
                    $date_start = date('Y-m-d', $date_start);

                    $newModuleStream->block_id = $newBlock->id;
                    $newModuleStream->index = $index;
                    $newModuleStream->authors = $module['authors'];
                    $newModuleStream->title = $module['title'];
                    $newModuleStream->link = $module['link'];
                    $newModuleStream->date_start = $date_start;

                    $newModuleStream->save();
                }

                if ($module['type'] == 'video')
                {
                    $newModuleVideo = new ModuleVideo();

                    $newModuleVideo->block_id = $newBlock->id;
                    $newModuleVideo->index = $index;
                    $newModuleVideo->authors = $module['authors'];
                    $newModuleVideo->title = $module['title'];
                    $newModuleVideo->link = $module['link'];

                    $newModuleVideo->save();
                }

                if ($module['type'] == 'job')
                {
                    $newModuleJob = new ModuleJob();

                    $deadline = $module['deadline'];
                    $deadline = strtotime($deadline);
                    $deadline = date('Y-m-d', $deadline);

                    $check_date = $module['check_date'];
                    $check_date = strtotime($check_date);
                    $check_date = date('Y-m-d', $check_date);

                    $newModuleJob->block_id = $newBlock->id;
                    $newModuleJob->index = $index;
                    $newModuleJob->authors = $module['authors'];
                    $newModuleJob->title = $module['title'];
                    $newModuleJob->text = $module['text'];
                    $newModuleJob->check_date = $check_date;
                    $newModuleJob->deadline = $deadline;

                    $newModuleJob->save();
                }

                if ($module['type'] == 'test')
                {
                    $newModuleTest = new ModuleTest();

                    $deadline = $module['deadline'];
                    $deadline = strtotime($deadline);
                    $deadline = date('Y-m-d', $deadline);

                    $check_date = $module['check_date'];
                    $check_date = strtotime($check_date);
                    $check_date = date('Y-m-d', $check_date);

                    $newModuleTest->block_id = $newBlock->id;
                    $newModuleTest->index = $index;
                    $newModuleTest->authors = $module['authors'];
                    $newModuleTest->title = $module['title'];
                    $newModuleTest->test = $module['test'];
                    //$newModuleTest->file = $module['file'];
                    $newModuleTest->check_date = $check_date;
                    $newModuleTest->deadline = $deadline;

                    $newModuleTest->save();
                }
            }
        }

        return $course;
    }
}

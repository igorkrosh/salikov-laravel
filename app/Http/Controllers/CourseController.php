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
            $filePath = $this->LoadImage($request->image, $course->id, 'images/courses/cover/');
            $course->image_path = $filePath;
        }

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

    public function GetCoursesByUser(Request $request)
    {
        $user_id = Auth::user()->id;

        $courses = Course::where('creator', $user_id)->get();
        $result = [];

        foreach ($courses as $course)
        {
            $result[] = [
                'id' => $course->id,
                'image' => url('/').'/'.$course->image_path,
                'lectors' => $course->authors,
                'title' => $course->name,
                'type' => 'Курс',
            ];
        }

        return $result;
    }

    public function GetCourseById(Request $request, $courseId)
    {
        $course = Course::where('id', $courseId)->first();

        if (empty($course))
        {
            return response()->json([
                'message' => 'Курс не найден'
            ] , 422);
        }
        
        $result = [
            'id' => $course->id,
            'name' => $course->name,
            'authors' => $course->authors,
            'date_start' => $course->date_start,
            'duration' => $course->duration,
            'image' => url('/').'/'.$course->image_path,
            'blocks' => []
        ];

        $blocks = CourseBlock::where('course_id', $course->id)->orderBy('index')->get();

        foreach ($blocks as $block)
        {
            $modules = [];

            $modulesStream = ModuleStream::where('block_id', $block->id)->get();

            foreach ($modulesStream as $module)
            {
                $modules[] = [
                    'id' => $module->id,
                    'type' => 'stream',
                    'index' => $module->index,
                    'authors' => $module->authors,
                    'title' => $module->title,
                    'link' => $module->link,
                    'date' => $module->date_start,
                ];
            }

            $modulesVideo = ModuleVideo::where('block_id', $block->id)->get();

            foreach ($modulesVideo as $module)
            {
                $modules[] = [
                    'id' => $module->id,
                    'type' => 'video',
                    'index' => $module->index,
                    'authors' => $module->authors,
                    'title' => $module->title,
                    'link' => $module->link,
                ];
            }

            $modulesJob = ModuleJob::where('block_id', $block->id)->get();

            foreach ($modulesJob as $module)
            {
                $modules[] = [
                    'id' => $module->id,
                    'type' => 'job',
                    'index' => $module->index,
                    'authors' => $module->authors,
                    'title' => $module->title,
                    'text' => $module->text,
                    'deadline' => $module->deadline,
                    'check_date' => $module->check_date,
                ];
            }

            $modulesTest = ModuleTest::where('block_id', $block->id)->get();

            foreach ($modulesTest as $module)
            {
                $modules[] = [
                    'id' => $module->id,
                    'type' => 'test',
                    'index' => $module->index,
                    'authors' => $module->authors,
                    'title' => $module->title,
                    'test' => $module->test,
                    'deadline' => $module->deadline,
                    'check_date' => $module->check_date,
                ];
            }

            usort($modules, function($a, $b){
                return $a['index'] <=> $b['index'];
            });

            $result['blocks'][] = [
                'id' => $block->id,
                'title' => $block->title,
                'date' => $block->date_start,
                'index' => $block->index,
                'modules' => $modules,
            ];

        }

        usort($result['blocks'], function($a, $b){
            return $a['index'] <=> $b['index'];
        });

        return $result;

    }

    public function EditCourse(Request $request, $courseId)
    {
        $course = Course::where('id', $courseId)->first();

        $course->authors = $request->authors;
        $course->date_start = $this->ConvertDate($request->date_start);
        $course->duration = $request->duration;
        $course->name = $request->name;

        if (!empty($request->image))
        {
            $filePath = $this->LoadImage($request->image, $course->id, 'images/courses/cover/');
            $course->image_path = $filePath;
        }

        //$blocks = CourseBlock::where('course_id', $courseId)->get();

        foreach ($request->blocks as $block)
        {
            if (empty($block['id']))
            {
                $editBlock = new CourseBlock();
                $editBlock->course_id = $request->id;

            }
            else 
            {
                $editBlock = CourseBlock::where('id', $block['id'])->first();
            }

            $editBlock->date_start = $this->ConvertDate($block['date']);
            $editBlock->index = $block['index'];
            $editBlock->title = $block['title'];

            $editBlock->save();

            foreach ($block['modules'] as $module)
            {
                if (empty($module['authors']))
                {
                    $module['authors'] = $request->authors;
                }
                switch ($module['type']) 
                {
                    case 'stream':
                        if (empty($module['id']))
                        {
                            $moduleStream = new ModuleStream();
                        }
                        else 
                        {
                            $moduleStream = ModuleStream::where('id', $module['id'])->first();
                        }

                        $moduleStream->block_id = $editBlock->id;
                        $moduleStream->index = $module['index'];
                        $moduleStream->authors = $module['authors'];
                        $moduleStream->title = $module['title'];
                        $moduleStream->link = $module['link'];
                        $moduleStream->date_start = $this->ConvertDate($module['date']);

                        $moduleStream->save();

                        break;
                    case 'video':
                        if (empty($module['id']))
                        {
                            $moduleVideo = new ModuleVideo();
                        }
                        else 
                        {
                            $moduleVideo = ModuleVideo::where('id', $module['id'])->first();
                        }

                        $moduleVideo->block_id = $editBlock->id;
                        $moduleVideo->index = $module['index'];
                        $moduleVideo->authors = $module['authors'];
                        $moduleVideo->title = $module['title'];
                        $moduleVideo->link = $module['link'];

                        $moduleVideo->save();
                        
                        break;
                    case 'job':
                        if (empty($module['id']))
                        {
                            $moduleJob = new ModuleJob();
                        }
                        else 
                        {
                            $moduleJob = ModuleJob::where('id', $module['id'])->first();
                        }

                        $moduleJob->block_id = $editBlock->id;
                        $moduleJob->index = $module['index'];
                        $moduleJob->authors = $module['authors'];
                        $moduleJob->title = $module['title'];
                        $moduleJob->text = $module['text'];
                        $moduleJob->check_date = $this->ConvertDate($module['check_date']);
                        $moduleJob->deadline = $this->ConvertDate($module['deadline']);

                        $moduleJob->save();

                        break;
                    case 'test':
                        if (empty($module['id']))
                        {
                            $moduleTest = new ModuleTest();
                        }
                        else 
                        {
                            $moduleTest = ModuleTest::where('id', $module['id'])->first();
                        }

                        $moduleTest->block_id = $editBlock->id;
                        $moduleTest->index = $module['index'];
                        $moduleTest->authors = $module['authors'];
                        $moduleTest->title = $module['title'];
                        $moduleTest->test = $module['test'];
                        //$moduleTest->file = $module['file'];
                        $moduleTest->check_date = $this->ConvertDate($module['check_date']);
                        $moduleTest->deadline = $this->ConvertDate($module['deadline']);

                        $moduleTest->save();
                        
                        break;
                    default:
                        # code...
                        break;
                }
            }

        }

        $course->save();

        foreach ($request->deleted['stream'] as $id)
        {
            ModuleStream::where('id', $id)->delete();
        }

        foreach ($request->deleted['video'] as $id)
        {
            ModuleVideo::where('id', $id)->delete();
        }

        foreach ($request->deleted['job'] as $id)
        {
            ModuleJob::where('id', $id)->delete();
        }

        foreach ($request->deleted['test'] as $id)
        {
            ModuleTest::where('id', $id)->delete();
        }

        return $course;
    }

    public function DeleteCourse(Request $request, $courseId)
    {
        $blocks = CourseBlock::where('course_id', $courseId)->get();

        foreach ($blocks as $block)
        {
            ModuleStream::where('block_id', $block->id)->delete();
            ModuleVideo::where('block_id', $block->id)->delete();
            ModuleJob::where('block_id', $block->id)->delete();
            ModuleTest::where('block_id', $block->id)->delete();
        }

        CourseBlock::where('course_id', $courseId)->delete();
        Course::where('id', $courseId)->delete();

        if (Storage::disk('public')->exists("images/courses/cover/$courseId.jpeg"))
        {
            Storage::disk('public')->delete("images/courses/cover/$courseId.jpeg");
        }

        if (Storage::disk('public')->exists("images/courses/cover/$courseId.png"))
        {
            Storage::disk('public')->delete("images/courses/cover/$courseId.png");
        }
    }
}

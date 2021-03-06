<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Config;

use App\Models\Course;
use App\Models\CourseBlock;
use App\Models\ModuleStream;
use App\Models\ModuleVideo;
use App\Models\ModuleJob;
use App\Models\ModuleTest;
use App\Models\User;
use App\Models\BlockAccess;

class CourseController extends Controller
{
    public function CreateCourse(Request $request)
    {
        $course = new Course();
        $config = json_decode($request->input('course'));

        $date_start = $config->date_start;
        $date_start = $this->ConvertDate($config->date_start);

        $course->name = $config->name;
        $course->authors = $config->authors;
        $course->date_start = $date_start;
        $course->duration = $config->duration;
        $course->creator = Auth::user()->id;
        $course->image_path = '';
        $course->kinescope_id = '';
        $course->hours = $config->hours;

        $course->save();

        app(KinescopeController::class)->CreateProject($course->name, $course->id);

        if (!empty($config->image))
        {
            $filePath = $this->LoadImage($config->image, $course->id, 'images/courses/cover/');
            $course->image_path = $filePath;
        }

        $course->save();

        $MIGXbloks = [];

        foreach ($config->blocks as $index => $block)
        {
            $newBlock = new CourseBlock();

            $date_start = $block->date;
            $date_start = strtotime($date_start);
            $date_start = date('Y-m-d',$date_start);

            $newBlock->course_id = $course->id;
            $newBlock->index = $index;
            $newBlock->title = $block->title;
            $newBlock->date_start = $date_start;

            $newBlock->save();

            $MIGXbloks[] = [
                'name' => $block->title,
                'desc' => '<ul>',
            ];

            $indexMIGX = count($MIGXbloks) - 1;

            foreach ($block->modules as $index => $module)
            {
                
                $MIGXbloks[$indexMIGX]['desc'] .= '<li>'.$module->title.'</li>';
                if ($module->type == 'stream')
                {
                    $newModuleStream = new ModuleStream();

                    $date_start = $module->date;
                    $date_start = strtotime($date_start);
                    $date_start = date('Y-m-d', $date_start);

                    $newModuleStream->block_id = $newBlock->id;
                    $newModuleStream->index = $index;
                    $newModuleStream->authors = $module->authors;
                    $newModuleStream->title = $module->title;
                    $newModuleStream->link = $module->link;
                    $newModuleStream->date_start = $date_start;

                    $newModuleStream->save();
                }

                if ($module->type == 'video')
                {
                    $newModuleVideo = new ModuleVideo();

                    $newModuleVideo->block_id = $newBlock->id;
                    $newModuleVideo->index = $index;
                    $newModuleVideo->authors = $module->authors;
                    $newModuleVideo->title = $module->title;
                    $newModuleVideo->link = '';

                    $newModuleVideo->save();

                    if (!empty($module->fileId))
                    {
                        app(KinescopeController::class)->LoadVideo($newModuleVideo->id, $request->file($module->fileId));
                    }
                }

                if ($module->type == 'job')
                {
                    $newModuleJob = new ModuleJob();

                    $deadline = $module->deadline;
                    $deadline = strtotime($deadline);
                    $deadline = date('Y-m-d', $deadline);

                    $check_date = $module->check_date;
                    $check_date = strtotime($check_date);
                    $check_date = date('Y-m-d', $check_date);

                    $newModuleJob->block_id = $newBlock->id;
                    $newModuleJob->index = $index;
                    $newModuleJob->authors = $module->authors;
                    $newModuleJob->title = $module->title;
                    $newModuleJob->text = $module->text;
                    $newModuleJob->check_date = $check_date;
                    $newModuleJob->deadline = $deadline;

                    $newModuleJob->save();

                    if (!empty($module->fileId))
                    {
                        $file = $request->file($module->fileId);

                        $path = app('App\Http\Controllers\FileController')->StoreJobFile($file, $newModuleJob->id);

                        $newModuleJob->file = $path;
                        $newModuleJob->save();
                    }
                }

                if ($module->type == 'test')
                {
                    $newModuleTest = new ModuleTest();

                    $deadline = $module->deadline;
                    $deadline = strtotime($deadline);
                    $deadline = date('Y-m-d', $deadline);

                    $check_date = $module->check_date;
                    $check_date = strtotime($check_date);
                    $check_date = date('Y-m-d', $check_date);

                    $newModuleTest->block_id = $newBlock->id;
                    $newModuleTest->index = $index;
                    $newModuleTest->authors = $module->authors;
                    $newModuleTest->title = $module->title;
                    $newModuleTest->test = $module->test;
                    //$newModuleTest->file = $module['file'];
                    $newModuleTest->check_date = $check_date;
                    $newModuleTest->deadline = $deadline;

                    $newModuleTest->save();

                    if (!empty($module->fileId))
                    {
                        $file = $request->file($module->fileId);
                        
                        $path = app('App\Http\Controllers\FileController')->StoreTestFile($file, $newModuleTest->id);

                        $newModuleTest->file = $path;
                        $newModuleTest->save();
                    }
                }
            }

            $MIGXbloks[$indexMIGX]['desc'] .= '</ul>';
        }

        $imgUrl = url('/').'/'.$course->image_path;

        $response = 0;

        if (config('modx.sync'))
        {
            $response = Http::post(config('modx.api').'/CreateCourse', [
                'pagetitle' => $course->name,
                'image' => $imgUrl,
                'date' => $this->ConvertDate($course->date_start),
                'id' => $course->id,
                'lectors' => $course->authors,
                'migx' => $MIGXbloks,
            ]);
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
                'type' => '????????',
            ];
        }

        return $result;
    }

    public function GetCourse(Request $request, $courseId)
    {
        $course = Course::where('id', $courseId)->first();

        if (empty($course))
        {
            return response()->json([
                'message' => '???????? ???? ????????????'
            ] , 422);
        }

        $modules = $this->GetModules($courseId);
        
        $result = [
            'id' => $course->id,
            'name' => $course->name,
            'authors' => $course->authors,
            'date_start' => $course->date_start,
            'duration' => $course->duration,
            'image' => url('/').'/'.$course->image_path,
            'hours' => $course->hours,
            'blocks' => [],
            'lessons' => count($modules['stream']) + count($modules['video']) + count($modules['job']) + count($modules['test']),
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
                    'date' => $module->date_start,
                    'title' => $module->title,
                    'educator' => $module->authors,
                    'type' => 'stream',
                    'index' => $module->index,
                ];
            }

            $modulesVideo = ModuleVideo::where('block_id', $block->id)->get();

            foreach ($modulesVideo as $module)
            {
                $modules[] = [
                    'id' => $module->id,
                    'date' => '',
                    'type' => 'video',
                    'index' => $module->index,
                    'educator' => $module->authors,
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
                    'educator' => $module->authors,
                    'title' => $module->title,
                    'date' => $module->deadline,
                ];
            }

            $modulesTest = ModuleTest::where('block_id', $block->id)->get();

            foreach ($modulesTest as $module)
            {
                $modules[] = [
                    'id' => $module->id,
                    'type' => 'test',
                    'index' => $module->index,
                    'educator' => $module->authors,
                    'title' => $module->title,
                    'date' => $module->deadline,
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

        $response = Http::withOptions([
            'verify' => false,
        ])->get(config('modx.api')."/GetCourseConfig?courseId=$courseId");

        $result['modx'] = json_decode($response->body(), true);

        return $result;

    }

    public function GetCourseById(Request $request, $courseId)
    {
        $course = Course::where('id', $courseId)->first();

        if (empty($course))
        {
            return response()->json([
                'message' => '???????? ???? ????????????'
            ] , 422);
        }

        $modules = $this->GetModules($courseId);
        $blockAccess = BlockAccess::where([['course_id', $course->id], ['user_id', Auth::user()->id]])->first();
        
        $result = [
            'id' => $course->id,
            'name' => $course->name,
            'authors' => $course->authors,
            'date_start' => $course->date_start,
            'duration' => $course->duration,
            'image' => url('/').'/'.$course->image_path,
            'hours' => $course->hours,
            'blocks' => [],
            'lessons' => count($modules['stream']) + count($modules['video']) + count($modules['job']) + count($modules['test']),
            'buy_at' => empty($blockAccess) ? '' : $blockAccess->created_at
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
                    'status' => $this->GetModuleStatus(Auth::user()->id, $module->id, 'stream'),
                    'access' => $this->IsStart($module->date_start)
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
                    'status' => $this->GetModuleStatus(Auth::user()->id, $module->id, 'video'),
                    'access' => $this->IsStart($module->date_start)
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
                    'file_path' => url('/').'/'.$module->file,
                    'status' => $this->GetModuleStatus(Auth::user()->id, $module->id, 'job'),
                    'access' => true
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
                    'file_path' => url('/').'/'.$module->file,
                    'status' => $this->GetModuleStatus(Auth::user()->id, $module->id, 'test'),
                    'access' => true
                ];
            }

            usort($modules, function($a, $b){
                return $a['index'] <=> $b['index'];
            });

            $access = !empty(BlockAccess::where([['user_id', Auth::user()->id], ['block_id', $block->id], ['course_id', $course->id]])->first());

            $result['blocks'][] = [
                'id' => $block->id,
                'title' => $block->title,
                'date' => $block->date_start,
                'index' => $block->index,
                'modules' => $modules,
                'access' => $access
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

        $config = json_decode($request->input('course'));

        $course->authors = $config->authors;
        $course->date_start = $this->ConvertDate($config->date_start);
        $course->duration = $config->duration;
        $course->name = $config->name;

        if (!empty($config->image))
        {
            $filePath = $this->LoadImage($config->image, $course->id, 'images/courses/cover/');
            $course->image_path = $filePath;
        }

        //$blocks = CourseBlock::where('course_id', $courseId)->get();

        foreach ($config->blocks as $block)
        {
            if (empty($block->id))
            {
                $editBlock = new CourseBlock();
                $editBlock->course_id = $config->id;
            }
            else 
            {
                $editBlock = CourseBlock::where('id', $block->id)->first();
            }

            $editBlock->date_start = $this->ConvertDate($block->date);
            $editBlock->index = $block->index;
            $editBlock->title = $block->title;

            $editBlock->save();

            foreach ($block->modules as $module)
            {
                if (empty($module->authors))
                {
                    $module->authors = $config->authors;
                }
                switch ($module->type) 
                {
                    case 'stream':
                        if (empty($module->id))
                        {
                            $moduleStream = new ModuleStream();
                        }
                        else 
                        {
                            $moduleStream = ModuleStream::where('id', $module->id)->first();
                        }

                        $moduleStream->block_id = $editBlock->id;
                        $moduleStream->index = $module->index;
                        $moduleStream->authors = $module->authors;
                        $moduleStream->title = $module->title;
                        $moduleStream->link = $module->link;
                        $moduleStream->date_start = $this->ConvertDate($module->date);

                        $moduleStream->save();

                        break;
                    case 'video':
                        if (empty($module->id))
                        {
                            $moduleVideo = new ModuleVideo();
                        }
                        else 
                        {
                            $moduleVideo = ModuleVideo::where('id', $module->id)->first();
                        }

                        $moduleVideo->block_id = $editBlock->id;
                        $moduleVideo->index = $module->index;
                        $moduleVideo->authors = $module->authors;
                        $moduleVideo->title = $module->title;
                        $moduleVideo->link = $module->link;

                        $moduleVideo->save();

                        if (!empty($module->fileId))
                        {
                            app(KinescopeController::class)->LoadVideo($moduleVideo->id, $request->file($module->fileId));
                        }
                        
                        break;
                    case 'job':
                        if (empty($module->id))
                        {
                            $moduleJob = new ModuleJob();
                        }
                        else 
                        {
                            $moduleJob = ModuleJob::where('id', $module->id)->first();
                        }

                        $moduleJob->block_id = $editBlock->id;
                        $moduleJob->index = $module->index;
                        $moduleJob->authors = $module->authors;
                        $moduleJob->title = $module->title;
                        $moduleJob->text = $module->text;
                        $moduleJob->check_date = $this->ConvertDate($module->check_date);
                        $moduleJob->deadline = $this->ConvertDate($module->deadline);

                        $moduleJob->save();

                        if (!empty($module->fileId))
                        {
                            $file = $request->file($module->fileId);
                            $path = app('App\Http\Controllers\FileController')->StoreJobFile($file, $moduleJob->id);

                            $moduleJob->file = $path;
                            $moduleJob->save();
                        }


                        if (!empty($file))
                        {
                            
                        }

                        break;
                    case 'test':
                        if (empty($module->id))
                        {
                            $moduleTest = new ModuleTest();
                        }
                        else 
                        {
                            $moduleTest = ModuleTest::where('id', $module->id)->first();
                        }

                        $moduleTest->block_id = $editBlock->id;
                        $moduleTest->index = $module->index;
                        $moduleTest->authors = $module->authors;
                        $moduleTest->title = $module->title;
                        $moduleTest->test = $module->test;
                        //$moduleTest->file = $module['file'];
                        $moduleTest->check_date = $this->ConvertDate($module->check_date);
                        $moduleTest->deadline = $this->ConvertDate($module->deadline);

                        $moduleTest->save();

                        if (!empty($module->fileId))
                        {
                            $file = $request->file($module->fileId);
                            
                            $path = app('App\Http\Controllers\FileController')->StoreTestFile($file, $moduleTest->id);

                            $moduleTest->file = $path;
                            $moduleTest->save();
                        }
                        
                        break;
                    default:
                        # code...
                        break;
                }
            }

        }

        $course->save();

        foreach ($config->deleted->stream as $id)
        {
            ModuleStream::where('id', $id)->delete();
        }

        foreach ($config->deleted->video as $id)
        {
            ModuleVideo::where('id', $id)->delete();
        }

        foreach ($config->deleted->job as $id)
        {
            ModuleJob::where('id', $id)->delete();
        }

        foreach ($config->deleted->test as $id)
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

        if (config('modx.sync'))
        {
            $response = Http::post(config('modx.api').'/DeleteCourse', [
                'id' => $courseId,
            ]);
        }
    }

    public function GetCourseUsers(Request $request, $courseId)
    {
        $course = Course::where('id', $courseId)->first();
        $creator = User::where('id', $course->creator)->first();

        $response = [
            'curators' => [],
            'users' => [],
        ];

        $response['curators'][] = [
            'image' => empty($creator->img_path) ? '' : url('/').'/'.$creator->img_path,
            'name' => $creator->name.' '.$creator->last_name,
            'id' => $creator->id
        ];

        $access = BlockAccess::where('course_id', $courseId)->get()->unique('user_id');

        foreach($access as $accessRow)
        {
            $user = User::where('id', $accessRow->user_id)->first();
            $response['users'][] = [
                'image' => empty($user->img_path) ? '' : url('/').'/'.$user->img_path,
                'name' => $user->name.' '.$user->last_name,
                'id' => $user->id
            ];
        }

        return $response;
    }

    public function AddUserAccess(Request $request)
    {
        //BlockAccess
        $user = User::where('email', $request->email)->first();

        foreach($request->blocks as $blockId)
        {
            $access = new BlockAccess();

            $access->user_id = $user->id;
            $access->block_id = $blockId;
            $access->course_id = $request->course_id;

            $access->save();
        }
    }

    public function GetCourseBlocks(Request $request, $courseId)
    {
        $course = Course::where('id', $courseId)->first();
        $blocks = CourseBlock::where('course_id', $course->id)->orderBy('index')->get();

        $response = [];

        foreach($blocks as $block)
        {
            $response[] = [
                'title' => $block->title,
                'id' => $block->id,
            ];
        }

        return $response;
    }

    public function GetCourseUserAccess(Request $request, $courseId, $userId)
    {
        $access = BlockAccess::where([['course_id', $courseId], ['user_id', $userId]])->get();

        $response = [];

        foreach ($access as $item)
        {
            $block = CourseBlock::where('id', $item->block_id)->first();
            $response[] = [
                'id' => $block->id,
                'title' => $block->title
            ];
        }

        return $response;
    }

    public function SetCourseUserAccess(Request $request, $courseId, $userId)
    {
        BlockAccess::where([['course_id', $courseId], ['user_id', $userId]])->delete();

        foreach($request->access as $blockId)
        {
            $access = new BlockAccess();

            $access->user_id = $userId;
            $access->block_id = $blockId;
            $access->course_id = $courseId;

            $access->save();
        }
    }

    public function GetCourseAll(Request $request)
    {
        $result = [];

        foreach(Course::get() as $course)
        {
            $result[] = [
                'date' => $course->date_start,
                'duration' => $course->duration,
                'title' => $course->name,
                'lectors' => $course->authors,
                'type' => '????????',
                'image' => url('/').'/'.$course->image_path,
            ];
        }

        return $result;
    }

    public function GetRecomendations(Request $request)
    {
        $courses = Course::latest()->take(6)->get();
        $response = [];

        foreach ($courses as $course)
        {
            $response[] = [
                'date' => $course->created_at,
                'duration' => $course->duration,
                'title' => $course->name,
                'lectors' => $course->authors,
                'type' => '????????',
                'image' => url('/').'/'.$course->image_path
            ];
        }

        return $response;
    }
}

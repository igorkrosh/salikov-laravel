<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Config;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use App\Models\Course;
use App\Models\CourseBlock;
use App\Models\ModuleStream;
use App\Models\ModuleVideo;
use App\Models\ModuleJob;
use App\Models\ModuleTest;
use App\Models\User;
use App\Models\BlockAccess;
use App\Models\CourseAccess;
use App\Models\File;
use App\Models\Progress;
use App\Models\CourseStatistic;
use App\Models\Task;
use App\Models\EducatorAccess;

class CourseController extends Controller
{
    public function CreateCourse(Request $request)
    {
        $course = new Course();
        $config = json_decode($request->input('course'));

        $date_start = $this->ConvertDate($config->date_start);

        $course->name = $config->name;
        $course->authors = $config->authors;
        $course->date_start = $date_start;
        $course->duration = $config->duration;
        $course->creator = Auth::user()->id;
        $course->image_path = '';
        $course->kinescope_id = '';
        $course->hours = $config->hours;
        $course->category = $config->category;
        $course->status = $config->status;

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

            $newBlock->course_id = $course->id;
            $newBlock->index = $index;
            $newBlock->title = $block->title;
            $newBlock->date_start = $this->ConvertDate($block->date);

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

                    if (!empty($module->new_files))
                    {
                        foreach ($module->new_files as $file)
                        {
                            app(FileController::class)->StoreModuleFile($request->file($file->id), $newModuleStream->id, $module->type);
                        }
                    }
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

                    if (!empty($module->new_files))
                    {
                        foreach ($module->new_files as $file)
                        {
                            app(FileController::class)->StoreModuleFile($request->file($file->id), $newModuleVideo->id, $module->type);
                        }
                    }
                }

                if ($module->type == 'job')
                {
                    $newModuleJob = new ModuleJob();

                    $newModuleJob->block_id = $newBlock->id;
                    $newModuleJob->index = $index;
                    $newModuleJob->authors = $module->authors;
                    $newModuleJob->title = $module->title;
                    $newModuleJob->text = $module->text;
                    $newModuleJob->check_date = $this->ConvertDate($module->check_date);
                    $newModuleJob->deadline = $this->ConvertDate($module->deadline);

                    $newModuleJob->save();

                    if (!empty($module->new_files))
                    {
                        foreach ($module->new_files as $file)
                        {
                            app(FileController::class)->StoreModuleFile($request->file($file->id), $newModuleJob->id, $module->type);
                        }
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
                    $newModuleTest->check_date = $check_date;
                    $newModuleTest->deadline = $deadline;

                    $newModuleTest->save();

                    if (!empty($module->new_files))
                    {
                        foreach ($module->new_files as $file)
                        {
                            app(FileController::class)->StoreModuleFile($request->file($file->id), $newModuleTest->id, $module->type);
                        }
                    }
                }
            }

            $MIGXbloks[$indexMIGX]['desc'] .= '</ul>';
        }

        $imgUrl = url('/').'/'.$course->image_path;

        $response = 0;

        if (config('modx.sync'))
        {
            $response = Http::withOptions([
                'verify' => false,
            ])->post(config('modx.api').'/CreateCourse', [
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

        if (Auth::user()->role == 'admin' || Auth::user()->role == 'moderator')
        {
            $courses = Course::all();
        }
        else 
        {
            $courses = Course::where('creator', $user_id)->get();
        }

        $result = [];

        foreach ($courses as $course)
        {
            $result[] = [
                'id' => $course->id,
                'image' => url('/').'/'.$course->image_path,
                'lectors' => $course->authors,
                'title' => $course->name,
                'type' => 'Курс',
                'status' => $course->status,
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
                'message' => 'Курс не найден'
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
                'message' => 'Курс не найден'
            ] , 422);
        }

        $modules = $this->GetModules($courseId);
        $blockAccess = BlockAccess::where([['course_id', $course->id], ['user_id', Auth::user()->id]])->first();
        $courseAccess = CourseAccess::where([['course_id', $course->id], ['user_id', Auth::user()->id]])->first();
        $courseAccess = empty($courseAccess) ? true : !Carbon::parse($courseAccess->deadline)->addDays(1)->isPast();
        
        $result = [
            'id' => $course->id,
            'name' => $course->name,
            'authors' => $course->authors,
            'date_start' => Carbon::parse($course->date_start)->translatedFormat('d.m.Y H:i'),
            'duration' => $course->duration,
            'image' => url('/').'/'.$course->image_path,
            'hours' => $course->hours,
            'blocks' => [],
            'lessons' => count($modules['stream']) + count($modules['video']) + count($modules['job']) + count($modules['test']),
            'buy_at' => empty($blockAccess) ? '' : $blockAccess->created_at,
            'category' => $course->category,
            'status' => $course->status,
            'access' => $courseAccess
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
                    'date' => Carbon::parse($module->date_start)->translatedFormat('d.m.Y H:i'),
                    'status' => $this->GetModuleStatus(Auth::user()->id, $module->id, 'stream'),
                    'access' => $this->IsStart($module->date_start),
                    'files' => $this->GetModuleFiles($module->id, 'stream'),
                    'deleted_files' => [],
                    'new_files' => [],
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
                    'access' => $this->IsStart($module->date_start),
                    'files' => $this->GetModuleFiles($module->id, 'video'),
                    'deleted_files' => [],
                    'new_files' => [],
                ];
            }

            $modulesJob = ModuleJob::where('block_id', $block->id)->get();

            foreach ($modulesJob as $module)
            {
                $task = Task::where([['user_id', Auth::user()->id], ['type', 'job'], ['module_id', $module->id]])->first();
                $data = [];

                if (!empty($task))
                {
                    $data = [
                        'title' => $module->title,
                        'comment' => $task->comment,
                        'job' => $module->text,
                        'answer' => $task->task,
                        'score' => $task->score,
                    ];
                }
                
                $modules[] = [
                    'id' => $module->id,
                    'type' => 'job',
                    'index' => $module->index,
                    'authors' => $module->authors,
                    'title' => $module->title,
                    'text' => $module->text,
                    'deadline' => Carbon::parse($module->deadline)->translatedFormat('d.m.Y H:i'),
                    'check_date' => Carbon::parse($module->check_date)->translatedFormat('d.m.Y H:i'),
                    'file_path' => url('/').'/'.$module->file,
                    'status' => $this->GetModuleStatus(Auth::user()->id, $module->id, 'job'),
                    'access' => true,
                    'files' => $this->GetModuleFiles($module->id, 'job'),
                    'deleted_files' => [],
                    'new_files' => [],
                    'data' => $data
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
                    'deadline' => Carbon::parse($module->deadline)->translatedFormat('d.m.Y H:i'),
                    'check_date' => Carbon::parse($module->check_date)->translatedFormat('d.m.Y H:i'),
                    'file_path' => url('/').'/'.$module->file,
                    'status' => $this->GetModuleStatus(Auth::user()->id, $module->id, 'test'),
                    'access' => true,
                    'files' => $this->GetModuleFiles($module->id, 'test'),
                    'deleted_files' => [],
                    'new_files' => [],
                ];
            }

            usort($modules, function($a, $b){
                return $a['index'] <=> $b['index'];
            });

            $access = !empty(BlockAccess::where([['user_id', Auth::user()->id], ['block_id', $block->id], ['course_id', $course->id]])->first());

            $result['blocks'][] = [
                'id' => $block->id,
                'title' => $block->title,
                'date' => Carbon::parse($block->date_start)->translatedFormat('d.m.Y H:i'),
                'index' => $block->index,
                'modules' => $modules,
                'access' => $access,
            ];

        }

        usort($result['blocks'], function($a, $b){
            return $a['index'] <=> $b['index'];
        });

        if (Auth::user()->role == 'user')
        {
            $this->SetCourseStatistic(Auth::user()->id, $courseId, 'last_visit', Carbon::now()->translatedFormat('d.m.Y H:i'));
        }

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
        $course->category = $config->category;
        $course->status = $config->status;

        if (!empty($config->image))
        {
            $filePath = $this->LoadImage($config->image, $course->id, 'images/courses/cover/');
            $course->image_path = $filePath;
        }

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

                $moduleId = null;

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

                        if (!empty($module->new_files))
                        {
                            foreach ($module->new_files as $file)
                            {
                                app(FileController::class)->StoreModuleFile($request->file($file->id), $moduleStream->id, 'stream');
                            }
                        }

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

                        if (!empty($module->new_files))
                        {
                            foreach ($module->new_files as $file)
                            {
                                app(FileController::class)->StoreModuleFile($request->file($file->id), $moduleVideo->id, 'video');
                            }
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

                        if (!empty($module->new_files))
                        {
                            foreach ($module->new_files as $file)
                            {
                                app(FileController::class)->StoreModuleFile($request->file($file->id), $moduleJob->id, 'job');
                            }
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
                        $moduleTest->check_date = $this->ConvertDate($module->check_date);
                        $moduleTest->deadline = $this->ConvertDate($module->deadline);

                        $moduleTest->save();   
                        
                        if (!empty($module->new_files))
                        {
                            foreach ($module->new_files as $file)
                            {
                                app(FileController::class)->StoreModuleFile($request->file($file->id), $moduleTest->id, 'test');
                            }
                        }

                        break;
                    default:
                        break;
                }

                if (!empty($module->deleted_files))
                {
                    foreach($module->deleted_files as $fileId)
                    {
                        app(FileController::class)->DeleteModuleFile($fileId);
                    }
                }

                
            }

        }

        $course->save();

        foreach(['stream', 'video', 'job', 'test'] as $type)
        {
            foreach ($config->deleted->$type as $id)
            {
                switch ($type) 
                {
                    case 'stream':
                        $module = ModuleStream::where('id', $id)->delete();
                        break;
                    case 'video':
                        $module = ModuleVideo::where('id', $id)->delete();
                        break;
                    case 'job':
                        $module = ModuleJob::where('id', $id)->delete();
                        break;
                    case 'test':
                        $module = ModuleTest::where('id', $id)->delete();
                        break;
                    default:
                        break;
                }

                $files = File::where([['module_id', $id], ['type', $type]])->get();
    
                foreach($files as $file)
                {
                    app(FileController::class)->DeleteModuleFile($file->id);
                    
                    $file->delete();
                }
            }
        }

        foreach ($config->deleted->block as $id)
        {
            foreach(['stream', 'video', 'job', 'test'] as $type)
            {
                switch ($type) 
                {
                    case 'stream':
                        $module = ModuleStream::where('block_id', $id)->first();
                        break;
                    case 'video':
                        $module = ModuleVideo::where('block_id', $id)->first();
                        break;
                    case 'job':
                        $module = ModuleJob::where('block_id', $id)->first();
                        break;
                    case 'test':
                        $module = ModuleTest::where('block_id', $id)->first();
                        break;
                    default:
                        break;
                }

                if (!empty($module))
                {
                    $files = File::where([['module_id', $module->id], ['type', $module->id]])->get();
    
                    foreach($files as $file)
                    {
                        app(FileController::class)->DeleteModuleFile($file->id);
                        
                        $file->delete();
                    }
    
                    $module->delete();
                }
            }

            CourseBlock::where('id', $id)->delete();
        }


        return $course;
    }

    public function DeleteCourse(Request $request, $courseId)
    {
        $blocks = CourseBlock::where('course_id', $courseId)->get();

        $modules = $this->GetModules($courseId);

        foreach (['stream', 'video', 'job', 'test'] as $type)
        {
            foreach($modules[$type] as $module)
            {
                $files = File::where([['module_id', $module->id], ['type', $type]])->get();
    
                foreach($files as $file)
                {
                    app(FileController::class)->DeleteModuleFile($file->id);
                    
                    $file->delete();
                }
    
                $module->delete();
            }
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
            $response = Http::withOptions([
                'verify' => false,
            ])->post(config('modx.api').'/DeleteCourse', [
                'id' => $courseId,
            ]);
        }
    }

    public function GetCourseUsers(Request $request, $courseId)
    {
        $course = Course::where('id', $courseId)->first();
        $creator = User::where('id', $course->creator)->first();
        
        $educatorsList = EducatorAccess::where('course_id', $courseId)->get();
        $educators = [];

        $response = [
            'curators' => [],
            'users' => [],
            'educators' => []
        ];

        foreach ($educatorsList as $educator)
        {
            $user = User::where('id', $educator->user_id)->first();

            $response['educators'][] = [
                'image' => empty($user->img_path) ? '' : url('/').'/'.$user->img_path,
                'name' => $user->name.' '.$user->last_name,
                'id' => $user->id,
            ];
        }

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

        if ($request->unlimited == true)
            return;
        
        $courseAccess = CourseAccess::where([['user_id', $user->id], ['course_id', $request->course_id]])->first();
        
        if (empty($courseAccess))
        {
            $courseAccess = new CourseAccess();

            $courseAccess->user_id = $user->id;
            $courseAccess->course_id = $request->course_id;
        }

        $courseAccess->deadline = Carbon::parse($request->access_date)->translatedFormat('Y-m-d H:i:s');

        $courseAccess->save();
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

        $response = [
            'access' => [],
            'accessDate' => [],
        ];

        foreach ($access as $item)
        {
            $block = CourseBlock::where('id', $item->block_id)->first();
            $response['access'][] = [
                'id' => $block->id,
                'title' => $block->title
            ];
        }

        $courseAccess = CourseAccess::where([['user_id', $userId], ['course_id', $courseId]])->first();

        if (!empty($courseAccess))
        {
            $response['accessDate'] = Carbon::parse($courseAccess->deadline)->translatedFormat('d.m.Y H:i');
        }
        else 
        {
            $response['accessDate'] = false;
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

        if ($request->accessDate == false)
            return CourseAccess::where([['user_id', $userId], ['course_id', $courseId]])->delete();
        
        $courseAccess = CourseAccess::where([['user_id', $userId], ['course_id', $courseId]])->first();
        
        if (empty($courseAccess))
        {
            $courseAccess = new CourseAccess();

            $courseAccess->user_id = $userId;
            $courseAccess->course_id = $courseId;
        }

        $courseAccess->deadline = Carbon::parse($request->accessDate)->translatedFormat('Y-m-d H:i:s');

        $courseAccess->save();
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
                'type' => 'Курс',
                'image' => url('/').'/'.$course->image_path,
            ];
        }

        return $result;
    }

    public function GetCoursesByStatus(Request $request, $status)
    {
        $result = [];

        foreach(Course::where('status', $status)->get() as $course)
        {
            $result[] = [
                'id' => $course->id,
                'date' => $course->date_start,
                'duration' => $course->duration,
                'title' => $course->name,
                'lectors' => $course->authors,
                'type' => 'Курс',
                'image' => url('/').'/'.$course->image_path,
            ];
        }

        return $result;
    }

    public function CourseCaterogies(Request $request)
    {
        return Http::withOptions([
            'verify' => false,
        ])->get(config('modx.api').'/CourseCategoriesList')->object();
    }

    public function CourseFilter(Request $request)
    {
        $categories = Http::withOptions([
            'verify' => false,
        ])->get(config('modx.api').'/CourseCategoriesList')->object();
        $courses = Course::get();
        
        $filterCourses = [];

        foreach ($courses as $course)
        {
            $filterCourses[] = [
                'name' => $course->name,
                'id' => $course->id
            ];
        }

        return [
            'categories' => $categories,
            'courses' => $filterCourses
        ];
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
                'type' => 'Курс',
                'image' => url('/').'/'.$course->image_path,
                'link' => config('modx.api').'/CourseRedirect?id='.$course->id
            ];
        }

        return $response;
    }

    public function CourseStudents(Request $request)
    {
        if (empty($request->filter) || empty($request->filter['course']))
        {
            return [
                'users' => [],
                'count' => 0
            ];
        }

        $users = DB::table('users');

        $useSort = !empty($request->filter['sortBy']);

        $courseId = $request->filter['course'];
        $course = Course::where('id', $courseId)->first();

        $blockAccess = BlockAccess::where('course_id', $course->id)->get()->unique('user_id');
        $ids = [];
        
        foreach ($blockAccess as $access)
        {
            $ids[] = $access->user_id;
        }

        $users->where(function ($query) use ($ids) {
            $query->whereIn('id', $ids);
        });

        $users = $users->get();

        $result = [];

        foreach ($users as $user)
        {
            $progress = $this->CalcCourseProgress($course->id, $user->id) == 100 ? 'done' : 'work';
            $courseStatistic = CourseStatistic::where([['course_id', $course->id], ['user_id', $user->id]])->first();
            $statistic = [];

            if (empty($courseStatistic))
            {
                $statistic = [
                    'date_complete' => '-',
                    'date_payment' => '-',
                    'last_visit' => '-',
                ];
            }
            else 
            {
                $statistic = [
                    'date_complete' => $courseStatistic->date_complete,
                    'date_payment' => $courseStatistic->date_payment,
                    'last_visit' => $courseStatistic->last_visit,
                ];
            }

            $result[] = [
                'user' => [
                    'id' => $user->id,
                    'avatar' => empty($user->img_path) ? '' : url('/').'/'.$user->img_path,
                    'name' => $user->name.' '.$user->last_name,
                    'email' => $user->email,
                ],
                'status' => $progress,
                'created_at' => empty($user->created_at) ? '-' : Carbon::parse($user->created_at)->translatedFormat('d.m.Y H:i'),
                'statistic' => $statistic
            ];
        }

        if ($useSort)
        {
            $sortBy = $request->filter['sortBy'];
            $sortDir = $request->filter['sortDir'];

            if ($sortDir == 'asc')
            {
                usort($result, function($a, $b) use ($sortBy) {
                    $dateA = $a['statistic'][$sortBy] == '-' ? 0 : Carbon::parse($a['statistic'][$sortBy])->timestamp;
                    $dateB = $b['statistic'][$sortBy] == '-' ? 0 : Carbon::parse($b['statistic'][$sortBy])->timestamp;
                    
                    return $dateA >= $dateB;
                });
            }
            else 
            {
                usort($result, function($a, $b) use ($sortBy) {
                    $dateA = $a['statistic'][$sortBy] == '-' ? 0 : Carbon::parse($a['statistic'][$sortBy])->timestamp;
                    $dateB = $b['statistic'][$sortBy] == '-' ? 0 : Carbon::parse($b['statistic'][$sortBy])->timestamp;
                    
                    return $dateA <= $dateB;
                });
            }
        }
        

        return [
            'users' => $result,
            'count' => count($result)
        ];
    }

    public function CalcCourseProgress($courseId, $userId)
    {
        $course = Course::where('id', $courseId)->first();
        $user = User::where('id', $userId)->first();

        $modules = $this->GetModules($course->id);

        $count = count($modules['stream']) + count($modules['video']) + count($modules['job']) + count($modules['test']);
        $done = 0;

        $moduleTypes = ['stream', 'video', 'job', 'test'];

        foreach ($moduleTypes as $key)
        {
            foreach ($modules[$key] as $module)
            {
                $progress = Progress::where([['module_id', $module->id], ['type', $key], ['user_id', $userId]])->first();

                if (empty($progress))
                {
                    continue;
                }

                if ($progress->status == 'done')
                {
                    $done++;
                }
            }
        }

        if ($done == 0)
        {
            $progress = 0;
        }
        else 
        {
            $progress = round(($done / $count) * 100);
        }

        return $progress;
    }

    public function AddEducator(Request $request, $courseId)
    {
        $request->validate([
            'email' => ['required']
        ]);

        $educator = User::where('email', $request->email)->first();

        if (empty($educator))
        {
            return response()->json([
                'message' => 'Пользователь не найдет'
            ] , 422);
        }

        if ($educator->role != 'educator')
        {
            return response()->json([
                'message' => 'Пользователь не преподаватель'
            ] , 422);
        }

        $access = EducatorAccess::where('course_id', $courseId)->first();

        if (!empty($access))
        {
            return response()->json([
                'message' => 'Пользователь уже добавлен в преподаватели'
            ] , 422);
        }

        $access = new EducatorAccess();

        $access->user_id = $educator->id;
        $access->course_id = $courseId;

        $access->save();
    }
}

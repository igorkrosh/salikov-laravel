<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\ModuleStream;
use App\Models\ModuleVideo;
use App\Models\ModuleJob;
use App\Models\ModuleTest;
use App\Models\Task;
use App\Models\Progress;
use App\Models\User;
use App\Models\TestResult;
use App\Models\Course;
use App\Models\CourseBlock;

class ModuleController extends Controller
{
    public function GetModuleById(Request $request, $type, $moduleId)
    {
        switch ($type) 
        {
            case 'stream':
                return ModuleStream::where('id', $moduleId)->first();
            case 'video':
                return ModuleVideo::where('id', $moduleId)->first();
            case 'job':
                $module =  ModuleJob::where('id', $moduleId)->first();

                $result = [
                    'title' => $module->title,
                    'text' => $module->text,
                    'file' => url('/').'/'.$module->file,
                ];

                return $result;
            case 'test':
                $module =  ModuleTest::where('id', $moduleId)->first();

                $result = [
                    'test' => $module->test,
                    'file' => url('/').'/'.$module->file,
                ];

                return $result;
            default:
                return null;
        }
    }

    public function SetModuleProgress(Request $request, $type, $moduleId)
    {
        $user = Auth::user();
        $progress = Progress::where([['user_id', $user->id], ['module_id', $moduleId], ['type', $type]])->first();

        if (empty($progress))
        {
            $progress = new Progress();

            $progress->user_id = $user->id;
            $progress->module_id = $moduleId;
            $progress->type = $type;
        }

        $progress->status = $request->status;
        $progress->save();

        return $progress;
    }

    public function SetModuleTask(Request $request, $type, $moduleId)
    {
        $user = Auth::user();
        $task = Task::where([['user_id', $user->id], ['module_id', $moduleId], ['type', $type]])->first();

        if (empty($task))
        {
            $task = new Task();

            $task->user_id = $user->id;
            $task->module_id = $moduleId;
            $task->type = $type;

            switch ($type) 
            {
                case 'stream':
                    $module = ModuleStream::where('id', $moduleId)->first();
                    break;
                case 'video':
                    $module = ModuleVideo::where('id', $moduleId)->first();
                    break;
                case 'job':
                    $module = ModuleJob::where('id', $moduleId)->first();
                    break;
                case 'test':
                    $module = ModuleTest::where('id', $moduleId)->first();
                    break;
                default:
                    $module = null;
                    break;
            }
            $task->course_id = CourseBlock::where('id', $module->block_id)->first()->course_id;
        }

        $task->task = $request->input('text');
        $task->save();

        if (!empty($request->file('file')))
        {
            $path = app('App\Http\Controllers\FileController')->StoreJobFile($request->file('file'), $moduleId);
            $task->file = $path;
            $task->save();
        }

        $request->replace(['status' => 'check']);

        $this->SetModuleProgress($request, $type, $moduleId);

        return $task;
    }

    public function SetTestResult(Request $request, $moduleId)
    {
        $user = Auth::user();

        $testResult = TestResult::where([['user_id', $user->id], ['module_id', $moduleId]])->first();

        if (empty($testResult))
        {
            $testResult = new TestResult();

            $testResult->user_id = $user->id;
            $testResult->module_id = $moduleId;
        }

        $result = [
            'data' => $request->answers,
            'result' => [],
        ];

        $test = ModuleTest::where('id', $moduleId)->first();

        $incorrect = 0;

        foreach($test->test as $i => $question)
        {
            $result['result'][$i] = true;
            
            $answer = $request->answers[$i];

            if (empty($answer))
            {
                $incorrect++;
                $result['result'][$i] = false;
                continue;
            }

            foreach ($answer as $j => $item)
            {
                if(!$question['answer'][$item]['correct'])
                {
                    $incorrect++;
                    $result['result'][$i] = false;

                    break;
                }
            }
        }

        $testResult->answers = $result;
        $testResult->correct = count($test->test) - $incorrect;
        $testResult->incorrect = $incorrect;
        $testResult->save();

        if ($testResult->incorrect == 0)
        {
            $request->replace(['status' => 'done']);
        }
        else 
        {
            $request->replace(['status' => 'close']);
        }


        $this->SetModuleProgress($request, 'test', $moduleId);

        return $testResult;
    }

    public function GetTestResult(Request $request, $moduleId)
    {
        $user = Auth::user();

        return TestResult::where([['user_id', $user->id], ['module_id', $moduleId]])->first();
    }

    public function GetModuleProgress(Request $request, $type, $moduleId)
    {
        $user = Auth::user();

        return Progress::where([['user_id', $user->id], ['module_id', $moduleId], ['type', $type]])->first();
    }

    public function GetCheckTaskList(Request $request)
    {
        $user = Auth::user();
        $courses = Course::where('creator', $user->id)->get();

        $result = [];

        foreach ($courses as $item)
        {
            $tasks = Task::where('course_id', $item->id)->get();

            foreach ($tasks as $task)
            {
                $module = $this->GetModuleByType($task->module_id, $task->type);
                $student = User::where('id', $task->user_id)->first();
                $result[] = [
                    'course' => $item->name,
                    'module' => $module->title,
                    'date' => $module->check_date,
                    'module_id' => $module->id,
                    'task_id' => $task->id,
                    'score' => $task->score,
                    'user' => $student->name.' '.$student->last_name,
                ];
            }
        }

        return $result;
    }

    public function GetTask(Request $request, $taskId)
    {
        $task = Task::where('id', $taskId)->first();
        $user = User::where('id', $task->user_id)->first();
        $module = $this->GetModuleByType($task->module_id, $task->type);
        
        $result = [
            'user' => $user->name.' '.$user->last_name,
            'question' => $module->text,
            'answer' => $task->task,
            'comment' => $task->comment,
            'score' => $task->score,
            'task_id' => $task->id,
            'module_id' => $module->id,
            'file' => empty($task->file) ? '' : url('/').'/'.$task->file,
            'user_id' => $task->user_id
        ];

        return $result;
    }

    public function SetCheckTask(Request $request, $taskId)
    {
        $task = Task::where('id', $taskId)->first();

        $task->comment = empty($request->comment) ? '' : $request->comment;
        $task->score = $request->score;

        $task->save();

        $module = $this->GetModuleByType($task->module_id, $task->type);
        $moduleTitle = $module->title;

        app('App\Http\Controllers\NotificationController')->CreateNotification($task->user_id, 'Задание проверено', "Задание \"$moduleTitle\" проверено.");

        return $task;
    }
}

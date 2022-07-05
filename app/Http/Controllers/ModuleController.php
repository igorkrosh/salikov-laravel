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

class ModuleController extends Controller
{
    public function GetModuleById(Request $request, $type, $moduleId)
    {
        switch ($type) 
        {
            case 'stream':
                return ModuleStream::where('id', $moduleId)->first();
                break;
            case 'video':
                return ModuleVideo::where('id', $moduleId)->first();
                break;
            case 'job':
                return ModuleJob::where('id', $moduleId)->first();
                break;
            case 'test':
                return ModuleTest::where('id', $moduleId)->first();
                break;
            default:
                return null;
                break;
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
        }

        $task->task = $request->task;
        $task->save();

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

        if ($testResult->correct > $testResult->incorrect)
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
}

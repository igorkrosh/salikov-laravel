<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Course;
use App\Models\CourseBlock;
use App\Models\ModuleStream;
use App\Models\ModuleVideo;
use App\Models\ModuleJob;
use App\Models\ModuleTest;
use App\Models\BlockAccess;

class NotificationDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all notifications once at day';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->NotificationStartCourse();
        $this->NotificationStartBlock();
        $this->NotificationDeadlineJob();
        $this->NotificationDeadlineTest();

        return 0;
    }

    private function IsToday($date)
    {
        $current = date("Y-m-d");
        $dateCheck = date_create($date);
        $dateCheck =  date_format($dateCheck,"Y-m-d");

        return $current == $dateCheck;
    }

    private function NotificationStartCourse()
    {
        $courses = Course::get();

        foreach ($courses as $course)
        {
            $courseTitle = $course->name;
            $courseDate = date_format(date_create($course->date_start),"H:i");
            
            if ($this->IsToday($course->date_start))
            {
                $users = BlockAccess::where('course_id', $course->id)->get()->unique('user_id');

                foreach($users as $user)
                {
                    app('App\Http\Controllers\NotificationController')->CreateNotification($user->id, 'Начало нового курса.', "Курс \"$courseTitle\" начинается сегодня в $courseDate");
                }
            }
        }
    }

    private function NotificationStartBlock()
    {
        $blocks = CourseBlock::get();

        foreach ($blocks as $block)
        {
            $blockTitle = $block->title;
            $blockDate = date_format(date_create($block->date_start),"H:i");

            if ($this->IsToday($block->date_start))
            {
                $users = BlockAccess::where('block_id', $block->id)->get()->unique('user_id');

                foreach($users as $user)
                {
                    app('App\Http\Controllers\NotificationController')->CreateNotification($user->id, 'Начало блока курса.', "Блок \"$blockTitle\" начинается сегодня в $blockDate");
                }
            }
        }
    }

    private function NotificationDeadlineJob()
    {
        $modules = ModuleJob::get();

        foreach ($modules as $module)
        {
            $moduleTitle = $module->title;
            $moduleDate = date_format(date_create($module->deadline),"H:i");

            if ($this->IsToday($module->deadline))
            {
                $users = BlockAccess::where('block_id', $module->block_id)->get()->unique('user_id');

                foreach($users as $user)
                {
                    app('App\Http\Controllers\NotificationController')->CreateNotification($user->id, 'Последний день сдачи задания.', "Задание \"$moduleTitle\" можно сдать сегодня до $moduleDate");
                }
            }
        }
    }

    private function NotificationDeadlineTest()
    {
        $modules = ModuleTest::get();

        foreach ($modules as $module)
        {
            $moduleTitle = $module->title;
            $moduleDate = date_format(date_create($module->deadline),"H:i");

            if ($this->IsToday($module->deadline))
            {
                $users = BlockAccess::where('block_id', $module->block_id)->get()->unique('user_id');

                foreach($users as $user)
                {
                    app('App\Http\Controllers\NotificationController')->CreateNotification($user->id, 'Последний день сдачи теста.', "Тест \"$moduleTitle\" можно сдать сегодня до $moduleDate");
                }
            }
        }
    }
}

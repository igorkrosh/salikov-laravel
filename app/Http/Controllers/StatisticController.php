<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

use App\Models\Order;
use App\Models\Promocode;
use App\Models\Course;

class StatisticController extends Controller
{
    public function StatisticToday(Request $request)
    {
        $orders = Order::whereDate('created_at', date('Y-m-d'))->get();

        $stats = [];

        foreach ($orders as $order)
        {
            if ($order->status != 'CONFIRMED')  continue;

            $hour = date('H', strtotime($order->created_at));

            if (empty($stats[$hour]))
            {
                $stats[$hour] = 0;
            }

            $stats[$hour] += $order->price;
            
        }

        $result = [];

        foreach (['00:00', '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'] as $time)
        {
            if (empty($stats[intval($time)]))
            {
                $result[] = [$time, 0];
            }
            else 
            {
                $result[] = [$time, $stats[intval($time)]];
            }
        }

        return $result;
    }

    public function StatisticDays(Request $request, $days)
    {
        $date = Carbon::today()->subDays($days);
        $period = CarbonPeriod::create($date, Carbon::today());

        $stats = [];

        foreach ($period as $date)
        {
            $formatDate = Carbon::parse($date)->translatedFormat('M d');
            $formatDate = mb_convert_case($formatDate, MB_CASE_TITLE);
            $orders = Order::whereDate('created_at', $date)->get();
            $price = 0;

            foreach ($orders as $order)
            {
                $price += $order->price;
            }

            $stats[$formatDate] = $price;
        }

        return $stats;
    }

    public function StatisticYear(Request $request)
    {
        $today = Carbon::now();
        $stats = [];
        
        foreach(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'] as $month)
        {
            $orders = Order::whereYear('created_at', $today->year)->whereMonth('created_at', $month)->get();
            $price = 0;

            foreach ($orders as $order)
            {
                $price += $order->price;
            }

            $monthDate = Carbon::parse($today->year."-".$month.'-01')->translatedFormat('M');

            $stats[] = [$monthDate, $price];
        }

        return $stats;
    }

    public function StatisticCourses(Request $request)
    {
        $courses = Course::get();
        $result = [];

        foreach($courses as $course)
        {
            $orders = Order::where([['object_id', $course->id], ['type', 'course']])->get();
            $sum = 0;

            foreach($orders as $order)
            {
                $sum += $order->price;
            }
            $result[] = [
                'name' => $course->name,
                'count' => $course->count,
                'orders' => count($orders),
                'sum' => $sum
            ];
        }

        return $result; 
    }

    public function CourseEnter(Request $request, $courseId)
    {
        $course = Course::where('id', $courseId)->first();

        $course->count += 1;

        $course->save();
    }
}

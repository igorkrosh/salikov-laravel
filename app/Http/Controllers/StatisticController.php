<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

use App\Models\Order;
use App\Models\Promocode;
use App\Models\Course;
use App\Models\Webinar;
use App\Models\JuricticRequest;

class StatisticController extends Controller
{
    public function StatisticToday(Request $request)
    {
        $result = [
            'chart' => [],
            'numbers' => [
                'sum' => 0,
                'count' => 0,
                'jurictic' => 0,
            ],
        ];

        if ($request->personal)
        {
            $courses = Course::where('creator', Auth::user()->id)->get();
            $ids = [];
    
            foreach ($courses as $course)
            {
                $ids[] = $course->id;
            }
    
            $ordersCourse = Order::where([['type', 'course'], ['status', 'CONFIRMED']])->whereDate('created_at', date('Y-m-d'))->whereIn('object_id', $ids)->get();

            $webinars = Webinar::where('creator', Auth::user()->id)->get();
            $ids = [];
    
            foreach ($webinars as $webinar)
            {
                $ids[] = $webinar->id;
            }

            $ordersWebinar = Order::where([['type', 'webinar'], ['status', 'CONFIRMED']])->whereDate('created_at', date('Y-m-d'))->whereIn('object_id', $ids)->get();

            $orders = $ordersCourse->concat($ordersWebinar);

            $result['numbers']['jurictic'] += count(JuricticRequest::whereDate('created_at', date('Y-m-d'))->whereIn('object_id', $ids)->get());
        }
        else 
        {
            $orders = Order::where('status', 'CONFIRMED')->whereDate('created_at', date('Y-m-d'))->get();

            $result['numbers']['jurictic'] += count(JuricticRequest::whereDate('created_at', date('Y-m-d'))->get());
        }

        $stats = [];

        foreach ($orders as $order)
        {
            if ($order->status != 'CONFIRMED')  continue;

            $hour = date('H', strtotime($order->created_at));
            $hour = intval($hour);

            if (empty($stats[$hour]))
            {
                $stats[$hour] = 0;
            }

            $stats[$hour] += $order->price;

            $result['numbers']['sum'] += $order->price;
            $result['numbers']['count'] += 1;
        }

        foreach (['00:00', '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'] as $time)
        {
            if (empty($stats[intval($time)]))
            {
                $result['chart'][] = [$time, 0];
            }
            else 
            {
                $result['chart'][] = [$time, $stats[intval($time)]];
            }


        }

        return $result;
    }

    public function StatisticDays(Request $request, $days)
    {
        $date = Carbon::today()->subDays($days);
        $period = CarbonPeriod::create($date, Carbon::today());

        $stats = [];
        $result = [
            'chart' => [],
            'numbers' => [
                'sum' => 0,
                'count' => 0,
                'jurictic' => 0
            ],
        ];

        foreach ($period as $date)
        {
            $formatDate = Carbon::parse($date)->translatedFormat('M d');
            $formatDate = mb_convert_case($formatDate, MB_CASE_TITLE);

            if ($request->personal)
            {
                $courses = Course::where('creator', Auth::user()->id)->get();
                $ids = [];
        
                foreach ($courses as $course)
                {
                    $ids[] = $course->id;
                }
        
                $ordersCourse = Order::where([['type', 'course'], ['status', 'CONFIRMED']])->whereDate('created_at', $date)->whereIn('object_id', $ids)->get();

                $webinars = Webinar::where('creator', Auth::user()->id)->get();
                $ids = [];
        
                foreach ($webinars as $webinar)
                {
                    $ids[] = $webinar->id;
                }

                $ordersWebinar = Order::where([['type', 'webinar'], ['status', 'CONFIRMED']])->whereDate('created_at', $date)->whereIn('object_id', $ids)->get();

                $orders = $ordersCourse->concat($ordersWebinar);

                $result['numbers']['jurictic'] += count(JuricticRequest::whereDate('created_at', $date)->whereIn('object_id', $ids)->get());
            }
            else 
            {
                $orders = Order::where('status', 'CONFIRMED')->whereDate('created_at', $date)->get();
                $result['numbers']['jurictic'] += count(JuricticRequest::whereDate('created_at', $date)->get());
            }

            $price = 0;

            foreach ($orders as $order)
            {
                $price += $order->price;
                $result['numbers']['sum'] += $order->price;
                $result['numbers']['count'] += 1;
            }

            $stats[$formatDate] = $price;
        }

        $result['chart'] = $stats;
        return $result;
    }

    public function StatisticYear(Request $request)
    {
        $today = Carbon::now();
        $stats = [];
        $result = [
            'chart' => [],
            'numbers' => [
                'sum' => 0,
                'count' => 0,
                'jurictic' => 0
            ],
        ];
        
        foreach(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'] as $month)
        {
            if ($request->personal)
            {
                $courses = Course::where('creator', Auth::user()->id)->get();
                $ids = [];
        
                foreach ($courses as $course)
                {
                    $ids[] = $course->id;
                }
        
                $ordersCourse = Order::where([['type', 'course'], ['status', 'CONFIRMED']])->whereYear('created_at', $today->year)->whereMonth('created_at', $month)->whereIn('object_id', $ids)->get();

                $webinars = Webinar::where('creator', Auth::user()->id)->get();
                $ids = [];
        
                foreach ($webinars as $webinar)
                {
                    $ids[] = $webinar->id;
                }

                $ordersWebinar = Order::where([['type', 'webinar'], ['status', 'CONFIRMED']])->whereYear('created_at', $today->year)->whereMonth('created_at', $month)->whereIn('object_id', $ids)->get();

                $orders = $ordersCourse->concat($ordersWebinar);

                $result['numbers']['jurictic'] += count(JuricticRequest::whereYear('created_at', $today->year)->whereMonth('created_at', $month)->whereIn('object_id', $ids)->get());
            }
            else 
            {
                $orders = Order::where('status', 'CONFIRMED')->whereYear('created_at', $today->year)->whereMonth('created_at', $month)->get();
                $result['numbers']['jurictic'] += count(JuricticRequest::whereYear('created_at', $today->year)->whereMonth('created_at', $month)->get());
            }

            $price = 0;

            foreach ($orders as $order)
            {
                $price += $order->price;
                $result['numbers']['sum'] += $order->price;
                $result['numbers']['count'] += 1;
            }

            $monthDate = Carbon::parse($today->year."-".$month.'-01')->translatedFormat('M');

            $stats[] = [$monthDate, $price];
        }

        $result['chart'] = $stats;
        return $result;
    }

    public function StatisticCourses(Request $request)
    {
        if ($request->personal)
        {
            $courses = Course::where('creator', Auth::user()->id)->get();
        }
        else 
        {
            $courses = Course::get();
        }
        $result = [];

        foreach($courses as $course)
        {
            $orders = Order::where([['object_id', $course->id], ['type', 'course'], ['status', 'CONFIRMED']])->get();
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

    public function StatisticNumbers(Request $request)
    {
        if ($request->personal)
        {
            $courses = Course::where('creator', Auth::user()->id)->get();
            $ids = [];
    
            foreach ($courses as $course)
            {
                $ids[] = $course->id;
            }
    
            $ordersCourse = Order::where([['type', 'course'], ['status', 'CONFIRMED']])->whereIn('object_id', $ids)->get();
            $juricticCourse = JuricticRequest::where('type', 'course')->whereIn('object_id', $ids)->get();

            $webinars = Webinar::where('creator', Auth::user()->id)->get();
            $ids = [];
    
            foreach ($webinars as $webinar)
            {
                $ids[] = $webinar->id;
            }

            $ordersWebinar = Order::where([['type', 'webinar'], ['status', 'CONFIRMED']])->whereIn('object_id', $ids)->get();
            $juricticWebinar = JuricticRequest::where('type', 'webinar')->whereIn('object_id', $ids)->get();

            $orders = $ordersCourse->concat($ordersWebinar);
            $juricticRequests = $juricticCourse->concat($juricticWebinar);
        }
        else 
        {
            $orders = Order::get();
            $juricticRequests = JuricticRequest::get();
        }

        $orders = Order::where('status', 'CONFIRMED')->get();
        $juricticRequests = JuricticRequest::get();

        $sum = 0;

        foreach ($orders as $order)
        {
            $sum += $order->price;
        }

        $count = count($orders);
        $juricticCount = count($juricticRequests);

        return [
            'sum' => $sum,
            'count' => $count,
            'jurictic' => $juricticCount
        ];
    }
}

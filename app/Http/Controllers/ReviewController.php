<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

use App\Models\Review;
use App\Models\User;
use App\Models\Course;
use App\Models\Webinar;
use App\Models\ModuleVideo;
use App\Models\ModuleStream;

class ReviewController extends Controller
{
    public function CreateReview(Request $request, $type, $objectId, $method)
    {
        if ($request->score['speakers'] == 0 || $request->score['material'] == 0 || $request->score['files'] == 0 || $request->score['useful'] == 0)
        {
            return response()->json([
                'message' => 'Форма содержит ошибки'
            ] , 422);
        }
        
        if ($method == 'add')
        {
            $review = new Review();
        }

        if ($method == 'edit')
        {
            $review = Review::where([['user_id', Auth::user()->id], ['type', $type], ['object_id', $objectId]])->first();
        }

        $review->user_id = Auth::user()->id;
        $review->type = $type;
        $review->object_id = $objectId;
        $review->speakers = $request->score['speakers'];
        $review->material = $request->score['material'];
        $review->files = $request->score['files'];
        $review->useful = $request->score['useful'];
        $review->text = empty($request->text) ? '' : $request->text;

        $review->save();
    }

    public function GetReview(Request $request, $type, $objectId)
    {
        return Review::where([['user_id', Auth::user()->id], ['type', $type], ['object_id', $objectId]])->first();
    }

    public function GetAllReviewByType(Request $request, $type)
    {
        $reviews = Review::where('type', $type)->get();

        $result = [];

        foreach($reviews as $review)
        {
            $user = User::where('id', $review->user_id)->first();

            switch ($type) 
            {
                case 'course':
                    if (!empty(Course::where('id', $review->object_id)->first()))
                        $name = Course::where('id', $review->object_id)->first()->name;
                    break;
                case 'stream':
                    if (!empty(ModuleStream::where('id', $review->object_id)->first()))
                        $name = ModuleStream::where('id', $review->object_id)->first()->title;
                    break;
                case 'video':
                    if (!empty(ModuleVideo::where('id', $review->object_id)->first()))
                        $name = ModuleVideo::where('id', $review->object_id)->first()->title;
                    break;
                case 'webinar':
                    if (!empty(Webinar::where('id', $review->object_id)->first()))
                        $name = Webinar::where('id', $review->object_id)->first()->name;
                    break;
                default:
                    $name = null;
                    break;
            }

            if (empty($name))
            {
                continue;
            }

            $result[] = [
                'user' => [
                    'name' => $user->name.' '.$user->last_name,
                    'id' => $user->id,
                    'avatar' => empty($user->img_path) ? '' : url('/').'/'.$user->img_path,
                ],
                'name' => $name,
                'speakers' => $review->speakers,
                'material' => $review->material,
                'files' => $review->files,
                'useful' => $review->useful,
                'date' => Carbon::parse($review->updated_at)->translatedFormat('Y.m.d'),
                'text' => $review->text
            ];
        }

        return $result;
    }
}

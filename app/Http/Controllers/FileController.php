<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

use Image;

use App\Models\User;
use App\Models\Course;
use App\Models\Webinar;
use App\Models\ModuleJob;

class FileController extends Controller
{
    public function StoreUserAvatar(Request $request)
    {
        $this->validate($request, [
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        $user = Auth::user();
        $avatar = $request->file('avatar');

        $imagePath = $this->StoreImage($user->id, $avatar, '/images/users/avatars', 120);

        $userModel = User::where('id', $user->id)->first();
        $userModel->img_path = $imagePath;
        $userModel->save();

        return $userModel->img_path;
    }

    public function StoreCourseCover(Request $request, $courseId)
    {
        $this->validate($request, [
            'cover' => 'required|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        $course = Course::where('id', $courseId)->first();
        $cover = $request->file('cover');

        $imagePath = $this->StoreImage($courseId, $cover, '/images/courses/cover', 500);

        $course->image_path = $imagePath;
        $course->save();

        return $imagePath;
    }

    public function StoreWebinarCover(Request $request, $webinarId)
    {
        $this->validate($request, [
            'cover' => 'required|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        $webinar = Webinar::where('id', $webinarId)->first();
        $cover = $request->file('cover');

        $imagePath = $this->StoreImage($webinarId, $cover, '/images/webinars/cover', 500);

        $webinar->image_path = $imagePath;
        $webinar->save();

        return $imagePath;
    }

    public function StoreJobFile($file, $moduleId)
    {
        $path = 'files/modules/job/'.$moduleId.'/';
        Storage::disk('public')->putFileAs($path, $file, $file->getClientOriginalName());

        return 'storage/'.$path.$file->getClientOriginalName();
    }

    public function StoreTestFile($file, $moduleId)
    {
        $path = 'files/modules/test/'.$moduleId.'/';
        Storage::disk('public')->putFileAs($path, $file, $file->getClientOriginalName());

        return 'storage/'.$path.$file->getClientOriginalName();
    }

    private function StoreImage($name, $image, $path, $fit)
    {
        $filename = $name.'.'.$image->extension();
        $filePath = Storage::disk('public')->path($path);

        $img = Image::make($image->path());
        $img->fit($fit, $fit)->save($filePath.'/'.$filename);

        $time = time();

        return 'storage'.$path.'/'.$filename."?v=$time";
    }
}

<?php

namespace App\Helpers;
use File;

class ImageHelper
{
	public static function upload($file,$path,$model)
	{
        // dd($file,$path,$model);
        $number = mt_rand(1000000000, 9999999999);
        $time = time();
        $extension = $file->getClientOriginalExtension();
        $file_name = $time.$number.'.'.$extension;
        $file->move($path, $file_name);
        // dd($file_name,$model);
        if ($model && $model->image) {
            $old_file = $path.$model->image;
            if (file_exists($old_file)) {
                File::delete($old_file);
            }
        }
        return $file_name;
	}

    public static function delete($path)
    {
        if(File::exists(public_path($path))){
            File::delete(public_path($path));
            return true;
        }
        return false;
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserBusinessCategoryService extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    public function user_category()
    {
        return $this->belongsTo(UserCategory::class);
    }

    public function user_service()
    {
        return $this->belongsTo(UserService::class);
    }


    /**
    * Override parent boot and Call deleting event
    *
    * @return void
    */
    protected static function boot() 
    {

        parent::boot();

        static::deleting(function($model) {
            $model->user_service()->delete();
        });

        /*restoring*/
        static::restoring(function($model) {
            $model->user_service()->withTrashed()->delete();
        });
    }

}

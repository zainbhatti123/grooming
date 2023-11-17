<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory;
    use SoftDeletes;
    // protected $guraded = [];
    protected $fillable = [
        'booking_no','user_id','user_business_id','total_duration','date','estimated_time','payment_type','charges','payment_intent_id','billing_id','balance_transaction','billing_status','receipt_url'
    ];

    public function booking_services()
    {
        return $this->hasMany(BookingService::class);  
    }

    public function user()
    {
        return $this->belongsTo(User::class);  
    }

    public function user_business()
    {
        return $this->belongsTo(UserBusiness::class);  
    }
}

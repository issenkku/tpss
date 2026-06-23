<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M11 — ประวัติการอนุมัติของรายวิชาที่เปิดสอน (audit ของ workflow อนุมัติ)
 * ตารางมีแค่ created_at (useCurrent) ไม่มี updated_at → ปิด timestamps อัตโนมัติ
 */
class CourseOfferingApproval extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'course_offering_id',
        'actor_user_id',
        'action',       // submit | approve | reject | revise
        'comment',
        'from_status',
        'to_status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

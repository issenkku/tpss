<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * การแจ้งเตือนในระบบ (M11 ใช้ type 'approval_update' สำหรับ workflow อนุมัติ)
 * ตารางมีแค่ created_at — ปิด timestamps อัตโนมัติ
 */
class Notification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'schedule_id',
        'course_offering_id',
        'type',
        'message',
        'is_read',
        'created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}

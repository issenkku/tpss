<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AcademicYear extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'is_active', 'phase'];

    /**
     * ปฏิทินการศึกษาของปีนี้ (V4 — 1 ปีมีได้หลายปฏิทิน)
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(AcademicCalendar::class);
    }

    /**
     * ปฏิทิน fallback ของปี = ปฏิทินที่ใช้กับ "ทุกหลักสูตร + ทุกชั้นปี" (curriculum/ชั้นปี = null)
     * สร้างให้อัตโนมัติถ้ายังไม่มี · ใช้เมื่อไม่มีปฏิทินเฉพาะกลุ่มที่ match + เป็นที่เก็บเทอมเริ่มต้น
     */
    public function fallbackCalendar(): AcademicCalendar
    {
        return $this->calendars()->firstOrCreate(
            ['curriculum_id' => null, 'year_levels' => null],
            ['name' => 'ทุกหลักสูตร']
        );
    }

    /**
     * เทอม (ภาคการศึกษา) ของปีนี้ — รวมทุกปฏิทิน เรียงตามลำดับ
     * คงชื่อ relation "terms" ไว้เพื่อให้ reader เดิม ($year->terms) ใช้ได้เหมือนเดิม
     */
    public function terms(): HasManyThrough
    {
        return $this->hasManyThrough(Term::class, AcademicCalendar::class)
            ->orderBy('terms.sequence');
    }

    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    /**
     * M11 — ปีที่ใช้เป็นบริบทของ flow อนุมัติ (คิว pending / รายการตีกลับ / dashboard ผู้บริหาร)
     * ใช้ปีที่ active ก่อน ถ้าไม่มีให้ใช้ปีที่อยู่ในช่วงจัดตาราง — รวม logic ไว้จุดเดียว
     * เพื่อให้ทุกหน้าฝั่งผู้บริหารอ้างปีเดียวกัน (กันรายการ pending/rejected โชว์คนละปี)
     */
    public static function currentForApproval(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->orWhere('phase', 'scheduling')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->first();
    }

    public function paRounds(): HasMany
    {
        return $this->hasMany(PaRound::class);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /** เปิดการแจ้งเตือน: ทำเครื่องหมายอ่าน แล้วพาไปหน้าที่เกี่ยวข้อง */
    public function open(Notification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) Auth::id(), 403);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        return redirect()->to($this->targetUrl($notification));
    }

    /** ทำเครื่องหมายอ่านทั้งหมด */
    public function markAllRead(): RedirectResponse
    {
        Notification::forUser((int) Auth::id())->unread()->update(['is_read' => true]);

        return back();
    }

    private function targetUrl(Notification $notification): string
    {
        $role = session('active_role');

        if ($notification->course_offering_id && $notification->courseOffering) {
            if ($role === 'executive') {
                return route('approver.offerings.show', $notification->courseOffering);
            }
            if ($role === 'course_head') {
                return route('maker.course_offerings.show', $notification->courseOffering);
            }
        }

        // ไม่มีปลายทางเจาะจง → กลับหน้า dashboard ตามบทบาท (ไม่ค้างหน้าเดิม)
        return route('dashboard');
    }
}

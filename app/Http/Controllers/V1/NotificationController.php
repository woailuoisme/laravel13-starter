<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Http\Controllers\AppBaseController;
use App\Http\Resources\V1\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @group 用户通知
 */
final class NotificationController extends AppBaseController
{
    /**
     * 获取用户通知列表
     *
     * @authenticated
     *
     * 返回分页后的通知列表。
     *
     * @responseField data.data.id 通知 ID
     * @responseField data.data.title 通知标题
     * @responseField data.data.body 通知内容
     * @responseField data.data.read_at 已读时间
     *
     * @responseFile storage/responses/v1/auth/notifications.json
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        $notifications = $user->notifications()->paginate($request->integer('per_page', 15));
        $msg = __('admin.notifications_fetched');

        return $this->sendResponse(
            NotificationResource::collection($notifications)->response()->getData(true),
            is_string($msg) ? $msg : 'Notifications fetched successfully',
        );
    }

    /**
     * 标记通知为已读
     *
     * @authenticated
     */
    public function markAsRead(string $id): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        $msg = __('admin.notification_marked_as_read');

        return $this->sendSuccess(is_string($msg) ? $msg : 'Notification marked as read');
    }

    /**
     * 标记所有通知为已读
     *
     * @authenticated
     */
    public function markAllAsRead(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        $msg = __('admin.all_notifications_marked_as_read');

        return $this->sendSuccess(is_string($msg) ? $msg : 'All notifications marked as read');
    }

    /**
     * 删除通知
     *
     * @authenticated
     */
    public function destroy(string $id): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->findOrFail($id);
        $notification->delete();

        $msg = __('admin.notification_deleted');

        return $this->sendSuccess(is_string($msg) ? $msg : 'Notification deleted');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Http\Controllers\AppBaseController;
use App\Http\Resources\V1\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromFile;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('用户通知')]
final class NotificationController extends AppBaseController
{
    #[Endpoint('获取用户通知列表', '返回当前认证用户的分页通知列表。')]
    #[Authenticated]
    #[QueryParam('per_page', 'int', '每页条数', required: false, example: 15)]
    #[ResponseField('data.data.id', 'string', '通知 ID')]
    #[ResponseField('data.data.title', 'string', '通知标题')]
    #[ResponseField('data.data.body', 'string', '通知内容')]
    #[ResponseField('data.data.read_at', 'string', '已读时间')]
    #[ResponseFromFile('storage/responses/v1/auth/notifications.json')]
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

    #[Endpoint('标记通知为已读', '将指定 ID 的通知标记为已读状态。')]
    #[Authenticated]
    #[UrlParam('id', 'string', '通知 ID', required: true, example: '9a8b7c6d-5e4f-3a2b-1c0d-ef1234567890')]
    #[Response(['success' => true, 'message' => 'Notification marked as read'], 200)]
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

    #[Endpoint('标记所有通知为已读', '将当前用户所有未读通知一键标记为已读。')]
    #[Authenticated]
    #[Response(['success' => true, 'message' => 'All notifications marked as read'], 200)]
    public function markAllAsRead(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        $msg = __('admin.all_notifications_marked_as_read');

        return $this->sendSuccess(is_string($msg) ? $msg : 'All notifications marked as read');
    }

    #[Endpoint('删除通知', '删除指定 ID 的通知记录。')]
    #[Authenticated]
    #[UrlParam('id', 'string', '通知 ID', required: true, example: '9a8b7c6d-5e4f-3a2b-1c0d-ef1234567890')]
    #[Response(['success' => true, 'message' => 'Notification deleted'], 200)]
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

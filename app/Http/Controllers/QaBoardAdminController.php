<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaBoard\IndexRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaBoard\DestroyAction;
use App\UseCases\QaBoard\IndexAction;
use App\UseCases\QaBoard\ShowAction;
use App\UseCases\QaBoardReply\DestroyAction as ReplyDestroyAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板の管理者モデレーション Controller(`/admin/qa-board`)。
 *
 * ルートの `role:admin` Middleware でロールを絞る。公開停止中の資格を含む全スレッドを閲覧でき、
 * スレッド・回答を削除できる。内容の編集と解決マークの代行はできない。
 * 画面は受講生向けと同じ `qa-thread/` の Blade を共用する(Blade が `admin.*` ルートかで出し分ける)。
 */
class QaBoardAdminController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        return view('qa-thread.index', $action($request->user(), $request->validated()));
    }

    public function show(QaThread $thread, ShowAction $action): View
    {
        return view('qa-thread.show', ['thread' => $action($thread)]);
    }

    public function destroy(QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $action($thread);

        return redirect()
            ->route('admin.qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    public function destroyReply(QaThread $thread, QaReply $reply, ReplyDestroyAction $action): RedirectResponse
    {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $action($reply);

        return redirect()
            ->route('admin.qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }
}

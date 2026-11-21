<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaBoardReply\StoreRequest;
use App\Http\Requests\QaBoardReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaBoardReply\DestroyAction;
use App\UseCases\QaBoardReply\StoreAction;
use App\UseCases\QaBoardReply\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板(受講生 / コーチ向け)の回答 Controller。
 *
 * どの操作も、先にスレッドの `view` Policy を通す(コーチの担当外=403、公開停止中の資格=404)。
 * 回答がそのスレッドのものでなければ 404。
 */
class QaBoardReplyController extends Controller
{
    public function store(QaThread $thread, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $this->authorize('view', $thread);

        $reply = $action($thread, $request->user(), $request->validated());

        return redirect()
            ->to(route('qa-board.show', $thread).'#reply-'.$reply->id)
            ->with('success', '回答を投稿しました。');
    }

    public function edit(QaThread $thread, QaReply $reply): View
    {
        $this->authorizeReply($thread, $reply, 'update');

        return view('qa-thread.reply-edit', ['thread' => $thread, 'reply' => $reply]);
    }

    public function update(QaThread $thread, QaReply $reply, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $this->authorizeReply($thread, $reply, 'update');

        $action($reply, $request->validated());

        return redirect()
            ->to(route('qa-board.show', $thread).'#reply-'.$reply->id)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(QaThread $thread, QaReply $reply, DestroyAction $action): RedirectResponse
    {
        $this->authorizeReply($thread, $reply, 'delete');

        $action($reply);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }

    private function authorizeReply(QaThread $thread, QaReply $reply, string $ability): void
    {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $this->authorize('view', $thread);
        $this->authorize($ability, $reply);
    }
}

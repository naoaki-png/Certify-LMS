<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaBoard\IndexRequest;
use App\Http\Requests\QaBoard\StoreRequest;
use App\Http\Requests\QaBoard\UpdateRequest;
use App\Models\QaThread;
use App\UseCases\QaBoard\CreateAction;
use App\UseCases\QaBoard\DestroyAction;
use App\UseCases\QaBoard\IndexAction;
use App\UseCases\QaBoard\ResolveAction;
use App\UseCases\QaBoard\ShowAction;
use App\UseCases\QaBoard\StoreAction;
use App\UseCases\QaBoard\UnresolveAction;
use App\UseCases\QaBoard\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板(受講生 / コーチ向け)の質問スレッド Controller。
 *
 * 閲覧系は `view` Policy を先に通す(コーチの担当外=403、公開停止中の資格=404)。
 * 編集・削除・解決の切替は、そのうえで投稿者本人だけに許可する。
 */
class QaBoardController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        return view('qa-thread.index', $action($request->user(), $request->validated()));
    }

    public function create(CreateAction $action): View
    {
        $this->authorize('create', QaThread::class);

        return view('qa-thread.create', ['certifications' => $action()]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $thread = $action($request->user(), $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function show(QaThread $thread, ShowAction $action): View
    {
        $this->authorize('view', $thread);

        return view('qa-thread.show', ['thread' => $action($thread)]);
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('view', $thread);
        $this->authorize('update', $thread);

        return view('qa-thread.edit', ['thread' => $thread]);
    }

    public function update(QaThread $thread, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $this->authorize('view', $thread);

        $action($thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    public function destroy(QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('view', $thread);
        // 投稿者本人だけ(回答の有無は下で判定。回答ありは 403 ではなくメッセージで知らせる)
        $this->authorize('update', $thread);

        if ($thread->replies()->exists()) {
            return redirect()
                ->route('qa-board.show', $thread)
                ->with('error', 'このスレッドは削除できません。');
        }

        $action($thread);

        return redirect()
            ->route('qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    public function resolve(QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorize('view', $thread);
        $this->authorize('resolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '解決済みにしました。');
    }

    public function unresolve(QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorize('view', $thread);
        $this->authorize('unresolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '未解決に戻しました。');
    }
}

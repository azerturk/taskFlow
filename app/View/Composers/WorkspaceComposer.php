<?php

namespace App\View\Composers;

use App\Services\WorkspaceHeaderQueryService;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\View\View;

class WorkspaceComposer
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly WorkspaceHeaderQueryService $workspace,
    ) {}

    public function compose(View $view): void
    {
        $view->with($this->workspace->forUser($this->auth->guard()->user()));
    }
}

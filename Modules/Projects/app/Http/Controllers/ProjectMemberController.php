<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Projects\Data\UpdateProjectMemberData;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Exceptions\MemberHasOpenAssignments;
use Modules\Projects\Http\Requests\StoreProjectMemberRequest;
use Modules\Projects\Http\Requests\UpdateProjectMemberRequest;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;

class ProjectMemberController
{
    use AuthorizesRequests;

    public function __construct(private readonly ProjectMemberService $members) {}

    public function index(Project $project): View
    {
        $this->authorize('manageMembers', $project);

        return view('projects::members.index', $this->members->managementPage($project));
    }

    public function store(StoreProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $this->members->addMemberById(
            $project,
            $request->integer('user_id'),
            ProjectMemberRole::from($request->string('member_role')->toString()),
            $request->user(),
        );

        return back()->with('success', 'Project member added.');
    }

    public function destroy(Project $project, string $user): RedirectResponse
    {
        $this->authorize('manageMembers', $project);
        $member = $this->members->memberForProject($project, (int) $user);

        try {
            $this->members->removeMember($project, $member, request()->user());
        } catch (MemberHasOpenAssignments $exception) {
            return back()->withErrors([
                'user_id' => "This member has {$exception->count} open assignment(s). Reassign or unassign them before removal.",
            ]);
        }

        return back()->with('success', 'Project member removed.');
    }

    public function update(UpdateProjectMemberRequest $request, Project $project, string $user): RedirectResponse
    {
        $this->authorize('manageMembers', $project);
        $member = $this->members->memberForProject($project, (int) $user);

        $this->members->updateMemberRole(
            $project,
            $member,
            new UpdateProjectMemberData(ProjectMemberRole::from($request->string('member_role')->toString())),
            $request->user(),
        );

        return back()->with('success', 'Project member role updated.');
    }
}

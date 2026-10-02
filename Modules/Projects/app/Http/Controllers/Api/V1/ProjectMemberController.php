<?php

namespace Modules\Projects\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Projects\Data\UpdateProjectMemberData;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Http\Requests\Api\V1\ProjectMemberIndexRequest;
use Modules\Projects\Http\Requests\StoreProjectMemberRequest;
use Modules\Projects\Http\Requests\UpdateProjectMemberRequest;
use Modules\Projects\Http\Resources\ProjectMemberResource;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;

class ProjectMemberController
{
    use AuthorizesRequests;

    public function __construct(private readonly ProjectMemberService $members) {}

    public function index(ProjectMemberIndexRequest $request, Project $project): AnonymousResourceCollection
    {
        $page = $this->members->paginatedPageFor($request->user(), $project, $request->integer('per_page', 20));
        $project = $page['project'];
        $this->authorize('view', $project);

        return ProjectMemberResource::collection($page['memberships']);
    }

    public function store(StoreProjectMemberRequest $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $membership = $this->members->addMemberById(
            $project,
            $request->integer('user_id'),
            ProjectMemberRole::from($request->string('member_role')->toString()),
            $request->user(),
        );

        return (new ProjectMemberResource($membership))->response()->setStatusCode(201);
    }

    public function destroy(Project $project, string $user): JsonResponse
    {
        $this->authorize('manageMembers', $project);
        $member = $this->members->memberForProject($project, (int) $user);
        $this->members->removeMember($project, $member, request()->user());

        return response()->json(null, 204);
    }

    public function update(UpdateProjectMemberRequest $request, Project $project, string $user): ProjectMemberResource
    {
        $this->authorize('manageMembers', $project);
        $member = $this->members->memberForProject($project, (int) $user);

        $membership = $this->members->updateMemberRole(
            $project,
            $member,
            new UpdateProjectMemberData(ProjectMemberRole::from($request->string('member_role')->toString())),
            $request->user(),
        );

        return new ProjectMemberResource($membership);
    }
}

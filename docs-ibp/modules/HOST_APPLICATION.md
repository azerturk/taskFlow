# Host tətbiq

## Məsuliyyət

Laravel host tətbiqi modullara aid olmayan platforma axınlarına sahibdir:

- session authentication, login/logout və active-account middleware;
- daxili user create/edit/suspend/reactivate və admin password reset;
- authenticated self-service password change;
- Sanctum personal token issuance, `/me` və cari token revoke;
- database notification inbox/read əməliyyatları;
- ortaq exception mapping, rate limiter, route binding və UI layout;
- User repository, Spatie permission/role və framework infrastrukturu.

## Əsas sərhədlər

Public registration yoxdur. Hər account `active|suspended` statusu və dəqiq bir `admin|project_manager|member` rolu daşıyır. Son aktiv admin qorunur.

`EnsureActiveUser` qorunan Web/API request-ləri ilə yanaşı ilkin qorunan route-dan yaranan hər Livewire update request-ində persistent middleware kimi yenidən işləyir. Stale session və ya köhnə Livewire snapshot suspend edilmiş actor üçün 401/fail-closed nəticə verir; record-level policy-lər bu account sərhədini əvəz etmir.

`UserAdministrationService` suspend use case-in üst transaction sahibidir: login access bağlanır, session/PAT-lər ləğv edilir, Tasks vasitəsilə açıq işlər unassign və watcher-lər silinir, Activity tarixçəsi yazılır. Tarixi reporter/assignee əlaqələri silinmir.

`AuthenticationService` credential attempt, throttling və session orchestration sahibidir; Form Request yalnız input shape yoxlayır. Plaintext token yalnız issuance cavabında bir dəfə təqdim olunur.

## Asılılıqlar

Host user lifecycle üçün Projects membership məlumatından, Tasks responsibility/subscription cleanup-dan və Activity recorder-dan istifadə edir. Notification recipient-ləri Projects/Tasks visibility qaydaları ilə seçilir.

## İctimai səth

- Web: `/login`, `/logout`, `/password`, `/admin/users*`, `/notifications*`.
- API: `POST /api/v1/auth/token`, `GET /api/v1/me`, `DELETE /api/v1/auth/token`.

Ətraflı endpoint və security davranışı [`API.md`](../technical/API.md) və [`SECURITY.md`](../technical/SECURITY.md) sənədlərindədir.

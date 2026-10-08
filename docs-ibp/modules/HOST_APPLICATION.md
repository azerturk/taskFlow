# Host tətbiq

## Niyə hər şey ayrıca modul deyil?

Host Laravel tətbiqinin ümumi giriş və platforma hissəsidir. Login, hesabın suspend edilməsi, token verilməsi və notification inbox-u bir layihənin içindəki işin davranışı deyil; tətbiq səviyyəsində istifadə olunur. Ona görə bunlar `app/` daxilində qalır.

Məsələn, suspend edilmiş Muradın əvvəl açıq səhifəsi olsa da növbəti qorunan request-də hesab statusu yenidən yoxlanır. Təkcə login button-unu gizlətmək access-i bağlamaq olmazdı. Host middleware və user lifecycle service-i bu sərhədi qoruyur.

Host «bütün biznes kodunu bura yığmaq» yeri deyil. Task statusu Tasks-da, fayl binary əməliyyatı Media-da qalır. `Auth`, `Users` və `Notifications` adlı əlavə modullar cari quruluşda yoxdur.

Mövzunu ayrıca oxu: [session](../diagrams/flows/session.md), [API token](../diagrams/flows/pat.md), [hesab idarəsi/suspend](../diagrams/flows/account-admin.md), [parol dəyişməsi](../diagrams/flows/password.md), [bildiriş](../diagrams/flows/collaboration.md).

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

`EnsureActiveUser` qorunan Web/API request-ləri ilə yanaşı ilkin qorunan route-dan yaranan hər Livewire update request-ində persistent middleware kimi yenidən işləyir. Köhnə session/snapshot suspended actor-a icazə vermir: API/JSON 401 alır, adi Web request-də logout/session invalidation və login redirect edilir. Record-level policy-lər bu account sərhədini əvəz etmir.

`AdminUserService::suspend()` üst transaction sahibidir: Tasks repository-si ilə açıq işlər lock edilir və unassign olunur, versiyaları artırılır; watcher-lər, PAT və session-lar silinir, account suspended edilir, uyğun bildiriş və Activity yazılır. Tarixi reporter və bağlanmış işlərin assignee əlaqələri silinmir. Reactivate əvvəlki session, token, assignment və watcher-ləri bərpa etmir.

`AuthenticationService` credential yoxlaması və session idarəsinə sahibdir; Form Request yalnız input formasını yoxlayır. Throttling `AppServiceProvider` named limiter-ləri və route middleware-i ilə edilir. Plaintext token yalnız issuance cavabında bir dəfə təqdim olunur.

## Asılılıqlar

Host user lifecycle üçün Projects membership məlumatından, Tasks responsibility/subscription cleanup-dan və Activity recorder-dan istifadə edir. Notification recipient-ləri Projects/Tasks visibility qaydaları ilə seçilir.

## İctimai səth

- Web: `/login`, `/logout`, `/account/password`, `/admin/users*`, `/notifications*`.
- API: `POST /api/v1/auth/token`, `GET /api/v1/me`, `DELETE /api/v1/auth/token`.

Ətraflı endpoint və security davranışı [`API.md`](../technical/API.md) və [`SECURITY.md`](../technical/SECURITY.md) sənədlərindədir.

## Kod üzrə izləmə nümunələri

- Giriş: `routes/web.php -> AuthenticatedSessionController::store -> AuthenticationService::authenticateSession`; uğurda session ID yenilənir.
- Öz parolu: `PasswordController::update -> AdminUserService::changeOwnPassword`; current-password request yoxlamasından sonra digər session-lar və bütün PAT-lər ləğv edilir, controller cari session-u regenerate edir.
- Bildiriş: `NotificationController -> NotificationCenterService::paginate -> NotificationRepositoryInterface`; əlaqəli işlər bir actor-visible batch ilə hazırlanır. Artıq görünməyən iş üçün private title/link deyil, təhlükəsiz «əlçatan deyil» nəticəsi hazırlanır.
- Audit: `SecurityAuditService -> ActivityRecorder`; parol, hash və token audit payload-ına verilmir.

Testlər `tests/Feature/Auth`, `tests/Feature/Admin`, `NotificationCenterTest.php` və `LivewireActiveUserBoundaryTest.php` daxilindədir. Host-un learning modullarından asılılığı yoxdur. [Axın diagramları](../diagrams/README.md), [transaction davranışı](../technical/TRANSACTIONS_AND_FAILURES.md).

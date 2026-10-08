# Nested resource və təhlükəsiz 404

## Bu dərs nə üçündür?

URL-də iki ID olması onların bir-birinə aid olduğunu sübut etmir. `/tasks/42/comments/9` «9 nömrəli comment-i tap» yox, «42 nömrəli task-a aid 9 nömrəli comment-i tap» deməlidir.

Əks halda istifadəçi URL-dəki ID-ni dəyişərək başqa işin comment-inə və ya faylına çata bilər. Cari TaskFlow parent-scoped binding və actor-visible query ilə bu sərhədi qoruyur.

## Əvvəl terminləri anlayaq

- **Resource** göstərilən və ya dəyişdirilən record-dur: task, comment, attachment kimi.
- **Parent / child** URL context-ində əsas record və onun altındakı record-dur; burada task və comment. Bu, mütləq subtask əlaqəsi demək deyil.
- **Nested route** child-i parent altında ünvanlayan yoldur: `/tasks/{task}/comments/{comment}`.
- **Binding** URL parametrini PHP modelinə həll edən mərhələdir.
- **Scope** axtarışın sərhədidir: actor-un görə bildiyi task-lar və ya yalnız bir task-ın comment-ləri kimi.
- **Association** iki record-u bağlayan əlaqə sətridir; `TaskAttachment` task ilə Media record-unu bağlayır.
- **IDOR** user-in göndərdiyi ID əsasında ona aid olmayan obyektə icazəsiz çatmaq riskidir.
- **Existence oracle** cavab fərqlərindən gizli ID-nin mövcudluğunu öyrənmək imkanıdır. Burada məqsəd belə məlumat sızmasını azaltmaqdır.

## Həyat ssenarisi: düzgün fayl, yanlış task

Bu ad və rəqəmlər yalnız izah nümunəsidir. Aysel PAY layihəsinin üzvüdür, database ID-si `42` və `43` olan iki task-ı görə bilir. `TaskAttachment.id=13` isə yalnız task `42`-yə aiddir.

- `/api/v1/tasks/42/media/13/download`: task və attachment uyğun context-dədir; qalan view/storage yoxlamaları da keçərsə private stream alınır.
- `/api/v1/tasks/43/media/13/download`: iki task da görünən olsa belə attachment yanlış parent altındadır; `404` alınır.
- `/api/v1/tasks/43/media/999999/download`: child mövcud deyil; yenə safe `404` alınır.

Buradakı `42` database ID-sidir; display key-dəki `PAY-42` issue nömrəsi ilə eyni olmağa məcbur deyil. URL müqaviləsini görünən issue nömrəsinə baxıb təxmin etmə.

Yanlış-parent request binary-yə çatmır, association silmir və database-də yeni permission yaratmır. Adresin dəyişməsi ownership-i dəyişdirmir.

## Şəkli hansı ardıcıllıqla oxuyaq?

[Authorization axınının SVG-si və sadə izahı](../diagrams/flows/authorization.md) bu request-in giriş sərhədini göstərir. [Media stream dərsi](../diagrams/flows/media-stream.md) isə uğurlu binding-dən sonra private binary-nin necə alındığını izah edir.

```text
Token → aktiv hesab → route ability-si
  → actor üçün görünən parent task
  → yalnız həmin task daxilində child axtarışı
  → əməliyyat policy-si → service → private stream
```

Bu, məsuliyyətləri izləmək üçün sadələşdirilmiş axındır; bütün middleware və Form Request-lərin tam stack trace-i deyil. Query/policy/service yoxlamaları ayrı məqsədlər daşıyır.

## Real binding: parent hazır deyilsə child də qəbul edilmir

[TasksServiceProvider](../../Modules/Tasks/app/Providers/TasksServiceProvider.php) daxilindən:

```php
$attachmentBinding = function (string $value, RoutingRoute $route): TaskAttachment {
    $task = $route->parameter('task');
    if (! $task instanceof Task) {
        throw (new ModelNotFoundException)->setModel(TaskAttachment::class, [$value]);
    }

    return $this->app->make(TaskAttachmentRepositoryInterface::class)->findForTaskOrFail($task, (int) $value);
};
Route::bind('attachment', $attachmentBinding);
Route::bind('media', $attachmentBinding);
```

- `$route->parameter('task')` child-in hansı parent altında axtarılacağını götürür.
- `instanceof Task` həll edilməmiş/raw parametrin etibarlı parent kimi istifadəsini dayandırır.
- Repository-yə həm parent, həm child ID verilir; qlobal attachment axtarışı edilmir.
- Son iki sətir Web-dəki `{attachment}` və API-dəki `{media}` adlarını eyni association binding-inə bağlayır.

[EloquentTaskAttachmentRepository::findForTaskOrFail()](../../Modules/Tasks/app/Repositories/Eloquent/EloquentTaskAttachmentRepository.php) axtarışı belə məhdudlaşdırır:

```php
return TaskAttachment::query()
    ->with(['media.uploader', 'task.project'])
    ->where('task_id', $task->id)
    ->whereKey($id)
    ->firstOrFail();
```

`where('task_id', ...)` parent sərhədidir. `whereKey($id)` isə həmin sərhəddə konkret association-u seçir. `firstOrFail()` uyğun nəticə yoxdursa normal cavab əvəzinə not-found exception yaradır. `with(...)` response/use case üçün əlaqələri əvvəlcədən hazırlayır; permission vermir.

## `{media}` raw Media ID-si deyil

API yolundakı ad çaşdırıcı ola bilər: `{media}` burada **TaskAttachment association ID-si**dir. `TaskAttachment` daxilində ayrıca `media_id`, Media modelində isə metadata və `uuid` var.

[TaskAttachmentResource](../../Modules/Tasks/app/Http/Resources/TaskAttachmentResource.php) `id` olaraq association ID-ni, `media_uuid` olaraq Media UUID-sini qaytarır. `download_url` da task ID-si ilə association ID-sindən qurulur.

Raw Media ID-sini URL-ə qoymaq «Media-dan birbaşa fayl seçmək» deyil: binding həmin rəqəmi yenə association ID-si sayır. ID-lər təsadüfən üst-üstə düşə bilər; buna görə client təhlükəsiz resource URL-ini istifadə etməli, identity-lərin eyniliyini fərz etməməlidir.

## Ability niyə binding-dən əvvəl yoxlanır?

`bootstrap/app.php` middleware priority-də `CheckAbilities` və `CheckForAnyAbility` `SubstituteBindings`-dən əvvəldir.

Yalnız `projects:read` token-i ilə task endpoint-inə gələn actor görünən, gizli və mövcud olmayan task ID-ləri üçün ability mərhələsində `403` alır. Task binding-i həmin request üçün başlamır.

`tasks:read` keçdikdən sonra isə actor-visible parent binding-i işləyir. Gizli və mövcud olmayan task eyni `404 resource_not_found` formasını alır. `RequestBoundarySecurityTest` məhz bu nəticələrin bərabərliyini yoxlayır.

Bu, bütün HTTP cavablarının eyni olması demək deyil: authenticated olmayan request `401`, görünən record üzərində qadağan mutation isə policy-dən `403` ala bilər. Məqsəd düzgün sərhəddə məlumatı gizlətməkdir, bütün problemi `404` etmək deyil.

## Filter üçün safe nəticə həmişə 404 deyil

`GET /api/v1/tasks?project_id=...` bir detail URL-i deyil, siyahı sorğusudur. Cari repository filter-i actor-visible task query-sinə tətbiq edir; gizli project ID-si də, mövcud olmayan project ID-si də uğurlu boş siyahı verə bilər: `200`, `data: []`.

`TaskScopedFilterParityTest` project, assignee, reporter, parent və label ID-ləri üçün bu bərabərliyi yoxlayır. Filter option-ları da yalnız görünən query-dən hazırlanır; gizli layihənin adı dropdown-a sızmamalıdır.

`project_id=invalid` isə ID formatını pozur və `422` olur. «Gizli ID boş nəticə verdi» ilə «input integer deyil» fərqli mərhələlərdir. Input-a qlobal `exists` yoxlaması əlavə edib gizli ID üçün «var», random ID üçün «yoxdur» cavabı yaratmaq bu müqaviləni poza bilər.

## Nəyi etməməliyik?

Child-i qlobal `findOrFail($id)` ilə tapıb yalnız button-u gizlətmək parent sərhədi deyil. Comment üçün də düzgün repository `where('task_id', $task->id)` istifadə edir; label üçün parent project-dir.

Həmçinin caught exception-un `$exception->getMessage()` mətnini response-a çıxarma. Model class-ı, private path və gizli ID-ni ehtiva edə bilər. Cari JSON not-found mapper-i sabit təhlükəsiz mesaj, `resource_not_found` və boş `errors` qaytarır.

Safe `404` timing daxil olmaqla bütün yan kanalların formal sübutu deyil. Cari testlər konkret response müqaviləsini və məlumat sızmamasını yoxlayır; yeni endpoint üçün həmin testləri kor-koranə mövcud saymaq olmaz.

## Özünü yoxla

1. İki record-un mövcud olması nested request üçün yetərlidirmi? **Xeyr; child həmin parent-ə aid olmalıdır.**
2. API `{media}` hansı ID-ni alır? **TaskAttachment association ID-sini.**
3. Eager loading user-ə icazə verirmi? **Xeyr.**
4. Hidden filter ID-si mütləq 404 verməlidirmi? **Xeyr; cari task list müqaviləsi `200` boş nəticədir.**
5. Ability çatmırsa əvvəl task binding-i edilir? **Xeyr; ability denial əvvəl `403` verir.**

## Source, test və davamı

- [API müqaviləsi](../technical/API.md), [Security müqaviləsi](../technical/SECURITY.md), [HTTP status dərsi](http-errors.md)
- [Actor-visible task repository-si](../../Modules/Tasks/app/Repositories/Eloquent/EloquentTaskRepository.php), [comment repository-si](../../Modules/Tasks/app/Repositories/Eloquent/EloquentTaskCommentRepository.php)
- [Media controller-in əlavə parent/policy yoxlamaları](../../Modules/Tasks/app/Http/Controllers/Api/V1/TaskAttachmentController.php)
- [Middleware priority və not-found mapping](../../bootstrap/app.php)
- [Ability-before-binding, wrong-parent, removed-access, soft-delete testləri](../../tests/Feature/Security/RequestBoundarySecurityTest.php)
- [Filter visibility testləri](../../Modules/Tasks/tests/Feature/TaskScopedFilterParityTest.php), [cross-task Media testi](../../Modules/Tasks/tests/Feature/TaskMediaFlowTest.php)

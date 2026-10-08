# Controller, service və repository nə edir?

## Sual

Niyə controller daxilində `Task::query()` yazmırıq?

## Sadə cavab

Controller HTTP-ni tətbiqin use case-inə uyğunlaşdırır. Service bütöv işi və qaydaları idarə edir. Repository məlumatın necə oxunub/yazılacağını bilir. Ayrılıq eyni əməliyyatın Web, API və Livewire-da fərqli biznes qaydası ilə yazılmasının qarşısını alır.

## Cari vəziyyət

Bu qat bölgüsü məhsul kodunda işləyir və architecture testləri ilə qorunur. R1-də HTTP adapteri yoxdur; service → repository sərhədi yenə saxlanılır.

## Terminləri sadələşdirək

**Adapter** bir dünyadakı məlumatı digərinin qəbul etdiyi formaya çevirən giriş hissəsidir; controller HTTP-ni application əməliyyatına çevirir. **Use case** istifadəçinin məqsədini tam yerinə yetirən işdir, məsələn “task statusunu dəyiş”. **Persistence** məlumatın DB-də saxlanması/oxunmasıdır.

Restoranda sifarişi alan işçi mətbəxin hər addımını özü etmir. Sifarişin hazırlanması bir məsuliyyət, ərzağın anbardan alınması başqa məsuliyyətdir. Bu bənzətmədə controller sifarişi qəbul edir, service bütöv əməliyyatı idarə edir, repository DB ilə işləyir.

## Həyat ssenarisi: Fidan task-ı review-a keçirir

1. Əvvəl task `in_progress`, version=3 vəziyyətindədir.
2. HTTP request status və expected version göndərir; FormRequest input-u yoxlayır.
3. Controller Fidanın status dəyişmək icazəsini policy ilə yoxlayır.
4. Controller validated input-dan DTO qurub bir status service çağırır.
5. Service təzə state-i repository-dən lock ilə alır, transition/version qaydasını yoxlayır.
6. Service status, tarix, rank, audit və notification nəticələrini bir transaction-da tamamlayır.
7. Repository hazırlanmış nəticəni verir; controller onu Resource ilə JSON-a çevirir.

```text
Əvvəl: HTTP input və cari task
Kim nə edir: controller uyğunlaşdırır → service qərar/orchestration → repository persistence
Sonra: vahid use-case nəticəsi → explicit response
```

## Real kod

API controller-də status action-ı:

```php
$this->authorize('changeStatus', $task);
$task = $this->statuses->change(
    $task,
    ChangeTaskStatusData::fromArray($request->validated()),
    $request->user(),
);

return new TaskResource($task);
```

Service-də isə persistence üçün repository çağırılır:

```php
$task = $this->tasks->lockForRankMutation($task);
if ($task->version !== $data->expectedVersion) {
    throw new TaskVersionConflict('This task was changed by another request.');
}
```

Bu service transaction, transition, timestamp, rank, Activity və notification nəticəsinə sahibdir. Repository lock və eager loading edir; HTTP response yaratmır.

## Axın

```text
Route → FormRequest → controller authorization → readonly DTO
→ TaskStatusService → TaskRepositoryInterface → Eloquent implementation
→ prepared Task → Resource → JSON
```

## Yanlış yanaşmalar

- Controller-də həm repository, həm service çağırıb nəticəni özün compose etmək.
- Service-i bir query-ni gizlədən mənasız pass-through class-a çevirmək.
- Repository daxilində notification və workflow qərarı vermək.
- Service-ə “mən əvvəl bütün relation-ları load etmişəm” şərti qoymaq.

Bir action bir application boundary çağırır; bu, service daxilində lazım olan collaborator-ların qadağan olunması demək deyil.

## Kodun vacib sətirlərini açaq

- `$this->authorize(...)`: access qərarıdır, DB yazısı deyil.
- `ChangeTaskStatusData::fromArray(...)`: validated input məqsədli input obyektinə çevrilir.
- `$this->statuses->change(...)`: action-ın bir application sərhədidir.
- `lockForRankMutation(...)`: service SQL detalını özü yazmır, repository-dən məqsədli imkan istəyir.
- `TaskVersionConflict`: service gözlənilən biznes/state failure-ını bildirir.
- `new TaskResource($task)`: response sahələri ayrıca seçilir.

Service bir neçə collaborator çağıra bilər: Activity, notification, rank və membership. “Bir action bir boundary” service-in yalnız bir sətir və bir dependency-dən ibarət olması demək deyil.

## Niyə bir qayda bir service-dədir?

Web controller, API controller və Livewire eyni biznes əməliyyatını edə bilər. Qayda controller-lərə paylansa biri child yoxlamasını, digəri version yoxlamasını unuda bilər. Bir use case bu qaydaları toplu saxlayır; adapter yalnız öz giriş/çıxış formasına sahib olur.

## Konkret yanlış düzəliş

Junior API controller-də `$task->status = ...; $task->save();` yazır. Status dəyişə bilər, amma transition, rank append, version, timestamp, Activity və notification flow-u yan keçilir. Düzgün fix bu parçaları controller-ə əlavə etmək deyil, mövcud `TaskStatusService`-dən istifadə etməkdir.

Digər risk controller-in Resource üçün relation-ları sonradan yükləməsidir. Prepared nəticəni query/use-case service təmin etməlidir; HTTP qatında gizli query axını yaratmamalıyıq.

## Özünü yoxla

1. SQL query-nin yeri haradır? **Repository implementasiyasında.**
2. Transaction və biznes qaydasının sahibi kimdir? **Bütöv mutation use case-in service-i.**
3. Controller authorization etdikdən sonra direct `save()` edə bilərmi? **Bu arxitekturada xeyr.**

[Request qatlarının izahı](../diagrams/system/request-layers.md) və [status flow-u](../diagrams/flows/status.md) bu bölgünü praktik göstərir.

## Kod və yoxlama

- [API adapteri](../../Modules/Tasks/app/Http/Controllers/Api/V1/TaskController.php)
- [Status use case](../../Modules/Tasks/app/Services/TaskStatusService.php)
- [Eloquent persistence](../../Modules/Tasks/app/Repositories/Eloquent/EloquentTaskRepository.php)
- [Architecture testləri](../../tests/Architecture/ControllerBoundaryGuardTest.php)
- [Qat müqaviləsi](../technical/ARCHITECTURE.md)

# Lazy loading, eager loading və N+1

## Sual

Blade-də `$task->project->name` yazmaq niyə bəzən əlavə query yaradır?

## Sadə cavab

Relation əvvəl yüklənməyibsə Eloquent ona ilk müraciətdə ayrıca query edə bilər: lazy loading. Eager loading lazım olan relation-ları query qatında əvvəlcədən hazırlayır. List-də hər row ayrıca query edirsə N+1 problemi yaranır.

## Cari vəziyyət

TaskFlow-da repository relation loading sahibidir. Controller/Blade/Resource əlavə query etmir. Detail və list üçün fərqli prepared nəticələr var; bütün relation-ları həmişə yükləmək də məqsəd deyil.

## Terminləri sadələşdirək

**Relation** modelin başqa məlumatla əlaqəsidir, məsələn task-ın project-i. **Query** DB-yə oxu/yazı sorğusudur. **Lazy loading** relation-i ona müraciət anında ayrıca oxumaqdır. **Eager loading** lazım olan əlaqələri əvvəlcədən hazırlamaqdır.

Mağazada 20 məhsulun qiymətini bir-bir kassaya gedib soruşmaqla siyahını bir dəfə toplu almağı düşün. Hər sətirdə ayrıca gediş olarsa yol sayı artır. N+1 adı əsas list query-si üstəgəl hər record üçün əlavə query problemini sadələşdirərək göstərir.

## Həyat ssenarisi: 20 task-ın project adı

1. Əvvəl repository 20 task qaytarır, amma project relation-u hazırlanmır.
2. Blade hər task üçün project adını göstərməyə çalışır.
3. Relation lazy oxunarsa hər row yeni DB query-si başlada bilər.
4. Developer query-ni controller-ə köçürmək əvəzinə repository nəticəsini düzəldir.
5. Məqsədli `with('project')` ilə relation-lar batch hazırlanır.
6. Presentation artıq hazır nəticəni oxuyur; yeni query qurmağa ehtiyac yoxdur.

```text
Yanlış: əsas list → row 1 query → row 2 query → ...
Düzgün sərhəd: repository list + lazım relation batch-ləri → hazır rows → presentation
```

## Real kod

Resource üçün repository nəticəsi:

```php
return Task::query()
    ->with(['project', 'creator', 'assignee', 'labels', 'parent', 'subtasks'])
    ->whereKey($task->id)
    ->firstOrFail();
```

Resource optional relation-i yalnız yüklənibsə göstərir:

```php
'labels' => $this->whenLoaded('labels', fn (): array => $this->labels->map(fn ($label): array => ['id' => $label->id, 'name' => $label->name, 'slug' => $label->slug, 'color' => $label->color])->values()->all()),
```

## Axın

```text
Query service → repository → məqsədli eager loading → prepared result → Resource/Blade
Yanlış: list → hər row presentation-da relation query → N əlavə query
```

Məsələn, 20 task-ın project adını lazy oxumaq 20 əlavə query yarada bilər. Eager loading relation-ları batch-ləyir; “hər şey bir SQL query olmalıdır” tələbi deyil.

## Yanlış düzəliş

Controller-də `$task->load()` əlavə etmək problemi yanlış qatda həll edir. Lazım olan relation repository nəticəsinə daxil edilməli, query service onu presentation-a verməlidir. `whenLoaded()` da lazy relation-i yükləməz: olmayan relation response-dan buraxıla bilər.

Query count testləri real page composition-u yoxlayır. Architecture testinin keçməsi hər performans probleminin olmaması demək deyil; data ölçüsü və batch davranışı ayrıca nəzərə alınır.

## Kodun vacib sətirlərini açaq

- `Task::query()`: persistence query repository-də başlayır.
- `with([...])`: həmin use case-in response-u üçün lazım relation-lar seçilir.
- `whereKey($task->id)`: konkret record scope-u daraldılır.
- `firstOrFail()`: prepared Task nəticəsi və ya missing failure alınır.
- `whenLoaded('labels', ...)`: relation varsa serialize edir; olmayan relation-i özü yükləmir.
- `map(...)`: artıq yüklənmiş collection-dan response sahələri çıxarılır.

`with()` “bütün məlumat bir SQL join olacaq” zəmanəti deyil. Məqsəd nəzarətli batch query-lər və presentation-da gizli query olmamasıdır.

## Niyə hər relation-ı hər zaman yükləmirik?

Task list-də bütün comment body-ləri və binary metadata-sı lazım olmaya bilər. Lazımsız eager loading də yaddaş/query həcmini artıra bilər. Repository purpose-specific nəticə hazırlayır: list, API Resource və Web detail fərqli ehtiyacdır.

## Konkret failure nümunəsi

Junior Resource-da `$this->labels()->get()` yazır. Bu relation method query-si presentation zamanı icra edilir və hər task üçün təkrarlana bilər. Düzgün yol labels relation-unu repository-də hazırlamaq və Resource-da hazır collection-u göstərməkdir.

Architecture guard bunu struktur qaydası kimi yoxlayır; query-budget testi real səhifədə çoxalan query sayını da izləyir. Heç biri “məlumat həcmindən asılı olmayaraq həmişə sürətlidir” zəmanəti deyil.

## Özünü yoxla

1. `whenLoaded()` relation-i yükləyirmi? **Xeyr.**
2. Controller-də `load()` əlavə etmək qəbul edilmiş fix-dirmi? **Xeyr; repository/query nəticəsi düzəldilir.**
3. Eager loading bütün relation-ları almaq deməkdirmi? **Xeyr; lazım olanlar məqsədli seçilir.**

[Request qatları](../diagrams/system/request-layers.md) və [Dashboard read flow-u](../diagrams/flows/dashboard.md) prepared nəticənin sərhədini göstərir.

## Kod və yoxlama

- [Resource və detail eager loading](../../Modules/Tasks/app/Repositories/Eloquent/EloquentTaskRepository.php)
- [Task Resource](../../Modules/Tasks/app/Http/Resources/TaskResource.php)
- [Query boundary testləri](../../tests/Feature/QueryBoundaryTest.php)
- [Dashboard metrics testləri](../../Modules/Dashboard/tests/Feature/DashboardMetricsTest.php)
- [Presentation sərhədi](../technical/ARCHITECTURE.md)

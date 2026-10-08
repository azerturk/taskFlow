# Dashboard modulu

## Sadə dillə: bu ekran məlumatı haradan alır?

Dashboard yeni task və ya layihə cədvəli yaratmır. Mövcud layihələri, işləri və tarixçəni oxuyub «mənim işlərim», «izlədiklərim», «gecikənlər» kimi görünüşlər hazırlayır. **Aggregate** çoxlu sətirdən hesablanan say/xülasədir; **queue** isə burada göstərilən iş siyahısıdır, background job queue-su deyil.

Məsələn, Murad iki layihənin üzvüdürsə onun sayğacları həmin görünən məlumatlardan hesablanır. Başqasının private layihəsi bir siyahıda gizlənib sayğacda görünməməlidir. Eyni visibility scope buna xidmət edir; bu, bütün fərqli query-lərin tarix şərtinin də eyni olduğu zəmanəti deyil.

Dashboard read-only modul olsa da səhifədə QuickTaskCreate var. O, ayrıca Tasks create use case-inə müraciət edir; «dashboard özü öz cədvəlinə task yazır» deyil.

Geniş nümunə, real query-lər və sayğac/siyahı fərqi: [Dashboard dərsi](../diagrams/flows/dashboard.md).

## Məsuliyyət

Dashboard read-only application moduludur. Ayrı domain state yaratmır; Projects, Tasks və Activity mənbələrindən actor-visible aggregate, queue və presentation-ready nəticə hazırlayır.

## Göstəricilər

- active/completed/archived project sayları;
- total tasks, overdue, completed today;
- fixed workflow, work type və project status distribution-ları, zero dəyərlər daxil;
- My Assigned, Reported, Watched və Overdue queue-ları;
- recent Activity;
- active/mutable project-lər üçün `QuickTaskCreate` input-u.

Bütün count və queue eyni visibility scope istifadə edir. Gizli layihə filter və aggregate vasitəsilə mövcudluğunu açıqlamır. Enum distribution açarları canonical sırada həmişə mövcuddur.

Overdue **summary sayğacı** `due_at < today()->toDateString()` istifadə edir; bugünkü deadline burada overdue deyil. Ancaq `EloquentTaskRepository::overdueQuery()` overdue queue-su və paginated API siyahısı üçün `due_at < now()` istifadə edir. Date-only deadline bu günə aiddirsə siyahıya düşə bilər, sayğaca isə düşməz. Ümumi task filter-i `whereDate(..., '<', today())` istifadə edir; queue prioritetləşdirməsi isə `now()` ilədir. Bu cari uyğunsuzluq sənədləşdirilir, bu işdə düzəldilmir. [Query-lərin izahlı müqayisəsi](../diagrams/flows/dashboard.md).

Completed-today hazırda `done` olan və `completed_at` tətbiqin lokal bugünkü gün intervalına düşən işləri sayır. `draft` ayrıca top-level say deyil, amma project-status distribution daxilində sıfır olsa da açarı qalır.

## Səth və asılılıqlar

Web dashboard Blade və yalnız `QuickTaskCreate` Livewire komponentini təqdim edir. API summary, my-tasks, reported, watched və overdue olmaqla 5 endpoint verir. Modul Projects/Tasks/Activity query sərhədlərini çağırır, Eloquent query-ni controller və view-a buraxmır.

Aggregate parity, role/visibility isolation, overdue/completed-today semantics, watched queue, zero enum keys, pagination və query budget Pest testləri; responsive əsas görünüş Playwright journey-ləri ilə qorunur.

## Sadə oxu axını

`DashboardService::page()` üç mənbəni bir səhifə nəticəsinə yığır: Projects repository-si layihə saylarını, Tasks repository-si summary/queue-ları, `ActivityQueryService` isə son 8 görünən audit qeydini verir. Blade ayrıca query açmır. API-də `summary()` readonly `DashboardSummaryData` qaytarır, Resource onu explicit JSON sahələrinə çevirir.

Dashboard read-only modul olsa da onun səhifəsindəki `QuickTaskCreate` ayrıca mutation adapteridir: işi Dashboard cədvəlinə deyil, mövcud Tasks create use case-i ilə yaradır. Dashboard-un öz domain cədvəli yoxdur. LearningInsights adlı projection modulu ilə oxşar «oxu» sözünü paylaşması onların əlaqəli olması demək deyil; aralarında dependency yoxdur.

Kod: [DashboardService](../../Modules/Dashboard/app/Services/DashboardService.php), [QuickTaskCreate](../../Modules/Dashboard/app/Livewire/QuickTaskCreate.php); testlər: [Dashboard Feature](../../Modules/Dashboard/tests/Feature). [Diagram xəritəsi](../diagrams/README.md).

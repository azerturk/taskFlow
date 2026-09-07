# Dashboard modulu

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

## Səth və asılılıqlar

Web dashboard Blade və yalnız `QuickTaskCreate` Livewire komponentini təqdim edir. API summary, my-tasks, reported, watched və overdue olmaqla 5 endpoint verir. Modul Projects/Tasks/Activity query sərhədlərini çağırır, Eloquent query-ni controller və view-a buraxmır.

Aggregate parity, role/visibility isolation, overdue/completed-today semantics, watched queue, zero enum keys, pagination və query budget Pest testləri; responsive əsas görünüş Playwright journey-ləri ilə qorunur.

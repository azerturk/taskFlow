# Projects modulu

## Məsuliyyət

Projects layihə aggregate-i, lifecycle, owner və project membership-in sahibidir. Modul actor-visible project query-ləri, manager qərarları və layihə context-inə görə iştirak imkanını təqdim edir.

## Model və qaydalar

- `Project`: name, slug, immutable-after-first-issue key, description, status, owner, tarixlər və `next_issue_number`.
- `ProjectMember`: project/user cütü, `manager|member` rolu və joined time.
- Yeni layihə `draft`, creator owner/manager olur.
- Yalnız `active` layihə məhsul mutasiyası qəbul edir.
- `completed` read-only, manager tərəfindən `active` edilə bilər; `archived` terminaldır.
- Owner membership-dən silinə və manager rolundan endirilə bilməz.
- Açıq assignment-lı üzvün çıxarılması 409-dur; uğurlu çıxarılmada həmin layihənin watcher-ləri təmizlənir.

## Application sərhədləri

`ProjectService` create/update/lifecycle və tam səhifə nəticələrini, `ProjectMemberService` membership use case-lərini orkestrasiya edir. Controller-lər repository çağırmır. Repository actor scope, pagination, eager loading, key/slug persistence və project sequence lock-larını idarə edir.

## Asılılıqlar

Projects Activity yazır; member removal və lifecycle integrity üçün Tasks query/cleanup imkanlarından istifadə edir. Tasks layihə membership və manage/participate qərarları üçün Projects service-lərini çağırır.

## Səth və testlər

Web project list/detail/create/edit/lifecycle/member səhifələrini, API isə 9 project və membership əməliyyatını təqdim edir. Policy matrix, lifecycle, immutable key, owner protection, scope, query budget, Activity və SQLite/MySQL constraint-lər Pest ilə qorunur.

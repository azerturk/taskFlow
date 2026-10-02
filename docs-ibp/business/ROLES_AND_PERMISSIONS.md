# Rollar və səlahiyyətlər

## İki səviyyəli rol modeli

Qlobal rol təşkilat səviyyəli geniş capability-ni, layihə rolu isə konkret layihədə authority-ni müəyyən edir. Spatie permission policy-yə daxil olmağa imkan verir; son qərarı account statusu, layihə üzvlüyü, project statusu və record əlaqəsi ilə Policy/Gate verir.

Məsələn, qlobal `member` konkret layihədə `manager` ola bilər. Buna görə geniş task permission-ları təkbaşına başqasının işini dəyişmək icazəsi sayılmır.

## Qlobal rollar

| Rol | Əsas imkan |
|---|---|
| `admin` | Daxili istifadəçi/rol idarəsi, bütün layihələrə görünürlük və bütün məhsul capability-ləri |
| `project_manager` | Layihə yarada bilər; konkret layihədə digər səlahiyyətlər layihə üzvlüyü və rolundan gəlir |
| `member` | Layihələrdə iştirak edir; qlobal olaraq yeni layihə yarada bilmir |

## Layihə rolları

| Rol | Əsas imkan |
|---|---|
| `manager` | Layihə detail/lifecycle/member/label idarəsi, bütün layihə işlərinin mutasiyası, reorder və manager-only əməliyyatlar |
| `member` | Bütün layihə işlərinə baxış, iş report etmə, özünə assignment, təyin olunduğu işi progress etmə, şərh/media/watcher əməkdaşlığı |

Admin bütün layihələr üçün idarəçi kontekstinə malikdir. Layihə owner-i həmişə manager sayılır.

## Əməliyyat matrisi

| Əməliyyat | Admin | Project manager | Project member | Şərt |
|---|---:|---:|---:|---|
| İstifadəçi yarat/suspend/reactivate/reset | Bəli | Xeyr | Xeyr | Son aktiv admin qorunur |
| Layihə yarat | Bəli | Bəli | Xeyr | Aktiv hesab |
| Layihəyə bax | Bəli | Üzvdürsə | Üzvdürsə | Removed/suspended actor dərhal itirir |
| Layihə detail/lifecycle/member idarəsi | Bəli | Layihədə managerdirsə | Layihədə managerdirsə | Archived terminal, completed read-only |
| Work item yarat | Bəli | Active layihədə üzvdürsə | Active layihədə üzvdürsə | İlkin status/rank serverindir |
| Work item detail edit | Bəli | Layihə manager-i | Reporter və yalnız backlog/todo | Project active olmalıdır |
| Work item soft-delete | Bəli | Layihə manager-i | Xeyr | Project active olmalıdır |
| Başqasına assign/unassign | Bəli | Layihə manager-i | Xeyr | Target aktiv layihə üzvü |
| Özünə assign | Bəli | Bəli | Bəli | Aktiv layihə üzvü |
| Status dəyiş | Bəli | İcazəli bütün keçidlər | Yalnız assignee üçün adi keçid | `expected_version` tələb olunur |
| Açıq reorder | Bəli | Layihə manager-i | Xeyr | Eyni project/status qonşuları |
| Label CRUD | Bəli | Layihə manager-i | Xeyr | Active project |
| Mövcud label sync | Bəli | Bəli | İcazəli reporter editor | Eyni layihə label-ı |
| Watch/unwatch self | Bəli | Bəli | Bəli | Aktiv layihə üzvü |
| Başqasının watcher statusu | Bəli | Layihə manager-i | Xeyr | Target aktiv layihə üzvü |
| Şərh yaz | Bəli | Bəli | Bəli | Görünən iş, active project |
| Şərh sil | Bəli | İstənilən | Yalnız öz şərhi | Active project |
| Media upload | Bəli | Bəli | Bəli | Görünən iş, active project |
| Media sil | Bəli | İstənilən | Yalnız öz upload-u | Active project |
| Activity/Dashboard | Bütün görünən məlumat | Üzv scope-u | Üzv scope-u | Eyni visibility qaydası |

## API ability-ləri

- `projects:read`, `projects:write`
- `tasks:read`, `tasks:write`
- `comments:write`
- `activity:read`
- `dashboard:read`

Ability yalnız route ailəsini daraldır. Token-də ability olsa belə Spatie permission və record policy keçməzsə əməliyyat rədd edilir.

## Hesab və layihə vəziyyətinin təsiri

- `suspended` actor Web/API giriş edə bilməz və recipient/assignee/watcher ola bilməz.
- `draft` layihə work item mutasiyası qəbul etmir.
- `completed` və `archived` layihələr read-only-dir.
- Üzvlük silinməsi görünürlük və watcher access-i dərhal ləğv edir, tarixçəni silmir.


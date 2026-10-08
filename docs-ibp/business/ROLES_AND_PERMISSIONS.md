# Rollar və səlahiyyətlər

## Əvvəl bir nümunə

Muradın qlobal rolu `member` ola bilər, amma PAY layihəsində ona `manager` rolu verilə bilər. Onda Murad PAY layihəsini idarə edə bilər; bu, onun bütün təşkilat üzrə admin olması demək deyil. Başqa bir layihədə üzv deyilsə həmin layihəyə görünürlük də əldə etmir.

**Görmək** və **dəyişmək** eyni icazə deyil. Layihə üzvü başqasına assign olunmuş işi görə bilər, amma assignee və ya manager olmadan onun statusunu dəyişə bilməz. Watcher olmaq da əlavə edit hüququ vermir.

Aşağıdakı cədvəldə «Bəli»ni həmişə son sütundakı şərtlə birlikdə oxu. Məsələn, layihə manager-i olmaq archived layihəni adi edit ilə dəyişməyə icazə vermir.

Sözlərin ayrı-ayrı izahı: [role, permission, policy, ability](../extended/roles-permissions-policies-abilities.md). Request zamanı bu yoxlamaların ardıcıllığı: [authorization axını](../diagrams/flows/authorization.md).

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
| Layihə detail/member idarəsi | Bəli | Layihədə managerdirsə | Layihədə managerdirsə | Yalnız draft və active |
| Layihə lifecycle keçidi | Bəli | Layihədə managerdirsə | Layihədə managerdirsə | Yalnız status cədvəlindəki keçidlər; archived terminal |
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
- `draft` layihədə detail və üzv hazırlığı mümkündür; iş/label/şərh/watcher/media mutasiyası mümkün deyil.
- `completed` layihədə detail/member/iş dəyişmir, amma manager `active` və ya `archived` keçidini edə bilər. `archived` heç bir lifecycle keçidi qəbul etmir.
- Üzvlük silinməsi görünürlük və watcher access-i dərhal ləğv edir, tarixçəni silmir.

Buradakı «Project manager» sütunu qlobal rolu göstərir; həmin istifadəçinin konkret layihədə `manager` olması ayrıca şərtdir. Səlahiyyətin hesablanması üçün kod: `Modules/Projects/app/Services/ProjectMemberService.php::canParticipate/canManage`, `Modules/Projects/app/Policies/ProjectPolicy.php` və `Modules/Tasks/app/Policies/TaskPolicy.php`.

Sadə müqayisə: [rol, permission, policy və ability](../extended/roles-permissions-policies-abilities.md).


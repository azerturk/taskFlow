# Test növləri nəyi sübut edir?

## Sual

Architecture test keçibsə feature və integration testinə niyə ehtiyac var?

## Sadə cavab

Hər test qatı fərqli sualı cavablandırır. Düzgün dependency, düzgün biznes nəticəsi və real browser davranışı eyni şey deyil. Bir qatın PASS olması digərlərinin əvəzi deyil.

## Cari vəziyyət

TaskFlow əsasən Pest, kritik browser journey-lərində Playwright istifadə edir. Qovluq adı təkbaşına testin daxili quruluşunu müəyyən etmir: repository/constraint integration yoxlamalarının bir hissəsi feature suite-dədir. R1 real commit ssenariləri ayrıca integration qovluğundadır.

| Qat | Sual | Real nümunə |
|---|---|---|
| Unit | Xarici asılılığı olmayan qayda düzgün hesablanır? | Activity sanitizer, task domain qaydaları |
| Repository/integration | Real DB constraint və persistence necədir? | R1 unique/rollback, project issue allocation |
| Feature | Girişdən nəticəyə authorization/state düzgündür? | API, Web, notification və Livewire ssenariləri |
| Architecture | Qadağan qat/modul asılılığı əlavə edilib? | Controller persistence və R1 public səth guard-ları |
| E2E | Real browser axını işləyir? | Login, board drag/drop, media preview |

## Terminləri sadələşdirək

**Unit** kiçik qaydanın, **integration** bir neçə real komponentin birlikdə, **feature** isə application girişindən nəticəyə ssenarinin yoxlanmasıdır. **End-to-end/E2E** real browser istifadəçisi kimi axını keçir. **Mock/fake** testdə real collaborator-u nəzarətli əvəz etməyə kömək edir; bununla sübut sərhədi də dəyişir.

Avtomobil misalında lampanı tək yoxlamaq, naqillə birlikdə yoxlamaq və bütün maşını yolda sürmək ayrı yoxlamalardır. Sükan testinin keçməsi faranın işlədiyini sübut etməz. Daha böyük test həmişə daha yaxşı test deyil; hansı failure-ı tutmaq istədiyin önəmlidir.

## Həyat ssenarisi: status bug-ını hansı test tutmalıdır?

1. Əvvəl transition cədvəli bir enum statusundan digərinə keçidi qadağan edir.
2. Pure rule səhvdirsə unit test onu tez tutmalıdır.
3. Version/write/constraint problemi varsa real DB integration yoxlaması lazımdır.
4. Assignee olmayan üzv API-dən status dəyişirsə feature authorization testi lazımdır.
5. Controller direct persistence əlavə edibsə architecture testi qadağan sərhədi tutmalıdır.
6. Board drag/drop browser-də yanlış request göndərirsə focused E2E journey kömək edir.
7. Bu testlər eyni bug-ın fərqli səbəb/sərhədlərini araşdırır; birini keçmək qalanlarını silmir.

```text
Qayda → persistence → adapter/access → struktur → browser
Hər qat öz sualına uyğun fixture və assertion istifadə edir
```

## Real test nümunəsi

R1 integration testi listener-in həqiqətən işlədiyini real provider wiring ilə yoxlayır:

```php
$entry = app(LearningEntryService::class)->publish(new PublishLearningEntryData('Public API and Events'));
$projection = LearningEntryInsight::query()->sole();
```

Sonra event identity/payload, projection parity və transaction səviyyəsi assert edilir. Bu ssenaridə event fake edilmir.

## Axın

```text
Xarici asılılığı olmayan qayda → unit
Persistence/failure semantics → integration
HTTP/Livewire access və nəticə → feature
Kod sərhədi → architecture
Browser və JavaScript journey → E2E
```

## Yanlış sübut

`Event::fake()` ilə “event dispatch olundu” assertion-u listener-in düzgün yazdığını sübut etmir. Mock repository ilə test də real UNIQUE constraint-i yoxlamır. Digər tərəfdən hər edge case-i browser testinə daşımaq suite-i ağırlaşdırır; edge case əsasən Pest-də qalır.

Test sayını keyfiyyət ölçüsü kimi tək istifadə etmə. Vacib olan failure nöqtəsi, authorization matrisi və yanlış nəticəni testin həqiqətən tutmasıdır. Skipped/focused test release sübutu deyil.

## Real kod parçasını açaq

- `app(LearningEntryService::class)`: real container wiring ilə service alınır.
- `new PublishLearningEntryData(...)`: real input qaydası işləyir.
- `publish(...)`: Catalog write və dispatch axını çağırılır.
- `LearningEntryInsight::query()->sole()`: test real DB-də tək projection gözləyir; production service-in query qatı deyil, test assertion hazırlığıdır.
- Sonrakı transaction/event assertion-ları: row-un təkcə olması deyil, nə vaxt yarandığı da yoxlanılır.

Test source-da DB query görmək production controller-in DB query etməsinə icazə deyil. Testin məsuliyyəti nəticəni müşahidə etməkdir.

## Niyə mock və real test birlikdədir?

Mock ilə nadir failure nöqtəsini dəqiq yaratmaq rahatdır: “ikinci write exception atsın”. Amma real UNIQUE constraint və provider/listener wiring üçün həqiqi komponentləri də yoxlamalıyıq. R1 həm feature ssenariləri, həm real commit integration testləri saxlayır.

## Konkret yanlış sübut nümunəsi

Junior yalnız `Event::assertDispatched(...)` yazır və “Insights hazırdır” deyir. Listener heç qeydiyyatdan keçməsə belə fake dispatch assertion-u keçə bilər. Real wiring/projection testi bunu ayrıca yoxlamalıdır.

Başqa nümunə: E2E-də login keçir, amma hidden project filter-i metadata sızdırır. Onlarla access edge case-i browser-ə daşımaq əvəzinə Pest-də təhlükəsizlik matrisi qurulur.

## Özünü yoxla

1. Mock test real DB constraint-i yoxlayırmı? **Mock sərhədində xeyr.**
2. Architecture PASS bütün permission bug-larını istisna edirmi? **Xeyr.**
3. Browser testi Pest-i əvəz edirmi? **Xeyr; onu tamamlayır.**

[Test izolyasiya izahı](../diagrams/flows/test-isolation.md) profilləri, [authorization flow-u](../diagrams/flows/authorization.md) isə feature ssenarilərinin səbəbini göstərir.

## Kod və yoxlama

- [Sanitizer-in unit testi](../../Modules/Activity/tests/Unit/ActivitySanitizerTest.php)
- [Authorization matrix](../../Modules/Tasks/tests/Feature/AuthorizationMatrixTest.php)
- [Real R1 integration](../../Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php)
- [R1 architecture testləri](../../tests/Architecture/R1LearningBoundaryTest.php)
- [Browser ssenariləri](../../tests/e2e)
- [Canonical test strategiyası](../technical/TESTING.md)

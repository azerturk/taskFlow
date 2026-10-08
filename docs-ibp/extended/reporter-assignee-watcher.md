# Reporter, assignee və watcher

## Sual

Bir task-da niyə bir assignee, amma çoxlu watcher var?

## Sadə cavab

Reporter işi bildirən şəxsdir. Assignee icraya cavabdeh şəxsdir. Watcher isə dəyişiklikləri izləyən şəxsdir. İşi izləmək onun sahibi və ya editor-u olmaq demək deyil.

## Cari vəziyyət

Hər task bir reporter, sıfır/bir assignee və çoxlu watcher daşıyır. Reporter DB-də `creator_id` ilə saxlanılır. Layihə üzvləri layihənin bütün task-larını görə bilir: visibility assignee/watcher üzərindən verilmir.

## Terminləri sadələşdirək

**Reporter** problemi və ya işi bildirən şəxsdir. **Assignee** hazırda işin icrasına cavabdeh şəxsdir. **Watcher** dəyişiklikləri izləmək üçün subscription saxlayan şəxsdir. **Visibility** məlumatı görə bilmə, **authority** isə onu dəyişmə səlahiyyətidir.

Bir problemi müştəri nümayəndəsi bildirə, developer düzəldə, QA isə prosesi izləyə bilər. Üçü eyni insan olmaq məcburiyyətində deyil. Watcher siyahısını “işi birlikdə icra edən assignee-lər” kimi oxumaq yanlışdır.

## Həyat ssenarisi: bug komanda daxilində gedir

1. Əvvəl PAY layihəsinin üzvləri Əhməd, Fidan və Rauf həmin layihənin işlərini görə bilirlər.
2. Əhməd bug yaradır: reporter Əhməddir və avtomatik watcher olur.
3. Manager Fidan-a assign edir: tək assignee Fidan olur, o da watcher olur.
4. Rauf QA kimi watch edir; yeni edit/status səlahiyyəti qazanmır.
5. Fidan status dəyişir; uyğun watcher-lər notification ala bilər, Fidan öz action bildirişini almır.
6. Sonra Rauf unwatch edir; layihə üzvlüyü qaldığı üçün bug-a baxışı itirmir.

```text
Əvvəl: layihə üzvlüyü → görünürlük
Əməliyyat: report / assign / watch → ayrı əlaqələr
Sonra: məsuliyyət və notification dəyişə bilər, browse qaydası ayrıca qalır
```

## Real kod

Task yaradılarkən actor reporter olur və avtomatik watch edir:

```php
'project_id' => $project->id, 'creator_id' => $actor->id, 'assignee_id' => $data->assigneeId, 'type' => $data->type, 'parent_id' => $parent?->id,
```

```php
$this->watchers->ensureWatching($task, $actor);
if ($data->assigneeId && $data->assigneeId !== $actor->id) {
    $this->watchers->ensureWatching($task, $assignee);
}
```

Notification recipient-lərindən action actor-u çıxarılır:

```php
->reject(fn (User $watcher): bool => $watcher->id === $actor->id)
```

## Axın nümunəsi

```text
Əhməd bug report edir → reporter=Əhməd
Manager Fidan-a assign edir → assignee=Fidan, Fidan watch edir
Rauf watch edir → watcher=Rauf, əlavə edit/status səlahiyyəti qazanmır
Fidan status dəyişir → uyğun watcher-lər xəbər alır, Fidan öz action bildirişini almır
```

## Access və cleanup

Adi üzv özünü watch/unwatch edir; manager başqa aktiv layihə üzvünü idarə edə bilər. Üzvlük silinməsi həmin layihə watcher-lərini təmizləyir. Suspend açıq assignment-ları unassign və watcher-ləri silir, amma reporter və tarixi Activity əlaqələrini silmir.

Watcher olmaq completed/archived layihədə mutation icazəsi vermir. Arbitrary watcher filter ümumi task list-də yoxdur; “mənim izlədiklərim” Dashboard queue-sudur.

## Kodun vacib sətirlərini açaq

- `creator_id => $actor->id`: reporter client-dən seçilmir; yaradan actor-dur.
- `assignee_id => $data->assigneeId`: sıfır və ya bir target olur; membership qaydası service-də yoxlanılır.
- `ensureWatching($task, $actor)`: reporter üçün subscription yaranır.
- Assignee actor-dan fərqlidirsə ayrıca `ensureWatching(...)`: ikinci maraqlı tərəf əlavə edilir.
- `reject(... actor id ...)`: recipient seçimində əməliyyatı edən şəxs çıxarılır.

`ensureWatching` təkrar çağırılsa duplicate subscription yaratmamalıdır. Bu, birdən çox assignee yaratmaq deyil; watcher cütlüyü ayrıca saxlanılır.

## Niyə bir assignee saxlanılır?

Cari məhsul məsuliyyəti bir nəfərə bağlayır. Başqa maraqlı tərəfləri watcher kimi əlavə edir. Bu model “komanda işi yoxdur” demək deyil; ownership ilə izləməni aydın ayırır. Çoxlu assignee bu layihənin cari scope-u deyil.

## Konkret xəta nümunəsi

Junior “watcher-ə notification gedirsə status da dəyişə bilər” deyə policy-yə watcher yoxlaması əlavə edir. Bu authority-ni subscription ilə qarışdırır. Status üçün manager və ya uyğun assignee qaydası qalmalıdır.

Üzvlük çıxarılıbsa köhnə watcher row-u access-i davam etdirməməlidir. Membership removal watcher cleanup edir; notification link-i açılarkən də access yenidən qiymətləndirilir.

## Özünü yoxla

1. Assignee olmayan layihə üzvü task-ı görə bilərmi? **Bəli.**
2. Unwatch task-a baxışı ləğv edirmi? **Üzvlük qaldıqda xeyr.**
3. Reporter hansı DB sahəsindədir? **`creator_id`.**

[Assignment izahı](../diagrams/flows/assignment.md) və [əməkdaşlıq/bildiriş axını](../diagrams/flows/collaboration.md) əlaqələri ayrı göstərir.

## Kod və yoxlama

- [Create flow](../../Modules/Tasks/app/Services/TaskService.php)
- [Watcher mutation](../../Modules/Tasks/app/Services/TaskWatcherService.php)
- [Recipient seçimi](../../Modules/Tasks/app/Services/TaskWatcherNotificationService.php)
- [Watcher/notification testləri](../../Modules/Tasks/tests/Feature/TaskWatcherNotificationTest.php)
- [Assignment testləri](../../Modules/Tasks/tests/Feature/TaskAssignmentRulesTest.php)
- [Biznes qaydaları](../business/BUSINESS_RULES.md)

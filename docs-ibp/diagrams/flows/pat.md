# API token necə verilir və ləğv edilir?

[Diagram atlasına qayıt](../README.md) · [Brauzer session-u](session.md)

## Bu axın nə üçündür?

Brauzerdə form ilə daxil olmaqdan başqa TaskFlow-un REST API-sinə müraciət etmək də mümkündür.
API client hər qorunan request-də kim olduğunu göstərməlidir.
Bunun üçün Personal Access Token — qısaca PAT istifadə edilir.

Bu sənəddə Murad adlı developer yalnız izah ssenarisidir.
Heç bir real email, parol və token təqdim edilmir.
Token şəxsi giriş açarıdır: onu reporta, screenshot-a, Git commit-inə və log-a qoymaq olmaz.

## Əvvəl sözləri anlayaq

- **REST API**: proqramın HTTP endpoint-lərinə request göndərib cavab almasıdır.
- **Client**: request-i göndərən proqramdır; məsələn API aləti və ya başqa kod.
- **Sanctum**: bu layihədə PAT ilə API authentication üçün istifadə olunan Laravel paketidir.
- **PAT**: konkret istifadəçiyə aid giriş token-idir.
- **Bearer**: token-in HTTP authorization header-i ilə daşınma üsuludur. Token-i bilən tərəf onu təqdim edə bilər.
- **Plaintext token**: client-in istifadə edəcəyi orijinal token dəyəridir.
- **Hash**: token-in DB-də saxlanan geri çevrilməyən yoxlama təqdimatıdır; plaintext token-in özü deyil.
- **Ability**: token üçün açılan əməliyyat dairəsidir. İstifadəçinin project membership-i və Policy qərarı ilə eyni deyil.
- **Revoke**: token-i artıq istifadə oluna bilməyəcək şəkildə ləğv etməkdir.
- **DTO**: input-dan qəbul edilmiş məlumatı məqsədli field-lərlə service-ə daşıyan obyektdir.

Token-i bir qapının açarı kimi düşün.
Ability açarın hansı növ qapılara uyğun olduğunu daraldır.
Amma konkret project-ə daxil olmaq haqqını yenə istifadəçinin icazələri müəyyən edir.

## Ssenari

Murad API ilə özünə görünən task-ları oxumaq istəyir.
O, token endpoint-inə giriş məlumatlarını və cihaz adını göndərir.
Uğurda aldığı token-i client-də təhlükəsiz saxlayır.
İşi bitəndə həmin token ilə revoke endpoint-inə müraciət edir.

Muradın iki ayrı token-i varsa, birini revoke etmək digərini avtomatik ləğv etmir.
Hesab suspend edilərsə və ya parol dəyişdirilərsə bütün PAT-lərin ləğvi ayrıca daha geniş axındır.

## Şəkli addım-addım oxuyaq

![Personal Access Token axını](pat.svg)

Burada soldakı client, ortadakı middleware/adapter və service, sağdakı repository/DB ardıcıl əməkdaşlıq edir.
Oxlar HTTP request, daxili çağırış və cavabın istiqamətini göstərir.

1. **API client** `POST /api/v1/auth/token` göndərir.
2. **Middleware / adapter** giriş limitini və input shape-ni yoxlayır. Endpoint dəqiqədə beş cəhdlə məhdudlaşdırılır.
3. **AuthenticationService** email ilə hesabı repository-dən tapır.
4. **Aktivlik və parol** yoxlanır. Hesab yoxdur, suspended-dır və ya parol uyğun deyil — bunlar client üçün eyni ümumi credential xətasına çevrilir.
5. **Repository / DB** Sanctum token-i yaradır. Verilən ability-lər token-ə bağlanır.
6. **Audit və cavab** təhlükəsiz token-issued hadisəsini qeyd edir. HTTP 201 cavabında plaintext token bir dəfə qaytarılır.
7. **Sonrakı API request** `auth:sanctum` ilə istifadəçini tanıyır; active-user, lazım olan ability və record Policy-si ayrıca tətbiq edilir.
8. **DELETE /api/v1/auth/token** request-i authenticate edən token-i ləğv edir və 204 qaytarır. Həmin token-in sonrakı istifadəsi qorunan endpoint-də 401 alır.

201 “yaradıldı”, 204 isə “əməliyyat uğurludur, response body yoxdur” mənasını verir.
Şəkildə response oxu “token DB-dən istənilən vaxt plaintext oxunur” mənasına gəlmir.

## Real kod: token yaratmazdan əvvəlki qərar

[AuthenticationService](../../../app/Services/AuthenticationService.php)-dən:

~~~php
$user = $this->users->findByEmail($data->email);

if (! $user || ! $user->isActive() || ! Hash::check($data->password, $user->password)) {
    return null;
}

$token = $this->tokens->issue($user, $data->deviceName, $data->abilityValues());
~~~

- `findByEmail` hesabı tapmaq işini repository-yə verir; controller query yazmır.
- `! $user` hesabın tapılmadığını bildirir.
- `! $user->isActive()` suspended hesabı dayandırır.
- `Hash::check` gələn parolu saxlanmış hash ilə yoxlayır; DB parolunu deşifrə etmir.
- `||` şərtlərdən hər hansı biri doğrudursa uğursuz nəticə qaytarır.
- `return null` controller üçün credential uğursuzluğu siqnalıdır. Controller hansı şərtin keçmədiyini açıqlamır.
- `issue` repository sərhədidir: Sanctum yaradılmasını persistence tərəfi edir.
- `deviceName` token-in tanınan adıdır; “bu client daha çox səlahiyyətlidir” qərarı deyil.
- `abilityValues()` DTO-dakı qəbul edilmiş ability-ləri token üçün dəyərlərə çevirir.

Repository-də əsas yaradılma sətiri budur:

~~~php
return $user->createToken($deviceName, $abilities);
~~~

Bu sətir Sanctum-un yaradılma mexanizmini istifadə edir.
Service sonra təhlükəsiz audit qeyd edir və yaradılma nəticəsini controller-ə qaytarır.
Bu axın üçün kodda olmayan vahid “token+audit həmişə bir DB transaction-dadır” təminatı uydurmaq olmaz.

## DB-də və client-də nəticə

`personal_access_tokens` cədvəlində istifadəçiyə bağlı token qeydi və onun ability-ləri saxlanır.
Token-in DB təqdimatı hash-dir.
Plaintext token yalnız yaradılma response-unda client-ə verilir; sonrakı `me` response-u onu geri qaytarmır.

`ApiTokenIssued` auditində user ID, cihaz adı və ability-lər kimi icazəli məlumat var.
Orijinal token və parol audit payload-ına salınmır.
Revoke zamanı repository təqdim edilən token-in həmin istifadəçiyə aid olduğunu yoxlayıb qeydi silir.
Uğurlu silinmə üçün `ApiTokenRevoked` audit qeyd olunur.

## Xəta və alternativlər

| Hadisə | Nəticə |
|---|---|
| Input və ability dəyərləri qəbul edilmir | 422 validation; token yaradılmır. |
| Yanlış və ya suspended credential | Ümumi 422 credential cavabı; hesabın mövcudluğu açıqlanmır. |
| Token yaratma cəhdlərinin limiti dolur | 429. |
| Qorunan endpoint-ə token olmadan və ya revoked token ilə müraciət | 401. |
| Token düzgündür, tələb edilən ability yoxdur | Ability qorunan route-da 403. |
| Ability var, istifadəçi konkret task-a baxa bilmir | Record scope/Policy qərarı yenə tətbiq olunur; token bunu bypass etmir. |

`GET /api/v1/me` və cari token revoke route-u ümumi auth/active-user/API throttle qrupundadır.
Onların route-unda ayrıca task ability-si tələb edilmir.
Module endpoint-lərindəki ability qaydasını bütün API endpoint-lərinə kor-koranə eyniləşdirmə.

## Tez-tez qarışan suallar

**Token user-in roludur?** Xeyr. Rol hesaba, ability isə konkret token-in istifadə dairəsinə aiddir.

**Read ability-si ilə mənə görünməyən project-i görə bilərəm?** Xeyr. Ability əlavə daraltmadır; Policy-ni əvəz etmir.

**Web logout token-i ləğv edir?** Cari Web logout axını bunu etmir.

**Token-i itirsəm DB-dən yenidən plaintext götürə bilərəm?** Xeyr. Təhlükəsiz yanaşma köhnəni ləğv edib ehtiyac varsa yeni token yaratmaqdır.

**REST API ilə module public API eynidir?** Xeyr. REST HTTP girişidir. R1 modul public API-si isə eyni PHP tətbiqində modulların müqaviləli əlaqəsidir; [lab izahına](../labs/public-feed.md) bax.

## Özünü yoxla

1. Düzgün parol suspended hesab üçün token yaradır? **Xeyr.**
2. Revoke bir token-i, yoxsa bütün token-ləri silir? **Bu endpoint cari token-i silir.**
3. Plaintext token niyə reporta yazılmır? **Onu bilən tərəf token kimi təqdim edə bilər.**
4. 401 ilə 403 fərqi nədir? **401-də giriş etibarlı deyil; 403-də tanınan giriş üçün tələb edilən icazə çatmır.**

## Mənbə və testlər

- [API route-lar](../../../routes/api.php), [AuthenticationController](../../../app/Http/Controllers/Api/V1/AuthenticationController.php).
- [AuthenticationService](../../../app/Services/AuthenticationService.php), [PAT repository](../../../app/Repositories/Eloquent/EloquentPersonalAccessTokenRepository.php).
- [CreatePersonalAccessTokenData](../../../app/Data/CreatePersonalAccessTokenData.php): normallaşdırma və ability təqdimatı.
- [CredentialTokenApiTest](../../../tests/Feature/Auth/CredentialTokenApiTest.php), [AuthAdminSecurityAuditTest](../../../tests/Feature/Auth/AuthAdminSecurityAuditTest.php).
- [API müqaviləsi](../../technical/API.md), [Security](../../technical/SECURITY.md).


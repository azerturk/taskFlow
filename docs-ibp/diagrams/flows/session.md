# Brauzerdə giriş və çıxış necə işləyir?

[Diagram atlasına qayıt](../README.md) · [Token ilə API girişi](pat.md)

## Bu axın nə üçündür?

Leyla hər səhifəni açanda yenidən email və parol yazmamalıdır.
TaskFlow bir dəfə uğurlu girişdən sonra onun brauzerini tanımaq üçün session istifadə edir.
“Çıxış” düyməsi isə həmin brauzerdəki girişi etibarsız etməlidir.

Bu sənəddəki Leyla adı yalnız izah ssenarisidir; real hesab və giriş məlumatı göstərilmir.
TaskFlow-da açıq qeydiyyat yoxdur. Hesabı əvvəl administrator yaradır.

## Əvvəl bu sözləri anlayaq

- **Authentication**: “Sən kimsən?” sualının yoxlanmasıdır. Email, parol və hesabın aktivliyi girişdə yoxlanır.
- **Session**: serverin brauzerə aid saxladığı giriş vəziyyətidir. Brauzer session cookie-si ilə həmin vəziyyətə bağlanır.
- **Session ID**: həmin vəziyyəti tapmaq üçün identifikatordur. İstifadəçinin parolu deyil.
- **Guard**: Laravel-də hansı giriş üsulunun istifadə edildiyini müəyyən edən mexanizmdir. Burada guard adı `web`-dir.
- **Middleware**: request controller-ə çatmazdan əvvəl araya girən yoxlamadır.
- **Throttle / rate limit**: müəyyən müddətdə icazə verilən request sayını məhdudlaşdırır.
- **CSRF token**: başqa saytdan istifadəçinin adından form göndərilməsinə qarşı Web müdafiəsidir. Session ID və API token ilə eyni şey deyil.
- **Regenerate**: mövcud giriş üçün yeni session ID yaratmaqdır.
- **Invalidate**: cari session vəziyyətini etibarsız etməkdir.

Cookie-ni “brauzerin serverə göstərdiyi giriş bileti”, session-u isə “serverdə həmin biletə bağlı qeyd” kimi düşün.
Bu bənzətmə texniki detalları əvəz etmir, amma parolun hər request-də göndərilmədiyini anlamağa kömək edir.

## Ssenari

Leyla login formasını açır, məlumatlarını yazır və daxil olur.
Sonra Dashboard və project səhifələrinə keçir.
İşi bitəndə “Çıxış” düyməsini basır.
Başqa brauzerdəki API token-in də silindiyini düşünməməlidir: Web logout yalnız bu giriş üsuluna aiddir.

## Şəkli addım-addım oxuyaq

![Session login və logout](session.svg)

Şəkildəki dörd şaquli sütun işi edən tərəflərdir.
Soldan sağa gedən ox bir tərəfin digərinə müraciətini göstərir; şaquli kəsik xətlər queue deyil, zaman boyunca həmin tərəfi izləmək üçündür.

1. **Brauzer** `POST /login` göndərir. Email, parol və remember seçimi formdan gəlir.
2. **Web middleware** giriş cəhdinin limitini yoxlayır. Limit dolubsa credential yoxlamasına keçmədən 429 nəticəsi yarana bilər.
3. **Form Request və controller** input-u yoxlayır, yalnız qəbul edilən məlumatdan DTO hazırlayır. DTO request məlumatını service-ə daşıyan kiçik readonly obyektdir.
4. **AuthenticationService** `web` guard ilə giriş cəhdi edir. Şərtlərə `status=active` də daxildir.
5. **Auth / session** uğurlu girişin istifadəçisini saxlayır. Service session ID-ni yeniləyir.
6. **Brauzerə cavab** redirect olur. Sonrakı request-lərdə session istifadəçini tanımağa kömək edir.
7. **Logout hissəsi** ayrıca müraciətdir: guard çıxış edir, session invalid olur, CSRF token yenilənir.

Throttle-ın sahibi service deyil.
Limit [AppServiceProvider](../../../app/Providers/AppServiceProvider.php)-də adlandırılır və [route](../../../routes/web.php)-da middleware kimi tətbiq edilir.
Cari login limiti normallaşdırılmış email və IP üzrə dəqiqədə beş cəhddir.

## Real kodun kiçik hissəsi

[AuthenticationService](../../../app/Services/AuthenticationService.php)-də uğurlu girişə aparan əsas yoxlama belədir:

~~~php
if (! Auth::guard('web')->attempt([
    'email' => $data->email,
    'password' => $data->password,
    'status' => AccountStatus::Active->value,
], $data->remember)) {
    throw new InvalidCredentials('The credentials are invalid.');
}
~~~

- `guard('web')` Web session giriş üsulunu seçir; Sanctum PAT yaratmır.
- `attempt(...)` verilən credential-lərlə uyğun hesabı yoxlayır.
- `email` hansı hesabın axtarıldığını bildirir.
- `password` daxil edilən paroldur; DB-də plaintext parol saxlanması demək deyil.
- `status` suspended hesabın düzgün parolla da daxil olmasına imkan vermir.
- `$data->remember` remember seçimini guard-a ötürür.
- `!` nəticəni tərsinə çevirir: cəhd uğursuzdursa exception atılır.
- `InvalidCredentials` məqsədli tətbiq xətasıdır. Controller onu təhlükəsiz validation mesajına çevirir.

Bundan sonra service alınan istifadəçinin həqiqətən `User` olduğunu yoxlayır və `$session->regenerate()` çağırır.
Beləliklə login-dən əvvəlki session ID-si ilə login-dən sonrakı ID eyni saxlanmır.

Çıxışın bütün əsas kodu isə üç addımdır:

~~~php
Auth::guard('web')->logout();
$session->invalidate();
$session->regenerateToken();
~~~

Birinci sətir guard-dakı girişi bitirir.
İkinci sətir cari session-u etibarsız edir.
Üçüncü sətir CSRF token-i yeniləyir; bütün API token-ləri silmir.

## DB-də və istifadəçidə nə dəyişir?

Giriş `users` cədvəlində yeni istifadəçi yaratmır və onun parolunu dəyişmir.
Session sürücüsünə uyğun giriş vəziyyəti yaranır; tətbiqin adi database session sürücüsü ilə bu vəziyyət session saxlanmasına bağlıdır.
Testdə array session istifadə edilməsi ayrıca test mühitidir, məhsulun session davranışını dəyişən yeni giriş qaydası deyil.

Leyla uğurda nəzərdə tutulan səhifəyə, o yoxdursa əsas səhifəyə yönləndirilir.
Logout-dan sonra login səhifəsinə qayıdır.
Əvvəldən açıq qalan qorunan səhifəni yenidən request etdikdə artıq həmin session ilə daxil olmuş sayılmır.

## Axın harada dayana bilər?

| Vəziyyət | Nəticə və səbəb |
|---|---|
| Input forması düzgün deyil | Validation xətası; credential attempt-ə keçilmir. |
| Email/parol uyğun deyil | Eyni ümumi giriş xətası; hansı hissənin səhv olduğu açıqlanmır. |
| Hesab suspended-dır | Düzgün parol olsa da giriş qəbul edilmir. |
| Cəhd limiti dolub | 429; bu, “parol səhvdir” qərarı deyil. |
| Girişdən sonra admin hesabı suspend edir | Session-lar təmizlənir, active-user yoxlaması növbəti qorunan girişdə də müdafiə verir. |

Web validation normalda formaya error ilə geri yönləndirmədir.
Bunu bütün login səhvlərinin brauzerdə xam JSON 422 göstərməsi kimi başa düşmə.

## Tez-tez qarışan suallar

**Login olmaq bütün task-ları görmək deməkdir?** Xeyr. Bu yalnız kimliyi müəyyən edir. Görünürlük və əməliyyat icazəsi [authorization](authorization.md) mövzusudur.

**Session ID-ni yeniləmək parolu dəyişir?** Xeyr. Giriş vəziyyətinin identifikatoru yenilənir.

**Logout bütün cihazları və bütün PAT-ləri çıxarır?** Xeyr. Bu method cari Web session axınıdır. Suspend və password change daha geniş revoke qaydalarına malikdir.

**Giriş limitini service içinə qoymalıyıq?** Cari kodda qoyulmayıb. Middleware request tezliyinə, service credential/session use case-inə sahibdir.

## Özünü yoxla

1. Aktiv olmayan hesab düzgün parolla girə bilər? **Xeyr, attempt şərtində active status var.**
2. 429 nəyi göstərir? **Request sayı limiti keçilib; credential nəticəsini göstərmir.**
3. Logout-dakı `regenerateToken()` PAT yaradır? **Xeyr, CSRF token yenilənir.**
4. Session ilə tanınan istifadəçi başqa project-in task-ına avtomatik baxa bilər? **Xeyr, record icazəsi ayrıca yoxlanır.**

## Kodu və testləri harada izləməli?

- [Web route-lar](../../../routes/web.php): giriş/çıxış middleware-ləri.
- [Session controller](../../../app/Http/Controllers/Auth/AuthenticatedSessionController.php): DTO, təhlükəsiz error və redirect.
- [AuthenticationService](../../../app/Services/AuthenticationService.php): attempt və session əməliyyatları.
- [SessionAuthenticationTest](../../../tests/Feature/Auth/SessionAuthenticationTest.php): uğurlu/uğursuz giriş və logout nümunələri.
- [Host application sənədi](../../modules/HOST_APPLICATION.md) və [təhlükəsizlik müqaviləsi](../../technical/SECURITY.md): qəbul edilən qaydalar.

Test linki onun bu sənəd hazırlanarkən yenidən işlədildiyini bildirmir.


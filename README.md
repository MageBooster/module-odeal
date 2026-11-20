<!-- mb-top -->
# MageBooster Ödeal

Ödeal virtual POS (pay by link, 3D Secure) for Magento 2, by MageBooster

![Magento 2.4.4–2.4.9](https://img.shields.io/badge/Magento-2.4.4%E2%80%932.4.9-orange) ![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777bb4) ![Luma | Hyvä](https://img.shields.io/badge/themes-Luma%20%7C%20Hyv%C3%A4-0a7) ![Licence: Commercial](https://img.shields.io/badge/licence-Commercial-red)

```bash
composer require magebooster/module-odeal
bin/magento setup:upgrade
```

Paid packages are served from the MageBooster Composer repository (`https://repo.magebooster.com`); your serial number is the password. See the guide for the full steps.

- Guide: [English](docs/HOWTO.en.md) · [Türkçe](docs/HOWTO.tr.md)
- Changes: [CHANGELOG.md](CHANGELOG.md)
- Buy a licence: https://magebooster.com
- Support: info@magebooster.com

---

<!-- /mb-top -->
# MageBooster_Odeal – Ödeal for Magento 2

Ödeal payment method for Magento Open Source / Adobe Commerce **2.4.9** and Mage-OS (PHP 8.0+ – 8.5).
Made by [MageBooster](https://magebooster.com). Türkçe açıklama aşağıda.

## Install

```bash
composer require magebooster/module-odeal
bin/magento module:enable MageBooster_Odeal
bin/magento setup:upgrade
bin/magento setup:di:compile && bin/magento setup:static-content:deploy   # production mode
```

Manual: copy this folder to `app/code/MageBooster/Odeal` and run the same commands.

## Settings

Stores › Configuration › Sales › Payment Methods › **Ödeal · MageBooster**:
enabled, title, checkout text, test mode, credentials (secrets are stored encrypted), test and live API address,
currencies charged directly, allowed countries, sort order, debug log (`var/log/magebooster_mbodeal.log`, card data and secrets are masked).

Credentials: Client ID, client secret, grant type, scope, and the test and live API addresses (they are not published in the docs).

## How it works

After the order is placed the module gets a bearer token (`/token`), creates a 3D Secure payment page
(`/vpos/pay-by-link`, `externalId` = order number) and sends the customer there. On the way back (`/mbodeal/payment/callback`)
the module asks Ödeal for the result (`/vpos/check-status`); a cron job repeats this every 10 minutes for customers who
closed the browser.

- The order is created as **pending payment** and the order email waits until the payment is confirmed.
- Paid: an invoice is created automatically and the order moves to **processing**.
- Failed or cancelled: the order is cancelled and the customer's cart is restored.
- Unpaid orders are cancelled by Magento's own *Pending Payment Order Lifetime* (Sales › Sales › Orders Cron Settings).
- Refunds: Credit memo → "Refund" calls the refund path set in the admin (default `/vpos/refund`; confirm with Ödeal).
- Return URLs carry the order number signed with your store's crypt key, so they can't be guessed.

**PCI DSS:** card details are entered on Ödeal's page only; your store never sees them.

## Currencies and TRY

Charged directly: TRY (add others in the admin if Ödeal enables them for you). When the order currency is something else, the base grand total is converted to TRY
with the rate saved in Stores › Currency Rates. The TRY amount and rate are shown under the payment method at checkout,
stored on the payment, and shown on the order page, so the customer is never charged a different amount than shown.
For daily Central Bank of Türkiye (TCMB) rates use **MageBooster Currency Rates** (paid); without it, any Magento rate source works.

## Checkout compatibility

- Uses the standard Magento payment renderer (`checkout_index_index` layout + a `Magento_Checkout/js/view/payment/default`
  method renderer), so it works in the native checkout and in one-step checkouts that render standard payment methods,
  including **MageBooster Checkout**.
- It doesn't depend on the native multi-step step navigator.
- **Hyvä:** the Knockout renderer is for Luma-based checkouts (also a Hyvä theme with the Luma checkout fallback).
  On a Hyvä theme with **MageBooster Checkout**, install `magebooster/module-odeal-hyva` for the method panel; the
  redirect itself works without it, because the module sets `payment/<code>/order_place_redirect_url` to
  `mbodeal/redirect/start` and MageBooster Checkout sends the customer there after placing. Hyvä Checkout
  (Magewire) is not supported yet.
- Payment always happens on the Ödeal page (hosted payment link), so there is no in-checkout card form.

## Not tested yet

- Not run against Ödeal yet (no test credentials).
- API addresses, the token answer fields (`accessToken`/`access_token`/`token` are accepted) and the refund endpoint are not in the
  public docs, so they are admin settings.
- Each new token invalidates the previous one. The module uses one token per request; if many payments start at the
  same second, one of them may have to be retried.

## Licence & copying

Commercial licence, see `LICENSE.txt`. Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved.

- One serial number = one production installation (one store domain). Staging and development copies of that same store are allowed.
- Each serial number and each download is tied to the customer who bought it. Luma and Hyvä editions are separate products with separate serial numbers.
- You may modify the code for your own licensed store. Keep the copyright and licence notices.
- Not allowed: copying to other stores, redistributing, reselling, sublicensing, publishing in public repositories, marketplaces or forums, removing licence checks or these notices, sharing serial numbers.
- In case of unauthorised copying or use, the serial number is revoked without refund, updates and support stop, and WebWorks 101 OÜ may claim damages and take legal action. Estonian law applies; the court is Harju County Court.

Ödeal is a trademark of its owner; this module is not made or endorsed by them.

---

# MageBooster_Odeal – Magento 2 için Ödeal

Magento Open Source / Adobe Commerce **2.4.9** ve Mage-OS için Ödeal ödeme yöntemi (PHP 8.0+ – 8.5).

## Kurulum

```bash
composer require magebooster/module-odeal
bin/magento module:enable MageBooster_Odeal
bin/magento setup:upgrade
bin/magento setup:di:compile && bin/magento setup:static-content:deploy tr_TR en_US
```

## Ayarlar

Mağazalar › Yapılandırma › Satış › Ödeme Yöntemleri › **Ödeal · MageBooster**: etkin, başlık, ödeme adımı metni, test modu,
API bilgileri (gizli alanlar şifreli saklanır), test ve canlı API adresi, doğrudan tahsil edilen para birimleri, ülkeler,
sıralama, hata ayıklama kaydı (`var/log/magebooster_mbodeal.log`; kart bilgisi ve gizli anahtarlar maskelenir).

Gerekli bilgiler: Client ID, client secret, grant type, scope ve test/canlı API adresleri (dokümanda yayınlanmıyor).

## Nasıl çalışır

Sipariş verildikten sonra modül bearer token alır (`/token`), 3D Secure ödeme sayfası oluşturur
(`/vpos/pay-by-link`, `externalId` = sipariş no) ve müşteriyi oraya yönlendirir. Dönüşte (`/mbodeal/payment/callback`)
sonucu Ödeal'e sorar (`/vpos/check-status`); tarayıcıyı kapatan müşteriler için cron her 10 dakikada tekrar kontrol eder.

- Sipariş **pending payment** durumunda açılır, sipariş e-postası ödeme onaylanınca gider.
- Ödeme başarılıysa fatura otomatik kesilir, sipariş **processing** olur.
- Başarısız veya iptalse sipariş iptal edilir, müşterinin sepeti geri yüklenir.
- Ödenmeyen siparişleri Magento'nun *Pending Payment Order Lifetime* ayarı iptal eder.
- İade: Credit memo → "Refund" admin'deki iade yolunu çağırır (varsayılan `/vpos/refund`; Ödeal ile teyit edin).
- Dönüş adresleri mağazanın şifreleme anahtarıyla imzalı sipariş numarası taşır, tahmin edilemez.

**PCI DSS:** kart bilgileri yalnızca Ödeal sayfasında girilir; mağazanız bu bilgileri hiç görmez.

## Para birimi ve TL

Doğrudan tahsil: TRY (add others in the admin if Ödeal enables them for you). Sipariş başka para birimindeyse ana para birimindeki toplam, Mağazalar › Döviz Kurları'nda
kayıtlı kurla TL'ye çevrilir. TL tutarı ve kur ödeme adımında yöntemin altında gösterilir, ödemeye kaydedilir ve sipariş
sayfasında görünür; müşteriden gösterilenden farklı tutar çekilmez. Günlük TCMB kurları için **MageBooster Currency Rates**
(ücretli) kullanılabilir; yoksa Magento'nun herhangi bir kur kaynağı çalışır.

## Checkout uyumluluğu

- Standart Magento ödeme renderer'ı kullanılır; Magento'nun kendi checkout'unda ve standart ödeme yöntemlerini gösteren tek
  adımlı checkout'larda (**MageBooster Checkout** dahil) çalışır. Çok adımlı step navigator'a bağımlı değildir.
- **Hyvä:** Knockout renderer Luma tabanlı checkout'lar içindir (Luma checkout'a düşen Hyvä teması dahil). Hyvä
  temasında **MageBooster Checkout** ile yöntem paneli için `magebooster/module-odeal-hyva` kurun; yönlendirme onsuz da
  çalışır, çünkü modül `payment/<code>/order_place_redirect_url` değerini `mbodeal/redirect/start` yapar ve
  MageBooster Checkout sipariş sonrası müşteriyi oraya gönderir. Hyvä Checkout (Magewire) henüz desteklenmiyor.
- Ödeme her zaman Ödeal sayfasında (barındırılan ödeme linki) yapılır; ödeme adımında kart formu yoktur.

## Henüz test edilmeyenler

- Henüz Ödeal'e karşı çalıştırılmadı (test bilgileri yok).
- API adresleri, token yanıt alanları (`accessToken`/`access_token`/`token` kabul edilir) ve iade servisi açık dokümanda yok;
  bu yüzden admin ayarı olarak bırakıldı.
- Her yeni token öncekini geçersiz kılar. Modül istek başına tek token kullanır; aynı saniyede çok sayıda ödeme başlarsa
  biri tekrar denenmek zorunda kalabilir.

## Lisans ve kopyalama

Ticari lisans, bkz. `LICENSE.txt`. Telif hakkı (c) 2026 WebWorks 101 OÜ (MageBooster). Tüm hakları saklıdır.

- Bir seri numarası = bir canlı kurulum (bir mağaza alan adı). Aynı mağazanın test (staging) ve geliştirme kopyaları serbesttir.
- Her seri numarası ve her indirme, satın alan müşteriye bağlıdır. Luma ve Hyvä sürümleri ayrı ürünlerdir, seri numaraları da ayrıdır.
- Kodu kendi lisanslı mağazanız için değiştirebilirsiniz. Telif ve lisans notlarını silmeyin.
- Yasak olanlar: başka mağazalara kopyalamak, dağıtmak, yeniden satmak, alt lisans vermek, herkese açık depolarda, pazar yerlerinde veya forumlarda yayınlamak, lisans kontrolünü ya da bu notları kaldırmak, seri numarasını paylaşmak.
- İzinsiz kopyalama veya kullanımda seri numarası iade yapılmadan iptal edilir, güncelleme ve destek durur; WebWorks 101 OÜ tazminat isteyebilir ve yasal yola gidebilir. Estonya hukuku geçerlidir; yetkili mahkeme Harju İlçe Mahkemesi'dir (Harju Maakohus).

Ödeal kendi sahibinin markasıdır; bu modül onlar tarafından yapılmamış veya onaylanmamıştır.

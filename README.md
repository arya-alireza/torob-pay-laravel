# درگاه پرداخت ترب‌پی برای لاراول
این راهنما به شما کمک می‌کند تا به سادگی و با استفاده از یک کلاس Helper، درگاه خرید اعتباری ترب‌پی (CPG) را به پروژه لاراول خود اضافه کنید. این مجموعه شامل یک فایل Helper برای منطق اصلی و یک فایل config برای مدیریت تنظیمات است.

این مخزن براساس آخرین داکیومنت رسمی ارائه شده توسط [ترب‌پی](https://torobpay.com/) برنامه‌نویسی شده است.

## مراحل راه‌اندازی
برای شروع، فایل‌های مورد نیاز را به پروژه خود اضافه کرده و تنظیمات اولیه را انجام دهید.

#### گام اول: کپی کردن فایل‌ها
فایل TorobPay.php را در مسیر app/Helpers/ پروژه خود قرار دهید. (اگر این پوشه وجود ندارد، آن را ایجاد کنید.)

فایل torobpay.php را در مسیر config/ پروژه خود کپی کنید.

#### گام دوم: افزودن اطلاعات درگاه به فایل .env
اطلاعات محرمانه‌ای که از ترب‌پی دریافت کرده‌اید را در فایل .env پروژه خود وارد کنید.
```
TOROBPAY_BASE_URL=https://cpg.torobpay.com
TOROBPAY_CLIENT_ID="your_client_id"
TOROBPAY_CLIENT_SECRET="your_client_secret"
TOROBPAY_USERNAME="your_username"
TOROBPAY_PASSWORD="your_password"
```

#### گام سوم: پاک‌سازی کش تنظیمات
پس از ذخیره کردن مقادیر جدید در فایل .env، دستور زیر را در ترمینال اجرا کنید تا لاراول تنظیمات جدید را شناسایی کند.
```
php artisan config:cache
```

## نحوه استفاده و مثال کاربردی
پس از راه‌اندازی، می‌توانید در کنترلرهای خود یک نمونه از TorobPay بسازید و فرآیند پرداخت را مدیریت کنید.

مثال کامل: از درخواست پرداخت تا تایید نهایی
در ادامه یک سناریوی کامل از هدایت کاربر به درگاه، مدیریت بازگشت و تایید نهایی تراکنش آمده است.

```php
<?php

namespace App\Http\Controllers;

use App\Helpers\TorobPay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentController extends Controller
{
    /**
     * مرحله ۱: ساخت درخواست پرداخت و هدایت کاربر به درگاه
     */
    public function redirectToGateway()
    {
        try {
            $torobPay = new TorobPay();

            $paymentData = [
                'amount' => 500000, // مبلغ کل به ریال
                'mobile' => '09123456789',
                'returnURL' => route('payment.callback'), // آدرس بازگشت از درگاه
                'transactionId' => 'ORDER-' . uniqid(), // شناسه یکتای سفارش شما
                'paymentMethodTypeDto' => 'ONLINE_CREDIT',
                'cartList' => [
                    [
                        'cart_id' => 'CART-123',
                        'total_amount' => 500000,
                        'cartItems' => [
                            [
                                'item_id' => 'PRODUCT-001',
                                'name' => 'کالای تستی',
                                'count' => 1,
                                'amount' => 500000,
                                'category' => 'لوازم دیجیتال',
                            ]
                        ]
                    ]
                ]
            ];
            
            $response = $torobPay->requestPaymentToken($paymentData);

            if ($response['successful']) {
                // کاربر را به صفحه پرداخت هدایت کنید
                return redirect()->away($response['response']['paymentPageUrl']);
            }

            // مدیریت خطا در صورت عدم موفقیت در ایجاد توکن
            return back()->with('error', 'خطا در ایجاد تراکنش: ' . ($response['error']['user_message'] ?? 'خطای نامشخص'));

        } catch (Exception $e) {
            Log::error('TorobPay Redirect Error: ' . $e->getMessage());
            return back()->with('error', 'مشکلی در ارتباط با درگاه پرداخت به وجود آمده است.');
        }
    }

    /**
     * مرحله ۲: مدیریت بازگشت کاربر از درگاه و تایید تراکنش
     */
    public function handleCallback(Request $request)
    {
        $paymentToken = $request->query('paymentToken');

        if (empty($paymentToken)) {
            return redirect('/')->with('error', 'پرداخت ناموفق بود یا توسط کاربر لغو شد.');
        }

        $torobPay = new TorobPay();

        try {
            // گام اول: تایید اولیه (Verify)
            $verifyResponse = $torobPay->verifyPayment($paymentToken);
            
            if (!$verifyResponse['successful']) {
                // اگر Verify ناموفق بود، عملیات را متوقف کن
                throw new Exception('خطا در مرحله Verify: ' . ($verifyResponse['error']['user_message'] ?? 'خطای نامشخص'));
            }

            // در این مرحله می‌توانید وضعیت سفارش را در دیتابیس خود به‌روز کنید (مثلا: در انتظار تسویه)

            // گام دوم: تسویه نهایی (Settle)
            $settleResponse = $torobPay->settlePayment($paymentToken);

            if ($settleResponse['successful']) {
                // تسویه با موفقیت انجام شد. می‌توانید محصول را به کاربر تحویل دهید.
                $transactionId = $settleResponse['response']['transactionId'];
                // وضعیت سفارش را به "پرداخت شده" تغییر دهید
                return redirect('/')->with('success', "پرداخت شما با موفقیت کامل شد. شماره تراکنش: {$transactionId}");
            }
            
            // اگر Settle ناموفق بود، تراکنش را برگردان (Revert)
            throw new Exception('خطا در مرحله Settle: ' . ($settleResponse['error']['user_message'] ?? 'خطای نامشخص'));

        } catch (Exception $e) {
            Log::error('TorobPay Callback Error: ' . $e->getMessage(), ['paymentToken' => $paymentToken]);
            
            // در صورت بروز هرگونه خطا پس از بازگشت از درگاه، تراکنش را Revert کنید
            try {
                $torobPay->revertPayment($paymentToken);
            } catch (Exception $revertException) {
                Log::critical('TorobPay REVERT FAILED: ' . $revertException->getMessage(), ['paymentToken' => $paymentToken]);
            }

            return redirect('/')->with('error', 'فرآیند پرداخت با مشکل مواجه شد. لطفاً مجددا تلاش کنید.');
        }
    }
}
```


## لیست تمام متدهای Helper
کلاس TorobPay متدهای زیر را برای تعامل با API ترب‌پی فراهم می‌کند:
```
obtainAccessToken(): string
```
توضیح: دریافت توکن احراز هویت (JWT). این متد به صورت خودکار توسط سایر متدها فراخوانی می‌شود.

```
checkEligibility(int $amount): array
```
توضیح: بررسی می‌کند که آیا کاربر با توجه به مبلغ سفارش، صلاحیت استفاده از خرید اعتباری را دارد یا خیر.

```
requestPaymentToken(array $paymentData): array
```
توضیح: برای شروع فرآیند پرداخت، یک توکن پرداخت جدید ایجاد می‌کند.

```
verifyPayment(string $paymentToken): array
```
توضیح: پس از بازگشت کاربر از درگاه، صحت تراکنش را تایید اولیه می‌کند.

```
settlePayment(string $paymentToken): array
```
توضیح: تراکنش تایید شده را نهایی (تسویه) می‌کند. این مرحله برای تکمیل خرید الزامی است.

```
revertPayment(string $paymentToken): array
```
توضیح: یک تراکنش را قبل از مرحله settle لغو کرده و وجه را برمی‌گرداند.

```
getPaymentStatus(string $paymentToken): array
```
توضیح: وضعیت فعلی یک تراکنش را استعلام می‌کند.

```
cancelPayment(string $paymentToken): array
```
توضیح: یک سفارش را پس از settle شدن توسط فروشنده لغو می‌کند.

```
updatePayment(array $updateData): array
```
توضیح: اطلاعات یک سفارش فعال (مانند مبلغ یا آیتم‌های سبد خرید) را به‌روزرسانی می‌کند.

**نکته مهم**: تمامی متدها در صورت بروز خطای ارتباطی با سرور یا دریافت پاسخ ناموفق، یک Exception پرتاب می‌کنند. حتماً فراخوانی آن‌ها را در بلوک try-catch قرار دهید تا بتوانید خطاها را به درستی مدیریت کنید.

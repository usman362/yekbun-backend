<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── Controller Imports ───
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CmsApiController;
use App\Http\Controllers\Api\OtpLoginController;
use App\Http\Controllers\Api\Admin\CmsController as AdminCmsController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\AccountSettingController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\FeedsController;
use App\Http\Controllers\Api\VotingController;
use App\Http\Controllers\Api\MultimediaController;
use App\Http\Controllers\Api\ClipsController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\ReportCommentsController;
use App\Http\Controllers\Api\ViewsController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\AdminActivityController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\EmojiFeedController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\AIVideoController;
use App\Http\Controllers\Api\PolicyAndTermsController;
use App\Http\Controllers\Api\RingtoneController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayPalController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\AppVersionController;
use App\Http\Controllers\Api\AnimationEmojiController;
use App\Http\Controllers\Api\ZercashApiController;
use App\Http\Controllers\Api\OfficialsApiController;
use App\Http\Controllers\Api\ComplaintApiController;
use App\Http\Controllers\Api\Admin\ComplaintsAdminController;
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\CartApiController;
use App\Http\Controllers\Api\WalletApiController;
use App\Http\Controllers\Api\KycApiController;
use App\Http\Controllers\Api\PurchaseVerificationController;
use App\Http\Controllers\Api\VotingReactionController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\UserSuggestionController;
use App\Http\Controllers\Api\AvatarsController;
use App\Http\Controllers\Api\TVController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\NewsCategoryController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostGalleryController;
use App\Http\Controllers\Api\BazarController;
use App\Http\Controllers\Api\BazarSubCategoryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\FanPageController;
use App\Http\Controllers\Api\ManageFanPageController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\DiamondUserController;
use App\Http\Controllers\Api\FlaggedUserController;
use App\Http\Controllers\Api\PremiumUserController;
use App\Http\Controllers\Api\StandardUserController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\EventCategoryController;
use App\Http\Controllers\Api\VotingCategoryController;
use App\Http\Controllers\Api\HistoryCategoryController;
use App\Http\Controllers\Api\UploadMovieController;
use App\Http\Controllers\Api\UploadMovieCategoryController;
use App\Http\Controllers\Api\ContactUsController;
use App\Http\Controllers\Api\FeedBackgroundImageController;
use App\Http\Controllers\Api\UserSettingController;
use App\Http\Controllers\Api\UploadMediaController;
use App\Http\Controllers\Api\UpgradeAccountController;
use App\Http\Controllers\Api\MarketServiceContorller;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\UserRolesController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\UsersController as AdminUsersController;
use App\Http\Controllers\Api\Admin\TeamRoleAdminController;
use App\Http\Controllers\Api\Admin\FeedsController as AdminFeedsController;
use App\Http\Controllers\Api\Admin\MusicController as AdminMusicController;
use App\Http\Controllers\Api\Admin\RingtoneController as AdminRingtoneController;
use App\Http\Controllers\Api\Admin\UserClipsController as AdminUserClipsController;
use App\Http\Controllers\Api\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Api\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Api\Admin\LocationAdminController;
use App\Http\Controllers\Api\Admin\CitiesAdminController;
use App\Http\Controllers\Api\Admin\LanguagesAdminController;
use App\Http\Controllers\Api\Admin\PolicyAdminController;
use App\Http\Controllers\Api\Admin\PortalMiscAdminController;
use App\Http\Controllers\Api\Admin\ContentBrowseAdminController;
use App\Http\Controllers\Api\Admin\ContentHistoryAdminController;
use App\Http\Controllers\Api\Admin\ContentAiVideoAdminController;
use App\Http\Controllers\Api\Admin\ContentVotingsAdminController;
use App\Http\Controllers\Api\Admin\OfficialsAdminController;
use App\Http\Controllers\Api\Admin\FileController as AdminFileController;
use App\Http\Controllers\Api\Admin\FeedsSettingsAdminController;
use App\Http\Controllers\Api\Admin\AppUpdatesAdminController;
use App\Http\Controllers\Api\Admin\MaintenanceAdminController;
use App\Http\Controllers\Api\Admin\AutoLogoutAdminController;
use App\Http\Controllers\Api\Admin\SystemAdminController;
use App\Http\Controllers\Api\Admin\SettingsAdminController;
use App\Http\Controllers\Api\Admin\TransactionsAdminController;
use App\Http\Controllers\Api\Admin\ZercashAdminController;
use App\Http\Controllers\Api\Admin\LogipayAdminController;
use App\Http\Controllers\Api\Admin\ProductsAdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ─── Test Route ───
Route::get('/test', function () {
    return response()->json(['status' => 'API is running']);
});

// ══════════════════════════════════════════════════════════════
// PUBLIC ROUTES (No Authentication Required)
// ══════════════════════════════════════════════════════════════

// ─── Authentication ───
Route::post('/signup', [AuthController::class, 'signup']);
Route::get('/check-username', [AuthController::class, 'checkUsername']);
// Public: mobile app polls this for the live release (force-update check).
Route::get('/app/update', [AppUpdatesAdminController::class, 'current']);
// Public: mobile app checks what's under maintenance.
Route::get('/maintenance', [MaintenanceAdminController::class, 'status']);
// Public: mobile app reads the auto-logout / inactivity policy.
Route::get('/app/auto-logout', [AutoLogoutAdminController::class, 'current']);
Route::post('/login', [AuthController::class, 'login']);

// Passwordless OTP login (yekbun.app web app). Step 1 sends a 6-digit code to the user's
// inbox; step 2 verifies the code and returns a JWT in the same shape as /api/login.
Route::post('/otp-login/send', [OtpLoginController::class, 'send']);
Route::post('/otp-login/verify', [OtpLoginController::class, 'verify']);
Route::post('/register-verify-device', [AuthController::class, 'verifyDevice']);
Route::post('/register-device', [AuthController::class, 'registerDevice']);
Route::post('forgot-password', [AuthController::class, 'forgot_password']);
Route::post('reactivate-account', [AuthController::class, 'reactivateAccount']);
Route::post('/reset', [AuthController::class, 'reset'])->name('password.reset');
Route::post('/reset/password', [AuthController::class, 'resetpassword'])->name('reset.complete');
Route::post('/user-otp-code', [AuthController::class, 'getCode'])->name('get.userCode');
Route::post('/reset/resend', [AuthController::class, 'reset_resend'])->name('reset.resend');
Route::post('2fa', [TwoFactorController::class, 'store'])->name('2fa.post');
Route::post('2fa/reset', [TwoFactorController::class, 'resend'])->name('2fa.resend');
Route::get('/user-imei', [AuthController::class, 'userImei']);
Route::post('/check-email-exists', [AuthController::class, 'existsEmail']);

// ─── Currency Login ───
Route::post('/currency-login', [AuthController::class, 'currenceyLogin']);
Route::post('/verify-currency-login', [AuthController::class, 'verifyCurrenceyLogin']);

// ─── Account Setting (Public) ───
Route::post('/send-old-email-code', [AccountSettingController::class, 'send_old_email_code']);
Route::post('/send-new-email-code', [AccountSettingController::class, 'send_new_email_code']);
Route::post('/change-email', [AccountSettingController::class, 'change_email']);
Route::post('/resend-email', [AccountSettingController::class, 'resend_email_code']);
Route::post('/upgrade-account', [AccountSettingController::class, 'upgrade_account']);

// ─── Public User Routes ───
Route::get('get-login-image', [UsersController::class, 'getLoginImage']);
Route::get('profile-banners', [UsersController::class, 'getProfileBanners']);

// ─── App Version ───
Route::get('get-version', [AppVersionController::class, 'getVersion']);
Route::post('update-version', [AppVersionController::class, 'updateVersion']);

// ─── Public Feeds & Content ───
Route::get('public-feeds', [FeedsController::class, 'public_index']);
Route::get('voting-public', [VotingController::class, 'votingPublic']);
Route::get('/get-artists-public', [MultimediaController::class, 'getArtistsPublic'])->middleware('maintenance:music');
Route::get('/get-all-songs-public', [MultimediaController::class, 'getAllSongsPublic'])->middleware('maintenance:music');
Route::get('/get-all-videos-public', [MultimediaController::class, 'getAllClipsPublic'])->middleware('maintenance:music');
Route::post('/media-trimmer', [MultimediaController::class, 'mediaTrimmer']);

// ─── Public Views ───
Route::get('get-multimedia-views', [ViewsController::class, 'get_multimedia_views']);
Route::get('get-feeds-views', [ViewsController::class, 'get_feeds_views']);

// ─── Notifications (Public) ───
Route::get('notifications-center/{id}', [NotificationsController::class, 'read']);
Route::delete('notifications-center/{id}/delete', [NotificationsController::class, 'delete']);
Route::get('system-settings', [NotificationsController::class, 'getSystemSettings']);

// ─── Test Notification ───
Route::post('send-test-notification', [UsersController::class, 'testNotification']);

// ─── Admin Activity (Public) ───
Route::get("/admin-activity/system-info", [AdminActivityController::class, 'getSystemInfo']);
Route::get("/admin-activity/donation", [AdminActivityController::class, 'getDonations']);
Route::get("/admin-activity/surveys", [AdminActivityController::class, 'getSurveys']);
Route::get("/admin-activity/greetings", [AdminActivityController::class, 'getGreetings']);
Route::post("/admin-activity/system-info", [AdminActivityController::class, 'store_systemInfo']);
Route::post("/admin-activity/donation", [AdminActivityController::class, 'store_donation']);
Route::post("/admin-activity/surveys", [AdminActivityController::class, 'store_surveys']);
Route::post("/admin-activity/greetings", [AdminActivityController::class, 'store_greetings']);
Route::get("/admin-activity/get-public-feeds", [AdminActivityController::class, 'getpublicpopFeeds']);
Route::post("/admin-activity/delete-feeds", [AdminActivityController::class, 'delete_pops']);

// ─── Countries ───
Route::get('countries', [CountryController::class, 'index']);
Route::post('countries', [CountryController::class, 'store']);
Route::put('countries/{id}', [CountryController::class, 'update']);
Route::delete('countries/{id}', [CountryController::class, 'destroy']);
Route::get('kurdish-peoples', [CountryController::class, 'kurdishPeoples']);
Route::get('all-countries', [CountryController::class, 'allCountries']);
Route::post('/searchlocation', [CountryController::class, 'search_location']);

// ─── Provinces / Regions ───
Route::get('provinces', [RegionController::class, 'index']);
Route::post('provinces', [RegionController::class, 'store']);
Route::put('provinces/{id}', [RegionController::class, 'update']);
Route::delete('provinces/{id}', [RegionController::class, 'destroy']);

// ─── Cities ───
Route::get('cities', [CityController::class, 'index']);
Route::post('cities', [CityController::class, 'store']);
Route::put('cities/{id}', [CityController::class, 'update']);
Route::delete('cities/{id}', [CityController::class, 'destroy']);
Route::get('get-cities', [CityController::class, 'getCities']);
Route::get('all-cities', [CityController::class, 'allCities']);

// ─── Emojis ───
Route::get('emojis', [EmojiFeedController::class, 'index']);
Route::post('emojis', [EmojiFeedController::class, 'store']);

// ─── News Feeds ───
Route::post('news-feeds', [FeedsController::class, 'news_store']);
Route::get('news-feeds', [FeedsController::class, 'news']);

// ─── Events ───
Route::post('events', [FeedsController::class, 'store_event']);
Route::get('events', [FeedsController::class, 'index']);

// ─── Policy & Terms ───
Route::get('policy_and_terms', [PolicyAndTermsController::class, 'index']);
Route::post('policy_and_terms', [PolicyAndTermsController::class, 'store']);
Route::post('policy_and_terms/saveFileds', [PolicyAndTermsController::class, 'saveFileds']);
Route::delete('policy_and_terms/{id}', [PolicyAndTermsController::class, 'destroy']);
Route::apiResource('policy-and-terms', PolicyAndTermsController::class);
Route::post('saveFileds', [PolicyAndTermsController::class, 'saveFileds']);

// ─── Languages ───
Route::get('languages', [LanguageController::class, 'index']);
Route::get('languages-list', [LanguageController::class, 'languagesList']);
Route::post('languages', [LanguageController::class, 'store']);
Route::post('languages/{id}', [LanguageController::class, 'update']);
Route::delete('languages/{id}', [LanguageController::class, 'destroy']);
Route::get('languages/{id}/keywords', [LanguageController::class, 'keywords']);
Route::get('languages/{id}/keywords/{keyword}', [LanguageController::class, 'keyword']);

// ─── Translation ───
Route::prefix('/translate')->name('api.translate.')->group(function () {
    Route::get('/languages', [TranslationController::class, 'fetch_languages'])->name('languages');
    Route::get('/{id}', [TranslationController::class, 'translate'])->name('translate');
});

// ─── App Settings ───
Route::get('app-setting/appinfo', [SettingsController::class, 'app_info']);

// ─── Ringtones ───
Route::get('app-setting/message-ringtone', [RingtoneController::class, 'getMessage']);
Route::get('app-setting/call-ringtone', [RingtoneController::class, 'getCall']);
Route::get('app-setting/notification-ringtone', [RingtoneController::class, 'getNotification']);
Route::get('app-setting/ringtone', [RingtoneController::class, 'index']);
Route::post('app-setting/ringtone', [RingtoneController::class, 'store']);
Route::delete('app-setting/ringtone/{id}', [RingtoneController::class, 'destroy']);
Route::get('/ringtone', [RingtoneController::class, 'get']);
Route::post('/ringtone/{id}/download', [RingtoneController::class, 'download']);

// ─── History ───
Route::get('/history', [HistoryController::class, 'index']);
Route::get('/category-history/{id}', [HistoryController::class, 'categorgy_history']);
Route::get('/history-cover', [HistoryController::class, 'cover_history']);
Route::get('/history-category', [HistoryController::class, 'categories']);
Route::get('/history-detail/{id}', [HistoryController::class, 'detail']);
Route::post('/history-search', [HistoryController::class, 'search']);

// ─── AI Videos ───
Route::get('/ai-videos', [AIVideoController::class, 'index']);

// ─── Voting (Public) ───
Route::get('/voting-cover/{id?}', [VotingController::class, 'get_cover']);
Route::get('/fetch-voting/{id?}', [VotingController::class, 'fetch']);
Route::get('/fetch-voting/all/{id?}', [VotingController::class, 'fetch_all']);
Route::get('/voting-details/{id}/{user_id?}', [VotingController::class, 'get_details']);
Route::get('/voting-stats/{id}', [VotingController::class, 'get_statistics']);
Route::get('/voting-province-stats/{id}', [VotingController::class, 'get_province_statistics']);

// ─── Animation Emoji ───
Route::get('/get-all-emoji/{userId?}/{type?}/{value?}', [AnimationEmojiController::class, 'get_all_emoji']);

// ─── Payments (Public) ───
Route::post('charge', [PaymentController::class, 'charge']);
Route::get('success', [PaymentController::class, 'success']);
Route::get('error', [PaymentController::class, 'error']);
Route::get('/payment-details/{payment_id}', [PaymentController::class, 'payment_details']);
Route::get('get-payment-list', [PaymentController::class, 'paymentList']);

// ─── PayPal ───
Route::prefix('paypal')->group(function () {
    Route::post('create-order', [PayPalController::class, 'createOrder']);
    Route::post('capture-order', [PayPalController::class, 'captureOrder']);
    Route::get('keys', [PayPalController::class, 'keys']);
});

// ─── Stripe ───
Route::post('/stripe/checkout', [StripeController::class, 'index']);
Route::get('/stripe/update-transaction', [StripeController::class, 'update']);
Route::get('/stripe/update-success', [StripeController::class, 'success']);

// ─── Mobile: Avatars Feeds ───
Route::get('/getfeeds', [AvatarsController::class, 'getfeeds']);
Route::post('/postfeed', [AvatarsController::class, 'postfeed']);

// ─── Mobile: Zarok (TV) ───
Route::get('/getzarokstories', [TVController::class, 'zarokStories']);
Route::get('/getzarokvideos', [TVController::class, 'zarokVideos']);
Route::get('/getzarokmovies', [TVController::class, 'zarokMovies']);
Route::get('/getzarokseries', [TVController::class, 'zarokSeries']);
Route::get('/zarok-series-season/{id}', [TVController::class, 'zarokSeriesSeason']);
Route::get('/zarok-series-episodes/{id}', [TVController::class, 'zarokSeriesEpisodes']);
Route::post('/zarokStoriesPost', [TVController::class, 'zarokStoriesPost']);

// ─── Mobile: Posts ───
Route::resource('posts', PostController::class)->except(['create', 'edit']);

// ─── Mobile: Flagged users ───
Route::resource('flagged-users', FlaggedUserController::class)->except(['create', 'edit']);

// ─── Mobile: Reports ───
Route::resource('reports', ReportController::class)->except(['create', 'edit']);

// ─── Mobile: Organizations ───
Route::resource('organizations', OrganizationController::class)->except(['create', 'edit']);

// ─── Mobile: Donations ───
Route::resource('donations', DonationController::class)->except(['create', 'edit']);

// ─── Mobile: Event Categories ───
Route::resource('event-categories', EventCategoryController::class)->except(['create', 'edit']);

// ─── Mobile: Tickets ───
Route::resource('tickets', TicketController::class)->except(['create', 'edit']);

// ─── Mobile: Users (classification) ───
Route::prefix('/users')->group(function () {
    Route::post('{id}/block/', [StandardUserController::class, 'block'])->name('block');
    Route::post('{id}/warn/', [StandardUserController::class, 'warn'])->name('warn');
    Route::post('{id}/upgrade/', [StandardUserController::class, 'upgrade'])->name('upgrade');
    Route::resource('educated', StandardUserController::class);
    Route::resource('cultivated', PremiumUserController::class);
    Route::resource('academic', DiamondUserController::class);
});

// ─── Mobile: User Roles ───
Route::prefix('user-roles')->group(function () {
    Route::get('/educated', [UserRolesController::class, 'educated'])->name('educated');
    Route::get('/cultivated', [UserRolesController::class, 'cultivated'])->name('cultivated');
    Route::get('/academic', [UserRolesController::class, 'academic'])->name('academic');
});
Route::get('user-prices/{userLevel}', [UserRolesController::class, 'prices']);

// ─── Mobile: News ───
Route::resource('news', NewsController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
Route::resource('news-category', NewsCategoryController::class)->only(['index', 'store', 'update', 'show', 'destroy']);
Route::get('/category-news/{id}', [NewsController::class, 'category_news']);
Route::get('/news-cover', [NewsController::class, 'cover_news']);
Route::get('/news-category', [NewsController::class, 'categories']);
Route::get('/news-detail/{id}', [NewsController::class, 'detail']);
Route::post('/news-search', [NewsController::class, 'search']);

// ─── Mobile: Fan Pages ───
Route::resource('fan-page', FanPageController::class)->only(['index', 'store', 'show', 'destroy', 'update']);
Route::resource('manage-fanpage', ManageFanPageController::class)->only(['index', 'store', 'show', 'destroy', 'update']);

// ─── Mobile: Voting Category ───
Route::resource('voting-category', VotingCategoryController::class)->only(['index', 'store', 'show', 'destroy', 'update']);

// ─── Mobile: History Category ───
Route::resource('history-category', HistoryCategoryController::class)->only(['index', 'store', 'show', 'destroy', 'update']);

// ─── Mobile: Bazar ───
Route::resource('bazar-subcategory', BazarSubCategoryController::class)->only(['index', 'store', 'show', 'destroy', 'update']);
Route::resource('bazar', BazarController::class)->only(['index', 'store', 'show', 'destroy', 'update']);

// ─── Mobile: Movies ───
Route::resource('movie-category', UploadMovieCategoryController::class)->only(['index', 'store', 'show', 'destroy', 'update']);
Route::resource('movie', UploadMovieController::class)->only(['index', 'store', 'show', 'destroy', 'update']);

// ─── Mobile: Contact Us ───
Route::post('/contact-us', [ContactUsController::class, 'contact_us'])->name('contact-us');

// ─── Mobile: User Setting ───
Route::post('/user-setting/{user_id}', [UserSettingController::class, 'index'])->name('user-setting');
Route::post('/user-setting/save', [UserSettingController::class, 'save'])
    ->name('user-setting-save')
    ->middleware('auth:sanctum');

// ─── Mobile: Upload Media ───
Route::post('/upload-media', [UploadMediaController::class, 'index']);

// ─── Mobile: Feed Background Image ───
Route::post('/upload-background', [FeedBackgroundImageController::class, 'upload'])->name('upload-background');
Route::get('/get-background', [FeedBackgroundImageController::class, 'get'])->name('get-background');

// ─── Mobile: Upgrade Account ───
Route::get('get_account_price', [UpgradeAccountController::class, 'price_upgrade'])->name('get_account_price');
Route::post('/account-upgrade', [UpgradeAccountController::class, 'account_upgrade'])
    ->name('account-upgrade')
    ->middleware('auth:sanctum');

// ─── Mobile: Reaction ───
Route::post('/store-reaction', [ReactionController::class, 'store_reaction'])->name('store-reaction');

// ─── Mobile: Market Services ───
Route::post('/market-services', [MarketServiceContorller::class, 'market_services'])->name('market-services');

// ─── Mobile: Post Gallery ───
Route::post('/get-gallery', [PostGalleryController::class, 'get_gallery']);

// ─── Mobile: Authenticated User ───
Route::get('/user', function (Request $request) {
    return $request->user();
});

// ─── Zercash (Public) ───
Route::get('zercash/products', [ZercashApiController::class, 'products']);
Route::get('zercash/products/{id}', [ZercashApiController::class, 'productDetail']);
Route::get('zercash/categories', [ZercashApiController::class, 'categories']);
Route::get('zercash/plans', [ZercashApiController::class, 'plans']);
Route::get('zercash/settings', [ZercashApiController::class, 'settings']);
// Mobile read endpoints for the admin-managed Cashback Manager + Country matrix.
// Only enabled / active rows are returned. Admin writes via /admin/zercash/* (auth-gated).
Route::get('zercash/cashback-rules', [ZercashApiController::class, 'cashbackRules']);

// ─── Officials / Kurdistan (Public read for the mobile app) ───
// Admin manages these under /admin/officials/* ; the mobile app reads them here.
Route::get('officials', [OfficialsApiController::class, 'index']);
Route::get('officials/constitutions', [OfficialsApiController::class, 'constitutions']);
Route::get('officials/people', [OfficialsApiController::class, 'people']);
Route::get('officials/ministries', [OfficialsApiController::class, 'ministries']);
Route::get('officials/civil-laws', [OfficialsApiController::class, 'civilLaws']);
Route::get('officials/holidays', [OfficialsApiController::class, 'holidays']);
Route::get('officials/content/{section}', [OfficialsApiController::class, 'content']);

// ─── Kurdistan Complaints — categories (public) ───
// The feed of approved complaints, submit, and like/comment all need auth (see the
// authenticated group). Like/comment reuse /feeds/{id}/{likes|comments} (feed_type=complaint).
Route::get('complaint-categories', [ComplaintApiController::class, 'categories']);

// Invoice PDF download — PUBLIC route (no token) so it opens directly in a browser / native
// downloader. Looks up by _id / invoice_id / order_id.
Route::get('invoices/{id}/download', [InvoiceApiController::class, 'download']);

// ── Public CMS reads — driven by the admin's WebApp CMS page ──
// Pages: { landing: {key→value}, login: {…} } — for hardcoded copy on the public app.
// Translations: per-key per-language overrides on top of the bundled i18n catalogue.
// Media: { slot → CDN URL } for admin-replaceable banners, logos, etc.
Route::get('cms/pages',        [CmsApiController::class, 'pages']);
Route::get('cms/translations', [CmsApiController::class, 'translations']);
Route::get('cms/media',        [CmsApiController::class, 'media']);
Route::get('zercash/countries', [ZercashApiController::class, 'zercashCountries']);
Route::get('zercash/shops', [ZercashApiController::class, 'shops']);
Route::get('zercash/sale-managers', [ZercashApiController::class, 'saleManagers']);
Route::get('zercash/faqs', [ZercashApiController::class, 'faqs']);
Route::get('zercash/site-settings', [ZercashApiController::class, 'siteSettings']);


// ══════════════════════════════════════════════════════════════
// PROTECTED ROUTES (JWT Authentication Required)
// ══════════════════════════════════════════════════════════════

Route::middleware('jwt.custom')->group(function () {

    // Purchases
    Route::post('purchases/verify', [PurchaseVerificationController::class, 'verify']);

    // ─── Auth ───
    Route::post('/change-password', [AccountSettingController::class, 'change_password']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/get-my-details', [AuthController::class, 'getMyDetails']);
    Route::delete('/delete-my-account', [AuthController::class, 'deleteMyAccount']);
    Route::get('deactivate-account', [AuthController::class, 'deactivateAccount']);
    Route::post('/accept-privacy-policy', [AuthController::class, 'acceptPrivacyPolicy']);

    // ─── Users ───
    Route::post('send-users-request', [UsersController::class, 'sendRequest']);
    Route::post('accept-users-request', [UsersController::class, 'acceptRequest']);
    Route::post('user-online/{id}', [UsersController::class, 'store_user_online']);
    Route::get('get-users-list', [UsersController::class, 'users_list']);
    Route::get('get-users-may-you-know-list', [UsersController::class, 'users_may_you_know_list']);
    Route::get('get-users-details/{id}', [UsersController::class, 'users_details']);
    Route::get('get-friends-list/{id}', [UsersController::class, 'freind_list']);
    Route::post('update-friends-list', [UsersController::class, 'update_freind_list']);
    Route::get('get-block-friends-list/{id}', [UsersController::class, 'block_list']);
    Route::post('block-friends', [UsersController::class, 'update_block_list']);
    Route::get('unblock-user/{id}', [UsersController::class, 'unblock_user']);
    Route::get('get-family-list/{id}', [UsersController::class, 'family_list']);
    Route::get('unfriend-user/{id}', [UsersController::class, 'unfriend_user']);
    Route::get('get-requests-list/{id}', [UsersController::class, 'request_list']);
    Route::post('user-visitor', [UsersController::class, 'vistor']);
    Route::get('get-user-visitor', [UsersController::class, 'get_vistor']);
    Route::post('update-fcm-token', [UsersController::class, 'updateDeviceToken']);
    Route::post('send-fcm-notification', [UsersController::class, 'sendNotification']);
    Route::post('my-service', [UsersController::class, 'storeMyService']);
    Route::post('my-network', [UsersController::class, 'storeMyNetwork']);
    Route::post('my-notification', [UsersController::class, 'storeMyNotification']);
    Route::post('user/{id}/report', [UsersController::class, 'reportstore']);
    Route::get('user-report', [UsersController::class, 'getReport']);
    Route::post('search-users', [UsersController::class, 'search_user']);
    Route::get('congrats-popup', [UsersController::class, 'congrats_popup_off']);

    // User Images/Videos
    Route::get('user-images', [UsersController::class, 'user_images']);
    Route::get('user-videos', [UsersController::class, 'user_videos']);

    // ─── Feeds ───
    Route::get('feeds', [FeedsController::class, 'index']);
    Route::get('user-feeds/{user_id}', [FeedsController::class, 'userFeeds']);
    Route::get('user-feeds', [FeedsController::class, 'userFeeds']); // ?user_id=
    Route::post('feeds', [FeedsController::class, 'store']);
    Route::post('share-feeds', [FeedsController::class, 'share']);
    Route::post('search-feeds-users', [FeedsController::class, 'search_user']);
    Route::post('feeds-permission/{id}', [FeedsController::class, 'change_permission']);
    Route::delete('feeds/{id}', [FeedsController::class, 'delete']);
    Route::get('get-feeds-statistics/{id}', [FeedsController::class, 'get_statistics']);

    // Feed Comments & Likes
    Route::get('feeds/{id}/comments', [FeedsController::class, 'getComments']);
    Route::post('feeds/{id}/comments', [FeedsController::class, 'storeComments']);
    Route::post('comments/{id}/edit', [FeedsController::class, 'editComments']);
    Route::post('feeds/{id}/likes', [FeedsController::class, 'feedLike']);
    Route::get('feeds/{id}/likes', [FeedsController::class, 'getfeedLike']);
    Route::post('comments/{id}/likes', [FeedsController::class, 'commentLike']);
    Route::delete('comments/{id}/delete', [FeedsController::class, 'commentDelete']);

    // Feed Reports
    Route::get('comments/{id}/report', [ReportCommentsController::class, 'index']);
    Route::post('comments/{id}/report', [ReportCommentsController::class, 'store']);
    Route::post('feed/{id}/report', [ReportCommentsController::class, 'reportfeedstore']);
    Route::get('/reported-comments', [ReportCommentsController::class, 'getUserReportedComments']);
    Route::post('/resolve-report-violation', [ReportCommentsController::class, 'resolveReportViolation']);

    // ─── Kurdistan Complaints (feed + submit + own list) ───
    // Like/comment use the existing /feeds/{id}/likes & /feeds/{id}/comments with
    // feed_type=complaint — no new endpoints needed for engagement.
    Route::get('complaints', [ComplaintApiController::class, 'index']);
    Route::post('complaints', [ComplaintApiController::class, 'store']);
    Route::get('my-complaints', [ComplaintApiController::class, 'mine']);
    Route::post('complaints/upload-image', [ComplaintApiController::class, 'uploadImage']);
    Route::get('complaints/{id}', [ComplaintApiController::class, 'show']);

    // ─── Voting ───
    Route::resource('voting', VotingController::class)->only(['index', 'store', 'show', 'destroy', 'update']);
    Route::post('/voting/reaction', [VotingReactionController::class, 'store']);
    Route::get('/most-view-votes', [VotingController::class, 'mostViews']);
    Route::get('/latest-votes', [VotingController::class, 'latestVotes']);
    Route::get('/previous-votes', [VotingController::class, 'previousVotes']);
    Route::get('/already-voted-votes', [VotingController::class, 'alreadyVoted']);
    Route::get('/waiting-votes', [VotingController::class, 'waitingVote']);
    Route::get('/surveys-home', [VotingController::class, 'surveysHome']);
    Route::get('/votes-home', [VotingController::class, 'surveysHome']);
    Route::get('/voting/{voting_id}/reactions', [VotingReactionController::class, 'index']);
    Route::post('/voting-views', [VotingReactionController::class, 'votingViews']);
    Route::delete('/voting/reaction/{id}', [VotingReactionController::class, 'destroy']);

    // ─── User Profile ───
    Route::post('/user/profile/store', [UserProfileController::class, 'store'])->name('user_profile.store');

    // ─── User Suggestions ───
    Route::get('/user-suggestions', [UserSuggestionController::class, 'index']);

    // ─── Multimedia / Artists ───
    Route::get('/get-artists', [MultimediaController::class, 'getArtists'])->middleware('maintenance:music');
    Route::get('/get-all-songs', [MultimediaController::class, 'getAllSongs'])->middleware('maintenance:music');
    Route::get('/get-all-videos', [MultimediaController::class, 'getAllClips'])->middleware('maintenance:music');
    Route::get('/get-artist-songs/{id}', [MultimediaController::class, 'getSongByArtists'])->middleware('maintenance:music');
    Route::get('/get-artist-videos/{id}', [MultimediaController::class, 'getClipsByArtists']);
    Route::get('/get-popular-artists', [MultimediaController::class, 'getPopularArtists']);
    Route::get('/artists-grouped', [MultimediaController::class, 'getArtistsGrouped']);
    Route::get('/get-artists-details/{id}', [MultimediaController::class, 'getArtistDetail']);
    Route::get('get-favorite-artists', [MultimediaController::class, 'getFavArtists']);
    Route::post('store-artist-song-views/{id}', [MultimediaController::class, 'store_artist_song_views']);
    Route::post('store-artist-video-views/{id}', [MultimediaController::class, 'store_artist_video_views']);
    Route::post('store-artist-favorites/{id}', [MultimediaController::class, 'store_artist_favorites']);
    Route::get('get-all-media-videos', [MultimediaController::class, 'allMediaRecord']);

    // Play Music/Video
    Route::get('/play-music/{id}', [MultimediaController::class, 'playMusic']);
    Route::get('/play-video/{id}', [MultimediaController::class, 'playVideo']);

    // Playlists
    Route::get('get-songs-playlist', [MultimediaController::class, 'getSongsPlaylist']);
    Route::get('get-playlist/{id}', [MultimediaController::class, 'getPlaylistDetail']);
    Route::post('edit-playlist/{id}', [MultimediaController::class, 'editPlaylist']);
    Route::post('store-songs-playlist', [MultimediaController::class, 'storeSongsPlaylist']);
    Route::get('get-clips-playlist', [MultimediaController::class, 'getClipsPlaylist']);
    Route::post('store-clips-playlist', [MultimediaController::class, 'storeClipsPlaylist']);
    Route::post('move-playlist-music/{id}', [MultimediaController::class, 'movePlaylist']);
    Route::delete('delete-playlist-music/{id}', [MultimediaController::class, 'deletePlaylist']);
    Route::delete('delete-playlist-group/{id}', [MultimediaController::class, 'deletePlaylistGroup']);

    // ─── Clips ───
    Route::get('get-clips', [ClipsController::class, 'index']);
    Route::get('get-my-clips', [ClipsController::class, 'getMyClips']);
    Route::post('store-clips', [ClipsController::class, 'store_clips']);
    Route::delete('delete-clips/{id}', [ClipsController::class, 'destroy']);
    Route::post('like-clips', [ClipsController::class, 'like_clips']);
    Route::get('get-clips-templates', [ClipsController::class, 'get_templates']);
    Route::post('store-clips-templates', [ClipsController::class, 'store_templates']);

    // ─── Views ───
    Route::post('store-multimedia-views', [ViewsController::class, 'store_multimedia_views']);
    Route::post('store-feeds-views', [ViewsController::class, 'store_feeds_views']);

    // ─── Collections ───
    Route::post('/create-collection', [CollectionController::class, 'insert']);
    Route::post('/add-to-collection', [CollectionController::class, 'add_to_collection']);
    Route::get('/list-collections', [CollectionController::class, 'get_collection']);
    Route::delete('/remove-collection/{id}', [CollectionController::class, 'destroy']);
    Route::get('/list-collection-items/{collection_id}', [CollectionController::class, 'listCollectionItems']);
    Route::delete('/collections/{collection_id}/feeds/{feed_id}', [CollectionController::class, 'destroyCollectionFeed']);

    // ─── Admin Activity (Protected) ───
    Route::get("/admin-activity/get-feeds", [AdminActivityController::class, 'getpopFeeds']);
    Route::get("/deactivate-sos/{id}", [AdminActivityController::class, 'deactivateSOS']);

    // ─── Notifications Center ───
    Route::get('notifications-center', [NotificationsController::class, 'index']);
    Route::post('notifications-center', [NotificationsController::class, 'store']);

    // Wallet (Zercash) app notification screen — scoped to wallet events only (System Info /
    // Wallet News tabs). Per-item read/delete reuse the shared notifications-center/{id} routes.
    Route::get('wallet/notifications', [NotificationsController::class, 'walletIndex']);
    Route::post('wallet/notifications/read-all', [NotificationsController::class, 'walletReadAll']);

    // ─── Payments & Transactions ───
    Route::get('get-transaction-list', [PaymentController::class, 'getTransactionList']);
    Route::post('post-transaction', [PaymentController::class, 'storeTransaction']);
    Route::get('get-invoice/{tId}', [PaymentController::class, 'getInvoice']);

    // ─── Zercash (Authenticated) ───
    Route::get('zercash/wallet', [ZercashApiController::class, 'wallet']);
    Route::get('zercash/wallet/transactions', [ZercashApiController::class, 'walletTransactions']);
    Route::post('zercash/subscription/update', [ZercashApiController::class, 'updateSubscription']);

    // ─── Cart API (New Zercash) ───
    Route::get('cart', [CartApiController::class, 'index']);
    Route::post('cart/items', [CartApiController::class, 'store']);
    Route::put('cart/items/{id}', [CartApiController::class, 'update']);
    Route::delete('cart/items/{id}', [CartApiController::class, 'destroy']);
    Route::post('cart/clear', [CartApiController::class, 'clear']);

    // ─── Order detail ── (orders list + checkout live in the Cart-checkout block below) ──
    Route::get('orders/{id}', [CheckoutController::class, 'orderDetail']);

    // ─── Wallet Management ───
    Route::post('wallet/create-pin', [WalletApiController::class, 'createWallet']);
    Route::post('wallet/verify-pin', [WalletApiController::class, 'verifyPin']);
    Route::post('wallet/change-pin', [WalletApiController::class, 'changePin']);
    Route::get('wallet/status', [WalletApiController::class, 'walletStatus']);
    Route::get('wallet/dashboard', [WalletApiController::class, 'dashboard']);
    // Standalone payments chart — week / month / year bar chart on the dashboard.
    // Pass `?period=week|month|year` for a single series, or omit to get all three.
    Route::get('wallet/payments', [WalletApiController::class, 'payments']);
    Route::get('wallet/quick-access', [WalletApiController::class, 'quickAccess']);
    Route::get('wallet/chart', [WalletApiController::class, 'chartData']);
    // "My Activity / Wallet Statistics" screen — summary + chart + spend-by-category overview.
    Route::get('wallet/statistics', [WalletApiController::class, 'statistics']);
    Route::get('wallet/deposits', [WalletApiController::class, 'deposits']);
    Route::get('wallet/cashbacks', [WalletApiController::class, 'cashbacks']);
    // Total claimable (pending) cashback + sweep it into the wallet balance.
    Route::get('wallet/cashback-balance', [WalletApiController::class, 'cashbackBalance']);
    Route::post('wallet/cashback/transfer', [WalletApiController::class, 'transferCashback']);
    Route::get('wallet/payouts', [WalletApiController::class, 'payouts']);
    Route::get('wallet/transactions', [WalletApiController::class, 'transactions']);
    Route::post('wallet/deposit', [WalletApiController::class, 'deposit']);

    // ── Cart checkout / orders ──
    // Single entry point that re-prices the cart, applies the chosen payment method
    // (wallet balance debit, or PENDING for store / bank / paypal) and records per-line
    // transactions. Mobile + web use the same endpoint.
    Route::post('checkout',  [CheckoutController::class, 'checkout']);
    Route::get('orders',     [CheckoutController::class, 'myOrders']);

    // Invoices for Zercash purchases (auto-generated on checkout; shown in profile).
    Route::get('invoices',      [InvoiceApiController::class, 'index']);
    Route::get('invoices/{id}', [InvoiceApiController::class, 'show']);
    Route::post('wallet/activate', [WalletApiController::class, 'activateWallet']);
    Route::post('wallet/update-status', [WalletApiController::class, 'updateWalletStatus']);

    // ─── KYC Verification ───
    Route::get('kyc/document-types', [KycApiController::class, 'documentTypes']);
    Route::post('kyc/send-otp', [KycApiController::class, 'sendOtp']);
    Route::post('kyc/verify-otp', [KycApiController::class, 'verifyOtp']);
    Route::post('kyc/submit', [KycApiController::class, 'submit']);
    Route::get('kyc/status', [KycApiController::class, 'status']);
    Route::post('kyc/review', [KycApiController::class, 'review']);
    Route::get('kyc/pending', [KycApiController::class, 'pendingList']);
});


// ══════════════════════════════════════════════════════════════
// ADMIN PANEL ROUTES (React Frontend)
// ══════════════════════════════════════════════════════════════

Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);

    Route::middleware('jwt.custom')->group(function () {
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::put('/profile', [AdminAuthController::class, 'updateProfile']);
        Route::post('/profile', [AdminAuthController::class, 'updateProfile']); // multipart fields
        Route::post('/profile/avatar', [AdminAuthController::class, 'updateAvatar']);
        Route::post('/profile/password', [AdminAuthController::class, 'changePassword']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::post('/refresh', [AdminAuthController::class, 'refresh']);

        // ─── Dashboard ───
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);
        Route::get('/dashboard/activity', [AdminDashboardController::class, 'activity']);
        Route::get('/dashboard/geo', [AdminDashboardController::class, 'geo']);
        Route::get('/dashboard/server', [AdminDashboardController::class, 'server']);

        Route::get('/users', [AdminUsersController::class, 'index']);
        Route::get('/users/stats', [AdminUsersController::class, 'stats']);
        Route::get('/users/{id}/details', [AdminUsersController::class, 'details']);
        Route::post('/users/{id}/wallet/accept', [AdminUsersController::class, 'walletAccept']);
        Route::post('/users/{id}/wallet/reject', [AdminUsersController::class, 'walletReject']);
        Route::delete('/users/{id}', [AdminUsersController::class, 'destroy']);

        // ─── Team & Role ───
        Route::get('/team/roles', [TeamRoleAdminController::class, 'roles']);
        Route::post('/team/roles', [TeamRoleAdminController::class, 'storeRole']);
        Route::put('/team/roles/{id}', [TeamRoleAdminController::class, 'updateRole']);
        Route::delete('/team/roles/{id}', [TeamRoleAdminController::class, 'destroyRole']);
        Route::get('/team/members', [TeamRoleAdminController::class, 'members']);
        Route::post('/team/members', [TeamRoleAdminController::class, 'storeMember']);
        Route::put('/team/members/{id}', [TeamRoleAdminController::class, 'updateMember']);
        Route::delete('/team/members/{id}', [TeamRoleAdminController::class, 'destroyMember']);

        // ─── Feeds ───
        Route::get('/feeds/latest', [AdminFeedsController::class, 'latest']);
        Route::get('/feeds/on-hold', [AdminFeedsController::class, 'onHold']);
        Route::get('/feeds/stats', [AdminFeedsController::class, 'stats']);
        Route::get('/feeds/reported', [AdminFeedsController::class, 'reportedFeeds']);
        Route::get('/feeds/reported-comments', [AdminFeedsController::class, 'reportedComments']);
        Route::post('/feeds/action', [AdminFeedsController::class, 'actionFeed']);
        Route::get('/feeds/{id}/comments', [AdminFeedsController::class, 'feedComments']);
        Route::post('/feeds/{id}/comments', [AdminFeedsController::class, 'addComment']);
        Route::post('/comments/{id}/likes', [AdminFeedsController::class, 'likeComment']);
        Route::delete('/comments/{id}', [AdminFeedsController::class, 'deleteComment']);

        // ─── Music ───
        Route::get('/music/artists', [AdminMusicController::class, 'artists']);
        Route::post('/music/artists', [AdminMusicController::class, 'storeArtist']);
        Route::match(['put','post'], '/music/artists/{id}', [AdminMusicController::class, 'updateArtist']);
        Route::delete('/music/artists/{id}', [AdminMusicController::class, 'deleteArtist']);
        Route::get('/music/songs', [AdminMusicController::class, 'songs']);
        Route::post('/music/songs', [AdminMusicController::class, 'storeSong']);
        Route::match(['put', 'post'], '/music/songs/{id}', [AdminMusicController::class, 'updateSong']);
        Route::delete('/music/songs/{id}', [AdminMusicController::class, 'deleteSong']);
        Route::get('/music/categories', [AdminMusicController::class, 'musicCategories']);
        Route::get('/music/video-clips', [AdminMusicController::class, 'videoClips']);
        Route::post('/music/video-clips/generate-thumbnails', [AdminMusicController::class, 'generateClipThumbnails']);
        Route::post('/music/video-clips', [AdminMusicController::class, 'storeClip']);
        Route::match(['put','post'], '/music/video-clips/{id}', [AdminMusicController::class, 'updateClip']);
        Route::delete('/music/video-clips/{id}', [AdminMusicController::class, 'deleteClip']);
        Route::get('/music/artists/{id}/songs', [AdminMusicController::class, 'artistSongs']);
        Route::get('/music/artists/{id}/clips', [AdminMusicController::class, 'artistClips']);
        Route::get('/music/stats', [AdminMusicController::class, 'stats']);

        // ─── Ringtones ───
        Route::get('/ringtones', [AdminRingtoneController::class, 'index']);
        Route::get('/ringtones/stats', [AdminRingtoneController::class, 'stats']);
        Route::post('/ringtones', [AdminRingtoneController::class, 'store']);
        Route::post('/ringtones/{id}/toggle', [AdminRingtoneController::class, 'toggle']);
        Route::delete('/ringtones/{id}', [AdminRingtoneController::class, 'destroy']);

        // ─── User Clips ───
        Route::get('/clips/templates', [AdminUserClipsController::class, 'templates']);
        Route::post('/clips/templates', [AdminUserClipsController::class, 'storeTemplate']);
        Route::put('/clips/templates/{id}', [AdminUserClipsController::class, 'updateTemplate']);
        Route::delete('/clips/templates/{id}', [AdminUserClipsController::class, 'destroyTemplate']);
        Route::post('/clips/templates/generate-thumbnails', [AdminUserClipsController::class, 'generateTemplateThumbnails']);
        Route::get('/clips', [AdminUserClipsController::class, 'clips']);
        Route::get('/clips/reported', [AdminUserClipsController::class, 'reported']);

        // ─── Team & Roles ───
        // Team & Role GETs moved to TeamRoleAdminController (see below)

        // ─── Departments ───
        Route::get('/departments', [AdminDepartmentController::class, 'index']);
        Route::post('/departments', [AdminDepartmentController::class, 'store']);
        Route::put('/departments/{id}', [AdminDepartmentController::class, 'update']);
        Route::delete('/departments/{id}', [AdminDepartmentController::class, 'destroy']);
        Route::get('/departments/{id}/sub', [AdminDepartmentController::class, 'subDepartments']);

        // ─── Locations (countries / regions) ───
        Route::get('/locations/tree', [LocationAdminController::class, 'tree']);
        Route::get('/locations/regions', [LocationAdminController::class, 'regionsList']);
        Route::post('/locations/countries', [LocationAdminController::class, 'storeCountry']);
        Route::put('/locations/countries/{id}', [LocationAdminController::class, 'updateCountry']);
        Route::delete('/locations/countries/{id}', [LocationAdminController::class, 'destroyCountry']);
        Route::post('/locations/regions', [LocationAdminController::class, 'storeRegion']);
        Route::put('/locations/regions/{id}', [LocationAdminController::class, 'updateRegion']);
        Route::delete('/locations/regions/{id}', [LocationAdminController::class, 'destroyRegion']);

        // ─── Cities ───
        Route::get('/cities/browse', [CitiesAdminController::class, 'index']);
        Route::get('/cities/meta', [CitiesAdminController::class, 'meta']);
        Route::post('/cities', [CitiesAdminController::class, 'store']);
        Route::put('/cities/{id}', [CitiesAdminController::class, 'update']);
        Route::delete('/cities/{id}', [CitiesAdminController::class, 'destroy']);

        // ─── Languages ───
        Route::get('/languages/browse', [LanguagesAdminController::class, 'index']);
        Route::post('/languages', [LanguagesAdminController::class, 'store']);
        Route::put('/languages/{id}', [LanguagesAdminController::class, 'update']);
        Route::delete('/languages/{id}', [LanguagesAdminController::class, 'destroy']);
        Route::get('/languages/{id}/keywords', [LanguagesAdminController::class, 'keywords']);
        Route::put('/languages/{languageId}/keywords/{translationId}', [LanguagesAdminController::class, 'updateKeyword']);
        Route::get('/languages/{id}/keywords/export', [LanguagesAdminController::class, 'exportKeywords']);
        Route::post('/languages/{id}/keywords/import', [LanguagesAdminController::class, 'importKeywords']);

        // ─── Policy ───
        Route::get('/policy', [PolicyAdminController::class, 'index']);
        Route::post('/policy', [PolicyAdminController::class, 'store']);
        Route::put('/policy/{id}', [PolicyAdminController::class, 'update']);
        Route::delete('/policy/{id}', [PolicyAdminController::class, 'destroy']);

        // ─── Portal: notifications & app info ───
        Route::get('/portal/notifications', [PortalMiscAdminController::class, 'notifications']);
        // Portal Notification templates (per-type title/description/toggle) — config editor.
        Route::get('/portal/notification-config', [PortalMiscAdminController::class, 'notificationConfig']);
        Route::put('/portal/notification-config', [PortalMiscAdminController::class, 'updateNotificationConfig']);
        Route::get('/app-info', [PortalMiscAdminController::class, 'appInfo']);
        Route::put('/app-info', [PortalMiscAdminController::class, 'updateAppInfo']);

        // ─── Content browse ───
        Route::get('/content/history', [ContentBrowseAdminController::class, 'history']);
        Route::get('/content/ai-videos', [ContentBrowseAdminController::class, 'aiVideos']);
        Route::get('/content/events', [ContentBrowseAdminController::class, 'events']);
        Route::get('/content/votings', [ContentVotingsAdminController::class, 'index']);
        Route::post('/content/votings', [ContentVotingsAdminController::class, 'store']);
        Route::put('/content/votings/{id}', [ContentVotingsAdminController::class, 'update']);
        Route::delete('/content/votings/{id}', [ContentVotingsAdminController::class, 'destroy']);
        Route::get('/content/votings/{id}/statistics', [ContentVotingsAdminController::class, 'statistics']);
        Route::get('/content/complaints', [ContentBrowseAdminController::class, 'complaints']);
        Route::get('/content/posts-preview', [ContentBrowseAdminController::class, 'postsPreview']);
        Route::get('/content/admin-activity/{type}', [ContentBrowseAdminController::class, 'adminActivity']);
        Route::post('/content/admin-activity/{type}', [ContentBrowseAdminController::class, 'adminActivityStore']);
        Route::delete('/content/admin-activity/feed/{id}', [ContentBrowseAdminController::class, 'adminActivityDestroy']);
        // Comments + likes for admin-activity posts (dashboard view modal)
        Route::get('/content/admin-activity/feed/{id}/comments', [ContentBrowseAdminController::class, 'adminActivityComments']);
        Route::post('/content/admin-activity/feed/{id}/comments', [ContentBrowseAdminController::class, 'adminActivityAddComment']);
        Route::post('/content/admin-activity/feed/{id}/likes', [ContentBrowseAdminController::class, 'adminActivityFeedLike']);
        Route::delete('/content/admin-activity/comments/{id}', [ContentBrowseAdminController::class, 'adminActivityDeleteComment']);
        Route::post('/content/admin-activity/comments/{id}/likes', [ContentBrowseAdminController::class, 'adminActivityCommentLike']);

        // ─── Content: History (create/edit/delete + thumbnail generation) ───
        Route::post('/content/history/generate-thumbnails', [ContentHistoryAdminController::class, 'generateThumbnails']);
        Route::post('/content/history', [ContentHistoryAdminController::class, 'store']);
        Route::put('/content/history/{id}', [ContentHistoryAdminController::class, 'update']);
        Route::delete('/content/history/{id}', [ContentHistoryAdminController::class, 'destroy']);
        Route::get('/content/history/{id}/comments', [ContentHistoryAdminController::class, 'comments']);
        Route::post('/content/history/{id}/comments', [ContentHistoryAdminController::class, 'addComment']);

        // ─── Content: AI Videos (create/edit/delete + thumbnail generation) ───
        Route::post('/content/ai-videos/generate-thumbnails', [ContentAiVideoAdminController::class, 'generateThumbnails']);
        Route::post('/content/ai-videos', [ContentAiVideoAdminController::class, 'store']);
        Route::put('/content/ai-videos/{id}', [ContentAiVideoAdminController::class, 'update']);
        Route::delete('/content/ai-videos/{id}', [ContentAiVideoAdminController::class, 'destroy']);
        Route::get('/content/ai-videos/{id}/comments', [ContentAiVideoAdminController::class, 'comments']);
        Route::post('/content/ai-videos/{id}/comments', [ContentAiVideoAdminController::class, 'addComment']);

        // ─── Generic file upload to BunnyCDN ───
        Route::post('/files/upload', [AdminFileController::class, 'upload']);

        // ─── Officials ───
        Route::get('/officials/counts', [OfficialsAdminController::class, 'counts']);

        Route::get('/officials/constitutions', [OfficialsAdminController::class, 'constitutionsIndex']);
        Route::post('/officials/constitutions', [OfficialsAdminController::class, 'constitutionsStore']);
        Route::put('/officials/constitutions/{id}', [OfficialsAdminController::class, 'constitutionsUpdate']);
        Route::delete('/officials/constitutions/{id}', [OfficialsAdminController::class, 'constitutionsDestroy']);

        Route::get('/officials/people', [OfficialsAdminController::class, 'peopleIndex']);
        Route::post('/officials/people', [OfficialsAdminController::class, 'peopleStore']);
        Route::put('/officials/people/{id}', [OfficialsAdminController::class, 'peopleUpdate']);
        Route::delete('/officials/people/{id}', [OfficialsAdminController::class, 'peopleDestroy']);

        Route::get('/officials/ministries', [OfficialsAdminController::class, 'ministriesIndex']);
        Route::post('/officials/ministries', [OfficialsAdminController::class, 'ministriesStore']);
        Route::put('/officials/ministries/{id}', [OfficialsAdminController::class, 'ministriesUpdate']);
        Route::delete('/officials/ministries/{id}', [OfficialsAdminController::class, 'ministriesDestroy']);

        Route::get('/officials/civil-laws', [OfficialsAdminController::class, 'civilLawsIndex']);
        Route::post('/officials/civil-laws', [OfficialsAdminController::class, 'civilLawsStore']);
        Route::put('/officials/civil-laws/{id}', [OfficialsAdminController::class, 'civilLawsUpdate']);
        Route::delete('/officials/civil-laws/{id}', [OfficialsAdminController::class, 'civilLawsDestroy']);

        Route::get('/officials/holidays', [OfficialsAdminController::class, 'holidaysIndex']);
        Route::post('/officials/holidays', [OfficialsAdminController::class, 'holidaysStore']);
        Route::put('/officials/holidays/{id}', [OfficialsAdminController::class, 'holidaysUpdate']);
        Route::delete('/officials/holidays/{id}', [OfficialsAdminController::class, 'holidaysDestroy']);

        // Officials CMS content blobs (structure / constitution / civil / holidays / people)
        Route::get('/officials/content/{section}', [OfficialsAdminController::class, 'getContent']);
        Route::put('/officials/content/{section}', [OfficialsAdminController::class, 'saveContent']);

        // ─── Complaints (categories + moderation) ───
        Route::get('/complaint-categories', [ComplaintsAdminController::class, 'categoriesIndex']);
        Route::post('/complaint-categories', [ComplaintsAdminController::class, 'categoriesStore']);
        Route::put('/complaint-categories/{id}', [ComplaintsAdminController::class, 'categoriesUpdate']);
        Route::delete('/complaint-categories/{id}', [ComplaintsAdminController::class, 'categoriesDestroy']);
        Route::get('/complaints', [ComplaintsAdminController::class, 'index']);
        Route::post('/complaints/{id}/status', [ComplaintsAdminController::class, 'updateStatus']);

        // ─── Feeds settings (backgrounds / emojis) ───
        // ─── App Updates (mobile release manager) ───
        Route::get('/app-updates', [AppUpdatesAdminController::class, 'index']);
        Route::post('/app-updates', [AppUpdatesAdminController::class, 'store']);
        Route::put('/app-updates/{id}', [AppUpdatesAdminController::class, 'update']);
        Route::delete('/app-updates/{id}', [AppUpdatesAdminController::class, 'destroy']);
        Route::post('/app-updates/{id}/set-current', [AppUpdatesAdminController::class, 'setCurrent']);
        Route::post('/app-updates/{id}/toggle-force', [AppUpdatesAdminController::class, 'toggleForce']);

        // ─── Maintenance Mode ───
        Route::get('/maintenance', [MaintenanceAdminController::class, 'index']);
        Route::put('/maintenance', [MaintenanceAdminController::class, 'save']);

        // ─── Auto-Logout policy ───
        Route::get('/auto-logout', [AutoLogoutAdminController::class, 'index']);
        Route::put('/auto-logout', [AutoLogoutAdminController::class, 'save']);

        Route::get('/feeds-settings/backgrounds', [FeedsSettingsAdminController::class, 'backgrounds']);
        Route::post('/feeds-settings/backgrounds', [FeedsSettingsAdminController::class, 'storeBackground']);
        Route::put('/feeds-settings/backgrounds/{id}', [FeedsSettingsAdminController::class, 'updateBackground']);
        Route::delete('/feeds-settings/backgrounds/{id}', [FeedsSettingsAdminController::class, 'destroyBackground']);
        Route::get('/feeds-settings/emojis', [FeedsSettingsAdminController::class, 'emojis']);
        Route::post('/feeds-settings/emojis', [FeedsSettingsAdminController::class, 'storeEmoji']);
        Route::delete('/feeds-settings/emojis/{id}', [FeedsSettingsAdminController::class, 'destroyEmoji']);

        // ─── System ───
        Route::get('/system/logs', [SystemAdminController::class, 'logs']);
        Route::delete('/system/logs', [SystemAdminController::class, 'clearLogs']);
        Route::get('/system/health', [SystemAdminController::class, 'health']);
        Route::get('/system/backups', [SystemAdminController::class, 'backups']);
        Route::get('/system/api-status', [SystemAdminController::class, 'apiStatus']);

        // ─── App settings document ───
        Route::get('/settings/document', [SettingsAdminController::class, 'show']);
        Route::put('/settings/document', [SettingsAdminController::class, 'update']);

        // ─── User tier permissions (cultivated / educated / academic) ───
        Route::get('/user-settings/{tier}', [SettingsAdminController::class, 'tierShow']);
        Route::put('/user-settings/{tier}', [SettingsAdminController::class, 'tierUpdate']);

        // ─── Transactions ───
        Route::get('/transactions', [TransactionsAdminController::class, 'index']);
        Route::get('/transactions/stats', [TransactionsAdminController::class, 'stats']);
        Route::get('/transactions/details', [TransactionsAdminController::class, 'details']);

        // ─── Zercash ───
        Route::get('/zercash/overview', [ZercashAdminController::class, 'overview']);
        Route::get('/zercash/settings', [ZercashAdminController::class, 'settings']);
        Route::put('/zercash/settings', [ZercashAdminController::class, 'updateSettings']);
        // Cashback Manager (tab) — list of reward categories with kind/value/min-purchase per row.
        Route::get('/zercash/cashback', [ZercashAdminController::class, 'cashback']);
        Route::put('/zercash/cashback', [ZercashAdminController::class, 'updateCashback']);
        // Zercash Countries (tab) — country availability matrix (Active / Restricted / Pending).
        Route::get('/zercash/countries', [ZercashAdminController::class, 'countries']);
        Route::put('/zercash/countries', [ZercashAdminController::class, 'updateCountries']);

        // ── WebApp CMS (yekbun.app content) ──
        // Pages / texts: page-id-scoped key→value editor rows.
        Route::get('/cms/pages', [AdminCmsController::class, 'pages']);
        Route::put('/cms/pages/{id}', [AdminCmsController::class, 'updatePage']);
        // Translations: per-key per-language override map.
        Route::get('/cms/translations', [AdminCmsController::class, 'translations']);
        Route::put('/cms/translations', [AdminCmsController::class, 'updateTranslations']);
        // Media slots: named asset URLs (banner, logo, promo, …) replaceable via upload.
        Route::get('/cms/media', [AdminCmsController::class, 'media']);
        Route::post('/cms/media/upload', [AdminCmsController::class, 'uploadMedia']);
        Route::delete('/cms/media/{slot}', [AdminCmsController::class, 'deleteMedia']);
        // Aggregated list of pending KYC / wallet / transaction requests — feeds the navbar dropdown.
        Route::get('/zercash/pending-requests', [ZercashAdminController::class, 'pendingRequests']);

        // ─── Logipay / Wallet Users ───
        Route::get('/logipay/stats', [LogipayAdminController::class, 'stats']);
        Route::get('/logipay/new-requests', [LogipayAdminController::class, 'newRequests']);
        Route::get('/logipay/active-wallets', [LogipayAdminController::class, 'activeWallets']);
        Route::get('/logipay/closed-wallets', [LogipayAdminController::class, 'closedWallets']);
        Route::get('/logipay/request/{userId}', [LogipayAdminController::class, 'showRequest']);
        Route::post('/logipay/request/{userId}/approve', [LogipayAdminController::class, 'approveRequest']);
        Route::post('/logipay/request/{userId}/reject', [LogipayAdminController::class, 'rejectRequest']);

        // ─── Zercash Products & Sale Managers ───
        Route::get('/products', [ProductsAdminController::class, 'index']);
        Route::post('/products', [ProductsAdminController::class, 'storeProduct']);
        Route::put('/products/{id}', [ProductsAdminController::class, 'updateProduct']);
        Route::delete('/products/{id}', [ProductsAdminController::class, 'destroyProduct']);
        // Image upload — used by the admin product modals to convert local cover/icon files
        // to short CDN URLs before submitting the product create/update payload.
        Route::post('/products/upload-image', [ProductsAdminController::class, 'uploadImage']);
        Route::post('/sale-managers', [ProductsAdminController::class, 'storeSaleManager']);
        Route::put('/sale-managers/{id}', [ProductsAdminController::class, 'updateSaleManager']);
        Route::delete('/sale-managers/{id}', [ProductsAdminController::class, 'destroySaleManager']);
    });
});

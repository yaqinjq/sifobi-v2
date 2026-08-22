<?php

use App\Http\Controllers\Admin\CoreHealthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Debug\DatabaseDebugController;
use App\Http\Controllers\MasterData\ImportExportController;
use App\Http\Controllers\MasterData\ItemAliasController;
use App\Http\Controllers\MasterData\ItemController;
use App\Http\Controllers\MasterData\ItemConversionController;
use App\Http\Controllers\MasterData\UnitController;
use App\Http\Controllers\Operations\OpenStockImportController;
use App\Http\Controllers\Operations\OpenStockController;
use App\Http\Controllers\Operations\OpnameController;
use App\Http\Controllers\Operations\SpoilWasteController;
use App\Http\Controllers\Receiving\GoodsReceiptController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Production\MenuController;
use App\Http\Controllers\Production\RecipeController;
use App\Http\Controllers\Settings\AppSettingController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\BrandController;
use App\Http\Controllers\Settings\CalendarEventController;
use App\Http\Controllers\Settings\DefaultConversionController;
use App\Http\Controllers\Settings\DepartmentController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\WiproCatalogController;
use App\Http\Controllers\Settings\ItemCategoryController;
use App\Http\Controllers\Settings\ItemJenisController;
use App\Http\Controllers\Settings\OutletController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\StockConfigController;
use App\Http\Controllers\Settings\SupplierController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Stock\SmartOrderController;
use App\Http\Controllers\Stock\StockBalanceController;
use App\Http\Controllers\Stock\StockTransferController;
use Illuminate\Support\Facades\Route;

// PWA — tidak butuh auth
Route::get('/manifest.json', function () {
    $setting = \App\Models\AppSetting::current();
    $name    = $setting?->app_name ?? config('app.name', 'SIFOBI');
    $color   = $setting?->primary_color ?: '#1B4332';

    $icons = [
        ['src' => '/icons/icon-192.png',         'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/icons/icon-512.png',         'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/icons/icon-maskable-512.png','sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ];

    if ($setting?->logo_path) {
        $logoMime = match (strtolower(pathinfo($setting->logo_path, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };

        array_unshift($icons, [
            'src' => \Illuminate\Support\Facades\Storage::url($setting->logo_path),
            'sizes' => 'any',
            'type' => $logoMime,
            'purpose' => 'any',
        ]);
    }

    return response()->json([
        'name'             => $name,
        'short_name'       => $name,
        'description'      => $setting?->app_tagline ?? 'Food & Beverage Inventory System',
        'start_url'        => '/dashboard',
        'scope'            => '/',
        'display'          => 'standalone',
        'orientation'      => 'portrait-primary',
        'background_color' => $color,
        'theme_color'      => $color,
        'lang'             => 'id',
        'categories'       => ['productivity', 'business'],
        'icons'            => $icons,
        'screenshots'      => [],
    ])->header('Content-Type', 'application/manifest+json')
      ->header('Cache-Control', 'public, max-age=3600');
})->name('pwa.manifest');

Route::get('/offline', function () {
    return view('pwa.offline');
})->name('pwa.offline');

// Inbound webhook dari Wipro — auth sendiri lewat bearer token, bukan session.
Route::post('/api/wipro/dispatch-notification', [\App\Http\Controllers\Api\WiproWebhookController::class, 'dispatchNotification'])
    ->name('api.wipro.dispatch-notification');

// Inbound outlet-list pull dari Wipro — auth sendiri lewat bearer token, bukan session.
Route::get('/api/outlets', [\App\Http\Controllers\Api\WiproOutletController::class, 'index'])
    ->name('api.wipro.outlets');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

if (config('app.debug')) {
    Route::get('/test-db', DatabaseDebugController::class)->name('debug.test-db');
}

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::redirect('/register', '/login')->name('register');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::middleware(['auth', \App\Http\Middleware\SetPermissionsTeam::class])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/changelog', fn () => view('changelog'))->name('changelog');
    Route::get('/tutorial', fn () => view('tutorial'))->name('tutorial');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}/open', [\App\Http\Controllers\NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

    Route::get('/admin/core', CoreHealthController::class)->middleware('permission:manage_core')->name('admin.core');

    Route::prefix('admin/tenants')
        ->name('admin.tenants.')
        ->middleware('permission:manage_tenants')
        ->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Admin\TenantController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Admin\TenantController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Admin\TenantController::class, 'store'])->name('store');
            Route::put('/{tenant}', [\App\Http\Controllers\Admin\TenantController::class, 'update'])->name('update');
        });

    Route::prefix('settings')
        ->name('settings.')
        ->middleware('permission:manage_settings')
        ->group(function (): void {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::get('app', [AppSettingController::class, 'edit'])->name('app');
            Route::post('app', [AppSettingController::class, 'update'])->name('app.update');
            Route::post('app/test-smtp', [AppSettingController::class, 'testSmtp'])->name('app.test-smtp');
            Route::resource('item-jenises', ItemJenisController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['item-jenises' => 'itemJenis'])
                ->names('item-jenises');
            Route::resource('item-categories', ItemCategoryController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['item-categories' => 'itemCategory'])
                ->names('item-categories');
            Route::get('default-conversions', [DefaultConversionController::class, 'index'])->name('default-conversions.index');
            Route::post('default-conversions', [DefaultConversionController::class, 'store'])->name('default-conversions.store');
            Route::delete('default-conversions/{id}', [DefaultConversionController::class, 'destroy'])->name('default-conversions.destroy');
            Route::post('default-conversions/preset/{type}', [DefaultConversionController::class, 'loadPreset'])->name('default-conversions.preset');
            Route::resource('departments', DepartmentController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->names('departments');
            Route::resource('suppliers', SupplierController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->names('suppliers');

            Route::middleware('permission:manage_users')->group(function (): void {
                Route::resource('users', UserController::class)
                    ->only(['index', 'create', 'store', 'edit', 'update'])
                    ->names('users');
                Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
                    ->name('users.toggle-status');
                Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
                    ->name('users.reset-password');

                Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
                Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
                Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
                Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
                Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
            });

            Route::middleware('permission:view_audit_log')->group(function (): void {
                Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
                Route::get('audit-log/{auditLog}', [AuditLogController::class, 'show'])->name('audit-log.show');
            });

            Route::middleware('permission:manage_brands_outlets')->group(function (): void {
                Route::resource('brands', BrandController::class)
                    ->except(['show'])
                    ->names('brands');
                Route::resource('outlets', OutletController::class)
                    ->except(['show', 'destroy'])
                    ->names('outlets');
            });

            Route::middleware('permission:manage_integrations')->group(function (): void {
                Route::resource('integrations', IntegrationController::class)
                    ->only(['index', 'store', 'update', 'destroy'])
                    ->names('integrations');
                Route::post('integrations/{integration}/test', [IntegrationController::class, 'testConnection'])
                    ->name('integrations.test');
                Route::post('integrations/{integration}/sync-outlets', [IntegrationController::class, 'syncOutlets'])
                    ->name('integrations.sync-outlets');

                Route::get('wipro-catalog', [WiproCatalogController::class, 'index'])
                    ->name('wipro-catalog.index');
                Route::post('wipro-catalog/import', [WiproCatalogController::class, 'import'])
                    ->name('wipro-catalog.import');
            });
        });

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::resource('stock-configs', StockConfigController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:manage_stock_configs')
            ->names('stock-configs');

        Route::resource('calendar-events', CalendarEventController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:manage_calendar_events')
            ->names('calendar-events');
    });

    Route::get('api/stock-suggestion', [SmartOrderController::class, 'suggest'])
        ->name('api.stock-suggestion');

    Route::get('api/items/search-for-po', \App\Http\Controllers\Api\PoItemSearchController::class)
        ->name('api.items.search-for-po');

    Route::get('api/items/po-catalog', \App\Http\Controllers\Api\PoCatalogBrowseController::class)
        ->name('api.items.po-catalog');

    Route::middleware('permission:manage_units')->group(function (): void {
        Route::resource('master-data/units', UnitController::class)
            ->except(['index', 'show'])
            ->names('master-data.units');
    });

    Route::middleware('permission:manage_items')->group(function (): void {
        Route::post('master-data/items/{item}/conversions', [ItemConversionController::class, 'store'])
            ->name('master-data.items.conversions.store');
        Route::delete('master-data/items/{item}/conversions/{conversion}', [ItemConversionController::class, 'destroy'])
            ->name('master-data.items.conversions.destroy');
        Route::post('master-data/items/{item}/aliases', [ItemAliasController::class, 'store'])
            ->name('master-data.items.aliases.store');
        Route::delete('master-data/items/{item}/aliases/{alias}', [ItemAliasController::class, 'destroy'])
            ->name('master-data.items.aliases.destroy');
        Route::patch('master-data/items/{item}/toggle-active', [ItemController::class, 'toggleStatus'])
            ->name('master-data.items.toggle-active');

        Route::resource('master-data/items', ItemController::class)
            ->except(['index', 'show'])
            ->names('master-data.items');
    });

    Route::middleware('permission:view_master_data')->group(function (): void {
        Route::resource('master-data/units', UnitController::class)
            ->only(['index'])
            ->names('master-data.units');

        Route::resource('master-data/items', ItemController::class)
            ->only(['index', 'show'])
            ->names('master-data.items');
    });

    Route::prefix('master-data/import-export')
        ->name('master-data.ie.')
        ->middleware('permission:export_master_data')
        ->group(function (): void {
            Route::get('/', [ImportExportController::class, 'index'])->name('index');

            Route::get('export/items', [ImportExportController::class, 'exportItems'])->name('export.items');
            Route::get('export/units', [ImportExportController::class, 'exportUnits'])->name('export.units');
            Route::get('export/conversions', [ImportExportController::class, 'exportConversions'])->name('export.conversions');
            Route::get('export/item-outlets', [ImportExportController::class, 'exportItemOutlets'])->name('export.item-outlets');
            Route::get('export/stock-configs', [ImportExportController::class, 'exportStockConfigs'])->name('export.stock-configs');
            Route::get('export/po-tags', [ImportExportController::class, 'exportPoTags'])->name('export.po-tags');

            Route::get('template/items', [ImportExportController::class, 'templateItems'])->name('template.items');
            Route::get('template/units', [ImportExportController::class, 'templateUnits'])->name('template.units');
            Route::get('template/conversions', [ImportExportController::class, 'templateConversions'])->name('template.conversions');
        });

    Route::prefix('master-data/import-export')
        ->name('master-data.ie.')
        ->middleware('permission:import_master_data')
        ->group(function (): void {
            Route::post('import/items', [ImportExportController::class, 'importItems'])->name('import.items');
            Route::post('import/units', [ImportExportController::class, 'importUnits'])->name('import.units');
            Route::post('import/conversions', [ImportExportController::class, 'importConversions'])->name('import.conversions');
            Route::post('import/po-tags/preview', [ImportExportController::class, 'previewPoTags'])->name('import.po-tags.preview');
            Route::post('import/po-tags/apply', [ImportExportController::class, 'applyPoTags'])->name('import.po-tags.apply');
        });

    Route::prefix('receiving')
        ->name('receiving.')
        ->middleware('permission:view_goods_receipt')
        ->group(function (): void {
            Route::get('goods-receipts', [GoodsReceiptController::class, 'index'])
                ->name('goods-receipts.index');
            Route::get('goods-receipts/create', [GoodsReceiptController::class, 'create'])
                ->middleware('permission:create_goods_receipt')
                ->name('goods-receipts.create');
            Route::post('goods-receipts', [GoodsReceiptController::class, 'store'])
                ->middleware('permission:create_goods_receipt')
                ->name('goods-receipts.store');
            Route::get('goods-receipts/{receipt}', [GoodsReceiptController::class, 'show'])
                ->name('goods-receipts.show');
            Route::get('goods-receipts/{receipt}/edit', [GoodsReceiptController::class, 'edit'])
                ->middleware('permission:create_goods_receipt')
                ->name('goods-receipts.edit');
            Route::put('goods-receipts/{receipt}', [GoodsReceiptController::class, 'update'])
                ->middleware('permission:create_goods_receipt')
                ->name('goods-receipts.update');
            Route::delete('goods-receipts/{receipt}', [GoodsReceiptController::class, 'destroy'])
                ->middleware('permission:create_goods_receipt')
                ->name('goods-receipts.destroy');
            Route::post('goods-receipts/{receipt}/submit', [GoodsReceiptController::class, 'submit'])
                ->middleware('permission:submit_goods_receipt')
                ->name('goods-receipts.submit');
            Route::post('goods-receipts/{receipt}/approve', [GoodsReceiptController::class, 'approve'])
                ->middleware('permission:approve_goods_receipt')
                ->name('goods-receipts.approve');
            Route::post('goods-receipts/{receipt}/reject', [GoodsReceiptController::class, 'reject'])
                ->middleware('permission:reject_goods_receipt')
                ->name('goods-receipts.reject');
        });

    Route::prefix('stock')
        ->name('stock.')
        ->middleware('permission:view_stock_balance')
        ->group(function (): void {
            Route::get('balance', [StockBalanceController::class, 'index'])
                ->name('balance.index');
            Route::get('balance/{item}', [StockBalanceController::class, 'show'])
                ->name('balance.show');
        });

    Route::prefix('laporan')
        ->name('laporan.')
        ->middleware('permission:view_reports')
        ->group(function (): void {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('mutasi', [ReportController::class, 'mutationReport'])->name('mutasi');
            Route::get('spoil', [ReportController::class, 'spoilReport'])->name('spoil');
            Route::get('penerimaan', [ReportController::class, 'receivingReport'])->name('penerimaan');
            Route::get('stok-summary', [ReportController::class, 'stockSummary'])->name('stok-summary');
            Route::get('stok-menipis', [ReportController::class, 'stokMenipis'])->name('stok-menipis');
            Route::get('mutasi/export', [ReportController::class, 'exportMutasi'])->name('mutasi.export');
            Route::get('spoil/export', [ReportController::class, 'exportSpoil'])->name('spoil.export');
            Route::get('penerimaan/export', [ReportController::class, 'exportPenerimaan'])->name('penerimaan.export');
            Route::get('hpp', [\App\Http\Controllers\Reports\HppReportController::class, 'index'])->name('hpp');
            Route::get('hpp/export', [\App\Http\Controllers\Reports\HppReportController::class, 'export'])->name('hpp.export');
            Route::get('stok-summary/export', [ReportController::class, 'exportStokSummary'])->name('stok-summary.export');
            Route::get('stok-menipis/export', [ReportController::class, 'exportStokMenipis'])->name('stok-menipis.export');
        });

    Route::prefix('operations')->name('operations.')->group(function (): void {
        Route::get('/spoil-wastes/search-items', [SpoilWasteController::class, 'searchItems'])
            ->middleware('permission:record_spoil')
            ->name('spoil-wastes.search-items');

        Route::get('/spoil-wastes', [SpoilWasteController::class, 'index'])
            ->middleware('permission:record_spoil')
            ->name('spoil-wastes.index');
        Route::get('/spoil-wastes/create', [SpoilWasteController::class, 'create'])
            ->middleware('permission:record_spoil')
            ->name('spoil-wastes.create');
        Route::post('/spoil-wastes', [SpoilWasteController::class, 'store'])
            ->middleware('permission:record_spoil')
            ->name('spoil-wastes.store');
        Route::get('/spoil-wastes/{spoil}', [SpoilWasteController::class, 'show'])
            ->middleware('permission:record_spoil')
            ->name('spoil-wastes.show');
        Route::post('/spoil-wastes/{spoil}/approve', [SpoilWasteController::class, 'approve'])
            ->middleware('permission:approve_spoil')
            ->name('spoil-wastes.approve');
        Route::post('/spoil-wastes/{spoil}/reject', [SpoilWasteController::class, 'reject'])
            ->middleware('permission:approve_spoil')
            ->name('spoil-wastes.reject');

        Route::prefix('opname')->name('opname.')->group(function (): void {
            Route::get('/', [OpnameController::class, 'index'])
                ->middleware('permission:input_opname')
                ->name('index');
            Route::get('/create', [OpnameController::class, 'create'])
                ->middleware('permission:input_opname')
                ->name('create');
            Route::post('/', [OpnameController::class, 'store'])
                ->middleware('permission:input_opname')
                ->name('store');
            Route::get('/{session}', [OpnameController::class, 'show'])
                ->middleware('permission:input_opname')
                ->name('show');
            Route::patch('/{session}/items/{item}', [OpnameController::class, 'updateItem'])
                ->middleware('permission:input_opname')
                ->name('update-item');
            Route::post('/{session}/submit', [OpnameController::class, 'submit'])
                ->middleware('permission:input_opname')
                ->name('submit');
            Route::post('/{session}/approve', [OpnameController::class, 'approve'])
                ->middleware('permission:approve_opname')
                ->name('approve');
        });

        Route::prefix('stock-transfers')
            ->name('stock-transfers.')
            ->group(function (): void {
                Route::get('/', [StockTransferController::class, 'index'])
                    ->middleware('permission:create_stock_transfers')
                    ->name('index');
                Route::get('/create', [StockTransferController::class, 'create'])
                    ->middleware('permission:create_stock_transfers')
                    ->name('create');
                Route::post('/', [StockTransferController::class, 'store'])
                    ->middleware('permission:create_stock_transfers')
                    ->name('store');
                Route::get('/{transfer}', [StockTransferController::class, 'show'])
                    ->middleware('permission:create_stock_transfers')
                    ->name('show');
                Route::post('/{transfer}/submit', [StockTransferController::class, 'submit'])
                    ->middleware('permission:create_stock_transfers')
                    ->name('submit');
                Route::post('/{transfer}/approve', [StockTransferController::class, 'approve'])
                    ->middleware('permission:approve_stock_transfers')
                    ->name('approve');
                Route::post('/{transfer}/reject', [StockTransferController::class, 'reject'])
                    ->middleware('permission:approve_stock_transfers')
                    ->name('reject');
                Route::post('/{transfer}/void', [StockTransferController::class, 'void'])
                    ->middleware('permission:approve_stock_transfers')
                    ->name('void');
            });

        // Item search API; static segment must come before {openStock} parameter.
        Route::get('/open-stocks/item-search', [OpenStockController::class, 'itemSearch'])
            ->name('open-stocks.item-search');

        Route::get('/open-stocks/import', [OpenStockImportController::class, 'showImport'])
            ->middleware('permission:input_open_stock')
            ->name('open-stocks.import');

        Route::get('/open-stocks/import/template', [OpenStockImportController::class, 'template'])
            ->middleware('permission:input_open_stock')
            ->name('open-stocks.import.template');

        Route::post('/open-stocks/import', [OpenStockImportController::class, 'import'])
            ->middleware('permission:input_open_stock')
            ->name('open-stocks.import.store');

        Route::get('/open-stocks', [OpenStockController::class, 'index'])->name('open-stocks.index');

        Route::get('/open-stocks/create', [OpenStockController::class, 'create'])
            ->middleware('permission:input_open_stock')
            ->name('open-stocks.create');

        Route::post('/open-stocks', [OpenStockController::class, 'store'])
            ->middleware('permission:input_open_stock')
            ->name('open-stocks.store');

        Route::get('/open-stocks/{openStock}', [OpenStockController::class, 'show'])
            ->name('open-stocks.show');

        Route::get('/open-stocks/{openStock}/edit', [OpenStockController::class, 'edit'])
            ->middleware('permission:input_open_stock')
            ->name('open-stocks.edit');

        Route::put('/open-stocks/{openStock}', [OpenStockController::class, 'update'])
            ->middleware('permission:input_open_stock')
            ->name('open-stocks.update');

        Route::delete('/open-stocks/{openStock}', [OpenStockController::class, 'destroy'])
            ->middleware('permission:input_open_stock')
            ->name('open-stocks.destroy');

        Route::post('/open-stocks/{openStock}/post', [OpenStockController::class, 'post'])
            ->middleware('permission:post_open_stock')
            ->name('open-stocks.post');

        Route::post('/open-stocks/{openStock}/void', [OpenStockController::class, 'void'])
            ->middleware('permission:post_open_stock')
            ->name('open-stocks.void');
    });

    // ── Purchase Order ────────────────────────────────────────────────────
    Route::prefix('procurement/purchase-orders')
        ->name('procurement.purchase-orders.')
        ->middleware('permission:create_po')
        ->group(function (): void {
            Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');

            Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
            Route::post('/store-batch', [PurchaseOrderController::class, 'storeBatch'])->name('store-batch');
            Route::get('/batch-summary', [PurchaseOrderController::class, 'batchSummary'])->name('batch-summary');
            Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');

            Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');

            Route::post('/{purchaseOrder}/items', [PurchaseOrderController::class, 'addItem'])->name('items.store');
            Route::patch('/{purchaseOrder}/items/{item}', [PurchaseOrderController::class, 'updateItem'])->name('items.update');
            Route::delete('/{purchaseOrder}/items/{item}', [PurchaseOrderController::class, 'removeItem'])->name('items.destroy');

            Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('submit');

            Route::post('/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
                ->middleware('permission:approve_po')
                ->name('approve');

            Route::post('/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])
                ->middleware('permission:approve_po')
                ->name('reject');

            Route::post('/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])
                ->middleware('permission:approve_po')
                ->name('send');

            Route::post('/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])
                ->middleware('permission:approve_po')
                ->name('close');

            Route::post('/{purchaseOrder}/resend', [PurchaseOrderController::class, 'resend'])
                ->middleware('permission:approve_po')
                ->name('resend');
        });

    // ── Menu & Resep (R&D + HPP) ─────────────────────────────────────────
    Route::prefix('production')
        ->name('production.')
        ->middleware('permission:manage_recipes')
        ->group(function (): void {
            Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
            Route::get('/menus/create', [MenuController::class, 'create'])->name('menus.create');
            Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
            Route::get('/menus/{menu}', [MenuController::class, 'show'])->name('menus.show');
            Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');

            Route::get('/menus/{menu}/recipes/create', [RecipeController::class, 'create'])->name('recipes.create');
            Route::post('/menus/{menu}/recipes', [RecipeController::class, 'store'])->name('recipes.store');

            Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
            Route::get('/recipes/{recipe}/edit', [RecipeController::class, 'edit'])->name('recipes.edit');
            Route::put('/recipes/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
            Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy'])->name('recipes.destroy');
            Route::post('/recipes/{recipe}/submit', [RecipeController::class, 'submit'])->name('recipes.submit');

            Route::post('/recipes/{recipe}/approve', [RecipeController::class, 'approve'])
                ->middleware('permission:approve_recipes')
                ->name('recipes.approve');

            Route::post('/recipes/{recipe}/reject', [RecipeController::class, 'reject'])
                ->middleware('permission:approve_recipes')
                ->name('recipes.reject');

            // ── Kalkulator HPP berdiri sendiri — coba-coba sebelum resmi jadi resep ──
            Route::get('/hpp-calculator', [\App\Http\Controllers\Production\HppCalculatorController::class, 'index'])->name('hpp-calculator.index');
            Route::post('/hpp-calculator', [\App\Http\Controllers\Production\HppCalculatorController::class, 'store'])->name('hpp-calculator.store');
            Route::get('/hpp-calculator/{hppCalculation}', [\App\Http\Controllers\Production\HppCalculatorController::class, 'show'])->name('hpp-calculator.show');
            Route::delete('/hpp-calculator/{hppCalculation}', [\App\Http\Controllers\Production\HppCalculatorController::class, 'destroy'])->name('hpp-calculator.destroy');
        });

    // ── POS + Layout Tempat Usaha ────────────────────────────────────────
    Route::prefix('pos')
        ->name('pos.')
        ->group(function (): void {
            Route::middleware('permission:operate_pos|manage_pos_layout|view_pos_reports')->group(function (): void {
                Route::get('layout', [\App\Http\Controllers\Pos\PosAreaController::class, 'index'])->name('layout.index');
            });

            Route::middleware('permission:manage_pos_layout')->group(function (): void {
                Route::post('areas', [\App\Http\Controllers\Pos\PosAreaController::class, 'store'])->name('areas.store');
                Route::put('areas/{area}', [\App\Http\Controllers\Pos\PosAreaController::class, 'update'])->name('areas.update');
                Route::delete('areas/{area}', [\App\Http\Controllers\Pos\PosAreaController::class, 'destroy'])->name('areas.destroy');

                Route::post('tables', [\App\Http\Controllers\Pos\PosTableController::class, 'store'])->name('tables.store');
                Route::put('tables/{table}', [\App\Http\Controllers\Pos\PosTableController::class, 'update'])->name('tables.update');
                Route::delete('tables/{table}', [\App\Http\Controllers\Pos\PosTableController::class, 'destroy'])->name('tables.destroy');
                Route::patch('tables/{table}/position', [\App\Http\Controllers\Pos\PosTableController::class, 'updatePosition'])->name('tables.position');
            });

            Route::middleware('permission:operate_pos')->group(function (): void {
                Route::get('orders', [\App\Http\Controllers\Pos\PosOrderController::class, 'index'])->name('orders.index');
                Route::get('orders/create', [\App\Http\Controllers\Pos\PosOrderController::class, 'create'])->name('orders.create');
                Route::post('orders', [\App\Http\Controllers\Pos\PosOrderController::class, 'store'])->name('orders.store');
                Route::get('orders/{order}', [\App\Http\Controllers\Pos\PosOrderController::class, 'show'])->name('orders.show');
                Route::post('orders/{order}/items', [\App\Http\Controllers\Pos\PosOrderController::class, 'addItem'])->name('orders.items.store');
                Route::delete('orders/{order}/items/{item}', [\App\Http\Controllers\Pos\PosOrderController::class, 'removeItem'])->name('orders.items.destroy');
                Route::post('orders/{order}/checkout', [\App\Http\Controllers\Pos\PosOrderController::class, 'checkout'])->name('orders.checkout');
                Route::post('orders/{order}/pay', [\App\Http\Controllers\Pos\PosOrderController::class, 'pay'])->name('orders.pay');
                Route::get('orders/{order}/receipt', [\App\Http\Controllers\Pos\PosOrderController::class, 'receipt'])->name('orders.receipt');
            });

            Route::post('orders/{order}/void', [\App\Http\Controllers\Pos\PosOrderController::class, 'void'])
                ->middleware('permission:void_pos_order')
                ->name('orders.void');

            Route::middleware('permission:view_pos_reports')->group(function (): void {
                Route::get('reports', [\App\Http\Controllers\Pos\PosReportController::class, 'index'])->name('reports.index');
            });

            Route::middleware('permission:operate_pos')->group(function (): void {
                Route::get('shifts', [\App\Http\Controllers\Pos\PosShiftController::class, 'index'])->name('shifts.index');
                Route::post('shifts', [\App\Http\Controllers\Pos\PosShiftController::class, 'store'])->name('shifts.store');
                Route::get('shifts/{shift}', [\App\Http\Controllers\Pos\PosShiftController::class, 'show'])->name('shifts.show');
                Route::post('shifts/{shift}/close', [\App\Http\Controllers\Pos\PosShiftController::class, 'close'])->name('shifts.close');
            });

            Route::post('shifts/{shift}/reconcile', [\App\Http\Controllers\Pos\PosShiftController::class, 'reconcile'])
                ->middleware('permission:approve_pos_shift')
                ->name('shifts.reconcile');
        });
});

<?php

use App\Http\Controllers\Admin\CoreHealthController;
use App\Http\Controllers\Auth\LoginController;
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
use App\Http\Controllers\Settings\AppSettingController;
use App\Http\Controllers\Settings\BrandController;
use App\Http\Controllers\Settings\CalendarEventController;
use App\Http\Controllers\Settings\DepartmentController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\ItemCategoryController;
use App\Http\Controllers\Settings\ItemJenisController;
use App\Http\Controllers\Settings\OutletController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\StockConfigController;
use App\Http\Controllers\Settings\SupplierController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Stock\SmartOrderController;
use App\Http\Controllers\Stock\StockBalanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    $mode = config('app.landing_mode', 'saas');
    $mode = in_array($mode, ['saas', 'mko'], true) ? $mode : 'saas';

    return view("landing.{$mode}");
})->name('home')->middleware('guest');

if (config('app.debug')) {
    Route::get('/test-db', DatabaseDebugController::class)->name('debug.test-db');
}

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::redirect('/register', '/login')->name('register');
    Route::redirect('/forgot-password', '/login')->name('password.request');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/admin/core', CoreHealthController::class)->middleware('permission:manage_core')->name('admin.core');

    Route::prefix('settings')
        ->name('settings.')
        ->middleware('permission:manage_settings')
        ->group(function (): void {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::get('app', [AppSettingController::class, 'edit'])->name('app');
            Route::post('app', [AppSettingController::class, 'update'])->name('app.update');
            Route::resource('item-jenises', ItemJenisController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['item-jenises' => 'itemJenis'])
                ->names('item-jenises');
            Route::resource('item-categories', ItemCategoryController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['item-categories' => 'itemCategory'])
                ->names('item-categories');
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
            Route::get('mutasi/export', [ReportController::class, 'exportMutasi'])->name('mutasi.export');
            Route::get('spoil/export', [ReportController::class, 'exportSpoil'])->name('spoil.export');
            Route::get('penerimaan/export', [ReportController::class, 'exportPenerimaan'])->name('penerimaan.export');
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
});

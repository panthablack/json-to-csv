<?php

use App\Http\Controllers\CsvConfigController;
use App\Http\Controllers\CsvExportController;
use App\Http\Controllers\JsonProcessorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/json-upload', function () {
        return Inertia::render('JsonUpload');
    })->name('json.upload.page');

    Route::get('/csv-config', function (Request $request) {
        // Redirect old query param usage to new nested route
        if ($request->has('json_data_id')) {
            return redirect("/json-data/{$request->json_data_id}/csv-config");
        }

        // Show general CSV config page (for cases without specific JSON data)
        return Inertia::render('CsvConfiguration');
    })->name('csv.config.page');

    Route::get('/json-data/{jsonData}/csv-config', function ($jsonDataId) {
        return Inertia::render('CsvConfiguration', ['json_data_id' => $jsonDataId]);
    })->name('csv.config.page.with.json')->where('jsonData', '[0-9]+');

    Route::get('/export', function () {
        return Inertia::render('Export');
    })->name('export.page');
});

Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    Route::resource('json', JsonProcessorController::class)->except(['create', 'edit']);
    Route::post('json/{jsonData}/analyze', [JsonProcessorController::class, 'analyze'])->name('json.analyze');
    Route::get('json/{jsonData}/suggest', [CsvConfigController::class, 'suggest'])->name('json.suggest');

    // Nested CSV config routes under specific JSON data
    Route::prefix('json-data/{jsonData}')->where(['jsonData' => '[0-9]+'])->group(function () {
        Route::get('csv-config', [CsvConfigController::class, 'indexForJson'])->name('json.csv.config.index');
        Route::post('csv-config', [CsvConfigController::class, 'storeForJson'])->name('json.csv.config.store');
        Route::post('csv-config/preview', [CsvConfigController::class, 'previewForJson'])->name('json.csv.config.preview');
    });

    // Individual CSV config management routes (for existing configs)
    Route::resource('csv-config', CsvConfigController::class)->except(['create', 'edit']);
    Route::post('csv-config/preview', [CsvConfigController::class, 'preview'])->name('csv.config.preview');
    Route::get('csv-config/{csvConfiguration}/analyze', [CsvConfigController::class, 'analyze'])->name('csv.config.analyze');
    Route::post('csv-config/{csvConfiguration}/duplicate', [CsvConfigController::class, 'duplicate'])->name('csv.config.duplicate');

    Route::get('export/single/{csvConfiguration}', [CsvExportController::class, 'exportSingle'])->name('export.single');
    Route::post('export/multiple', [CsvExportController::class, 'exportMultiple'])->name('export.multiple');
    Route::post('export/quick', [CsvExportController::class, 'quickExport'])->name('export.quick');
    Route::post('export/store', [CsvExportController::class, 'generateAndStore'])->name('export.store');
    Route::get('export/list', [CsvExportController::class, 'listExports'])->name('export.list');
    Route::delete('export/{filename}', [CsvExportController::class, 'deleteExport'])->name('export.delete');
    Route::delete('export/bulk', [CsvExportController::class, 'bulkDelete'])->name('export.bulk.delete');
});

Route::get('download/{filename}', [CsvExportController::class, 'download'])
    ->middleware(['auth', 'verified'])
    ->name('export.download');

Route::get('csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token()]);
})->name('csrf.token');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

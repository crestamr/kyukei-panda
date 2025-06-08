<?php

use App\Http\Controllers\Api\ActivityTrackingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Health Check Routes
Route::get('/health', [HealthController::class, 'check'])->name('api.health.check');
Route::get('/ping', [HealthController::class, 'ping'])->name('api.health.ping');

// Authentication Routes
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('token', [AuthController::class, 'generateToken'])->name('token.generate');
    Route::post('token/doc', [AuthController::class, 'generateDocToken'])->name('token.doc');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('validate', [AuthController::class, 'validateToken'])->name('token.validate');
        Route::get('tokens', [AuthController::class, 'listTokens'])->name('tokens.list');
        Route::delete('tokens/{token_id}', [AuthController::class, 'revokeToken'])->name('tokens.revoke');
    });
});

// Kyukei-Panda API Routes
Route::prefix('kyukei-panda')->name('api.kyukei-panda.')->middleware(['throttle:api', 'api.security'])->group(function () {
    // Public endpoints (no auth required)
    Route::post('activities', [ActivityTrackingController::class, 'recordActivity'])->name('activities.record');
    Route::get('status', [ActivityTrackingController::class, 'getStatus'])->name('status');
    Route::get('suggestions', [ActivityTrackingController::class, 'getProductivitySuggestions'])->name('suggestions');

    // Authenticated endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('activities/assign-project', [ActivityTrackingController::class, 'assignProject'])->name('activities.assign-project');
    });
});

// Advanced AI API Routes
Route::prefix('ai')->name('api.ai.')->middleware(['auth:sanctum', 'throttle:ai'])->group(function () {
    Route::post('neural-network/predict-productivity', [AdvancedAIController::class, 'predictProductivityWithNeuralNetwork'])->name('neural.predict');
    Route::post('nlp/generate-insights', [AdvancedAIController::class, 'generateNaturalLanguageInsights'])->name('nlp.insights');
    Route::post('computer-vision/analyze-workspace', [AdvancedAIController::class, 'analyzeWorkspaceFromScreenshots'])->name('cv.workspace');
    Route::post('gnn/predict-team-dynamics', [AdvancedAIController::class, 'predictTeamDynamicsWithGNN'])->name('gnn.team');
    Route::post('reinforcement-learning/optimize-breaks', [AdvancedAIController::class, 'optimizeBreakSchedulingWithRL'])->name('rl.breaks');
    Route::post('autoencoder/detect-anomalies', [AdvancedAIController::class, 'detectAnomaliesWithAutoencoder'])->name('ae.anomalies');
    Route::post('sentiment/analyze-communication', [AdvancedAIController::class, 'analyzeTeamCommunicationSentiment'])->name('sentiment.communication');
    Route::post('predict-project-success', [AdvancedAIController::class, 'predictProjectSuccess'])->name('project.success');
});

// Blockchain API Routes
Route::prefix('blockchain')->name('api.blockchain.')->middleware(['auth:sanctum', 'throttle:blockchain'])->group(function () {
    Route::post('nft/create-achievement', [BlockchainController::class, 'createProductivityNFT'])->name('nft.achievement');
    Route::post('identity/verify', [BlockchainController::class, 'verifyDecentralizedIdentity'])->name('identity.verify');
    Route::post('tokens/distribute', [BlockchainController::class, 'createProductivityTokens'])->name('tokens.distribute');
    Route::post('governance/create-proposal', [BlockchainController::class, 'createGovernanceProposal'])->name('governance.proposal');
    Route::post('verify-productivity-data', [BlockchainController::class, 'verifyProductivityData'])->name('verify.data');
    Route::post('marketplace/create-listing', [BlockchainController::class, 'createMarketplaceListing'])->name('marketplace.listing');
});

// IoT Integration API Routes
Route::prefix('iot')->name('api.iot.')->middleware(['auth:sanctum', 'throttle:iot'])->group(function () {
    Route::post('smart-desk/integrate', [IoTController::class, 'integrateSmartDesk'])->name('desk.integrate');
    Route::post('lighting/integrate', [IoTController::class, 'integrateSmartLighting'])->name('lighting.integrate');
    Route::post('environmental/integrate', [IoTController::class, 'integrateEnvironmentalSensors'])->name('environmental.integrate');
    Route::post('wearables/integrate', [IoTController::class, 'integrateWearableDevices'])->name('wearables.integrate');
    Route::post('data/process', [IoTController::class, 'processIoTDataStream'])->name('data.process');
    Route::get('analytics', [IoTController::class, 'generateIoTAnalytics'])->name('analytics');
});

// Future Technologies API Routes
Route::prefix('future-tech')->name('api.future.')->middleware(['auth:sanctum', 'throttle:future'])->group(function () {
    Route::post('quantum/optimize-scheduling', [FutureTechController::class, 'optimizeSchedulingWithQuantum'])->name('quantum.scheduling');
    Route::post('ar/create-workspace', [FutureTechController::class, 'createARWorkspaceVisualization'])->name('ar.workspace');
    Route::post('vr/create-collaboration-space', [FutureTechController::class, 'createVRCollaborationSpace'])->name('vr.collaboration');
    Route::post('bci/integrate', [FutureTechController::class, 'integrateBrainComputerInterface'])->name('bci.integrate');
    Route::post('digital-twin/create', [FutureTechController::class, 'createProductivityDigitalTwin'])->name('digital.twin');
    Route::post('edge-ai/deploy', [FutureTechController::class, 'deployEdgeAI'])->name('edge.ai');
    Route::post('holographic/create-display', [FutureTechController::class, 'createHolographicDisplay'])->name('holographic.display');
});

// Native App API Routes
Route::prefix('native')->name('api.native.')->middleware(['throttle:api'])->group(function () {
    Route::get('status', [App\Http\Controllers\Api\NativeAppController::class, 'status'])->name('status');
    Route::post('test-notification', [App\Http\Controllers\Api\NativeAppController::class, 'testNotification'])->name('test.notification');
    Route::post('panda-break-notification', [App\Http\Controllers\Api\NativeAppController::class, 'pandaBreakNotification'])->name('panda.notification');
});

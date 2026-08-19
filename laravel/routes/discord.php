<?php

use App\Http\Controllers\DiscordInteractionController;
use App\Http\Middleware\VerifyDiscordSignature;
use Illuminate\Support\Facades\Route;

Route::post('/discord/interactions', DiscordInteractionController::class)
    ->middleware(VerifyDiscordSignature::class)
    ->name('discord.interactions');

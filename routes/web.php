<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\EventImageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/events-visual-1')->name('home');

// Original table listing (kept and de-bugged).
Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/data', [EventController::class, 'data'])->name('events.data');

// The two browse experiences.
Route::get('events-visual-1', [EventController::class, 'visualOne'])->name('events.visual1');
Route::get('events-visual-2', [EventController::class, 'visualTwo'])->name('events.visual2');

// Generated event poster artwork (local, deterministic, immutably cached).
Route::get('img/event-poster', EventImageController::class)->name('events.poster');

// JSON data feeds (loaded client-side so the large dataset stays off the Inertia payload).
Route::get('api/events/feed', [EventController::class, 'feed'])->name('events.feed');
Route::get('api/events/map', [EventController::class, 'map'])->name('events.map');

// Event detail + attendee registration.
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
Route::post('events/{event}/attendees', [EventController::class, 'storeAttendee'])->name('events.attendees.store');

Route::inertia('dashboard', 'Dashboard')->name('dashboard');

require __DIR__.'/settings.php';

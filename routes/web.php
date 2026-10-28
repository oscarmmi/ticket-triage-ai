<?php

use App\Http\Controllers\TicketDeskController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TicketDeskController::class, 'index'])->name('home');
Route::post('/tickets/{ticket}/classify', [TicketDeskController::class, 'classify'])->name('tickets.classify');
Route::patch('/tickets/{ticket}/accept', [TicketDeskController::class, 'accept'])->name('tickets.accept');

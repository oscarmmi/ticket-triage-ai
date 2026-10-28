<?php

use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

beforeEach(function () {
    $this->withoutVite();
    app()->instance(LlmClient::class, new FakeLlmClient);
})->in('Feature');

<?php

namespace App\Services\Llm;

interface LlmClient
{
    /**
     * Return a model response constrained by the supplied JSON schema.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $schema
     */
    public function complete(array $input, array $schema): LlmResponse;
}

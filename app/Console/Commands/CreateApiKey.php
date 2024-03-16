<?php

namespace App\Console\Commands;

use App\Rules\DbTextMaxLength;
use App\Rules\DbVarcharMaxLength;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Validator;

class CreateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:api-key';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an API Key';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $ownerEmail = $this->ask('Email of the owner of this API key');
        $name = $this->ask('Name of the API Key');
        $description = $this->ask('Description of the API Key');

        $this->alert('Set the Date of Expiration. Format: yyyy-mm-dd (2024-01-31). Leave as blank to not set an expiration (not recommended)');
        $expiresAt = $this->ask('Date of expiration');

        $data = [
            'owner_email' => $ownerEmail,
            'name' => $name,
            'description' => $description,
            'expiration' => $expiresAt,
        ];

        try {
            Validator::validate($data, [
                'owner_email' => ['required', 'email'],
                'name' => ['string', 'required', new DbVarcharMaxLength()],
                'description' => ['string', new DbTextMaxLength()],
                'expiration' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.date('Y-m-d')],
            ]);
        } catch (ValidationException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\WebhookPermission;
use App\Models\User;
use App\Rules\DbTextMaxLength;
use App\Rules\DbVarcharMaxLength;
use App\Services\ApiKeyManager;
use Carbon\Carbon;
use ConversionHelper;
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
    protected $signature = 'api_key:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an API Key';

    private ApiKeyManager $apiKeyService;

    public function __construct(ApiKeyManager $apiKeyService)
    {
        parent::__construct();
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userEmail = $this->ask('Email of the owner of this API key');
        $name = $this->ask('Name of the API Key');
        $description = $this->ask('Description of the API Key');

        $dateNow = now()->format('Y-m-d');
        $this->alert("Set the Date of Expiration. Format: yyyy-mm-dd (ex. $dateNow). Leave as blank to not set an expiration (not recommended)");
        $expiresAt = $this->ask('Date of expiration');

        $data = [
            'user_email' => $userEmail,
            'name' => $name,
            'description' => $description,
            'expires_at' => $expiresAt,
        ];

        try {
            Validator::validate($data, [
                'user_email' => ['required', 'email', 'exists:users,email'],
                'name' => ['string', 'required', new DbVarcharMaxLength()],
                'description' => ['nullable', 'string', new DbTextMaxLength()],
                'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.date('Y-m-d')],
            ]);
        } catch (ValidationException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $data['user_id'] = User::where('email', $data['user_email'])->firstOrFail()->id;
        $data['expires_at'] = Carbon::parse($data['expires_at'])->endOfDay();
        $apiKey = $this->apiKeyService->create(
            $data['name'],
            $data['user_id'],
            $data['description'],
            $data['expires_at'],

            // Change this as needed
            ConversionHelper::enumToArray(WebhookPermission::class)
        );
        $this->info($apiKey);
        $this->info('Un-hashed: '.$apiKey->rawKeyValue);

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ChatbotService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TestChatbotNlu extends Command
{
    protected $signature = 'test:chatbot-nlu {user_id?}';
    protected $description = 'Run a set of NLU tests against ChatbotService and print results';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $user = null;
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with id {$userId} not found.");
                return 1;
            }
        } else {
            $user = User::first();
            if (!$user) {
                $this->info('No user found, creating temporary test user...');
                $user = User::create([
                    'username' => 'testuser',
                    'email' => 'testuser@example.local',
                    'password' => Hash::make('password'),
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'role' => 'client',
                    'is_active' => true,
                ]);
            }
        }

        $this->info('Using user id: ' . $user->id . ' (' . $user->email . ')');

        $samples = [
            'hm mch dat ting?',
            'Tangina ano oras nyo?!',
            'how 2 bok apt tmrw',
            'bok apntmnt plsss',
            'ilang user ko na admn',
            'pwede ba magbook apntmnt bukas kasi kelangan ko notary',
            'gago',
            'what services do you offer',
            'magkano yan',
            'nasaan yung opisina',
        ];

        $service = app(ChatbotService::class);

        foreach ($samples as $s) {
            $this->line('---');
            $this->info('Input: ' . $s);
            try {
                $out = $service->interpretAndRespond($user->id, $s);
                $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {
                $this->error('Error: ' . $e->getMessage());
            }
        }

        $this->info('Done.');
        return 0;
    }
}

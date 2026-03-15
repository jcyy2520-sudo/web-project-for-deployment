<?php

namespace App\Console\Commands;

use App\Services\MLServiceClient;
use Illuminate\Console\Command;

class RetrainMLModel extends Command
{
    protected $signature = 'ml:retrain {--force : Force retraining even if model exists}';
    protected $description = 'Trigger ML model retraining via the ML microservice';

    public function handle(MLServiceClient $mlClient): int
    {
        $this->info('Checking ML service availability...');

        if (!$mlClient->isAvailable()) {
            $this->error('ML service is not running. Start it with: python ml-service/main.py');
            return Command::FAILURE;
        }

        $this->info('ML service is available. Checking data quality...');

        $quality = $mlClient->getDataQuality();
        $data = $quality['data'] ?? [];
        $total = $data['total_records'] ?? 0;
        $minRequired = $data['min_required'] ?? 500;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Records', $total],
                ['Min Required', $minRequired],
                ['Sufficient', $total >= $minRequired ? 'Yes' : 'No'],
                ['Completed', $data['status_breakdown']['completed'] ?? 0],
                ['Cancelled', $data['status_breakdown']['cancelled'] ?? 0],
                ['No-Show', $data['status_breakdown']['no_show'] ?? 0],
            ]
        );

        if ($total < $minRequired && !$this->option('force')) {
            $this->warn("Insufficient data ({$total}/{$minRequired}). Use --force to attempt anyway.");
            return Command::FAILURE;
        }

        $this->info('Triggering model training...');
        $this->output->write('Training');

        $result = $mlClient->train();

        $this->newLine();

        $resultData = $result['data'] ?? $result;
        $status = $resultData['status'] ?? 'unknown';

        if ($status === 'trained') {
            $this->info('Model trained successfully!');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Algorithm', $resultData['algorithm'] ?? 'unknown'],
                    ['ROC-AUC', $resultData['metrics']['roc_auc'] ?? 'N/A'],
                    ['Precision', $resultData['metrics']['precision'] ?? 'N/A'],
                    ['Recall', $resultData['metrics']['recall'] ?? 'N/A'],
                    ['F1 Score', $resultData['metrics']['f1'] ?? 'N/A'],
                    ['Training Samples', $resultData['training_samples'] ?? 'N/A'],
                    ['Duration', ($resultData['training_duration_seconds'] ?? 'N/A') . 's'],
                ]
            );
            return Command::SUCCESS;
        }

        if ($status === 'insufficient_data') {
            $this->warn('Insufficient data: ' . ($resultData['message'] ?? ''));
            return Command::FAILURE;
        }

        $this->error('Training failed: ' . ($resultData['message'] ?? 'Unknown error'));
        return Command::FAILURE;
    }
}

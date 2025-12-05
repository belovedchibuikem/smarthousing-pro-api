<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CreateStorageLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:create-link 
                            {--force : Force the operation to run when link already exists}
                            {--check : Only check if link exists without creating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a symbolic link from public/storage to storage/app/public';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $publicStoragePath = public_path('storage');
        $storageAppPublicPath = storage_path('app/public');

        // Check if link already exists
        if (is_link($publicStoragePath)) {
            $target = readlink($publicStoragePath);
            
            if ($this->option('check')) {
                $this->info('✓ Storage link already exists');
                $this->line("  Link: {$publicStoragePath}");
                $this->line("  Target: {$target}");
                return Command::SUCCESS;
            }

            if ($target === $storageAppPublicPath || $target === '../storage/app/public') {
                if (!$this->option('force')) {
                    $this->info('✓ Storage link already exists and points to the correct location.');
                    $this->line("  Link: {$publicStoragePath}");
                    $this->line("  Target: {$target}");
                    return Command::SUCCESS;
                }
                
                $this->warn('Removing existing link...');
                if (!unlink($publicStoragePath)) {
                    $this->error('Failed to remove existing link. Please check permissions.');
                    return Command::FAILURE;
                }
            } else {
                $this->warn('Existing link points to wrong location. Removing...');
                if (!unlink($publicStoragePath)) {
                    $this->error('Failed to remove existing link. Please check permissions.');
                    return Command::FAILURE;
                }
            }
        } elseif (is_dir($publicStoragePath) || is_file($publicStoragePath)) {
            if (!$this->option('force')) {
                $this->error('A file or directory already exists at public/storage.');
                $this->warn('Use --force to remove it and create the link.');
                return Command::FAILURE;
            }
            
            $this->warn('Removing existing file/directory...');
            if (is_dir($publicStoragePath)) {
                File::deleteDirectory($publicStoragePath);
            } else {
                unlink($publicStoragePath);
            }
        }

        // Ensure storage/app/public directory exists
        if (!File::exists($storageAppPublicPath)) {
            $this->info('Creating storage/app/public directory...');
            File::makeDirectory($storageAppPublicPath, 0755, true);
        }

        // Create the symbolic link
        $this->info('Creating symbolic link...');
        
        try {
            // Try relative path first (more portable)
            $relativePath = '../storage/app/public';
            $linkCreated = symlink($relativePath, $publicStoragePath);
            
            if (!$linkCreated) {
                // Fallback to absolute path
                $linkCreated = symlink($storageAppPublicPath, $publicStoragePath);
            }
            
            if (!$linkCreated) {
                $this->error('Failed to create symbolic link.');
                $this->error('Error: ' . error_get_last()['message'] ?? 'Unknown error');
                $this->newLine();
                $this->warn('Manual steps:');
                $this->line("  1. SSH into your server");
                $this->line("  2. Run: ln -s {$relativePath} {$publicStoragePath}");
                $this->line("  Or: ln -s {$storageAppPublicPath} {$publicStoragePath}");
                return Command::FAILURE;
            }
            
            $this->info('✓ Storage link created successfully!');
            $this->line("  Link: {$publicStoragePath}");
            $this->line("  Target: " . readlink($publicStoragePath));
            
            // Set permissions
            $this->info('Setting permissions...');
            chmod($publicStoragePath, 0755);
            chmod($storageAppPublicPath, 0755);
            
            $this->newLine();
            $this->info('✓ Storage symbolic link is ready!');
            $this->line('  Files stored in storage/app/public will be accessible via /storage/');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to create symbolic link: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Manual steps:');
            $this->line("  1. SSH into your server");
            $this->line("  2. Run: ln -s ../storage/app/public public/storage");
            return Command::FAILURE;
        }
    }
}

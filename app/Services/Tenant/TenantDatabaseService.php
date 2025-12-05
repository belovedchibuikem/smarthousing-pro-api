<?php

namespace App\Services\Tenant;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class TenantDatabaseService
{
    /**
     * Get the database prefix from environment or config
     */
    protected function getDatabasePrefix(): string
    {
        return env('TENANCY_DB_PREFIX', config('tenancy.database.prefix', ''));
    }

    /**
     * Get the full database name with prefix
     */
    public function getFullDatabaseName(string $baseName): string
    {
        $prefix = $this->getDatabasePrefix();
        return $prefix . $baseName;
    }

    /**
     * Create tenant database from SQL file
     */
    public function createDatabaseFromSql(Tenant $tenant): bool
    {
        $baseDatabaseName = $tenant->id . '_smart_housing';
        $databaseName = $this->getFullDatabaseName($baseDatabaseName);
        
        try {
            // Check if database already exists
            if ($this->databaseExists($databaseName)) {
                Log::warning('Tenant database already exists', [
                    'tenant_id' => $tenant->id,
                    'database_name' => $databaseName
                ]);
                return true; // Return true since database exists
            }

            // Create database
            DB::connection('mysql')->statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            Log::info('Tenant database created', [
                'tenant_id' => $tenant->id,
                'database_name' => $databaseName
            ]);

            // Read SQL file
            $sqlFilePath = database_path('sql/tenant_database_schema.sql');
            
            if (!File::exists($sqlFilePath)) {
                throw new \Exception("SQL file not found at: {$sqlFilePath}");
            }

            $sqlContent = File::get($sqlFilePath);
            
            // Replace placeholders if any
            $sqlContent = str_replace('{DATABASE_NAME}', $databaseName, $sqlContent);
            $sqlContent = str_replace('{TENANT_ID}', $tenant->id, $sqlContent);
            
            // Switch to tenant database connection
            Config::set('database.connections.tenant.database', $databaseName);
            DB::purge('tenant');
            DB::connection('tenant')->reconnect();
            
            // Execute SQL statements
            // Split by semicolon but preserve statements that span multiple lines
            $statements = $this->splitSqlStatements($sqlContent);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && !$this->isComment($statement)) {
                    try {
                        DB::connection('tenant')->unprepared($statement);
                    } catch (\Exception $e) {
                        Log::warning('Failed to execute SQL statement', [
                            'statement' => substr($statement, 0, 100) . '...',
                            'error' => $e->getMessage()
                        ]);
                        // Continue with next statement
                    }
                }
            }
            
            Log::info('Tenant database schema executed successfully', [
                'tenant_id' => $tenant->id,
                'database_name' => $databaseName
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create tenant database', [
                'tenant_id' => $tenant->id,
                'database_name' => $databaseName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Check if tenant database exists
     * @param string $databaseName - Can be base name (without prefix) or full name (with prefix)
     */
    public function databaseExists(string $databaseName): bool
    {
        try {
            // If database name doesn't start with prefix, add it
            $prefix = $this->getDatabasePrefix();
            if (!empty($prefix) && !str_starts_with($databaseName, $prefix)) {
                $databaseName = $this->getFullDatabaseName($databaseName);
            }

            $result = DB::connection('mysql')->select(
                "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?",
                [$databaseName]
            );
            return count($result) > 0;
        } catch (\Exception $e) {
            Log::error('Failed to check database existence', [
                'database_name' => $databaseName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Split SQL content into individual statements
     */
    private function splitSqlStatements(string $sqlContent): array
    {
        // Remove comments
        $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
        $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);
        
        // Split by semicolon, but preserve statements in functions/procedures
        $statements = [];
        $currentStatement = '';
        $inFunction = false;
        $delimiter = ';';
        
        $lines = explode("\n", $sqlContent);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Check for DELIMITER command
            if (preg_match('/^DELIMITER\s+(.+)$/i', $line, $matches)) {
                $delimiter = trim($matches[1]);
                continue;
            }
            
            $currentStatement .= $line . "\n";
            
            // Check if line ends with delimiter
            if (substr(rtrim($line), -strlen($delimiter)) === $delimiter) {
                $statement = trim($currentStatement);
                if (!empty($statement)) {
                    $statements[] = rtrim($statement, $delimiter);
                }
                $currentStatement = '';
            }
        }
        
        // Add remaining statement if any
        if (!empty(trim($currentStatement))) {
            $statements[] = trim($currentStatement);
        }
        
        return array_filter($statements, fn($stmt) => !empty(trim($stmt)));
    }
    
    /**
     * Check if line is a comment
     */
    private function isComment(string $line): bool
    {
        $trimmed = trim($line);
        return empty($trimmed) || 
               str_starts_with($trimmed, '--') || 
               str_starts_with($trimmed, '/*') ||
               str_starts_with($trimmed, '*');
    }
    
    /**
     * Create database connection for tenant
     * @param string $databaseName - Can be base name (without prefix) or full name (with prefix)
     */
    public function createDatabaseConnection(string $databaseName): void
    {
        // If database name doesn't start with prefix, add it
        $prefix = $this->getDatabasePrefix();
        if (!empty($prefix) && !str_starts_with($databaseName, $prefix)) {
            $databaseName = $this->getFullDatabaseName($databaseName);
        }

        Config::set('database.connections.tenant.database', $databaseName);
        DB::purge('tenant');
        DB::connection('tenant')->reconnect();
    }
}


<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class BlockchainWallet extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'network',
        'address',
        'private_key_encrypted',
        'mnemonic_encrypted',
        'balance',
        'is_active',
        'is_default',
        'notes',
        'last_synced_at',
        'created_by', // Track who created the wallet
    ];

    protected $casts = [
        'balance' => 'decimal:18',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'private_key_encrypted',
        'mnemonic_encrypted',
    ];

    /**
     * Get the decrypted private key
     * WARNING: Only decrypt when absolutely necessary and handle securely
     */
    public function getPrivateKey(): ?string
    {
        if (!$this->private_key_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->private_key_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set the encrypted private key
     */
    public function setPrivateKey(string $privateKey): void
    {
        $this->private_key_encrypted = Crypt::encryptString($privateKey);
    }

    /**
     * Get the decrypted mnemonic
     * WARNING: Only decrypt when absolutely necessary and handle securely
     */
    public function getMnemonic(): ?string
    {
        if (!$this->mnemonic_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->mnemonic_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set the encrypted mnemonic
     */
    public function setMnemonic(string $mnemonic): void
    {
        $this->mnemonic_encrypted = Crypt::encryptString($mnemonic);
    }

    /**
     * Get the default wallet for a network
     */
    public static function getDefaultForNetwork(string $network): ?self
    {
        return self::where('network', $network)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get active wallets for a network
     */
    public static function getActiveForNetwork(string $network): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('network', $network)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}


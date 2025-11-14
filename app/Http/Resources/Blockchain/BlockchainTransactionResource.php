<?php

namespace App\Http\Resources\Blockchain;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockchainTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hash' => $this->hash,
            'reference' => $this->reference,
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'confirmed_at' => $this->confirmed_at?->toDateTimeString(),
            'failed_at' => $this->failed_at?->toDateTimeString(),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->first_name . ' ' . $this->user->last_name,
                    'email' => $this->user->email,
                ];
            }),
        ];
    }
}

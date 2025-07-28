<?php

namespace App\Http\Resources\AdminShelter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class KeluargaCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($keluarga) {
                return new KeluargaResource($keluarga);
            }),
            'meta' => [
                'total_families' => $this->collection->count(),
                'status_distribution' => $this->getStatusDistribution(),
                'bank_account_stats' => $this->getBankAccountStats(),
                'phone_stats' => $this->getPhoneStats(),
            ],
        ];
    }

    /**
     * Get additional data for the collection
     */
    public function with(Request $request): array
    {
        return [
            'summary' => [
                'collection_generated_at' => now()->format('Y-m-d H:i:s'),
                'total_records' => $this->collection->count(),
                'api_version' => '1.0',
            ],
        ];
    }

    /**
     * Get distribution of parent status
     */
    private function getStatusDistribution(): array
    {
        $distribution = $this->collection->groupBy('status_ortu')
            ->map(function ($group) {
                return $group->count();
            });

        return [
            'yatim' => $distribution->get('yatim', 0),
            'piatu' => $distribution->get('piatu', 0),
            'yatim_piatu' => $distribution->get('yatim piatu', 0),
            'dhuafa' => $distribution->get('dhuafa', 0),
            'non_dhuafa' => $distribution->get('non dhuafa', 0),
        ];
    }

    /**
     * Get bank account statistics
     */
    private function getBankAccountStats(): array
    {
        $withBank = $this->collection->filter(function ($keluarga) {
            return !empty($keluarga->id_bank);
        })->count();

        $withoutBank = $this->collection->count() - $withBank;

        return [
            'with_bank_account' => $withBank,
            'without_bank_account' => $withoutBank,
            'percentage_with_bank' => $this->collection->count() > 0 
                ? round(($withBank / $this->collection->count()) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Get phone statistics
     */
    private function getPhoneStats(): array
    {
        $withPhone = $this->collection->filter(function ($keluarga) {
            return !empty($keluarga->no_tlp);
        })->count();

        $withoutPhone = $this->collection->count() - $withPhone;

        return [
            'with_phone' => $withPhone,
            'without_phone' => $withoutPhone,
            'percentage_with_phone' => $this->collection->count() > 0 
                ? round(($withPhone / $this->collection->count()) * 100, 2) 
                : 0,
        ];
    }
}
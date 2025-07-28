<?php

namespace App\Services\AdminShelter;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    public function uploadChildPhoto(UploadedFile $file, int $anakId): string
    {
        $folderPath = 'Anak/' . $anakId;
        $fileName = $file->getClientOriginalName();
        
        $file->storeAs($folderPath, $fileName, 'public');
        
        return $fileName;
    }

    public function deleteChildPhoto(int $anakId, string $fileName): bool
    {
        $filePath = 'Anak/' . $anakId . '/' . $fileName;
        
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->delete($filePath);
        }
        
        return true; // File doesn't exist, consider it deleted
    }

    public function updateChildPhoto(UploadedFile $file, int $anakId, ?string $oldFileName = null): string
    {
        // Delete old photo if exists
        if ($oldFileName) {
            $this->deleteChildPhoto($anakId, $oldFileName);
        }
        
        // Upload new photo
        return $this->uploadChildPhoto($file, $anakId);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesAttachment extends Model
{
    protected $table = 'sales_attachments';

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'file_name',
        'file_path',
        'file_url',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    protected $appends = [
        'url',
        'formatted_size',
        'uploaded_at',
    ];

    public function getUrlAttribute()
    {
        if (! empty($this->file_url)) {
            return $this->file_url;
        }

        return '';
    }

    public function getFormattedSizeAttribute()
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '0 KB';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 1).' '.($units[$i] ?? 'KB');
    }

    public function getUploadedAtAttribute()
    {
        return $this->created_at ? $this->created_at->format('H:i d/m/Y') : '';
    }
}

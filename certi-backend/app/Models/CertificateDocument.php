<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_id',
        'document_type',
        'file_path',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    /**
     * Relación con certificado
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /**
     * Scope para documentos por tipo
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('document_type', $type);
    }
}
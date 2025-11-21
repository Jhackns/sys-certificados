<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'file_path',
        'name_position',
        'date_position',
        'qr_position',
        'background_image_size',
        'template_styles',
        'activity_type',
        'status',
    ];

    protected $casts = [
        'name_position' => 'array',
        'date_position' => 'array',
        'qr_position' => 'array',
        'background_image_size' => 'array',
        'template_styles' => 'array',
    ];

    /**
     * Relación con certificados
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'id_template');
    }

    /**
     * Relación con empresa (si existe)
     */
    // public function company(): BelongsTo
    // {
    //     return $this->belongsTo(Company::class);
    // }

    /**
     * Scope para plantillas activas
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope para plantillas por tipo de actividad
     */
    public function scopeForActivityType($query, $type)
    {
        return $query->where('activity_type', $type);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'activity_id',
        'id_template',
        'signed_by',
        'nombre',
        'descripcion',
        'unique_code',
        'qr_url',
        'fecha_emision',
        'fecha_vencimiento',
        'issued_at',
        'status',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    /**
     * Relación con plantilla de certificado
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'id_template');
    }

    /**
     * Relación con usuario (receptor del certificado)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con actividad
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Relación con usuario que firmó (signed_by)
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    /**
     * Relación con validaciones
     */
    public function validations(): HasMany
    {
        return $this->hasMany(Validation::class);
    }

    /**
     * Relación con documentos
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CertificateDocument::class);
    }

    /**
     * Relación con envíos de correo
     */
    public function emailSends(): HasMany
    {
        return $this->hasMany(EmailSend::class);
    }

    /**
     * Scope para certificados por código único
     */
    public function scopeByCode($query, $code)
    {
        return $query->where('unique_code', $code);
    }
}
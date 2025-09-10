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
        'company_id',
        'user_id',
        'activity_id',
        'unique_code',
        'qr_url',
        'issued_at',
        'signed_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    /**
     * Relación con empresa
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
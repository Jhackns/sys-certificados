<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Certificate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ActivityService
{
    /**
     * Obtener todas las actividades con paginación
     */
    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return Activity::withCount('certificates')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Buscar actividades por criterios
     */
    public function search(array $criteria, int $perPage = 15): LengthAwarePaginator
    {
        $query = Activity::withCount('certificates');

        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Si existe una columna is_active, permitir filtrar por ella; de lo contrario, ignorar
        if (array_key_exists('is_active', $criteria) && Schema::hasColumn('activities', 'is_active')) {
            $query->where('is_active', (bool) $criteria['is_active']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Obtener actividad por ID
     */
    public function getById(int $id): ?Activity
    {
        return Activity::withCount('certificates')->find($id);
    }

    /**
     * Crear actividad
     */
    public function create(array $data): Activity
    {
        $activity = Activity::create($data);
        Log::info('Actividad creada', ['activity_id' => $activity->id]);
        return $activity->fresh();
    }

    /**
     * Actualizar actividad
     */
    public function update(Activity $activity, array $data): Activity
    {
        $activity->update($data);
        Log::info('Actividad actualizada', ['activity_id' => $activity->id]);
        return $activity->fresh();
    }

    /**
     * Eliminar actividad
     */
    public function delete(Activity $activity): bool
    {
        return (bool) $activity->delete();
    }

    /**
     * Alternar estado de actividad si existe la columna is_active; si no, no hace nada y retorna el modelo.
     */
    public function toggleStatus(Activity $activity, bool $status): Activity
    {
        if (Schema::hasColumn('activities', 'is_active')) {
            $activity->update(['is_active' => $status]);
        }
        return $activity->fresh();
    }

    /**
     * Obtener todas las actividades (sin paginación)
     */
    public function getAllActivities(): \Illuminate\Database\Eloquent\Collection
    {
        return Activity::withCount('certificates')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Certificados de una actividad
     */
    public function getCertificates(Activity $activity, int $perPage = 15): LengthAwarePaginator
    {
        return $activity->certificates()->orderByDesc('id')->paginate($perPage);
    }
}



<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyService
{
    /**
     * Obtener todas las empresas con paginación
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAll(int $perPage = 15)
    {
        return Company::with(['users', 'activities', 'certificates'])
            ->withCount(['users', 'activities', 'certificates'])
            ->paginate($perPage);
    }

    /**
     * Obtener empresa por ID
     *
     * @param int $id
     * @return Company|null
     */
    public function getById(int $id): ?Company
    {
        return Company::with(['users', 'activities', 'certificates', 'certificateTemplates'])
            ->withCount(['users', 'activities', 'certificates'])
            ->find($id);
    }

    /**
     * Crear nueva empresa
     *
     * @param array $data
     * @return Company
     */
    public function create(array $data): Company
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create($data);
            
            Log::info('Empresa creada exitosamente', ['company_id' => $company->id]);
            
            return $company;
        });
    }

    /**
     * Actualizar empresa
     *
     * @param Company $company
     * @param array $data
     * @return Company
     */
    public function update(Company $company, array $data): Company
    {
        return DB::transaction(function () use ($company, $data) {
            $company->update($data);
            
            Log::info('Empresa actualizada exitosamente', ['company_id' => $company->id]);
            
            return $company->fresh();
        });
    }

    /**
     * Eliminar empresa
     *
     * @param Company $company
     * @return bool
     */
    public function delete(Company $company): bool
    {
        return DB::transaction(function () use ($company) {
            // Verificar si tiene usuarios asociados
            if ($company->users()->count() > 0) {
                throw new \Exception('No se puede eliminar la empresa porque tiene usuarios asociados');
            }

            // Verificar si tiene certificados asociados
            if ($company->certificates()->count() > 0) {
                throw new \Exception('No se puede eliminar la empresa porque tiene certificados asociados');
            }

            $companyId = $company->id;
            $deleted = $company->delete();
            
            if ($deleted) {
                Log::info('Empresa eliminada exitosamente', ['company_id' => $companyId]);
            }
            
            return $deleted;
        });
    }

    /**
     * Buscar empresas por criterios
     *
     * @param array $criteria
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function search(array $criteria, int $perPage = 15)
    {
        $query = Company::with(['users', 'activities', 'certificates'])
            ->withCount(['users', 'activities', 'certificates']);

        if (isset($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('ruc', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($criteria['is_active'])) {
            $query->where('is_active', $criteria['is_active']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Activar/Desactivar empresa
     *
     * @param Company $company
     * @param bool $status
     * @return Company
     */
    public function toggleStatus(Company $company, bool $status): Company
    {
        $company->update(['is_active' => $status]);
        
        Log::info('Estado de empresa actualizado', [
            'company_id' => $company->id,
            'new_status' => $status
        ]);
        
        return $company->fresh();
    }
}
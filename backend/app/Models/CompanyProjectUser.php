<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyProjectUser extends Model
{
    use HasFactory;

    protected $table = 'company_project_users';

    protected $fillable = [
        'company_project_access_id',
        'user_id',
        'external_user_id',
        'external_username',
        'external_role',
        'status',
        'last_sso_at',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'last_sso_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Get the company project access.
     */
    public function companyProjectAccess()
    {
        return $this->belongsTo(CompanyProjectAccess::class);
    }

    /**
     * Get the user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who revoked this mapping.
     */
    public function revoker()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
